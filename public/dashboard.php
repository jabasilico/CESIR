<?php
session_start();
if (!($_SESSION['is_admin'] ?? false)) {
    header('Location: ../index.php'); exit;
}
require_once '../config/database.php';
require_once '../includes/header.php';

/* ---------- INDICADOR: PACIENTES ACTIVOS ---------- */
$activos = $conn->query("SELECT COUNT(*) AS total FROM cl_paciente WHERE c_id_estado = 1")->fetch_assoc()['total'];
?>
<div class="container mt-4">
  <h3 class="titulo-resaltado">Dashboard Administrativo</h3>

  <!-- Indicador -->
  <div class="row">
    <div class="col-md-2">
      <div class="card text-white bg-success text-center">
        <div class="card-body">
          <h5 class="card-title">Pacientes Activos</h5>
          <h2 class="display-2"><?= $activos ?></h2>
        </div>
      </div>
    </div>
  </div>

<?php
/* ---------- CONFIG ---------- */
$especialidades = [
    2 => 'Kinesiología',
    3 => 'Terapista ocupacional',
    5 => 'Fonoaudiólogo',
    6 => 'Psicopedagogo'
];

$diaLimite  = 20;
$hoy        = new DateTime();
$anioActual = (int) $hoy->format('Y');
$mesActual  = (int) $hoy->format('m');
$diaActual  = (int) $hoy->format('d');

/* ---------- HELPERS ---------- */
function hastaFechaCorte(int $anio, int $mes, int $diaLimite, DateTime $hoy): string
{
    $corte = (clone $hoy)->setDate($anio, $mes, $diaLimite);
    if ((int)$corte->format('d') < $diaLimite) {
        $corte->modify('last day of this month');
    }
    return $hoy <= $corte
        ? $corte->format('Y-m-d')
        : (clone $corte)->modify('last day of this month')->format('Y-m-d');
}

/* ---------- TABLA ---------- */
echo '<h4 class="mt-4">Pacientes sin evolución por mes y especialidad</h4>';
echo '<table class="table table-bordered table-hover align-middle">';
echo '<thead class="table-light">
        <tr>
          <th>Especialidad</th>
          <th>Mes</th>
          <th class="text-center">Sin evolución</th>
        </tr>
      </thead>
      <tbody>';

foreach ($especialidades as $idEsp => $nombreEsp) {
    /* Mes más antiguo con historia activa */
    $sqlMin = "
        SELECT DATE_FORMAT(MIN(hm.f_alta), '%Y-%m') AS min_mes
        FROM cl_historia_medica hm
        JOIN cl_plan_tratamiento pt ON pt.c_id_historia_medica = hm.c_id
        WHERE (hm.f_fin IS NULL OR hm.f_fin > CURDATE())
          AND pt.c_id_especialidad = ?
    ";
    $stmtMin = $conn->prepare($sqlMin);
    $stmtMin->bind_param('i', $idEsp);
    $stmtMin->execute();
    $minMes = $stmtMin->get_result()->fetch_assoc()['min_mes'] ?? null;
    if (!$minMes) continue;

    $inicio = DateTime::createFromFormat('Y-m', $minMes)->modify('first day of this month');
    $periodo = new DatePeriod(
        $inicio,
        new DateInterval('P1M'),
        (clone $hoy)->modify('first day of next month')
    );

    foreach ($periodo as $mes) {
        $a = (int) $mes->format('Y');
        $m = (int) $mes->format('m');
        $fechaCorte = hastaFechaCorte($a, $m, $diaLimite, $hoy);

        $sql = "
            SELECT COUNT(DISTINCT p.c_id) AS sin_evo
            FROM cl_paciente p
            JOIN cl_historia_medica hm  ON hm.c_id_paciente = p.c_id
            JOIN cl_plan_tratamiento pt ON pt.c_id_historia_medica = hm.c_id
            WHERE (hm.f_fin IS NULL OR hm.f_fin > CURDATE())
              AND pt.c_id_especialidad = ?
              AND DATE(hm.f_alta) <= ?
              AND NOT EXISTS (
                  SELECT 1
                  FROM cl_evolucion_paciente ev
                  WHERE ev.c_id_paciente = p.c_id
                    AND ev.c_id_especialista = ?
                    AND ev.f_evolucion BETWEEN ? AND ?
              )
        ";
        $stmt = $conn->prepare($sql);
        $primerDia = $mes->format('Y-m-01');
        $stmt->bind_param('isiss', $idEsp, $fechaCorte, $idEsp, $primerDia, $fechaCorte);
        $stmt->execute();
        $sinEvo = (int) $stmt->get_result()->fetch_assoc()['sin_evo'];

        if ($sinEvo) {
            $url = 'ajax_detalle_sin_evo.php?esp=' . $idEsp . '&mes=' . $mes->format('Y-m');
            echo '<tr class="fila-detalle" style="cursor:pointer"
                      data-url="' . $url . '"
                      data-bs-toggle="modal"
                      data-bs-target="#modalDetalle">
                    <td>' . htmlspecialchars($nombreEsp) . '</td>
                    <td>' . $mes->format('m/Y') . '</td>
                    <td class="text-center">' . $sinEvo . '</td>
                  </tr>';
        }
    }
}
echo '</tbody></table>';
?>
</div>

<!-- Modal -->
<div class="modal fade" id="modalDetalle" tabindex="-1" aria-labelledby="modalDetalleLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalDetalleLabel">Pacientes sin evolución</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div id="contenidoModal">
          <div class="d-flex justify-content-center">
            <div class="spinner-border" role="status">
              <span class="visually-hidden">Cargando…</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('modalDetalle');
  const contenido = document.getElementById('contenidoModal');

  modal.addEventListener('show.bs.modal', function (e) {
    const url = e.relatedTarget.dataset.url;
    fetch(url)
      .then(res => res.text())
      .then(html => { contenido.innerHTML = html; });
  });
});
</script>

<?php require_once '../includes/footer.php'; ?>