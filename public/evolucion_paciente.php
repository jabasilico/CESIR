<?php
session_start();
/* Solo usuarios logueados */
if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit;
}
require_once '../config/database.php';
require_once '../includes/header.php';

/* ---------- VARIABLES ---------- */
$idEsp   = $_SESSION['id_especialista'] ?? 0;
$q       = trim($_GET['q'] ?? '');            // filtro instantáneo
$like    = "%$q%";

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
  <meta charset="utf-8">
  <title>Seleccionar Paciente para Evolución</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    .header-obs{background:linear-gradient(135deg,#4CAF50,#81C784);color:#fff;padding:1rem 1.5rem;font-size:1.4rem;font-weight:600;}
    tbody tr{cursor:pointer;}
  </style>
</head>
<body>
<?php require_once '../includes/header.php'; ?>

<div class="container mt-4">
  <div class="header-obs">Seleccionar Paciente para Evolución</div>

  <!-- Buscador instantáneo -->
  <div class="row mb-3">
    <div class="col-md-6">
      <input type="text" id="buscar" class="form-control" placeholder="Buscar por apellido, nombre o DNI...">
    </div>
  </div>

  <!-- Tabla de pacientes -->
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
          <tr onclick="location.href='evolucion_paciente_detalle.php?id=<?= $p['c_id'] ?>'">
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
</div>

<!-- Script AJAX -->
<script>
const input = document.getElementById('buscar');
const table = document.getElementById('tblPac');

input.addEventListener('input', () => {
  const val = input.value.toLowerCase();
  [...table.tBodies[0].rows].forEach(row => {
    const txt = row.textContent.toLowerCase();
    row.style.display = txt.includes(val) ? '' : 'none';
  });
});
</script>

<?php require_once '../includes/footer.php'; ?>