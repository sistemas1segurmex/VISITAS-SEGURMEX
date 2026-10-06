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
/* ============================================================
   Ver cotización — celular primero (navegador y APK) y dos columnas
   en escritorio (>= 992px): a la izquierda la cotización y el envío,
   a la derecha cliente, condiciones, estado e historial.
   ============================================================ */
.vc-wrap { padding-bottom: 28px; }
.vc-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 14px; }
.vc-col { display: flex; flex-direction: column; gap: 14px; min-width: 0; }
.vc-card { background: var(--v26-surface-solid); border: 1px solid var(--v26-border); border-radius: 18px; padding: 16px; box-shadow: var(--v26-shadow-sm); }
.vc-card-titulo { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: .74rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; color: var(--v26-ink-soft); margin-bottom: 12px; }
.vc-card-titulo .cuenta { font-size: .7rem; font-weight: 700; background: rgba(20,23,31,.06); color: var(--v26-ink-soft); border-radius: 999px; padding: 2px 9px; text-transform: none; letter-spacing: 0; }

/* ---------- Tarjeta principal ---------- */
.vc-hero { position: relative; overflow: hidden; border-radius: 22px; padding: 18px 16px 16px; background:
    radial-gradient(120% 90% at 100% 0%, rgba(255, 210, 63, .28), transparent 60%),
    linear-gradient(160deg, #FFFFFF 0%, #FFFBF0 100%);
  border: 1px solid rgba(201, 136, 0, .22); box-shadow: 0 18px 40px -26px rgba(201, 136, 0, .55); }
.vc-hero-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
.vc-folio { font-size: 1.35rem; font-weight: 800; letter-spacing: .01em; line-height: 1.1; }
.vc-cliente { font-size: .9rem; font-weight: 700; margin-top: 4px; }
.vc-creada { font-size: .76rem; color: var(--v26-ink-soft); margin-top: 1px; }
.vc-pills { display: flex; flex-wrap: wrap; gap: 6px; justify-content: flex-end; }
.vc-total { margin-top: 16px; }
.vc-total .lbl { font-size: .72rem; font-weight: 700; color: #8A6D14; text-transform: uppercase; letter-spacing: .05em; }
.vc-total .monto { font-size: 2rem; font-weight: 800; letter-spacing: -.01em; color: #17140C; line-height: 1.1; }
.vc-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin-top: 14px; }
.vc-stat { background: rgba(255,255,255,.75); border: 1px solid rgba(201,136,0,.18); border-radius: 14px; padding: 9px 10px; min-width: 0; }
.vc-stat .l { font-size: .66rem; font-weight: 700; color: var(--v26-ink-soft); text-transform: uppercase; letter-spacing: .04em; }
.vc-stat .v { font-size: .95rem; font-weight: 800; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.vc-stat .s { font-size: .7rem; color: var(--v26-ink-soft); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.vc-stat .s.alerta { color: var(--v26-red); font-weight: 700; }
.vc-stat .s.ojo { color: #B45309; font-weight: 700; }

/* Avance del estado */
.vc-pasos { display: flex; align-items: center; margin-top: 16px; }
.vc-paso { display: flex; flex-direction: column; align-items: center; gap: 4px; flex: none; width: 68px; text-align: center; }
.vc-paso .punto { width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: .8rem; background: #fff; border: 2px solid #E5DFCF; color: #B8AF9C; }
.vc-paso .txt { font-size: .66rem; font-weight: 700; color: var(--v26-ink-soft); line-height: 1.15; }
.vc-paso.hecho .punto { background: var(--v26-brand-grad); border-color: transparent; color: #fff; }
.vc-paso.actual .punto { box-shadow: 0 0 0 4px rgba(232,164,0,.2); }
.vc-paso.actual .txt { color: var(--v26-ink); }
.vc-paso.ok .punto { background: var(--v26-green); color: #fff; border-color: transparent; box-shadow: 0 0 0 4px rgba(22,163,74,.18); }
.vc-paso.mal .punto { background: var(--v26-red); color: #fff; border-color: transparent; box-shadow: 0 0 0 4px rgba(225,29,72,.15); }
.vc-paso.gris .punto { background: #9CA3AF; color: #fff; border-color: transparent; }
.vc-linea { flex: 1; height: 3px; border-radius: 3px; background: #E5DFCF; margin: 0 -14px 18px; }
.vc-linea.hecha { background: linear-gradient(90deg, var(--v26-brand-1), var(--v26-brand-2)); }

.vc-hero-acciones { display: flex; gap: 8px; margin-top: 16px; }
.vc-hero-acciones .v26-btn { flex: 1; min-height: 44px; font-size: .85rem; text-decoration: none; white-space: nowrap; }

/* ---------- Avisos ---------- */
.vc-aviso { display: flex; gap: 12px; align-items: flex-start; border-radius: 16px; padding: 14px 16px; animation: vc-entra .3s var(--v26-ease); }
.vc-aviso > i { font-size: 1.4rem; line-height: 1; }
.vc-aviso strong { display: block; font-size: .95rem; }
.vc-aviso span { font-size: .8rem; }
.vc-aviso.ok { background: rgba(22,163,74,.08); border: 1.5px solid rgba(22,163,74,.35); color: #14532D; }
.vc-aviso.ok > i { color: var(--v26-green); }
.vc-aviso.mal { background: rgba(225,29,72,.06); border: 1.5px solid rgba(225,29,72,.3); color: #881337; }
.vc-aviso.mal > i { color: var(--v26-red); }
.vc-aviso.gris { background: rgba(107,114,128,.08); border: 1.5px solid rgba(107,114,128,.25); color: #374151; }
.vc-aviso.gris > i { color: #6B7280; }
@keyframes vc-entra { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: none; } }

/* ---------- Enviar al cliente ---------- */
.vc-enviar-titulo { font-weight: 800; font-size: 1rem; display: flex; align-items: center; gap: 8px; }
.vc-enviar-sub { font-size: .78rem; color: var(--v26-ink-soft); margin: 2px 0 14px; }
.vc-enviar label { display: block; font-size: .72rem; font-weight: 700; color: var(--v26-ink-soft); text-transform: uppercase; letter-spacing: .03em; margin-bottom: 6px; }
.vc-enviar .v26-input, .vc-enviar textarea { font-size: 16px; }
.vc-enviar textarea { width: 100%; border: 1px solid var(--v26-border); border-radius: var(--v26-r-md); padding: 10px 12px; font-family: inherit; resize: vertical; min-height: 120px; background: #FCFBF8; }
.vc-campo { margin-bottom: 12px; }
.vc-btn-wa { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; min-height: 52px; border-radius: 14px; background: #25D366; color: #fff; font-weight: 800; font-size: .98rem; text-decoration: none; border: none; box-shadow: 0 12px 26px -12px rgba(37,211,102,.7); }
.vc-btn-wa:hover, .vc-btn-wa:active { color: #fff; background: #1EBE5A; }
.vc-btn-wa i { font-size: 1.25rem; }
.vc-acciones-2 { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
.vc-acciones-2 .v26-btn { flex: 1; min-height: 46px; font-size: .85rem; white-space: nowrap; text-decoration: none; }
.vc-link-mini { font-size: .72rem; color: var(--v26-ink-soft); margin-top: 10px; word-break: break-all; }
.vc-marcada { display: flex; gap: 8px; align-items: flex-start; margin-top: 12px; padding: 10px 12px; border-radius: 12px; background: rgba(22,163,74,.08); color: #14532D; font-size: .8rem; font-weight: 600; }
.vc-marcada i { color: var(--v26-green); }

/* ---------- Modelos ---------- */
.vc-renglones { display: flex; flex-direction: column; }
.vc-renglon { display: flex; gap: 12px; align-items: flex-start; padding: 12px 0; border-top: 1px solid var(--v26-border); }
.vc-renglon:first-child { border-top: none; padding-top: 0; }
.vc-foto { width: 54px; height: 54px; object-fit: contain; border: 1px solid var(--v26-border); border-radius: 12px; background: #fff; flex: none; }
.vc-foto--vacia { display: flex; align-items: center; justify-content: center; color: #C9BFA6; font-size: 1.2rem; background: #FBF9F4; }
.vc-r-info { flex: 1; min-width: 0; }
.vc-r-top { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; }
.vc-r-clave { font-weight: 800; font-size: .95rem; }
.vc-r-importe { font-weight: 800; font-size: .95rem; white-space: nowrap; }
.vc-r-nombre { font-size: .76rem; color: var(--v26-ink-soft); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-top: 1px; }
.vc-r-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 6px; font-size: .76rem; color: var(--v26-ink-soft); }
.vc-tag { display: inline-flex; align-items: center; gap: 4px; font-size: .68rem; font-weight: 700; padding: 2px 9px; border-radius: 999px; background: rgba(20,23,31,.06); color: var(--v26-ink-soft); }
.vc-tag--desc { background: rgba(22,163,74,.1); color: var(--v26-green); }
.vc-tag--entrega { background: #E0F2FE; color: #075985; }
.vc-tag--attr { background: transparent; border: 1px solid #F5A623; color: #92400E; }
.vc-leyenda-attr { font-size: .72rem; color: var(--v26-ink-soft); margin-top: 8px; line-height: 1.5; }
.vc-aviso-entrega { display: flex; gap: 8px; align-items: flex-start; font-size: .78rem; font-weight: 600; color: #B45309; background: #FFFBEB; border-radius: 10px; padding: 8px 10px; margin: 2px 0 8px; }
.vc-tachado { text-decoration: line-through; color: #B8AF9C; }

.vc-totales { margin-top: 12px; padding: 12px 14px; border-radius: 14px; background: #FBF7EA; border: 1px solid #EFE4C4; font-size: .86rem; }
.vc-totales .fila { display: flex; justify-content: space-between; padding: 3px 0; color: var(--v26-ink-soft); }
.vc-totales .fila span:last-child { color: var(--v26-ink); font-weight: 600; }
.vc-totales .total { margin-top: 6px; padding-top: 8px; border-top: 1.5px solid #E3D6B2; font-size: 1.05rem; color: var(--v26-ink); font-weight: 800; }
.vc-totales .total span:last-child { font-weight: 800; color: #8A6D14; }
.vc-descuentos { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 6px; }

/* ---------- Datos (cliente / condiciones) ---------- */
.vc-quien { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
.vc-avatar { width: 46px; height: 46px; border-radius: 50%; flex: none; display: flex; align-items: center; justify-content: center; font-weight: 800; color: #fff; background: var(--v26-brand-grad); box-shadow: var(--v26-shadow-brand); }
.vc-quien .nom { font-weight: 800; font-size: .98rem; }
.vc-quien .sub { font-size: .78rem; color: var(--v26-ink-soft); }
.vc-dato { display: flex; gap: 12px; align-items: flex-start; padding: 10px 0; border-top: 1px solid var(--v26-border); }
.vc-dato > i { width: 32px; height: 32px; border-radius: 10px; flex: none; display: flex; align-items: center; justify-content: center; background: rgba(232,164,0,.1); color: var(--v26-brand-2); font-size: .95rem; }
.vc-dato .cuerpo { flex: 1; min-width: 0; }
.vc-dato .k { font-size: .68rem; font-weight: 700; color: var(--v26-ink-soft); text-transform: uppercase; letter-spacing: .03em; }
.vc-dato .v { font-size: .88rem; font-weight: 600; word-break: break-word; }
.vc-dato .v.vacio { color: #B8AF9C; font-weight: 500; }
.vc-dato .v a { color: inherit; text-decoration: none; }
.vc-mini { display: flex; gap: 6px; flex: none; align-self: center; }
.vc-mini a { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 1px solid var(--v26-border); background: #fff; color: var(--v26-ink); text-decoration: none; font-size: .95rem; }
.vc-mini a.wa { color: #16A34A; }
.vc-notas { margin-top: 10px; padding: 10px 12px; border-radius: 12px; background: #FBF9F4; border: 1px dashed #E3D6B2; font-size: .84rem; white-space: pre-wrap; }

/* ---------- Estado ---------- */
.vc-estado-ayuda { font-size: .76rem; color: var(--v26-ink-soft); margin: -4px 0 10px; }
.vc-estados { display: flex; flex-wrap: wrap; gap: 8px; }
.vc-btn-estado { display: inline-flex; align-items: center; gap: 6px; min-height: 42px; border-radius: 12px; padding: 9px 14px; font-weight: 700; font-size: .8rem; border: 1.5px solid var(--v26-border); background: var(--v26-surface-solid); color: var(--v26-ink); font-family: inherit; cursor: pointer; }
.vc-btn-estado.aceptar { color: #065F46; border-color: rgba(22,163,74,.35); background: rgba(22,163,74,.06); }
.vc-btn-estado.cancelar { color: #991B1B; border-color: #FCA5A5; background: #FEF2F2; }

/* ---------- Historial ---------- */
.vc-hist { position: relative; padding-left: 4px; }
.vc-hist-item { position: relative; display: flex; gap: 12px; padding-bottom: 14px; }
.vc-hist-item:last-child { padding-bottom: 0; }
.vc-hist-item:not(:last-child)::before { content: ''; position: absolute; left: 13px; top: 28px; bottom: 2px; width: 2px; background: #EFE9DA; }
.vc-hist-ico { width: 28px; height: 28px; border-radius: 50%; flex: none; display: flex; align-items: center; justify-content: center; font-size: .78rem; background: rgba(107,114,128,.12); color: #4B5563; }
.vc-hist-ico.enviada, .vc-hist-ico.en_negociacion { background: rgba(37,99,235,.12); color: #1E40AF; }
.vc-hist-ico.aceptada { background: rgba(22,163,74,.14); color: var(--v26-green); }
.vc-hist-ico.rechazada, .vc-hist-ico.cancelada { background: rgba(225,29,72,.12); color: var(--v26-red); }
.vc-hist-txt strong { display: block; font-size: .84rem; }
.vc-hist-txt .quien { font-size: .72rem; color: var(--v26-ink-soft); margin-top: 1px; }
.vc-hist-txt .cliente { color: #1E40AF; font-weight: 700; }

/* ---------- Escritorio ---------- */
@media (hover: hover) {
  .vc-mini a:hover { border-color: var(--v26-brand-1); color: var(--v26-brand-2); }
  .vc-btn-estado:hover { border-color: var(--v26-brand-1); }
}
@media (min-width: 992px) {
  .v26-wrap.vc-wrap { max-width: 1120px; padding: 24px 24px 32px; }
  .vc-grid { grid-template-columns: minmax(0, 1fr) 380px; gap: 20px; align-items: start; }
  .vc-col--lado { position: sticky; top: 92px; }
  .vc-hero { padding: 24px 24px 20px; }
  .vc-total .monto { font-size: 2.4rem; }
  .vc-card { padding: 20px; }
}
@media (max-width: 380px) {
  .vc-stats { grid-template-columns: 1fr 1fr; }
  .vc-stats .vc-stat:last-child { grid-column: 1 / -1; }
}
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

  <div class="v26-wrap vc-wrap" id="contenido">
    <div class="v26-skel"></div>
    <div class="v26-skel"></div>
    <div class="v26-skel"></div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/v26-modal.js<?= assetVer(__DIR__ . '/../assets/js/v26-modal.js') ?>"></script>
<script src="../assets/js/vendedor.js<?= assetVer(__DIR__ . '/../assets/js/vendedor.js') ?>"></script>
<script>
iniciarTrackingPeriodico();
const ID = <?= (int)$id ?>;
const NOMBRE_VENDEDOR = <?= json_encode((string)($u['nombre'] ?? ''), JSON_UNESCAPED_UNICODE) ?>;
// Viene de "Guardar cotización" (nueva_cotizacion.php): se avisa una vez y
// se quita el parámetro para que al recargar no vuelva a salir.
const RECIEN_GENERADA = new URLSearchParams(location.search).get('nueva') === '1';
if (RECIEN_GENERADA) history.replaceState(null, '', location.pathname + '?id=' + ID);
// Texto de los botones de estado: "Enviada" sola parecía que mandaba algo.
const ACCION_ESTADO = {
  enviada: 'Marcar como enviada', en_negociacion: 'Pasar a negociación', aceptada: 'Marcar como aceptada',
  rechazada: 'Marcar como rechazada', cancelada: 'Cancelar cotización',
};
const TEXTO_ESTADO = {
  enviada: 'Úsalo si se la mandaste por otro medio. El cliente podrá aceptarla o rechazarla desde el PDF.',
  en_negociacion: 'Indica que estás ajustando la cotización con el cliente. Desde el PDF todavía puede aceptarla o rechazarla.',
  aceptada: 'Regístralo si el cliente te confirmó por teléfono, WhatsApp o en persona.',
  rechazada: 'Regístralo si el cliente te dijo que no.',
  cancelada: 'El cliente ya no podrá abrir el PDF ni responderla. No se puede deshacer.',
};
const ESTADOS_PARA_ENVIAR = ['pendiente', 'enviada', 'en_negociacion'];
let mostrarAvisoGenerada = RECIEN_GENERADA;
let avisoMarcadaEnviada = false;
let estadoActual = '';

// Lo que se le manda al cliente es el PDF; dentro del PDF va el botón para
// responder en línea (link público del ERP).
function mensajeWhatsApp(c, url) {
  const saludo = c.cliente_contacto ? `Hola ${c.cliente_contacto}` : 'Hola';
  return `${saludo}, le comparto la cotización ${c.folio} de Segurmex.\n\n`
    + `Total: ${money(c.total)} (IVA incluido)\n`
    + `Vigencia: ${parseInt(c.vigencia_dias)} días\n\n`
    + `Puede ver la cotización en PDF aquí:\n${url}\n\n`
    + `Quedo atento a sus comentarios.`
    + (NOMBRE_VENDEDOR ? `\n${NOMBRE_VENDEDOR}` : '');
}

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

// Fotos del catálogo: viven en el ERP (mismo servidor), igual que en Nueva cotización.
const FOTOS_URL = '/erp/assets/img/cotizador/';
const DIA_MS = 86400000;

function iniciales(nombre) {
  const p = String(nombre || '').trim().split(/\s+/).filter(Boolean);
  return ((p[0] || '?')[0] + (p[1] ? p[1][0] : '')).toUpperCase();
}
function fechaCorta(d) { return d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short' }).replace('.', ''); }

// Avance: Pendiente → Enviada → respuesta del cliente.
function pasosHTML(c, historial) {
  const e = c.estado;
  const pasoEnviada = e === 'en_negociacion' ? 'En negociación' : 'Enviada';
  const seEnvio = ['enviada', 'en_negociacion', 'aceptada', 'rechazada'].includes(e)
    || historial.some(h => h.estado_nuevo === 'enviada');
  let final = { txt: 'Aceptada', cls: '', ico: '<i class="bi bi-check-lg"></i>' };
  if (e === 'aceptada')  final = { txt: 'Aceptada',  cls: 'hecho ok actual', ico: '<i class="bi bi-check-lg"></i>' };
  if (e === 'rechazada') final = { txt: 'Rechazada', cls: 'hecho mal actual', ico: '<i class="bi bi-x-lg"></i>' };
  if (e === 'cancelada') final = { txt: 'Cancelada', cls: 'hecho gris actual', ico: '<i class="bi bi-slash-lg"></i>' };
  const p1 = `hecho ${e === 'pendiente' ? 'actual' : ''}`;
  const p2 = seEnvio ? `hecho ${['enviada', 'en_negociacion'].includes(e) ? 'actual' : ''}` : '';
  return `
    <div class="vc-pasos" aria-label="Avance de la cotización">
      <div class="vc-paso ${p1}"><span class="punto"><i class="bi bi-file-earmark-text"></i></span><span class="txt">Creada</span></div>
      <div class="vc-linea ${seEnvio ? 'hecha' : ''}"></div>
      <div class="vc-paso ${p2}"><span class="punto"><i class="bi bi-send"></i></span><span class="txt">${pasoEnviada}</span></div>
      <div class="vc-linea ${final.cls ? 'hecha' : ''}"></div>
      <div class="vc-paso ${final.cls}"><span class="punto">${final.ico}</span><span class="txt">${final.txt}</span></div>
    </div>`;
}

// Aviso cuando la cotización ya tiene una respuesta (o se canceló).
function avisoResultadoHTML(c, historial) {
  const h = historial.find(x => x.estado_nuevo === c.estado);
  // fechaHora() ya termina en "a.m."/"p.m.": se quita ese punto para no dejar "p.m.."
  const cuando = h ? ` el ${fechaHora(h.created_at).replace(/\.$/, '')}` : '';
  const porCliente = h && h.origen === 'cliente';
  if (c.estado === 'aceptada') {
    return `<div class="vc-aviso ok" role="status"><i class="bi bi-patch-check-fill"></i><div>
      <strong>${porCliente ? 'El cliente aceptó la cotización' : 'Cotización aceptada'}</strong>
      <span>${porCliente ? 'La aceptó desde el link' : 'Marcada como aceptada'}${cuando}.</span></div></div>`;
  }
  if (c.estado === 'rechazada') {
    return `<div class="vc-aviso mal" role="status"><i class="bi bi-x-octagon-fill"></i><div>
      <strong>${porCliente ? 'El cliente rechazó la cotización' : 'Cotización rechazada'}</strong>
      <span>${porCliente ? 'La rechazó desde el link' : 'Marcada como rechazada'}${cuando}. Puedes contactarlo para ofrecerle otra.</span></div></div>`;
  }
  if (c.estado === 'cancelada') {
    return `<div class="vc-aviso gris" role="status"><i class="bi bi-slash-circle-fill"></i><div>
      <strong>Cotización cancelada</strong><span>Se canceló${cuando}. El cliente ya no puede abrir el PDF.</span></div></div>`;
  }
  return '';
}

function renglonHTML(d) {
  const foto = d.foto
    ? `<img src="${FOTOS_URL}${encodeURIComponent(d.foto)}" alt="" loading="lazy" class="vc-foto">`
    : '<span class="vc-foto vc-foto--vacia"><i class="bi bi-box-seam"></i></span>';
  const lista = parseFloat(d.precio_lista) || 0;
  const final = parseFloat(d.precio_final) || 0;
  const tachado = lista > 0 && final < lista ? `<span class="vc-tachado">${money(lista)}</span>` : '';
  const pares = parseInt(d.cantidad) || 0;
  return `
    <div class="vc-renglon">
      ${foto}
      <div class="vc-r-info">
        <div class="vc-r-top"><span class="vc-r-clave">${escHtml(d.clave_estilo)}</span><span class="vc-r-importe">${money(d.importe)}</span></div>
        <div class="vc-r-nombre">${escHtml(d.nombre_estilo)}</div>
        <div class="vc-r-meta">
          ${d.atributo ? `<span class="vc-tag vc-tag--attr">${escHtml(d.atributo)}</span>` : ''}
          ${d.color ? `<span class="vc-tag">${escHtml(d.color)}</span>` : ''}
          ${d.entrega_dias ? `<span class="vc-tag vc-tag--entrega"><i class="bi bi-truck"></i>Entrega: ${parseInt(d.entrega_dias)} días hábiles</span>` : ''}
          <span>${pares} ${pares === 1 ? 'par' : 'pares'} × ${tachado} ${money(final)}</span>
        </div>
      </div>
    </div>`;
}

// Qué significa cada atributo de seguridad que trae ESTA cotización (PP+D, O…).
function leyendaAtributosHTML(atributos) {
  const pares = Object.entries(atributos || {});
  if (!pares.length) return '';
  return `<div class="vc-leyenda-attr">${pares.map(([c, s]) => `<strong>${escHtml(c)}</strong> = ${escHtml(s)}`).join(' · ')}</div>`;
}

function datoHTML(icono, etiqueta, valorHtml, extra = '') {
  const vacio = !valorHtml;
  return `<div class="vc-dato"><i class="bi ${icono}"></i>
    <div class="cuerpo"><div class="k">${etiqueta}</div><div class="v ${vacio ? 'vacio' : ''}">${vacio ? 'Sin capturar' : valorHtml}</div></div>${extra}</div>`;
}

function historialHTML(historial) {
  if (!historial.length) return '<div class="text-muted" style="font-size:.82rem;">Sin movimientos todavía.</div>';
  return '<div class="vc-hist">' + historial.map(h => {
    const actor = (h.nombre || h.apellidos)
      ? escHtml(`${h.nombre || ''} ${h.apellidos || ''}`.trim())
      : (h.origen === 'cliente' ? '<span class="cliente">El cliente, desde el link</span>' : 'Sistema');
    const titulo = h.estado_anterior
      ? `${escHtml(ETIQUETAS[h.estado_nuevo] || h.estado_nuevo)}`
      : 'Cotización creada';
    const ico = h.estado_anterior ? (ICONOS[h.estado_nuevo] || 'bi-arrow-right') : 'bi-file-earmark-plus';
    return `<div class="vc-hist-item">
      <span class="vc-hist-ico ${escHtml(h.estado_nuevo)}"><i class="bi ${ico}"></i></span>
      <div class="vc-hist-txt"><strong>${titulo}</strong><div class="quien">${fechaHora(h.created_at)} · ${actor}</div></div>
    </div>`;
  }).join('') + '</div>';
}

function render(data) {
  const c = data.cotizacion;
  estadoActual = c.estado;
  document.getElementById('titulo-folio').textContent = c.folio;

  const creada = new Date(c.created_at);
  const vence = new Date(creada.getTime() + (parseInt(c.vigencia_dias) || 0) * DIA_MS);
  const abierta = ['pendiente', 'enviada', 'en_negociacion'].includes(c.estado);
  const quedan = Math.ceil((vence.getTime() - Date.now()) / DIA_MS);
  let vigSub = `vence ${fechaCorta(vence)}`, vigCls = '';
  if (abierta) {
    if (data.vencida || quedan < 0) { vigSub = 'vencida'; vigCls = 'alerta'; }
    else if (quedan <= 3) { vigSub = quedan <= 0 ? 'vence hoy' : `quedan ${quedan} día${quedan === 1 ? '' : 's'}`; vigCls = 'ojo'; }
    else vigSub = `quedan ${quedan} días`;
  }
  const descuentos = [];
  if (parseInt(c.aplica_mayoreo)) descuentos.push('mayoreo');
  if (parseInt(c.pronto_pago)) descuentos.push('pronto pago');
  const lista = c.tipo_lista === 'distribuidor' ? 'Distribuidor' : 'Industria';
  const tel = telefonoMx(c.cliente_telefono);
  const pares = parseInt(c.total_pares) || 0;

  const hero = `
    <div class="vc-hero">
      <div class="vc-hero-top">
        <div style="min-width:0;">
          <div class="vc-folio">${escHtml(c.folio)}</div>
          <div class="vc-cliente">${escHtml(c.cliente_nombre)}</div>
          <div class="vc-creada">Creada el ${fecha(c.created_at)}</div>
        </div>
        <div class="vc-pills">
          <span class="v26-pill v26-pill--${escHtml(c.estado)}">${escHtml(ETIQUETAS[c.estado] || c.estado)}</span>
          ${data.vencida && abierta ? '<span class="v26-pill v26-pill--noverificado">Vencida</span>' : ''}
          ${c.visitas_cita_id ? '<span class="v26-pill v26-pill--verificado">De una visita</span>' : ''}
        </div>
      </div>
      <div class="vc-total">
        <div class="lbl">Total con IVA</div>
        <div class="monto">${money(c.total)}</div>
      </div>
      <div class="vc-stats">
        <div class="vc-stat"><div class="l">Pares</div><div class="v">${pares}</div><div class="s">${data.detalle.length} modelo${data.detalle.length === 1 ? '' : 's'}</div></div>
        <div class="vc-stat"><div class="l">Vigencia</div><div class="v">${parseInt(c.vigencia_dias)} días</div><div class="s ${vigCls}">${vigSub}</div></div>
        <div class="vc-stat"><div class="l">Lista</div><div class="v">${lista}</div><div class="s">${descuentos.length ? 'con ' + descuentos.join(' y ') : 'sin descuentos'}</div></div>
      </div>
      ${pasosHTML(c, data.historial)}
      ${data.url_pdf && c.estado !== 'cancelada' ? `
      <div class="vc-hero-acciones">
        <a class="v26-btn v26-btn-ghost" id="btn-ver-pdf" href="${escHtml(data.url_pdf)}" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf"></i> Ver PDF</a>
      </div>` : ''}
    </div>`;

  const avisoGenerada = mostrarAvisoGenerada ? `
    <div class="vc-aviso ok" role="status"><i class="bi bi-check-circle-fill"></i>
      <div><strong>Cotización ${escHtml(c.folio)} generada</strong><span>Ya quedó guardada. Ahora envíasela al cliente.</span></div>
    </div>` : '';

  const enviar = data.url_pdf && ESTADOS_PARA_ENVIAR.includes(c.estado) ? `
    <div class="vc-card vc-enviar" id="vc-enviar">
      <div class="vc-enviar-titulo"><i class="bi bi-send-check" style="color:var(--v26-brand-2)"></i> ${c.estado === 'pendiente' ? 'Enviar al cliente' : 'Volver a enviar'}</div>
      <div class="vc-enviar-sub">El cliente recibe un link a la cotización en PDF. Al enviarla se marca como <b>Enviada</b> y desde el PDF el cliente puede aceptarla o rechazarla, sin cuenta.</div>
      <div class="vc-campo">
        <label for="wa-telefono">WhatsApp del cliente</label>
        <input type="text" id="wa-telefono" class="v26-input" value="${escHtml(c.cliente_telefono || '')}">
      </div>
      <div class="vc-campo">
        <label for="wa-mensaje">Mensaje</label>
        <textarea id="wa-mensaje" rows="6">${escHtml(mensajeWhatsApp(c, data.url_pdf))}</textarea>
      </div>
      <a class="vc-btn-wa" id="btn-whatsapp" href="#" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i><span>Enviar por WhatsApp</span></a>
      <div class="vc-acciones-2">
        <button type="button" class="v26-btn v26-btn-ghost" id="btn-copiar-link"><i class="bi bi-link-45deg"></i> Copiar link</button>
        <button type="button" class="v26-btn v26-btn-ghost" id="btn-copiar-mensaje"><i class="bi bi-clipboard"></i> Copiar mensaje</button>
      </div>
      <div class="vc-link-mini" id="link-publico">${escHtml(data.url_pdf)}</div>
      ${avisoMarcadaEnviada ? `
      <div class="vc-marcada" role="status"><i class="bi bi-check-circle-fill"></i><span>Se marcó como <b>Enviada</b>. El cliente ya puede aceptarla o rechazarla desde el PDF.</span></div>` : ''}
    </div>` : '';

  const modelos = `
    <div class="vc-card">
      <div class="vc-card-titulo"><span>Modelos cotizados</span><span class="cuenta">${pares} ${pares === 1 ? 'par' : 'pares'}</span></div>
      <div class="vc-renglones">${data.detalle.map(renglonHTML).join('')}</div>
      ${leyendaAtributosHTML(data.atributos)}
      <div class="vc-totales">
        ${descuentos.length ? `<div class="vc-descuentos">${descuentos.map(d => `<span class="vc-tag vc-tag--desc"><i class="bi bi-tag"></i>${d}</span>`).join('')}</div>` : ''}
        <div class="fila"><span>Subtotal</span><span>${money(c.subtotal)}</span></div>
        <div class="fila"><span>IVA</span><span>${money(c.iva)}</span></div>
        <div class="fila total"><span>Total</span><span>${money(c.total)}</span></div>
      </div>
    </div>`;

  const mapa = c.cliente_direccion ? `<div class="vc-mini"><a href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(c.cliente_direccion)}" target="_blank" rel="noopener" aria-label="Ver en el mapa"><i class="bi bi-map"></i></a></div>` : '';
  const telAcc = tel.valido ? `<div class="vc-mini">
      <a href="tel:${tel.digitos}" aria-label="Llamar"><i class="bi bi-telephone"></i></a>
      <a class="wa" href="${linkWhatsApp(tel.digitos)}" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a>
    </div>` : '';
  const cliente = `
    <div class="vc-card">
      <div class="vc-card-titulo"><span>Cliente</span></div>
      <div class="vc-quien">
        <div class="vc-avatar">${escHtml(iniciales(c.cliente_nombre))}</div>
        <div style="min-width:0;"><div class="nom">${escHtml(c.cliente_nombre)}</div><div class="sub">${c.cliente_contacto ? 'Atiende: ' + escHtml(c.cliente_contacto) : 'Sin contacto capturado'}</div></div>
      </div>
      ${datoHTML('bi-telephone', 'Teléfono', tel.valido ? formatoTelefonoMx(tel.digitos) : escHtml(c.cliente_telefono || ''), telAcc)}
      ${datoHTML('bi-envelope', 'Correo', c.cliente_email ? `<a href="mailto:${escHtml(c.cliente_email)}">${escHtml(c.cliente_email)}</a>` : '')}
      ${datoHTML('bi-geo-alt', 'Dirección', escHtml(c.cliente_direccion || ''), mapa)}
    </div>`;

  const condiciones = `
    <div class="vc-card">
      <div class="vc-card-titulo"><span>Condiciones</span></div>
      ${datoHTML('bi-calendar-check', 'Vigencia', `${parseInt(c.vigencia_dias)} días · hasta el ${fecha(vence.toISOString())}`)}
      ${datoHTML('bi-truck', 'Tiempo de entrega', escHtml(c.tiempo_entrega || ''))}
      ${data.aviso_entrega ? `<div class="vc-aviso-entrega"><i class="bi bi-exclamation-triangle"></i><span>${escHtml(data.aviso_entrega)}</span></div>` : ''}
      ${datoHTML('bi-credit-card', 'Forma de pago', escHtml(c.forma_pago || ''))}
      ${c.notas ? `<div class="vc-notas">${escHtml(c.notas)}</div>` : ''}
    </div>`;

  const estado = data.transiciones.length ? `
    <div class="vc-card">
      <div class="vc-card-titulo"><span>Cambiar estado</span></div>
      ${c.estado === 'pendiente' ? '<div class="vc-estado-ayuda">Si se la mandaste por otro medio (correo, en persona), márcala como enviada.</div>' : ''}
      ${['enviada', 'en_negociacion'].includes(c.estado) ? '<div class="vc-estado-ayuda">Si el cliente te respondió por otro medio, regístralo aquí.</div>' : ''}
      <div class="vc-estados">
        ${data.transiciones.map(t => `
          <button type="button" class="vc-btn-estado ${t === 'aceptada' ? 'aceptar' : ''} ${['cancelada', 'rechazada'].includes(t) ? 'cancelar' : ''}" data-estado="${t}">
            <i class="bi ${ICONOS[t] || 'bi-arrow-right-circle'}"></i>${escHtml(ACCION_ESTADO[t] || ETIQUETAS[t] || t)}
          </button>`).join('')}
      </div>
    </div>` : '';

  const historial = `
    <div class="vc-card">
      <div class="vc-card-titulo"><span>Historial</span></div>
      ${historialHTML(data.historial)}
    </div>`;

  document.getElementById('contenido').innerHTML = `
    <div class="vc-grid">
      <div class="vc-col">${hero}${avisoGenerada}${avisoResultadoHTML(c, data.historial)}${enviar}${modelos}</div>
      <div class="vc-col vc-col--lado">${cliente}${condiciones}${estado}${historial}</div>
    </div>`;

  activarEnvio(c, data);
  document.querySelectorAll('.vc-btn-estado').forEach(btn => btn.addEventListener('click', async () => {
    const nuevo = btn.dataset.estado;
    const ok = await v26Sheet({
      titulo: `¿${ACCION_ESTADO[nuevo] || 'Cambiar a ' + (ETIQUETAS[nuevo] || nuevo)}?`,
      desc: TEXTO_ESTADO[nuevo] || `La cotización ${c.folio} pasará a "${ETIQUETAS[nuevo] || nuevo}".`,
      pedirMotivo: false,
      textoConfirmar: 'Sí, continuar',
      textoCancelar: 'Volver',
    });
    if (ok) cambiarEstado(nuevo);
  }));
}

function copiarTexto(texto, btn) {
  const original = btn.innerHTML;
  const hecho = () => {
    btn.innerHTML = '<i class="bi bi-check2"></i> Copiado';
    setTimeout(() => { btn.innerHTML = original; }, 1600);
    marcarEnviadaAlCompartir();
  };
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(texto).then(hecho).catch(() => copiarRespaldo(texto) && hecho());
  } else if (copiarRespaldo(texto)) {
    hecho();
  }
}
// Respaldo para http (localhost) o WebViews sin portapapeles moderno.
function copiarRespaldo(texto) {
  const ta = document.createElement('textarea');
  ta.value = texto; ta.setAttribute('readonly', '');
  ta.style.cssText = 'position:fixed;top:-1000px;opacity:0';
  document.body.appendChild(ta); ta.select(); ta.setSelectionRange(0, texto.length);
  let ok = false; try { ok = document.execCommand('copy'); } catch (e) {}
  ta.remove();
  return ok;
}

// Al compartirla (WhatsApp o copiar) la cotización pasa sola de Pendiente a
// Enviada: el link del ERP solo deja aceptar/rechazar desde Enviada o En
// negociación, y antes el cliente abría el PDF y le salía "todavía no está
// lista para responderse" si el vendedor no la marcaba a mano.
let marcandoEnviada = false;
function marcarEnviadaAlCompartir() {
  if (estadoActual !== 'pendiente' || marcandoEnviada) return;
  marcandoEnviada = true;
  cambiarEstado('enviada', { alCompartir: true }).finally(() => { marcandoEnviada = false; });
}

// Sección "Enviar al cliente": número (10 dígitos), mensaje editable y
// botón de WhatsApp que se arma con lo que haya en ambos campos.
function activarEnvio(c, data) {
  const caja = document.getElementById('vc-enviar');
  if (!caja) return;
  const campoTel = activarCampoTelefono(document.getElementById('wa-telefono'), { avisarAlCargar: true });
  const mensaje = document.getElementById('wa-mensaje');
  const btnWa = document.getElementById('btn-whatsapp');

  function actualizarLink() {
    const t = telefonoMx(campoTel.input.value);
    btnWa.href = linkWhatsApp(t.valido ? t.digitos : '', mensaje.value);
    btnWa.querySelector('span').textContent = t.vacio ? 'Elegir contacto en WhatsApp' : 'Enviar por WhatsApp';
  }
  campoTel.input.addEventListener('input', actualizarLink);
  campoTel.input.addEventListener('blur', actualizarLink);
  mensaje.addEventListener('input', actualizarLink);
  actualizarLink();

  btnWa.addEventListener('click', e => {
    if (!campoTel.validar()) {
      e.preventDefault();
      campoTel.input.focus();
      return;
    }
    actualizarLink();
    marcarEnviadaAlCompartir();
  });
  document.getElementById('btn-copiar-link').addEventListener('click', e => copiarTexto(data.url_pdf, e.currentTarget));
  document.getElementById('btn-copiar-mensaje').addEventListener('click', e => copiarTexto(mensaje.value, e.currentTarget));
}

async function cambiarEstado(nuevoEstado, { alCompartir = false } = {}) {
  const fd = new FormData();
  fd.set('accion', 'cambiar_estado');
  fd.set('id', ID);
  fd.set('nuevo_estado', nuevoEstado);
  let data;
  try {
    // keepalive: en la APK la app se va a segundo plano al abrir WhatsApp y
    // la petición tiene que terminar de todos modos.
    const res = await fetch('../api/cotizacion_detalle.php', { method: 'POST', body: fd, keepalive: true });
    data = await res.json();
  } catch (e) {
    data = { ok: false, error: 'No se pudo actualizar el estado (revisa tu conexión).' };
  }
  if (data.ok) {
    mostrarAvisoGenerada = false;
    avisoMarcadaEnviada = alCompartir;
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
