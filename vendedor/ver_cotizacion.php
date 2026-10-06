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


.vc-estado-banner { background:var(--v26-surface-solid); border:1px solid var(--v26-border); border-radius:16px; padding:14px; margin-bottom:14px; }
.vc-btn-estado { display:inline-flex; align-items:center; gap:6px; border-radius:10px; padding:9px 14px; font-weight:700; font-size:.8rem; border:1.5px solid var(--v26-border); background:var(--v26-surface-solid); color:var(--v26-ink); margin:0 6px 6px 0; }
.vc-btn-estado.cancelar { color:#991B1B; border-color:#FCA5A5; background:#FEF2F2; }

/* Aviso de cotización recién guardada */
.vc-generada { display:flex; gap:12px; align-items:flex-start; background:rgba(22,163,74,.08); border:1.5px solid rgba(22,163,74,.35); color:#14532D; border-radius:16px; padding:14px 16px; margin-bottom:14px; animation:vc-entra .3s var(--v26-ease); }
.vc-generada i { font-size:1.4rem; color:var(--v26-green); line-height:1; }
.vc-generada strong { display:block; font-size:.95rem; }
.vc-generada span { font-size:.8rem; }
@keyframes vc-entra { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:none; } }

/* Enviar al cliente */
.vc-enviar { background:var(--v26-surface-solid); border:1px solid var(--v26-border); border-radius:18px; padding:16px; margin-bottom:14px; box-shadow:var(--v26-shadow-sm); }
.vc-enviar-titulo { font-weight:800; font-size:1rem; display:flex; align-items:center; gap:8px; }
.vc-enviar-sub { font-size:.78rem; color:var(--v26-ink-soft); margin:2px 0 14px; }
.vc-enviar label { display:block; font-size:.72rem; font-weight:700; color:var(--v26-ink-soft); text-transform:uppercase; letter-spacing:.03em; margin-bottom:6px; }
.vc-enviar .v26-input, .vc-enviar textarea { font-size:16px; }
.vc-enviar textarea { width:100%; border:1px solid var(--v26-border); border-radius:var(--v26-r-md); padding:10px 12px; font-family:inherit; resize:vertical; min-height:120px; background:#FCFBF8; }
.vc-campo { margin-bottom:12px; }
.vc-btn-wa { display:flex; align-items:center; justify-content:center; gap:10px; width:100%; min-height:52px; border-radius:14px; background:#25D366; color:#fff; font-weight:800; font-size:.98rem; text-decoration:none; border:none; box-shadow:0 12px 26px -12px rgba(37,211,102,.7); }
.vc-btn-wa:hover, .vc-btn-wa:active { color:#fff; background:#1EBE5A; }
.vc-btn-wa i { font-size:1.25rem; }
.vc-acciones-2 { display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; }
.vc-acciones-2 .v26-btn { white-space:nowrap; text-decoration:none; }
.vc-acciones-2 .v26-btn { flex:1; min-height:46px; font-size:.85rem; }
.vc-link-mini { font-size:.72rem; color:var(--v26-ink-soft); margin-top:10px; word-break:break-all; }
.vc-preguntar { display:none; margin-top:12px; padding:12px; border-radius:14px; background:#FFFBEB; border:1px solid #FDE68A; }
.vc-preguntar.show { display:block; }
.vc-preguntar p { margin:0 0 10px; font-size:.85rem; font-weight:700; color:#78350F; }
.vc-preguntar .vc-acciones-2 { margin-top:0; }
.vc-estado-ayuda { font-size:.76rem; color:var(--v26-ink-soft); margin:-2px 0 10px; }
.vc-btn-estado { min-height:42px; }
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
const NOMBRE_VENDEDOR = <?= json_encode((string)($u['nombre'] ?? ''), JSON_UNESCAPED_UNICODE) ?>;
// Viene de "Guardar cotización" (nueva_cotizacion.php): se avisa una vez y
// se quita el parámetro para que al recargar no vuelva a salir.
const RECIEN_GENERADA = new URLSearchParams(location.search).get('nueva') === '1';
if (RECIEN_GENERADA) history.replaceState(null, '', location.pathname + '?id=' + ID);
// Texto de los botones de estado: "Enviada" sola parecía que mandaba algo.
const ACCION_ESTADO = {
  enviada: 'Marcar como enviada', en_negociacion: 'En negociación', aceptada: 'Aceptada',
  rechazada: 'Rechazada', cancelada: 'Cancelar cotización',
};
const ESTADOS_PARA_ENVIAR = ['pendiente', 'enviada', 'en_negociacion'];
let mostrarAvisoGenerada = RECIEN_GENERADA;

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

    ${mostrarAvisoGenerada ? `
    <div class="vc-generada" role="status">
      <i class="bi bi-check-circle-fill"></i>
      <div><strong>Cotización ${escHtml(c.folio)} generada</strong><span>Ya quedó guardada. Ahora envíasela al cliente.</span></div>
    </div>` : ''}
    ${data.url_pdf && ESTADOS_PARA_ENVIAR.includes(c.estado) ? `
    <div class="vc-enviar" id="vc-enviar">
      <div class="vc-enviar-titulo"><i class="bi bi-send-check" style="color:var(--v26-brand-2)"></i> Enviar al cliente</div>
      <div class="vc-enviar-sub">El cliente recibe un link a la cotización en PDF. Desde el PDF también puede aceptarla o responder, sin cuenta.</div>
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
        <a class="v26-btn v26-btn-ghost" id="btn-ver-pdf" href="${escHtml(data.url_pdf)}" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf"></i> Ver PDF</a>
        <button type="button" class="v26-btn v26-btn-ghost" id="btn-copiar-link"><i class="bi bi-link-45deg"></i> Copiar link</button>
        <button type="button" class="v26-btn v26-btn-ghost" id="btn-copiar-mensaje"><i class="bi bi-clipboard"></i> Copiar mensaje</button>
      </div>
      <div class="vc-link-mini" id="link-publico">${escHtml(data.url_pdf)}</div>
      ${c.estado === 'pendiente' ? `
      <div class="vc-preguntar" id="vc-preguntar">
        <p><i class="bi bi-question-circle"></i> ¿Ya se la enviaste al cliente?</p>
        <div class="vc-acciones-2">
          <button type="button" class="v26-btn v26-btn-primary" id="btn-si-enviada">Sí, marcar como enviada</button>
          <button type="button" class="v26-btn v26-btn-ghost" id="btn-no-enviada">Todavía no</button>
        </div>
      </div>` : ''}
    </div>` : ''}
    <div class="v26-card mb-3">
      <div class="vc-card-titulo">Cliente</div>
      <div class="vc-dato"><span>Nombre</span><span>${escHtml(c.cliente_nombre)}</span></div>
      <div class="vc-dato"><span>Contacto</span><span>${escHtml(c.cliente_contacto || '—')}</span></div>
      <div class="vc-dato"><span>Teléfono</span><span>${escHtml(telefonoMx(c.cliente_telefono).valido ? formatoTelefonoMx(telefonoMx(c.cliente_telefono).digitos) : (c.cliente_telefono || '—'))}</span></div>
      <div class="vc-dato"><span>Correo</span><span>${escHtml(c.cliente_email || '—')}</span></div>
      <div class="vc-dato"><span>Dirección</span><span>${escHtml(c.cliente_direccion || '—')}</span></div>
      <div class="vc-dato"><span>Lista de precios</span><span>${c.tipo_lista === 'distribuidor' ? 'Distribuidor' : 'Industria'}</span></div>
    </div>

    <div class="v26-card mb-3">
      <div class="vc-card-titulo">Modelos cotizados</div>
      <div style="overflow-x:auto;">
        <table class="vc-tabla">
          <thead><tr><th>Modelo</th><th>Cant.</th><th>Precio</th><th>Importe</th></tr></thead>
          <tbody>
            ${data.detalle.map(d => `
              <tr>
                <td><strong>${escHtml(d.clave_estilo)}</strong><br><span class="text-muted" style="font-size:.72rem;">${escHtml(d.nombre_estilo)}</span>${d.color ? `<br><span style="font-size:.72rem;">Color: ${escHtml(d.color)}</span>` : ''}</td>
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

    ${data.transiciones.length ? `
    <div class="vc-estado-banner">
      <div class="vc-card-titulo" style="margin-bottom:6px;">Estado de la cotización</div>
      ${c.estado === 'pendiente' ? '<div class="vc-estado-ayuda">Si se la mandaste por otro medio (correo, en persona), márcala como enviada.</div>' : ''}
      <div>
        ${data.transiciones.map(t => `
          <button type="button" class="vc-btn-estado ${t === 'cancelada' ? 'cancelar' : ''}" data-estado="${t}">
            <i class="bi ${ICONOS[t] || 'bi-arrow-right-circle'}"></i>${escHtml(ACCION_ESTADO[t] || ETIQUETAS[t] || t)}
          </button>
        `).join('')}
      </div>
    </div>` : ''}
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

  activarEnvio(c, data);
  document.querySelectorAll('.vc-btn-estado').forEach(btn => btn.addEventListener('click', () => {
    const nuevoEstado = btn.dataset.estado;
    if (!confirm(`¿Cambiar el estado a "${ETIQUETAS[nuevoEstado] || nuevoEstado}"?`)) return;
    cambiarEstado(nuevoEstado);
  }));
}

function copiarTexto(texto, btn) {
  const original = btn.innerHTML;
  const hecho = () => {
    btn.innerHTML = '<i class="bi bi-check2"></i> Copiado';
    setTimeout(() => { btn.innerHTML = original; }, 1600);
    preguntarSiEnviada();
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

function preguntarSiEnviada() {
  const p = document.getElementById('vc-preguntar');
  if (p) p.classList.add('show');
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
    // Se pregunta al volver: WhatsApp no avisa si de verdad se envió.
    setTimeout(preguntarSiEnviada, 600);
  });
  document.getElementById('btn-copiar-link').addEventListener('click', e => copiarTexto(data.url_pdf, e.currentTarget));
  document.getElementById('btn-copiar-mensaje').addEventListener('click', e => copiarTexto(mensaje.value, e.currentTarget));
  document.getElementById('btn-si-enviada')?.addEventListener('click', () => cambiarEstado('enviada'));
  document.getElementById('btn-no-enviada')?.addEventListener('click', () => {
    document.getElementById('vc-preguntar').classList.remove('show');
  });
}

async function cambiarEstado(nuevoEstado) {
  const fd = new FormData();
  fd.set('accion', 'cambiar_estado');
  fd.set('id', ID);
  fd.set('nuevo_estado', nuevoEstado);
  const res = await fetch('../api/cotizacion_detalle.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.ok) {
    mostrarAvisoGenerada = false;
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
