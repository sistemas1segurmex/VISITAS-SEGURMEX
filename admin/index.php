<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$u = requireRole('admin');
$hoy = date('Y-m-d');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Panel — Control de Visitas</title>
<link rel="icon" type="image/png" href="../assets/img/Favv-Segu.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<link rel="stylesheet" href="../assets/css/style.css<?= assetVer(__DIR__ . '/../assets/css/style.css') ?>">
<link rel="stylesheet" href="../assets/css/admin-2026.css<?= assetVer(__DIR__ . '/../assets/css/admin-2026.css') ?>">
</head>
<body class="v26">
<div class="v26-header">
  <div class="v26-topbar">
    <div class="v26-topbar-left">
      <div class="v26-avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr($u['nombre'], 0, 1))) ?></div>
      <div class="v26-greeting">
        <div class="hi">Control de Visitas</div>
        <div class="name">Panel de administrador</div>
      </div>
    </div>
    <div class="v26-topbar-right">
      <a href="usuarios.php" class="v26-btn-chip"><i class="bi bi-people"></i> Vendedores</a>
      <a href="bitacora.php" class="v26-btn-chip"><i class="bi bi-journal-text"></i> Bitácora</a>
      <a href="ubicaciones.php" class="v26-btn-chip"><i class="bi bi-geo-alt"></i> Ubicaciones</a>
      <span class="v26-user"><?= htmlspecialchars($u['nombre']) ?></span>
      <a href="../logout.php" class="v26-icon-btn v26-tip v26-tip--bottom" data-tip="Cerrar sesión" aria-label="Salir"><i class="bi bi-box-arrow-right"></i></a>
    </div>
  </div>
</div>

<div class="v26-wrap">
  <!-- Embudo de ventas: oculto por mientras (quitar "d-none" para reactivarlo). -->
  <div class="card shadow-sm mb-3 d-none">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
        <h6 class="mb-0">Embudo de ventas — todo el equipo</h6>
        <span id="embudo-tasa" class="v26-badge-conteo"></span>
      </div>
      <div id="embudo-pills" class="d-flex flex-wrap gap-2"><p class="text-muted small mb-0">Cargando...</p></div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card shadow-sm mb-3">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <h6 class="mb-0">Ubicación en vivo de los vendedores</h6>
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <select id="filtro-vendedor-mapa" class="form-select form-select-sm" style="width:auto;min-width:170px">
                <option value="0">Todos los vendedores</option>
              </select>
              <span id="resumen-vendedores" class="text-muted small"></span>
            </div>
          </div>
          <div id="mapa-wrap">
            <div id="mapa"></div>
            <div class="mapa-leyenda">
              <span><span class="dot"></span> En línea <strong id="conteo-en-linea">—</strong></span>
              <span><span class="dot off"></span> Desconectado <strong id="conteo-desconectado">—</strong></span>
              <span><span class="dot lost"></span> Conexión perdida <strong id="conteo-perdida">—</strong></span>
            </div>
          </div>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
              <h6 class="mb-0">Visitas</h6>
              <span id="conteo-citas-dia" class="v26-badge-conteo"></span>
            </div>
            <input type="date" id="filtro-fecha" class="form-control form-control-sm" style="width:160px" value="<?= $hoy ?>">
          </div>
          <div class="v26-sparkline-wrap"><canvas id="sparkline-visitas"></canvas></div>
          <div class="v26-citas-list" id="tabla-citas"><p class="text-muted small px-2 mb-0">Cargando...</p></div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card shadow-sm mb-3 d-none" id="panel-estado-wrap">
        <div class="card-body" id="panel-estado"></div>
      </div>

      <!-- Alertas (rediseño etapa 1, ver includes/alertas.php y la sección
           "Alertas" de assets/js/admin.js). -->
      <div class="card shadow-sm v26-al">
        <div class="card-body">
          <div class="v26-al-cab">
            <h6 class="mb-0">Alertas</h6>
            <span class="v26-al-gen" id="alertas-revisadas"></span>
          </div>
          <div class="v26-al-resumen">
            <button type="button" class="v26-al-tile crit" data-filtro="crit"><b id="alertas-n-crit">0</b> Atender hoy</button>
            <button type="button" class="v26-al-tile warn" data-filtro="warn"><b id="alertas-n-warn">0</b> Revisar</button>
            <button type="button" class="v26-al-tile info" data-filtro="info"><b id="alertas-n-info">0</b> Info</button>
          </div>
          <div class="v26-al-controles">
            <div class="v26-al-filtros" role="group" aria-label="Filtrar alertas por prioridad">
              <button type="button" class="opt active" data-filtro="todas">Todas <span id="alertas-n-todas">0</span></button>
              <button type="button" class="opt" data-filtro="crit">Atender</button>
              <button type="button" class="opt" data-filtro="warn">Revisar</button>
              <button type="button" class="opt" data-filtro="info">Info</button>
            </div>
            <div class="v26-al-vista" role="group" aria-label="Forma de ver las alertas">
              <button type="button" class="opt active" data-vista="grupo">Por vendedor</button>
              <button type="button" class="opt" data-vista="lista">Lista</button>
            </div>
          </div>
          <div id="lista-alertas" class="v26-al-lista"><p class="text-muted small mb-0">Cargando...</p></div>
          <details class="v26-al-solas" id="alertas-solas-wrap" hidden>
            <summary><i class="bi bi-check-circle-fill"></i> Se resolvieron solas <span class="v26-al-chip ok" id="alertas-n-solas">0</span></summary>
            <div id="alertas-solas"></div>
          </details>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalFoto" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered v26-foto-modal-dialog">
    <div class="modal-content v26-foto-modal-content">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title small text-white-50" id="foto-modal-titulo"></h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body text-center pt-2">
        <img id="foto-modal-img" src="" alt="Foto de evidencia" class="v26-foto-modal-img">
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/es.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="../assets/js/estados_mx.js<?= assetVer(__DIR__ . '/../assets/js/estados_mx.js') ?>"></script>
<script src="../assets/js/fecha_utils.js<?= assetVer(__DIR__ . '/../assets/js/fecha_utils.js') ?>"></script>
<script src="../assets/js/capas_mapa.js<?= assetVer(__DIR__ . '/../assets/js/capas_mapa.js') ?>"></script>
<script src="../assets/js/admin_historial_cita.js<?= assetVer(__DIR__ . '/../assets/js/admin_historial_cita.js') ?>"></script>
<script src="../assets/js/admin.js<?= assetVer(__DIR__ . '/../assets/js/admin.js') ?>"></script>
<script src="../assets/js/admin_mapa_estados.js<?= assetVer(__DIR__ . '/../assets/js/admin_mapa_estados.js') ?>"></script>
<script>
  initMapa();
  initCapaEstados();
  initCalendarioFecha();
  initSparkline();
  refrescarTodo();
  document.getElementById('filtro-fecha').addEventListener('change', () => { cargarCitasHoy(); dibujarRutasDia(); });
  setInterval(refrescarTodo, 20000); // refresco automático cada 20s
</script>
</body>
</html>
