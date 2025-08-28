<?php
require_once '../../config/database.php';  // tu conexión $conn
header('Content-Type: application/json');

$espId = (int)($_GET['especialidad'] ?? 0);
$out   = [];

if ($espId) {
    $st = $conn->prepare(
        "SELECT c_id, CONCAT(d_apellido, ', ', d_nombre) AS nombre
         FROM cl_especialista
         WHERE c_id_especialidad = ? AND f_baja IS NULL
         ORDER BY d_apellido, d_nombre"
    );
    $st->bind_param('i', $espId);
    $st->execute();
    $rs = $st->get_result();
    while ($r = $rs->fetch_assoc()) $out[] = $r;
}

echo json_encode($out);