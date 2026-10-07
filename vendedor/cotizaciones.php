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

    <a href="solicitar_muestra.php" class="v26-cta mt-2" data-tour="cta-muestra">
      <span class="v26-cta-icon"><i class="bi bi-box-seam"></i></span>
      <span class="v26-cta-text">
        <strong>Solicitar muestra</strong>
        <small id="cta-muestra-sub">Se le avisa a la responsable de muestras</small>
      </span>
      <i class="bi bi-chevron-right chev"></i>
    </a>

    <div class="v26-seg v26-filtro-tipo" id="seg-tipo-lista" data-tour="filtro-tipo">
      <button type="button" class="v26-seg-btn active" data-tipo="todo">Todo</button>
      <button type="button" class="v26-seg-btn" data-tipo="cotizaciones">Cotizaciones</button>
      <button type="button" class="v26-seg-btn" data-tipo="muestras">Muestras</button>
    </div>

    <div class="v26-funnel-filtro d-none" id="funnel-estado-muestra">
      <div class="v26-funnel-chip active" data-estado="todas">Todas <span class="n" id="n-todas">0</span></div>
      <div class="v26-funnel-chip" data-estado="en_curso">En curso <span class="n" id="n-en_curso">0</span></div>
      <div class="v26-funnel-chip" data-estado="embarcadas">Embarcadas <span class="n" id="n-embarcadas">0</span></div>
      <div class="v26-funnel-chip" data-estado="canceladas">Canceladas <span class="n" id="n-canceladas">0</span></div>
    </div>

    <div id="msg-muestras-error"></div>

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
  { selector: '[data-tour="cta-muestra"]', texto: 'Pide una muestra para un cliente o prospecto. Le llega un aviso a la responsable de muestras, y a ti te avisamos cuando esté en preparación y cuando se embarque.' },
  { selector: '[data-tour="filtro-tipo"]', texto: 'Filtra entre cotizaciones y muestras, o velo todo junto.' },
  { selector: '#lista-cotizaciones .v26-cita, #lista-cotizaciones .v26-muestra-card', texto: 'Toca cualquiera para ver el detalle. En las muestras ves en qué paso va y, ya embarcada, la paquetería y la guía.' },
  { selector: '.v26-avisos-btn', texto: 'Aquí te llegan los avisos de tus muestras. El número rojo son los que no has visto.' },
];
const OPCIONES_TOUR_COTIZACIONES = {
  storageKey: 'v26_tour_cotizaciones_visto',
  saludoTitulo: 'Cotizaciones y muestras',
  saludoTexto: 'Un par de cosas rápidas antes de que las uses.',
  finalTexto: 'Repite este recorrido cuando quieras tocando el ícono ? de arriba.',
};
document.getElementById('btn-tour-ayuda').addEventListener('click', () => V26Tour.reiniciar(PASOS_TOUR_COTIZACIONES, OPCIONES_TOUR_COTIZACIONES));
document.getElementById('btn-tour-guiado').addEventListener('click', () => V26Tour.reiniciar(PASOS_TOUR_COTIZACIONES, OPCIONES_TOUR_COTIZACIONES));

// -------- Muestras: constantes de estado y helpers de fecha --------
// Etapa "solo aviso" (07-oct-2026): la responsable de muestras surte la
// solicitud y marca el avance -- enviada -> en_preparacion -> embarcada (o
// cancelada). Ver includes/muestras.php.
const ETIQUETAS_TIPO_MUESTRA = { identico: 'Idéntico al estilo', variante: 'Variante' };
const CATEGORIAS_CAMBIO_MUESTRA = { casco: 'Casco', suela: 'Suela', piel: 'Piel', forro: 'Forro' };
const PASOS_MUESTRA = [
  { icono: 'bi-send',          txt: 'Enviada' },
  { icono: 'bi-box-seam',      txt: 'En preparación' },
  { icono: 'bi-truck',         txt: 'Embarcada' },
];
let responsableMuestras = null;

function fechaCorta(iso) {
  if (!iso) return '';
  // created_at/actualizada_en vienen en UTC; fecha_promesa es solo fecha.
  const soloFecha = /^\d{4}-\d{2}-\d{2}$/.test(iso);
  const d = new Date(soloFecha ? iso + 'T12:00:00' : iso.replace(' ', 'T') + (iso.endsWith('Z') ? '' : 'Z'));
  if (isNaN(d)) return '';
  return d.toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'America/Mexico_City' });
}

function grupoEstadoMuestra(m) {
  if (m.estado === 'cancelada') return 'canceladas';
  if (m.estado === 'embarcada') return 'embarcadas';
  return 'en_curso';
}

function textoEnvioMuestra(m) {
  if (m.envio_modo === 'en_persona') return 'Entregada en persona';
  return [m.paqueteria, m.guia ? 'guía ' + m.guia : ''].filter(Boolean).join(', ') || 'Por paquetería';
}

function trackMuestraHTML(m) {
  // embarcada = los 3 pasos completos.
  const idxActual = m.estado === 'embarcada' ? 3 : (m.estado === 'en_preparacion' ? 1 : 0);
  return `<div class="v26-muestra-track">` + PASOS_MUESTRA.map((p, i) => {
    const clase = i < idxActual ? 'hecho' : (i === idxActual ? 'actual' : '');
    const icono = i < idxActual ? 'bi-check' : p.icono;
    return `<div class="paso ${clase}"><div class="linea"></div><div class="dot"><i class="bi ${icono}"></i></div><div class="txt">${p.txt}</div></div>`;
  }).join('') + `</div>`;
}

function textoEstadoMuestra(m) {
  const quien = responsableMuestras ? escHtml(responsableMuestras) : 'la responsable de muestras';
  if (m.estado === 'enviada') return `Se le avisó a ${quien}. Te avisaremos cuando empiece a prepararla.`;
  if (m.estado === 'en_preparacion') return `${responsableMuestras ? quien : 'La responsable de muestras'} ya la está preparando. Te avisaremos cuando se embarque.`;
  if (m.estado === 'embarcada') return `<i class="bi bi-truck"></i> ${escHtml(textoEnvioMuestra(m))}`;
  return '';
}

function metaMuestraHTML(m) {
  const chips = [];
  if (m.talla) chips.push(`<span><i class="bi bi-rulers"></i> Talla ${escHtml(m.talla)}</span>`);
  chips.push(`<span><i class="bi bi-shuffle"></i> ${escHtml(ETIQUETAS_TIPO_MUESTRA[m.tipo] || m.tipo)}</span>`);
  if (m.fecha_promesa) chips.push(`<span><i class="bi bi-calendar-event"></i> Promesa: ${fechaCorta(m.fecha_promesa)}</span>`);
  return `<div class="v26-muestra-meta">${chips.join('')}</div>`;
}

function detalleMuestraHTML(m) {
  const filas = [];
  filas.push(`<div>Solicitada el <strong>${fechaCorta(m.created_at)}</strong></div>`);
  if (m.destino_direccion) filas.push(`<div>Entregar en: <strong>${escHtml(m.destino_direccion)}</strong></div>`);
  (m.cambios || []).forEach(c => {
    const cat = c.categoria === 'otro' ? (c.categoria_otro || 'Otro') : (CATEGORIAS_CAMBIO_MUESTRA[c.categoria] || c.categoria);
    filas.push(`<div>${escHtml(cat)}: <strong>${escHtml(c.descripcion)}</strong></div>`);
  });
  if (m.estado === 'embarcada') filas.push(`<div>Embarcada el <strong>${fechaCorta(m.actualizada_en)}</strong> · ${escHtml(textoEnvioMuestra(m))}</div>`);
  return `<div class="v26-muestra-detalle-inner">${filas.join('')}</div>`;
}

function renderMuestraItem(m) {
  const cancelada = m.estado === 'cancelada';
  const estado = textoEstadoMuestra(m);
  return `
    <div class="v26-muestra-card${cancelada ? ' v26-muestra-card--rechazada' : ''}" data-key="${escHtml(m.folio)}">
      <div class="v26-muestra-top">
        <span class="v26-muestra-icon"><i class="bi ${cancelada ? 'bi-x-circle' : (m.estado === 'embarcada' ? 'bi-truck' : 'bi-box-seam')}"></i></span>
        <div class="v26-muestra-info">
          <div class="folio">${escHtml(m.folio)}</div>
          <div class="cliente">${escHtml(m.cliente_nombre)} — ${escHtml(m.estilo_nombre)}</div>
        </div>
        <i class="bi bi-chevron-right chev"></i>
      </div>
      ${cancelada
        ? `<div class="v26-muestra-rechazo"><i class="bi bi-exclamation-triangle-fill"></i> Cancelada: ${escHtml(m.motivo_cancelacion || '')}</div>`
        : trackMuestraHTML(m)}
      ${estado ? `<div class="v26-muestra-estado">${estado}</div>` : ''}
      ${metaMuestraHTML(m)}
      <div class="v26-muestra-detalle">${detalleMuestraHTML(m)}</div>
    </div>`;
}

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

// -------- Estado de filtros + datos ya cargados --------
let cotizacionesData = [];
let muestrasData = [];
let filtroTipo = 'todo';
let filtroEstadoMuestra = 'todas';

function actualizarContadoresMuestra() {
  const grupos = { todas: muestrasData.length, en_curso: 0, embarcadas: 0, canceladas: 0 };
  muestrasData.forEach(m => { grupos[grupoEstadoMuestra(m)]++; });
  Object.keys(grupos).forEach(g => {
    const el = document.getElementById('n-' + g);
    if (el) el.textContent = grupos[g];
  });
}

function renderLista() {
  const cont = document.getElementById('lista-cotizaciones');

  let items = [];
  if (filtroTipo !== 'muestras') {
    items = items.concat(cotizacionesData.map(c => ({ tipo: 'cotizacion', fecha: c.created_at, data: c })));
  }
  if (filtroTipo !== 'cotizaciones') {
    const muestrasFiltradas = filtroEstadoMuestra === 'todas'
      ? muestrasData
      : muestrasData.filter(m => grupoEstadoMuestra(m) === filtroEstadoMuestra);
    items = items.concat(muestrasFiltradas.map(m => ({ tipo: 'muestra', fecha: m.created_at, data: m })));
  }
  items.sort((a, b) => new Date(b.fecha.replace(' ', 'T')) - new Date(a.fecha.replace(' ', 'T')));

  if (items.length === 0) {
    const mensaje = filtroTipo === 'muestras'
      ? 'No tienes solicitudes de muestra con este filtro.'
      : filtroTipo === 'cotizaciones'
        ? 'Aún no has hecho ninguna cotización.<br>Usa "Nueva cotización" arriba para armar la primera.'
        : 'Aún no tienes cotizaciones ni muestras.<br>Usa los botones de arriba para crear la primera.';
    cont.innerHTML = `
      <div class="v26-empty">
        <div class="icon"><i class="bi bi-file-earmark-text"></i></div>
        <p>${mensaje}</p>
      </div>`;
    return;
  }

  cont.innerHTML = items.map(it => it.tipo === 'cotizacion' ? renderCotizacionItem(it.data) : renderMuestraItem(it.data)).join('');
}

document.getElementById('lista-cotizaciones').addEventListener('click', (e) => {
  const card = e.target.closest('.v26-muestra-card');
  if (card) card.classList.toggle('abierta');
});

document.getElementById('seg-tipo-lista').addEventListener('click', (e) => {
  const btn = e.target.closest('.v26-seg-btn');
  if (!btn) return;
  document.querySelectorAll('#seg-tipo-lista .v26-seg-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  filtroTipo = btn.dataset.tipo;
  document.getElementById('funnel-estado-muestra').classList.toggle('d-none', filtroTipo === 'cotizaciones');
  renderLista();
});

document.getElementById('funnel-estado-muestra').addEventListener('click', (e) => {
  const chip = e.target.closest('.v26-funnel-chip');
  if (!chip) return;
  document.querySelectorAll('#funnel-estado-muestra .v26-funnel-chip').forEach(c => c.classList.remove('active'));
  chip.classList.add('active');
  filtroEstadoMuestra = chip.dataset.estado;
  renderLista();
});

async function cargarCotizacionesYMuestras() {
  const [resCot, resMue] = await Promise.all([
    fetch('../api/cotizaciones.php').then(r => r.json()).catch(() => ({ ok: false, error: 'No se pudo cargar tus cotizaciones.' })),
    fetch('../api/muestras_listar.php').then(r => r.json()).catch(() => ({ ok: false, error: 'No se pudo cargar tus muestras.' })),
  ]);

  if (resCot.ok) cotizacionesData = resCot.cotizaciones;
  if (resMue.ok) {
    muestrasData = resMue.muestras;
    responsableMuestras = resMue.responsable || null;
    if (resMue.tope_mes != null) {
      const quedan = Math.max(0, resMue.tope_mes - (resMue.usadas_mes || 0));
      document.getElementById('cta-muestra-sub').textContent = quedan === 0
        ? `Ya usaste tus ${resMue.tope_mes} muestras de este mes`
        : `Te ${quedan === 1 ? 'queda 1' : 'quedan ' + quedan} de ${resMue.tope_mes} este mes`;
    }
  }

  if (!resCot.ok && !resMue.ok) {
    document.getElementById('lista-cotizaciones').innerHTML = `<div class="alert alert-danger">${escHtml(resCot.error || resMue.error)}</div>`;
    return;
  }
  if (!resMue.ok) {
    document.getElementById('msg-muestras-error').innerHTML =
      `<div class="alert alert-warning py-2 small">${escHtml(resMue.error || 'No se pudieron cargar tus solicitudes de muestra.')}</div>`;
  }

  actualizarContadoresMuestra();
  renderLista();
}
// ?ver=muestras (links de los avisos y correos de muestras): abre directo en
// la pestaña Muestras.
if (new URLSearchParams(location.search).get('ver') === 'muestras') {
  const btnMuestras = document.querySelector('#seg-tipo-lista .v26-seg-btn[data-tipo="muestras"]');
  document.querySelectorAll('#seg-tipo-lista .v26-seg-btn').forEach(b => b.classList.toggle('active', b === btnMuestras));
  filtroTipo = 'muestras';
  document.getElementById('funnel-estado-muestra').classList.remove('d-none');
}
// Si llega un aviso nuevo mientras está abierta, se refresca la lista.
let ultimosNoLeidos = null;
document.addEventListener('v26:avisos', (e) => {
  const n = e.detail.no_leidos || 0;
  if (ultimosNoLeidos !== null && n > ultimosNoLeidos) cargarCotizacionesYMuestras();
  ultimosNoLeidos = n;
});
cargarCotizacionesYMuestras().then(() => V26Tour.iniciar(PASOS_TOUR_COTIZACIONES, OPCIONES_TOUR_COTIZACIONES));
</script>
</body>
</html>
