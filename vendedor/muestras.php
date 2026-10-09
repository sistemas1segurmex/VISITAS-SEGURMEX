<?php
// Mis muestras del vendedor externo (pestaña «Muestras»): pedir una muestra y
// ver en qué va cada una. Etapa "solo aviso" -- ver includes/muestras.php.
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
<title>Mis muestras</title>
<link rel="icon" type="image/png" href="../assets/img/Favv-Segu.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css<?= assetVer(__DIR__ . '/../assets/css/style.css') ?>">
<link rel="stylesheet" href="../assets/css/vendedor-2026.css<?= assetVer(__DIR__ . '/../assets/css/vendedor-2026.css') ?>">
<link rel="stylesheet" href="../assets/css/muestras.css<?= assetVer(__DIR__ . '/../assets/css/muestras.css') ?>">
</head>
<body class="v26">
  <div class="v26-header">
    <div class="v26-topbar">
      <div class="v26-topbar-left">
        <a href="index.php" class="v26-back v26-tip v26-tip--bottom" data-tip="Volver a mis visitas" aria-label="Volver"><i class="bi bi-arrow-left"></i></a>
        <div class="v26-greeting">
          <div class="hi">Ventas</div>
          <div class="name">Mis muestras</div>
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
      <a href="cotizaciones.php"><i class="bi bi-file-earmark-text-fill"></i>Cotizar</a>
      <a href="muestras.php" class="active"><i class="bi bi-box-seam-fill"></i>Muestras</a>
      <a href="reporte.php"><i class="bi bi-bar-chart-fill"></i>Reporte</a>
      <a href="mis_paradas.php"><i class="bi bi-signpost-2-fill"></i>Paradas</a>
    </div>
  </div>

  <div class="v26-wrap mu-wrap--vend">
    <section class="mu-hero mu-hero--vend">
      <div class="mu-hero-txt">
        <div class="mu-hero-eyebrow"><i class="bi bi-box-seam"></i> Muestras para tus clientes</div>
        <h1 class="mu-hero-titulo">Pide una muestra y síguela aquí</h1>
        <p class="mu-hero-sub">La recibe el equipo de Segurmex. Te avisamos aquí y por correo cuando esté en preparación y cuando se embarque.</p>
      </div>
      <div class="mu-cupo" data-tour="cupo">
        <div class="mu-cupo-txt"><span id="cupo-n">–</span> de <span id="cupo-tope">5</span><small>disponibles este mes</small></div>
        <div class="mu-cupo-barra"><span id="cupo-barra" style="width:0%"></span></div>
      </div>
    </section>

    <a href="solicitar_muestra.php" class="v26-cta" data-tour="cta-muestra" id="cta-muestra">
      <span class="v26-cta-icon"><i class="bi bi-plus-lg"></i></span>
      <span class="v26-cta-text">
        <strong>Solicitar muestra</strong>
        <small id="cta-muestra-sub">Para un cliente o prospecto tuyo</small>
      </span>
      <i class="bi bi-chevron-right chev"></i>
    </a>

    <div class="mu-stats mu-stats--3 mt-3" data-tour="filtros">
      <button type="button" class="mu-stat mu-stat--prep activa" data-estado="en_curso">
        <span class="mu-stat-ico"><i class="bi bi-hourglass-split"></i></span>
        <span><span class="mu-stat-n" id="n-en_curso">0</span><span class="mu-stat-lbl">En curso</span></span>
      </button>
      <button type="button" class="mu-stat mu-stat--emb" data-estado="embarcadas">
        <span class="mu-stat-ico"><i class="bi bi-truck"></i></span>
        <span><span class="mu-stat-n" id="n-embarcadas">0</span><span class="mu-stat-lbl">Embarcadas</span></span>
      </button>
      <button type="button" class="mu-stat mu-stat--cancel" data-estado="canceladas">
        <span class="mu-stat-ico"><i class="bi bi-x-circle-fill"></i></span>
        <span><span class="mu-stat-n" id="n-canceladas">0</span><span class="mu-stat-lbl">Canceladas</span></span>
      </button>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-2">
      <div class="mu-sec-titulo mb-0" id="titulo-lista">En curso</div>
      <button type="button" class="mu-ver-todas" id="btn-todas">Ver todas (<span id="n-todas">0</span>)</button>
    </div>

    <div id="msg-muestras-error"></div>
    <div id="lista-muestras">
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
function escHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

const PASOS_TOUR_MUESTRAS = [
  { selector: '[data-tour="cta-muestra"]', texto: 'Pide una muestra para un cliente o prospecto. La recibe el equipo de Segurmex.' },
  { selector: '[data-tour="cupo"]', texto: 'Cuántas muestras te quedan este mes. Las canceladas no cuentan.' },
  { selector: '[data-tour="filtros"]', texto: 'Toca para ver tus muestras en curso, las embarcadas o las canceladas.' },
  { selector: '#lista-muestras .v26-muestra-card', texto: 'Toca una muestra para ver el detalle. Ya embarcada, aquí ves la paquetería y la guía.' },
  { selector: '.v26-avisos-btn', texto: 'Aquí te llegan los avisos de tus muestras. El número rojo son los que no has visto.' },
];
const OPCIONES_TOUR_MUESTRAS = {
  storageKey: 'v26_tour_muestras_visto',
  saludoTitulo: 'Tus muestras',
  saludoTexto: 'Un par de cosas rápidas antes de que las uses.',
  finalTexto: 'Repite este recorrido cuando quieras tocando el ícono ? de arriba.',
};
document.getElementById('btn-tour-ayuda').addEventListener('click', () => V26Tour.reiniciar(PASOS_TOUR_MUESTRAS, OPCIONES_TOUR_MUESTRAS));
document.getElementById('btn-tour-guiado').addEventListener('click', () => V26Tour.reiniciar(PASOS_TOUR_MUESTRAS, OPCIONES_TOUR_MUESTRAS));

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
  // Al vendedor externo no se le muestra quién atiende (ver includes/muestras.php).
  if (m.estado === 'enviada') return 'Tu solicitud ya está con el equipo de Segurmex. Te avisaremos cuando empiece a prepararla.';
  if (m.estado === 'en_preparacion') {
    if (m.preparacion === 'pt') return '<i class="bi bi-box-seam"></i> Ya hay en almacén (Producto Terminado): se está preparando el envío. Te avisaremos cuando se embarque.';
    if (m.preparacion === 'por_programar') return '<i class="bi bi-gear"></i> No hay en almacén: se va a fabricar.'
      + (m.fecha_estimada_pt ? ` Fecha estimada para tenerla lista: <strong>${fechaCorta(m.fecha_estimada_pt)}</strong>.` : '')
      + ' Te avisaremos cuando esté lista y cuando se embarque.';
    return 'El equipo de Segurmex ya la está preparando. Te avisaremos cuando se embarque.';
  }
  if (m.estado === 'embarcada') return `<i class="bi bi-truck"></i> ${escHtml(textoEnvioMuestra(m))}`;
  return '';
}

function metaMuestraHTML(m) {
  const chips = [];
  if (m.color) chips.push(`<span><i class="bi bi-palette"></i> ${escHtml(m.color)}</span>`);
  if (m.talla) chips.push(`<span><i class="bi bi-rulers"></i> Talla ${escHtml(m.talla)}</span>`);
  const pares = m.cantidad || 1;
  chips.push(`<span><i class="bi bi-boxes"></i> ${pares} ${pares === 1 ? 'par' : 'pares'}</span>`);
  chips.push(`<span><i class="bi bi-shuffle"></i> ${escHtml(ETIQUETAS_TIPO_MUESTRA[m.tipo] || m.tipo)}</span>`);
  if (m.fecha_promesa) chips.push(`<span><i class="bi bi-calendar-event"></i> Promesa: ${fechaCorta(m.fecha_promesa)}</span>`);
  return `<div class="v26-muestra-meta">${chips.join('')}</div>`;
}

function detalleMuestraHTML(m) {
  const filas = [];
  filas.push(`<div>Solicitada el <strong>${fechaCorta(m.created_at)}</strong></div>`);
  if (m.contacto_nombre || m.contacto_telefono) filas.push(`<div>Contacto: <strong>${escHtml([m.contacto_nombre, m.contacto_telefono].filter(Boolean).join(' · '))}</strong></div>`);
  if (m.motivo) filas.push(`<div>Motivo: <strong>${escHtml(m.motivo)}</strong></div>`);
  if (m.tiempo_prueba_dias) filas.push(`<div>Tiempo de prueba: <strong>${m.tiempo_prueba_dias} ${m.tiempo_prueba_dias === 1 ? 'día' : 'días'}</strong></div>`);
  if (m.notas_planta) filas.push(`<div>Notas para planta: <strong>${escHtml(m.notas_planta)}</strong></div>`);
  filas.push(`<div>Entregar: <strong>${m.entregar_a === 'vendedor' ? 'A ti' : 'Al cliente'}</strong></div>`);
  if (m.destino_direccion) filas.push(`<div>Dirección: <strong>${escHtml(m.destino_direccion)}</strong></div>`);
  (m.cambios || []).forEach(c => {
    const cat = c.categoria === 'otro' ? (c.categoria_otro || 'Otro') : (CATEGORIAS_CAMBIO_MUESTRA[c.categoria] || c.categoria);
    filas.push(`<div>${escHtml(cat)}: <strong>${escHtml(c.descripcion)}</strong></div>`);
  });
  if (m.estado === 'embarcada') filas.push(`<div>Embarcada el <strong>${fechaCorta(m.actualizada_en)}</strong> · ${escHtml(textoEnvioMuestra(m))}</div>`);
  filas.push(`<div class="mt-2"><a href="../muestra_pdf.php?id=${encodeURIComponent(m.id)}" class="v26-btn v26-btn-ghost" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf"></i> Ver PDF</a></div>`);
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

let muestrasData = [];
let filtro = 'en_curso';
const TITULOS = { en_curso: 'En curso', embarcadas: 'Embarcadas', canceladas: 'Canceladas', todas: 'Todas tus muestras' };
const VACIOS = {
  en_curso: 'No tienes muestras en curso.<br>Usa «Solicitar muestra» para pedir una.',
  embarcadas: 'Todavía no tienes muestras embarcadas.',
  canceladas: 'No tienes muestras canceladas.',
  todas: 'Aún no has pedido ninguna muestra.<br>Usa «Solicitar muestra» para pedir la primera.',
};

function renderLista() {
  const cont = document.getElementById('lista-muestras');
  const grupos = { todas: muestrasData.length, en_curso: 0, embarcadas: 0, canceladas: 0 };
  muestrasData.forEach(m => { grupos[grupoEstadoMuestra(m)]++; });
  Object.entries(grupos).forEach(([g, n]) => { const el = document.getElementById('n-' + g); if (el) el.textContent = n; });
  document.querySelectorAll('.mu-stat').forEach(b => b.classList.toggle('activa', b.dataset.estado === filtro));
  document.getElementById('titulo-lista').textContent = TITULOS[filtro];
  document.getElementById('btn-todas').classList.toggle('d-none', filtro === 'todas');
  const lista = filtro === 'todas' ? muestrasData : muestrasData.filter(m => grupoEstadoMuestra(m) === filtro);
  cont.innerHTML = lista.length
    ? lista.map(renderMuestraItem).join('')
    : `<div class="mu-vacio"><div class="mu-vacio-ico"><i class="bi bi-box-seam"></i></div><p>${VACIOS[filtro]}</p></div>`;
}

function pintarCupo(usadas, tope) {
  const quedan = Math.max(0, tope - usadas);
  document.getElementById('cupo-n').textContent = quedan;
  document.getElementById('cupo-tope').textContent = tope;
  document.getElementById('cupo-barra').style.width = Math.round((quedan / tope) * 100) + '%';
  document.querySelector('.mu-cupo').classList.toggle('agotado', quedan === 0);
  const cta = document.getElementById('cta-muestra');
  cta.classList.toggle('v26-cta--disabled', quedan === 0);
  document.getElementById('cta-muestra-sub').textContent = quedan === 0
    ? `Ya usaste tus ${tope} muestras de este mes`
    : `Te ${quedan === 1 ? 'queda 1' : 'quedan ' + quedan} de ${tope} este mes`;
}

document.querySelectorAll('.mu-stat').forEach(b => b.addEventListener('click', () => { filtro = b.dataset.estado; renderLista(); }));
document.getElementById('btn-todas').addEventListener('click', () => { filtro = 'todas'; renderLista(); });
document.getElementById('lista-muestras').addEventListener('click', (e) => {
  if (e.target.closest('a')) return; // "Ver PDF" no abre/cierra la tarjeta
  const card = e.target.closest('.v26-muestra-card');
  if (card) card.classList.toggle('abierta');
});

async function cargarMuestras() {
  try {
    const r = await fetch('../api/muestras_listar.php', { cache: 'no-store' });
    const data = await r.json();
    if (!data.ok) throw new Error(data.error || '');
    muestrasData = data.muestras;
    pintarCupo(data.usadas_mes || 0, data.tope_mes || 5);
    // Sin nada en curso pero con historial: abre en "Todas".
    if (filtro === 'en_curso' && !muestrasData.some(m => grupoEstadoMuestra(m) === 'en_curso') && muestrasData.length) filtro = 'todas';
    renderLista();
  } catch (e) {
    document.getElementById('lista-muestras').innerHTML =
      `<div class="alert alert-danger">${escHtml(e.message || 'No se pudieron cargar tus muestras. Revisa tu conexión e intenta de nuevo.')}</div>`;
  }
}
// Si llega un aviso nuevo mientras está abierta, se refresca la lista.
let ultimosNoLeidos = null;
document.addEventListener('v26:avisos', (e) => {
  const n = e.detail.no_leidos || 0;
  if (ultimosNoLeidos !== null && n > ultimosNoLeidos) cargarMuestras();
  ultimosNoLeidos = n;
});
cargarMuestras().then(() => V26Tour.iniciar(PASOS_TOUR_MUESTRAS, OPCIONES_TOUR_MUESTRAS));
</script>
</body>
</html>
