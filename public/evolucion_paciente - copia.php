<?php
session_start();
/* Solo usuarios logueados */
if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit;
}
require_once '../config/database.php';
require_once '../includes/header.php';

/* ---------- VARIABLES ---------- */
$idEsp   = $_SESSION['id_especialista'] ?? 0;   // rol 3
$dni     = trim($_GET['dni'] ?? '');
$paciente= null;

if ($dni) {
    $stmt = $conn->prepare(
        "SELECT p.c_id, p.d_apellido, p.d_nombre, m.d_mutual, p.n_afiliado
         FROM cl_paciente p
         LEFT JOIN cl_mutual m ON m.c_id = p.c_id_mutual
         WHERE p.n_dni = ? LIMIT 1"
    );
    $stmt->bind_param('s', $dni);
    $stmt->execute();
    $paciente = $stmt->get_result()->fetch_assoc();
}

/* ---------- ALTA / MODIFICACIÓN ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar'])) {
    $idPac  = (int)$_POST['id_paciente'];
    $idEvol = $_POST['id_evolucion'] ?? null;
    $fecha  = $_POST['f_evolucion'] ?: date('Y-m-d');
    $texto  = trim($_POST['d_evolucion']);

    if (!$texto) die("Debe completar la evolución.");

    if ($idEvol) {
        $stmt = $conn->prepare(
            "UPDATE cl_evolucion_paciente
             SET f_evolucion = ?, d_evolucion = ?
             WHERE c_id = ? AND c_id_especialista = ?"
        );
        $stmt->bind_param('ssii', $fecha, $texto, $idEvol, $idEsp);
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO cl_evolucion_paciente
             (c_id_paciente, c_id_especialista, f_carga, f_evolucion, d_evolucion)
             VALUES (?,?,?,?,?)"
        );
        $stmt->bind_param('iisss', $idPac, $idEsp, date('Y-m-d'), $fecha, $texto);
    }
    $stmt->execute();
    header("Location: evolucion_paciente.php?dni=$dni"); exit;
}

/* ---------- BORRAR ---------- */
if (isset($_GET['del'])) {
    $id = (int)$_GET['del'];
    $conn->query("DELETE FROM cl_evolucion_paciente WHERE c_id=$id AND c_id_especialista=$idEsp");
    header("Location: evolucion_paciente.php?dni=$dni"); exit;
}

/* ---------- LISTADO ---------- */
$edit = null;
if (isset($_GET['edit'])) {
    $edit = $conn->query(
        "SELECT * FROM cl_evolucion_paciente
         WHERE c_id=".(int)$_GET['edit']." AND c_id_especialista=$idEsp"
    )->fetch_assoc();
}

$evoluciones = [];
if ($paciente) {
    $evoluciones = $conn->query("
        SELECT ep.*, esp.d_apellido AS esp_ap, esp.d_nombre AS esp_nom, espd.d_especialidad AS d_especialidad
        FROM cl_evolucion_paciente ep
        JOIN cl_especialista esp ON esp.c_id = ep.c_id_especialista
        JOIN cl_especialidad espd ON espd.c_id = esp.c_id_especialidad
        WHERE ep.c_id_paciente = {$paciente['c_id']}
        ORDER BY ep.f_evolucion DESC
    ");
}
?>

<head>
  <meta charset="utf-8">
  <title>Evolución del Paciente</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    .header-obs{background:linear-gradient(135deg,#4CAF50,#81C784);color:#fff;padding:1rem 1.5rem;font-size:1.4rem;font-weight:600;}
  </style>
</head>


<div class="container mt-4">
    <div class="header-obs">Carga / Edición de Evolución</div>

    <!-- Búsqueda por DNI -->
    <form method="get" class="row g-2 mb-3">
        <div class="col-md-4">
            <label class="form-label">DNI Paciente</label>
            <input type="text" name="dni" class="form-control" value="<?= htmlspecialchars($dni) ?>" required>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-success w-100">Buscar</button>
        </div>
    </form>

    <?php if ($paciente): ?>
        <!-- Datos del paciente -->
        <div class="card card-body mb-3">
            <strong><?= htmlspecialchars($paciente['d_apellido']) ?>, <?= htmlspecialchars($paciente['d_nombre']) ?></strong>
            <span class="text-muted">Mutual: <?= htmlspecialchars($paciente['d_mutual'] ?? '-') ?> | Afiliado: <?= htmlspecialchars($paciente['n_afiliado'] ?? '-') ?></span>
        </div>

        <!-- Formulario de alta / edición -->
        <form method="POST" class="card card-body mb-4">
            <input type="hidden" name="id_paciente" value="<?= $paciente['c_id'] ?>">
            <input type="hidden" name="id_evolucion" value="<?= $edit['c_id'] ?? '' ?>">
            <input type="hidden" name="guardar" value="1">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Fecha Alta</label>
                    <input type="date" name="f_evolucion" class="form-control" value="<?= $edit['f_evolucion'] ?? date('Y-m-d') ?>">
                </div>
                <div class="col-md-9">
                    <label class="form-label">Evolución</label>
                    <textarea name="d_evolucion" rows="4" class="form-control" required><?= htmlspecialchars($edit['d_evolucion'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="text-end mt-3">
                <button type="submit" class="btn btn-success"><?= $edit ? 'Actualizar' : 'Guardar' ?></button>
                <?php if ($edit): ?>
                    <a href="evolucion_paciente.php?dni=<?= $dni ?>" class="btn btn-secondary">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Tabla de evoluciones -->
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Fecha Alta</th>
                        <th>Especialista</th>
                        <th>Especialidad</th>
                        <th>Evolución</th>
                        <th width="70"></th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($ev = $evoluciones->fetch_assoc()): ?>
                    <tr>
                        <td>
                          <?php
                            $fecha = $ev['f_evolucion'] ? DateTime::createFromFormat('Y-m-d', $ev['f_evolucion']) : null;
                            echo $fecha ? $fecha->format('d/m/Y') : '';
                          ?>
                        </td>
                        <td><?= htmlspecialchars($ev['esp_ap']) ?>, <?= htmlspecialchars($ev['esp_nom']) ?></td>
                        <td><?= nl2br(htmlspecialchars($ev['d_especialidad'])) ?></td>
                        <td><?= nl2br(htmlspecialchars($ev['d_evolucion'])) ?></td>
                        <td>
                            <a href="?edit=<?= $ev['c_id'] ?>&dni=<?= $dni ?>" class="btn btn-sm btn-warning">Editar</a>
                            <a href="?del=<?= $ev['c_id'] ?>&dni=<?= $dni ?>" class="btn btn-sm btn-danger"
                               onclick="return confirm('¿Eliminar?')">Borrar</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>