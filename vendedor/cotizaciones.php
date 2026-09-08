<?php
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
<title>Mis cotizaciones</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css<?= assetVer(__DIR__ . '/../assets/css/style.css') ?>">
<link rel="stylesheet" href="../assets/css/vendedor-2026.css<?= assetVer(__DIR__ . '/../assets/css/vendedor-2026.css') ?>">
</head>
<body class="v26">
  <div class="v26-header">
    <div class="v26-topbar">
      <div class="v26-topbar-left">
        <a href="index.php" class="v26-back v26-tip v26-tip--bottom" data-tip="Volver a mis visitas" aria-label="Volver"><i class="bi bi-arrow-left"></i></a>
        <div class="v26-greeting">
          <div class="hi">Ventas</div>
          <div class="name">Mis cotizaciones</div>
        </div>
      </div>
      <div class="v26-topbar-right">
        <img src="../logo.png" alt="Segurmex" class="v26-logo">
      </div>
    </div>
    <div class="v26-tabbar">
      <a href="index.php"><i class="bi bi-house-fill"></i>Inicio</a>
      <a href="calendario.php"><i class="bi bi-calendar3"></i>Calendario</a>
      <a href="clientes.php"><i class="bi bi-people-fill"></i>Clientes</a>
      <a href="cotizaciones.php" class="active"><i class="bi bi-file-earmark-text-fill"></i>Cotizar</a>
      <a href="reporte.php"><i class="bi bi-bar-chart-fill"></i>Reporte</a>
    </div>
  </div>

  <div class="v26-wrap">
    <a href="nueva_cotizacion.php" class="v26-cta">
      <span class="v26-cta-icon"><i class="bi bi-file-earmark-plus"></i></span>
      <span class="v26-cta-text">
        <strong>Nueva cotización</strong>
        <small>Cotiza con las mismas condiciones que oficina</small>
      </span>
      <i class="bi bi-chevron-right chev"></i>
    </a>

    <div id="lista-cotizaciones">
      <div class="v26-skel"></div>
      <div class="v26-skel"></div>
      <div class="v26-skel"></div>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/vendedor.js<?= assetVer(__DIR__ . '/../assets/js/vendedor.js') ?>"></script>
<script>
iniciarTrackingPeriodico();

const ETIQUETAS = {
  pendiente: 'Pendiente', enviada: 'Enviada', en_negociacion: 'En negociación',
  aceptada: 'Aceptada', rechazada: 'Rechazada', facturada: 'Facturada',
  entregada: 'Entregada', cancelada: 'Cancelada',
};

function money(n) { return '$' + Number(n || 0).toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
function escHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

function renderCotizaciones(lista) {
  const cont = document.getElementById('lista-cotizaciones');
  if (lista.length === 0) {
    cont.innerHTML = `
      <div class="v26-empty">
        <div class="icon"><i class="bi bi-file-earmark-text"></i></div>
        <p>Aún no has hecho ninguna cotización.<br>Usa "Nueva cotización" arriba para armar la primera.</p>
      </div>`;
    return;
  }
  cont.innerHTML = lista.map(c => `
    <div class="v26-cita">
      <div class="info">
        <div class="cliente">${escHtml(c.folio)} — ${escHtml(c.cliente_nombre)}</div>
        <div class="hora"><i class="bi bi-cash-coin"></i> ${money(c.total)}</div>
        <div class="badges">
          <span class="v26-pill v26-pill--${escHtml(c.estado)}">${escHtml(ETIQUETAS[c.estado] || c.estado)}</span>
          ${c.visitas_cita_id ? '<span class="v26-pill v26-pill--verificado"><i class="bi bi-geo-alt"></i> De una visita</span>' : ''}
        </div>
      </div>
      <a href="ver_cotizacion.php?id=${c.id}" class="accion v26-tip" data-tip="Ver detalle" aria-label="Ver detalle"><i class="bi bi-chevron-right"></i></a>
    </div>
  `).join('');
}

async function cargarCotizaciones() {
  const res = await fetch('../api/cotizaciones.php');
  const data = await res.json();
  if (!data.ok) {
    document.getElementById('lista-cotizaciones').innerHTML = `<div class="alert alert-danger">${escHtml(data.error)}</div>`;
    return;
  }
  renderCotizaciones(data.cotizaciones);
}
cargarCotizaciones();
</script>
</body>
</html>
