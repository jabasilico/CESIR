<?php
require_once '../../config/database.php';
$id = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare(
    "SELECT e.d_nombre, g.d_grupo, re.n_series, re.n_repeticiones, re.n_peso_sugerido
     FROM gy_rutina_ejercicio re
     JOIN gy_ejercicio e ON re.c_id_ejercicio = e.c_id
     JOIN gy_grupo_muscular g ON e.c_id_grupo = g.c_id
     WHERE re.c_id_rutina = ?
     ORDER BY re.n_orden"
);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
?>
<table class="table table-sm">
  <thead>
    <tr><th>Ejercicio</th><th>Grupo</th><th>Series</th><th>Reps</th><th>Peso</th></tr>
  </thead>
  <tbody>
    <?php while ($row = $result->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($row['d_nombre']) ?></td>
        <td><?= htmlspecialchars($row['d_grupo']) ?></td>
        <td><?= $row['n_series'] ?></td>
        <td><?= htmlspecialchars($row['n_repeticiones']) ?></td>
        <td><?= $row['n_peso_sugerido'] ?></td>
      </tr>
    <?php endwhile; ?>
  </tbody>
</table>