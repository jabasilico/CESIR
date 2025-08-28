<?php
session_start();
/* Solo especialistas (rol 3) */
if (($_SESSION['id_especialista'] ?? 0) == 0) {
    header('Location: ../public/login.php');
    exit;
}

require_once '../config/database.php';
require_once '../includes/header.php';

$idPac = (int)($_GET['id'] ?? 0);
$pac   = $conn->query("SELECT d_apellido, d_nombre, n_dni FROM cl_paciente WHERE c_id=$idPac")->fetch_assoc();
if (!$pac) die('Paciente no encontrado');

$idEsp = $_SESSION['id_especialista'];

/* ---------- ALTA / MODIFICACIÓN ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar'])) {
    $idEvol = $_POST['id_evolucion'] ?? null;
    $fecha  = $_POST['f_evolucion'] ?: date('Y-m-d');
    $texto  = trim($_POST['d_evolucion']);

    if (!$texto) die("Debe completar la evolución.");

    if ($idEvol) {
        $stmt = $conn->prepare(
            "UPDATE cl_evolucion_paciente
             SET f_evolucion = ?, d_evolucion = ?
             WHERE c_id = ? AND c_id_paciente = ? AND c_id_especialista = ?"
        );
        $stmt->bind_param('ssiii', $fecha, $texto, $idEvol, $idPac, $idEsp);
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO cl_evolucion_paciente
             (c_id_paciente, c_id_especialista, f_carga, f_evolucion, d_evolucion)
             VALUES (?,?,?,?,?)"
        );
        $stmt->bind_param('iisss', $idPac, $idEsp, date('Y-m-d'), $fecha, $texto);
    }
    $stmt->execute();
    header("Location: evolucion_paciente_detalle.php?id=$idPac"); exit;
}

/* ---------- BORRAR ---------- */
if (isset($_GET['del'])) {
    $id = (int)$_GET['del'];
    $conn->query("DELETE FROM cl_evolucion_paciente WHERE c_id=$id AND c_id_paciente=$idPac AND c_id_especialista=$idEsp");
    header("Location: evolucion_paciente_detalle.php?id=$idPac"); exit;
}

/* ---------- EDICIÓN ---------- */
$edit = null;
if (isset($_GET['edit'])) {
    $edit = $conn->query(
        "SELECT * FROM cl_evolucion_paciente
         WHERE c_id=".(int)$_GET['edit']." AND c_id_paciente=$idPac AND c_id_especialista=$idEsp"
    )->fetch_assoc();
}

/* ---------- LISTADO ---------- */
$evoluciones = $conn->query("
    SELECT ep.*, esp.d_apellido AS esp_ap, esp.d_nombre AS esp_nom
    FROM cl_evolucion_paciente ep
    JOIN cl_especialista esp ON esp.c_id = ep.c_id_especialista
    WHERE ep.c_id_paciente = $idPac AND ep.c_id_especialista = $idEsp
    ORDER BY ep.f_evolucion DESC
");
?>

<div class="container mt-4">
    <div class="header-obs">
        Evolución de <?= htmlspecialchars($pac['d_apellido']) ?>, <?= htmlspecialchars($pac['d_nombre']) ?>
    </div>

    <!-- Botón Volver -->
    <a href="evolucion_paciente.php" class="btn btn-secondary mb-3">← Volver a la lista</a>

    <!-- Formulario -->
    <form method="POST" class="card card-body mb-4">
        <input type="hidden" name="id_paciente" value="<?= $idPac ?>">
        <input type="hidden" name="id_evolucion" value="<?= $edit['c_id'] ?? '' ?>">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Fecha Sesión</label>
                <input type="date" name="f_evolucion" class="form-control"
                       value="<?= $edit['f_evolucion'] ?? date('Y-m-d') ?>">
            </div>
            <div class="col-md-9">
                <label class="form-label">Evolución</label>
                <textarea name="d_evolucion" rows="4" class="form-control" required><?= htmlspecialchars($edit['d_evolucion'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="text-end mt-3">
            <button type="submit" name="guardar" class="btn btn-success"><?= $edit ? 'Actualizar' : 'Guardar' ?></button>
            <?php if ($edit): ?>
                <a href="evolucion_paciente_detalle.php?id=<?= $idPac ?>" class="btn btn-secondary">Cancelar</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Tabla de evoluciones -->
    <div class="table-responsive">
        <table class="table table-hover">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>Especialista</th>
                    <th>Evolución</th>
                    <th width="70"></th>
                </tr>
            </thead>
            <tbody>
            <?php while ($ev = $evoluciones->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($ev['f_evolucion']) ?></td>
                    <td><?= htmlspecialchars($ev['esp_ap']) ?>, <?= htmlspecialchars($ev['esp_nom']) ?></td>
                    <td><?= nl2br(htmlspecialchars($ev['d_evolucion'])) ?></td>
                    <td>
                        <a href="?edit=<?= $ev['c_id'] ?>&id=<?= $idPac ?>" class="btn btn-sm btn-warning">Editar</a>
                        <a href="?del=<?= $ev['c_id'] ?>&id=<?= $idPac ?>" class="btn btn-sm btn-danger"
                           onclick="return confirm('¿Eliminar?')">Borrar</a>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>