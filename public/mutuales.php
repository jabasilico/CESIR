<?php
ob_start();
session_start();
if (!($_SESSION['is_admin'] ?? false)) {
    header('Location: ../index.php'); exit;
}
require_once '../config/database.php';
require_once '../includes/header.php';

/* ---------- ALTA / MODIFICACIÓN ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id   = $_POST['id'] ?? null;
    $nom  = trim($_POST['nombre']);

    if (!$nom) die("El nombre es obligatorio.");

    if ($id) {
        $stmt = $conn->prepare("UPDATE cl_mutual SET d_mutual=? WHERE c_id=?");
        $stmt->bind_param('si', $nom, $id);
    } else {
        $stmt = $conn->prepare("INSERT INTO cl_mutual (d_mutual) VALUES (?)");
        $stmt->bind_param('s', $nom);
    }
    $stmt->execute();
    header("Location: mutuales.php"); exit;
}

/* ---------- SOFT-DELETE / REACTIVAR ---------- */
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $row = $conn->query("SELECT f_baja FROM cl_mutual WHERE c_id=$id")->fetch_assoc();
    if ($row) {
        $nuevaFecha = $row['f_baja'] ? null : date('Y-m-d');
        $conn->query("UPDATE cl_mutual SET f_baja = " . ($nuevaFecha ? "'$nuevaFecha'" : "NULL") . " WHERE c_id=$id");
    }
    header("Location: mutuales.php"); exit;
}

/* ---------- LISTADO ---------- */
$estadoFiltro = $_GET['estado'] ?? 'ACTIVO';
$sqlWhere     = ($estadoFiltro === 'ACTIVO') ? 'WHERE f_baja IS NULL' : '';
$mutuales = $conn->query("SELECT * FROM cl_mutual $sqlWhere ORDER BY d_mutual");

/* ---------- EDICIÓN ---------- */
$edit = null;
if (isset($_GET['edit'])) {
    $edit = $conn->query("SELECT * FROM cl_mutual WHERE c_id=".(int)$_GET['edit'])->fetch_assoc();
}
?>

<div class="container mt-4">
  <h3 class="titulo-resaltado">Administración de Mutuales</h3>

  <!-- Formulario -->
  <form method="POST" class="card card-body mb-4">
    <input type="hidden" name="id" value="<?= $edit['c_id'] ?? '' ?>">
    <div class="row g-3 align-items-end">
      <div class="col-md-8">
        <label class="form-label">Nombre de la Mutual</label>
        <input type="text" name="nombre" class="form-control" style="text-transform: uppercase;" value="<?= $edit['d_mutual'] ?? '' ?>" required>
      </div>
      <div class="col-md-4">
        <button type="submit" class="btn btn-success w-100"><?= $edit ? 'Actualizar' : 'Guardar' ?></button>
        <?php if ($edit): ?>
          <a href="mutuales.php" class="btn btn-secondary w-100 mt-1">Cancelar</a>
        <?php endif; ?>
      </div>
    </div>
  </form>

  <!-- Filtro -->
  <form method="get" class="row mb-3">
    <div class="col-md-3">
      <select name="estado" class="form-select" onchange="this.form.submit()">
        <option value="ACTIVO" <?= $estadoFiltro==='ACTIVO' ? 'selected' : '' ?>>Activas</option>
        <option value="TODOS"  <?= $estadoFiltro==='TODOS'  ? 'selected' : '' ?>>Todas</option>
      </select>
    </div>
  </form>

  <!-- Tabla -->
  <div class="table-responsive">
    <table class="table table-bordered table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>Mutual</th>
          <th>Estado</th>
          <th width="150">Acciones</th>
        </tr>
      </thead>
      <tbody>
      <?php while ($m = $mutuales->fetch_assoc()):
            $activo = is_null($m['f_baja']);
      ?>
        <tr>
          <td><?= htmlspecialchars($m['d_mutual']) ?></td>
          <td>
            <span class="badge <?= $activo ? 'bg-success' : 'bg-secondary' ?>">
              <?= $activo ? 'Activa' : 'Inactiva' ?>
            </span>
          </td>
          <td>
            <a href="?edit=<?= $m['c_id'] ?>" class="btn btn-sm btn-primary">Editar</a>
            <a href="?toggle=<?= $m['c_id'] ?>"
               class="btn btn-sm <?= $activo ? 'btn-danger' : 'btn-success' ?>"
               onclick="return confirm('¿<?= $activo ? 'Dar de baja' : 'Reactivar' ?>?')">
              <?= $activo ? 'Baja' : 'Reactivar' ?>
            </a>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once '../includes/footer.php';  ob_end_flush();?>