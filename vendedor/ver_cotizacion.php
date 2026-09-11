<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$u = requireRole('vendedor');

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: cotizaciones.php'); exit; }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cotización</title>
<link rel="icon" type="image/png" href="../assets/img/Favv-Segu.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css<?= assetVer(__DIR__ . '/../assets/css/style.css') ?>">
<link rel="stylesheet" href="../assets/css/vendedor-2026.css<?= assetVer(__DIR__ . '/../assets/css/vendedor-2026.css') ?>">
<style>
.vc-tabla { width:100%; border-collapse:collapse; font-size:.82rem; }
.vc-tabla th { text-align:left; font-size:.7rem; color:var(--v26-ink-soft); text-transform:uppercase; letter-spacing:.02em; padding:4px 6px; }
.vc-tabla td { padding:6px; border-top:1px solid var(--v26-border); vertical-align:middle; }
.vc-totales { font-size:.85rem; }
.vc-totales .fila { display:flex; justify-content:space-between; padding:3px 0; }
.vc-totales .total { font-weight:800; font-size:1rem; border-top:1px solid var(--v26-border); margin-top:6px; padding-top:8px; }
.vc-dato { display:flex; justify-content:space-between; gap:10px; padding:4px 0; font-size:.85rem; }
.vc-dato span:first-child { color:var(--v26-ink-soft); }
.vc-dato span:last-child { text-align:right; font-weight:600; }
.vc-card-titulo { font-size:.78rem; font-weight:800; text-transform:uppercase; letter-spacing:.03em; color:var(--v26-ink-soft); margin-bottom:10px; }

.vc-link-banner { background:linear-gradient(120deg,#FFFBEB,#FFF3D6 60%,#FFFBEB); border:1.5px solid #FFD23F; border-radius:16px; padding:16px; margin-bottom:14px; }
.vc-link-titulo { font-weight:800; font-size:.9rem; color:#111827; }
.vc-link-sub { font-size:.76rem; color:#92400E; margin:4px 0 10px; }
.vc-link-caja { display:flex; gap:8px; }
.vc-link-caja input { flex:1; font-size:.76rem; border:1px solid #F5D8A0; border-radius:10px; padding:8px 10px; background:#fff; color:#4B4536; }
.vc-link-btn { background:#FFD23F; border:none; color:#111827; font-weight:700; border-radius:10px; padding:0 14px; flex-shrink:0; }

.vc-estado-banner { background:var(--v26-surface-solid); border:1px solid var(--v26-border); border-radius:16px; padding:14px; margin-bottom:14px; }
.vc-btn-estado { display:inline-flex; align-items:center; gap:6px; border-radius:10px; padding:9px 14px; font-weight:700; font-size:.8rem; border:1.5px solid var(--v26-border); background:var(--v26-surface-solid); color:var(--v26-ink); margin:0 6px 6px 0; }
.vc-btn-estado.cancelar { color:#991B1B; border-color:#FCA5A5; background:#FEF2F2; }

.vc-historial-item { padding:9px 0; border-top:1px solid var(--v26-border); font-size:.8rem; }
.vc-historial-item:first-child { border-top:none; }
.vc-historial-item .quien { color:var(--v26-ink-soft); font-size:.72rem; margin-top:1px; }
</style>
</head>
<body class="v26">
  <div class="v26-header">
    <div class="v26-topbar">
      <div class="v26-topbar-left">
        <a href="cotizaciones.php" class="v26-back v26-tip v26-tip--bottom" data-tip="Volver a mis cotizaciones" aria-label="Volver"><i class="bi bi-arrow-left"></i></a>
        <div class="v26-greeting">
          <div class="hi">Ventas</div>
          <div class="name" id="titulo-folio">Cotización</div>
        </div>
      </div>
      <div class="v26-topbar-right">
        <img src="../logo.png" alt="Segurmex" class="v26-logo">
      </div>
    </div>
  </div>

  <div class="v26-wrap" id="contenido">
    <div class="v26-skel"></div>
    <div class="v26-skel"></div>
    <div class="v26-skel"></div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/vendedor.js<?= assetVer(__DIR__ . '/../assets/js/vendedor.js') ?>"></script>
<script>
iniciarTrackingPeriodico();
const ID = <?= (int)$id ?>;

const ETIQUETAS = {
  pendiente: 'Pendiente', enviada: 'Enviada', en_negociacion: 'En negociación',
  aceptada: 'Aceptada', rechazada: 'Rechazada', facturada: 'Facturada',
  entregada: 'Entregada', cancelada: 'Cancelada',
};
const ICONOS = {
  enviada: 'bi-send', en_negociacion: 'bi-chat-dots', aceptada: 'bi-check-circle',
  rechazada: 'bi-x-circle', cancelada: 'bi-slash-circle',
};

function money(n) { return '$' + Number(n || 0).toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
function escHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function fecha(iso) { const d = new Date(iso); return d.toLocaleDateString('es-MX', {day:'2-digit', month:'2-digit', year:'numeric'}); }
function fechaHora(iso) { const d = new Date(iso); return d.toLocaleDateString('es-MX', {day:'2-digit', month:'2-digit', year:'numeric'}) + ' ' + d.toLocaleTimeString('es-MX', {hour:'2-digit', minute:'2-digit'}); }

function render(data) {
  const c = data.cotizacion;
  document.getElementById('titulo-folio').textContent = c.folio;

  const cont = document.getElementById('contenido');
  cont.innerHTML = `
    <div class="v26-card">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
        <div>
          <div style="font-weight:800; font-size:1.05rem;">${escHtml(c.folio)}</div>
          <div class="text-muted" style="font-size:.8rem;">${escHtml(c.cliente_nombre)} · ${fecha(c.created_at)}</div>
        </div>
        <div class="d-flex flex-wrap gap-1">
          <span class="v26-pill v26-pill--${escHtml(c.estado)}">${escHtml(ETIQUETAS[c.estado] || c.estado)}</span>
          ${data.vencida ? '<span class="v26-pill v26-pill--noverificado">Vencida</span>' : ''}
          ${c.visitas_cita_id ? '<span class="v26-pill v26-pill--verificado"><i class="bi bi-geo-alt"></i> De una visita</span>' : ''}
        </div>
      </div>
    </div>

    ${data.transiciones.length ? `
    <div class="vc-estado-banner">
      <div class="vc-card-titulo" style="margin-bottom:8px;">Cambiar estado</div>
      <div>
        ${data.transiciones.map(t => `
          <button type="button" class="vc-btn-estado ${t === 'cancelada' ? 'cancelar' : ''}" data-estado="${t}">
            <i class="bi ${ICONOS[t] || 'bi-arrow-right-circle'}"></i>${escHtml(ETIQUETAS[t] || t)}
          </button>
        `).join('')}
      </div>
    </div>` : ''}

    ${data.url_publica ? `
    <div class="vc-link-banner">
      <div class="vc-link-titulo"><i class="bi bi-link-45deg"></i> Link para el cliente</div>
      <div class="vc-link-sub">Compártelo por WhatsApp o correo — el cliente puede aceptar o rechazar ahí mismo, sin necesitar cuenta.</div>
      <div class="vc-link-caja">
        <input type="text" id="link-publico" readonly value="${escHtml(data.url_publica)}">
        <button type="button" class="vc-link-btn" id="btn-copiar-link"><i class="bi bi-clipboard"></i></button>
      </div>
    </div>` : ''}

    <div class="v26-card mb-3">
      <div class="vc-card-titulo">Cliente</div>
      <div class="vc-dato"><span>Nombre</span><span>${escHtml(c.cliente_nombre)}</span></div>
      <div class="vc-dato"><span>Contacto</span><span>${escHtml(c.cliente_contacto || '—')}</span></div>
      <div class="vc-dato"><span>Teléfono</span><span>${escHtml(c.cliente_telefono || '—')}</span></div>
      <div class="vc-dato"><span>Correo</span><span>${escHtml(c.cliente_email || '—')}</span></div>
      <div class="vc-dato"><span>Dirección</span><span>${escHtml(c.cliente_direccion || '—')}</span></div>
      <div class="vc-dato"><span>Lista de precios</span><span>${c.tipo_lista === 'distribuidor' ? 'Distribuidor' : 'Industria'}</span></div>
    </div>

    <div class="v26-card mb-3">
      <div class="vc-card-titulo">Modelos cotizados</div>
      <div style="overflow-x:auto;">
        <table class="vc-tabla">
          <thead><tr><th>Estilo</th><th>Cant.</th><th>Precio</th><th>Importe</th></tr></thead>
          <tbody>
            ${data.detalle.map(d => `
              <tr>
                <td><strong>${escHtml(d.clave_estilo)}</strong><br><span class="text-muted" style="font-size:.72rem;">${escHtml(d.nombre_estilo)}</span></td>
                <td>${parseInt(d.cantidad)}</td>
                <td>${money(d.precio_final)}</td>
                <td>${money(d.importe)}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
    </div>

    ${c.notas ? `
    <div class="v26-card mb-3">
      <div class="vc-card-titulo">Notas</div>
      <div style="font-size:.85rem; white-space:pre-wrap;">${escHtml(c.notas)}</div>
    </div>` : ''}

    <div class="v26-card mb-3 vc-totales">
      <div class="vc-card-titulo">Totales</div>
      <div class="fila"><span>Total de pares</span><span>${parseInt(c.total_pares)}</span></div>
      <div class="fila"><span>Mayoreo</span><span>${c.aplica_mayoreo ? 'Sí' : 'No'}</span></div>
      <div class="fila"><span>Pronto pago</span><span>${c.pronto_pago ? 'Sí' : 'No'}</span></div>
      <div class="fila"><span>Subtotal</span><span>${money(c.subtotal)}</span></div>
      <div class="fila"><span>IVA</span><span>${money(c.iva)}</span></div>
      <div class="fila total"><span>Total</span><span>${money(c.total)}</span></div>
      <hr style="border-color:var(--v26-border);">
      <div class="fila"><span>Vigencia</span><span>${parseInt(c.vigencia_dias)} días</span></div>
      ${c.tiempo_entrega ? `<div class="fila"><span>Entrega</span><span>${escHtml(c.tiempo_entrega)}</span></div>` : ''}
      ${c.forma_pago ? `<div class="fila"><span>Forma de pago</span><span>${escHtml(c.forma_pago)}</span></div>` : ''}
    </div>

    <div class="v26-card">
      <div class="vc-card-titulo">Historial</div>
      ${data.historial.map(h => {
        let actor = (h.nombre || h.apellidos) ? `${h.nombre || ''} ${h.apellidos || ''}`.trim() : (h.origen === 'cliente' ? 'Cliente (vía link público)' : 'Sistema');
        const transicion = h.estado_anterior ? `${escHtml(ETIQUETAS[h.estado_anterior] || h.estado_anterior)} → ` : '';
        return `
        <div class="vc-historial-item">
          <div><strong>${transicion}${escHtml(ETIQUETAS[h.estado_nuevo] || h.estado_nuevo)}</strong></div>
          <div class="quien">${fechaHora(h.created_at)} · ${escHtml(actor)}</div>
        </div>`;
      }).join('') || '<div class="text-muted" style="font-size:.82rem;">Sin movimientos todavía.</div>'}
    </div>
  `;

  document.getElementById('btn-copiar-link')?.addEventListener('click', () => {
    const inp = document.getElementById('link-publico');
    inp.select(); inp.setSelectionRange(0, 99999);
    const hecho = () => {
      const btn = document.getElementById('btn-copiar-link');
      btn.innerHTML = '<i class="bi bi-check2"></i>';
      setTimeout(() => { btn.innerHTML = '<i class="bi bi-clipboard"></i>'; }, 1500);
    };
    (navigator.clipboard?.writeText(inp.value) ?? Promise.reject()).then(hecho).catch(() => {
      document.execCommand('copy');
      hecho();
    });
  });

  document.querySelectorAll('.vc-btn-estado').forEach(btn => btn.addEventListener('click', () => {
    const nuevoEstado = btn.dataset.estado;
    if (!confirm(`¿Cambiar el estado a "${ETIQUETAS[nuevoEstado] || nuevoEstado}"?`)) return;
    cambiarEstado(nuevoEstado);
  }));
}

async function cambiarEstado(nuevoEstado) {
  const fd = new FormData();
  fd.set('accion', 'cambiar_estado');
  fd.set('id', ID);
  fd.set('nuevo_estado', nuevoEstado);
  const res = await fetch('../api/cotizacion_detalle.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.ok) {
    render(data);
  } else {
    alert(data.error || 'No se pudo actualizar el estado.');
  }
}

async function cargar() {
  try {
    const res = await fetch('../api/cotizacion_detalle.php?id=' + ID);
    const data = await res.json();
    if (!data.ok) {
      document.getElementById('contenido').innerHTML = `<div class="alert alert-danger">${escHtml(data.error)}</div>`;
      return;
    }
    render(data);
  } catch (e) {
    document.getElementById('contenido').innerHTML = '<div class="alert alert-danger">No se pudo cargar la cotización.</div>';
  }
}
cargar();
</script>
</body>
</html>
