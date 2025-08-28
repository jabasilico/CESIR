<?php
session_start();
ob_start();
if (!($_SESSION['is_admin'] ?? false)) {
    header('Location: ../index.php'); exit;
}

require_once '../config/database.php';
require_once '../includes/header.php';

/* ---------- VARIABLES ---------- */
$idPac = (int)($_GET['id'] ?? 0);
$pac   = $conn->query("SELECT d_apellido, d_nombre FROM cl_paciente WHERE c_id=$idPac")->fetch_assoc();
if (!$pac) die('Paciente no encontrado');

/* ---------- ALTA / MODIFICACIÓN ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar'])) {
   
    $idEvol = $_POST['id_evolucion'] ?? null;
    $fecha  = $_POST['f_evolucion'] ?: date('Y-m-d');
    $texto  = trim($_POST['d_evolucion']);
    $idEsp  = (int)$_POST['c_id_especialista'];
    $f_carga = date('Y-m-d');

    if (!$texto || !$idEsp) {
        $_SESSION['msg'] = 'Completa todos los campos.';
        header("Location: evoluciones_admin.php?id=$idPac"); exit;
    }

    if ($idEvol) {
        $stmt = $conn->prepare(
            "UPDATE cl_evolucion_paciente
             SET f_evolucion = ?, d_evolucion = ?, c_id_especialista = ?
             WHERE c_id = ? AND c_id_paciente = ?"
        );
        $stmt->bind_param('ssiii', $fecha, $texto, $idEsp, $idEvol, $idPac);
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO cl_evolucion_paciente
             (c_id_paciente, c_id_especialista, f_carga, f_evolucion, d_evolucion)
             VALUES (?,?,?,?,?)"
        );
        $stmt->bind_param('iisss', $idPac, $idEsp, $f_carga, $fecha, $texto);
    }
    $stmt->execute();
    $_SESSION['msg'] = $idEvol ? 'Actualizado' : 'Guardado';
    header("Location: evoluciones_admin.php?id=$idPac"); exit;
}

/* ---------- BORRAR ---------- */
if (isset($_GET['del'])) {
    $id = (int)$_GET['del'];
    $conn->query("DELETE FROM cl_evolucion_paciente WHERE c_id=$id AND c_id_paciente=$idPac");
    $_SESSION['msg'] = 'Eliminado';
    header("Location: evoluciones_admin.php?id=$idPac"); exit;
}

/* ---------- EDICIÓN ---------- */
$edit = null;
if (isset($_GET['edit'])) {
    $edit = $conn->query(
        "SELECT * FROM cl_evolucion_paciente
         WHERE c_id=".(int)$_GET['edit']." AND c_id_paciente=$idPac"
    )->fetch_assoc();
}

/* ---------- LISTADO ---------- */
$evoluciones = $conn->query("
    SELECT ep.*, esp.d_apellido AS esp_ap, esp.d_nombre AS esp_nom, espd.d_especialidad AS d_especialidad
    FROM cl_evolucion_paciente ep
    JOIN cl_especialista esp ON esp.c_id = ep.c_id_especialista
    JOIN cl_especialidad espd ON espd.c_id = esp.c_id_especialidad
    WHERE ep.c_id_paciente = $idPac
    ORDER BY ep.f_evolucion DESC
");

/* ---------- COMBO ESPECIALISTAS ---------- */
$especialistas = $conn->query(
    "SELECT c_id, CONCAT(d_apellido, ', ', d_nombre) AS nombre_completo
     FROM cl_especialista WHERE f_baja IS NULL ORDER BY d_apellido"
);
?>

<div class="container mt-4">
    <h3 class="titulo-resaltado">
        Evoluciones de <?= htmlspecialchars($pac['d_apellido']) ?>, <?= htmlspecialchars($pac['d_nombre']) ?>
    </h3>

    <!-- Mensaje -->
    <?php if (isset($_SESSION['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['msg']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['msg']); ?>
    <?php endif; ?>

    <!-- Botón Volver al paciente 
    <a href="pacientes.php?edit=<?= $idPac ?>" class="btn btn-secondary mb-3">← Volver al paciente</a>-->
    <a href="pacientes.php" class="btn btn-secondary mb-3">← Volver al paciente</a>

    <!-- Formulario -->
    <form method="POST" action="evoluciones_admin.php?id=<?= $idPac ?>" class="card card-body mb-4">
        <input type="hidden" name="id_paciente" value="<?= $idPac ?>">
        <input type="hidden" name="id_evolucion" value="<?= $edit['c_id'] ?? '' ?>">

        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Fecha Alta</label>
                <input type="date" name="f_evolucion" class="form-control"
                       value="<?= $edit['f_evolucion'] ?? date('Y-m-d') ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label">Especialista</label>
                <select name="c_id_especialista" class="form-select" required>
                    <option value="">-- Seleccione --</option>
                    <?php while ($esp = $especialistas->fetch_assoc()): ?>
                        <option value="<?= $esp['c_id'] ?>"
                            <?= ($edit['c_id_especialista'] ?? '') == $esp['c_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($esp['nombre_completo']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="col-md-12">
                <label class="form-label">Evolución</label>
                <textarea name="d_evolucion" rows="4" class="form-control" required><?= htmlspecialchars($edit['d_evolucion'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="text-end mt-3">

            <button type="submit" name="guardar" class="btn btn-success">
                <?= $edit ? 'Actualizar' : 'Guardar' ?>
            </button>

            <?php if ($edit): ?>
                <a href="evoluciones_admin.php?id=<?= $idPac ?>" class="btn btn-secondary">Cancelar</a>
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
                    <th>Especialidad</th>
                    <th>Evolución</th>
                    <th width="70"></th>
                </tr>
            </thead>
            <tbody>
            <?php while ($ev = $evoluciones->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($ev['f_evolucion']) ?></td>
                    <td><?= htmlspecialchars($ev['esp_ap']) ?>, <?= htmlspecialchars($ev['esp_nom']) ?></td>
                    <td><?= htmlspecialchars($ev['d_especialidad']) ?></td>
                    <td><?= nl2br(htmlspecialchars($ev['d_evolucion'])) ?></td>
                    <td>
                        <a href="?edit=<?= $ev['c_id'] ?>&id=<?= $idPac ?>" class="btn btn-sm btn-warning">Editar</a>
                        <a href="?del=<?= $ev['c_id'] ?>&id=<?= $idPac ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar?')">Borrar</a>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>


<?php require_once '../includes/footer.php'; ob_end_flush(); ?>
