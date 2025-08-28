<?php
session_start();
$idEsp = $_SESSION['id_especialista'] ?? 0;
if (!$idEsp) {
    header('Location: ../public/login.php'); exit;
}
require_once '../config/database.php';
require_once '../includes/header.php';

$idPac = (int)($_GET['id'] ?? 0);
$pac   = $conn->query("SELECT d_apellido, d_nombre FROM cl_paciente WHERE c_id=$idPac")->fetch_assoc();
if (!$pac) die('Paciente no encontrado');

/* ---------- HISTORIA A EDITAR (si la piden) ---------- */
$idHistEdit = (int)($_GET['edit_hist'] ?? 0);
$editHist   = $idHistEdit
    ? $conn->query("SELECT * FROM cl_historia_medica WHERE c_id=$idHistEdit AND c_id_paciente=$idPac")->fetch_assoc()
    : null;

/* ---------- ALTA / EDICIÓN ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar'])) {
    $idHist = (int)($editHist['c_id'] ?? 0);
    $fecha  = $_POST['f_alta'] ?: date('Y-m-d');
    $texto  = trim($_POST['d_historia_medica']);

    if (!$texto) die("Debe completar la historia.");

    if ($idHist) {
        $stmt = $conn->prepare(
            "UPDATE cl_historia_medica
             SET f_alta = ?, d_historia_medica = ?
             WHERE c_id = ? AND c_id_paciente = ? AND c_id_especialista = ?"
        );
        $stmt->bind_param('ssiii', $fecha, $texto, $idHist, $idPac, $idEsp);
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO cl_historia_medica
             (c_id_paciente, c_id_especialista, f_alta, d_historia_medica)
             VALUES (?,?,?,?)"
        );
        $stmt->bind_param('iiss', $idPac, $idEsp, $fecha, $texto);
    }
    $stmt->execute();
    $idHist = $idHist ?: $conn->insert_id;

    /* ---------- PLAN DE TRATAMIENTO ---------- */
    /* BORRO y GRABO planes (siempre) */
    $conn->query("DELETE FROM cl_plan_tratamiento WHERE c_id_historia_medica=$idHist");

    $esps = $_POST['c_id_especialidad'] ?? [];
    $coms = $_POST['comentario'] ?? [];
    foreach ($esps as $i => $esp) {
        $espId = (int)$esp;
        $com   = trim($coms[$i] ?? '');
        if ($espId && $com !== '') {
            $ins = $conn->prepare("INSERT INTO cl_plan_tratamiento (c_id_historia_medica, c_id_especialidad, d_comentario) VALUES (?,?,?)");
            $ins->bind_param('iis', $idHist, $espId, $com);
            $ins->execute();
        }
    }
    header("Location: historia_detalle.php?id=$idPac"); exit;
}

/* ---------- BORRAR ---------- */
if (isset($_GET['del'])) {
    $id = (int)$_GET['del'];
    $conn->query("DELETE FROM cl_historia_medica WHERE c_id=$id AND c_id_paciente=$idPac");
    header("Location: historia_detalle.php?id=$idPac"); exit;
}

/* ---------- HISTORIAS PARA TABLA EDICIÓN ---------- */
$historias = $conn->query("
    SELECT hm.*, esp.d_apellido AS esp_ap, esp.d_nombre AS esp_nom
    FROM cl_historia_medica hm
    JOIN cl_especialista esp ON esp.c_id = hm.c_id_especialista
    WHERE hm.c_id_paciente = $idPac
    ORDER BY hm.f_alta DESC
");

/* ---------- ESPECIALIDADES ---------- */
$especialidades = $conn->query("SELECT c_id, d_especialidad FROM cl_especialidad ORDER BY d_especialidad");
?>

<div class="container mt-4">
    <div class="header-obs">
        Historia Médica – <?= htmlspecialchars($pac['d_apellido']) ?>, <?= htmlspecialchars($pac['d_nombre']) ?>
    </div>
    <a href="historia_seleccion.php" class="btn btn-secondary mb-3">← Volver a la lista</a>

    <!-- HISTORIA -->
    <form method="POST" class="card card-body mb-4">
        <input type="hidden" name="id_historia" value="<?= $editHist['c_id'] ?? '' ?>">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Fecha Alta</label>
                <input type="date" name="f_alta" class="form-control"
                       value="<?= $editHist['f_alta'] ?? date('Y-m-d') ?>">
            </div>
            <div class="col-md-12">
                <label class="form-label">Historia Médica</label>
                <textarea name="d_historia_medica" rows="5" class="form-control" required><?= htmlspecialchars($editHist['d_historia_medica'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- PLAN DE TRATAMIENTO DINÁMICO -->
        <h5 class="mt-4">Plan de Tratamiento</h5>
        <div id="planes" class="row g-2">
            <?php
            /* cargar planes si estamos editando */
            $planes = [];
            if ($editHist) {
                $planes = $conn->query("SELECT c_id_especialidad, d_comentario FROM cl_plan_tratamiento WHERE c_id_historia_medica={$editHist['c_id']}");
            }
            ?>
            <template id="tplPlan">
                <div class="col-md-12">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <select name="c_id_especialidad[]" class="form-select">
                                <option value="">-- Especialidad --</option>
                                <?php foreach ($especialidades as $esp): ?>
                                    <option value="<?= $esp['c_id'] ?>"><?= $esp['d_especialidad'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <textarea name="comentario[]" class="form-control" rows="2" placeholder="Comentario"></textarea>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-outline-danger" onclick="quitar(this)">−</button>
                        </div>
                    </div>
                </div>
            </template>

            <?php foreach ($planes as $pl): ?>
                <div class="col-md-12">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <select name="c_id_especialidad[]" class="form-select">
                                <option value="">-- Especialidad --</option>
                                <?php foreach ($especialidades as $esp): ?>
                                    <option value="<?= $esp['c_id'] ?>" <?= ($esp['c_id'] == $pl['c_id_especialidad']) ? 'selected' : '' ?>>
                                        <?= $esp['d_especialidad'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <textarea name="comentario[]" class="form-control" rows="2"><?= htmlspecialchars($pl['d_comentario']) ?></textarea>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-outline-danger" onclick="quitar(this)">−</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="text-end mt-3">
            <button type="button" class="btn btn-outline-primary btn-sm" onclick="agregar()">+ Agregar Especialidad</button>
            <button type="submit" name="guardar" class="btn btn-success"><?= $editHist ? 'Actualizar' : 'Guardar' ?></button>
            <?php if ($editHist): ?>
                <a href="historia_detalle.php?id=<?= $idPac ?>" class="btn btn-secondary">Cancelar</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Lista de historias -->
    <h5 class="mt-4">Historias previas</h5>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>Especialista</th>
                    <th>Historia</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($h = $historias->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($h['f_alta']) ?></td>
                    <td><?= htmlspecialchars($h['esp_ap']) ?>, <?= htmlspecialchars($h['esp_nom']) ?></td>
                    <td><?= nl2br(htmlspecialchars($h['d_historia_medica'])) ?></td>
                    <td>
                        <a href="historia_detalle.php?id=<?= $idPac ?>&edit_hist=<?= $h['c_id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                        <a href="?del=<?= $h['c_id'] ?>&id=<?= $idPac ?>" class="btn btn-sm btn-danger"
                           onclick="return confirm('¿Eliminar?')">Borrar</a>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- JS dinámico -->
<script>
function agregar() {
    const tpl = document.getElementById('tplPlan');
    const clone = tpl.content.cloneNode(true);
    document.getElementById('planes').appendChild(clone);
}
function quitar(btn) {
    btn.closest('.col-md-12').remove();
}
</script>

<?php require_once '../includes/footer.php'; ?>