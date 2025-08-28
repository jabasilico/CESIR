<?php
ob_start();
session_start();
$idEsp    = $_SESSION['id_especialista'] ?? 0;
$esAdmin  = ($_SESSION['is_admin'] ?? false);

if (!$idEsp && !$esAdmin) {
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

/* ---------- CONTROL DE BLOQUEO ---------- */
$hoy = date('Y-m-d');
$bloqueada = false;
if ($editHist) {
    $bloqueada = $editHist['f_fin'] && $editHist['f_fin'] <= $hoy;
}

/* ---------- ALTA / EDICIÓN ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar'])) {
    if ($bloqueada) {
        die("Historia cerrada. No se puede modificar.");
    }

    $idHist = $editHist['c_id'] ?? null;
    $fecha  = $_POST['f_alta'] ?: date('Y-m-d');
    $texto  = trim($_POST['d_historia_medica']);
    $fFin   = (!empty($_POST['f_fin'])) ? $_POST['f_fin'] : null;

    if (!$texto) die("Debe completar la historia.");

    if ($idHist) {
        $stmt = $conn->prepare(
            "UPDATE cl_historia_medica
             SET f_alta = ?, d_historia_medica = ?, f_fin = ?
             WHERE c_id = ? AND c_id_paciente = ? AND c_id_especialista = ?"
        );
        $stmt->bind_param('ssssii', $fecha, $texto, $fFin, $idHist, $idPac, $idEsp);
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO cl_historia_medica
             (c_id_paciente, c_id_especialista, f_alta, d_historia_medica, f_fin)
             VALUES (?,?,?,?,?)"
        );
        $stmt->bind_param('iisss', $idPac, $idEsp, $fecha, $texto, $fFin);
    }
    $stmt->execute();
    $idHist = $idHist ?: $conn->insert_id;

    /* PLANES */
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
    $conn->query("DELETE FROM cl_plan_tratamiento WHERE c_id_historia_medica=$id");
    $conn->query("DELETE FROM cl_historia_medica WHERE c_id=$id AND c_id_paciente=$idPac");
    header("Location: historia_detalle.php?id=$idPac"); exit;
}

/* ---------- HISTORIAS PARA TABLA ---------- */
$historias = $conn->query("
    SELECT hm.*, esp.d_apellido AS esp_ap, esp.d_nombre AS esp_nom
    FROM cl_historia_medica hm
    JOIN cl_especialista esp ON esp.c_id = hm.c_id_especialista
    WHERE hm.c_id_paciente = $idPac
    ORDER BY hm.f_alta DESC
");

/* ---------- ESPECIALIDADES ---------- */
$especialidades = $conn->query("SELECT c_id, d_especialidad FROM cl_especialidad ORDER BY d_especialidad");

/* ---------- PLAN DE TRATAMIENTO (SOLO LECTURA) ---------- */
$planes = $editHist
    ? $conn->query("SELECT esp.d_especialidad, pt.d_comentario
                    FROM cl_plan_tratamiento pt
                    JOIN cl_especialidad esp ON esp.c_id = pt.c_id_especialidad
                    WHERE pt.c_id_historia_medica = {$editHist['c_id']}")
    : false;
?>
<!doctype html>
<html lang="es">
<head>
  <title>Historia Médica</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    .header-obs{background:linear-gradient(135deg,#4CAF50,#81C784);color:#fff;padding:1rem 1.5rem;font-size:1.4rem;font-weight:600;}
    .bloqueada{pointer-events:none;opacity:.65;}
  </style>
</head>
<body>
<?php require_once '../includes/header.php'; ?>

<div class="container mt-4">
    <div class="header-obs">Historia Médica – <?= htmlspecialchars($pac['d_apellido']) ?>, <?= htmlspecialchars($pac['d_nombre']) ?></div>

    <!-- BOTONES SUPERIORES -->
    <div class="d-flex justify-content-between mb-3">
        <?php
        $volver = ($_SESSION['is_admin'] ?? false)
            ? 'pacientes.php'   // administrador
            : 'historia_seleccion.php';  // especialista
        ?>
        <a href="<?= $volver ?>" class="btn btn-secondary">← Volver</a>    
    </div>

    <!-- HISTORIA -->
    <form method="POST" class="card card-body mb-4 <?= $bloqueada ? 'bloqueada' : '' ?>">
        <input type="hidden" name="id_historia" value="<?= $editHist['c_id'] ?? '' ?>">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Fecha Alta</label>
                <input type="date" name="f_alta" class="form-control"
                       value="<?= $editHist['f_alta'] ?? date('Y-m-d') ?>"
                       <?= $bloqueada ? 'readonly' : '' ?>>
            </div>

            <div class="col-md-3">
                <label class="form-label">Fecha Fin (opcional)</label>
                <input type="date" name="f_fin" class="form-control"
                       value="<?= $editHist['f_fin'] ?? '' ?>"
                       <?= $bloqueada ? 'readonly' : '' ?>>
            </div>

            <div class="col-md-12">
                <label class="form-label">Historia Médica</label>
                <textarea name="d_historia_medica" rows="5" class="form-control"
                          <?= $bloqueada ? 'readonly' : '' ?> required><?= htmlspecialchars($editHist['d_historia_medica'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- PLAN DE TRATAMIENTO -->
        <?php if ($planes && $planes->num_rows > 0): ?>
            <h5 class="mt-4">Plan de Tratamiento</h5>
            <?php if (!$bloqueada): ?>
                <!-- MODO EDICIÓN -->
                <div id="planes" class="row g-2">
                    <?php
                    $planesEdit = $editHist
                        ? $conn->query("SELECT c_id_especialidad, d_comentario FROM cl_plan_tratamiento WHERE c_id_historia_medica={$editHist['c_id']}")
                        : false;
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

                    <?php if ($planesEdit): ?>
                        <?php while ($pl = $planesEdit->fetch_assoc()): ?>
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
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>
                <div class="text-end mt-3">
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="agregar()">+ Agregar Especialidad</button>
                </div>
            <?php else: ?>
                <!-- MODO SOLO LECTURA -->
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Especialidad</th>
                                <th>Comentario</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($pl = $planes->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($pl['d_especialidad']) ?></td>
                                    <td><?= nl2br(htmlspecialchars($pl['d_comentario'])) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="text-end mt-3">
            <?php if (!$bloqueada): ?>
                <button type="submit" name="guardar" class="btn btn-success"><?= $editHist ? 'Actualizar' : 'Guardar' ?></button>
            <?php else: ?>
                <span class="btn btn-secondary">Cerrada</span>
            <?php endif; ?>
        </div>
    </form>

    <!-- Historias previas -->
    <h5 class="mt-4">Historias previas</h5>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>Especialista</th>
                    <th>Historia</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($h = $historias->fetch_assoc()):
                $cerrada = $h['f_fin'] && $h['f_fin'] <= $hoy;
            ?>
                <tr>
                    <td><?= htmlspecialchars($h['f_alta']) ?></td>
                    <td><?= htmlspecialchars($h['esp_ap']) ?>, <?= htmlspecialchars($h['esp_nom']) ?></td>
                    <td><?= nl2br(htmlspecialchars($h['d_historia_medica'])) ?></td>
                    <td>
                        <span class="badge <?= $cerrada ? 'bg-danger' : 'bg-success' ?>">
                            <?= $cerrada ? 'Cerrada' : 'Abierta' ?>
                        </span>
                    </td>
                    <td>
                        <?php if (!$cerrada): ?>
                            <a href="historia_detalle.php?id=<?= $idPac ?>&edit_hist=<?= $h['c_id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                        <?php else: ?>
                            <a href="historia_detalle.php?id=<?= $idPac ?>&edit_hist=<?= $h['c_id'] ?>" class="btn btn-sm btn-info">Ver</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

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

<?php require_once '../includes/footer.php';  ob_end_flush();?>