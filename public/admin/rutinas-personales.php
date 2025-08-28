<?php
ob_start();
session_start();
if (!($_SESSION['is_admin'] ?? false)) {
    header('Location: ../index.php');
    exit;
}
require_once '../../config/database.php';
require_once '../../includes/header.php';

/* ---------- SELECCIÓN DE USUARIO ---------- */
$usuariosSel = $conn->query("SELECT c_id, d_nombre, d_Apellido FROM gy_usuario WHERE m_baja IS NULL ORDER BY d_Apellido, d_nombre");
$idUsuario   = (int)($_POST['usuario'] ?? $_GET['usuario'] ?? 0);

if (!$idUsuario && $usuariosSel->num_rows) {
    // Primera carga → mostrar selector
    ?>
    <div class="container-fluid mt-4">
      <h3>Rutinas personales</h3>
      <form method="POST" class="card card-body mb-4">
        <div class="row g-2">
          <div class="col-md-5">
            <label class="form-label">Seleccione un usuario</label>
            <select name="usuario" class="form-select" onchange="this.form.submit()">
              <option value="" disabled selected>Seleccione…</option>
              <?php while ($u = $usuariosSel->fetch_assoc()): ?>
                <option value="<?= $u['c_id'] ?>"><?= htmlspecialchars($u['d_Apellido'].', '.$u['d_nombre']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
        </div>
      </form>
    </div>
    <?php
    ob_end_flush();
    exit;
}

/* ---------- CLONAR RUTINA ESTÁNDAR ---------- */
if (isset($_GET['clone']) && isset($_GET['usuario'])) {
    $idUsuario = (int)$_GET['usuario'];
    $idOrigen  = (int)$_GET['clone'];

    // Copia cabecera
    $orig = $conn->query("SELECT d_nombre, d_objetivo FROM gy_rutina WHERE c_id = $idOrigen")->fetch_assoc();
    $nombre = 'Copia de ' . $orig['d_nombre'];
    $obj    = $orig['d_objetivo'];

    $stmt = $conn->prepare(
        "INSERT INTO gy_rutina_personal (c_id_usuario, d_nombre, d_objetivo, c_id_rutina_original, f_creacion)
         VALUES (?,?,?,?, CURDATE())"
    );
    $stmt->bind_param('issi', $idUsuario, $nombre, $obj, $idOrigen);
    $stmt->execute();
    $idNueva = $conn->insert_id;

    // Copia ejercicios
    $ej = $conn->query(
        "SELECT c_id_ejercicio, n_series, n_repeticiones, n_peso_sugerido, n_orden
         FROM gy_rutina_ejercicio
         WHERE c_id_rutina = $idOrigen"
    );
    while ($row = $ej->fetch_assoc()) {
        $stmt = $conn->prepare(
            "INSERT INTO gy_rutina_personal_ejercicio
             (c_id_rutina_personal, c_id_ejercicio, n_series, n_repeticiones, n_peso_sugerido, n_orden)
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

    header("Location: rutinas-personales.php?usuario=$idUsuario&edit=$idNueva");
    exit;
}

/* ---------- ALTA / EDICIÓN ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_personal'])) {
    $idPers   = $_POST['id_personal'] ?? null;
    $nombre   = trim($_POST['nombre']);
    $objetivo = trim($_POST['objetivo']);

    if ($idPers) {
        $stmt = $conn->prepare(
            "UPDATE gy_rutina_personal SET d_nombre = ?, d_objetivo = ? WHERE c_id = ? AND c_id_usuario = ?"
        );
        $stmt->bind_param('ssii', $nombre, $objetivo, $idPers, $idUsuario);
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO gy_rutina_personal (c_id_usuario, d_nombre, d_objetivo, f_creacion) VALUES (?,?,?, CURDATE())"
        );
        $stmt->bind_param('iss', $idUsuario, $nombre, $objetivo);
    }
    $stmt->execute();
    $idPers = $idPers ?: $conn->insert_id;

    // Borra ejercicios anteriores
    if ($idPers) {
        $conn->query("DELETE FROM gy_rutina_personal_ejercicio WHERE c_id_rutina_personal = $idPers");
    }

    // Guarda ejercicios
    $ejercicios = array_values($_POST['ejercicios'] ?? []);
    foreach ($ejercicios as $idx => $ej) {
        if (empty($ej['id'])) continue;
        $idEj  = (int)$ej['id'];
        $serie = (int)$ej['series'];
        $reps  = trim($ej['reps']);
        $peso  = (float)str_replace(',', '.', str_replace('.', '', $ej['peso']));
        $orden = $idx + 1;

        $stmtDet = $conn->prepare(
            "INSERT INTO gy_rutina_personal_ejercicio
             (c_id_rutina_personal, c_id_ejercicio, n_series, n_repeticiones, n_peso_sugerido, n_orden)
             VALUES (?,?,?,?,?,?)"
        );
        $stmtDet->bind_param('iiissi', $idPers, $idEj, $serie, $reps, $peso, $orden);
        $stmtDet->execute();
    }

    header("Location: rutinas-personales.php?usuario=$idUsuario");
    exit;
}

/* ---------- ELIMINAR ---------- */
if (isset($_GET['del'])) {
    $idPers = (int)$_GET['del'];
    $conn->query("DELETE FROM gy_rutina_personal WHERE c_id = $idPers AND c_id_usuario = $idUsuario");
    header("Location: rutinas-personales.php?usuario=$idUsuario");
    exit;
}

/* ---------- DATOS ---------- */
$usuario = $conn->query(
    "SELECT d_nombre, d_Apellido FROM gy_usuario WHERE c_id = $idUsuario"
)->fetch_assoc() ?: ['d_nombre' => '', 'd_Apellido' => ''];

$rutinasPers = $conn->query(
    "SELECT rp.*, COUNT(rpe.c_id) AS cant_ejercicios
     FROM gy_rutina_personal rp
     LEFT JOIN gy_rutina_personal_ejercicio rpe ON rp.c_id = rpe.c_id_rutina_personal
     WHERE rp.c_id_usuario = $idUsuario
     GROUP BY rp.c_id
     ORDER BY rp.d_nombre"
);

// Rutinas estándar para clonar
$rutinasStd = $conn->query(
    "SELECT r.c_id, r.d_nombre, COUNT(re.c_id) AS cant
     FROM gy_rutina r
     LEFT JOIN gy_rutina_ejercicio re ON r.c_id = re.c_id_rutina
     WHERE r.m_activo = 'S'
     GROUP BY r.c_id
     ORDER BY r.d_nombre"
);

$ejercicios = $conn->query(
    "SELECT e.c_id, e.d_nombre, g.d_grupo
     FROM gy_ejercicio e
     JOIN gy_grupo_muscular g ON e.c_id_grupo = g.c_id
     WHERE e.m_activo = 'S'
     ORDER BY e.d_nombre"
);

$edit = null;
if (isset($_GET['edit'])) {
    $edit = $conn->query(
        "SELECT * FROM gy_rutina_personal WHERE c_id = " . (int)$_GET['edit'] . " AND c_id_usuario = $idUsuario"
    )->fetch_assoc();

$editDet = [];
if ($edit) {
    $editDet = $conn->query(
        "SELECT rpe.*, e.d_nombre, g.d_grupo
         FROM gy_rutina_personal_ejercicio rpe
         JOIN gy_ejercicio e ON rpe.c_id_ejercicio = e.c_id
         JOIN gy_grupo_muscular g ON e.c_id_grupo = g.c_id
         WHERE rpe.c_id_rutina_personal = {$edit['c_id']}
         ORDER BY rpe.n_orden"
    )->fetch_all(MYSQLI_ASSOC);
}
}

?>

<div class="container-fluid mt-4">

  <!-- Selector de usuario -->
  <form method="POST" class="mb-4">
    <div class="row g-2">
      <div class="col-md-4">
        <label class="form-label">Usuario</label>
        <select name="usuario" class="form-select" onchange="this.form.submit()">
          <option value="" <?= $idUsuario ? '' : 'selected' ?>>Seleccione…</option>
          <?php $usuariosSel->data_seek(0); while ($u = $usuariosSel->fetch_assoc()): ?>
            <option value="<?= $u['c_id'] ?>" <?= $idUsuario == $u['c_id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($u['d_Apellido'].', '.$u['d_nombre']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
    </div>
  </form>

  <h3>Rutinas personales de <?= htmlspecialchars($usuario['d_Apellido'].', '.$usuario['d_nombre']) ?></h3>

  <?php if ($idUsuario): ?>

    <!-- Tabla de rutinas estándar para clonar -->
    <?php if ($rutinasStd->num_rows): ?>
      <h5>Rutinas estándar disponibles</h5>
      <table class="table table-sm table-bordered">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Ejercicios</th>
            <th>Acción</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($rs = $rutinasStd->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($rs['d_nombre']) ?></td>
              <td><?= $rs['cant'] ?></td>
              <td>
                <a href="rutinas-personales.php?usuario=<?= $idUsuario ?>&clone=<?= $rs['c_id'] ?>"
                   class="btn btn-sm btn-outline-info"
                   onclick="return confirm('¿Clonar «<?= htmlspecialchars($rs['d_nombre'], ENT_QUOTES) ?>» para <?= htmlspecialchars($usuario['d_nombre'], ENT_QUOTES) ?>?')">
                  Clonar
                </a>          
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="alert alert-info">No hay rutinas estándar activas.</div>
    <?php endif; ?>

    <!-- Listado de rutinas personales -->
    <h5>Rutinas personales</h5>

    <table class="table table-striped">
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Objetivo</th>
          <th>Ejercicios</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($rp = $rutinasPers->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($rp['d_nombre']) ?></td>
            <td><?= htmlspecialchars($rp['d_objetivo']) ?></td>
            <td><?= $rp['cant_ejercicios'] ?></td>
            <td>
              <a href="rutinas-personales.php?usuario=<?= $idUsuario ?>&edit=<?= $rp['c_id'] ?>" class="btn btn-sm btn-outline-warning">Editar</a>
              <a href="rutinas-personales.php?usuario=<?= $idUsuario ?>&del=<?= $rp['c_id'] ?>" class="btn btn-sm btn-outline-danger"
                 onclick="return confirm('¿Eliminar esta rutina personal?')">Eliminar</a>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>

    <!-- Formulario de alta / edición -->
    <form method="POST" class="card card-body mb-4" id="formPersonal">
      <input type="hidden" name="id_personal" value="<?= $edit['c_id'] ?? '' ?>">
      <input type="hidden" name="usuario" value="<?= $idUsuario ?>">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Nombre rutina</label>
          <input type="text" name="nombre" class="form-control" value="<?= $edit['d_nombre'] ?? '' ?>" required>
        </div>
        <div class="col-md-8">
          <label class="form-label">Objetivo / notas</label>
          <input type="text" name="objetivo" class="form-control" value="<?= $edit['d_objetivo'] ?? '' ?>">
        </div>
      </div>

      <hr>

      <h6>Ejercicios</h6>
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
            <input type="text" name="ejercicios[<?= $idx ?>][reps]" class="form-control" value="<?= $e['n_repeticiones'] ?? '10' ?>" style="width: 100px;">
            <input type="text" name="ejercicios[<?= $idx ?>][peso]" class="form-control peso-mask" value="<?= number_format($e['n_peso_sugerido'] ?? 0, 2, ',', '.') ?>" style="width: 100px;">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.parentElement.remove()">✕</button>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="col-12 mt-2">
        <button type="button" class="btn btn-outline-primary" onclick="agregarEjercicio()">+ Agregar ejercicio</button>
      </div>

      <div class="col-12 mt-3">
        <button type="submit" name="guardar_personal" class="btn btn-success">Guardar rutina</button>
        <?php if ($edit): ?>
          <a href="rutinas-personales.php?usuario=<?= $idUsuario ?>" class="btn btn-secondary ms-2">Cancelar</a>
        <?php endif; ?>
      </div>
    </form>
  <?php endif; ?>
</div>

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