<?php
ob_start();
session_start();
if (!($_SESSION['is_admin'] ?? false)) {
    header('Location: ../index.php');
    exit;
}
require_once '../../config/database.php';
require_once '../../includes/header.php';

/* ---------- ALTA / EDICIÓN RUTINA ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_rutina'])) {
    $id       = $_POST['id_rutina'] ?? null;
    $nombre   = trim($_POST['nombre']);
    $objetivo = trim($_POST['objetivo']);

    if ($id) {
        $stmt = $conn->prepare(
            "UPDATE gy_rutina SET d_nombre = ?, d_objetivo = ? WHERE c_id = ?"
        );
        $stmt->bind_param('ssi', $nombre, $objetivo, $id);
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO gy_rutina (d_nombre, d_objetivo) VALUES (?,?)"
        );
        $stmt->bind_param('ss', $nombre, $objetivo);
    }
    $stmt->execute();
    $idRutina = $conn->insert_id ?: $id;

    // Borra ejercicios anteriores si editamos
    if ($id) {
        $conn->query("DELETE FROM gy_rutina_ejercicio WHERE c_id_rutina = $idRutina");
    }

    // Guardamos ejercicios (clave string → int)
    $ejercicios = array_values($_POST['ejercicios'] ?? []);
    foreach ($ejercicios as $idx => $ej) {
        if (empty($ej['id'])) continue;

        $idEj   = (int)$ej['id'];
        $series = (int)$ej['series'];
        $reps   = trim($ej['reps']);
        $peso   = (float)str_replace(',', '.', str_replace('.', '', $ej['peso']));
        $orden  = $idx + 1;

        $stmtDet = $conn->prepare(
            "INSERT INTO gy_rutina_ejercicio
             (c_id_rutina, c_id_ejercicio, n_series, n_repeticiones, n_peso_sugerido, n_orden)
             VALUES (?,?,?,?,?,?)"
        );
        $stmtDet->bind_param('iiissi', $idRutina, $idEj, $series, $reps, $peso, $orden);
        $stmtDet->execute();
    }

    header("Location: rutinas.php");
    exit;
}

/* ---------- CLONAR RUTINA ---------- */
if (isset($_GET['clone'])) {
    $idOrigen = (int)$_GET['clone'];

    // Copia cabecera
    $orig = $conn->query("SELECT d_nombre, d_objetivo FROM gy_rutina WHERE c_id = $idOrigen")->fetch_assoc();
    $nombre = 'Copia de ' . $orig['d_nombre'];
    $obj    = $orig['d_objetivo'];

    $stmt = $conn->prepare("INSERT INTO gy_rutina (d_nombre, d_objetivo) VALUES (?,?)");
    $stmt->bind_param('ss', $nombre, $obj);
    $stmt->execute();
    $idNueva = $conn->insert_id;

    // Copia ejercicios
    $ej = $conn->query("SELECT c_id_ejercicio, n_series, n_repeticiones, n_peso_sugerido, n_orden
                        FROM gy_rutina_ejercicio
                        WHERE c_id_rutina = $idOrigen");
    while ($row = $ej->fetch_assoc()) {
        $stmt = $conn->prepare(
            "INSERT INTO gy_rutina_ejercicio
             (c_id_rutina, c_id_ejercicio, n_series, n_repeticiones, n_peso_sugerido, n_orden)
             VALUES (?,?,?,?,?,?)"
        );
        $stmt->bind_param('iiissi',
            $idNueva,
            $row['c_id_ejercicio'],
            $row['n_series'],
            $row['n_repeticiones'],
            $row['n_peso_sugerido'],
            $row['n_orden']
        );
        $stmt->execute();
    }

    header("Location: rutinas.php?edit=$idNueva");
    exit;
}

/* ---------- SOFT-TOGGLE ACTIVO ---------- */
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $conn->query("UPDATE gy_rutina SET m_activo = IF(m_activo='S','N','S') WHERE c_id = $id");
    header("Location: rutinas.php");
    exit;
}

/* ---------- DATOS PARA LA VISTA ---------- */
$grupos     = $conn->query("SELECT c_id, d_grupo FROM gy_grupo_muscular ORDER BY d_grupo");
$ejercicios = $conn->query(
    "SELECT e.c_id, e.d_nombre, g.d_grupo
     FROM gy_ejercicio e
     JOIN gy_grupo_muscular g ON e.c_id_grupo = g.c_id
     WHERE e.m_activo = 'S'
     ORDER BY e.d_nombre"
);

$rutinas = $conn->query(
    "SELECT r.*, COUNT(re.c_id) AS cant_ejercicios
     FROM gy_rutina r
     LEFT JOIN gy_rutina_ejercicio re ON r.c_id = re.c_id_rutina
     GROUP BY r.c_id
     ORDER BY r.d_nombre"
);

$edit = null;
if (isset($_GET['edit'])) {
    $edit = $conn->query(
        "SELECT * FROM gy_rutina WHERE c_id = " . (int)$_GET['edit']
    )->fetch_assoc();

    $editDet = $conn->query(
        "SELECT re.*, e.d_nombre, g.d_grupo
         FROM gy_rutina_ejercicio re
         JOIN gy_ejercicio e ON re.c_id_ejercicio = e.c_id
         JOIN gy_grupo_muscular g ON e.c_id_grupo = g.c_id
         WHERE re.c_id_rutina = {$edit['c_id']}
         ORDER BY re.n_orden"
    )->fetch_all(MYSQLI_ASSOC);
}
?>

<div class="container-fluid mt-4">
  <h3>Gestión de Rutinas</h3>

  <!-- Formulario Alta/Edición -->
  <form method="POST" class="card card-body mb-4" id="formRutina">
    <input type="hidden" name="id_rutina" value="<?= $edit['c_id'] ?? '' ?>">
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Nombre de la rutina</label>
        <input type="text" name="nombre" class="form-control" value="<?= $edit['d_nombre'] ?? '' ?>" required>
      </div>
      <div class="col-md-8">
        <label class="form-label">Objetivo / descripción</label>
        <input type="text" name="objetivo" class="form-control" value="<?= $edit['d_objetivo'] ?? '' ?>">
      </div>
    </div>

    <hr>

    <h6>Ejercicios de la rutina</h6>

    <!-- Encabezados -->
    <div class="row g-2 fw-bold text-left">
      <div class="col-4" style="width: 400px;">Ejercicio (Grupo)</div>
      <div class="col-2" style="width: 100px;">Series</div>
      <div class="col-2" style="width: 100px;">Reps</div>
      <div class="col-2" style="width: 100px;">Peso (kg)</div>
      <div class="col-1">Acción</div>
    </div>

    <div id="listaEjercicios" class="row g-2">
      <?php
      $det = $editDet ?? [];
      foreach ($det as $idx => $e):
      ?>
        <div class="col-12 d-flex gap-2 mb-2">
          <select name="ejercicios[<?= $idx ?>][id]" class="form-select" style="width: 400px;" required>
            <?php $ejercicios->data_seek(0); while ($ej = $ejercicios->fetch_assoc()): ?>
              <option value="<?= $ej['c_id'] ?>" <?= $ej['c_id'] == $e['c_id_ejercicio'] ? 'selected' : '' ?>>
                <?= $ej['d_nombre'] ?> (<?= $ej['d_grupo'] ?>)
              </option>
            <?php endwhile; ?>
          </select>
          <input type="number" name="ejercicios[<?= $idx ?>][series]" class="form-control" value="<?= $e['n_series'] ?? 3 ?>" min="1" style="width: 100px;">
          <input type="text" name="ejercicios[<?= $idx ?>][reps]" class="form-control" value="<?= $e['n_repeticiones'] ?? '10' ?>" placeholder="Reps" style="width: 100px;">
          <input type="text" name="ejercicios[<?= $idx ?>][peso]" class="form-control peso-mask" value="<?= number_format($e['n_peso_sugerido'] ?? 0, 2, ',', '.') ?>" placeholder="Kg" style="width: 100px;">
          <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.parentElement.remove()">✕</button>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="col-12 mt-2">
      <button type="button" class="btn btn-outline-primary" onclick="agregarEjercicio()">+ Agregar ejercicio</button>
    </div>

    <div class="col-12 mt-3">
      <button type="submit" name="guardar_rutina" class="btn btn-success">Guardar rutina</button>
      <?php if ($edit): ?>
        <a href="rutinas.php" class="btn btn-secondary ms-2">Cancelar</a>
      <?php endif; ?>
    </div>
  </form>

  <!-- Listado de rutinas -->
  <table class="table table-striped">
    <thead>
      <tr>
        <th>Nombre</th>
        <th>Objetivo</th>
        <th>Ejercicios</th>
        <th>Estado</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($r = $rutinas->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($r['d_nombre']) ?></td>
          <td><?= htmlspecialchars($r['d_objetivo']) ?></td>
          <td><?= $r['cant_ejercicios'] ?></td>
          <td><?= $r['m_activo'] === 'S' ? 'Activa' : 'Inactiva' ?></td>
          <td>
            <a href="?edit=<?= $r['c_id'] ?>" class="btn btn-sm btn-outline-warning">Editar</a>
            <a href="?clone=<?= $r['c_id'] ?>" class="btn btn-sm btn-outline-info"
              onclick="return confirm('¿Clonar esta rutina?')">
              <i class="bi bi-files"></i> Clonar
            </a>
            </a>
            <a href="?toggle=<?= $r['c_id'] ?>" class="btn btn-sm btn-outline-<?= $r['m_activo'] === 'S' ? 'danger' : 'success' ?>"
               onclick="return confirm('¿<?= $r['m_activo'] === 'S' ? 'Desactivar' : 'Activar' ?> esta rutina?')">
              <?= $r['m_activo'] === 'S' ? 'Desactivar' : 'Activar' ?>
            </a>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

<!-- Script para agregar filas dinámicas -->
<script>
function agregarEjercicio() {
  const container = document.getElementById('listaEjercicios');
  const idx = container.children.length;
  const row = document.createElement('div');
  row.className = 'col-12 d-flex gap-2 mb-2';
  row.innerHTML = `
    <select name="ejercicios[${idx}][id]" class="form-select" style="width: 400px;" required>
      <option value="" disabled selected>Seleccione…</option>
      <?php $ejercicios->data_seek(0); while ($ej = $ejercicios->fetch_assoc()): ?>
        <option value="<?= $ej['c_id'] ?>"><?= $ej['d_nombre'] ?> (<?= $ej['d_grupo'] ?>)</option>
      <?php endwhile; ?>
    </select>
    <input type="number" name="ejercicios[${idx}][series]" class="form-control" value="3" min="1" style="width: 100px;">
    <input type="text" name="ejercicios[${idx}][reps]" class="form-control" placeholder="Reps" style="width: 100px;">
    <input type="text" name="ejercicios[${idx}][peso]" class="form-control peso-mask" placeholder="Kg" style="width: 100px;">
    <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.parentElement.remove()">✕</button>
  `;
  container.appendChild(row);
}
</script>

<?php ob_end_flush(); ?>