<?php
ob_start();
session_start();
if (!($_SESSION['is_admin'] ?? false)) {
    header('Location: ../index.php'); exit;
}
require_once '../../config/database.php';
require_once '../../includes/header.php';

// --- ALTA / MODIFICACIÓN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id        = $_POST['id'] ?? null;
    $nombre    = trim($_POST['nombre']);
    $apellido  = trim($_POST['apellido']);
    $usuario   = trim($_POST['usuario']);
    $password  = $_POST['password'] ?? '';
    $nacimiento= $_POST['f_nacimiento'] ?: null;
    $admin     = isset($_POST['admin']) ? 'Y' : 'N';
    $telefono  = trim($_POST['telefono']?? '');
    $comentario  = trim($_POST['comentario']?? '');

    if ($id) { // Modificar
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE gy_usuario 
                                    SET d_nombre=?, d_Apellido=?, c_usuario=?, d_password=?, f_nacimiento=?, m_admin=?, d_telefono=?, d_comentario=?
                                    WHERE c_id=?");
            $stmt->bind_param('ssssssssi', $nombre, $apellido, $usuario, $hash, $nacimiento, $admin, $telefono, $comentario, $id);
        } else {
            $stmt = $conn->prepare("UPDATE gy_usuario 
                                    SET d_nombre=?, d_Apellido=?, c_usuario=?, f_nacimiento=?, m_admin=?, d_telefono=?, d_comentario=?
                                    WHERE c_id=?");
            $stmt->bind_param('sssssssi', $nombre, $apellido, $usuario, $nacimiento, $admin, $telefono, $comentario, $id);
        }
    } else { // Alta
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO gy_usuario  (d_nombre, d_Apellido, c_usuario, d_password, f_nacimiento, m_admin, d_telefono, d_comentario)
                                VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param('ssssssss',$nombre, $apellido, $usuario, $hash, $nacimiento, $admin, $telefono, $comentario);
    }
    $stmt->execute();
    header("Location: usuarios.php"); exit;
}

// --- SOFT-DELETE / RESTORE ---
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $conn->query("UPDATE gy_usuario SET m_baja = IF(m_baja IS NULL, CURDATE(), NULL) WHERE c_id=$id");
    header("Location: usuarios.php"); exit;
}

// --- LECTURA ---
$usuarios = $conn->query("SELECT c_id, d_nombre, d_Apellido, c_usuario, f_nacimiento, m_admin, m_baja, d_telefono, d_comentario,
                                 TIMESTAMPDIFF(YEAR, f_nacimiento, CURDATE()) AS edad
                          FROM gy_usuario 
                          ORDER BY m_baja, d_Apellido, d_nombre");

// --- DATOS PARA EDITAR ---
$edit = null;
if (isset($_GET['edit'])) {
    $edit = $conn->query("SELECT * FROM gy_usuario WHERE c_id=".(int)$_GET['edit'])->fetch_assoc();
}
?>

<div class="container mt-4">
  <h3>Administración de Usuarios</h3>

  <!-- Formulario Alta/Edición -->
  <form method="POST" class="card card-body mb-4">
    <input type="hidden" name="id" value="<?= $edit['c_id'] ?? '' ?>">
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Nombre</label>
        <input type="text" name="nombre" class="form-control" value="<?= $edit['d_nombre'] ?? '' ?>" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">Apellido</label>
        <input type="text" name="apellido" class="form-control" value="<?= $edit['d_Apellido'] ?? '' ?>" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">Usuario</label>
        <input type="text" name="usuario" class="form-control" value="<?= $edit['c_usuario'] ?? '' ?>" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">Teléfono</label>
        <input type="text" name="telefono" class="form-control" value="<?= $edit['d_telefono'] ?? '' ?>" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">Nacimiento</label>
        <input type="date" name="f_nacimiento" class="form-control" value="<?= $edit['f_nacimiento'] ?? '' ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Contraseña <?= $edit ? '(solo si quieres cambiarla)' : '' ?></label>
        <input type="password" name="password" class="form-control" <?= $edit ? '' : 'required' ?>>
      </div>
      <div class="col-md-12">
        <label class="form-label">Comentario</label>
        <textarea name="comentario" class="form-control" rows="2"
                  placeholder="Observaciones / notas"><?= $edit['d_comentario'] ?? '' ?></textarea>
      </div>
      <div class="col-md-4 d-flex align-items-end">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="admin" <?= ($edit['m_admin'] ?? '') === 'Y' ? 'checked' : '' ?>>
          <label class="form-check-label">Admin</label>
        </div>
      </div>
      <div class="col-12">
        <button class="btn btn-primary"><?= $edit ? 'Actualizar' : 'Guardar' ?></button>
        <?php if ($edit): ?>
          <a href="usuarios.php" class="btn btn-secondary ms-2">Cancelar</a>
        <?php endif; ?>
      </div>
    </div>
  </form>

  <!-- Filtro por estado-->
  <?php
  $ver = $_GET['ver'] ?? 'activos';   // activos | todos | bajas
  $where = match($ver) {
      'activos' => 'WHERE m_baja IS NULL',
      'bajas'   => 'WHERE m_baja IS NOT NULL',
      default   => ''
  };
  $usuarios = $conn->query("SELECT c_id, d_nombre, d_Apellido, c_usuario, f_nacimiento, m_admin, d_telefono, d_comentario, m_baja,
                                   TIMESTAMPDIFF(YEAR, f_nacimiento, CURDATE()) AS edad
                            FROM gy_usuario $where 
                            ORDER BY d_Apellido, d_nombre");
  ?>
  
  <!-- Filtro -->
  <div class="mb-3">
  <div class="btn-group" role="group">
      <a href="?ver=activos" class="btn btn-sm <?= $ver==='activos' ? 'btn-primary' : 'btn-outline-primary' ?>">Activos</a>
      <a href="?ver=bajas"   class="btn btn-sm <?= $ver==='bajas'   ? 'btn-primary' : 'btn-outline-primary' ?>">Dados de baja</a>
      <a href="?ver=todos"   class="btn btn-sm <?= $ver==='todos'   ? 'btn-primary' : 'btn-outline-primary' ?>">Todos</a>
  </div>
  </div>

  <!-- Buscador -->
  <div class="row mb-3">
  <div class="col-md-6">
      <label class="form-label">Buscar por nombre o apellido</label>
      <input type="text" id="buscador" class="form-control" placeholder="Escribí para filtrar...">
  </div>
  </div>   

  <!-- Grilla de usuarios -->
  <table class="table table-bordered table-hover align-middle" id="tablaUsuarios">
    <thead class="table-dark">
      <tr>
        <th>ID</th><th>Apellido</th><th>Nombre</th><th>Usuario</th><th>Teléfono</th><th>Nacimiento</th><th>Edad</th><th>Admin</th><th>Estado</th><th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($u = $usuarios->fetch_assoc()): ?>
        <tr class="<?= $u['m_baja'] ? 'table-danger text-decoration-line-through' : '' ?>">
          <td><?= $u['c_id'] ?></td>
          <td><?= htmlspecialchars($u['d_Apellido']) ?></td>
          <td><?= htmlspecialchars($u['d_nombre']) ?></td>
          <td><?= htmlspecialchars($u['c_usuario']) ?></td>
          <td><?= htmlspecialchars($u['d_telefono']) ?></td>
          <td><?= $u['f_nacimiento'] ?></td>
          <td><?= $u['edad'] ?? '-' ?></td>
          <td><?= $u['m_admin'] ?></td>
          <td><?= $u['m_baja'] ? 'Baja ('.$u['m_baja'].')' : 'Activo' ?></td>
          <td>
            <a href="?edit=<?= $u['c_id'] ?>" class="btn btn-sm btn-outline-warning">Editar</a>
            <a href="?toggle=<?= $u['c_id'] ?>" class="btn btn-sm btn-outline-<?= $u['m_baja'] ? 'success' : 'danger' ?>"
               onclick="return confirm('¿<?= $u['m_baja'] ? 'Reactivar' : 'Dar de baja' ?> este usuario?')">
               <?= $u['m_baja'] ? 'Reactivar' : 'Baja' ?>
            </a>
          </td>
        </tr>
      <?php endwhile; ?>

      <!-- Javascript (jQuery) para el filtro instantáneo -->
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script>
        $(function () {
            const $filas = $('#tablaUsuarios tbody tr');

            $('#buscador').on('keyup', function () {
                const texto = $(this).val().toLowerCase();

                $filas.each(function () {
                    const nombre  = $(this).find('td').eq(2).text().toLowerCase();
                    const apellido= $(this).find('td').eq(1).text().toLowerCase();
                    $(this).toggle(
                        (nombre.includes(texto) || apellido.includes(texto))
                    );
                });
            });
        });
        </script>

    </tbody>
  </table>
</div>

<?php require_once '../../includes/footer.php'; ob_end_flush(); ?>