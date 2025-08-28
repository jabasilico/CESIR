<?php
session_start();
require_once '../config/database.php';

$esp = (int)($_GET['esp'] ?? 0);
$mes = $_GET['mes'] ?? '';
if (!$esp || !preg_match('/^\d{4}-\d{2}$/', $mes)) {
    die('Parámetros inválidos');
}

$diaLimite = 20;
$hoy       = (new DateTime())->setTime(0, 0);
$primerDia = $mes . '-01';
$ultimoDia = (clone $hoy)->setDate((int)substr($mes, 0, 4), (int)substr($mes, 5, 2), 1)
             ->modify('last day of this month')
             ->format('Y-m-d');

$sql = "
    SELECT DISTINCT p.d_apellido, p.d_nombre, p.n_dni, concat(e.d_apellido, ', ', e.d_nombre) AS esp_nombre, e.d_celular
    FROM cl_paciente p
    JOIN cl_historia_medica hm  ON hm.c_id_paciente = p.c_id
    JOIN cl_plan_tratamiento pt ON pt.c_id_historia_medica = hm.c_id
    LEFT JOIN cl_especialista e ON e.c_id = pt.c_id_especialista
    WHERE (hm.f_fin IS NULL OR hm.f_fin > CURDATE())
      AND pt.c_id_especialidad = ?
      AND DATE(hm.f_alta) <= ?
      AND NOT EXISTS (
          SELECT 1
          FROM cl_evolucion_paciente ev
          WHERE ev.c_id_paciente = p.c_id
            AND ev.c_id_especialista = ?
            AND ev.f_evolucion BETWEEN ? AND ?
      )
    ORDER BY p.d_apellido, p.d_nombre
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('isiss', $esp, $ultimoDia, $esp, $primerDia, $ultimoDia);
$stmt->execute();
$lista = $stmt->get_result();
?>

<h6><?= htmlspecialchars($conn->query("SELECT d_especialidad FROM cl_especialidad WHERE c_id=$esp")->fetch_assoc()['d_especialidad'] ?? '') ?>
     – <?= $mes ?></h6>

<?php if ($lista->num_rows): ?>
  <table class="table table-sm table-striped mt-2">
    <thead>
      <tr><th>Apellido</th><th>Nombre</th><th>DNI</th><th>Especialista</th><th>Acción</th></tr>
    </thead>
    <tbody>
      <?php while ($p = $lista->fetch_assoc()): 

        $msg  = "Tiene que cargar evoluciones de " . htmlspecialchars($p['d_apellido']) . ", " . htmlspecialchars($p['d_nombre']) . ".";
        $wa   = 'https://wa.me/' . rawurlencode($p['d_celular']) . '?text=' . rawurlencode($msg);
      ?>

        <tr>
          <td><?= htmlspecialchars($p['d_apellido']) ?></td>
          <td><?= htmlspecialchars($p['d_nombre']) ?></td>
          <td><?= $p['n_dni'] ?></td>
          <td><?= $p['esp_nombre'] ?></td>
          <td>
            <?php if (!empty($p['esp_nombre'])): ?>
              <a href="<?= $wa ?>" target="_blank" class="btn btn-success btn-sm">
                <i class="bi bi-whatsapp"></i> Avisar
              </a>
            <?php endif; ?> 
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
<?php else: ?>
  <p class="text-muted mb-0">No hay pacientes en esta condición.</p>
<?php endif; ?>