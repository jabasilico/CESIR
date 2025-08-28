<?php
ob_start();
session_start();

// --- Seguridad básica ---
$idEsp   = (int)($_SESSION['id_especialista'] ?? 0);
$esAdmin = (int)($_SESSION['is_admin'] ?? 0) === 1;
if (!$idEsp && !$esAdmin) { header('Location: ../public/login.php'); exit; }

require_once '../config/database.php';
require_once '../includes/header.php';

// --- Utiles ---
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$hoy = date('Y-m-d');

// --- Parámetros ---
$idPac      = (int)($_GET['id'] ?? 0);
$idHistEdit = (int)($_GET['edit_hist'] ?? 0);

// --- Paciente ---
$pac = null;
if ($idPac > 0) {
    $rs = $conn->prepare("SELECT c_id, d_apellido, d_nombre FROM cl_paciente WHERE c_id = ?");
    $rs->bind_param('i', $idPac);
    $rs->execute();
    $pac = $rs->get_result()->fetch_assoc();
}
if (!$pac) { echo '<div class="alert alert-danger">Paciente no encontrado.</div>'; require_once '../includes/footer.php'; ob_end_flush(); exit; }

// --- Listas base (se materializan en arrays para reuso) ---
$especialidadesList = [];
$medicosList = [];

$res = $conn->query("SELECT c_id, d_especialidad FROM cl_especialidad ORDER BY d_especialidad");
while ($row = $res->fetch_assoc()) { $especialidadesList[] = $row; }

//-- if ($esAdmin) {
    $especialistasPorEsp = [];
    $res = $conn->query("SELECT c_id, CONCAT(d_apellido, ', ', d_nombre) AS nombre, c_id_especialidad FROM cl_especialista WHERE f_baja IS NULL ORDER BY d_apellido, d_nombre");
    while ($row = $res->fetch_assoc()) { 
        $medicosList[] = $row; 
        $espId = $row['c_id_especialidad'];
        $especialistasPorEsp[$espId][] = $row;
    }
//-- }

// --- Si se edita, cargar historia y su plan ---
$editHist = null; $planesEdit = [];
if ($idHistEdit > 0) {
    $st = $conn->prepare("SELECT * FROM cl_historia_medica WHERE c_id = ? AND c_id_paciente = ? ");

    $params   = [$idHistEdit, $idPac];
    $types    = 'ii';

    if (!$esAdmin) {
        $st = $conn->prepare("SELECT * FROM cl_historia_medica WHERE c_id = ? AND c_id_paciente = ? AND c_id_especialista = ?");
        $params   = [$idHistEdit, $idPac, $idEsp];
        $types    = 'iii';
    }

    $st->bind_param($types, ...$params);
    $st->execute();
    $editHist = $st->get_result()->fetch_assoc();

    if ($editHist) {
        $st = $conn->prepare("SELECT pt.*, e.d_especialidad FROM cl_plan_tratamiento pt JOIN cl_especialidad e ON e.c_id = pt.c_id_especialidad WHERE pt.c_id_historia_medica = ? ORDER BY e.d_especialidad");
        $st->bind_param('i', $idHistEdit);
        $st->execute();
        $rs = $st->get_result();
        while ($r = $rs->fetch_assoc()) { $planesEdit[] = $r; }
    }
}

// --- Guardar ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar'])) {
    // Datos principales
    $f_alta = $_POST['f_alta'] ?: $hoy;
    $f_fin  = ($_POST['f_fin'] ?? '') !== '' ? $_POST['f_fin'] : null;
    $texto  = trim($_POST['d_historia_medica'] ?? '');

    // Especialista asignado a la historia
    $idEspHistoria = $esAdmin ? (int)($_POST['c_id_especialista_historia'] ?? 0) : $idEsp;
    if ($idEspHistoria <= 0) { $idEspHistoria = $idEsp; }

    if ($texto === '') {
        echo '<div class="alert alert-warning">Debe completar el texto de la historia.</div>';
    } else {
        // Insert/Update historia
        if ($idHistEdit > 0 && $editHist) {
            $st = $conn->prepare("UPDATE cl_historia_medica SET c_id_especialista = ?, f_alta = ?, d_historia_medica = ?, f_fin = ? WHERE c_id = ? AND c_id_paciente = ?");
            $st->bind_param('isssii', $idEspHistoria, $f_alta, $texto, $f_fin, $idHistEdit, $idPac);
            $st->execute();
            $idHist = $idHistEdit;
        } else {
            $st = $conn->prepare("INSERT INTO cl_historia_medica (c_id_paciente, c_id_especialista, f_alta, d_historia_medica, f_fin) VALUES (?,?,?,?,?)");
            $st->bind_param('iisss', $idPac, $idEspHistoria, $f_alta, $texto, $f_fin);
            $st->execute();
            $idHist = $conn->insert_id;
        }

        // Reemplazar plan de tratamiento (DELETE + INSERT coherente)
        $st = $conn->prepare("DELETE FROM cl_plan_tratamiento WHERE c_id_historia_medica = ?");
        $st->bind_param('i', $idHist);
        $st->execute();

        $esps = $_POST['c_id_especialidad'] ?? [];
        $espsM = $_POST['c_id_especialista'] ?? [];
        $coms = $_POST['comentario'] ?? [];

        // Inserta cada fila alineando por índice y permitiendo comentario vacío
        $ins = $conn->prepare("INSERT INTO cl_plan_tratamiento (c_id_historia_medica, c_id_especialidad, d_comentario, c_id_especialista) VALUES (?,?,?,?)");
        foreach ($esps as $i => $esp) {
            $espId = (int)$esp;
            if ($espId > 0) {
                $espMed = !empty($espsM[$i]) ? (int)$espsM[$i] : null;
                $com = isset($coms[$i]) ? trim($coms[$i]) : '';
                $ins->bind_param('iisi', $idHist, $espId, $com, $espMed);
                $ins->execute();
            }
        }

        header("Location: historia_detalle.php?id={$idPac}"); exit;
    }
}

// --- Borrar historia ---
if (isset($_GET['del'])) {
    $idDel = (int)$_GET['del'];
    if ($idDel > 0) {
        $st = $conn->prepare("DELETE FROM cl_plan_tratamiento WHERE c_id_historia_medica = ?");
        $st->bind_param('i', $idDel);
        $st->execute();

        $st = $conn->prepare("DELETE FROM cl_historia_medica WHERE c_id = ? AND c_id_paciente = ?");
        $st->bind_param('ii', $idDel, $idPac);
        $st->execute();
    }
    header("Location: historia_detalle.php?id={$idPac}"); exit;
}

// --- Historias previas del paciente (para tabla) ---
$historiasList = [];
$sql =
    "SELECT hm.c_id, hm.f_alta, hm.f_fin, hm.d_historia_medica,
            es.c_id AS id_med, es.d_apellido, es.d_nombre,
            (SELECT COUNT(1) FROM cl_plan_tratamiento pt WHERE pt.c_id_historia_medica = hm.c_id) AS cant_esps
       FROM cl_historia_medica hm
       JOIN cl_especialista es ON es.c_id = hm.c_id_especialista
      WHERE hm.c_id_paciente = ?";
$params   = [$idPac];
$types    = 'i';
if (!$esAdmin) {
    $sql .= " AND hm.c_id_especialista = ? ";
    $params[] = $idEsp;
    $types   .= 'i';
}

$sql .= " ORDER BY hm.f_alta DESC ";


$st = $conn->prepare($sql);
$st->bind_param($types , ...$params);
$st->execute();
$rs = $st->get_result();


while ($r = $rs->fetch_assoc()) { $historiasList[] = $r; }

?>
<div class="container mt-4">
  <h2>Historia clínica de <?= h($pac['d_apellido'].' '.$pac['d_nombre']) ?></h2>

  <!-- ====== Formulario Alta/Edición ====== -->
  <div class="card mb-4 shadow-sm">
    <div class="card-header"> <?= $idHistEdit ? 'Editar historia' : 'Nueva historia' ?> </div>
    <div class="card-body">
      <form method="post" autocomplete="off">

        <!-- Profesional (combo para admin) -->
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Profesional</label>
            <?php if ($esAdmin): ?>
              <select name="c_id_especialista_historia" class="form-select" required>
                <option value="">-- Seleccione --</option>
                <?php foreach ($medicosList as $m):
                    $sel = ($editHist && (int)$editHist['c_id_especialista'] === (int)$m['c_id']) ? 'selected' : '';
                ?>
                  <option value="<?= (int)$m['c_id'] ?>" <?= $sel ?>><?= h($m['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            <?php else: ?> <!-- no admin, fijo al usuario logueado -->
              <input type="hidden" name="c_id_especialista_historia" value="<?= (int)$idEsp ?>">

              <?php
                    $nombre = $_SESSION['nombre_especialista'] ?? null;
                    if (!empty($idEsp)) {
                        $st = $conn->prepare("SELECT CONCAT(d_apellido, ', ', d_nombre) AS nombre
                                            FROM cl_especialista
                                            WHERE c_id = ?");
                        $st->bind_param('i', $idEsp);
                        $st->execute();
                        $res = $st->get_result()->fetch_assoc();
                        $nombre = $res['nombre'] ?? '';
                    }                
                ?>

              <div class="form-control" readonly>
                <?= h($nombre) ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="col-md-4">
            <label class="form-label">Fecha inicio</label>
            <input type="date" name="f_alta" class="form-control" value="<?= h($editHist['f_alta'] ?? $hoy) ?>" required>
          </div>

          <div class="col-md-4">
            <label class="form-label">Fecha fin</label>
            <input type="date" name="f_fin" class="form-control" value="<?= h($editHist['f_fin'] ?? '') ?>">
          </div>
        </div>

        <div class="mt-3">
          <label class="form-label">Historia</label>
          <textarea name="d_historia_medica" class="form-control" rows="4" required><?= h($editHist['d_historia_medica'] ?? '') ?></textarea>
        </div>

        <hr class="my-4">
        <h5 class="mb-3 subtitulo-resaltado">Plan de tratamiento</h5>

        <div id="planes" class="row g-2">
          <?php if ($idHistEdit && $planesEdit): ?>
            <?php foreach ($planesEdit as $p): ?>

                <div class="col-md-12 plan-item">
                    <div class="row g-2">
                        <div class="col-md-3">

                            <select name="c_id_especialidad[]" class="form-select sel-esp" required>
                                <option value="">-- Seleccione --</option>
                                <?php foreach ($especialidadesList as $esp):
                                $sel = ((int)$esp['c_id'] === (int)$p['c_id_especialidad']) ? 'selected' : '';
                                ?>
                                <option value="<?= (int)$esp['c_id'] ?>" <?= $sel ?>><?= h($esp['d_especialidad']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-5">
                            <textarea name="comentario[]" class="form-control" placeholder="Comentario"><?= h($p['d_comentario']) ?></textarea>
                        </div>

                        <!-- Especialista (opcional) -->
                        <div class="col-md-3">
                            <select name="c_id_especialista[]" class="form-select sel-esp-list">
                                <option value="">-- Especialista (opcional) --</option>
                                <?php
                                $espId = (int)$p['c_id_especialidad'];
                                if (!empty($especialistasPorEsp[$espId])):
                                    foreach ($especialistasPorEsp[$espId] as $med):
                                        $sel = ((int)($p['c_id_especialista'] ?? 0) === (int)$med['c_id']) ? 'selected' : '';
                                ?>
                                        <option value="<?= (int)$med['c_id'] ?>" <?= $sel ?>><?= h($med['nombre']) ?></option>
                                <?php
                                    endforeach;
                                endif;
                                ?>
                            </select>
                        </div>

                        <div class="col-md-1">                
                            <button type="button" class="btn btn-outline-danger" onclick="quitar(this)">❌</button>
                        </div>

                    </div>  
                </div>              

            <?php endforeach; ?>
          <?php else: ?>
              <!-- fila inicial vacía -->
            <div class="col-md-12 plan-item">
                <div class="row g-2">
                    <div class="col-md-3">
                        <select name="c_id_especialidad[]" class="form-select sel-esp" required>
                            <option value="">-- Seleccione --</option>
                            <?php foreach ($especialidadesList as $esp): ?>
                            <option value="<?= (int)$esp['c_id'] ?>"><?= h($esp['d_especialidad']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-5">
                        <textarea type="text" name="comentario[]" class="form-control" placeholder="Comentario"></textarea>
                    </div>

                    <!-- Especialista (opcional) -->
                    <div class="col-md-3">
                        <select name="c_id_especialista[]" class="form-select sel-esp-list">
                            <option value="">-- Especialista (opcional) --</option>
                        </select>
                    </div>

                    <div class="col-md-1">
                        <button type="button" class="btn btn-outline-danger" onclick="quitar(this)">❌</button>
                    </div>
                </div>  
            </div>

          <?php endif; ?>
        </div>

        <div class="mt-2 text-end">
          <button type="button" class="btn btn-secondary" onclick="agregar()">➕ Agregar especialidad</button>
        </div>

        <hr class="my-4">

        <div class="mt-4">
          <button class="btn btn-primary" name="guardar" type="submit">Guardar</button>
          <?php if ($idHistEdit): ?>
            <a class="btn btn-outline-secondary" href="historia_detalle.php?id=<?= (int)$idPac ?>">Cancelar</a>
          <?php endif; ?>
        </div>

      </form>
    </div>
  </div>

  <!-- ====== Historias previas ====== -->
  <div class="card shadow-sm">
    <div class="card-header">Historias previas</div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-striped mb-0 align-middle">
          <thead>
            <tr>
              <th style="width: 120px;">Inicio</th>
              <th style="width: 120px;">Fin</th>
              <th>Profesional</th>
              <th>Resumen</th>
              <th class="text-center" style="width: 120px;">Especialidades</th>
              <th style="width: 160px;">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$historiasList): ?>
              <tr><td colspan="6" class="text-center py-3">Sin historias previas.</td></tr>
            <?php else: ?>
              <?php foreach ($historiasList as $hrow): ?>
                <tr>
                  <td><?= h($hrow['f_alta']) ?></td>
                  <td><?= h($hrow['f_fin'] ?? '') ?></td>
                  <td><?= h($hrow['d_apellido'].', '.$hrow['d_nombre']) ?></td>
                  <td><?= h(mb_strimwidth($hrow['d_historia_medica'] ?? '', 0, 80, '…', 'UTF-8')) ?></td>
                  <td class="text-center"><?= (int)$hrow['cant_esps'] ?></td>
                  <td>
                    <a class="btn btn-sm btn-outline-primary" href="historia_detalle.php?id=<?= (int)$idPac ?>&edit_hist=<?= (int)$hrow['c_id'] ?>">Editar</a>
                    <a class="btn btn-sm btn-outline-danger" href="historia_detalle.php?id=<?= (int)$idPac ?>&del=<?= (int)$hrow['c_id'] ?>" onclick="return confirm('¿Eliminar historia y su plan?')">Borrar</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<!-- ====== Templates & JS ====== -->
<template id="tplPlan">
    <div class="col-md-12 plan-item">
        <div class="row g-2">

            <div class="col-md-3">
                <select name="c_id_especialidad[]" class="form-select sel-esp" required>
                <option value="">-- Seleccione --</option>
                <?php foreach ($especialidadesList as $esp): ?>
                <option value="<?= (int)$esp['c_id'] ?>"><?= h($esp['d_especialidad']) ?></option>
                <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-5">
                <textarea name="comentario[]" class="form-control" rows="2" placeholder="Comentario"></textarea>
            </div>

            <!-- Especialista (opcional) -->
            <div class="col-md-3">
                <select name="c_id_especialista[]" class="form-select sel-esp-list">
                <option value="">-- Especialista (opcional) --</option>
                </select>
            </div>

            <div class="col-md-1">
                <button type="button" class="btn btn-outline-danger" onclick="quitar(this)">❌</button>
            </div>

        </div>  
    </div>
</template>

<script>

document.getElementById('planes').addEventListener('change', e => {
  if (!e.target.classList.contains('sel-esp')) return;

  const espId = e.target.value;
  const row   = e.target.closest('.plan-item');
  const selEsp = row.querySelector('.sel-esp-list');

  selEsp.innerHTML = '<option value="">-- Especialista (opcional) --</option>';
  if (!espId) return;

  fetch('../ajax/especialistas_x_especialidad.php?especialidad=' + espId)
    .then(r => r.json())
    .then(list => {
      list.forEach(m => {
        const opt = document.createElement('option');
        opt.value = m.c_id;
        opt.textContent = m.nombre;
        selEsp.appendChild(opt);
      });
    });
});

function agregar() {
  const tpl = document.getElementById('tplPlan');
  const clone = tpl.content.cloneNode(true);
  document.getElementById('planes').appendChild(clone);
}

function quitar(btn) {
  const row = btn.closest('.plan-item');
  if (row) row.remove();
}

</script>


<?php require_once '../includes/footer.php'; ob_end_flush(); ?>
