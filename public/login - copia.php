<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['usuario'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT u.c_id, u.d_apellido, u.d_nombre, u.d_password, r.d_rol
                            FROM cl_usuario u join cl_rol r on u.c_id_rol=r.c_id 
                            WHERE c_usuario = ?");
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['d_password'])) {
        $_SESSION['user_id'] = $user['c_id'];
        $_SESSION['nombre'] = $user['d_nombre'];
        $_SESSION['apellido'] = $user['d_Apellido'];
        $_SESSION['is_admin'] = $user['d_rol'] === 'admin';
        header("Location: index.php");
        exit;
    } else {
        $error = "Usuario o contraseña incorrectos.";
    }
}
?>

<!doctype html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Login - Clinica</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="height: 100vh;">
  <div class="card p-4 shadow" style="width: 400px;">
    <h4 class="text-center mb-3">Iniciar Sesión</h4>
    <?php if (isset($error)): ?>
      <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    <form method="POST">
      <div class="mb-3">
        <label class="form-label">Usuario</label>
        <input type="text" name="usuario" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Contraseña</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button class="btn btn-primary w-100">Ingresar</button>
    </form>
  </div>
</body>
</html>