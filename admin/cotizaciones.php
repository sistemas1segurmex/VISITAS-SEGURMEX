<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$u = requireRole('admin');
$hoy = (new DateTime('now', new DateTimeZone('America/Mexico_City')))->format('Y-m-d');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cotizaciones — Control de Visitas</title>
<link rel="icon" type="image/png" href="../assets/img/Favv-Segu.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css<?= assetVer(__DIR__ . '/../assets/css/style.css') ?>">
<link rel="stylesheet" href="../assets/css/admin-2026.css<?= assetVer(__DIR__ . '/../assets/css/admin-2026.css') ?>">
<style>
/* Cotizaciones de los vendedores, por día (solo lectura). Celular primero. */
.ac-dia { display: flex; align-items: center; gap: 6px; }
.ac-dia .v26-icon-btn, .ac-dia .btn-hoy { flex: none; }
.ac-dia input { width: 150px; font-size: 16px; }
.ac-dia .btn-hoy { border: 1px solid var(--v26-border); background: var(--v26-surface-solid); border-radius: 10px; padding: 6px 12px; font-weight: 700; font-size: .8rem; min-height: 38px; }
.ac-dia .btn-hoy:disabled { opacity: .45; }
.ac-flecha { width: 38px; height: 38px; border-radius: 10px; border: 1px solid var(--v26-border); background: var(--v26-surface-solid); display: inline-flex; align-items: center; justify-content: center; }
.ac-flecha:disabled { opacity: .35; }

.ac-chips { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 4px; margin-bottom: 12px; scrollbar-width: thin; }
.ac-chip { flex: none; display: inline-flex; align-items: center; gap: 6px; border: 1px solid var(--v26-border); background: var(--v26-surface-solid); border-radius: 999px; padding: 6px 12px; font-size: .8rem; font-weight: 600; min-height: 38px; white-space: nowrap; }
.ac-chip b { font-size: .74rem; background: rgba(20,23,31,.07); border-radius: 999px; padding: 0 7px; }
.ac-chip.active { background: var(--v26-ink); color: #fff; border-color: var(--v26-ink); }
.ac-chip.active b { background: rgba(255,255,255,.2); }

.ac-lista { display: flex; flex-direction: column; gap: 10px; }
.ac-item { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 4px 12px; width: 100%; text-align: left; border: 1px solid var(--v26-border); border-left: 4px solid var(--v26-brand-1); background: var(--v26-surface-solid); border-radius: 14px; padding: 12px 14px; box-shadow: var(--v26-shadow-sm); color: var(--v26-ink); }
.ac-item:hover { border-color: rgba(201,136,0,.4); }
.ac-item.cancelada { border-left-color: #9CA3AF; opacity: .75; }
.ac-item .folio { font-weight: 800; font-size: .95rem; }
.ac-item .cliente { font-size: .86rem; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ac-item .meta { font-size: .74rem; color: var(--v26-ink-soft); display: flex; flex-wrap: wrap; gap: 4px 10px; align-items: center; }
.ac-item .total { font-weight: 800; font-size: 1rem; text-align: right; white-space: nowrap; }
.ac-item .lado { display: flex; flex-direction: column; align-items: flex-end; gap: 6px; grid-row: 1 / span 3; grid-column: 2; }

.v26-pill--enviada        { background: rgba(37,99,235,.12);  color: #1D4ED8; }
.v26-pill--en_negociacion { background: rgba(124,58,237,.12); color: #7C3AED; }
.v26-pill--aceptada       { background: rgba(22,163,74,.12);  color: var(--v26-green); }
.v26-pill--rechazada      { background: rgba(225,29,72,.12);  color: var(--v26-red); }

/* Ficha (modal) */
.ac-modal .modal-content { border-radius: 20px; border: 0; }
.ac-hero { border-radius: 16px; padding: 14px; background: linear-gradient(160deg, #FFFFFF 0%, #FFFBF0 100%); border: 1px solid rgba(201,136,0,.22); }
.ac-hero .monto { font-size: 1.7rem; font-weight: 800; line-height: 1.1; }
.ac-hero .lbl { font-size: .68rem; font-weight: 700; color: #8A6D14; text-transform: uppercase; letter-spacing: .05em; margin-top: 10px; }
.ac-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin-top: 10px; }
.ac-stat { background: rgba(255,255,255,.8); border: 1px solid rgba(201,136,0,.18); border-radius: 12px; padding: 7px 9px; min-width: 0; }
.ac-stat .l { font-size: .62rem; font-weight: 700; color: var(--v26-ink-soft); text-transform: uppercase; }
.ac-stat .v { font-size: .88rem; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ac-stat .s { font-size: .68rem; color: var(--v26-ink-soft); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ac-sec { font-size: .7rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; color: var(--v26-ink-soft); margin: 16px 0 6px; }
.ac-renglon { display: flex; gap: 10px; padding: 9px 0; border-top: 1px solid var(--v26-border); }
.ac-renglon:first-child { border-top: 0; }
.ac-foto { width: 46px; height: 46px; object-fit: contain; border: 1px solid var(--v26-border); border-radius: 10px; background: #fff; flex: none; display: flex; align-items: center; justify-content: center; color: #C9BFA6; }
.ac-renglon .info { flex: 1; min-width: 0; font-size: .8rem; }
.ac-renglon .top { display: flex; justify-content: space-between; gap: 8px; font-weight: 800; font-size: .88rem; }
.ac-renglon .nom { color: var(--v26-ink-soft); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ac-totales { margin-top: 8px; padding: 10px 12px; border-radius: 12px; background: #FBF7EA; border: 1px solid #EFE4C4; font-size: .84rem; }
.ac-totales .fila { display: flex; justify-content: space-between; padding: 2px 0; }
.ac-totales .fila.total { font-weight: 800; border-top: 1px solid #E3D6B2; margin-top: 4px; padding-top: 6px; }
.ac-dato { display: flex; justify-content: space-between; gap: 12px; padding: 7px 0; border-top: 1px solid var(--v26-border); font-size: .84rem; }
.ac-dato:first-child { border-top: 0; }
.ac-dato .k { color: var(--v26-ink-soft); flex: none; }
.ac-dato .v { text-align: right; font-weight: 600; word-break: break-word; }
.ac-notas { margin-top: 8px; padding: 9px 11px; border-radius: 10px; background: #FBF9F4; border: 1px dashed #E3D6B2; font-size: .82rem; white-space: pre-wrap; }
.ac-hist-item { font-size: .8rem; padding: 6px 0; border-top: 1px solid var(--v26-border); }
.ac-hist-item:first-child { border-top: 0; }
.ac-hist-item .q { font-size: .72rem; color: var(--v26-ink-soft); }
.ac-solo-lectura { font-size: .74rem; color: var(--v26-ink-soft); display: flex; gap: 6px; align-items: center; }

@media (min-width: 992px) {
  .ac-lista { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
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
        <div class="name">Cotizaciones</div>
      </div>
    </div>
    <div class="v26-topbar-right">
      <span class="v26-user"><?= htmlspecialchars($u['nombre']) ?></span>
      <a href="../logout.php" class="v26-icon-btn v26-tip v26-tip--bottom" data-tip="Cerrar sesión" aria-label="Salir"><i class="bi bi-box-arrow-right"></i></a>
    </div>
  </div>
</div>

<div class="v26-wrap">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
      <h5 class="mb-0">Cotizaciones de tus vendedores</h5>
      <p class="v26-subtitulo mb-0" id="subtitulo">Cuántas generó cada vendedor en el día</p>
    </div>
    <div class="ac-dia">
      <button type="button" class="ac-flecha" id="dia-ant" aria-label="Día anterior"><i class="bi bi-chevron-left"></i></button>
      <input type="date" id="fecha" class="form-control form-control-sm" max="<?= $hoy ?>">
      <button type="button" class="ac-flecha" id="dia-sig" aria-label="Día siguiente"><i class="bi bi-chevron-right"></i></button>
      <button type="button" class="btn-hoy" id="btn-hoy">Hoy</button>
    </div>
  </div>

  <div class="v26-stats-row mb-3">
    <div class="v26-stat-card"><div class="v26-stat-icon"><i class="bi bi-file-earmark-text"></i></div><div><div class="v26-stat-num" id="st-total">—</div><div class="v26-stat-label">Cotizaciones</div></div></div>
    <div class="v26-stat-card v26-stat-card--verde"><div class="v26-stat-icon"><i class="bi bi-cash-stack"></i></div><div><div class="v26-stat-num" id="st-monto">—</div><div class="v26-stat-label">Cotizado (con IVA)</div></div></div>
    <div class="v26-stat-card v26-stat-card--azul"><div class="v26-stat-icon"><i class="bi bi-people"></i></div><div><div class="v26-stat-num" id="st-vend">—</div><div class="v26-stat-label">Vendedores que cotizaron</div></div></div>
    <div class="v26-stat-card v26-stat-card--morado"><div class="v26-stat-icon"><i class="bi bi-geo-alt"></i></div><div><div class="v26-stat-num" id="st-visita">—</div><div class="v26-stat-label">Salieron de una visita</div></div></div>
  </div>

  <div class="ac-chips" id="chips" role="group" aria-label="Filtrar por vendedor"></div>
  <div class="ac-lista" id="lista"><p class="text-muted small mb-0">Cargando...</p></div>
</div>

<div class="modal fade ac-modal" id="modalCot" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <div>
          <h5 class="modal-title fw-bold mb-0" id="mc-folio">Cotización</h5>
          <div class="small text-muted" id="mc-sub"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body" id="mc-cuerpo"></div>
      <div class="modal-footer border-0 pt-0 justify-content-between">
        <span class="ac-solo-lectura"><i class="bi bi-eye"></i> Solo lectura: el estado lo cambia el vendedor.</span>
        <a class="btn btn-sm btn-outline-dark d-none" id="mc-pdf" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf"></i> Ver PDF</a>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/fecha_utils.js<?= assetVer(__DIR__ . '/../assets/js/fecha_utils.js') ?>"></script>
<script>
const HOY = <?= json_encode($hoy) ?>;
const ETIQUETAS = {
  pendiente: 'Pendiente', enviada: 'Enviada', en_negociacion: 'En negociación',
  aceptada: 'Aceptada', rechazada: 'Rechazada', facturada: 'Facturada',
  entregada: 'Entregada', cancelada: 'Cancelada',
};
const FOTOS_URL = '/erp/assets/img/cotizador/';
const params = new URLSearchParams(location.search);
let fecha = /^\d{4}-\d{2}-\d{2}$/.test(params.get('fecha') || '') ? params.get('fecha') : HOY;
let vendedor = parseInt(params.get('vendedor')) || 0;
let datos = null;
const modal = new bootstrap.Modal(document.getElementById('modalCot'));

function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function money(n) { return '$' + Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function moneyCorto(n) { return '$' + Number(n || 0).toLocaleString('es-MX', { maximumFractionDigits: 0 }); }
function hora12(hhmm) {
  const [h, m] = String(hhmm || '').split(':').map(Number);
  return isNaN(h) ? '' : `${h % 12 || 12}:${String(m).padStart(2, '0')} ${h < 12 ? 'a.m.' : 'p.m.'}`;
}
function sumarDias(iso, n) {
  const d = new Date(iso + 'T12:00:00');
  d.setDate(d.getDate() + n);
  return d.toISOString().slice(0, 10);
}
function fechaLarga(iso) {
  return new Date(iso + 'T12:00:00').toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long' });
}
// timestamptz de Postgres ("2026-10-06 17:05:12.3+00") -> "06/10/2026, 11:05 a.m." hora de México.
function fechaHoraTz(s) {
  const d = new Date(String(s || '').replace(' ', 'T').replace(/([+-]\d{2})$/, '$1:00'));
  return isNaN(d.getTime()) ? (s || '') : d.toLocaleString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric', hour: 'numeric', minute: '2-digit', timeZone: 'America/Mexico_City' });
}

function guardarUrl(id) {
  const q = new URLSearchParams();
  if (fecha !== HOY) q.set('fecha', fecha);
  if (vendedor) q.set('vendedor', vendedor);
  if (id) q.set('id', id);
  history.replaceState(null, '', location.pathname + (q.toString() ? '?' + q : ''));
}

async function cargar() {
  document.getElementById('fecha').value = fecha;
  document.getElementById('dia-sig').disabled = fecha >= HOY;
  document.getElementById('btn-hoy').disabled = fecha === HOY;
  document.getElementById('subtitulo').textContent = fecha === HOY ? 'Hoy, ' + fechaLarga(fecha) : fechaLarga(fecha).replace(/^./, c => c.toUpperCase());
  guardarUrl();
  try {
    // Siempre se pide el día completo: el filtro por vendedor se hace aquí
    // para que los números de arriba sean de todo el equipo.
    const res = await fetch('../api/admin_cotizaciones.php?fecha=' + encodeURIComponent(fecha));
    datos = await res.json();
    if (!datos.ok) throw new Error(datos.error);
    pintar();
  } catch (e) {
    document.getElementById('lista').innerHTML = `<div class="alert alert-danger mb-0">${esc(e.message || 'No se pudieron cargar las cotizaciones.')}</div>`;
  }
}

function pintar() {
  const cots = datos.cotizaciones || [];
  document.getElementById('st-total').textContent = datos.total;
  document.getElementById('st-monto').textContent = moneyCorto(datos.monto);
  document.getElementById('st-vend').textContent = datos.por_vendedor.filter(v => v.n > 0).length;
  document.getElementById('st-visita').textContent = cots.filter(c => c.visitas_cita_id).length;

  // Chips: todos los que cotizaron ese día, más el filtrado aunque lleve 0.
  const visibles = datos.por_vendedor.filter(v => v.n > 0 || Number(v.id) === vendedor);
  document.getElementById('chips').innerHTML =
    `<button type="button" class="ac-chip ${vendedor ? '' : 'active'}" data-v="0">Todos <b>${datos.total}</b></button>`
    + visibles.map(v => `<button type="button" class="ac-chip ${Number(v.id) === vendedor ? 'active' : ''}" data-v="${v.id}">${esc(v.nombre)} <b>${v.n}</b></button>`).join('');

  const lista = vendedor ? cots.filter(c => Number(c.vendedor_id) === vendedor) : cots;
  const cont = document.getElementById('lista');
  if (!lista.length) {
    const sin = datos.por_vendedor.filter(v => v.n === 0).map(v => esc(v.nombre));
    cont.innerHTML = `<div class="v26-empty"><i class="bi bi-file-earmark-x icon"></i><p>${vendedor ? 'Este vendedor no generó cotizaciones' : 'No se generaron cotizaciones'} ${fecha === HOY ? 'hoy' : 'este día'}.</p>
      ${!vendedor && sin.length ? `<p class="small text-muted mb-0">Vendedores activos: ${sin.join(', ')}</p>` : ''}</div>`;
    return;
  }
  cont.innerHTML = lista.map(c => `
    <button type="button" class="ac-item ${c.estado === 'cancelada' ? 'cancelada' : ''}" data-id="${c.id}">
      <span class="folio">${esc(c.folio)}</span>
      <span class="cliente">${esc(c.cliente_nombre)}</span>
      <span class="meta"><span><i class="bi bi-person"></i> ${esc(c.vendedor_nombre)}</span><span><i class="bi bi-clock"></i> ${hora12(c.hora)}</span><span>${parseInt(c.total_pares) || 0} pares</span>${c.visitas_cita_id ? '<span><i class="bi bi-geo-alt"></i> De una visita</span>' : ''}</span>
      <span class="lado"><span class="total">${money(c.total)}</span><span class="v26-pill v26-pill--${esc(c.estado)}">${esc(ETIQUETAS[c.estado] || c.estado)}</span></span>
    </button>`).join('');
}

async function abrir(id) {
  document.getElementById('mc-folio').textContent = 'Cotización';
  document.getElementById('mc-sub').textContent = '';
  document.getElementById('mc-cuerpo').innerHTML = '<p class="text-muted small">Cargando...</p>';
  document.getElementById('mc-pdf').classList.add('d-none');
  modal.show();
  guardarUrl(id);
  try {
    const res = await fetch('../api/admin_cotizaciones.php?id=' + encodeURIComponent(id));
    const d = await res.json();
    if (!d.ok) throw new Error(d.error);
    pintarFicha(d);
  } catch (e) {
    document.getElementById('mc-cuerpo').innerHTML = `<div class="alert alert-danger mb-0">${esc(e.message || 'No se pudo cargar la cotización.')}</div>`;
  }
}

function pintarFicha(d) {
  const c = d.cotizacion;
  const pares = parseInt(c.total_pares) || 0;
  const desc = [];
  if (c.aplica_mayoreo === true || c.aplica_mayoreo === 't' || parseInt(c.aplica_mayoreo)) desc.push('mayoreo');
  if (c.pronto_pago === true || c.pronto_pago === 't' || parseInt(c.pronto_pago)) desc.push('pronto pago');
  document.getElementById('mc-folio').textContent = c.folio;
  document.getElementById('mc-sub').textContent = `${c.vendedor_nombre} · ${fechaHoraTz(c.created_at)}`;
  const pdf = document.getElementById('mc-pdf');
  if (d.url_pdf) { pdf.href = d.url_pdf; pdf.classList.remove('d-none'); }

  const dato = (k, v) => `<div class="ac-dato"><span class="k">${k}</span><span class="v">${v || '<span class="text-muted fw-normal">Sin capturar</span>'}</span></div>`;
  document.getElementById('mc-cuerpo').innerHTML = `
    <div class="ac-hero">
      <div class="d-flex justify-content-between align-items-start gap-2">
        <div style="min-width:0"><div class="fw-bold">${esc(c.cliente_nombre)}</div>${c.cliente_contacto ? `<div class="small text-muted">Atiende: ${esc(c.cliente_contacto)}</div>` : ''}</div>
        <div class="d-flex flex-wrap gap-1 justify-content-end">
          <span class="v26-pill v26-pill--${esc(c.estado)}">${esc(ETIQUETAS[c.estado] || c.estado)}</span>
          ${d.vencida ? '<span class="v26-pill v26-pill--noverificado">Vencida</span>' : ''}
          ${c.visitas_cita_id ? '<span class="v26-pill v26-pill--verificado">De una visita</span>' : ''}
        </div>
      </div>
      <div class="lbl">Total con IVA</div>
      <div class="monto">${money(c.total)}</div>
      <div class="ac-stats">
        <div class="ac-stat"><div class="l">Pares</div><div class="v">${pares}</div><div class="s">${d.detalle.length} modelo${d.detalle.length === 1 ? '' : 's'}</div></div>
        <div class="ac-stat"><div class="l">Vigencia</div><div class="v">${parseInt(c.vigencia_dias) || 0} días</div></div>
        <div class="ac-stat"><div class="l">Lista</div><div class="v">${c.tipo_lista === 'distribuidor' ? 'Distribuidor' : 'Industria'}</div><div class="s">${desc.length ? 'con ' + desc.join(' y ') : 'sin descuentos'}</div></div>
      </div>
    </div>

    <div class="ac-sec">Modelos cotizados</div>
    ${d.detalle.map(r => `
      <div class="ac-renglon">
        ${r.foto ? `<img class="ac-foto" src="${FOTOS_URL}${encodeURIComponent(r.foto)}" alt="" loading="lazy">` : '<span class="ac-foto"><i class="bi bi-box-seam"></i></span>'}
        <div class="info">
          <div class="top"><span>${esc(r.clave_estilo)}</span><span>${money(r.importe)}</span></div>
          <div class="nom">${esc(r.nombre_estilo)}</div>
          <div class="text-muted">${r.color ? esc(r.color) + ' · ' : ''}${parseInt(r.cantidad) || 0} pares × ${money(r.precio_final)}</div>
        </div>
      </div>`).join('')}
    <div class="ac-totales">
      <div class="fila"><span>Subtotal</span><span>${money(c.subtotal)}</span></div>
      <div class="fila"><span>IVA</span><span>${money(c.iva)}</span></div>
      <div class="fila total"><span>Total</span><span>${money(c.total)}</span></div>
    </div>

    <div class="ac-sec">Cliente y condiciones</div>
    ${dato('Teléfono', esc(c.cliente_telefono || ''))}
    ${dato('Correo', esc(c.cliente_email || ''))}
    ${dato('Dirección', esc(c.cliente_direccion || ''))}
    ${dato('Tiempo de entrega', esc(c.tiempo_entrega || ''))}
    ${dato('Forma de pago', esc(c.forma_pago || ''))}
    ${c.notas ? `<div class="ac-notas">${esc(c.notas)}</div>` : ''}

    <div class="ac-sec">Historial</div>
    ${d.historial.length ? d.historial.map(h => {
      const quien = (h.nombre || h.apellidos) ? esc(`${h.nombre || ''} ${h.apellidos || ''}`.trim()) : (h.origen === 'cliente' ? 'El cliente, desde el link' : 'Sistema');
      return `<div class="ac-hist-item"><b>${h.estado_anterior ? esc(ETIQUETAS[h.estado_nuevo] || h.estado_nuevo) : 'Cotización creada'}</b>
        <div class="q">${fechaHoraTz(h.created_at)} · ${quien}</div></div>`;
    }).join('') : '<p class="small text-muted mb-0">Sin movimientos.</p>'}`;
}

document.getElementById('fecha').addEventListener('change', e => { if (e.target.value) { fecha = e.target.value > HOY ? HOY : e.target.value; cargar(); } });
document.getElementById('dia-ant').addEventListener('click', () => { fecha = sumarDias(fecha, -1); cargar(); });
document.getElementById('dia-sig').addEventListener('click', () => { if (fecha < HOY) { fecha = sumarDias(fecha, 1); cargar(); } });
document.getElementById('btn-hoy').addEventListener('click', () => { fecha = HOY; cargar(); });
document.getElementById('chips').addEventListener('click', e => {
  const b = e.target.closest('[data-v]');
  if (!b) return;
  vendedor = parseInt(b.dataset.v) || 0;
  guardarUrl();
  pintar();
});
document.getElementById('lista').addEventListener('click', e => {
  const b = e.target.closest('[data-id]');
  if (b) abrir(b.dataset.id);
});
document.getElementById('modalCot').addEventListener('hidden.bs.modal', () => guardarUrl());

cargar().then(() => { const id = parseInt(params.get('id')); if (id) abrir(id); });
// Si es hoy, se refresca solo (igual que el panel).
setInterval(() => { if (fecha === HOY && !document.getElementById('modalCot').classList.contains('show')) cargar(); }, 60000);
</script>
</body>
</html>
