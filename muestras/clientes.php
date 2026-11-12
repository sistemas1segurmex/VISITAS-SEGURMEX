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
        <a class="mu-tab" href="coincidencias.php"><i class="bi bi-intersect"></i> Coincidencias</a>
      </div>
    </nav>

    <section class="mu-hero">
      <div class="mu-hero-txt">
        <div class="mu-hero-eyebrow"><i class="bi bi-people"></i> Cartera de vendedores</div>
        <h1 class="mu-hero-titulo" id="hero-titulo">Cargando...</h1>
        <p class="mu-hero-sub">Los clientes y prospectos que tiene a su nombre cada vendedor. Solo consulta: el alta y los cambios los hace el vendedor.</p>
      </div>
      <a class="mu-btn-excel mu-btn-excel--hero" id="btn-exportar-todo" href="../api/exportar_cartera.php?todos=1"
         title="Descarga en Excel la cartera completa de todos los vendedores (una hoja por vendedor)"><i class="bi bi-file-earmark-excel"></i> Exportar todo</a>
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
        <div class="v26-funnel-chip mu-chip-sinact" id="chip-sinact" role="button" tabindex="0" aria-pressed="false"
             title="Sin visita hecha, cotización ni muestra en los últimos 30 días"><i class="bi bi-hourglass-split"></i> Sin actividad 30 días <span class="n" id="n-sinact">0</span></div>
      </div>
      <div class="mu-cot-buscar">
        <select id="orden" class="v26-select mu-orden" aria-label="Ordenar">
          <option value="nombre">Ordenar: Nombre (A-Z)</option>
          <option value="recientes">Ordenar: Registrados más recientes</option>
          <option value="antiguos">Ordenar: Registrados más antiguos</option>
          <option value="actividad">Ordenar: Actividad más reciente</option>
          <option value="sinact">Ordenar: Más tiempo sin actividad</option>
        </select>
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

  <!-- Ficha del cliente: vive fuera de #lista para que la recarga automática no la cierre. -->
  <div class="mf-velo" id="ficha-velo" hidden></div>
  <aside class="mf-ficha" id="ficha" role="dialog" aria-modal="true" aria-labelledby="ficha-nombre" hidden>
    <div class="mf-asa" aria-hidden="true"></div>
    <div class="mf-cab">
      <div class="mf-cab-txt">
        <small id="ficha-vendedor"></small>
        <h2 id="ficha-nombre"></h2>
        <span id="ficha-etapa"></span>
      </div>
      <button type="button" class="mf-cerrar" id="ficha-cerrar" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
    </div>
    <div id="ficha-cuerpo"></div>
  </aside>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/avisos.js<?= assetVer(__DIR__ . '/../assets/js/avisos.js') ?>"></script>
<script src="../assets/js/muestras.js<?= assetVer(__DIR__ . '/../assets/js/muestras.js') ?>"></script>
<script>
const NOMBRE = <?= json_encode(strtok($u['nombre'], ' ')) ?>;
// Mismas etiquetas que ETAPAS_CLIENTE en vendedor.js / etiquetaEtapa() en helpers.php,
// salvo «Convertido», que en esta pantalla se lee «Cliente» (igual que las pestañas).
const ETAPAS = {
  prospecto_agregado: 'Prospecto', contacto_establecido: 'Contacto establecido', reunion_presentacion: 'Reunión de presentación',
  propuesta_enviada: 'Propuesta enviada', convertido: 'Cliente', perdido: 'Perdido',
};
const DIAS_SIN_ACTIVIDAD = 30;
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
let soloSinActividad = false;
let orden = 'nombre';
let fichaId = null;
const cerrados = new Set(); // vendedores que Dulce colapsó, se respeta al recargar

function iniciales(n) { return String(n || '?').trim().split(/\s+/).slice(0, 2).map(p => p[0]).join('').toUpperCase(); }
function norm(s) { return String(s ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); }
// Acepta lo que manda Postgres: sin zona (se guarda en UTC), con zona corta
// ("+00") o completa ("-06:00"), y con microsegundos.
function aFecha(valor) {
  if (!valor) return null;
  if (valor instanceof Date) return isNaN(valor) ? null : valor;
  let t = String(valor).trim().replace(' ', 'T').replace(/(\.\d{3})\d+/, '$1');
  if (/[+-]\d{2}$/.test(t)) t += ':00';
  else if (/[+-]\d{4}$/.test(t)) t = t.slice(0, -2) + ':' + t.slice(-2);
  else if (!/[zZ]|[+-]\d{2}:\d{2}$/.test(t)) t += 'Z';
  const d = new Date(t);
  return isNaN(d) ? null : d;
}
function fechaMx(valor) {
  const d = aFecha(valor);
  return d ? d.toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'America/Mexico_City' }) : '';
}
function diasDesde(d) { return Math.floor((Date.now() - d.getTime()) / 86400000); }
function haceTexto(d) {
  const n = diasDesde(d);
  return n <= 0 ? 'hoy' : n === 1 ? 'ayer' : `hace ${n} días`;
}

// Lo más reciente entre visita hecha, cotización y muestra.
function ultimaActividad(c) {
  let ult = null;
  [['ultima_visita', 'Visita'], ['ultima_cotizacion', 'Cotización'], ['ultima_muestra', 'Muestra']].forEach(([k, tipo]) => {
    const d = aFecha(c[k]);
    if (d && (!ult || d > ult.fecha)) ult = { fecha: d, tipo };
  });
  return ult;
}
// Sin visita hecha, cotización ni muestra en 30 días (contando desde que se
// registró si nunca ha tenido actividad). Los perdidos no cuentan.
function sinActividad(c) {
  if (grupoDe(c) === 'perdidos') return false;
  const ult = ultimaActividad(c);
  const desde = ult ? ult.fecha : aFecha(c.created_at);
  return !!desde && diasDesde(desde) >= DIAS_SIN_ACTIVIDAD;
}
// Para ordenar por actividad: sin actividad nunca = fecha de registro.
function fechaActividad(c) {
  const ult = ultimaActividad(c);
  return (ult ? ult.fecha : aFecha(c.created_at))?.getTime() || 0;
}

function etiquetasFecha(c) {
  const g = grupoDe(c);
  const out = [];
  if (g === 'perdidos') {
    const f = fechaMx(c.etapa_actualizada_en);
    out.push(`<span class="mu-ftag mu-ftag--perdido"><i class="bi bi-x-circle"></i> Perdido${f ? ' el ' + f : ''}${c.etapa_perdido_motivo ? ' · «' + escHtml(c.etapa_perdido_motivo) + '»' : ''}</span>`);
    return out.join('');
  }
  if (g === 'clientes') {
    if (c.cliente_desde) {
      out.push(`<span class="mu-ftag"><i class="bi bi-trophy"></i> Cliente desde ${fechaMx(c.cliente_desde)}${c.cliente_desde_fuente === 'alta' ? ' <small>(alta en el sistema)</small>' : ''}</span>`);
    } else {
      out.push('<span class="mu-ftag"><i class="bi bi-trophy"></i> Cliente desde: pendiente de registro</span>');
    }
  } else {
    out.push(`<span class="mu-ftag"><i class="bi bi-calendar-plus"></i> Prospecto desde ${fechaMx(c.created_at)}</span>`);
  }
  const ult = ultimaActividad(c);
  if (sinActividad(c)) {
    out.push(ult
      ? `<span class="mu-ftag mu-ftag--sin"><i class="bi bi-hourglass-split"></i> Sin actividad ${haceTexto(ult.fecha)}</span>`
      : '<span class="mu-ftag mu-ftag--sin"><i class="bi bi-hourglass-split"></i> Sin actividad desde que se registró</span>');
  } else if (ult) {
    out.push(`<span class="mu-ftag mu-ftag--act"><i class="bi bi-activity"></i> ${ult.tipo} · ${haceTexto(ult.fecha)}</span>`);
  } else {
    out.push('<span class="mu-ftag"><i class="bi bi-activity"></i> Sin actividad aún</span>');
  }
  return out.join('');
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
  const sel = String(c.id) === String(fichaId);
  return `
    <div class="mu-cli mu-cli--abre${sel ? ' sel' : ''}" data-cliente="${c.id}" role="button" tabindex="0" aria-label="Ver ficha de ${escHtml(c.nombre)}">
      <span class="mu-cli-viendo"><i class="bi bi-eye-fill"></i> Viendo ficha</span>
      <div class="mu-cli-top">
        <span class="mu-cli-nom">${escHtml(c.nombre)}</span>
        <span class="v26-pill v26-pill--etapa-${escHtml(etapa)}">${escHtml(ETAPAS[etapa] || etapa)}</span>
      </div>
      <div class="mu-cli-datos">${datos}</div>
      <div class="mu-cli-fechas">${etiquetasFecha(c)}</div>
    </div>`;
}

const ORDENES = {
  nombre:    (a, b) => String(a.nombre).localeCompare(String(b.nombre), 'es', { sensitivity: 'base' }),
  recientes: (a, b) => (aFecha(b.created_at)?.getTime() || 0) - (aFecha(a.created_at)?.getTime() || 0),
  antiguos:  (a, b) => (aFecha(a.created_at)?.getTime() || 0) - (aFecha(b.created_at)?.getTime() || 0),
  actividad: (a, b) => fechaActividad(b) - fechaActividad(a),
  sinact:    (a, b) => fechaActividad(a) - fechaActividad(b),
};

// Los totales del encabezado son de toda la cartera del vendedor, no del filtro.
function bloqueVendedor(v, lista, i) {
  const suyos = clientes.filter(c => String(c.vendedor_id) === String(v.id));
  const cuenta = g => suyos.filter(c => grupoDe(c) === g).length;
  const abierto = !cerrados.has(String(v.id));
  const conFicha = fichaId != null && lista.some(c => String(c.id) === String(fichaId));
  return `
    <details class="mu-vend${conFicha ? ' mu-vend--ficha' : ''}" data-id="${v.id}" ${abierto ? 'open' : ''} style="animation-delay:${Math.min(i, 10) * 0.04}s">
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
        <a class="mu-btn-excel mu-btn-excel--o" href="../api/exportar_cartera.php?vendedor_id=${encodeURIComponent(v.id)}"
           data-exportar="${v.id}" title="Descarga en Excel la cartera completa de este vendedor"><i class="bi bi-download"></i> Exportar</a>
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
  // El chip cuenta los sin actividad de la pestaña elegida (Todos / Clientes / Prospectos).
  document.getElementById('n-sinact').textContent =
    base.filter(c => (grupo === 'todos' || grupoDe(c) === grupo) && sinActividad(c)).length;
  const vendVisibles = vendedores.filter(v => !vendedorFiltro || String(v.id) === vendedorFiltro);
  document.getElementById('s-vendedores').textContent = vendVisibles.length;
  document.getElementById('hero-titulo').innerHTML =
    `Hola, ${escHtml(NOMBRE)}. Hay <span class="mu-hero-n">${conteos.clientes}</span> clientes y <span class="mu-hero-n">${conteos.prospectos}</span> prospectos`;

  const q = norm(busqueda);
  const filtrados = base.filter(c => (grupo === 'todos' || grupoDe(c) === grupo)
    && (!soloSinActividad || sinActividad(c))
    && (!q || norm(`${c.nombre} ${c.nombre_contacto} ${c.telefono} ${c.municipio} ${c.estado}`).includes(q)))
    .sort(ORDENES[orden] || ORDENES.nombre);
  const porVendedor = new Map();
  filtrados.forEach(c => {
    const k = String(c.vendedor_id);
    if (!porVendedor.has(k)) porVendedor.set(k, []);
    porVendedor.get(k).push(c);
  });
  // Con búsqueda o filtro se ocultan los vendedores sin coincidencias.
  const mostrar = vendVisibles.filter(v => (!q && grupo === 'todos' && !soloSinActividad) || porVendedor.has(String(v.id)));
  const cont = document.getElementById('lista');
  if (mostrar.length) {
    cont.innerHTML = mostrar.map((v, i) => bloqueVendedor(v, porVendedor.get(String(v.id)) || [], i)).join('');
  } else {
    const [ico, tit, txt] = q ? ['bi-search', 'Sin resultados', 'Nada coincide con la búsqueda.']
      : soloSinActividad ? ['bi-check2-circle', 'Todos con actividad', `Nadie lleva ${DIAS_SIN_ACTIVIDAD} días o más sin actividad en este filtro.`]
      : ['bi-people', 'Sin clientes', 'No hay clientes en este filtro.'];
    cont.innerHTML = `<div class="mu-vacio"><div class="mu-vacio-ico"><i class="bi ${ico}"></i></div><strong>${tit}</strong><p>${txt}</p></div>`;
  }
}

function activarGrupo(g) {
  grupo = g;
  document.querySelectorAll('#filtro-grupo .v26-funnel-chip[data-grupo]').forEach(c => c.classList.toggle('active', c.dataset.grupo === g));
  document.querySelectorAll('.mu-stat[data-grupo]').forEach(c => c.classList.toggle('activa', c.dataset.grupo === g));
  pintar();
}
function alternarSinActividad() {
  soloSinActividad = !soloSinActividad;
  const chip = document.getElementById('chip-sinact');
  chip.classList.toggle('active', soloSinActividad);
  chip.setAttribute('aria-pressed', String(soloSinActividad));
  pintar();
}

// ── Exportar a Excel (12-nov-2026) ───────────────────────────────────────
// Siempre la cartera completa del vendedor (o de todos), aunque en pantalla
// haya un filtro. Se avisa antes porque queda en la bitácora.
function avisoExportar(ev, texto) {
  if (!confirm(texto + '\n\nEl archivo lleva todo lo que tiene asignado hoy, aunque en pantalla haya un filtro. Queda registrado en la bitácora.')) ev.preventDefault();
}
document.getElementById('btn-exportar-todo').addEventListener('click', ev => {
  avisoExportar(ev, `Se descargará en Excel la cartera de todos los vendedores (${vendedores.length} vendedores, ${clientes.length} registros), una hoja por vendedor.`);
});
document.getElementById('lista').addEventListener('click', ev => {
  const a = ev.target.closest('a[data-exportar]');
  if (!a) return;
  ev.stopPropagation();
  const v = vendedores.find(x => String(x.id) === a.dataset.exportar);
  const n = clientes.filter(c => String(c.vendedor_id) === a.dataset.exportar).length;
  avisoExportar(ev, `Se descargará en Excel la cartera completa de ${v ? v.nombre : 'este vendedor'} (${n} ${n === 1 ? 'registro' : 'registros'}).`);
});

// ── Ficha ────────────────────────────────────────────────────────────────
const fichaEl = document.getElementById('ficha');
const veloEl = document.getElementById('ficha-velo');
let fichaPeticion = 0;

function kpi(n, lbl, cls = '') { return `<div class="mf-kpi ${cls}"><b>${Number(n) || 0}</b><span>${lbl}</span></div>`; }
function filaFecha(lbl, valor, nota = '') {
  return `<div class="mf-fila"><span>${lbl}</span><b>${valor}${nota ? ` <small>${nota}</small>` : ''}</b></div>`;
}

function pintarFicha(d) {
  const c = d.cliente, v = d.visitas, co = d.cotizaciones, m = d.muestras;
  const etapa = c.etapa || 'prospecto_agregado';
  document.getElementById('ficha-vendedor').textContent = c.vendedor_nombre || '';
  document.getElementById('ficha-nombre').textContent = c.nombre;
  document.getElementById('ficha-etapa').innerHTML = `<span class="v26-pill v26-pill--etapa-${escHtml(etapa)}">${escHtml(ETAPAS[etapa] || etapa)}</span>`;

  let clienteDesde;
  if (etapa !== 'convertido') clienteDesde = filaFecha('Cliente desde', '—', 'aún no es cliente');
  else if (c.cliente_desde) clienteDesde = filaFecha('Cliente desde', fechaMx(c.cliente_desde), c.cliente_desde_fuente === 'alta' ? 'alta en el sistema' : '')
    + (c.cliente_desde_fuente === 'aprobado' ? '<div class="mf-nota"><i class="bi bi-info-circle"></i> Fecha en que Cuentas por Cobrar aprobó su registro.</div>'
      : '<div class="mf-nota"><i class="bi bi-info-circle"></i> Se cargó directo como cliente; es la fecha en que se dio de alta en el sistema.</div>');
  else clienteDesde = filaFecha('Cliente desde', 'Pendiente de registro');

  const ult = ultimaActividad({ ultima_visita: v.ultima, ultima_cotizacion: co.ultima, ultima_muestra: m.ultima });
  const perdido = etapa === 'perdido'
    ? filaFecha('Perdido', fechaMx(c.etapa_actualizada_en) || '—', c.etapa_perdido_motivo ? '· ' + escHtml(c.etapa_perdido_motivo) : '') : '';

  const tope = Number(m.tope_mes) || 0, pares = Number(m.pares_mes) || 0;
  const pct = tope ? Math.min(100, Math.round(pares / tope * 100)) : 0;

  const cambios = d.recorrido.filter(r => r.tipo === 'cambio').length;
  const pasos = d.recorrido.map(r => {
    const txt = r.tipo === 'alta'
      ? (r.etapa === 'Convertido' ? 'Registrado como cliente' : 'Registrado como prospecto')
      : (r.etapa === 'Convertido' ? 'Cliente' : escHtml(r.etapa || '—'));
    return `<li class="${r.etapa === 'Perdido' ? 'mal' : 'ok'}">${txt}<small>${fechaMx(r.fecha)}${r.motivo ? ' · «' + escHtml(r.motivo) + '»' : ''}</small></li>`;
  }).join('');

  document.getElementById('ficha-cuerpo').innerHTML = `
    <section class="mf-sec">
      <h3><i class="bi bi-calendar3"></i> Fechas</h3>
      ${filaFecha(c.etapa_inicial === 'convertido' ? 'Registrado' : 'Prospecto desde', fechaMx(c.created_at) || '—')}
      ${clienteDesde}
      ${perdido}
      ${filaFecha('Última actividad', ult ? fechaMx(ult.fecha) : 'Sin actividad', ult ? '· ' + ult.tipo.toLowerCase() : '')}
    </section>
    <section class="mf-sec">
      <h3><i class="bi bi-calendar-check"></i> Visitas</h3>
      <div class="mf-kpis">${kpi(v.hechas, 'Hechas', 'ok')}${kpi(v.proximas, 'Próximas')}${kpi(v.no_realizadas, 'No realizadas', 'warn')}${kpi(v.canceladas, 'Canceladas', 'bad')}</div>
      <div class="mf-mini"><span>Última: <b>${fechaMx(v.ultima) || '—'}</b></span><span>Próxima: <b>${fechaMx(v.proxima) || '—'}</b></span></div>
    </section>
    <section class="mf-sec">
      <h3><i class="bi bi-file-earmark-text"></i> Cotizaciones</h3>
      <div class="mf-kpis">${kpi(co.total, 'Total')}${kpi(co.abiertas, 'Abiertas', 'warn')}${kpi(co.aceptadas, 'Aceptadas', 'ok')}${kpi(co.canceladas, 'Canceladas', 'bad')}</div>
      <div class="mf-mini"><span>Primera: <b>${fechaMx(co.primera) || '—'}</b></span><span>Última: <b>${fechaMx(co.ultima) || '—'}</b></span></div>
    </section>
    <section class="mf-sec">
      <h3><i class="bi bi-box-seam"></i> Muestras</h3>
      <div class="mf-kpis">${kpi(m.total, 'Total')}${kpi(m.en_proceso, 'En proceso', 'warn')}${kpi(m.embarcadas, 'Embarcadas', 'ok')}${kpi(m.canceladas, 'Canceladas', 'bad')}</div>
      <div class="mf-mini"><span>Pares este mes: <b>${pares} de ${tope}</b></span><span>Última: <b>${fechaMx(m.ultima) || '—'}</b></span></div>
      <div class="mf-barra"><i style="width:${pct}%"></i></div>
    </section>
    <section class="mf-sec">
      <details class="mf-reco">
        <summary><span><i class="bi bi-signpost-split"></i> Recorrido · ${cambios ? `${cambios} ${cambios === 1 ? 'cambio' : 'cambios'} de etapa` : 'sin cambios de etapa'}</span><i class="bi bi-chevron-down"></i></summary>
        <ul class="mf-recorrido">${pasos}</ul>
        ${cambios ? '' : '<p class="mf-nota">Sin cambios de etapa registrados.</p>'}
        <p class="mf-nota">Los cambios de etapa los marca el vendedor; se guardan desde el 14 de septiembre de 2026.</p>
      </details>
    </section>`;
}

async function abrirFicha(id) {
  const estabaCerrada = !fichaEl.classList.contains('open');
  fichaId = id;
  document.querySelectorAll('.mu-cli.sel').forEach(e => e.classList.remove('sel'));
  document.querySelectorAll('.mu-vend--ficha').forEach(e => e.classList.remove('mu-vend--ficha'));
  const tarjeta = document.querySelector(`.mu-cli[data-cliente="${CSS.escape(String(id))}"]`);
  tarjeta?.classList.add('sel');
  tarjeta?.closest('.mu-vend')?.classList.add('mu-vend--ficha');
  const c = clientes.find(x => String(x.id) === String(id));
  document.getElementById('ficha-vendedor').textContent = '';
  document.getElementById('ficha-nombre').textContent = c ? c.nombre : '';
  document.getElementById('ficha-etapa').innerHTML = '';
  document.getElementById('ficha-cuerpo').innerHTML = '<div class="v26-skel"></div><div class="v26-skel"></div>';
  fichaEl.hidden = false; veloEl.hidden = false;
  document.body.classList.add('mf-abierta');
  requestAnimationFrame(() => { fichaEl.classList.add('open'); veloEl.classList.add('open'); });
  // Botón «atrás» del celular / APK cierra la ficha en lugar de salir de la página.
  if (estabaCerrada) history.pushState({ ficha: true }, '');
  const yo = ++fichaPeticion;
  try {
    const r = await fetch('../api/muestras_cliente_ficha.php?id=' + encodeURIComponent(id), { cache: 'no-store' });
    const data = await r.json();
    if (yo !== fichaPeticion) return;
    if (!data.ok) throw new Error(data.error || 'Error');
    pintarFicha(data);
  } catch (e) {
    if (yo !== fichaPeticion) return;
    document.getElementById('ficha-cuerpo').innerHTML = '<div class="alert alert-danger mt-3">No se pudo cargar la ficha. Revisa tu conexión e intenta de nuevo.</div>';
  }
}
function cerrarFicha(desdeHistorial = false) {
  if (!fichaEl.classList.contains('open')) return;
  fichaId = null; fichaPeticion++;
  fichaEl.classList.remove('open'); veloEl.classList.remove('open');
  document.body.classList.remove('mf-abierta');
  document.querySelectorAll('.mu-cli.sel').forEach(e => e.classList.remove('sel'));
  document.querySelectorAll('.mu-vend--ficha').forEach(e => e.classList.remove('mu-vend--ficha'));
  setTimeout(() => { if (!fichaId) { fichaEl.hidden = true; veloEl.hidden = true; } }, 250);
  if (!desdeHistorial && history.state && history.state.ficha) history.back();
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
  const chip = e.target.closest('.v26-funnel-chip[data-grupo]');
  if (chip) activarGrupo(chip.dataset.grupo);
});
const chipSinAct = document.getElementById('chip-sinact');
chipSinAct.addEventListener('click', alternarSinActividad);
chipSinAct.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); alternarSinActividad(); } });
document.querySelectorAll('.mu-stat[data-grupo]').forEach(b => b.addEventListener('click', () => activarGrupo(grupo === b.dataset.grupo ? 'todos' : b.dataset.grupo)));
document.getElementById('buscar').addEventListener('input', (e) => { busqueda = e.target.value.trim(); pintar(); });
document.getElementById('filtro-vendedor').addEventListener('change', (e) => { vendedorFiltro = e.target.value; pintar(); });
document.getElementById('orden').addEventListener('change', (e) => { orden = e.target.value; pintar(); });
const lista = document.getElementById('lista');
lista.addEventListener('toggle', (e) => {
  const d = e.target.closest('.mu-vend');
  if (d) d.open ? cerrados.delete(d.dataset.id) : cerrados.add(d.dataset.id);
}, true);
lista.addEventListener('click', (e) => {
  if (e.target.closest('a')) return; // el teléfono sigue marcando
  const card = e.target.closest('.mu-cli[data-cliente]');
  if (card) abrirFicha(card.dataset.cliente);
});
lista.addEventListener('keydown', (e) => {
  const card = e.target.closest('.mu-cli[data-cliente]');
  if (card && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); abrirFicha(card.dataset.cliente); }
});
document.getElementById('ficha-cerrar').addEventListener('click', () => cerrarFicha());
veloEl.addEventListener('click', () => cerrarFicha());
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') cerrarFicha(); });
window.addEventListener('popstate', () => cerrarFicha(true));
// La recarga solo repinta la lista; la ficha queda abierta (vive fuera de #lista).
setInterval(() => { if (!document.hidden) cargar(); }, 120000);
cargar();
</script>
</body>
</html>
