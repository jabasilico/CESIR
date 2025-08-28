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
    $idEsp          = $_POST['id'] ?? null;
    $apellido       = trim($_POST['apellido']);
    $nombre         = trim($_POST['nombre']);
    $usuario        = trim($_POST['usuario']);
    $idEspecialidad = (int)($_POST['c_id_especialidad'] ?? 0);
    $matricula      = trim($_POST['n_matricula']);
    $celular        = trim($_POST['d_celular']);

    if (!$apellido || !$nombre || !$usuario || !$idEspecialidad) {
        die("Todos los campos son obligatorios.");
    }

    if ($idEsp) {
        // Actualizar especialista
        $stmt = $conn->prepare(
            "UPDATE cl_especialista
             SET d_apellido = ?, d_nombre = ?, d_usuario = ?, c_id_especialidad = ?, n_matricula = ?, d_celular = ?
             WHERE c_id = ?"
        );
        $stmt->bind_param('ssssssi', $apellido, $nombre, $usuario, $idEspecialidad, $matricula, $celular, $idEsp);
        $stmt->execute();

        // Actualizar usuario vinculado
        $stmtU = $conn->prepare(
            "UPDATE cl_usuario
             SET d_apellido = ?, d_nombre = ?, c_usuario = ?
             WHERE c_id_especialista = ?"
        );
        $stmtU->bind_param('sssi', $apellido, $nombre, $usuario, $idEsp);
        $stmtU->execute();
    } else {
        // Insertar especialista
        $stmt = $conn->prepare(
            "INSERT INTO cl_especialista (d_apellido, d_nombre, d_usuario, c_id_especialidad, n_matricula, d_celular)
             VALUES (?,?,?,?,?,?)"
        );
        $stmt->bind_param('sssiss', $apellido, $nombre, $usuario, $idEspecialidad, $matricula, $celular);
        $stmt->execute();
        $idEsp = $conn->insert_id;

        // Crear o actualizar usuario
        $pass_hash = password_hash('1234567', PASSWORD_DEFAULT);
        $stmtU = $conn->prepare(
            "INSERT INTO cl_usuario (c_usuario, d_password, c_id_especialista, d_apellido, d_nombre, c_id_rol)
             VALUES (?,?,?,?,?,3)"
        );
        $stmtU->bind_param('ssiss', $usuario, $pass_hash, $idEsp, $apellido, $nombre);
        $stmtU->execute();
    }
    header("Location: especialistas.php"); exit;
}

/* ---------- SOFT-DELETE / REACTIVAR ---------- */
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $row = $conn->query("SELECT f_baja FROM cl_especialista WHERE c_id=$id")->fetch_assoc();
    if ($row) {
        $nuevaFecha = $row['f_baja'] ? null : date('Y-m-d');
        $conn->query("UPDATE cl_especialista SET f_baja = " . ($nuevaFecha ? "'$nuevaFecha'" : "NULL") . " WHERE c_id=$id");
    }
    header("Location: especialistas.php"); exit;
}

/* ---------- RESET PASSWORD ---------- */
if (isset($_GET['reset_pass'])) {
    $id = (int)$_GET['reset_pass'];
    $pass_hash = password_hash('1234567', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE cl_usuario SET d_password = ? WHERE c_id_especialista = ?");
    if ($stmt) {
        $stmt->bind_param('si', $pass_hash, $id);
        $stmt->execute();
        $_SESSION['msg'] = "Clave reseteada a 1234567";
    } else {
        $_SESSION['msg'] = "Error al resetear: " . $conn->error;
    }
    header("Location: especialistas.php?edit=$id"); exit;
}

/* ---------- LISTADO ---------- */
$estadoFiltro = $_GET['estado'] ?? 'ACTIVO';
$sqlWhere     = ($estadoFiltro === 'ACTIVO') ? 'AND e.f_baja IS NULL' : '';
$especialistas = $conn->query("
    SELECT e.*, esp.d_especialidad AS esp_desc
    FROM cl_especialista e
    LEFT JOIN cl_especialidad esp ON esp.c_id = e.c_id_especialidad
    WHERE 1 $sqlWhere
    ORDER BY e.d_apellido, e.d_nombre
");

/* ---------- EDICIÓN ---------- */
$edit = null;
if (isset($_GET['edit'])) {
    $edit = $conn->query("SELECT * FROM cl_especialista WHERE c_id=".(int)$_GET['edit'])->fetch_assoc();
}

/* ---------- COMBOS ---------- */
$especialidades = $conn->query("SELECT * FROM cl_especialidad ORDER BY d_especialidad");
?>

<div class="container mt-4">
  <h3 class="titulo-resaltado">Administración de Especialistas</h3>

  <!-- Mensaje de éxito -->
  <?php if (isset($_SESSION['msg'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($_SESSION['msg']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['msg']); ?>
  <?php endif; ?>

  <!-- Formulario -->
  <form method="POST" class="card card-body mb-4">
    <input type="hidden" name="id" value="<?= $edit['c_id'] ?? '' ?>">
    <div class="row g-3 align-items-end">
      <!-- Apellido -->
      <div class="col-md-2">
        <label class="form-label">Apellido</label>
        <input type="text" name="apellido" class="form-control" value="<?= $edit['d_apellido'] ?? '' ?>" required>
      </div>
      <!-- Nombre -->
      <div class="col-md-2">
        <label class="form-label">Nombre</label>
        <input type="text" name="nombre" class="form-control" value="<?= $edit['d_nombre'] ?? '' ?>" required>
      </div>
      <!-- Usuario -->
      <div class="col-md-2">
        <label class="form-label">Usuario</label>
        <input type="text" name="usuario" class="form-control" value="<?= $edit['d_usuario'] ?? '' ?>" required>
      </div>
      <!-- Matrícula -->
      <div class="col-md-2">
        <label class="form-label">Matrícula</label>
        <input type="text" name="n_matricula" class="form-control" value="<?= $edit['n_matricula'] ?? '' ?>" required>
      </div>
      <!-- Celular -->
      <div class="col-md-2">
        <label class="form-label">Celular</label>
        <input type="tel" name="d_celular" class="form-control" value="<?= $edit['d_celular'] ?? '' ?>" required>
      </div>
      <!-- Especialidad -->
      <div class="col-md-2">
        <label class="form-label">Especialidad</label>
        <select name="c_id_especialidad" class="form-select" required>
          <option value="">-- Seleccione --</option>
          <?php while ($esp = $especialidades->fetch_assoc()): ?>
            <option value="<?= $esp['c_id'] ?>" <?= (($edit['c_id_especialidad'] ?? '') == $esp['c_id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($esp['d_especialidad']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
      <!-- Botón -->
      <div class="col-md-12 text-end">
        <button type="submit" class="btn btn-success"><?= $edit ? 'Actualizar' : 'Guardar' ?></button>
        <?php if ($edit): ?>
          <a href="especialistas.php" class="btn btn-secondary">Cancelar</a>
        <?php endif; ?>
      </div>
    </div>
  </form>

  <!-- Filtro -->
  <form method="get" class="row mb-3">
    <div class="col-md-3">
      <select name="estado" class="form-select" onchange="this.form.submit()">
        <option value="ACTIVO" <?= $estadoFiltro==='ACTIVO' ? 'selected' : '' ?>>Activos</option>
        <option value="TODOS"  <?= $estadoFiltro==='TODOS'  ? 'selected' : '' ?>>Todos</option>
      </select>
    </div>
  </form>

  <!-- Tabla -->
  <div class="table-responsive">
    <table class="table table-bordered table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>Apellido</th>
          <th>Nombre</th>
          <th>Usuario</th>
          <th>Especialidad</th>
          <th>Matricula</th>
          <th>Celular</th>
          <th>Estado</th>
          <th width="150">Acciones</th>
        </tr>
      </thead>
      <tbody>
      <?php while ($e = $especialistas->fetch_assoc()):
            $activo = is_null($e['f_baja']);
      ?>
        <tr>
          <td><?= htmlspecialchars($e['d_apellido']) ?></td>
          <td><?= htmlspecialchars($e['d_nombre']) ?></td>
          <td><?= htmlspecialchars($e['d_usuario']) ?></td>
          <td><?= htmlspecialchars($e['esp_desc'] ?? '-') ?></td>
          <td><?= htmlspecialchars($e['n_matricula']) ?></td>
          <td><?= htmlspecialchars($e['d_celular']) ?></td>
          <td>
            <span class="badge <?= $activo ? 'bg-success' : 'bg-secondary' ?>">
              <?= $activo ? 'Activo' : 'Inactivo' ?>
            </span>
          </td>
          <td>
            <a href="?edit=<?= $e['c_id'] ?>" class="btn btn-sm btn-primary">Editar</a>
            <a href="?toggle=<?= $e['c_id'] ?>"
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