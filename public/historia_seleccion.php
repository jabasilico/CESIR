<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php'); exit;
}
require_once '../config/database.php';
require_once '../includes/header.php';

$q     = trim($_GET['q'] ?? '');
$like  = "%$q%";

$pacientes = $conn->prepare("
    SELECT p.c_id, p.d_apellido, p.d_nombre, p.n_dni, m.d_mutual, p.n_afiliado
    FROM cl_paciente p
    LEFT JOIN cl_mutual m ON m.c_id = p.c_id_mutual
    WHERE CONCAT_WS(' ', p.d_apellido, p.d_nombre, p.n_dni) LIKE ?
    ORDER BY p.d_apellido, p.d_nombre
");
$pacientes->bind_param('s', $like);
$pacientes->execute();
$result = $pacientes->get_result();
?>
<!doctype html>
<html lang="es">
<head>
  <title>Seleccionar Paciente – Historia</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php require_once '../includes/header.php'; ?>

<div class="container mt-4">
  <div class="header-obs">Seleccionar Paciente – Historia Médica</div>

  <!-- Buscador -->
  <input type="text" id="buscar" class="form-control mb-3" placeholder="Buscar…">

  <!-- Tabla -->
  <div class="table-responsive">
    <table class="table table-hover" id="tblPac">
      <thead class="table-light">
        <tr>
          <th>Apellido</th>
          <th>Nombre</th>
          <th>DNI</th>
          <th>Mutual</th>
          <th>Nº Afiliado</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($p = $result->fetch_assoc()): ?>
          <tr onclick="location.href='historia_detalle.php?id=<?= $p['c_id'] ?>'">
            <td><?= htmlspecialchars($p['d_apellido']) ?></td>
            <td><?= htmlspecialchars($p['d_nombre']) ?></td>
            <td><?= htmlspecialchars($p['n_dni']) ?></td>
            <td><?= htmlspecialchars($p['d_mutual'] ?? '-') ?></td>
            <td><?= htmlspecialchars($p['n_afiliado'] ?? '-') ?></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <!-- Script AJAX para filtrar -->
  <script>
    document.getElementById('buscar').addEventListener('input', () => {
      const val = document.getElementById('buscar').value.toLowerCase();
      [...document.querySelectorAll('#tblPac tbody tr')].forEach(tr => {
        tr.style.display = tr.textContent.toLowerCase().includes(val) ? '' : 'none';
    });
  });
  </script>
</div>

<?php require_once '../includes/footer.php'; ?>