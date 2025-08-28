<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Test Modal</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="m-4">

<!-- Botón -->
<button type="button" class="btn btn-primary"
        onclick="new bootstrap.Modal(document.getElementById('m')).show()">
  Abrir Modal
</button>

<!-- Modal -->
<div class="modal fade" id="m" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Prueba</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">¡Funciona!</div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>