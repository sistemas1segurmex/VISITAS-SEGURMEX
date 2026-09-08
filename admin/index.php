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
      <span class="v26-user"><?= htmlspecialchars($u['nombre']) ?></span>
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
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0">Ubicación en vivo de los vendedores</h6>
            <span id="resumen-vendedores" class="text-muted small"></span>
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

      <div class="card shadow-sm">
        <div class="card-body">
          <h6 class="mb-2">Alertas</h6>
          <!-- Pestañas Críticas/Sin solución: ocultas por mientras (quitar "d-none" para reactivarlas). -->
          <div class="v26-alert-tabs d-none">
            <button type="button" class="v26-alert-tab criticas active" data-tab="criticas" onclick="cambiarTabAlertas('criticas')">
              Críticas <span class="n" id="conteo-tab-criticas"></span>
            </button>
            <button type="button" class="v26-alert-tab" data-tab="seguimiento" onclick="cambiarTabAlertas('seguimiento')">
              Sin solución <span class="n" id="conteo-tab-seguimiento"></span>
            </button>
          </div>
          <div id="lista-alertas"><p class="text-muted small">Cargando...</p></div>
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
