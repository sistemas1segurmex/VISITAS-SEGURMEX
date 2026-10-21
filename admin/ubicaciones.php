<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$u = requireRole('admin');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ubicaciones corregidas — Control de Visitas</title>
<link rel="icon" type="image/png" href="../assets/img/Favv-Segu.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css<?= assetVer(__DIR__ . '/../assets/css/style.css') ?>">
<link rel="stylesheet" href="../assets/css/admin-2026.css<?= assetVer(__DIR__ . '/../assets/css/admin-2026.css') ?>">
<style>
  /* Diseño "distancia destacada" (oct-2026): tarjeta por corrección, km grandes
     y comparación lado a lado de las dos ubicaciones. */
  .corr-card { background: #fff; border: 1px solid var(--v26-border); border-radius: var(--v26-r-lg); padding: 14px 16px; margin-bottom: 12px; box-shadow: var(--v26-shadow-sm); }
  .corr-top { display: flex; gap: 12px; align-items: flex-start; }
  .corr-foto { width: 72px; height: 72px; border-radius: 12px; object-fit: cover; flex: none; background: #eee; display: block; }
  .corr-datos { flex: 1; min-width: 0; }
  .corr-nombre { font-weight: 800; font-size: 1rem; color: #1F2328; }
  .corr-datos .linea { font-size: .78rem; color: var(--v26-ink-soft); margin-top: 3px; }
  .corr-km { flex: none; text-align: center; border-radius: 12px; padding: 8px 12px; background: #FFF1F2; color: #BE123C; min-width: 92px; }
  .corr-km b { display: block; font-size: 1.35rem; font-weight: 800; line-height: 1.1; font-variant-numeric: tabular-nums; }
  .corr-km span { font-size: .68rem; font-weight: 600; color: #9F1239; }
  .corr-km.ok { background: #F3F4F6; color: #374151; } .corr-km.ok span { color: var(--v26-ink-soft); }
  .corr-aviso { margin-top: 10px; font-size: .78rem; color: #9F1239; background: #FFF1F2; border-radius: 10px; padding: 7px 10px; }
  .corr-pines { display: grid; grid-template-columns: minmax(0,1fr) auto minmax(0,1fr); gap: 10px; align-items: stretch; margin-top: 12px; }
  .corr-pin { border-radius: 12px; padding: 9px 12px; text-decoration: none; display: flex; flex-direction: column; gap: 2px; transition: filter .15s; }
  .corr-pin:hover { filter: brightness(.97); }
  .corr-pin .t { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
  .corr-pin .d { font-size: .78rem; color: #1F2328; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .corr-pin .v { font-size: .74rem; font-weight: 700; color: var(--v26-brand-text); }
  .corr-pin--antes { background: #F7F4EE; } .corr-pin--antes .t { color: #6B5A3A; }
  .corr-pin--ahora { background: var(--v26-brand-soft); } .corr-pin--ahora .t { color: var(--v26-brand-ink); }
  .corr-flecha { align-self: center; color: var(--v26-brand-1); font-size: 1.3rem; }
  .corr-pie { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
  .corr-pie .revisado { font-size: .74rem; color: var(--v26-ink-soft); margin-right: auto; }
  .corr-btn { border: 0; border-radius: 10px; padding: 8px 16px; font-size: .84rem; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; transition: background .15s, filter .15s; }
  .corr-btn:disabled { opacity: .6; cursor: wait; }
  .corr-btn--aprobar { background: var(--v26-brand-1); color: #fff; box-shadow: var(--v26-shadow-brand); }
  .corr-btn--aprobar:hover { background: var(--v26-brand-2); }
  .corr-btn--revertir { background: #FFF1F2; color: #BE123C; }
  .corr-btn--revertir:hover { background: #FFE4E6; }
  /* Filtros vendedor / fechas */
  .corr-filtros .card-body { padding: 12px 14px; }
  .corr-filtros-fila { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; }
  .corr-f { display: flex; flex-direction: column; gap: 4px; margin: 0; }
  .corr-f > span { font-size: .7rem; font-weight: 700; color: var(--v26-brand-ink); text-transform: uppercase; letter-spacing: .03em; padding-left: 4px; }
  .corr-f .form-select { min-width: 210px; }
  .corr-f .form-control { width: 175px; }
  .corr-btn--limpiar { background: #fff; color: var(--v26-brand-ink); border: 1px solid #F0D592; border-radius: var(--v26-r-pill); }
  .corr-btn--limpiar:hover { background: var(--v26-brand-soft-2); }
  /* Filtro Por revisar / Todas */
  #filtro-estado { background: var(--v26-brand-soft); border-color: transparent; }
  #filtro-estado .opt { color: var(--v26-brand-ink); }
  #filtro-estado .opt.active { background: var(--v26-brand-1); color: #fff; }
  @media (max-width: 576px) {
    .corr-top { flex-wrap: wrap; }
    .corr-foto { width: 56px; height: 56px; }
    .corr-km { order: 3; width: 100%; display: flex; align-items: baseline; justify-content: center; gap: 6px; padding: 6px 10px; }
    .corr-pines { gap: 6px; }
    .corr-pin { padding: 8px 10px; }
    .corr-pin .t { font-size: .64rem; }
    .corr-flecha { font-size: 1rem; }
    .corr-pie .revisado { width: 100%; }

    .corr-btn { flex: 1; justify-content: center; white-space: nowrap; padding: 8px 10px; }
    .corr-f { flex: 1 1 0; min-width: 0; } .corr-f:first-child { flex: 1 1 100%; }
    .corr-f .form-select, .corr-f .form-control { min-width: 0; width: 100%; }
    .corr-filtros-fila .corr-btn--limpiar { flex: 1 1 100%; }
    /* En celular la fecha no cabe con la flecha: se quita la flecha */
    .v26-wrap .card.v26-card-ambar .corr-f .form-control { padding-left: 1.9rem; padding-right: .5rem; background-size: 14px 14px, 0 0; background-position: left .6rem center, right 0 center; font-size: .8rem; }
  }
</style>
</head>
<body class="v26">
<div class="v26-header">
  <div class="v26-topbar">
    <div class="v26-topbar-left">
      <a href="index.php" class="v26-back" aria-label="Volver"><i class="bi bi-arrow-left"></i></a>
      <div class="v26-greeting">
        <div class="hi">Control de Visitas</div>
        <div class="name">Ubicaciones corregidas</div>
      </div>
    </div>
    <div class="v26-topbar-right">
      <?= v26UsuarioHeader($u) ?>
    </div>
  </div>
</div>

<div class="v26-wrap">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
      <h5 class="mb-0">Pines de clientes corregidos en visita</h5>
      <p class="v26-subtitulo mb-0">Cuando el pin estaba lejos y el vendedor confirmó que estaba en el lugar</p>
    </div>
    <div class="v26-segmented" id="filtro-estado">
      <button type="button" class="opt active" data-estado="por_revisar"><i class="bi bi-exclamation-circle"></i> Por revisar</button>
      <button type="button" class="opt" data-estado="todas"><i class="bi bi-list-ul"></i> Todas</button>
    </div>
  </div>
  <!-- Filtros (solo en "Todas"): vendedor y rango de fechas -->
  <div class="card v26-card-ambar corr-filtros mb-3 d-none" id="filtros">
    <div class="card-body">
      <div class="corr-filtros-fila">
        <label class="corr-f"><span>Vendedor</span>
          <select id="f-vendedor" class="form-select form-select-sm"><option value="0">Todos los vendedores</option></select>
        </label>
        <label class="corr-f"><span>Desde</span><input type="date" id="f-desde" class="form-control form-control-sm"></label>
        <label class="corr-f"><span>Hasta</span><input type="date" id="f-hasta" class="form-control form-control-sm"></label>
        <button type="button" class="corr-btn corr-btn--limpiar d-none" id="f-limpiar"><i class="bi bi-x-lg"></i> Limpiar</button>
      </div>
    </div>
  </div>
  <div id="msg" class="mb-2"></div>
  <div id="lista"><p class="text-muted small">Cargando...</p></div>
</div>

<script src="../assets/js/fecha_utils.js<?= assetVer(__DIR__ . '/../assets/js/fecha_utils.js') ?>"></script>
<script>
const ESTADOS = {
  aplicada:    ['v26-pill--verificado', 'Corregida'],
  por_revisar: ['v26-pill--pendiente', 'Por revisar'],
  aprobada:    ['v26-pill--verificado', 'Aprobada'],
  revertida:   ['v26-pill--noverificado', 'Revertida'],
};
let estadoFiltro = 'por_revisar';

const esc = (t) => String(t ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const mapa = (lat, lng) => `https://www.google.com/maps?q=${lat},${lng}`;
const metros = (m) => m >= 1000 ? (m / 1000).toFixed(1) + ' km' : Math.round(m) + ' m';

async function cargar() {
  const qs = new URLSearchParams({ estado: estadoFiltro });
  if (estadoFiltro === 'todas') {
    const v = document.getElementById('f-vendedor').value;
    const d = document.getElementById('f-desde').value;
    const h = document.getElementById('f-hasta').value;
    if (v !== '0') qs.set('vendedor', v);
    if (d) qs.set('desde', d);
    if (h) qs.set('hasta', h);
    document.getElementById('f-limpiar').classList.toggle('d-none', v === '0' && !d && !h);
  }
  const res = await fetch('../api/admin_ubicaciones.php?' + qs);
  const data = await res.json();
  const lista = document.getElementById('lista');
  if (!data.ok) { lista.innerHTML = `<div class="alert alert-danger py-2">${esc(data.error)}</div>`; return; }
  document.querySelector('[data-estado="por_revisar"]').innerHTML = `<i class="bi bi-exclamation-circle"></i> Por revisar (${data.por_revisar})`;
  llenarVendedores(data.vendedores || []);
  const conFiltro = estadoFiltro === 'todas' && !document.getElementById('f-limpiar').classList.contains('d-none');
  if (!data.correcciones.length) {
    lista.innerHTML = `<p class="text-muted small">${estadoFiltro === 'por_revisar' ? 'No hay correcciones pendientes de revisar.' : (conFiltro ? 'No hay correcciones con estos filtros.' : 'Todavía no hay correcciones.')}</p>`;
    return;
  }
  lista.innerHTML = data.correcciones.map(c => {
    const [clase, texto] = ESTADOS[c.estado] || ['', c.estado];
    const revisable = c.estado === 'aplicada' || c.estado === 'por_revisar';
    // Aviso del vendedor: el pin no se movió en el check-in; se mueve al aprobar.
    const esAviso = String(c.nota || '').startsWith('Aviso del vendedor:');
    const nota = c.nota ? c.nota.replace(/^El pin anterior/, 'La ubicación registrada').replace(/el pin anterior/g, 'la ubicación registrada') : '';
    return `
      <div class="corr-card">
        <div class="corr-top">
          <a href="../api/foto.php?checkin_id=${c.checkin_id}" target="_blank" rel="noopener" title="Ver foto de entrada"><img class="corr-foto" src="../api/foto.php?checkin_id=${c.checkin_id}" alt="Foto de entrada" loading="lazy"></a>
          <div class="corr-datos">
            <span class="corr-nombre">${esc(c.cliente_nombre)}</span> <span class="v26-pill ${clase}" style="margin-left:6px;">${texto}</span>
            <div class="linea"><i class="bi bi-person"></i> ${esc(c.vendedor_nombre)} · ${formatearFechaUTC(c.creado_en)}</div>
            <div class="linea"><i class="bi bi-broadcast"></i> GPS del vendedor ±${Math.round(c.accuracy)} m</div>
          </div>
          <div class="corr-km ${revisable ? '' : 'ok'}"><b>${metros(c.distancia_metros)}</b><span>de diferencia</span></div>
        </div>
        ${nota ? `<div class="corr-aviso"><i class="bi bi-exclamation-triangle"></i> ${esc(nota)}</div>` : ''}
        <div class="corr-pines">
          <a class="corr-pin corr-pin--antes" href="${mapa(c.lat_anterior, c.lng_anterior)}" target="_blank" rel="noopener">
            <span class="t"><i class="bi bi-geo"></i> Ubicación registrada</span>
            <span class="d" title="${esc(c.direccion)}">${esc(c.direccion)}</span>
            <span class="v">Ver en mapa <i class="bi bi-box-arrow-up-right"></i></span>
          </a>
          <i class="bi bi-arrow-right corr-flecha" aria-hidden="true"></i>
          <a class="corr-pin corr-pin--ahora" href="${mapa(c.lat_nueva, c.lng_nueva)}" target="_blank" rel="noopener">
            <span class="t"><i class="bi bi-geo-alt-fill"></i> Donde estaba el vendedor</span>
            <span class="d">Al hacer check-in</span>
            <span class="v">Ver en mapa <i class="bi bi-box-arrow-up-right"></i></span>
          </a>
        </div>
        <div class="corr-pie">
          ${c.revisado_por_nombre ? `<span class="revisado"><i class="bi bi-person-check"></i> Revisó: ${esc(c.revisado_por_nombre)} · ${formatearFechaUTC(c.revisado_en)}</span>` : '<span class="revisado"></span>'}
          ${revisable ? `
            <button type="button" class="corr-btn corr-btn--revertir" data-accion="revertir" data-id="${c.id}"><i class="bi bi-arrow-counterclockwise"></i> Revertir</button>
            <button type="button" class="corr-btn corr-btn--aprobar" data-accion="aprobar" data-id="${c.id}"><i class="bi bi-check-lg"></i> ${esAviso ? 'Aprobar y mover pin' : 'Aprobar'}</button>` : ''}
        </div>
      </div>`;
  }).join('');
}

document.getElementById('lista').addEventListener('click', async (e) => {
  const btn = e.target.closest('[data-accion]');
  if (!btn) return;
  const accion = btn.dataset.accion;
  if (accion === 'revertir' && !confirm('¿Revertir? El cliente regresa a la ubicación registrada y esa visita queda "Fuera de zona".')) return;
  btn.disabled = true;
  const fd = new FormData();
  fd.append('action', accion);
  fd.append('id', btn.dataset.id);
  const msg = document.getElementById('msg');
  try {
    const data = await (await fetch('../api/admin_ubicaciones.php', { method: 'POST', body: fd })).json();
    if (!data.ok) throw new Error(data.error);
    msg.innerHTML = accion === 'revertir' && data.pin_restaurado === false
      ? '<div class="alert alert-warning py-2">Revertida. El pin del cliente no se tocó porque ya se había movido después.</div>'
      : '';
    cargar();
  } catch (err) {
    msg.innerHTML = `<div class="alert alert-danger py-2">${esc(err.message || 'Error de conexión')}</div>`;
    btn.disabled = false;
  }
});

// Llena el select de vendedores una sola vez (conserva lo elegido).
function llenarVendedores(lista) {
  const sel = document.getElementById('f-vendedor');
  if (sel.dataset.listo) return;
  sel.dataset.listo = '1';
  lista.forEach(v => sel.insertAdjacentHTML('beforeend', `<option value="${v.id}">${esc(v.nombre)}</option>`));
}

['f-vendedor', 'f-desde', 'f-hasta'].forEach(id => document.getElementById(id).addEventListener('change', () => {
  const d = document.getElementById('f-desde'), h = document.getElementById('f-hasta');
  // Si eligen "desde" después de "hasta", se acomodan solas
  if (d.value && h.value && d.value > h.value) [d.value, h.value] = [h.value, d.value];
  cargar();
}));
// Abrir el calendario al tocar cualquier parte del campo de fecha
['f-desde', 'f-hasta'].forEach(id => document.getElementById(id).addEventListener('click', (e) => {
  try { e.target.showPicker?.(); } catch (_) {}
}));
document.getElementById('f-limpiar').addEventListener('click', () => {
  document.getElementById('f-vendedor').value = '0';
  document.getElementById('f-desde').value = '';
  document.getElementById('f-hasta').value = '';
  cargar();
});

document.querySelectorAll('#filtro-estado .opt').forEach(b => b.addEventListener('click', () => {
  document.querySelectorAll('#filtro-estado .opt').forEach(x => x.classList.remove('active'));
  b.classList.add('active');
  estadoFiltro = b.dataset.estado;
  document.getElementById('filtros').classList.toggle('d-none', estadoFiltro !== 'todas');
  cargar();
}));

cargar();
</script>
</body>
</html>
