<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = $_POST['usuario'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT u.c_id, u.d_apellido, u.d_nombre, u.d_password, r.d_rol, 
                                   u.c_id_especialista 
                            FROM cl_usuario u join cl_rol r on u.c_id_rol=r.c_id 
                            WHERE c_usuario = ?");
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['d_password'])) {
        $_SESSION['user_id']         = $user['c_id'];
        $_SESSION['nombre']          = $user['d_nombre'];
        $_SESSION['apellido']        = $user['d_Apellido'];
        $_SESSION['is_admin']        = $user['d_rol'] === 'admin';
        $_SESSION['is_paciente']     = $user['d_rol'] === 'paciente';
        $_SESSION['id_especialista']  = $user['c_id_especialista'];
        $_SESSION['is_especialista'] = $user['d_rol'] === 'especialista';
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
  <!-- Favicon -->
  <link rel="icon" type="image/png" href="../LogoTransparente.png">  
  <title>Login - Clinica</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-green-100 via-white to-green-200 min-h-screen flex items-center justify-center">
  <div class="w-full max-w-md mx-auto bg-white rounded-xl shadow-lg p-8">

    <div class="flex flex-col items-center mb-6">
      <img src="../logoCISER.png" alt="CESIR" class="h-12 mb-2">
      <h2 class="text-2xl font-bold text-green-700">Acceso a CESIR</h2>
      <p class="text-gray-500 text-sm">Ingresa tus credenciales para continuar</p>
    </div>

    <?php if (isset($error)): ?>
      <div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4 text-center">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>
    <form method="POST">
      <div class="mb-3">
        <label class="block text-gray-700 mb-1">Usuario</label>
        <input type="text" name="usuario" class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" required>
      </div>
      <div class="mb-3">
        <label class="block text-gray-700 mb-1">Contraseña</label>
        <input type="password" name="password" class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-400" required>
      </div>
      <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-2 rounded transition">Ingresar</button>
    </form>
  </div>
</body>
</html>