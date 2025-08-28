<?php
session_start();
if (!($_SESSION['is_admin'] ?? false)) {
    header('Location: ../index.php'); exit;
}
require_once '../config/database.php';
require_once '../includes/header.php';

$id_pac = (int)($_GET['id'] ?? 0);
$pac = $conn->query("SELECT d_apellido, d_nombre FROM cl_paciente WHERE c_id=$id_pac")->fetch_assoc();
if (!$pac) die('Paciente no encontrado');

// Alta / Edición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $obs   = trim($_POST['observacion']);
    $fecha = $_POST['fecha'] ?: date('Y-m-d');

    if (isset($_POST['id_obs'])) {                 // EDITAR
        $id_obs = (int)$_POST['id_obs'];
        $stmt = $conn->prepare(
            "UPDATE cl_observacion
             SET f_fecha = ?, d_observacion = ?
             WHERE c_id = ? AND c_id_paciente = ?"
        );
        $stmt->bind_param('ssii', $fecha, $obs, $id_obs, $id_pac);
    } else {                                        // NUEVA
        $stmt = $conn->prepare(
            "INSERT INTO cl_observacion (c_id_paciente, f_fecha, d_observacion)
             VALUES (?,?,?)"
        );
        $stmt->bind_param('iss', $id_pac, $fecha, $obs);
    }
    $stmt->execute();
    header("Location: observaciones.php?id=$id_pac"); exit;
}

$observaciones = $conn->query(
    "SELECT * FROM cl_observacion WHERE c_id_paciente=$id_pac ORDER BY f_fecha DESC"
);

$edit = null;
if (isset($_GET['edit_obs'])) {
    $edit = $conn->query("SELECT * FROM cl_observacion WHERE c_id=".(int)$_GET['edit_obs'])->fetch_assoc();
}
?>

<div class="container mt-4">
    <h3 class="titulo-resaltado">
        Observaciones de <?= htmlspecialchars($pac['d_apellido']) ?>, <?= htmlspecialchars($pac['d_nombre']) ?>
    </h3>

    <!-- Botón Volver -->
    <a href="pacientes.php?edit=<?= $id_pac ?>" class="btn btn-secondary mb-3">← Volver al paciente</a>

    <!-- Formulario -->
    <form method="POST" class="card card-body mb-4">
        <?php if ($edit): ?>
            <input type="hidden" name="id_obs" value="<?= $edit['c_id'] ?>">
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Fecha</label>
                <input type="date" name="fecha" class="form-control"
                       value="<?= $edit['f_fecha'] ?? date('Y-m-d') ?>">
            </div>
            <div class="col-md-9">
                <label class="form-label">Observación</label>
                <textarea name="observacion" rows="4" class="form-control" required><?= htmlspecialchars($edit['d_observacion'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="text-end mt-3">
            <button type="submit" class="btn btn-success"><?= $edit ? 'Actualizar' : 'Guardar' ?></button>
            <?php if ($edit): ?>
                <a href="observaciones.php?id=<?= $id_pac ?>" class="btn btn-secondary">Cancelar</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Tabla -->
    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>Observación</th>
                    <th width="90"></th>
                </tr>
            </thead>
            <tbody>
            <?php while ($o = $observaciones->fetch_assoc()): ?>
                <tr>
                    <td><?= $o['f_fecha'] ?></td>
                    <td><?= nl2br(htmlspecialchars($o['d_observacion'])) ?></td>
                    <td>
                        <a href="observaciones.php?id=<?= $id_pac ?>&edit_obs=<?= $o['c_id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
