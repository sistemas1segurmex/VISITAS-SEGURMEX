<?php
// Clientes y prospectos de cada vendedor, para la responsable de muestras
// (rol 'muestras'). Solo consulta -- los datos vienen de api/muestras_clientes.php.
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
<title>Clientes por vendedor</title>
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
          <div class="name">Clientes por vendedor</div>
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
        <a class="mu-tab" href="cotizaciones.php"><i class="bi bi-file-earmark-text"></i> Cotizaciones</a>
        <a class="mu-tab activa" href="clientes.php" aria-current="page"><i class="bi bi-people"></i> Clientes</a>
      </div>
    </nav>

    <section class="mu-hero">
      <div class="mu-hero-txt">
        <div class="mu-hero-eyebrow"><i class="bi bi-people"></i> Cartera de vendedores</div>
        <h1 class="mu-hero-titulo" id="hero-titulo">Cargando...</h1>
        <p class="mu-hero-sub">Los clientes y prospectos que tiene a su nombre cada vendedor. Solo consulta: el alta y los cambios los hace el vendedor.</p>
      </div>
    </section>

    <div class="mu-stats">
      <div class="mu-stat mu-stat--cvend">
        <span class="mu-stat-ico"><i class="bi bi-person-badge"></i></span>
        <span><span class="mu-stat-n" id="s-vendedores">0</span><span class="mu-stat-lbl">Vendedores</span></span>
      </div>
      <button type="button" class="mu-stat mu-stat--ccli" data-grupo="clientes">
        <span class="mu-stat-ico"><i class="bi bi-trophy"></i></span>
        <span><span class="mu-stat-n" id="s-clientes">0</span><span class="mu-stat-lbl">Clientes</span></span>
      </button>
      <button type="button" class="mu-stat mu-stat--cpros" data-grupo="prospectos">
        <span class="mu-stat-ico"><i class="bi bi-person-plus"></i></span>
        <span><span class="mu-stat-n" id="s-prospectos">0</span><span class="mu-stat-lbl">Prospectos</span></span>
      </button>
      <button type="button" class="mu-stat mu-stat--cperd" data-grupo="perdidos">
        <span class="mu-stat-ico"><i class="bi bi-x-circle"></i></span>
        <span><span class="mu-stat-n" id="s-perdidos">0</span><span class="mu-stat-lbl">Perdidos</span></span>
      </button>
    </div>

    <div class="mu-toolbar">
      <div class="v26-funnel-filtro mb-0" id="filtro-grupo">
        <div class="v26-funnel-chip active" data-grupo="todos">Todos <span class="n" id="n-todos">0</span></div>
        <div class="v26-funnel-chip" data-grupo="clientes">Clientes <span class="n" id="n-clientes">0</span></div>
        <div class="v26-funnel-chip" data-grupo="prospectos">Prospectos <span class="n" id="n-prospectos">0</span></div>
        <div class="v26-funnel-chip" data-grupo="perdidos">Perdidos <span class="n" id="n-perdidos">0</span></div>
      </div>
      <div class="mu-cot-buscar">
        <select id="filtro-vendedor" class="v26-select" aria-label="Filtrar por vendedor">
          <option value="">Todos los vendedores</option>
        </select>
        <div class="v26-search mb-0">
          <i class="bi bi-search"></i>
          <input type="text" id="buscar" class="v26-input" placeholder="Buscar cliente, contacto o ciudad...">
        </div>
      </div>
    </div>

    <div id="lista" class="mu-vends">
      <div class="v26-skel"></div>
      <div class="v26-skel"></div>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/avisos.js<?= assetVer(__DIR__ . '/../assets/js/avisos.js') ?>"></script>
<script src="../assets/js/muestras.js<?= assetVer(__DIR__ . '/../assets/js/muestras.js') ?>"></script>
<script>
const NOMBRE = <?= json_encode(strtok($u['nombre'], ' ')) ?>;
// Mismas etiquetas que ETAPAS_CLIENTE en vendedor.js / etiquetaEtapa() en helpers.php.
const ETAPAS = {
  prospecto_agregado: 'Prospecto', contacto_establecido: 'Contacto establecido', reunion_presentacion: 'Reunión de presentación',
  propuesta_enviada: 'Propuesta enviada', convertido: 'Convertido', perdido: 'Perdido',
};
// "Cliente" es el convertido; todo lo que sigue en el embudo cuenta como prospecto.
function grupoDe(c) {
  const e = c.etapa || 'prospecto_agregado';
  return e === 'convertido' ? 'clientes' : e === 'perdido' ? 'perdidos' : 'prospectos';
}
let vendedores = [];
let clientes = [];
let grupo = 'todos';
let busqueda = '';
let vendedorFiltro = '';
const cerrados = new Set(); // vendedores que Dulce colapsó, se respeta al recargar

function iniciales(n) { return String(n || '?').trim().split(/\s+/).slice(0, 2).map(p => p[0]).join('').toUpperCase(); }
function norm(s) { return String(s ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); }
function fechaMx(valor) {
  if (!valor) return '';
  const d = new Date(String(valor).replace(' ', 'T') + (/[zZ]|[+-]\d{2}:?\d{2}$/.test(valor) ? '' : 'Z'));
  return isNaN(d) ? '' : d.toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'America/Mexico_City' });
}

function fila(c) {
  const etapa = c.etapa || 'prospecto_agregado';
  const lugar = [c.municipio, c.estado].filter(Boolean).join(', ');
  const visitas = Number(c.total_visitas) || 0;
  const datos = [
    c.nombre_contacto ? `<span><i class="bi bi-person"></i> ${escHtml(c.nombre_contacto)}</span>` : '',
    c.telefono ? `<a href="tel:${escHtml(c.telefono)}"><i class="bi bi-telephone"></i> ${escHtml(c.telefono)}</a>` : '',
    lugar ? `<span><i class="bi bi-geo-alt"></i> ${escHtml(lugar)}</span>` : '',
    `<span><i class="bi bi-calendar-check"></i> ${visitas} ${visitas === 1 ? 'visita' : 'visitas'}${c.ultima_visita ? ' · última ' + fechaMx(c.ultima_visita) : ''}</span>`,
  ].filter(Boolean).join('');
  return `
    <div class="mu-cli">
      <div class="mu-cli-top">
        <span class="mu-cli-nom">${escHtml(c.nombre)}</span>
        <span class="v26-pill v26-pill--etapa-${escHtml(etapa)}">${escHtml(ETAPAS[etapa] || etapa)}</span>
      </div>
      <div class="mu-cli-datos">${datos}</div>
    </div>`;
}

// Los totales del encabezado son de toda la cartera del vendedor, no del filtro.
function bloqueVendedor(v, lista, i) {
  const suyos = clientes.filter(c => String(c.vendedor_id) === String(v.id));
  const cuenta = g => suyos.filter(c => grupoDe(c) === g).length;
  const abierto = !cerrados.has(String(v.id));
  return `
    <details class="mu-vend" data-id="${v.id}" ${abierto ? 'open' : ''} style="animation-delay:${Math.min(i, 10) * 0.04}s">
      <summary class="mu-vend-cab">
        <span class="mu-avatar">${escHtml(iniciales(v.nombre))}</span>
        <span class="mu-vend-txt">
          <span class="mu-vend-nom">${escHtml(v.nombre)}${Number(v.activo) ? '' : ' <span class="mu-tag">Inactivo</span>'}</span>
          <span class="mu-vend-mail">${escHtml(v.email || '')}</span>
        </span>
        <span class="mu-badges">
          <span class="mu-badge mu-badge--verde"><i class="bi bi-trophy"></i> ${cuenta('clientes')} clientes</span>
          <span class="mu-badge mu-badge--ambar"><i class="bi bi-person-plus"></i> ${cuenta('prospectos')} prospectos</span>
          ${cuenta('perdidos') ? `<span class="mu-badge mu-badge--rojo"><i class="bi bi-x-circle"></i> ${cuenta('perdidos')}</span>` : ''}
        </span>
        <i class="bi bi-chevron-down mu-vend-flecha"></i>
      </summary>
      <div class="mu-vend-cuerpo">
        ${lista.length ? lista.map(fila).join('') : '<p class="mu-vend-vacio">Sin clientes en este filtro.</p>'}
      </div>
    </details>`;
}

function pintarVendedores() {
  const sel = document.getElementById('filtro-vendedor');
  if (vendedorFiltro && !vendedores.some(v => String(v.id) === vendedorFiltro)) vendedorFiltro = '';
  sel.innerHTML = '<option value="">Todos los vendedores</option>'
    + vendedores.map(v => `<option value="${v.id}" ${String(v.id) === vendedorFiltro ? 'selected' : ''}>${escHtml(v.nombre)}</option>`).join('');
}

function pintar() {
  const base = clientes.filter(c => !vendedorFiltro || String(c.vendedor_id) === vendedorFiltro);
  const conteos = { todos: base.length, clientes: 0, prospectos: 0, perdidos: 0 };
  base.forEach(c => conteos[grupoDe(c)]++);
  Object.entries(conteos).forEach(([k, v]) => {
    const n = document.getElementById('n-' + k); if (n) n.textContent = v;
    const s = document.getElementById('s-' + k); if (s) s.textContent = v;
  });
  const vendVisibles = vendedores.filter(v => !vendedorFiltro || String(v.id) === vendedorFiltro);
  document.getElementById('s-vendedores').textContent = vendVisibles.length;
  document.getElementById('hero-titulo').innerHTML =
    `Hola, ${escHtml(NOMBRE)}. Hay <span class="mu-hero-n">${conteos.clientes}</span> clientes y <span class="mu-hero-n">${conteos.prospectos}</span> prospectos`;

  const q = norm(busqueda);
  const filtrados = base.filter(c => (grupo === 'todos' || grupoDe(c) === grupo)
    && (!q || norm(`${c.nombre} ${c.nombre_contacto} ${c.telefono} ${c.municipio} ${c.estado}`).includes(q)));
  const porVendedor = new Map();
  filtrados.forEach(c => {
    const k = String(c.vendedor_id);
    if (!porVendedor.has(k)) porVendedor.set(k, []);
    porVendedor.get(k).push(c);
  });
  // Con búsqueda o filtro de etapa se ocultan los vendedores sin coincidencias.
  const mostrar = vendVisibles.filter(v => (!q && grupo === 'todos') || porVendedor.has(String(v.id)));
  const cont = document.getElementById('lista');
  if (mostrar.length) {
    cont.innerHTML = mostrar.map((v, i) => bloqueVendedor(v, porVendedor.get(String(v.id)) || [], i)).join('');
  } else {
    const [ico, tit, txt] = q ? ['bi-search', 'Sin resultados', 'Nada coincide con la búsqueda.'] : ['bi-people', 'Sin clientes', 'No hay clientes en este filtro.'];
    cont.innerHTML = `<div class="mu-vacio"><div class="mu-vacio-ico"><i class="bi ${ico}"></i></div><strong>${tit}</strong><p>${txt}</p></div>`;
  }
}

function activarGrupo(g) {
  grupo = g;
  document.querySelectorAll('#filtro-grupo .v26-funnel-chip').forEach(c => c.classList.toggle('active', c.dataset.grupo === g));
  document.querySelectorAll('.mu-stat[data-grupo]').forEach(c => c.classList.toggle('activa', c.dataset.grupo === g));
  pintar();
}

async function cargar() {
  try {
    const r = await fetch('../api/muestras_clientes.php', { cache: 'no-store' });
    const data = await r.json();
    if (!data.ok) throw new Error(data.error || 'Error');
    vendedores = data.vendedores;
    clientes = data.clientes;
    pintarVendedores();
    pintar();
  } catch (e) {
    document.getElementById('lista').innerHTML = '<div class="alert alert-danger">No se pudieron cargar los clientes. Revisa tu conexión e intenta de nuevo.</div>';
  }
}

document.getElementById('filtro-grupo').addEventListener('click', (e) => {
  const chip = e.target.closest('.v26-funnel-chip');
  if (chip) activarGrupo(chip.dataset.grupo);
});
document.querySelectorAll('.mu-stat[data-grupo]').forEach(b => b.addEventListener('click', () => activarGrupo(grupo === b.dataset.grupo ? 'todos' : b.dataset.grupo)));
document.getElementById('buscar').addEventListener('input', (e) => { busqueda = e.target.value.trim(); pintar(); });
document.getElementById('filtro-vendedor').addEventListener('change', (e) => { vendedorFiltro = e.target.value; pintar(); });
document.getElementById('lista').addEventListener('toggle', (e) => {
  const d = e.target.closest('.mu-vend');
  if (d) d.open ? cerrados.delete(d.dataset.id) : cerrados.add(d.dataset.id);
}, true);
setInterval(() => { if (!document.hidden) cargar(); }, 120000);
cargar();
</script>
</body>
</html>
