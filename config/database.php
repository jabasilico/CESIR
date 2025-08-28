<?php
$host = '192.168.1.121';
//$host = '170.155.148.123';
$port = '22236';
$db   = 'ClinicaDB';
$user = 'root'; // cambia si usas otro
$pass = 'Tgpsql.4647';

$conn = new mysqli($host, $user, $pass, $db, $port);
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>
