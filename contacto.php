<?php
header('Content-Type: application/json; charset=utf-8');

try {
    $nombre   = $_POST['nombre']   ?? '';
    $email    = $_POST['email']    ?? '';
    $consulta = $_POST['consulta'] ?? '';
    $destino  = $_POST['destino']  ?? 'jabasilico@gmail.com';

    if (!$nombre || !$email || !$consulta) {
        echo json_encode(["ok" => false, "mensaje" => "Faltan datos obligatorios"]);
        exit;
    }

    $asunto = "Consulta desde la web - CESIR";
    $cuerpo = "Nombre: $nombre\nEmail: $email\n\nConsulta:\n$consulta";

    $headers = "From: $email\r\n" .
               "Reply-To: $email\r\n" .
               "X-Mailer: PHP/" . phpversion();

    if (@mail($destino, $asunto, $cuerpo, $headers)) {
        echo json_encode(["ok" => true, "mensaje" => "Mensaje enviado correctamente"]);
    } else {
        echo json_encode(["ok" => false, "mensaje" => "No se pudo enviar el mensaje (problema con mail)"]);
    }
} catch (Throwable $e) {
    echo json_encode(["ok" => false, "mensaje" => "Error en el servidor: ".$e->getMessage()]);
}
?>