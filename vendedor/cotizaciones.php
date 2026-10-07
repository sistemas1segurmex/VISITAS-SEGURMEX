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
<link rel="icon" type="image/png" href="../assets/img/Favv-Segu.png">
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
        <button type="button" class="v26-icon-btn v26-tip v26-tip--bottom" data-tip="Ver el recorrido de nuevo" aria-label="Ayuda" id="btn-tour-ayuda"><i class="bi bi-question-lg"></i></button>
      </div>
    </div>
    <div class="v26-tabbar">
      <a href="index.php"><i class="bi bi-house-fill"></i>Inicio</a>
      <a href="calendario.php"><i class="bi bi-calendar3"></i>Calendario</a>
      <a href="clientes.php"><i class="bi bi-people-fill"></i>Clientes</a>
      <a href="cotizaciones.php" class="active"><i class="bi bi-file-earmark-text-fill"></i>Cotizar</a>
      <a href="muestras.php"><i class="bi bi-box-seam-fill"></i>Muestras</a>
      <a href="reporte.php"><i class="bi bi-bar-chart-fill"></i>Reporte</a>
      <a href="mis_paradas.php"><i class="bi bi-signpost-2-fill"></i>Paradas</a>
    </div>
  </div>

  <div class="v26-wrap">
    <a href="nueva_cotizacion.php" class="v26-cta" data-tour="cta-cotizar">
      <span class="v26-cta-icon"><i class="bi bi-file-earmark-plus"></i></span>
      <span class="v26-cta-text">
        <strong>Nueva cotización</strong>
        <small>Cotiza con las mismas condiciones que oficina</small>
      </span>
      <i class="bi bi-chevron-right chev"></i>
    </a>

    <a href="muestras.php" class="mu-link-muestras" data-tour="link-muestras">
      <i class="bi bi-box-seam"></i> ¿Buscas tus muestras? Ahora están en la pestaña <strong>Muestras</strong> <i class="bi bi-chevron-right"></i>
    </a>

    <div id="lista-cotizaciones">
      <div class="v26-skel"></div>
      <div class="v26-skel"></div>
      <div class="v26-skel"></div>
    </div>

    <button type="button" class="v26-btn v26-btn-ghost v26-btn-block mt-3" id="btn-tour-guiado"><i class="bi bi-signpost-split"></i> Tour guiado</button>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/v26-tour.js<?= assetVer(__DIR__ . '/../assets/js/v26-tour.js') ?>"></script>
<script src="../assets/js/vendedor.js<?= assetVer(__DIR__ . '/../assets/js/vendedor.js') ?>"></script>
<script src="../assets/js/avisos.js<?= assetVer(__DIR__ . '/../assets/js/avisos.js') ?>"></script>
<script>
iniciarTrackingPeriodico();

const ETIQUETAS = {
  pendiente: 'Pendiente', enviada: 'Enviada', en_negociacion: 'En negociación',
  aceptada: 'Aceptada', rechazada: 'Rechazada', facturada: 'Facturada',
  entregada: 'Entregada', cancelada: 'Cancelada',
};

function money(n) { return '$' + Number(n || 0).toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
function escHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

const PASOS_TOUR_COTIZACIONES = [
  { selector: '[data-tour="cta-cotizar"]', texto: 'Arma una cotización con las mismas condiciones y precios que usa oficina.' },
  { selector: '#lista-cotizaciones .v26-cita', texto: 'Toca cualquiera para ver el detalle.' },
  { selector: '[data-tour="link-muestras"]', texto: 'Las muestras ahora tienen su propia pestaña: Muestras.' },
];
const OPCIONES_TOUR_COTIZACIONES = {
  storageKey: 'v26_tour_cotizaciones_visto',
  saludoTitulo: 'Tus cotizaciones',
  saludoTexto: 'Un par de cosas rápidas antes de que las uses.',
  finalTexto: 'Repite este recorrido cuando quieras tocando el ícono ? de arriba.',
};
document.getElementById('btn-tour-ayuda').addEventListener('click', () => V26Tour.reiniciar(PASOS_TOUR_COTIZACIONES, OPCIONES_TOUR_COTIZACIONES));
document.getElementById('btn-tour-guiado').addEventListener('click', () => V26Tour.reiniciar(PASOS_TOUR_COTIZACIONES, OPCIONES_TOUR_COTIZACIONES));

function renderCotizacionItem(c) {
  return `
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
    </div>`;
}

// ?ver=muestras (links viejos de avisos y correos): las muestras ahora
// tienen su propia pestaña.
if (new URLSearchParams(location.search).get('ver') === 'muestras') location.replace('muestras.php');

let cotizacionesData = [];

function renderLista() {
  const cont = document.getElementById('lista-cotizaciones');
  if (cotizacionesData.length === 0) {
    cont.innerHTML = `
      <div class="v26-empty">
        <div class="icon"><i class="bi bi-file-earmark-text"></i></div>
        <p>Aún no has hecho ninguna cotización.<br>Usa "Nueva cotización" arriba para armar la primera.</p>
      </div>`;
    return;
  }
  cont.innerHTML = cotizacionesData.map(renderCotizacionItem).join('');
}

async function cargarCotizaciones() {
  const res = await fetch('../api/cotizaciones.php').then(r => r.json()).catch(() => ({ ok: false, error: 'No se pudo cargar tus cotizaciones.' }));
  if (!res.ok) {
    document.getElementById('lista-cotizaciones').innerHTML = `<div class="alert alert-danger">${escHtml(res.error)}</div>`;
    return;
  }
  cotizacionesData = res.cotizaciones;
  renderLista();
}
cargarCotizaciones().then(() => V26Tour.iniciar(PASOS_TOUR_COTIZACIONES, OPCIONES_TOUR_COTIZACIONES));
</script>
</body>
</html>
