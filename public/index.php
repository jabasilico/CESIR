<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once '../includes/header.php';
?>

<style>  
  .hero-right { background: url('https://image.pollinations.ai/prompt/Wide-angle%20interior%20of%20a%20modern%20rehabilitation%20clinic%20gym.%20Soft%20natural%20light%20filters%20through%20large%20windows%20onto%20light%20wooden%20floors.%20Equipment%20includes%20therapy%20beds%2C%20resistance%20bands%2C%20stability%20balls%2C%20and%20parallel%20bars%20neatly%20arranged.%20A%20therapist%20in%20scrubs%20guides%20a%20patient%20doing%20gentle%20exercises.%20Color%20palette%3A%20calming%20whites%2C%20sage%20green%2C%20and%20warm%20wood%20tones.%20Minimalist%2C%20clean%2C%20and%20welcoming%20atmosphere.%204K%20with%20shallow%20depth%20of%20field.?width=1600&height=900&nologo=true&style=photo') 
                                center/cover no-repeat; border-radius: .75rem; min-height: 520px; height: 100%}
  .btn-glow { transition: all .3s ease; }
  .btn-glow:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15); }
  .offcanvas {background: rgba(255,255,255,.75); backdrop-filter: blur(10px);}
</style>


<!-- Panel izquierdo -->
<div class="col-lg-4 d-flex">
  <div class="hero-left p-4 w-100 ..."></div>
</div>

<!-- Panel derecho -->
<div class="col-lg-8 d-flex">
  <div class="hero-right w-100 shadow-sm"></div>
</div>

<?php if (isset($_SESSION['error'])): ?>
  <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
  <?php unset($_SESSION['error']); ?>
<?php elseif (isset($_SESSION['ok'])): ?>
  <div class="alert alert-success"><?= $_SESSION['ok'] ?></div>
  <?php unset($_SESSION['ok']); ?>
<?php endif; ?>



<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<?php require_once '../includes/footer.php'; ?>