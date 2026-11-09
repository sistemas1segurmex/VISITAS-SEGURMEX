<?php
// Bandeja de solicitudes de muestra de los vendedores externos.
//  - Responsable de muestras (rol 'muestras'): su pantalla de inicio. Ve las
//    nuevas, las que está preparando y las cerradas, y desde el detalle
//    (ver.php) marca el avance. Le llega un aviso (campanita) y un correo
//    por cada solicitud nueva.
//  - Admin: misma bandeja, solo para consultar (sin botones de estado ni
//    avisos).
// Ver includes/muestras.php.
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$u = requireAnyRole(['muestras', 'admin']);
$esAdmin = $u['rol'] === 'admin';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Solicitudes de muestra</title>
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
        <?php if ($esAdmin): ?>
        <a href="../admin/index.php" class="v26-back" aria-label="Volver al panel"><i class="bi bi-arrow-left"></i></a>
        <?php endif; ?>
        <div class="v26-greeting">
          <div class="hi"><?= $esAdmin ? 'Control de Visitas · Consulta' : 'Control de Visitas' ?></div>
          <div class="name">Solicitudes de muestra</div>
        </div>
      </div>
      <div class="v26-topbar-right">
        <img src="../logo.png" alt="Segurmex" class="v26-logo">
        <a href="../logout.php" class="v26-icon-btn v26-tip v26-tip--bottom" data-tip="Cerrar sesión" aria-label="Salir"><i class="bi bi-box-arrow-right"></i></a>
      </div>
    </div>
  </div>

  <div class="v26-wrap mu-wrap mu-wrap--bandeja">
    <?php if (!$esAdmin): ?>
    <nav class="mu-tabs" aria-label="Secciones">
      <div class="mu-tabs-in">
        <a class="mu-tab activa" href="index.php" aria-current="page"><i class="bi bi-box-seam"></i> Muestras</a>
        <a class="mu-tab" href="cotizaciones.php"><i class="bi bi-file-earmark-text"></i> Cotizaciones</a>
        <a class="mu-tab" href="clientes.php"><i class="bi bi-people"></i> Clientes</a>
        <a class="mu-tab" href="coincidencias.php"><i class="bi bi-intersect"></i> Coincidencias</a>
      </div>
    </nav>
    <?php endif; ?>
    <section class="mu-hero">
      <div class="mu-hero-txt">
        <div class="mu-hero-eyebrow"><i class="bi bi-box-seam"></i> <?= $esAdmin ? 'Consulta de muestras' : 'Muestras de vendedores externos' ?></div>
        <h1 class="mu-hero-titulo" id="hero-titulo">Cargando...</h1>
        <p class="mu-hero-sub"><?= $esAdmin
          ? 'Vista de solo consulta: quien cambia el estado es la responsable de muestras.'
          : 'Abre una solicitud para ver sus datos y marcar el avance. Cada vez que la marcas, al vendedor le llega un aviso.' ?></p>
      </div>
      <div class="mu-hero-pasos" aria-hidden="true">
        <span><i class="bi bi-inbox"></i> Nueva</span><i class="bi bi-chevron-right"></i>
        <span><i class="bi bi-box-seam"></i> En preparación</span><i class="bi bi-chevron-right"></i>
        <span><i class="bi bi-truck"></i> Embarcada</span>
      </div>
    </section>

    <div class="mu-stats">
      <button type="button" class="mu-stat mu-stat--nuevas" data-grupo="nuevas">
        <span class="mu-stat-ico"><i class="bi bi-inbox-fill"></i></span>
        <span><span class="mu-stat-n" id="s-nuevas">0</span><span class="mu-stat-lbl">Nuevas</span></span>
      </button>
      <button type="button" class="mu-stat mu-stat--prep" data-grupo="en_preparacion">
        <span class="mu-stat-ico"><i class="bi bi-box-seam-fill"></i></span>
        <span><span class="mu-stat-n" id="s-en_preparacion">0</span><span class="mu-stat-lbl">En preparación</span></span>
      </button>
      <button type="button" class="mu-stat mu-stat--vencidas" data-grupo="vencidas">
        <span class="mu-stat-ico"><i class="bi bi-alarm-fill"></i></span>
        <span><span class="mu-stat-n" id="s-vencidas">0</span><span class="mu-stat-lbl">Promesa vencida</span></span>
      </button>
      <button type="button" class="mu-stat mu-stat--emb" data-grupo="cerradas">
        <span class="mu-stat-ico"><i class="bi bi-truck"></i></span>
        <span><span class="mu-stat-n" id="s-embarcadas_mes">0</span><span class="mu-stat-lbl">Embarcadas este mes</span></span>
      </button>
    </div>

    <div class="mu-toolbar">
      <div class="v26-funnel-filtro mb-0" id="filtro-grupo">
        <div class="v26-funnel-chip d-none" data-grupo="peticiones" id="chip-peticiones"><i class="bi bi-chat-left-text-fill"></i> Piden cambio <span class="n" id="n-peticiones">0</span></div>
        <div class="v26-funnel-chip active" data-grupo="nuevas">Nuevas <span class="n" id="n-nuevas">0</span></div>
        <div class="v26-funnel-chip" data-grupo="en_preparacion">En preparación <span class="n" id="n-en_preparacion">0</span></div>
        <div class="v26-funnel-chip" data-grupo="vencidas">Vencidas <span class="n" id="n-vencidas">0</span></div>
        <div class="v26-funnel-chip" data-grupo="cerradas">Cerradas <span class="n" id="n-cerradas">0</span></div>
        <div class="v26-funnel-chip" data-grupo="todas">Todas <span class="n" id="n-todas">0</span></div>
      </div>
      <div class="v26-search mb-0">
        <i class="bi bi-search"></i>
        <input type="text" id="buscar" class="v26-input" placeholder="Buscar folio, vendedor, cliente o estilo...">
      </div>
    </div>

    <div class="mu-filtros">
      <label class="mu-filtro">
        <span class="mu-filtro-lbl"><i class="bi bi-person"></i> Vendedor</span>
        <select id="f-vendedor" class="v26-select"><option value="">Todos</option></select>
      </label>
      <label class="mu-filtro">
        <span class="mu-filtro-lbl"><i class="bi bi-calendar3"></i> Periodo</span>
        <select id="f-periodo" class="v26-select">
          <option value="">Cualquier fecha</option>
          <option value="hoy">Hoy</option>
          <option value="semana">Esta semana</option>
          <option value="mes">Este mes</option>
          <option value="mes_pasado">Mes pasado</option>
          <option value="30">Últimos 30 días</option>
          <option value="rango">Rango…</option>
        </select>
      </label>
      <div class="mu-filtro mu-filtro--rango d-none" id="f-rango">
        <span class="mu-filtro-lbl">Del · al</span>
        <div class="mu-filtro-fechas">
          <input type="date" id="f-desde" class="v26-input" aria-label="Desde">
          <input type="date" id="f-hasta" class="v26-input" aria-label="Hasta">
        </div>
      </div>
      <div class="mu-filtro">
        <span class="mu-filtro-lbl">Contar por fecha de</span>
        <div class="mu-seg" id="f-campo" role="group" aria-label="Fecha que se usa para el periodo">
          <button type="button" data-campo="solicitud" class="activa">Solicitud</button>
          <button type="button" data-campo="promesa">Promesa</button>
          <button type="button" data-campo="embarque">Embarque</button>
        </div>
      </div>
    </div>
    <div class="mu-filtros-activos" id="filtros-activos"></div>

    <div id="lista" class="mu-grid">
      <div class="v26-skel"></div>
      <div class="v26-skel"></div>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if (!$esAdmin): ?>
<script src="../assets/js/avisos.js<?= assetVer(__DIR__ . '/../assets/js/avisos.js') ?>"></script>
<?php endif; ?>
<script src="../assets/js/muestras.js<?= assetVer(__DIR__ . '/../assets/js/muestras.js') ?>"></script>
<script>
let grupo = 'nuevas';
let busqueda = '';
// Filtros de vendedor y periodo (se guardan en la URL para poder compartir la vista).
const filtro = { vendedor: '', periodo: '', desde: '', hasta: '', campo: 'solicitud' };
const NOMBRE_PERIODO = { hoy: 'Hoy', semana: 'Esta semana', mes: 'Este mes', mes_pasado: 'Mes pasado', 30: 'Últimos 30 días', rango: 'Rango' };
const NOMBRE_CAMPO = { solicitud: 'solicitud', promesa: 'promesa', embarque: 'embarque' };
const ES_ADMIN = <?= $esAdmin ? 'true' : 'false' ?>;
const NOMBRE = <?= json_encode(strtok($u['nombre'], ' ')) ?>;
const VACIO = {
  peticiones: ['bi-chat-left-text', 'Sin peticiones', 'Ningún vendedor está pidiendo corregir o cancelar una muestra.'],
  nuevas: ['bi-inbox', 'Todo al día', 'No hay solicitudes nuevas. Cuando un vendedor pida una muestra te llegará un aviso.'],
  en_preparacion: ['bi-box-seam', 'Nada en preparación', 'Las muestras que marques "En preparación" aparecen aquí.'],
  vencidas: ['bi-emoji-smile', 'Sin promesas vencidas', 'Ninguna muestra abierta ha pasado su fecha promesa.'],
  cerradas: ['bi-archive', 'Sin cerradas todavía', 'Aquí quedan las muestras embarcadas y las canceladas.'],
  todas: ['bi-inbox', 'Sin solicitudes', 'Todavía no hay solicitudes de muestra.'],
};
const ICONO_ESTADO = { enviada: 'bi-inbox-fill', en_preparacion: 'bi-box-seam-fill', embarcada: 'bi-truck', cancelada: 'bi-x-circle-fill' };

function iniciales(n) { return String(n || '?').trim().split(/\s+/).slice(0, 2).map(p => p[0]).join('').toUpperCase(); }
function diasDesde(valor) { const d = fechaDeMuestra(valor); return d ? Math.floor((Date.now() - d.getTime()) / 86400000) : 0; }
function diasParaPromesa(m) {
  if (!m.fecha_promesa) return null;
  const hoy = new Date(new Date().toLocaleDateString('en-CA', { timeZone: 'America/Mexico_City' }) + 'T12:00:00');
  return Math.round((fechaDeMuestra(m.fecha_promesa) - hoy) / 86400000);
}

function badgeEspera(m) {
  if (m.estado !== 'enviada' && m.estado !== 'en_preparacion') {
    return `<span class="mu-badge"><i class="bi bi-check2-circle"></i> ${m.estado === 'embarcada' ? 'Embarcada' : 'Cancelada'} ${fechaMx(m.actualizada_en)}</span>`;
  }
  const d = diasDesde(m.created_at);
  const clase = d >= 3 ? 'mu-badge--rojo' : (d >= 1 ? 'mu-badge--ambar' : 'mu-badge--verde');
  return `<span class="mu-badge ${clase}"><i class="bi bi-hourglass-split"></i> ${d === 0 ? 'Pedida ' + haceMuestra(m.created_at) : 'Esperando ' + (d === 1 ? '1 día' : d + ' días')}</span>`;
}
function badgePromesa(m) {
  const d = diasParaPromesa(m);
  if (d === null) return '';
  const abierta = m.estado === 'enviada' || m.estado === 'en_preparacion';
  if (abierta && d < 0) return `<span class="mu-badge mu-badge--rojo"><i class="bi bi-alarm"></i> Promesa vencida (${fechaMx(m.fecha_promesa)})</span>`;
  if (abierta && d <= 2) return `<span class="mu-badge mu-badge--ambar"><i class="bi bi-alarm"></i> Promesa ${d === 0 ? 'hoy' : (d === 1 ? 'mañana' : 'en ' + d + ' días')}</span>`;
  return `<span class="mu-badge"><i class="bi bi-calendar-event"></i> Promesa ${fechaMx(m.fecha_promesa)}</span>`;
}

// De dónde sale la que está en preparación: En PT / Por programar (+ fecha estimada).
function badgePreparacion(m) {
  if (m.estado !== 'en_preparacion' || !m.preparacion) return '';
  if (m.preparacion === 'pt') return '<span class="mu-badge mu-badge--verde"><i class="bi bi-box-seam"></i> En Producto Terminado</span>';
  return `<span class="mu-badge mu-badge--ambar"><i class="bi bi-gear"></i> Por programar${m.fecha_estimada_pt ? ' · est. ' + fechaMx(m.fecha_estimada_pt) : ''}</span>`;
}

function tarjeta(m, i) {
  return `
    <a class="mu-card mu-card--${m.estado}" href="ver.php?id=${m.id}" style="animation-delay:${Math.min(i, 10) * 0.04}s">
      <div class="mu-card-top">
        <span class="mu-card-ico"><i class="bi ${ICONO_ESTADO[m.estado] || 'bi-box'}"></i></span>
        <div class="mu-card-id">
          <span class="mu-folio">${escHtml(m.folio)}</span>
          ${m.tipo === 'variante' ? '<span class="mu-tag"><i class="bi bi-shuffle"></i> Con cambios</span>' : ''}
        </div>
        ${pillEstadoMuestra(m.estado)}
      </div>
      ${(m.estilos && m.estilos.length ? m.estilos : [{ estilo_nombre: m.estilo_nombre, color: m.color, tallas: [{ talla: m.talla, pares: m.cantidad || 1 }] }]).map(e => `
        <div class="mu-card-titulo">${escHtml(e.estilo_nombre)}</div>
        <div class="mu-card-talla">${[e.color ? `Color <strong>${escHtml(e.color)}</strong>` : '',
          e.tallas.map(t => (t.talla ? `Talla <strong>${escHtml(t.talla)}</strong>` : 'Sin talla') + ` × ${t.pares}`).join(', ')].filter(Boolean).join(' · ')}</div>`).join('')}
      <div class="mu-card-talla"><strong>Total: ${m.cantidad || 1} ${(m.cantidad || 1) === 1 ? 'par' : 'pares'}</strong></div>
      <div class="mu-card-quien">
        <span class="mu-avatar">${escHtml(iniciales(m.vendedor_nombre))}</span>
        <div class="mu-card-quien-txt">
          <span class="mu-card-vend" data-vendedor="${m.vendedor_id}" title="Ver solo las de este vendedor">${escHtml(m.vendedor_nombre)} <i class="bi bi-funnel"></i></span>
          <span class="mu-card-cli"><i class="bi bi-building"></i> ${escHtml(m.cliente_nombre)}</span>
        </div>
      </div>
      ${m.peticion_tipo && (m.estado === 'enviada' || m.estado === 'en_preparacion') ? `<div class="mu-card-peticion"><i class="bi bi-chat-left-text-fill"></i> ${m.peticion_tipo === 'cancelacion' ? 'Pide cancelarla' : 'Pide corrección'}: «${escHtml(m.peticion_texto || '')}»</div>` : ''}
      ${m.estado === 'embarcada' ? `<div class="mu-card-envio"><i class="bi bi-truck"></i> ${escHtml(textoEnvio(m))}</div>` : ''}
      ${m.estado === 'cancelada' ? `<div class="mu-card-envio mu-card-envio--cancel"><i class="bi bi-x-circle"></i> ${escHtml(m.motivo_cancelacion || '')}</div>` : ''}
      <div class="mu-card-pie">
        <div class="mu-badges"><span class="mu-badge"><i class="bi bi-inbox"></i> Solicitada ${fechaMx(m.created_at)}</span>${badgePreparacion(m)}${badgeEspera(m)}${badgePromesa(m)}</div>
        <span class="mu-abrir">${ES_ADMIN || m.estado === 'embarcada' || m.estado === 'cancelada' ? 'Ver' : (m.peticion_tipo ? 'Decidir' : 'Atender')} <i class="bi bi-arrow-right"></i></span>
      </div>
    </a>`;
}

function pintarHero(c) {
  const t = document.getElementById('hero-titulo');
  if (ES_ADMIN) { t.textContent = `${c.todas} solicitud${c.todas === 1 ? '' : 'es'} en total`; return; }
  t.innerHTML = (c.nuevas === 0
    ? `Hola, ${escHtml(NOMBRE)}. No tienes solicitudes nuevas`
    : `Hola, ${escHtml(NOMBRE)}. Tienes <span class="mu-hero-n">${c.nuevas}</span> solicitud${c.nuevas === 1 ? '' : 'es'} nueva${c.nuevas === 1 ? '' : 's'}`)
    + (c.peticiones ? `<br><small>y ${c.peticiones === 1 ? '1 vendedor te pide' : c.peticiones + ' vendedores te piden'} un cambio</small>` : '');
}

function activarGrupo(g) {
  grupo = g;
  document.querySelectorAll('#filtro-grupo .v26-funnel-chip').forEach(c => c.classList.toggle('active', c.dataset.grupo === g));
  document.querySelectorAll('.mu-stat').forEach(c => c.classList.toggle('activa', c.dataset.grupo === g));
  cargar();
}

// --- Filtros de vendedor y periodo ---
// Fechas como 'AAAA-MM-DD' en hora de CDMX.
function hoyMx() { return new Date().toLocaleDateString('en-CA', { timeZone: 'America/Mexico_City' }); }
function ymd(d) { return d.toISOString().slice(0, 10); }
function rangoPeriodo(p) {
  const h = hoyMx();
  const d = new Date(h + 'T00:00:00Z');
  if (p === 'hoy') return [h, h];
  if (p === 'semana') { const lun = new Date(d); lun.setUTCDate(d.getUTCDate() - ((d.getUTCDay() + 6) % 7)); return [ymd(lun), h]; }
  if (p === 'mes') return [h.slice(0, 8) + '01', h];
  if (p === 'mes_pasado') {
    const ini = new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth() - 1, 1));
    const fin = new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), 0));
    return [ymd(ini), ymd(fin)];
  }
  if (p === '30') { const ini = new Date(d); ini.setUTCDate(d.getUTCDate() - 29); return [ymd(ini), h]; }
  if (p === 'rango') return [filtro.desde, filtro.hasta];
  return ['', ''];
}
function hayFiltros() { return !!(filtro.vendedor || filtro.periodo); }

function leerFiltrosDeUrl() {
  const u = new URLSearchParams(location.search);
  filtro.vendedor = u.get('vendedor') || '';
  filtro.periodo = NOMBRE_PERIODO[u.get('periodo')] ? u.get('periodo') : '';
  filtro.desde = u.get('desde') || '';
  filtro.hasta = u.get('hasta') || '';
  filtro.campo = NOMBRE_CAMPO[u.get('campo')] ? u.get('campo') : 'solicitud';
  if (u.get('grupo')) grupo = u.get('grupo');
}
function guardarFiltrosEnUrl() {
  const u = new URLSearchParams();
  if (grupo !== 'nuevas') u.set('grupo', grupo);
  if (filtro.vendedor) u.set('vendedor', filtro.vendedor);
  if (filtro.periodo) {
    u.set('periodo', filtro.periodo);
    if (filtro.periodo === 'rango') { if (filtro.desde) u.set('desde', filtro.desde); if (filtro.hasta) u.set('hasta', filtro.hasta); }
  }
  if (filtro.campo !== 'solicitud') u.set('campo', filtro.campo);
  const qs = u.toString();
  history.replaceState(null, '', location.pathname + (qs ? '?' + qs : ''));
}

function pintarControlesFiltro() {
  document.getElementById('f-vendedor').value = filtro.vendedor;
  document.getElementById('f-periodo').value = filtro.periodo;
  document.getElementById('f-desde').value = filtro.desde;
  document.getElementById('f-hasta').value = filtro.hasta;
  document.getElementById('f-rango').classList.toggle('d-none', filtro.periodo !== 'rango');
  document.querySelectorAll('#f-campo button').forEach(b => b.classList.toggle('activa', b.dataset.campo === filtro.campo));
}

function pintarVendedores(vendedores) {
  const sel = document.getElementById('f-vendedor');
  const html = '<option value="">Todos</option>' + vendedores.map(v => `<option value="${v.id}">${escHtml(v.nombre)} (${v.n})</option>`).join('');
  if (sel.dataset.html !== html) { sel.innerHTML = html; sel.dataset.html = html; }
  sel.value = filtro.vendedor;
}

function pintarFiltrosActivos(solicitudes, vendedores) {
  const cont = document.getElementById('filtros-activos');
  let html = '';
  if (filtro.vendedor) {
    const v = vendedores.find(x => String(x.id) === String(filtro.vendedor));
    html += `<span class="mu-filtro-tag"><i class="bi bi-person"></i> ${escHtml(v ? v.nombre : 'Vendedor')}
      <button type="button" data-quitar="vendedor" aria-label="Quitar filtro de vendedor"><i class="bi bi-x"></i></button></span>`;
  }
  if (filtro.periodo) {
    let txt = NOMBRE_PERIODO[filtro.periodo];
    if (filtro.periodo === 'rango') txt = [filtro.desde && 'del ' + fechaMx(filtro.desde), filtro.hasta && 'al ' + fechaMx(filtro.hasta)].filter(Boolean).join(' ') || 'Rango sin fechas';
    html += `<span class="mu-filtro-tag"><i class="bi bi-calendar3"></i> ${escHtml(txt)} · por ${NOMBRE_CAMPO[filtro.campo]}
      <button type="button" data-quitar="periodo" aria-label="Quitar filtro de periodo"><i class="bi bi-x"></i></button></span>`;
  }
  if (hayFiltros()) html += '<button type="button" class="mu-filtro-limpiar" data-quitar="todo">Limpiar filtros</button>';
  const pares = solicitudes.reduce((s, m) => s + (m.cantidad || 1), 0);
  html += `<span class="mu-filtro-total">${solicitudes.length} solicitud${solicitudes.length === 1 ? '' : 'es'} · ${pares} par${pares === 1 ? '' : 'es'}</span>`;
  cont.innerHTML = html;
}

async function cargar() {
  const lista = document.getElementById('lista');
  guardarFiltrosEnUrl();
  try {
    const [desde, hasta] = rangoPeriodo(filtro.periodo);
    const qs = new URLSearchParams({ grupo, q: busqueda });
    if (filtro.vendedor) qs.set('vendedor', filtro.vendedor);
    if (filtro.periodo) { if (desde) qs.set('desde', desde); if (hasta) qs.set('hasta', hasta); qs.set('campo', filtro.campo); }
    const r = await fetch(`../api/muestras_bandeja.php?${qs}`, { cache: 'no-store' });
    const data = await r.json();
    if (!data.ok) throw new Error(data.error || 'Error');
    const c = data.conteos;
    const cf = data.conteos_filtro || c;
    // Chips: con los filtros aplicados. Tarjetas de arriba y saludo: siempre el total.
    Object.entries(cf).forEach(([k, v]) => { const n = document.getElementById('n-' + k); if (n) n.textContent = v; });
    Object.entries(c).forEach(([k, v]) => { const s = document.getElementById('s-' + k); if (s) s.textContent = v; });
    document.querySelector('.mu-stat--vencidas').classList.toggle('alerta', c.vencidas > 0);
    // «Piden cambio»: solo se ve si hay peticiones (o si está elegida).
    document.getElementById('chip-peticiones').classList.toggle('d-none', !cf.peticiones && grupo !== 'peticiones');
    pintarHero(c);
    pintarVendedores(data.vendedores || []);
    pintarFiltrosActivos(data.solicitudes, data.vendedores || []);
    if (data.solicitudes.length) {
      lista.innerHTML = data.solicitudes.map(tarjeta).join('');
    } else {
      const [ico, tit, txt] = busqueda ? ['bi-search', 'Sin resultados', 'Nada coincide con la búsqueda.']
        : (hayFiltros() ? ['bi-funnel', 'Sin resultados', 'Nada coincide con los filtros. Prueba otro vendedor o periodo.'] : VACIO[grupo]);
      lista.innerHTML = `<div class="mu-vacio"><div class="mu-vacio-ico"><i class="bi ${ico}"></i></div><strong>${tit}</strong><p>${txt}</p></div>`;
    }
  } catch (e) {
    lista.innerHTML = `<div class="alert alert-danger">${escHtml(e.message && e.message !== 'Error' ? e.message : 'No se pudieron cargar las solicitudes. Revisa tu conexión e intenta de nuevo.')}</div>`;
  }
}

document.getElementById('filtro-grupo').addEventListener('click', (e) => {
  const chip = e.target.closest('.v26-funnel-chip');
  if (chip) activarGrupo(chip.dataset.grupo);
});
document.querySelectorAll('.mu-stat').forEach(b => b.addEventListener('click', () => activarGrupo(b.dataset.grupo)));
let tBuscar;
document.getElementById('buscar').addEventListener('input', (e) => {
  clearTimeout(tBuscar);
  tBuscar = setTimeout(() => { busqueda = e.target.value.trim(); cargar(); }, 300);
});

// Se refresca sola: cada minuto y cuando llega un aviso nuevo.
let ultimosNoLeidos = null;
document.addEventListener('v26:avisos', (e) => {
  const n = e.detail.no_leidos || 0;
  if (ultimosNoLeidos !== null && n > ultimosNoLeidos) cargar();
  ultimosNoLeidos = n;
});
setInterval(() => { if (!document.hidden) cargar(); }, 60000);

// Eventos de los filtros.
document.getElementById('f-vendedor').addEventListener('change', (e) => { filtro.vendedor = e.target.value; cargar(); });
document.getElementById('f-periodo').addEventListener('change', (e) => {
  filtro.periodo = e.target.value;
  if (filtro.periodo === 'rango' && !filtro.desde && !filtro.hasta) {
    const [d, h] = rangoPeriodo('mes'); filtro.desde = d; filtro.hasta = h;
  }
  pintarControlesFiltro(); cargar();
});
['f-desde', 'f-hasta'].forEach(id => document.getElementById(id).addEventListener('change', () => {
  filtro.desde = document.getElementById('f-desde').value;
  filtro.hasta = document.getElementById('f-hasta').value;
  cargar();
}));
document.getElementById('f-campo').addEventListener('click', (e) => {
  const b = e.target.closest('button'); if (!b) return;
  filtro.campo = b.dataset.campo; pintarControlesFiltro(); cargar();
});
document.getElementById('filtros-activos').addEventListener('click', (e) => {
  const b = e.target.closest('[data-quitar]'); if (!b) return;
  const q = b.dataset.quitar;
  if (q === 'vendedor' || q === 'todo') filtro.vendedor = '';
  if (q === 'periodo' || q === 'todo') { filtro.periodo = ''; filtro.desde = ''; filtro.hasta = ''; }
  pintarControlesFiltro(); cargar();
});
// Clic en el nombre del vendedor de una tarjeta: filtra por él (sin abrir la tarjeta).
document.getElementById('lista').addEventListener('click', (e) => {
  const v = e.target.closest('[data-vendedor]'); if (!v) return;
  e.preventDefault(); e.stopPropagation();
  filtro.vendedor = v.dataset.vendedor; pintarControlesFiltro(); cargar();
  window.scrollTo({ top: document.querySelector('.mu-filtros').offsetTop - 80, behavior: 'smooth' });
});

leerFiltrosDeUrl();
pintarControlesFiltro();
activarGrupo(VACIO[grupo] ? grupo : 'nuevas');
</script>
</body>
</html>
