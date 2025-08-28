<?php
// Protección básica: si no hay sesión y no estamos en login, redirigir
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) !== 'login.php') {
    header('Location: /public/login.php');
    exit;
}
?>

<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Centro Especializado en Salud Integral y Rehabilitación</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- iOS / Safari -->
    <link rel="apple-touch-icon" href="LogoTransparente.png">

    <!-- Tamaños estándar -->
    <link rel="icon" type="image/png" sizes="32x32" href="LogoTransparente.png">
    <link rel="icon" type="image/png" sizes="16x16" href="LogoTransparente.png">

    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<nav class="navbar px-3" style="background-color: #d1fae5;">
  <div class="d-flex align-items-center">
    <img src="../LogoTransparente.png" alt="Logo CESIR" style="height:40px; width:auto;" class="me-2 align-middle" />
    <span class="navbar-brand mb-0 h1" style="color: #065f46;">Centro Especializado en Salud Integral y Rehabilitación</span>
  </div>

  <!-- Volver al index + Bienvenido alineados -->
  <div class="d-flex align-items-center gap-2">
    <a href="../public/index.php" class="btn btn-sm" style="background-color: #8e44ad; color: #f6f7f6ff;">
      <i class="bi bi-house-door"></i> Inicio
    </a>
    <span class="ms-2" style="color: #065f46;">
      Bienvenido, 
      <a href="#" style="color: #065f46; text-decoration: underline;" data-bs-toggle="modal" data-bs-target="#modalClave">
        <strong><?= htmlspecialchars($_SESSION['nombre'] ?? '') ?></strong>
      </a>
    </span>
  </div>

  <!-- Cerrar sesión -->
  <a href="../public/logout.php" class="btn btn-outline-danger btn-sm ms-2">
    <i class="bi bi-box-arrow-right"></i> Cerrar sesión
  </a>
</nav>


<!-- Sidebar fijo -->
<div class="d-flex">
  <aside id="sidebar" class="d-flex flex-column p-3"
         style="width: 220px; min-height: 100vh; background-color: #d1fae5; color: #065f46;">
    <h5 class="mb-4">Menú</h5>

    <!--Opciones para el administrador -->
    <?php if ($_SESSION['is_admin'] ?? false): ?>
      <a href="../public/dashboard.php" class="btn btn-violet mb-2"> 
        <i class="bi bi-speedometer2 me-2"></i>Dashboard
      </a>

      <a href="../public/especialistas.php" class="btn btn-violet mb-2">
        <i class="bi bi-person-badge me-2"></i>Especialistas
      </a>

      <a href="../public/mutuales.php" class="btn btn-violet mb-2">
        <i class="bi bi-shield-plus me-2"></i>Mutuales
      </a>
      
      <a href="../public/pacientes.php" class="btn btn-violet">
        <i class="bi bi-people me-2"></i>Pacientes
      </a>

    <?php endif; ?>

    <!-- Opciones para los pacientes -->
    <?php if ($_SESSION['is_paciente'] ?? false): ?>
      <a href="../public/mis-pagos.php" class="btn btn-violet">
        <i class="bi bi-receipt me-2"></i>Mis turnos
      </a>
           
    <?php endif; ?>

    <!-- Opciones para los especialistas -->
    <?php if ($_SESSION['is_especialista'] ?? false): ?>
      <a href="../public/historia_seleccion.php" class="btn btn-violet mb-2">
        <i class="bi bi-receipt me-2"></i>Historia Médica
      </a>   

      <a href="../public/evolucion_paciente.php" class="btn btn-violet mb-2">
        <i class="bi bi-receipt me-2"></i>Evolución de Pacientes
      </a>
    <?php endif; ?>

  </aside>

  <!-- Contenido principal -->
  <main class="flex-fill p-4">

<main class="container-fluid py-4">

  <!-- Modal cambiar contraseña -->
<div class="modal fade" id="modalClave" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="../public/cambiar-clave.php">
        <div class="modal-header">
          <h5 class="modal-title">Cambiar contraseña</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Contraseña actual</label>
            <input type="password" name="actual" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Nueva contraseña</label>
            <input type="password" name="nueva" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Repetir nueva contraseña</label>
            <input type="password" name="confirmar" class="form-control" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>