<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}
require_once '../config/database.php';
require_once '../includes/header.php';

$idUsuario = $_SESSION['user_id'];

// Historial del usuario logueado
$historial = $conn->query("SELECT p.f_pago, p.i_pago, c.d_mes, c.n_anio
                           FROM gy_pago p
                           JOIN gy_calendario c ON p.c_id_calendario = c.c_id
                           WHERE p.c_id_usuario = $idUsuario
                           ORDER BY c.n_anio DESC, c.n_mes DESC");
?>

<div class="container-fluid mt-4">
  <h3>Mis pagos</h3>

  <?php if (!$historial->num_rows): ?>
    <div class="alert alert-info">No hay pagos registrados.</div>
  <?php else: ?>
    <table class="table table-striped">
      <thead>
        <tr>
          <th>Mes-Año</th>
          <th>Fecha</th>
          <th>Monto</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($p = $historial->fetch_assoc()): ?>
          <tr>
            <td><?= $p['d_mes'].' '.$p['n_anio'] ?></td>
            <td><?= date('d/m/Y', strtotime($p['f_pago'])) ?></td>
            <td>$<?= number_format($p['i_pago'], 0, ',', '.') ?></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>