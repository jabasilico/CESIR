<?php
session_start();
if (!($_SESSION['is_admin'] ?? false)) {
    header('Location: ../index.php'); exit;
}
require_once '../../config/database.php';
require_once '../../includes/header.php';

/*  Lógica:
    1. Obtener todos los usuarios activos
    2. Para cada uno, detectar el último mes-año pagado
    3. Calcular cuántos meses lleva sin pagar
*/

$deudores = [];

$usuarios = $conn->query("SELECT c_id, d_nombre, d_Apellido, c_usuario, d_telefono
                          FROM gy_usuario
                          WHERE m_baja IS NULL
                          ORDER BY d_Apellido, d_nombre");

while ($u = $usuarios->fetch_assoc()) {
    // Último pago
    $last = $conn->query("SELECT MAX(c_id_calendario) AS ultimo_id
                          FROM gy_pago
                          WHERE c_id_usuario = {$u['c_id']}")->fetch_assoc()['ultimo_id'];

    $ultimoCal = $last ? $conn->query("SELECT n_anio, n_mes, d_mes
                                       FROM gy_calendario
                                       WHERE c_id = $last")->fetch_assoc() : null;

    // Mes-año actual
    $hoy = new DateTime();
    $mesActual = (int)$hoy->format('m');
    $anioActual  = (int)$hoy->format('Y');

    // Si nunca pagó, tomamos como “mes 1 año 2025”
    $mesUltimo = $ultimoCal ? (int)$ultimoCal['n_mes'] : 1;
    $anioUltimo = $ultimoCal ? (int)$ultimoCal['n_anio'] : 2025;

    $mesesAtraso = ($anioActual - $anioUltimo) * 12 + ($mesActual - $mesUltimo) - 1;
    if ($mesesAtraso >= 1 || !$ultimoCal) {
        $deudores[] = [
            'usuario' => $u,
            'ultimo'  => $ultimoCal ? $ultimoCal['d_mes'].' '.$ultimoCal['n_anio'] : 'Nunca',
            'atraso'  => max($mesesAtraso, 1)
        ];
    }
}
?>

<div class="container-fluid mt-4">
  <h3>Listado de Deudores</h3>

  <?php if (!$deudores): ?>
    <div class="alert alert-success">No hay socios con cuotas atrasadas.</div>
  <?php else: ?>
    <table class="table table-striped">
      <thead>
        <tr>
            <th>Apellido y Nombre</th>
            <th>Usuario</th>
            <th>Último pago</th>
            <th>Meses atraso</th>
            <th>WhatsApp</th>
        </tr>
        </thead>

        <tbody>
        <?php foreach ($deudores as $d):

                // Mensaje dinámico
                $mensaje = "Hola {$d['usuario']['d_nombre']}, "
                        . "te recordamos que tu último pago fue en {$d['ultimo']} "
                        . "y actualmente tenés {$d['atraso']} mes/es de atraso.";
                // Teléfono ficticio (reemplazá por el campo real de tu tabla)
                $telefono = $d['usuario']['d_telefono'] ?? '';

                //envio a Whatsapp
                $urlWhatsApp = 'https://wa.me/' . $telefono . '?text=' . urlencode($mensaje);
                
        ?>
            <tr>
            <td><?= htmlspecialchars($d['usuario']['d_Apellido'].', '.$d['usuario']['d_nombre']) ?></td>
            <td><?= htmlspecialchars($d['usuario']['c_usuario']) ?></td>
            <td><?= $d['ultimo'] ?></td>
            <td><?= $d['atraso'] ?></td>
            <td>
                <a href="<?= $urlWhatsApp ?>" target="_blank" class="btn btn-success btn-sm">
                <i class="bi bi-whatsapp"></i> Enviar
                </a>
            </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>