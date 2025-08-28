<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once '../config/database.php';
require_once '../includes/header.php';

$idUsuario = (int)$_SESSION['user_id'];

/* planes ordenados: primero el activo, luego los demás */
$stmt = $conn->prepare(
    "SELECT c_id, f_inicio, f_fin, descripcion,
            CASE WHEN CURDATE() BETWEEN f_inicio AND f_fin THEN 1 ELSE 0 END AS activo
     FROM gy_usuario_plan
     WHERE c_id_usuario = ?
     ORDER BY activo DESC, f_inicio DESC"
);
$stmt->bind_param('i', $idUsuario);
$stmt->execute();
$planes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<div class="container-fluid py-4">
  <h3>Mis planes de entrenamiento</h3>

  <?php foreach ($planes as $plan): ?>
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>
          <strong><?= htmlspecialchars($plan['descripcion']) ?></strong>
          (<?= $plan['f_inicio'] ?> → <?= $plan['f_fin'] ?>)
          <?php if ($plan['activo']): ?>
            <span class="badge bg-success ms-2">ACTIVO</span>
          <?php endif; ?>
        </span>
        <a href="mis-planes.php?plan=<?= $plan['c_id'] ?>"
           class="btn btn-sm btn-primary">
          Ver rutina
        </a>
      </div>

      <?php if (isset($_GET['plan']) && (int)$_GET['plan'] === (int)$plan['c_id']): ?>
        <div class="card-body">
          <?php
          /* =======  días con rutina  ======= */
          $dias = ['1'=>'Lunes','2'=>'Martes','3'=>'Miércoles',
                   '4'=>'Jueves','5'=>'Viernes','6'=>'Sábado','7'=>'Domingo'];

          // rutinas estándar
          $stmt = $conn->prepare(
              "SELECT n_dia_semana, c_id_rutina
               FROM gy_usuario_dia_rutina
               WHERE c_id_usuario = ? AND c_id_plan = ?"
          );
          $stmt->bind_param('ii', $idUsuario, $plan['c_id']);
          $stmt->execute();
          $std = [];
          foreach ($stmt->get_result() as $row) $std[$row['n_dia_semana']] = $row['c_id_rutina'];

          // rutinas personal
          $stmt = $conn->prepare(
              "SELECT n_dia_semana, c_id_rutina_personal
               FROM gy_usuario_dia_rutina_personal
               WHERE c_id_usuario = ? AND c_id_plan = ?"
          );
          $stmt->bind_param('ii', $idUsuario, $plan['c_id']);
          $stmt->execute();
          $per = [];
          foreach ($stmt->get_result() as $row) $per[$row['n_dia_semana']] = $row['c_id_rutina_personal'];

          // recorremos los 7 días
          foreach ($dias as $num => $nombreDia):
              $idRutina = null;
              $tipo     = null;

              if (isset($std[$num]))  { $idRutina = $std[$num];  $tipo = 'std'; }
              elseif (isset($per[$num])) { $idRutina = $per[$num]; $tipo = 'per'; }

              if (!$idRutina):
                  echo "<p class='mb-1'><strong>$nombreDia:</strong> descanso.</p>";
                  continue;
              endif;

              // nombre de la rutina
              if ($tipo === 'std') {
                  $r = $conn->query("SELECT d_nombre FROM gy_rutina WHERE c_id = $idRutina")->fetch_assoc();
              } else {
                  $r = $conn->query("SELECT d_nombre FROM gy_rutina_personal WHERE c_id = $idRutina")->fetch_assoc();
              }
              $nombreRutina = $r['d_nombre'] ?? 'Sin nombre';
              ?>
              <div class="mb-3">
                <h6><?= "$nombreDia: ".htmlspecialchars($nombreRutina) ?></h6>

                <?php
                // ejercicios
                if ($tipo === 'std') {
                    $stmt = $conn->prepare(
                        "SELECT e.d_nombre, g.d_grupo, re.n_series, re.n_repeticiones, re.n_peso_sugerido
                         FROM gy_rutina_ejercicio re
                         JOIN gy_ejercicio e ON re.c_id_ejercicio = e.c_id
                         JOIN gy_grupo_muscular g ON e.c_id_grupo = g.c_id
                         WHERE re.c_id_rutina = ?
                         ORDER BY re.n_orden"
                    );
                } else {
                    $stmt = $conn->prepare(
                        "SELECT e.d_nombre, g.d_grupo, rpe.n_series, rpe.n_repeticiones, rpe.n_peso_sugerido
                         FROM gy_rutina_personal_ejercicio rpe
                         JOIN gy_ejercicio e ON rpe.c_id_ejercicio = e.c_id
                         JOIN gy_grupo_muscular g ON e.c_id_grupo = g.c_id
                         WHERE rpe.c_id_rutina_personal = ?
                         ORDER BY rpe.n_orden"
                    );
                }
                $stmt->bind_param('i', $idRutina);
                $stmt->execute();
                $ejercicios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                ?>
                <table class="table table-sm table-striped">
                  <thead>
                    <tr>
                      <th>Ejercicio</th><th>Grupo</th><th>Series</th>
                      <th>Reps</th><th>Peso (kg)</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($ejercicios as $e): ?>
                      <tr>
                        <td><?= htmlspecialchars($e['d_nombre']) ?></td>
                        <td><?= htmlspecialchars($e['d_grupo']) ?></td>
                        <td><?= $e['n_series'] ?></td>
                        <td><?= htmlspecialchars($e['n_repeticiones']) ?></td>
                        <td><?= $e['n_peso_sugerido'] ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<?php require_once '../includes/footer.php'; ?>