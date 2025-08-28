<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id         = $_SESSION['user_id'];
    $actual     = $_POST['actual'];
    $nueva      = $_POST['nueva'];
    $confirmar  = $_POST['confirmar'];

    if ($nueva !== $confirmar) {
        $_SESSION['error'] = 'Las nuevas contraseñas no coinciden.';
        header('Location: index.php'); exit;
    }

    // Verificar contraseña actual
    $stmt = $conn->prepare("SELECT d_password FROM cl_usuario WHERE c_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $hash = $stmt->get_result()->fetch_assoc()['d_password'];

    if (!password_verify($actual, $hash)) {
        $_SESSION['error'] = 'Contraseña actual incorrecta.';
        header('Location: index.php'); exit;
    }

    // Actualizar
    $nuevaHash = password_hash($nueva, PASSWORD_DEFAULT);
    $conn->prepare("UPDATE cl_usuario SET d_password = ? WHERE c_id = ?")
         ->execute([$nuevaHash, $id]);

    $_SESSION['ok'] = 'Contraseña actualizada correctamente.';
    header('Location: index.php'); exit;
}