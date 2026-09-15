<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$u = requireRole('vendedor');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mis paradas — Control de Visitas</title>
<link rel="icon" type="image/png" href="../assets/img/Favv-Segu.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css<?= assetVer(__DIR__ . '/../assets/css/style.css') ?>">
<link rel="stylesheet" href="../assets/css/vendedor-2026.css<?= assetVer(__DIR__ . '/../assets/css/vendedor-2026.css') ?>">
<style>
  .v26-parada-item {
    display: flex; gap: 10px; background: var(--v26-surface-solid); border: 1px solid var(--v26-border);
    border-radius: var(--v26-r-md); padding: 12px 13px; box-shadow: var(--v26-shadow-sm); margin-bottom: 8px;
  }
  .v26-parada-item .icono {
    width: 34px; height: 34px; border-radius: 50%; flex: none; display: flex; align-items: center; justify-content: center;
  }
  .v26-parada-item.cerrada .icono { background: rgba(22,163,74,.12); color: var(--v26-green); }
  .v26-parada-item.abierta .icono { background: rgba(79,70,229,.12); color: var(--v26-blue); }
  .v26-parada-item .info { flex: 1; min-width: 0; }
  .v26-parada-item .nombre { font-weight: 700; font-size: .88rem; }
  .v26-parada-item .direccion { font-size: .76rem; color: var(--v26-ink-soft); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .v26-parada-item .meta { font-size: .72rem; color: var(--v26-ink-faint); margin-top: 3px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
  .v26-parada-item .pill-curso {
    font-size: .64rem; font-weight: 800; text-transform: uppercase; letter-spacing: .03em;
    background: rgba(79,70,229,.12); color: var(--v26-blue); padding: 2px 8px; border-radius: var(--v26-r-pill);
  }
</style>
</head>
<body class="v26">
  <div class="v26-header">
    <div class="v26-topbar">
      <div class="v26-topbar-left">
        <a href="index.php" class="v26-back" aria-label="Volver"><i class="bi bi-arrow-left"></i></a>
        <div class="v26-greeting">
          <div class="hi">Control de visitas</div>
          <div class="name">Mis paradas de hoy</div>
        </div>
      </div>
      <div class="v26-topbar-right">
        <img src="../logo.png" alt="Segurmex" class="v26-logo">
      </div>
    </div>
  </div>

  <div class="v26-wrap">
    <a href="prospeccion.php" class="v26-btn v26-btn-primary v26-btn-block mb-3">
      <i class="bi bi-signpost-2-fill"></i> <span id="txt-btn-nueva">Salí a buscar prospectos</span>
    </a>

    <div id="lista-paradas"><p class="text-muted small">Cargando...</p></div>
  </div>

<script src="../assets/js/vendedor.js<?= assetVer(__DIR__ . '/../assets/js/vendedor.js') ?>"></script>
<script>
iniciarTrackingPeriodico();

async function cargarListado() {
  const cont = document.getElementById('lista-paradas');
  try {
    const res = await fetch('../api/prospeccion.php');
    const data = await res.json();
    if (!data.ok) { cont.innerHTML = `<p class="text-danger small">${data.error}</p>`; return; }
    const paradas = data.paradas || [];
    if (paradas.length === 0) {
      cont.innerHTML = '<p class="text-muted small">Aún no registras ninguna parada hoy.</p>';
      return;
    }
    if (paradas.some(p => !p.hora_fin)) {
      document.getElementById('txt-btn-nueva').textContent = 'Continuar mi parada abierta';
    }
    cont.innerHTML = paradas.map(p => {
      const abierta = !p.hora_fin;
      return `
      <div class="v26-parada-item ${abierta ? 'abierta' : 'cerrada'}">
        <div class="icono"><i class="bi ${abierta ? 'bi-signpost-2-fill' : 'bi-check-circle-fill'}"></i></div>
        <div class="info">
          <div class="nombre"><i class="bi ${p.tipo === 'persona' ? 'bi-person' : 'bi-building'}"></i> ${p.nombre}</div>
          ${p.direccion ? `<div class="direccion">${p.direccion}</div>` : ''}
          <div class="meta">
            ${abierta
              ? `<span class="pill-curso">En curso</span><span>desde las ${horaCortaUTC(p.hora_inicio)}</span>`
              : `<span>${horaCortaUTC(p.hora_inicio)} – ${horaCortaUTC(p.hora_fin)}</span>`}
          </div>
        </div>
      </div>`;
    }).join('');
  } catch (e) {
    cont.innerHTML = '<p class="text-danger small">No se pudo cargar tu listado.</p>';
  }
}

cargarListado();
</script>
</body>
</html>
