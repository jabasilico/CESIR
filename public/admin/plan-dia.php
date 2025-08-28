<?php
ob_start();
session_start();
if (!($_SESSION['is_admin'] ?? false)) {
    header('Location: ../index.php');
    exit;
}
require_once '../../config/database.php';
require_once '../../includes/header.php';

$idDia = (int)($_GET['dia'] ?? 0);
$idPlan = (int)($_GET['plan'] ?? 0);

$dia = $conn->query("SELECT * FROM gy_plan_dia WHERE c_id = $idDia")->fetch_assoc();
if (!$dia) {
    header("Location: plan-rutinas.php");
    exit;
}

$rutinasStd = $conn->query("SELECT c_id, d_nombre FROM gy_rutina WHERE m_activo = 'S'");
$idUsuarioPlan = (int)$conn->query("SELECT c_id_usuario FROM gy_plan_rutina WHERE c_id = $idPlan")->fetch_assoc()['c_id_usuario'];

$rutinasStd = $conn->query("SELECT c_id, d_nombre FROM gy_rutina WHERE m_activo = 'S'");
$rutinasPer = $conn->query("SELECT c_id, d_nombre FROM gy_rutina_personal WHERE c_id_usuario = $idUsuarioPlan");

?>
<div class="container-fluid mt-4">
  <h4>Editar día <?= $dia['f_fecha'] ?></h4>
  <form method="POST" action="plan-dia.php?dia=<?= $idDia ?>">
    <input type="hidden" name="id_plan" value="<?= $idPlan ?>">
    <div class="row g-3">
      <div class="col-md-6">
        <label>Rutina estándar</label>
        <select name="rutina_std" class="form-select">
          <option value="">Sin estándar</option>
          <?php while ($r = $rutinasStd->fetch_assoc()): ?>
            <option value="<?= $r['c_id'] ?>" <?= $dia['c_id_rutina_std'] == $r['c_id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($r['d_nombre']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label>Rutina personal</label>
        <select name="rutina_per" class="form-select">
          <option value="">Sin personal</option>
          <?php while ($p = $rutinasPer->fetch_assoc()): ?>
            <option value="<?= $p['c_id'] ?>" <?= $dia['c_id_rutina_per'] == $p['c_id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($p['d_nombre']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
    </div>
    <button type="submit" name="guardar_dia" class="btn btn-primary mt-2">Guardar día</button>
  </form>
</div>
<?php require_once '../../includes/footer.php'; ?>