<?php
session_start();
if (!($_SESSION['is_admin'] ?? false)) {
    header('Location: ../index.php'); exit;
}
require_once '../../config/database.php';
require_once '../../includes/header.php';

/*  Usuarios activos que cumplen en el dia de hoy */
$hoy = (new DateTime())->modify('-1 days');
$fin = (new DateTime())->modify('+1 days');

$stmt = $conn->prepare("
    SELECT c_id, d_nombre, d_Apellido, c_usuario, f_nacimiento, d_telefono
    FROM gy_usuario
    WHERE m_baja IS NULL
      AND DATE_FORMAT(f_nacimiento, '%m-%d') BETWEEN ? AND ?
");
$f1 = $hoy->format('m-d');
$f2 = $fin->format('m-d');
$stmt->bind_param('ss', $f1, $f2);
$stmt->execute();
$cumples = $stmt->get_result();
?>

<div class="container-fluid mt-4">
  <h3>Cumpleaños de hoy</h3>

  <?php if (!$cumples->num_rows): ?>
    <div class="alert alert-info">Nadie cumple hoy.</div>
  <?php else: ?>
    <table class="table table-striped align-middle">
      <thead>
        <tr>
          <th>Apellido y Nombre</th>
          <th>Fecha nac.</th>
          <th>Edad</th>
          <th>Teléfono</th>
          <th>Acción</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($u = $cumples->fetch_assoc()):
                $edad = date_diff(new DateTime($u['f_nacimiento']), new DateTime())->y;
                $msg  = "¡Feliz cumpleaños {$u['d_nombre']}! 🎉 Te deseamos un gran día.";
                $wa   = 'https://wa.me/' . $u['d_telefono'] . '?text=' . urlencode($msg);
        ?>
          <tr>
            <td><?= htmlspecialchars($u['d_Apellido'].', '.$u['d_nombre']) ?></td>
            <td><?= date('d/m', strtotime($u['f_nacimiento'])) ?></td>
            <td><?= $edad ?></td>
            <td><?= htmlspecialchars($u['d_telefono']) ?></td>
            <td>
              <a href="<?= $wa ?>" target="_blank" class="btn btn-success btn-sm">
                <i class="bi bi-whatsapp"></i> Felicitar
              </a>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>