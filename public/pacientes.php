<?php
session_start();
if (!($_SESSION['is_admin'] ?? false)) {
    header('Location: ../index.php'); exit;
}
require_once '../config/database.php';
require_once '../includes/header.php';

/* ---------- RESET PASS ---------- */
if (isset($_GET['reset_pass'])) {
    $id_reset = (int)$_GET['reset_pass'];
    $row = $conn->query("SELECT n_dni FROM cl_paciente WHERE c_id = $id_reset")->fetch_assoc();
    if ($row) {
        $dni = $row['n_dni'];
        $pass_hash = password_hash($dni, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE cl_usuario SET d_password = ? WHERE c_id_paciente = ?");
        $stmt->bind_param('si', $pass_hash, $id_reset);
        $stmt->execute();
        $_SESSION['msg'] = "Clave reseteada al DNI $dni";
    }
    header("Location: pacientes.php?edit=$id_reset"); exit;
}

/* ---------- ALTA / MODIFICACIÓN ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id        = $_POST['id'] ?? null;
    $apellido  = trim($_POST['apellido']);
    $nombre    = trim($_POST['nombre']);
    $nacimiento= $_POST['nacimiento'] ?: null;
    $dni       = (int)$_POST['dni'];
    $usuario   = trim($_POST['usuario']);
    $idEstado  = $_POST['c_id_estado'];
    $idModalidad = $_POST['c_id_modalidad'];
    $Mail      = filter_var($_POST['d_mail'], FILTER_VALIDATE_EMAIL) ? $_POST['d_mail'] : null;
    $Telefono  = trim($_POST['d_telefono']) ?: null;
    $Celular   = trim($_POST['d_celular']) ?: null;
    $Calle     = trim($_POST['d_calle']) ?: null;
    $Numero    = trim($_POST['d_numero']) ?: null;
    $Piso      = trim($_POST['d_piso']) ?: null;
    $Depto     = trim($_POST['d_depto']) ?: null;
    $Otro      = trim($_POST['d_otro']) ?: null;
    $IdLocalidad = $_POST['c_id_localidad'] ?: null;
    $FecIngreso = $_POST['f_ingreso'] ?: date('Y-m-d');
    $IdEspecialista = $_POST['c_id_especialista_cabecera'] ?: null;
    $idMutual = $_POST['c_id_mutual'] ?: null;
    $nroAfiliado = trim($_POST['n_afiliado']) ?: null;

    if (!$apellido || !$nombre || !$dni || !$usuario || !$idEstado || !$idModalidad) {
        die("Faltan datos obligatorios.");
    }

    if ($id) {
        $stmt = $conn->prepare(
            "UPDATE cl_paciente
             SET d_apellido=?, d_nombre=?, f_nacimiento=?, n_dni=?,
                 c_id_estado=?, c_id_modalidad=?,
                 d_mail=?, d_telefono=?, d_celular=?,
                 d_calle=?, d_numero=?, d_piso=?, d_depto=?, d_otro=?,
                 c_id_localidad=?, f_ingreso=?, c_id_especialista_cabecera=?,
                 c_id_mutual=?, n_afiliado=?
             WHERE c_id=?"
        );
        $stmt->bind_param(
            'sssiiissssssssisiisi',
            $apellido, $nombre, $nacimiento, $dni,
            $idEstado, $idModalidad,
            $Mail, $Telefono, $Celular,
            $Calle, $Numero, $Piso, $Depto, $Otro,
            $IdLocalidad, $FecIngreso, $IdEspecialista,
            $idMutual, $nroAfiliado,
            $id
        );
        $stmt->execute();

        $stmtU = $conn->prepare("UPDATE cl_usuario SET c_usuario=? WHERE c_id_paciente=?");
        $stmtU->bind_param('si', $usuario, $id);
        $stmtU->execute();
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO cl_paciente
             (d_apellido, d_nombre, f_nacimiento, n_dni,
              c_id_estado, c_id_modalidad,
              d_mail, d_telefono, d_celular,
              d_calle, d_numero, d_piso, d_depto, d_otro,
              c_id_localidad, f_ingreso, c_id_especialista_cabecera,
              c_id_mutual, n_afiliado)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->bind_param(
            'sssiiissssssssisiis',
            $apellido, $nombre, $nacimiento, $dni,
            $idEstado, $idModalidad,
            $Mail, $Telefono, $Celular,
            $Calle, $Numero, $Piso, $Depto, $Otro,
            $IdLocalidad, $FecIngreso, $IdEspecialista,
            $idMutual, $nroAfiliado
        );
        $stmt->execute();
        $paciente_id = $conn->insert_id;

        $pass_hash = password_hash('1234', PASSWORD_DEFAULT);
        $stmtU = $conn->prepare(
            "INSERT INTO cl_usuario 
             (c_usuario, d_password, c_id_paciente, c_id_rol, d_apellido, d_nombre) 
             VALUES (?,?,?,?,?,?)"
        );
        $stmtU->bind_param('ssiiss', $usuario, $pass_hash, $paciente_id, 2, $apellido, $nombre);
        $stmtU->execute();
    }
    header("Location: pacientes.php"); exit;
}

/* ---------- LISTADO (AJAX) ---------- */
$estadoFiltro = $_GET['estado'] ?? 'ACTIVO';
$q            = trim($_GET['q']  ?? '');
$estadoWhere  = ($estadoFiltro === 'ACTIVO') ? ' AND p.c_id_estado = 1' : '';

$sql = "
SELECT  p.*,
        ep.d_estado  AS estado_desc,
        m.d_modalidad AS modalidad_desc,
        l.d_localidad AS localidad_desc,
        CONCAT(e.d_apellido, ', ', e.d_nombre) AS especialista_desc
FROM    cl_paciente p
LEFT JOIN cl_estado_paciente ep ON ep.c_id = p.c_id_estado
LEFT JOIN cl_modalidad        m ON m.c_id  = p.c_id_modalidad
LEFT JOIN cl_localidad        l ON l.c_id  = p.c_id_localidad
LEFT JOIN cl_especialista     e ON e.c_id  = p.c_id_especialista_cabecera
WHERE   CONCAT_WS(' ', p.d_apellido, p.d_nombre, p.n_dni, p.d_mail) LIKE ?
        $estadoWhere
ORDER BY p.d_apellido, p.d_nombre";

$stmt = $conn->prepare($sql);
$like = "%$q%";
$stmt->bind_param('s', $like);
$stmt->execute();
$pacientes = $stmt->get_result();

/* ---------- EDICIÓN ---------- */
$edit = null;
$usuario_edit = '';
if (isset($_GET['edit'])) {
    $edit = $conn->query("SELECT * FROM cl_paciente WHERE c_id=".(int)$_GET['edit'])->fetch_assoc();
    $res = $conn->query("SELECT c_usuario FROM cl_usuario WHERE c_id_paciente=".(int)$_GET['edit']." LIMIT 1");
    if ($row = $res->fetch_assoc()) {
        $usuario_edit = $row['c_usuario'];
    }
}
?>

<div class="container mt-4">
  <h3 class="titulo-resaltado">Administración de Pacientes</h3>

  <?php if (isset($_SESSION['msg'])): ?>
    <div class="container mt-2">
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_SESSION['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    </div>
    <?php unset($_SESSION['msg']); ?>
  <?php endif; ?>

  <!-- FORMULARIO -->
  <form method="POST" class="card card-body mb-4">
    <input type="hidden" name="id" value="<?= $edit['c_id'] ?? '' ?>">
    <div class="row g-3">
      <div class="col-md-4"><label class="form-label">Apellido</label><input type="text" name="apellido" class="form-control" value="<?= $edit['d_apellido'] ?? '' ?>" required></div>
      <div class="col-md-4"><label class="form-label">Nombre</label><input type="text" name="nombre" class="form-control" value="<?= $edit['d_nombre'] ?? '' ?>" required></div>
      <div class="col-md-2"><label class="form-label">Nacimiento</label><input type="date" name="nacimiento" class="form-control" value="<?= $edit['f_nacimiento'] ?? '' ?>"></div>
      <div class="col-md-2"><label class="form-label">DNI</label><input type="number" name="dni" class="form-control" value="<?= $edit['n_dni'] ?? '' ?>" required></div>

      <div class="col-md-3">
        <label class="form-label">Usuario</label>
        <div class="input-group">
          <input type="text" name="usuario" class="form-control" value="<?= htmlspecialchars($usuario_edit ?? '') ?>" required>
          <?php if (isset($edit['c_id'])): ?>
            <a href="?reset_pass=<?= $edit['c_id'] ?>" class="btn btn-outline-danger" onclick="return confirm('¿Resetear clave al DNI del paciente?')">Reset</a>
          <?php endif; ?>
        </div>
      </div>

      <div class="col-md-2"><label class="form-label">Estado</label>
        <select name="c_id_estado" class="form-select" required>
          <option value="">-- Seleccione --</option>
          <?php
            $estados = $conn->query("SELECT c_id, d_estado FROM cl_estado_paciente ORDER BY d_estado");
            while ($e = $estados->fetch_assoc()):
              $selected = ($edit['c_id_estado'] ?? '') == $e['c_id'] ? 'selected' : '';
          ?>
            <option value="<?= $e['c_id'] ?>" <?= $selected ?>><?= $e['d_estado'] ?></option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="col-md-2"><label class="form-label">Modalidad</label>
        <select name="c_id_modalidad" class="form-select" required>
          <option value="">-- Seleccione --</option>
          <?php
            $modalidades = $conn->query("SELECT c_id, d_modalidad FROM cl_modalidad ORDER BY d_modalidad");
            while ($m = $modalidades->fetch_assoc()):
              $selected = ($edit['c_id_modalidad'] ?? '') == $m['c_id'] ? 'selected' : '';
          ?>
            <option value="<?= $m['c_id'] ?>" <?= $selected ?>><?= $m['d_modalidad'] ?></option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="col-md-3"><label class="form-label">Especialista Cabecera</label>
        <select name="c_id_especialista_cabecera" class="form-select">
          <option value="">-- Sin asignar --</option>
          <?php
            $espec = $conn->query("SELECT c_id, CONCAT(d_apellido,', ',d_nombre) AS nombre_completo FROM cl_especialista WHERE f_baja IS NULL ORDER BY d_apellido");
            while ($esp = $espec->fetch_assoc()):
              $selected = ($edit['c_id_especialista_cabecera'] ?? '') == $esp['c_id'] ? 'selected' : '';
          ?>
            <option value="<?= $esp['c_id'] ?>" <?= $selected ?>><?= htmlspecialchars($esp['nombre_completo']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="d_mail" class="form-control" value="<?= $edit['d_mail'] ?? '' ?>"></div>
      <div class="col-md-4"><label class="form-label">Teléfono</label><input type="tel" name="d_telefono" class="form-control" value="<?= $edit['d_telefono'] ?? '' ?>"></div>
      <div class="col-md-4"><label class="form-label">Celular</label><input type="tel" name="d_celular" class="form-control" value="<?= $edit['d_celular'] ?? '' ?>"></div>

      <div class="col-md-6"><label class="form-label">Calle</label><input type="text" name="d_calle" class="form-control" value="<?= $edit['d_calle'] ?? '' ?>"></div>
      <div class="col-md-2"><label class="form-label">Número</label><input type="text" name="d_numero" class="form-control" value="<?= $edit['d_numero'] ?? '' ?>"></div>
      <div class="col-md-2"><label class="form-label">Piso</label><input type="text" name="d_piso" class="form-control" value="<?= $edit['d_piso'] ?? '' ?>"></div>
      <div class="col-md-2"><label class="form-label">Depto</label><input type="text" name="d_depto" class="form-control" value="<?= $edit['d_depto'] ?? '' ?>"></div>
      <div class="col-md-7"><label class="form-label">Otro</label><input type="text" name="d_otro" class="form-control" value="<?= $edit['d_otro'] ?? '' ?>"></div>
      <div class="col-md-3"><label class="form-label">Localidad</label>
        <select name="c_id_localidad" class="form-select">
          <option value="">-- Sin localidad --</option>
          <?php
            $loc = $conn->query("SELECT c_id, d_localidad FROM cl_localidad ORDER BY d_localidad");
            while ($l = $loc->fetch_assoc()):
              $selected = ($edit['c_id_localidad'] ?? '') == $l['c_id'] ? 'selected' : '';
          ?>
            <option value="<?= $l['c_id'] ?>" <?= $selected ?>><?= $l['d_localidad'] ?></option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="col-md-2"><label class="form-label">Fecha de Ingreso</label><input type="date" name="f_ingreso" class="form-control" value="<?= $edit['f_ingreso'] ?? date('Y-m-d') ?>"></div>

      <div class="row g-3 mt-2">
        <div class="col-md-3">
          <label class="form-label">Mutual</label>
          <select name="c_id_mutual" class="form-select">
            <option value="">-- Sin mutual --</option>
            <?php
              $mut = $conn->query("SELECT c_id, d_mutual FROM cl_mutual ORDER BY d_mutual");
              while ($m = $mut->fetch_assoc()):
                $selected = ($edit['c_id_mutual'] ?? '') == $m['c_id'] ? 'selected' : '';
            ?>
              <option value="<?= $m['c_id'] ?>" <?= $selected ?>><?= $m['d_mutual'] ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">N° de Afiliado</label>
          <input type="text" name="n_afiliado" class="form-control" value="<?= $edit['n_afiliado'] ?? '' ?>">
        </div>
      </div>
    </div>

    <div class="d-flex gap-2 mt-3">
      <?php if (isset($edit['c_id'])): ?>
        <a href="observaciones.php?id=<?= $edit['c_id'] ?>" class="btn btn-violet">Observaciones</a>
      <?php endif; ?>
      <div class="flex-grow-1"></div>
      <button type="submit" class="btn btn-success"><?= $edit ? 'Actualizar' : 'Guardar' ?></button>
      <?php if (isset($_GET['edit'])): ?>
        <a href="pacientes.php" class="btn btn-secondary">Cancelar</a>
      <?php endif; ?>
    </div>
  </form>

  <!-- Buscador en tiempo real -->
  <form method="get" class="row mb-3">
    <div class="col-md-6">
      <label class="form-label fw-bold mb-1">Buscar</label>
      <input type="text" id="q" class="form-control" placeholder="Escriba para filtrar…">
    </div>
    <div class="col-md-3">
      <label class="form-label fw-bold mb-1">Estado</label>
      <select id="estado" class="form-select">
        <option value="ACTIVO" selected>Activos</option>
        <option value="TODOS">Todos</option>
      </select>
    </div>
  </form>

  <!-- Tabla que se actualiza vía AJAX -->
  <div id="tablaPacientes" class="table-responsive">
    <!-- Aquí llegará la tabla -->
  </div>
</div>

<!-- Script AJAX -->
<script>
function cargarTabla() {
    const q = document.getElementById('q').value.trim();
    const estado = document.getElementById('estado').value;
    fetch('buscar_pacientes_ajax.php?q=' + encodeURIComponent(q) + '&estado=' + encodeURIComponent(estado))
        .then(res => res.text())
        .then(html => document.getElementById('tablaPacientes').innerHTML = html);
}
document.getElementById('q').addEventListener('input', cargarTabla);
document.getElementById('estado').addEventListener('change', cargarTabla);
document.addEventListener('DOMContentLoaded', cargarTabla);
</script>

<?php require_once '../includes/footer.php'; ?>