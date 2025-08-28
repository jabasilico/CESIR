<?php
ob_start();
session_start();
if (!($_SESSION['is_admin'] ?? false)) {
    header('Location: ../index.php');
    exit;
}
require_once '../../config/database.php';
require_once '../../includes/header.php';

$diasSem = ['1' => 'Lunes', '2' => 'Martes', '3' => 'Miércoles',
            '4' => 'Jueves', '5' => 'Viernes', '6' => 'Sábado', '7' => 'Domingo'];

/* ---------- ALTA / EDICIÓN PLAN ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_plan'])) {
    $idPlan   = $_POST['id_plan'] ?? null;
    $idUser   = (int)$_POST['id_usuario'];
    $inicio   = $_POST['f_inicio'];
    $fin      = $_POST['f_fin'];
    $descr    = trim($_POST['descripcion']);

    if ($idPlan) {
        $stmt = $conn->prepare("UPDATE gy_plan_rutina SET f_inicio=?, f_fin=?, d_descripcion=? WHERE c_id=?");
        $stmt->bind_param('sssi', $inicio, $fin, $descr, $idPlan);
    } else {
        $stmt = $conn->prepare("INSERT INTO gy_plan_rutina (c_id_usuario, f_inicio, f_fin, d_descripcion) VALUES (?,?,?,?)");
        $stmt->bind_param('isss', $idUser, $inicio, $fin, $descr);
    }
    $stmt->execute();
    $idPlan = $idPlan ?: $conn->insert_id;

    // Borra días anteriores si editamos
    if ($idPlan) $conn->query("DELETE FROM gy_plan_dia WHERE c_id_plan = $idPlan");

    // Llenar días
    $ini = new DateTime($inicio);
    $fin = new DateTime($fin);
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($ini, $interval, $fin->modify('+1 day'));
    foreach ($period as $date) {
        $diaSem = (int)$date->format('N');
        $fFecha = $date->format('Y-m-d');
        $idStd  = $_POST['rutina_std'][$date->format('Y-m-d')] ?? null;
        $idPer  = $_POST['rutina_per'][$date->format('Y-m-d')] ?? null;

        $stmt = $conn->prepare("INSERT INTO gy_plan_dia (c_id_plan, n_dia_semana, f_fecha, c_id_rutina_std, c_id_rutina_per)
                                VALUES (?,?,?,?,?)");
        $stmt->bind_param('iisii', $idPlan, $diaSem, $fFecha, $idStd, $idPer);
        $stmt->execute();
    }

    header("Location: plan-rutinas.php?usuario=$idUser");
    exit;
}

/* ---------- ELIMINAR PLAN ---------- */
if (isset($_GET['del'])) {
    $idPlan = (int)$_GET['del'];
    $conn->query("DELETE FROM gy_plan_rutina WHERE c_id = $idPlan");
    header("Location: plan-rutinas.php");
    exit;
}

/* ---------- DATOS ---------- */
$usuarios   = $conn->query("SELECT c_id, d_nombre, d_Apellido FROM gy_usuario WHERE m_baja IS NULL ORDER BY d_Apellido, d_nombre");

$idUsuario = (int)($_GET['usuario'] ?? 0);
$planes = [];
if ($idUsuario) {
    $planes = $conn->query(
        "SELECT pr.*, COUNT(pd.c_id) AS cant_dias
         FROM gy_plan_rutina pr
         LEFT JOIN gy_plan_dia pd ON pr.c_id = pd.c_id_plan
         WHERE pr.c_id_usuario = $idUsuario
         GROUP BY pr.c_id
         ORDER BY pr.f_inicio DESC"
    )->fetch_all(MYSQLI_ASSOC);
}
?>
<div class="container-fluid mt-4">
  <h3>Planes de rutinas</h3>

  <!-- Selector de usuario -->
  <form method="GET" class="mb-4">
    <div class="row g-2">
      <div class="col-md-5">
        <label class="form-label">Usuario</label>
        <select name="usuario" class="form-select" onchange="this.form.submit()">
          <option value="" <?= $idUsuario ? '' : 'selected' ?>>Seleccione…</option>
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
    <!-- Formulario nuevo / editar -->
    <div class="card card-body mb-4">
      <h5><?= isset($_GET['edit']) ? 'Editar plan' : 'Nuevo plan' ?></h5>
      <form method="POST">
        <input type="hidden" name="id_plan" value="<?= $_GET['edit'] ?? '' ?>">
        <input type="hidden" name="id_usuario" value="<?= $idUsuario ?>">
        <div class="row g-3">
          <div class="col-md-4">
            <label>Fecha inicio</label>
            <input type="date" name="f_inicio" class="form-control" value="<?= isset($planes[0]['f_inicio']) ? $planes[0]['f_inicio'] : '' ?>" required>
          </div>
          <div class="col-md-4">
            <label>Fecha fin</label>
            <input type="date" name="f_fin" class="form-control" value="<?= isset($planes[0]['f_fin']) ? $planes[0]['f_fin'] : '' ?>" required>
          </div>
          <div class="col-md-4">
            <label>Descripción</label>
            <input type="text" name="descripcion" class="form-control" value="<?= isset($planes[0]['d_descripcion']) ? htmlspecialchars($planes[0]['d_descripcion']) : '' ?>">
          </div>
        </div>
        <button type="submit" name="guardar_plan" class="btn btn-primary mt-2">Guardar plan</button>
      </form>
    </div>

    <!-- Listado de planes -->
    <table class="table table-striped">
      <thead>
        <tr>
          <th>Inicio</th>
          <th>Fin</th>
          <th>Descripción</th>
          <th>Días</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($planes as $plan): ?>
        <tr>
            <td><?= htmlspecialchars($plan['f_inicio']) ?></td>
            <td><?= htmlspecialchars($plan['f_fin']) ?></td>
            <td><?= htmlspecialchars($plan['d_descripcion']) ?></td>
            <td><?= $plan['cant_dias'] ?></td>
            <td>
            <a href="plan-rutinas.php?usuario=<?= $idUsuario ?>&edit=<?= $plan['c_id'] ?>" class="btn btn-sm btn-outline-warning">Editar</a>
            <a href="plan-rutinas.php?del=<?= $plan['c_id'] ?>" class="btn btn-sm btn-outline-danger">Eliminar</a>
            </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- -->
    <?php if (isset($_GET['plan'])): ?>
    <?php
    $idPlan = (int)$_GET['plan'];
        $plan = $conn->query("SELECT * FROM gy_plan_rutina WHERE c_id = $idPlan")->fetch_assoc();
        $dias = $conn->query(
            "SELECT pd.*, e_std.d_nombre AS rutina_std, e_per.d_nombre AS rutina_per
            FROM gy_plan_dia pd
            LEFT JOIN gy_rutina e_std          ON pd.c_id_rutina_std = e_std.c_id
            LEFT JOIN gy_rutina_personal e_per ON pd.c_id_rutina_per = e_per.c_id
            WHERE pd.c_id_plan = $idPlan
            ORDER BY pd.f_fecha"
        )->fetch_all(MYSQLI_ASSOC);
    ?>
    <hr>
    <h4>Plan del <?= $plan['f_inicio'] ?> al <?= $plan['f_fin'] ?></h4>
    <table class="table table-sm table-striped">
        <thead>
        <tr>
            <th>Fecha</th>
            <th>Día</th>
            <th>Rutina</th>
            <th>Acción</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($dias as $dia): ?>
            <tr>
            <td><?= $dia['f_fecha'] ?></td>
            <td><?= $diasSem[$dia['n_dia_semana']] ?? '' ?></td>
            <td>
                <?= $dia['rutina_std'] ? 'Estándar: '.$dia['rutina_std'] : '' ?>
                <?= $dia['rutina_per'] ? 'Personal: '.$dia['rutina_per'] : '' ?>
            </td>
            <td>
                <a href="plan-dia.php?plan=<?= $idPlan ?>&dia=<?= $dia['c_id'] ?>" class="btn btn-sm btn-outline-warning">Editar</a>
            </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>   
     
  <?php else: ?>
    <div class="alert alert-info">Seleccione un usuario para ver sus planes.</div>
  <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>