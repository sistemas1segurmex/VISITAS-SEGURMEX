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

    <a href="solicitar_muestra.php" class="v26-cta mt-2">
      <span class="v26-cta-icon"><i class="bi bi-box-seam"></i></span>
      <span class="v26-cta-text">
        <strong>Solicitar muestra</strong>
        <small>Se procesa igual que en oficina, con autorización de Dirección</small>
      </span>
      <i class="bi bi-chevron-right chev"></i>
    </a>

    <div class="v26-seg v26-filtro-tipo" id="seg-tipo-lista">
      <button type="button" class="v26-seg-btn active" data-tipo="todo">Todo</button>
      <button type="button" class="v26-seg-btn" data-tipo="cotizaciones">Cotizaciones</button>
      <button type="button" class="v26-seg-btn" data-tipo="muestras">Muestras</button>
    </div>

    <div class="v26-funnel-filtro d-none" id="funnel-estado-muestra">
      <div class="v26-funnel-chip active" data-estado="todas">Todas <span class="n" id="n-todas">0</span></div>
      <div class="v26-funnel-chip" data-estado="por_autorizar">Por autorizar <span class="n" id="n-por_autorizar">0</span></div>
      <div class="v26-funnel-chip" data-estado="en_proceso">En proceso <span class="n" id="n-en_proceso">0</span></div>
      <div class="v26-funnel-chip" data-estado="entregadas">Entregadas <span class="n" id="n-entregadas">0</span></div>
      <div class="v26-funnel-chip" data-estado="rechazadas">Rechazadas <span class="n" id="n-rechazadas">0</span></div>
    </div>

    <div id="msg-muestras-error"></div>

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

// -------- Muestras: constantes de estado y helpers de fecha --------
const ETIQUETAS_TIPO_MUESTRA = { identico: 'Idéntico', variante: 'Variante' };
const PASOS_MUESTRA = [
  { icono: 'bi-send',                    txt: 'Solicitada' },
  { icono: 'bi-clipboard-check',         txt: 'Autorizada' },
  { icono: 'bi-gear-wide-connected',     txt: 'Producción' },
  { icono: 'bi-truck',                   txt: 'Entregada' },
];

function fechaCorta(iso) {
  if (!iso) return '';
  const d = new Date(iso.replace(' ', 'T'));
  if (isNaN(d)) return '';
  return d.toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' });
}

function grupoEstadoMuestra(m) {
  if (m.estado === 'rechazada') return 'rechazadas';
  if (m.entregada_en) return 'entregadas';
  if (m.estado === 'autorizada' || m.estado === 'liberada_produccion') return 'en_proceso';
  return 'por_autorizar';
}

function trackMuestraHTML(m) {
  const idxActual = m.entregada_en ? 3 : (m.estado === 'liberada_produccion' ? 2 : (m.estado === 'autorizada' ? 1 : 0));
  return `<div class="v26-muestra-track">` + PASOS_MUESTRA.map((p, i) => {
    const clase = i < idxActual ? 'hecho' : (i === idxActual ? 'actual' : '');
    const icono = i < idxActual ? 'bi-check' : p.icono;
    return `<div class="paso ${clase}"><div class="linea"></div><div class="dot"><i class="bi ${icono}"></i></div><div class="txt">${p.txt}</div></div>`;
  }).join('') + `</div>`;
}

function metaMuestraHTML(m) {
  const chips = [];
  if (m.talla) chips.push(`<span><i class="bi bi-rulers"></i> Talla ${escHtml(m.talla)}</span>`);
  chips.push(`<span><i class="bi bi-shuffle"></i> ${escHtml(ETIQUETAS_TIPO_MUESTRA[m.tipo] || m.tipo_etiqueta || m.tipo)}</span>`);
  if (m.fecha_promesa) chips.push(`<span><i class="bi bi-calendar-event"></i> Promesa: ${fechaCorta(m.fecha_promesa)}</span>`);
  if (m.guia_envio) chips.push(`<span><i class="bi bi-upc-scan"></i> Guía ${escHtml(m.guia_envio)}</span>`);
  return `<div class="v26-muestra-meta">${chips.join('')}</div>`;
}

function detalleMuestraHTML(m) {
  const filas = [];
  filas.push(`<div>Solicitada el <strong>${fechaCorta(m.created_at)}</strong></div>`);
  if (m.autoriza_nombre) filas.push(`<div>Autorizó: <strong>${escHtml(m.autoriza_nombre)}</strong></div>`);
  if (m.destino_modo === 'direccion' && m.destino_entrega) {
    filas.push(`<div>Entrega en: <strong>${escHtml(m.destino_entrega)}</strong></div>`);
  } else if (m.destino_modo === 'vendedor') {
    filas.push(`<div>Se recoge en Almacén (oficina).</div>`);
  }
  if (m.entregada_en) filas.push(`<div>Entregada el <strong>${fechaCorta(m.entregada_en)}</strong></div>`);
  return `<div class="v26-muestra-detalle-inner">${filas.join('')}</div>`;
}

function renderMuestraItem(m) {
  const rechazada = m.estado === 'rechazada';
  return `
    <div class="v26-muestra-card${rechazada ? ' v26-muestra-card--rechazada' : ''}" data-key="${escHtml(m.folio)}">
      <div class="v26-muestra-top">
        <span class="v26-muestra-icon"><i class="bi ${rechazada ? 'bi-x-circle' : 'bi-box-seam'}"></i></span>
        <div class="v26-muestra-info">
          <div class="folio">${escHtml(m.folio)}</div>
          <div class="cliente">${escHtml(m.cliente_nombre)} — ${escHtml(m.estilo_nombre)}</div>
        </div>
        <i class="bi bi-chevron-right chev"></i>
      </div>
      ${rechazada
        ? `<div class="v26-muestra-rechazo"><i class="bi bi-exclamation-triangle-fill"></i> ${escHtml(m.motivo_rechazo || 'Rechazada por Dirección.')}</div>`
        : trackMuestraHTML(m)}
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
  const grupos = { todas: muestrasData.length, por_autorizar: 0, en_proceso: 0, entregadas: 0, rechazadas: 0 };
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
  if (resMue.ok) muestrasData = resMue.muestras;

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
cargarCotizacionesYMuestras();
</script>
</body>
</html>
