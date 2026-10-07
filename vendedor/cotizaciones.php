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
<link rel="stylesheet" href="../assets/css/muestras.css<?= assetVer(__DIR__ . '/../assets/css/muestras.css') ?>">
<style>
/* Mis cotizaciones: resumen del mes, filtros por estado, lista por día. */
.ct-wrap { max-width: 720px; }
.ct-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); }
@media (max-width: 480px) {
  .ct-stats { gap: 8px; }
  .ct-stats .mu-stat { flex-direction: column; align-items: flex-start; gap: 8px; padding: 12px; cursor: default; }
  .ct-stats .mu-stat-ico { width: 34px; height: 34px; border-radius: 11px; font-size: .95rem; }
  .ct-stats .mu-stat-n { font-size: 1.1rem; }
  .ct-stats .mu-stat-lbl { font-size: .6rem; }
}
.ct-stats .mu-stat { cursor: default; }
.ct-stats .mu-stat:hover { transform: none; box-shadow: var(--v26-shadow-sm); }
.ct-stat-monto .mu-stat-n { font-size: 1.15rem; }
.ct-dia { font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: var(--v26-ink-soft); margin: 18px 2px 8px; display: flex; align-items: center; gap: 8px; }
.ct-dia::after { content: ''; flex: 1; height: 1px; background: var(--v26-border); }
.ct-dia b { color: var(--v26-ink); }
.ct-card {
  display: flex; gap: 12px; align-items: stretch; text-decoration: none; color: var(--v26-ink);
  background: var(--v26-surface-solid); border: 1px solid var(--v26-border); border-radius: var(--v26-r-lg);
  padding: 14px; margin-bottom: 10px; box-shadow: var(--v26-shadow-sm); position: relative; overflow: hidden;
  transition: transform .15s var(--v26-ease), box-shadow .15s var(--v26-ease); animation: v26-rise .4s var(--v26-ease) both;
}
.ct-card::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: var(--ct-color, var(--v26-brand-1)); }
.ct-card:hover { color: var(--v26-ink); transform: translateY(-1px); box-shadow: var(--v26-shadow-md); }
.ct-ico { flex: none; width: 40px; height: 40px; border-radius: 13px; display: flex; align-items: center; justify-content: center; font-size: 1.05rem; color: #fff; background: var(--ct-color, var(--v26-brand-1)); }
.ct-main { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 3px; }
.ct-folio { font-size: .74rem; font-weight: 800; color: var(--v26-ink-soft); letter-spacing: .02em; }
.ct-cliente { font-size: .98rem; font-weight: 800; line-height: 1.2; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ct-meta { display: flex; flex-wrap: wrap; gap: 4px 12px; font-size: .74rem; color: var(--v26-ink-soft); font-weight: 600; }
.ct-badges { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 4px; }
.ct-badges .v26-pill { font-size: .68rem; }
.ct-lado { flex: none; display: flex; flex-direction: column; align-items: flex-end; justify-content: space-between; gap: 6px; }
.ct-total { font-size: 1.02rem; font-weight: 800; white-space: nowrap; }
.ct-chev { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: rgba(232,164,0,.12); color: var(--v26-brand-2); }
.ct-card--pendiente      { --ct-color: #9CA3AF; }
.ct-card--enviada        { --ct-color: #2563EB; }
.ct-card--en_negociacion { --ct-color: #7C3AED; }
.ct-card--aceptada, .ct-card--facturada, .ct-card--entregada { --ct-color: #16A34A; }
.ct-card--rechazada      { --ct-color: #E11D48; }
.ct-card--cancelada      { --ct-color: #6B7280; opacity: .8; }
.ct-filtros { margin-top: 14px; }
.ct-buscar { margin: 0 0 4px; }
</style>
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
        <a href="../logout.php" class="v26-icon-btn v26-tip v26-tip--bottom" data-tip="Cerrar sesión" aria-label="Salir"><i class="bi bi-box-arrow-right"></i></a>
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

  <div class="v26-wrap ct-wrap">
    <a href="nueva_cotizacion.php" class="v26-cta" data-tour="cta-cotizar">
      <span class="v26-cta-icon"><i class="bi bi-file-earmark-plus"></i></span>
      <span class="v26-cta-text">
        <strong>Nueva cotización</strong>
        <small>Arma una cotización para tu cliente</small>
      </span>
      <i class="bi bi-chevron-right chev"></i>
    </a>

    <div class="mu-stats ct-stats mt-3" data-tour="resumen">
      <div class="mu-stat mu-stat--nuevas">
        <span class="mu-stat-ico"><i class="bi bi-file-earmark-text-fill"></i></span>
        <span><span class="mu-stat-n" id="st-mes">0</span><span class="mu-stat-lbl" id="st-mes-lbl">Este mes</span></span>
      </div>
      <div class="mu-stat mu-stat--prep ct-stat-monto">
        <span class="mu-stat-ico"><i class="bi bi-cash-stack"></i></span>
        <span><span class="mu-stat-n" id="st-monto">$0</span><span class="mu-stat-lbl">Cotizado este mes</span></span>
      </div>
      <div class="mu-stat mu-stat--emb">
        <span class="mu-stat-ico"><i class="bi bi-hand-thumbs-up-fill"></i></span>
        <span><span class="mu-stat-n" id="st-aceptadas">0</span><span class="mu-stat-lbl">Aceptadas este mes</span></span>
      </div>
    </div>

    <div class="ct-filtros" data-tour="filtros">
      <div class="v26-funnel-filtro" id="filtro-estado"></div>
      <div class="v26-search ct-buscar">
        <i class="bi bi-search"></i>
        <input type="text" id="buscar" class="v26-input" placeholder="Buscar por folio o cliente...">
      </div>
    </div>

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
  { selector: '[data-tour="resumen"]', texto: 'Cómo vas este mes: cuántas cotizaciones llevas, cuánto has cotizado y cuántas te aceptaron.' },
  { selector: '[data-tour="filtros"]', texto: 'Filtra por estado o busca por folio o cliente.' },
  { selector: '#lista-cotizaciones .ct-card', texto: 'Cada cotización dice cuándo la hiciste y si está por vencer. Tócala para ver el detalle.' },
];
const OPCIONES_TOUR_COTIZACIONES = {
  storageKey: 'v26_tour_cotizaciones_visto',
  saludoTitulo: 'Tus cotizaciones',
  saludoTexto: 'Un par de cosas rápidas antes de que las uses.',
  finalTexto: 'Repite este recorrido cuando quieras tocando el ícono ? de arriba.',
};
document.getElementById('btn-tour-ayuda').addEventListener('click', () => V26Tour.reiniciar(PASOS_TOUR_COTIZACIONES, OPCIONES_TOUR_COTIZACIONES));
document.getElementById('btn-tour-guiado').addEventListener('click', () => V26Tour.reiniciar(PASOS_TOUR_COTIZACIONES, OPCIONES_TOUR_COTIZACIONES));

const ICONO_ESTADO = { pendiente: 'bi-hourglass-split', enviada: 'bi-send-fill', en_negociacion: 'bi-chat-dots-fill', aceptada: 'bi-check-lg', facturada: 'bi-receipt', entregada: 'bi-truck', rechazada: 'bi-x-lg', cancelada: 'bi-slash-circle' };
const ABIERTAS = ['pendiente', 'enviada', 'en_negociacion'];
const TZ = 'America/Mexico_City';
// timestamptz del ERP ("2026-10-06 17:05:12.3+00") -> Date.
function fechaTz(s) {
  const t = String(s || '').replace(' ', 'T').replace(/([+-]\d{2})$/, '$1:00');
  const d = new Date(/[zZ]|[+-]\d{2}:\d{2}$/.test(t) ? t : t + 'Z');
  return isNaN(d) ? null : d;
}
function diaMx(d) { return d.toLocaleDateString('en-CA', { timeZone: TZ }); }
function horaMx(d) { return d.toLocaleTimeString('es-MX', { hour: 'numeric', minute: '2-digit', timeZone: TZ }); }
const HOY = diaMx(new Date());
const AYER = diaMx(new Date(Date.now() - 86400000));
function etiquetaDia(d) {
  const k = diaMx(d);
  if (k === HOY) return 'Hoy';
  if (k === AYER) return 'Ayer';
  const dias = Math.round((new Date(HOY + 'T12:00:00') - new Date(k + 'T12:00:00')) / 86400000);
  if (dias < 7) return d.toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'short', timeZone: TZ });
  return d.toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: k.slice(0, 4) === HOY.slice(0, 4) ? undefined : 'numeric', timeZone: TZ });
}
function badgeVigencia(c, d) {
  if (!ABIERTAS.includes(c.estado) || !d || !c.vigencia_dias) return '';
  const vence = new Date(d.getTime() + Number(c.vigencia_dias) * 86400000);
  const dias = Math.ceil((vence - Date.now()) / 86400000);
  if (dias < 0) return '<span class="mu-badge mu-badge--rojo"><i class="bi bi-alarm"></i> Vencida</span>';
  if (dias <= 3) return `<span class="mu-badge mu-badge--ambar"><i class="bi bi-alarm"></i> Vence ${dias === 0 ? 'hoy' : (dias === 1 ? 'mañana' : 'en ' + dias + ' días')}</span>`;
  return `<span class="mu-badge"><i class="bi bi-calendar-check"></i> Vigente ${dias} días más</span>`;
}

function renderCotizacionItem(c, i) {
  const d = fechaTz(c.created_at);
  const pares = Number(c.total_pares || 0);
  return `
    <a class="ct-card ct-card--${escHtml(c.estado)}" href="ver_cotizacion.php?id=${c.id}" style="animation-delay:${Math.min(i || 0, 10) * 0.03}s">
      <span class="ct-ico"><i class="bi ${ICONO_ESTADO[c.estado] || 'bi-file-earmark-text'}"></i></span>
      <span class="ct-main">
        <span class="ct-folio">${escHtml(c.folio)}</span>
        <span class="ct-cliente">${escHtml(c.cliente_nombre)}</span>
        <span class="ct-meta">
          ${d ? `<span><i class="bi bi-clock"></i> ${escHtml(horaMx(d))}</span>` : ''}
          ${pares ? `<span><i class="bi bi-box"></i> ${pares} par${pares === 1 ? '' : 'es'}</span>` : ''}
        </span>
        <span class="ct-badges">
          <span class="v26-pill v26-pill--${escHtml(c.estado)}">${escHtml(ETIQUETAS[c.estado] || c.estado)}</span>
          ${c.visitas_cita_id ? '<span class="v26-pill v26-pill--verificado"><i class="bi bi-geo-alt"></i> De una visita</span>' : ''}
          ${badgeVigencia(c, d)}
        </span>
      </span>
      <span class="ct-lado">
        <span class="ct-total">${money(c.total)}</span>
        <span class="ct-chev"><i class="bi bi-chevron-right"></i></span>
      </span>
    </a>`;
}

// ?ver=muestras (links viejos de avisos y correos): las muestras ahora
// tienen su propia pestaña.
if (new URLSearchParams(location.search).get('ver') === 'muestras') location.replace('muestras.php');

let cotizacionesData = [];
let filtroEstado = 'todas';
let busqueda = '';
const ORDEN_ESTADOS = ['pendiente', 'enviada', 'en_negociacion', 'aceptada', 'facturada', 'entregada', 'rechazada', 'cancelada'];
const PLURAL = { pendiente: 'Pendientes', enviada: 'Enviadas', en_negociacion: 'En negociación', aceptada: 'Aceptadas', facturada: 'Facturadas', entregada: 'Entregadas', rechazada: 'Rechazadas', cancelada: 'Canceladas' };

function pintarResumen() {
  const mes = HOY.slice(0, 7);
  const delMes = cotizacionesData.filter(c => { const d = fechaTz(c.created_at); return d && diaMx(d).slice(0, 7) === mes; });
  const monto = delMes.filter(c => c.estado !== 'cancelada').reduce((t, c) => t + Number(c.total || 0), 0);
  document.getElementById('st-mes').textContent = delMes.length;
  document.getElementById('st-mes-lbl').textContent = delMes.length === 1 ? 'Cotización este mes' : 'Cotizaciones este mes';
  document.getElementById('st-monto').textContent = '$' + Math.round(monto).toLocaleString('es-MX');
  document.getElementById('st-aceptadas').textContent = delMes.filter(c => ['aceptada', 'facturada', 'entregada'].includes(c.estado)).length;
}

function pintarFiltros() {
  const cuenta = {};
  cotizacionesData.forEach(c => { cuenta[c.estado] = (cuenta[c.estado] || 0) + 1; });
  const chips = [['todas', 'Todas', cotizacionesData.length]].concat(ORDEN_ESTADOS.filter(e => cuenta[e]).map(e => [e, PLURAL[e], cuenta[e]]));
  document.getElementById('filtro-estado').innerHTML = chips.map(([k, t, n]) =>
    `<div class="v26-funnel-chip ${k === filtroEstado ? 'active' : ''}" data-estado="${k}">${t} <span class="n">${n}</span></div>`).join('');
}

function renderLista() {
  const cont = document.getElementById('lista-cotizaciones');
  if (cotizacionesData.length === 0) {
    cont.innerHTML = `
      <div class="mu-vacio">
        <div class="mu-vacio-ico"><i class="bi bi-file-earmark-text"></i></div>
        <strong>Aún no tienes cotizaciones</strong>
        <p>Usa «Nueva cotización» arriba para armar la primera.</p>
      </div>`;
    return;
  }
  const q = busqueda.toLowerCase();
  const lista = cotizacionesData.filter(c =>
    (filtroEstado === 'todas' || c.estado === filtroEstado) &&
    (!q || String(c.folio).toLowerCase().includes(q) || String(c.cliente_nombre || '').toLowerCase().includes(q)));
  if (!lista.length) {
    cont.innerHTML = '<div class="mu-vacio"><div class="mu-vacio-ico"><i class="bi bi-search"></i></div><strong>Sin resultados</strong><p>Ninguna cotización coincide con el filtro.</p></div>';
    return;
  }
  let html = '', grupo = null;
  lista.forEach((c, i) => {
    const d = fechaTz(c.created_at);
    const g = d ? etiquetaDia(d) : 'Sin fecha';
    if (g !== grupo) {
      grupo = g;
      const delDia = lista.filter(x => { const dx = fechaTz(x.created_at); return (dx ? etiquetaDia(dx) : 'Sin fecha') === g; });
      html += `<div class="ct-dia"><b>${escHtml(g)}</b> ${delDia.length} cotizaci${delDia.length === 1 ? 'ón' : 'ones'}</div>`;
    }
    html += renderCotizacionItem(c, i);
  });
  cont.innerHTML = html;
}

document.getElementById('filtro-estado').addEventListener('click', (e) => {
  const chip = e.target.closest('.v26-funnel-chip');
  if (!chip) return;
  filtroEstado = chip.dataset.estado;
  pintarFiltros(); renderLista();
});
let tBuscar;
document.getElementById('buscar').addEventListener('input', (e) => {
  clearTimeout(tBuscar);
  tBuscar = setTimeout(() => { busqueda = e.target.value.trim(); renderLista(); }, 200);
});

async function cargarCotizaciones() {
  const res = await fetch('../api/cotizaciones.php').then(r => r.json()).catch(() => ({ ok: false, error: 'No se pudo cargar tus cotizaciones.' }));
  if (!res.ok) {
    document.getElementById('lista-cotizaciones').innerHTML = `<div class="alert alert-danger">${escHtml(res.error)}</div>`;
    return;
  }
  cotizacionesData = res.cotizaciones;
  pintarResumen(); pintarFiltros(); renderLista();
}
cargarCotizaciones().then(() => V26Tour.iniciar(PASOS_TOUR_COTIZACIONES, OPCIONES_TOUR_COTIZACIONES));
</script>
</body>
</html>
