<?php
// Cotizaciones de la responsable de muestras (rol 'muestras'): ve las de
// todos los vendedores de VISITAS y captura nuevas a nombre del vendedor que
// elija (vendedor/nueva_cotizacion.php). El detalle se abre en
// vendedor/ver_cotizacion.php, igual que el vendedor.
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$u = requireRole('muestras');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cotizaciones</title>
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
        <div class="v26-greeting">
          <div class="hi">Control de Visitas</div>
          <div class="name">Cotizaciones</div>
        </div>
      </div>
      <div class="v26-topbar-right">
        <img src="../logo.png" alt="Segurmex" class="v26-logo">
        <a href="../logout.php" class="v26-icon-btn v26-tip v26-tip--bottom" data-tip="Cerrar sesión" aria-label="Salir"><i class="bi bi-box-arrow-right"></i></a>
      </div>
    </div>
  </div>

  <div class="v26-wrap mu-wrap mu-wrap--bandeja">
    <nav class="mu-tabs" aria-label="Secciones">
      <div class="mu-tabs-in">
        <a class="mu-tab" href="index.php"><i class="bi bi-box-seam"></i> Muestras</a>
        <a class="mu-tab activa" href="cotizaciones.php" aria-current="page"><i class="bi bi-file-earmark-text"></i> Cotizaciones</a>
      </div>
    </nav>

    <section class="mu-hero">
      <div class="mu-hero-txt">
        <div class="mu-hero-eyebrow"><i class="bi bi-file-earmark-text"></i> Cotizaciones de vendedores</div>
        <h1 class="mu-hero-titulo" id="hero-titulo">Cargando...</h1>
        <p class="mu-hero-sub">Aquí ves las cotizaciones que tú capturaste. Las haces a nombre de cualquier vendedor y quedan en su cartera.</p>
      </div>
      <a class="mu-hero-acc" href="../vendedor/nueva_cotizacion.php"><i class="bi bi-plus-lg"></i> Nueva cotización</a>
    </section>

    <div class="mu-stats">
      <button type="button" class="mu-stat mu-stat--cpend" data-grupo="pendiente">
        <span class="mu-stat-ico"><i class="bi bi-hourglass-split"></i></span>
        <span><span class="mu-stat-n" id="s-pendiente">0</span><span class="mu-stat-lbl">Pendientes</span></span>
      </button>
      <button type="button" class="mu-stat mu-stat--cenv" data-grupo="enviada">
        <span class="mu-stat-ico"><i class="bi bi-send-fill"></i></span>
        <span><span class="mu-stat-n" id="s-enviada">0</span><span class="mu-stat-lbl">Enviadas</span></span>
      </button>
      <button type="button" class="mu-stat mu-stat--cneg" data-grupo="en_negociacion">
        <span class="mu-stat-ico"><i class="bi bi-chat-dots-fill"></i></span>
        <span><span class="mu-stat-n" id="s-en_negociacion">0</span><span class="mu-stat-lbl">En negociación</span></span>
      </button>
      <button type="button" class="mu-stat mu-stat--cacep" data-grupo="aceptadas">
        <span class="mu-stat-ico"><i class="bi bi-check-lg"></i></span>
        <span><span class="mu-stat-n" id="s-aceptadas_mes">0</span><span class="mu-stat-lbl">Aceptadas este mes</span></span>
      </button>
    </div>

    <div class="mu-toolbar">
      <div class="v26-funnel-filtro mb-0" id="filtro-grupo">
        <div class="v26-funnel-chip active" data-grupo="abiertas">Abiertas <span class="n" id="n-abiertas">0</span></div>
        <div class="v26-funnel-chip" data-grupo="aceptadas">Aceptadas <span class="n" id="n-aceptadas">0</span></div>
        <div class="v26-funnel-chip" data-grupo="cerradas">Cerradas <span class="n" id="n-cerradas">0</span></div>
        <div class="v26-funnel-chip" data-grupo="todas">Todas <span class="n" id="n-todas">0</span></div>
      </div>
      <div class="mu-cot-buscar">
        <select id="filtro-vendedor" class="v26-select" aria-label="Filtrar por vendedor">
          <option value="">Todos los vendedores</option>
        </select>
        <div class="v26-search mb-0">
          <i class="bi bi-search"></i>
          <input type="text" id="buscar" class="v26-input" placeholder="Buscar folio o cliente...">
        </div>
      </div>
    </div>

    <div id="lista" class="mu-grid">
      <div class="v26-skel"></div>
      <div class="v26-skel"></div>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/avisos.js<?= assetVer(__DIR__ . '/../assets/js/avisos.js') ?>"></script>
<script src="../assets/js/muestras.js<?= assetVer(__DIR__ . '/../assets/js/muestras.js') ?>"></script>
<script>
const NOMBRE = <?= json_encode(strtok($u['nombre'], ' ')) ?>;
const ETIQUETAS = { pendiente: 'Pendiente', enviada: 'Enviada', en_negociacion: 'En negociación', aceptada: 'Aceptada', facturada: 'Facturada', entregada: 'Entregada', rechazada: 'Rechazada', cancelada: 'Cancelada' };
const ICONO_ESTADO = { pendiente: 'bi-hourglass-split', enviada: 'bi-send-fill', en_negociacion: 'bi-chat-dots-fill', aceptada: 'bi-check-lg', facturada: 'bi-receipt', entregada: 'bi-truck', rechazada: 'bi-x-lg', cancelada: 'bi-slash-circle' };
// Grupos de los filtros; un estado suelto (las tarjetas de arriba) filtra solo ese estado.
const GRUPOS = {
  abiertas: ['pendiente', 'enviada', 'en_negociacion'],
  aceptadas: ['aceptada', 'facturada', 'entregada'],
  cerradas: ['rechazada', 'cancelada'],
};
const VACIO = {
  abiertas: ['bi-inbox', 'Sin cotizaciones abiertas', 'Las cotizaciones pendientes, enviadas o en negociación aparecen aquí.'],
  aceptadas: ['bi-check2-circle', 'Sin aceptadas todavía', 'Aquí quedan las que el cliente aceptó.'],
  cerradas: ['bi-archive', 'Sin cerradas', 'Aquí quedan las rechazadas y las canceladas.'],
  todas: ['bi-file-earmark-text', 'Sin cotizaciones', 'Todavía no hay cotizaciones de los vendedores.'],
};
let cotizaciones = [];
let grupo = 'abiertas';
let busqueda = '';
let vendedorFiltro = '';

const money = n => '$' + Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
function iniciales(n) { return String(n || '?').trim().split(/\s+/).slice(0, 2).map(p => p[0]).join('').toUpperCase(); }
function norm(s) { return String(s ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); }
// created_at del ERP es timestamptz («2026-10-27 18:51:27.02+00»): fechaDeMuestra()
// le pegaría otra «Z» y daría fecha inválida.
function fechaCot(valor) {
  if (!valor) return null;
  const iso = String(valor).replace(' ', 'T').replace(/([+-]\d{2})$/, '$1:00');
  const d = new Date(/[zZ]|[+-]\d{2}:\d{2}$/.test(iso) ? iso : iso + 'Z');
  return isNaN(d) ? null : d;
}
function fechaCotMx(valor) {
  const d = fechaCot(valor);
  return d ? d.toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'America/Mexico_City' }) : '';
}
function esteMes(valor) {
  const d = fechaCot(valor);
  const mes = x => x.toLocaleDateString('en-CA', { timeZone: 'America/Mexico_City' }).slice(0, 7);
  return !!d && mes(d) === mes(new Date());
}

function tarjeta(c, i) {
  return `
    <a class="mu-card mu-card--cot-${escHtml(c.estado)}" href="../vendedor/ver_cotizacion.php?id=${c.id}" style="animation-delay:${Math.min(i, 10) * 0.04}s">
      <div class="mu-card-top">
        <span class="mu-card-ico"><i class="bi ${ICONO_ESTADO[c.estado] || 'bi-file-earmark-text'}"></i></span>
        <div class="mu-card-id"><span class="mu-folio">${escHtml(c.folio || 'Sin folio')}</span></div>
        <span class="v26-pill v26-pill--${escHtml(c.estado)}">${escHtml(ETIQUETAS[c.estado] || c.estado)}</span>
      </div>
      <div class="mu-cot-total">${money(c.total)}</div>
      <div class="mu-card-talla">${c.total_pares} ${Number(c.total_pares) === 1 ? 'par' : 'pares'} · ${fechaCotMx(c.created_at)}</div>
      ${c.reemplazada_por ? `<div class="mu-card-talla"><i class="bi bi-arrow-return-right"></i> Reemplazada por ${escHtml(c.reemplazada_por)}</div>` : ''}
      <div class="mu-card-quien">
        <span class="mu-avatar">${escHtml(iniciales(c.vendedor_nombre))}</span>
        <div class="mu-card-quien-txt">
          <span class="mu-card-vend">${escHtml(c.vendedor_nombre)}</span>
          <span class="mu-card-cli"><i class="bi bi-building"></i> ${escHtml(c.cliente_nombre)}</span>
        </div>
      </div>
      <div class="mu-card-pie">
        <div class="mu-badges"></div>
        <span class="mu-abrir">Ver <i class="bi bi-arrow-right"></i></span>
      </div>
    </a>`;
}

// El select lista los vendedores que aparecen en sus cotizaciones.
function pintarVendedores() {
  const sel = document.getElementById('filtro-vendedor');
  const vistos = new Map();
  cotizaciones.forEach(c => vistos.set(String(c.vendedor_id), c.vendedor_nombre));
  if (vendedorFiltro && !vistos.has(vendedorFiltro)) vendedorFiltro = '';
  sel.innerHTML = '<option value="">Todos los vendedores</option>'
    + [...vistos].sort((a, b) => a[1].localeCompare(b[1], 'es'))
      .map(([id, nombre]) => `<option value="${escHtml(id)}" ${id === vendedorFiltro ? 'selected' : ''}>${escHtml(nombre)}</option>`).join('');
}

function pintar() {
  const base = cotizaciones.filter(c => !vendedorFiltro || String(c.vendedor_id) === vendedorFiltro);
  const cuenta = estados => base.filter(c => estados.includes(c.estado)).length;
  const conteos = {
    abiertas: cuenta(GRUPOS.abiertas), aceptadas: cuenta(GRUPOS.aceptadas), cerradas: cuenta(GRUPOS.cerradas), todas: base.length,
    pendiente: cuenta(['pendiente']), enviada: cuenta(['enviada']), en_negociacion: cuenta(['en_negociacion']),
    aceptadas_mes: base.filter(c => GRUPOS.aceptadas.includes(c.estado) && esteMes(c.created_at)).length,
  };
  Object.entries(conteos).forEach(([k, v]) => {
    const n = document.getElementById('n-' + k); if (n) n.textContent = v;
    const s = document.getElementById('s-' + k); if (s) s.textContent = v;
  });
  const t = document.getElementById('hero-titulo');
  t.innerHTML = conteos.pendiente === 0
    ? `Hola, ${escHtml(NOMBRE)}. No hay cotizaciones pendientes de enviar`
    : `Hola, ${escHtml(NOMBRE)}. Hay <span class="mu-hero-n">${conteos.pendiente}</span> cotizaci${conteos.pendiente === 1 ? 'ón pendiente' : 'ones pendientes'} de enviar`;

  const estados = GRUPOS[grupo] || (grupo === 'todas' ? null : [grupo]);
  const q = norm(busqueda);
  const lista = base.filter(c => (!estados || estados.includes(c.estado))
    && (!q || norm(`${c.folio} ${c.cliente_nombre}`).includes(q)));
  const cont = document.getElementById('lista');
  if (lista.length) {
    cont.innerHTML = lista.map(tarjeta).join('');
  } else {
    const [ico, tit, txt] = q ? ['bi-search', 'Sin resultados', 'Nada coincide con la búsqueda.'] : (VACIO[grupo] || ['bi-inbox', 'Sin cotizaciones', 'No hay cotizaciones en este estado.']);
    cont.innerHTML = `<div class="mu-vacio"><div class="mu-vacio-ico"><i class="bi ${ico}"></i></div><strong>${tit}</strong><p>${txt}</p></div>`;
  }
}

function activarGrupo(g) {
  grupo = g;
  document.querySelectorAll('#filtro-grupo .v26-funnel-chip').forEach(c => c.classList.toggle('active', c.dataset.grupo === g));
  document.querySelectorAll('.mu-stat').forEach(c => c.classList.toggle('activa', c.dataset.grupo === g));
  pintar();
}

async function cargar() {
  try {
    const r = await fetch('../api/cotizaciones.php', { cache: 'no-store' });
    const data = await r.json();
    if (!data.ok) throw new Error(data.error || 'Error');
    cotizaciones = data.cotizaciones;
    pintarVendedores();
    pintar();
  } catch (e) {
    document.getElementById('lista').innerHTML = '<div class="alert alert-danger">No se pudieron cargar las cotizaciones. Revisa tu conexión e intenta de nuevo.</div>';
  }
}

document.getElementById('filtro-grupo').addEventListener('click', (e) => {
  const chip = e.target.closest('.v26-funnel-chip');
  if (chip) activarGrupo(chip.dataset.grupo);
});
document.querySelectorAll('.mu-stat').forEach(b => b.addEventListener('click', () => activarGrupo(b.dataset.grupo)));
document.getElementById('buscar').addEventListener('input', (e) => { busqueda = e.target.value.trim(); pintar(); });
document.getElementById('filtro-vendedor').addEventListener('change', (e) => { vendedorFiltro = e.target.value; pintar(); });
setInterval(() => { if (!document.hidden) cargar(); }, 60000);
cargar();
</script>
</body>
</html>
