<?php
ob_start();
session_start();
if (!($_SESSION['is_admin'] ?? false)) {
    header('Location: ../index.php'); exit;
}
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Obtener usuario que estamos viendo
$ver = (int)($_GET['ver'] ?? 0);

// Editar monto
if (isset($_POST['editar_pago']) && isset($_POST['monto'])) {
    $idPago = (int)$_POST['editar_pago'];
    $monto  = (float)str_replace(',', '.', str_replace('.', '', $_POST['monto']));
    $conn->prepare("UPDATE gy_pago SET i_pago = ? WHERE c_id = ?")
         ->execute([$monto, $idPago]);
    header("Location: cobros.php?ver=$ver");
    exit;
} 

// Buscador de usuario
$usuarios = [];
if (isset($_GET['q']) && trim($_GET['q']) !== '') {
    $like = '%'.trim($_GET['q']).'%';
    $stmt = $conn->prepare("SELECT * FROM gy_usuario 
                           WHERE (d_nombre LIKE ? OR d_Apellido LIKE ? OR c_usuario LIKE ?)
                             AND m_baja IS NULL
                           ORDER BY d_Apellido, d_nombre");
    $stmt->bind_param('sss', $like, $like, $like);
    $stmt->execute();
    $usuarios = $stmt->get_result();
} else {
    $usuarios = $conn->query("SELECT * FROM gy_usuario WHERE m_baja IS NULL ORDER BY d_Apellido, d_nombre");
}

// Eliminar pago
if (isset($_POST['eliminar_pago'])) {
    $idPago = (int)$_POST['eliminar_pago'];
    $stmt   = $conn->prepare("DELETE FROM gy_pago WHERE c_id = ?");
    $stmt->bind_param('i', $idPago);
    $stmt->execute();
    header("Location: cobros.php?ver=$ver");
    exit;
}

// Registrar pago
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_pago'])) {
    $id_usuario   = (int)$_POST['id_usuario'];
    $id_cal       = (int)$_POST['id_calendario'];
    $monto        = (float)$_POST['monto'];
    $conn->prepare("INSERT INTO gy_pago (c_id_usuario, c_id_calendario, f_pago, i_pago)
                    VALUES (?,?, CURDATE(), ?)")
         ->execute([$id_usuario, $id_cal, $monto]);
    header("Location: cobros.php?ver=$id_usuario"); 
    exit;
}

// Historial del usuario seleccionado

$historial = [];
if ($ver) {
    $historial = $conn->query("SELECT p.*, c.d_mes, c.n_anio
                               FROM gy_pago p
                               JOIN gy_calendario c ON p.c_id_calendario = c.c_id
                               WHERE p.c_id_usuario = $ver
                               ORDER BY c.n_anio DESC, c.n_mes DESC");
}
$calendarios = $conn->query("SELECT * FROM gy_calendario ORDER BY n_anio DESC, n_mes DESC");
?>


<div class="container-fluid mt-4">
  <h3>Gestión de Cobros</h3>

  <!-- Buscador -->
  <form method="GET" class="row g-2 mb-4">
    <div class="col-md-4">
      <input type="text" name="q" class="form-control" placeholder="Buscar por nombre / apellido / usuario"
             value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
    </div>
    <div class="col-md-2">
      <button class="btn btn-primary">Buscar</button>
    </div>
  </form>

  <div class="row">
    <!-- Lista de usuarios -->
    <div class="col-md-5">
      <div class="list-group">
        <?php while ($u = $usuarios->fetch_assoc()): ?>
          <a href="?ver=<?= $u['c_id'] ?>&q=<?= htmlspecialchars($_GET['q'] ?? '') ?>"
             class="list-group-item list-group-item-action <?= $ver == $u['c_id'] ? 'active' : '' ?>">
            <?= htmlspecialchars($u['d_Apellido'].', '.$u['d_nombre']) ?>
            <small class="text-muted d-block"><?= $u['c_usuario'] ?></small>
          </a>
        <?php endwhile; ?>
      </div>
    </div>

    <!-- Detalle / historial -->
    <div class="col-md-7">
      <?php if ($ver): ?>
        <?php
        $usr = $conn->query("SELECT * FROM gy_usuario WHERE c_id=$ver")->fetch_assoc();
        ?>
        <h5 class="mb-3">Historial de pagos – <?= htmlspecialchars($usr['d_Apellido'].', '.$usr['d_nombre']) ?></h5>

        <!-- Formulario nuevo pago -->
        <form method="POST" class="row g-2 mb-3">
          <input type="hidden" name="id_usuario" value="<?= $ver ?>">
          <div class="col-md-5">
            <select name="id_calendario" class="form-select" required>
              <option value="">Seleccione mes-año</option>
              <?php while ($c = $calendarios->fetch_assoc()): ?>
                <option value="<?= $c['c_id'] ?>"><?= $c['d_mes'].' '.$c['n_anio'] ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-md-4">
            <input type="text" id="montoMask" class="form-control" placeholder="0.000,00" required>
            <input type="hidden" name="monto" id="montoReal">
         </div>
          <div class="col-md-3">
            <button name="registrar_pago" class="btn btn-success w-100">Registrar</button>
          </div>
        </form>

      
        <!-- Tabla de pagos -->
        <table class="table table-sm table-striped">
          <thead>
            <tr>
              <th>Mes-Año</th><th>Fecha pago</th><th>Monto</th>
              <th class="text-center">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($h = $historial->fetch_assoc()): ?>
                <tr>
                <td><?= $h['d_mes'].' '.$h['n_anio'] ?></td>
                <td><?= $h['f_pago'] ?></td>
                <td>
                    <span class="monto-text">$<?= number_format($h['i_pago'], 2, ',', '.') ?></span>
                    <form method="POST" class="monto-form d-none" style="display:inline-flex; gap:.25rem;">
                    <input type="hidden" name="editar_pago" value="<?= $h['c_id'] ?>">
                    <input type="text" class="form-control form-control-sm monto-mask" 
                            name="monto"
                            value="<?= number_format($h['i_pago'], 2, ',', '.') ?>" 
                            style="width: 90px;">
                    <button class="btn btn-sm btn-outline-success">✓</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary cancel-edit">✕</button>
                    </form>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-warning btn-editar">Editar</button>
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="eliminar_pago" value="<?= $h['c_id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger"
                              onclick="return confirm('¿Seguro que querés borrar este pago?');">Eliminar
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>                    
                </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
      <?php else: ?>
        <div class="alert alert-info">Seleccione un usuario para ver su historial y registrar pagos.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
// Máscara mientras escribe
document.getElementById('montoMask').addEventListener('input', function (e) {
  let value = e.target.value
    .replace(/\./g, '')          // quita puntos miles
    .replace(',', '.');          // convierte coma decimal a punto

  if (!isNaN(value) && value !== '') {
    // Separador de miles
    let parts = value.split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    e.target.value = parts.join(',');
    // Guardamos el valor real para enviar
    document.getElementById('montoReal').value = value.replace(',', '.');
  }
});

// Al enviar, asegurar que el campo oculto tenga el valor numérico
document.querySelector('form').addEventListener('submit', function () {
  document.getElementById('montoReal').value =
    document.getElementById('montoMask').value
      .replace(/\./g, '')
      .replace(',', '.');
});
</script>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(function () {
  // Máscara mientras escribe
  $(document).on('input', '.monto-mask', function () {
    let v = $(this).val().replace(/\./g, '').replace(',', '.');
    if (!isNaN(v)) {
      let parts = v.split('.');
      parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
      $(this).val(parts.join(','));
    }
  });

  // Editar
  $('.btn-editar').click(function () {
    const tr = $(this).closest('tr');
    tr.find('.monto-text, .btn-editar').addClass('d-none');
    tr.find('.monto-form').removeClass('d-none');
  });

  // Cancelar
  $(document).on('click', '.cancel-edit', function () {
    const tr = $(this).closest('tr');
    tr.find('.monto-text, .btn-editar').removeClass('d-none');
    tr.find('.monto-form').addClass('d-none');
  });
});
</script>

<?php require_once '../../includes/footer.php'; ob_end_flush(); ?>