<?php
ob_start();
session_start();

if (!($_SESSION['is_admin'] ?? false)) {
    header('Location: ../index.php');
    exit;
}
require_once '../../config/database.php';
require_once '../../includes/header.php';

$dias = [
    '1' => 'Lunes',
    '2' => 'Martes',
    '3' => 'Miércoles',
    '4' => 'Jueves',
    '5' => 'Viernes',
    '6' => 'Sábado',
    '7' => 'Domingo',
];

/* ----------------------------------------------------------
   1.  CRUD de planes
---------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_plan'])) {
    $idUsuario = (int)($_POST['usuario'] ?? 0);
    $idPlan    = (int)($_POST['id_plan']   ?? 0);
    $fInicio   = $_POST['f_inicio']  ?? '';
    $fFin      = $_POST['f_fin']     ?? '';
    $desc      = trim($_POST['descripcion'] ?? '');

    if ($idUsuario < 1) {
        $_SESSION['error'] = 'Usuario inválido.';
        header('Location: asignar-rutina.php');
        exit;
    }

    if ($idPlan) {                       // UPDATE
        $stmt = $conn->prepare(
            "UPDATE gy_usuario_plan
               SET f_inicio = ?, f_fin = ?, descripcion = ?
             WHERE c_id = ? AND c_id_usuario = ?"
        );
        $stmt->bind_param('sssii', $fInicio, $fFin, $desc, $idPlan, $idUsuario);
    } else {                             // INSERT
        $stmt = $conn->prepare(
            "INSERT INTO gy_usuario_plan (c_id_usuario, f_inicio, f_fin, descripcion)
             VALUES (?,?,?,?)"
        );
        $stmt->bind_param('isss', $idUsuario, $fInicio, $fFin, $desc);
    }
    $stmt->execute();
    header("Location: asignar-rutina.php?usuario=$idUsuario");
    exit;
}

/* ----------------------------------------------------------
   2.  Guardar días de un plan
---------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar'])) {
    $idUsuario = (int)($_POST['usuario'] ?? 0);
    $idPlan    = (int)($_POST['plan']    ?? 0);

    if ($idUsuario < 1 || $idPlan < 1) {
        $_SESSION['error'] = 'Usuario o plan inválido.';
        header('Location: asignar-rutina.php');
        exit;
    }

    // Borrar asignaciones previas de este plan
    $stmt = $conn->prepare("DELETE FROM gy_usuario_dia_rutina          WHERE c_id_usuario = ? AND c_id_plan = ?");
    $stmt->bind_param('ii', $idUsuario, $idPlan);
    $stmt->execute();

    $stmt = $conn->prepare("DELETE FROM gy_usuario_dia_rutina_personal WHERE c_id_usuario = ? AND c_id_plan = ?");
    $stmt->bind_param('ii', $idUsuario, $idPlan);
    $stmt->execute();

    // Insertar nuevas
    foreach ($_POST['rutinas'] ?? [] as $dia => $valor) {
        if (empty($valor)) continue;
        [$tipo, $idRutina] = explode('_', $valor);
        $idRutina = (int)$idRutina;

        if ($tipo === 'std') {
            $ins = $conn->prepare(
                "INSERT INTO gy_usuario_dia_rutina
                 (c_id_usuario, c_id_plan, c_id_rutina, n_dia_semana)
                 VALUES (?,?,?,?)"
            );
            $ins->bind_param('iiii', $idUsuario, $idPlan, $idRutina, $dia);
        } else {
            $ins = $conn->prepare(
                "INSERT INTO gy_usuario_dia_rutina_personal
                 (c_id_usuario, c_id_plan, c_id_rutina_personal, n_dia_semana)
                 VALUES (?,?,?,?)"
            );
            $ins->bind_param('iiii', $idUsuario, $idPlan, $idRutina, $dia);
        }
        $ins->execute();
    }

    header("Location: asignar-rutina.php?usuario=$idUsuario&plan=$idPlan");
    exit;
}

/* ----------------------------------------------------------
   3.  Cargar listados
---------------------------------------------------------- */
$usuarios   = $conn->query(
    "SELECT c_id, d_nombre, d_Apellido
     FROM gy_usuario
     WHERE m_baja IS NULL
     ORDER BY d_Apellido, d_nombre"
);
$rutinasStd = $conn->query(
    "SELECT c_id, d_nombre
     FROM gy_rutina
     WHERE m_activo = 'S'
     ORDER BY d_nombre"
);

$idUsuario  = (int)($_GET['usuario'] ?? $_POST['usuario'] ?? 0);
$idPlan     = (int)($_GET['plan']    ?? $_POST['plan']    ?? 0);
$idPlanEdit = (int)($_GET['edit_plan'] ?? 0);

/* planes del usuario */
$planes = [];
if ($idUsuario) {
    $stmt = $conn->prepare(
        "SELECT c_id, f_inicio, f_fin, descripcion
         FROM gy_usuario_plan
         WHERE c_id_usuario = ?
         ORDER BY f_inicio DESC"
    );
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $planes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/* datos del plan que se está editando */
$planEdit = null;
if ($idPlanEdit) {
    $stmt = $conn->prepare(
        "SELECT * FROM gy_usuario_plan
         WHERE c_id = ? AND c_id_usuario = ?"
    );
    $stmt->bind_param('ii', $idPlanEdit, $idUsuario);
    $stmt->execute();
    $planEdit = $stmt->get_result()->fetch_assoc();
}

/* asignaciones actuales del plan $idPlan */
$asignadas = ['std' => [], 'per' => []];
if ($idUsuario && $idPlan) {
    // std
    $stmt = $conn->prepare(
        "SELECT n_dia_semana, c_id_rutina
         FROM gy_usuario_dia_rutina
         WHERE c_id_usuario = ? AND c_id_plan = ?"
    );
    $stmt->bind_param('ii', $idUsuario, $idPlan);
    $stmt->execute();
    foreach ($stmt->get_result() as $row) {
        $asignadas['std'][$row['n_dia_semana']] = $row['c_id_rutina'];
    }

    // personal
    $stmt = $conn->prepare(
        "SELECT n_dia_semana, c_id_rutina_personal
         FROM gy_usuario_dia_rutina_personal
         WHERE c_id_usuario = ? AND c_id_plan = ?"
    );
    $stmt->bind_param('ii', $idUsuario, $idPlan);
    $stmt->execute();
    foreach ($stmt->get_result() as $row) {
        $asignadas['per'][$row['n_dia_semana']] = $row['c_id_rutina_personal'];
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Asignar Rutinas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="m-4">

<h3>Asignar rutinas por día</h3>

<!-- Selector de usuario -->
<form method="POST" class="mb-4">
  <div class="row g-2">
    <div class="col-md-5">
      <label class="form-label">Usuario</label>
      <select name="usuario" class="form-select" onchange="this.form.submit()">
        <option value="">Seleccione…</option>
        <?php while ($u = $usuarios->fetch_assoc()): ?>
          <option value="<?= $u['c_id'] ?>" <?= $idUsuario == $u['c_id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($u['d_Apellido'].', '.$u['d_nombre']) ?>
          </option>
        <?php endwhile; ?>
      </select>
    </div>
  </div>
</form>

<?php if ($idUsuario): ?>
  <!-- Formulario Nuevo/Editar plan -->
  <div class="card mb-4">
    <div class="card-header fw-bold">
      <?= $planEdit ? 'Editar plan' : 'Nuevo plan' ?>
    </div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="usuario"    value="<?= $idUsuario ?>">
        <input type="hidden" name="id_plan"    value="<?= $planEdit['c_id'] ?? '' ?>">
        <div class="row g-2">
          <div class="col-md-3">
            <label class="form-label">Fecha inicio</label>
            <input type="date" name="f_inicio" class="form-control"
                   value="<?= $planEdit['f_inicio'] ?? '' ?>" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Fecha fin</label>
            <input type="date" name="f_fin" class="form-control"
                   value="<?= $planEdit['f_fin'] ?? '' ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Descripción</label>
            <input type="text" name="descripcion" class="form-control"
                   value="<?= htmlspecialchars($planEdit['descripcion'] ?? '') ?>">
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button type="submit" name="guardar_plan" class="btn btn-primary w-100">
              <?= $planEdit ? 'Actualizar' : 'Crear' ?>
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Listado de planes -->
  <div class="card mb-4">
    <div class="card-header fw-bold">Planes vigentes</div>
    <div class="card-body p-0">
      <table class="table table-sm mb-0">
        <thead>
          <tr>
            <th>Inicio</th><th>Fin</th><th>Descripción</th><th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($planes as $p): ?>
          <tr>
            <td><?= $p['f_inicio'] ?></td>
            <td><?= $p['f_fin'] ?></td>
            <td><?= htmlspecialchars($p['descripcion']) ?></td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-primary"
                 href="asignar-rutina.php?usuario=<?= $idUsuario ?>&edit_plan=<?= $p['c_id'] ?>">
                Editar
              </a>
              <a class="btn btn-sm btn-outline-success"
                 href="asignar-rutina.php?usuario=<?= $idUsuario ?>&plan=<?= $p['c_id'] ?>">
                Gestionar días
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php
/* ----------------------------------------------------------
   4.  Tabla de 7 días (solo si hay un plan seleccionado)
---------------------------------------------------------- */
if ($idUsuario && $idPlan):
  $rutinasPer = $conn->prepare(
      "SELECT c_id, d_nombre
       FROM gy_rutina_personal
       WHERE c_id_usuario = ?
       ORDER BY d_nombre"
  );
  $rutinasPer->bind_param('i', $idUsuario);
  $rutinasPer->execute();
  $rutinasPer = $rutinasPer->get_result();
?>
  <form method="POST">
    <input type="hidden" name="usuario" value="<?= $idUsuario ?>">
    <input type="hidden" name="plan"    value="<?= $idPlan ?>">

    <table class="table table-bordered table-striped">
      <thead>
        <tr>
          <th>Día</th>
          <th>Rutina asignada</th>
          <th>Acción</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($dias as $num => $nombre): ?>
          <tr>
            <td><?= $nombre ?></td>
            <td>
              <select name="rutinas[<?= $num ?>]" class="form-select">
                <option value="">Sin rutina</option>

                <optgroup label="Estándar">
                  <?php $rutinasStd->data_seek(0); while ($r = $rutinasStd->fetch_assoc()): ?>
                    <option value="std_<?= $r['c_id'] ?>" <?= ($asignadas['std'][$num] ?? null) == $r['c_id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($r['d_nombre']) ?> (Estándar)
                    </option>
                  <?php endwhile; ?>
                </optgroup>

                <?php if ($rutinasPer->num_rows): ?>
                  <optgroup label="Personal">
                    <?php $rutinasPer->data_seek(0); while ($p = $rutinasPer->fetch_assoc()): ?>
                      <option value="per_<?= $p['c_id'] ?>" <?= ($asignadas['per'][$num] ?? null) == $p['c_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['d_nombre']) ?> (Personal)
                      </option>
                    <?php endwhile; ?>
                  </optgroup>
                <?php endif; ?>
              </select>
            </td>
            <td>
              <?php
              $valorSelect = '';
              if (isset($asignadas['std'][$num])) $valorSelect = 'std_'.$asignadas['std'][$num];
              elseif (isset($asignadas['per'][$num])) $valorSelect = 'per_'.$asignadas['per'][$num];
              ?>
              <a href="asignar-rutina.php?usuario=<?= $idUsuario ?>&plan=<?= $idPlan ?>&ver=<?= $valorSelect ?>"
                 class="btn btn-sm btn-outline-info">
                Ver
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <button type="submit" name="guardar" class="btn btn-success">Guardar cambios</button>
  </form>

  <!-- Detalle de rutina (modal) -->
  <?php if (isset($_GET['ver']) && $_GET['ver'] !== ''): ?>
    <hr>
    <?php
    [$tipo, $id] = explode('_', $_GET['ver']);
    $id = (int)$id;
    if ($tipo === 'std') {
        $nombre = $conn->query("SELECT d_nombre FROM gy_rutina WHERE c_id = $id")->fetch_assoc()['d_nombre'] ?? '';
        $stmt = $conn->prepare(
          "SELECT e.d_nombre, g.d_grupo, re.n_series, re.n_repeticiones, re.n_peso_sugerido
           FROM gy_rutina_ejercicio re
           JOIN gy_ejercicio e ON re.c_id_ejercicio = e.c_id
           JOIN gy_grupo_muscular g ON e.c_id_grupo = g.c_id
           WHERE re.c_id_rutina = ?
           ORDER BY re.n_orden"
        );
    } else {
        $nombre = $conn->query("SELECT d_nombre FROM gy_rutina_personal WHERE c_id = $id")->fetch_assoc()['d_nombre'] ?? '';
        $stmt = $conn->prepare(
          "SELECT e.d_nombre, g.d_grupo, rpe.n_series, rpe.n_repeticiones, rpe.n_peso_sugerido
           FROM gy_rutina_personal_ejercicio rpe
           JOIN gy_ejercicio e ON rpe.c_id_ejercicio = e.c_id
           JOIN gy_grupo_muscular g ON e.c_id_grupo = g.c_id
           WHERE rpe.c_id_rutina_personal = ?
           ORDER BY rpe.n_orden"
        );
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $detalle = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    ?>
    <h4>Detalle de rutina: <?= htmlspecialchars($nombre) ?></h4>
    <table class="table table-sm table-striped">
      <thead>
        <tr>
          <th>Ejercicio</th><th>Grupo</th><th>Series</th><th>Reps</th><th>Peso (kg)</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($detalle as $e): ?>
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
  <?php endif; ?>

<?php else: ?>
  <div class="alert alert-info mt-4">Seleccione un usuario y un plan para continuar.</div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
</body>
</html>