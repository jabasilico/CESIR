<?php
ob_start();
session_start();
if (!($_SESSION['is_admin'] ?? false)) {
    header('Location: ../index.php');
    exit;
}
require_once '../../config/database.php';
require_once '../../includes/header.php';

/* ---------- ALTA / EDICIÓN ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id          = $_POST['id'] ?? null;
    $nombre      = trim($_POST['nombre']);
    $desc        = trim($_POST['descripcion']);
    $id_grupo    = (int)($_POST['id_grupo'] ?? 0);

    if ($id) {
        // Editar
        $stmt = $conn->prepare(
            "UPDATE gy_ejercicio 
             SET d_nombre = ?, d_descripcion = ?, c_id_grupo = ? 
             WHERE c_id = ?"
        );
        $stmt->bind_param('ssii', $nombre, $desc, $id_grupo, $id);
    } else {
        // Alta
        $stmt = $conn->prepare(
            "INSERT INTO gy_ejercicio (d_nombre, d_descripcion, c_id_grupo) 
             VALUES (?,?,?)"
        );
        $stmt->bind_param('ssi', $nombre, $desc, $id_grupo);
    }
    $stmt->execute();
    header("Location: ejercicios.php");
    exit;
}

/* ---------- SOFT-TOGGLE ACTIVO/INACTIVO ---------- */
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $conn->query(
        "UPDATE gy_ejercicio 
         SET m_activo = IF(m_activo='S','N','S') 
         WHERE c_id = $id"
    );
    header("Location: ejercicios.php");
    exit;
}

/* ---------- DATOS PARA LA VISTA ---------- */
$grupos = $conn->query("SELECT c_id, d_grupo FROM gy_grupo_muscular ORDER BY d_grupo");

$idGrupo = (int)($_GET['grupo'] ?? 0);
$sql = "
    SELECT e.*, g.d_grupo
    FROM gy_ejercicio e
    JOIN gy_grupo_muscular g ON e.c_id_grupo = g.c_id
    " . ($idGrupo ? "WHERE e.c_id_grupo = $idGrupo" : "") . "
    ORDER BY e.d_nombre";
$ejercicios = $conn->query($sql);

$edit = null;
if (isset($_GET['edit'])) {
    $edit = $conn->query(
        "SELECT * FROM gy_ejercicio WHERE c_id = " . (int)$_GET['edit']
    )->fetch_assoc();
}
?>

<div class="container-fluid mt-4">
  <h3>Gestión de Ejercicios</h3>

  <!-- Formulario Alta/Edición -->
  <form method="POST" class="card card-body mb-4">
    <input type="hidden" name="id" value="<?= $edit['c_id'] ?? '' ?>">
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Nombre</label>
        <input type="text" name="nombre" class="form-control" value="<?= $edit['d_nombre'] ?? '' ?>" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">Grupo muscular</label>
        <select name="id_grupo" class="form-select" required>
          <option value="">Seleccione…</option>
          <?php while ($g = $grupos->fetch_assoc()): ?>
            <option value="<?= $g['c_id'] ?>" <?= ($edit['c_id_grupo'] ?? '') == $g['c_id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($g['d_grupo']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="col-md-12">
        <label class="form-label">Descripción</label>
        <textarea name="descripcion" class="form-control" rows="2"><?= $edit['d_descripcion'] ?? '' ?></textarea>
      </div>
      <div class="col-12">
        <button class="btn btn-primary"><?= $edit ? 'Actualizar' : 'Guardar' ?></button>
        <?php if ($edit): ?>
          <a href="ejercicios.php" class="btn btn-secondary ms-2">Cancelar</a>
        <?php endif; ?>
      </div>
    </div>
  </form>

  <!-- Filtro por grupo-->
  <form method="GET" class="row g-2 mb-3">
    <div class="col-md-4">
      <label class="form-label">Filtrar por Grupo</label>
      <select name="grupo" class="form-select" onchange="this.form.submit()">
        <option value="">Todos</option>
        <?php
        $gruposFiltro = $conn->query("SELECT c_id, d_grupo FROM gy_grupo_muscular ORDER BY d_grupo");
        while ($g = $gruposFiltro->fetch_assoc()):
        ?>
          <option value="<?= $g['c_id'] ?>" <?= ($_GET['grupo'] ?? '') == $g['c_id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($g['d_grupo']) ?>
          </option>
        <?php endwhile; ?>
      </select>
    </div>
  </form>

  <script>
  document.addEventListener('DOMContentLoaded', function () {
    const grupoSel = document.querySelector('select[name="grupo"]');
    const table = document.querySelector('table tbody');
    if (!grupoSel || !table) return;

    grupoSel.addEventListener('change', function () {
      const grupoId = this.value;
      [...table.rows].forEach(row => {
          const grupo = row.dataset.grupo;
          row.style.display = (!grupoId || grupo === grupoId) ? '' : 'none';
      });
    });
  });
  </script>

  <!-- Tabla de ejercicios -->
  <table class="table table-striped align-middle">
    <thead>
      <tr>
        <th>Nombre</th>
        <th>Grupo</th>
        <th>Descripción</th>
        <th>Estado</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($e = $ejercicios->fetch_assoc()): ?>
        <tr data-grupo="<?= $e['c_id_grupo'] ?>">
        <tr class="<?= $e['m_activo'] === 'N' ? 'table-secondary text-decoration-line-through' : '' ?>">
          <td><?= htmlspecialchars($e['d_nombre']) ?></td>
          <td><?= htmlspecialchars($e['d_grupo']) ?></td>
          <td><?= nl2br(htmlspecialchars($e['d_descripcion'])) ?></td>
          <td><?= $e['m_activo'] === 'S' ? 'Activo' : 'Inactivo' ?></td>
          <td>
            <a href="?edit=<?= $e['c_id'] ?>" class="btn btn-sm btn-outline-warning">Editar</a>
            <a href="?toggle=<?= $e['c_id'] ?>" class="btn btn-sm btn-outline-<?= $e['m_activo'] === 'S' ? 'danger' : 'success' ?>"
               onclick="return confirm('¿<?= $e['m_activo'] === 'S' ? 'Desactivar' : 'Activar' ?> este ejercicio?')">
              <?= $e['m_activo'] === 'S' ? 'Desactivar' : 'Activar' ?>
            </a>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

<?php require_once '../../includes/footer.php'; ob_end_flush(); ?>