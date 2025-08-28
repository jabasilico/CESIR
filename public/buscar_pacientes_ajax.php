<?php
require_once '../config/database.php';

header('Content-Type: text/html; charset=utf-8');

$q     = trim($_GET['q'] ?? '');
$est   = $_GET['estado'] ?? 'ACTIVO';
$where = ($est === 'ACTIVO') ? 'AND p.c_id_estado = 1' : '';

$sql = "
SELECT  p.*,
        ep.d_estado  AS estado_desc,
        m.d_modalidad AS modalidad_desc,
        l.d_localidad AS localidad_desc,
        CONCAT(e.d_apellido,', ',e.d_nombre) AS especialista_desc
FROM    cl_paciente p
LEFT JOIN cl_estado_paciente ep ON ep.c_id = p.c_id_estado
LEFT JOIN cl_modalidad        m ON m.c_id  = p.c_id_modalidad
LEFT JOIN cl_localidad        l ON l.c_id  = p.c_id_localidad
LEFT JOIN cl_especialista     e ON e.c_id  = p.c_id_especialista_cabecera
WHERE   CONCAT_WS(' ', p.d_apellido, p.d_nombre, p.n_dni, p.d_mail) LIKE ?
        $where
ORDER BY p.d_apellido, p.d_nombre";

$stmt = $conn->prepare($sql);
$like = "%$q%";
$stmt->bind_param('s', $like);
$stmt->execute();
$result = $stmt->get_result();
?>

<table class="table table-bordered table-hover align-middle">
  <thead class="table-light">
    <tr>
      <th>Apellido</th>
      <th>Nombre</th>
      <th>Nac.</th>
      <th>Edad</th>
      <th>DNI</th>
      <th>Estado</th>
      <th width="90">Acciones</th>
    </tr>
  </thead>
  <tbody>
  <?php while ($p = $result->fetch_assoc()):
        $edad = '';
        if ($p['f_nacimiento']) {
            $fn = new DateTime($p['f_nacimiento']);
            $edad = $fn->diff(new DateTime())->y;
        }
  ?>
    <tr>
      <td><?= htmlspecialchars($p['d_apellido']) ?></td>
      <td><?= htmlspecialchars($p['d_nombre']) ?></td>
      <td><?= $p['f_nacimiento'] ?></td>
      <td><?= $edad ?: '-' ?></td>
      <td><?= $p['n_dni'] ?></td>
      <td><span class="badge bg-secondary"><?= $p['estado_desc'] ?></span></td>

      <td>
        <div class="d-flex gap-1">
          <a href="?edit=<?= $p['c_id'] ?>" class="btn btn-sm btn-primary">Editar</a>
          <a href="evoluciones_admin.php?id=<?= $p['c_id'] ?>" class="btn btn-sm btn-info">Evoluciones</a>
          <a href="historia_detalle.php?id=<?= $p['c_id'] ?>" class="btn btn-sm btn-info">Historia Médica</a>          
        </div>
      </td>   

    </tr>
  <?php endwhile; ?>
  </tbody>
</table>