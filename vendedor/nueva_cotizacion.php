<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$u = requireRole('vendedor');

$clientePrellenado      = (int)($_GET['cliente_id'] ?? 0);
$citaPrellenada         = (int)($_GET['cita_id'] ?? 0);
$prospeccionPrellenada  = (int)($_GET['prospeccion_id'] ?? 0);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Nueva cotización</title>
<link rel="icon" type="image/png" href="../assets/img/Favv-Segu.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css<?= assetVer(__DIR__ . '/../assets/css/style.css') ?>">
<link rel="stylesheet" href="../assets/css/vendedor-2026.css<?= assetVer(__DIR__ . '/../assets/css/vendedor-2026.css') ?>">
<style>
.nc-tabla { width:100%; border-collapse:collapse; font-size:.82rem; }
.nc-tabla th { text-align:left; font-size:.7rem; color:var(--v26-ink-soft); text-transform:uppercase; letter-spacing:.02em; padding:4px 6px; }
.nc-tabla td { padding:6px; border-top:1px solid var(--v26-border); vertical-align:middle; }
.nc-quitar { color:var(--v26-red); background:none; border:none; font-size:1.05rem; }
.nc-totales { font-size:.85rem; }
.nc-totales .fila { display:flex; justify-content:space-between; padding:3px 0; }
.nc-totales .total { font-weight:800; font-size:1rem; border-top:1px solid var(--v26-border); margin-top:6px; padding-top:8px; }

/* Estilos: lista tocable en vez de buscador de texto obligatorio */
.nc-estilos-buscador { position:relative; margin-bottom:8px; }
.nc-lista-estilos { max-height:280px; overflow-y:auto; border:1px solid var(--v26-border); border-radius:12px; }
.nc-estilo-item { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:10px 12px; font-size:.83rem; cursor:pointer; border-bottom:1px solid var(--v26-border); }
.nc-estilo-item:last-child { border-bottom:none; }
.nc-estilo-item:active { background:rgba(0,0,0,.04); }
.nc-estilo-item .clave { font-weight:800; }
.nc-estilo-item .nombre { color:var(--v26-ink-soft); font-size:.76rem; }
.nc-estilo-item .agregar { font-size:1.3rem; color:var(--v26-brand-2); flex-shrink:0; }
.nc-estilo-item.en-carrito { background:rgba(22,163,74,.07); }
.nc-estilo-item.en-carrito .agregar { color:var(--v26-green); }
.nc-estilo-item .info { display:flex; align-items:center; gap:10px; min-width:0; }
.nc-foto { width:40px; height:40px; object-fit:contain; border:1px solid var(--v26-border); border-radius:8px; background:#fff; flex:none; }
.nc-foto--vacia { display:flex; align-items:center; justify-content:center; color:var(--v26-ink-soft); font-size:1rem; }
.nc-grupo { position:sticky; top:0; z-index:1; background:var(--v26-surface-solid); padding:6px 12px; font-size:.68rem; font-weight:800; text-transform:uppercase; letter-spacing:.03em; color:var(--v26-ink-soft); border-bottom:1px solid var(--v26-border); }
.nc-dickies { display:inline-block; font-size:.62rem; font-weight:700; padding:1px 7px; border-radius:999px; background:#FEF9C3; color:#854D0E; margin-top:3px; }
.nc-color { margin-top:4px; font-size:.74rem; border:1px solid var(--v26-border); border-radius:8px; padding:3px 6px; max-width:130px; }
.nc-color.falta { border-color:var(--v26-red); }
.nc-color-fijo { font-size:.72rem; color:var(--v26-ink-soft); }
.nc-aviso { font-size:.76rem; font-weight:600; margin-top:8px; }
.nc-aviso:empty { display:none; }
.nc-aviso.ok { color:var(--v26-green); }
.nc-aviso.dickies { color:#B45309; }

/* Stepper de cantidad */
.nc-stepper { display:flex; align-items:center; gap:6px; }
.nc-stepper button { width:28px; height:28px; border-radius:8px; border:1px solid var(--v26-border); background:var(--v26-surface-solid); font-size:1rem; font-weight:800; line-height:1; color:var(--v26-ink); }
.nc-stepper button:active { background:rgba(0,0,0,.06); }
.nc-stepper .cant { min-width:22px; text-align:center; font-weight:800; }
.nc-precio { width:88px; border:1px solid var(--v26-border); border-radius:8px; padding:5px 6px; font-size:.82rem; text-align:right; }

/* Chips de opciones (vigencia, entrega, forma de pago) */
.nc-chips { display:flex; flex-wrap:wrap; gap:8px; }
.nc-chip { border:1px solid var(--v26-border); background:var(--v26-surface-solid); color:var(--v26-ink); border-radius:var(--v26-r-pill); padding:8px 14px; font-size:.8rem; font-weight:700; cursor:pointer; box-shadow:var(--v26-shadow-sm); }
.nc-chip.active { background:var(--v26-brand-grad); color:#fff; border-color:transparent; box-shadow:var(--v26-shadow-brand); }
.nc-chip-otro { flex-basis:100%; }
.nc-otro-wrap { margin-top:8px; }
.nc-otro-wrap.oculto { display:none; }
</style>
</head>
<body class="v26">
  <div class="v26-header">
    <div class="v26-topbar">
      <div class="v26-topbar-left">
        <a href="cotizaciones.php" class="v26-back v26-tip v26-tip--bottom" data-tip="Volver a mis cotizaciones" aria-label="Volver"><i class="bi bi-arrow-left"></i></a>
        <div class="v26-greeting">
          <div class="hi">Ventas</div>
          <div class="name">Nueva cotización</div>
        </div>
      </div>
      <div class="v26-topbar-right">
        <img src="../logo.png" alt="Segurmex" class="v26-logo">
      </div>
    </div>
  </div>

  <div class="v26-wrap">
    <div class="v26-card">
      <form id="form-cotizacion">
        <div class="v26-field">
          <label>Cliente</label>
          <div style="display:flex; align-items:center; gap:8px;">
            <select name="cliente_id" id="select-cliente" class="v26-select" style="flex:1;" required>
              <option value="">Cargando clientes...</option>
            </select>
            <span class="v26-pill" id="badge-etapa-cliente" style="display:none; flex:none;"></span>
          </div>
          <div class="form-text mt-1" style="font-size:.76rem;">¿No está en la lista? <a href="nuevo_cliente.php">Regístralo primero</a>.</div>
        </div>

        <div class="v26-fila-2">
          <div class="v26-field">
            <label>Tipo de lista</label>
            <div class="v26-seg" id="seg-tipo-lista">
              <button type="button" class="v26-seg-btn active" data-valor="industria">Industria</button>
              <button type="button" class="v26-seg-btn" data-valor="distribuidor">Distribuidor</button>
            </div>
          </div>

          <div class="v26-field">
            <label>Pronto pago</label>
            <div class="v26-seg" id="seg-pronto-pago">
              <button type="button" class="v26-seg-btn" data-valor="0">No</button>
              <button type="button" class="v26-seg-btn" data-valor="1">Sí aplica</button>
            </div>
          </div>
        </div>

        <div class="v26-field">
          <label>Modelos</label>
          <div class="nc-estilos-buscador">
            <div class="v26-search mb-2">
              <i class="bi bi-search"></i>
              <input type="text" id="input-buscar-estilo" class="v26-input" placeholder="Filtrar por modelo, línea o marca...">
            </div>
            <div id="nc-lista-estilos" class="nc-lista-estilos">
              <div class="v26-skel"></div>
              <div class="v26-skel"></div>
            </div>
          </div>

          <div id="nc-seleccionados" class="mt-3" style="display:none;">
            <label style="display:block; font-size:.78rem; font-weight:700; color:var(--v26-ink-soft); margin-bottom:6px; text-transform:uppercase; letter-spacing:.03em;">Agregados a la cotización</label>
            <div style="overflow-x:auto;">
              <table class="nc-tabla">
                <thead><tr><th>Modelo</th><th>Cant.</th><th>Precio</th><th>Importe</th><th></th></tr></thead>
                <tbody id="tbody-renglones"></tbody>
              </table>
            </div>
            <div class="nc-aviso" id="aviso-mayoreo"></div>
            <div class="nc-aviso dickies" id="aviso-dickies"></div>
          </div>
        </div>

        <div class="v26-card mt-2 nc-totales" style="background:var(--v26-surface-solid);">
          <div class="fila"><span>Subtotal</span><span id="tot-subtotal">$0.00</span></div>
          <div class="fila"><span>IVA</span><span id="tot-iva">$0.00</span></div>
          <div class="fila total"><span>Total</span><span id="tot-total">$0.00</span></div>
        </div>

        <div class="v26-field mt-2">
          <label>Vigencia</label>
          <div class="nc-chips" id="chips-vigencia">
            <button type="button" class="nc-chip" data-valor="7">7 días</button>
            <button type="button" class="nc-chip" data-valor="15">15 días</button>
            <button type="button" class="nc-chip" data-valor="30">30 días</button>
            <button type="button" class="nc-chip" data-valor="45">45 días</button>
          </div>
          <input type="hidden" name="vigencia_dias" id="input-vigencia" value="15">
        </div>

        <div class="v26-field">
          <label>Tiempo de entrega</label>
          <div class="nc-chips" id="chips-entrega">
            <button type="button" class="nc-chip" data-valor="5 días hábiles">5 días hábiles</button>
            <button type="button" class="nc-chip" data-valor="10 días hábiles">10 días hábiles</button>
            <button type="button" class="nc-chip" data-valor="15 días hábiles">15 días hábiles</button>
            <button type="button" class="nc-chip" data-valor="20 días hábiles">20 días hábiles</button>
            <button type="button" class="nc-chip" data-valor="A convenir">A convenir</button>
            <button type="button" class="nc-chip nc-chip-otro" data-otro="1">Otro (escribir)</button>
          </div>
          <div class="nc-otro-wrap oculto" id="otro-entrega-wrap">
            <input type="text" id="input-entrega-otro" class="v26-input" placeholder="Escribe el tiempo de entrega...">
          </div>
          <input type="hidden" name="tiempo_entrega" id="input-tiempo-entrega" value="">
        </div>

        <div class="v26-field">
          <label>Forma de pago</label>
          <div class="nc-chips" id="chips-forma-pago">
            <button type="button" class="nc-chip" data-valor="Contado">Contado</button>
            <button type="button" class="nc-chip" data-valor="50% anticipo, 50% contra entrega">50% anticipo, 50% resto</button>
            <button type="button" class="nc-chip" data-valor="30 días de crédito">30 días de crédito</button>
            <button type="button" class="nc-chip" data-valor="Transferencia bancaria">Transferencia bancaria</button>
            <button type="button" class="nc-chip nc-chip-otro" data-otro="1">Otro (escribir)</button>
          </div>
          <div class="nc-otro-wrap oculto" id="otro-forma-wrap">
            <input type="text" id="input-forma-otro" class="v26-input" placeholder="Escribe la forma de pago...">
          </div>
          <input type="hidden" name="forma_pago" id="input-forma-pago" value="">
        </div>

        <div class="v26-field">
          <label>Contacto en el cliente</label>
          <input type="text" name="cliente_contacto" class="v26-input">
        </div>
        <div class="v26-field">
          <label>Correo del cliente</label>
          <input type="email" name="cliente_email" class="v26-input">
        </div>
        <div class="v26-field">
          <label>Notas</label>
          <textarea name="notas" class="v26-textarea" rows="2"></textarea>
        </div>

        <input type="hidden" name="visitas_cita_id" value="<?= $citaPrellenada ?: '' ?>">
        <input type="hidden" name="visitas_prospeccion_id" value="<?= $prospeccionPrellenada ?: '' ?>">
        <input type="hidden" name="renglones" id="input-renglones" value="[]">

        <div id="msg-cotizacion"></div>
        <button type="submit" class="v26-btn v26-btn-primary v26-btn-block">Guardar cotización</button>
      </form>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/vendedor.js<?= assetVer(__DIR__ . '/../assets/js/vendedor.js') ?>"></script>
<script>
iniciarTrackingPeriodico();

const CLIENTE_PRELLENADO = <?= (int)$clientePrellenado ?>;
let CFG = { descuento_distribuidor: 0.15, descuento_pronto_pago: 0.05, descuento_mayoreo: 0.10, minimo_pares_mayoreo: 16, minimo_pares_dickies: 16, tasa_iva: 0.16, vigencia_default_dias: 15 };
// Fotos del catálogo: viven en el ERP (mismo servidor), no se duplican aquí.
const FOTOS_URL = '/erp/assets/img/cotizador/';
// Renglón: {it (item del catálogo), cantidad, precio_final, auto, color}.
// auto = el precio lo pone la lista (true) o lo tecleó el vendedor (false).
let renglones = [];
let tipoLista = 'industria';
let prontoPago = false;

function money(n) { return '$' + Number(n || 0).toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
function escHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

// Mismas reglas que erp/cotizacion/_renglones_js.php; el servidor vuelve a
// validar todo al guardar (resolverRenglonesCotizacionErp).
function esDickies(it) { return !!it && it.marca === 'DICKIES'; }
function precioLista(it, tl) { return tl === 'distribuidor' ? it.precio_distribuidor : it.precio_industria; }
function paresPorMarca() {
  const p = { total: 0, segurmex: 0, dickies: 0 };
  renglones.forEach(r => {
    const n = parseInt(r.cantidad) || 0;
    p.total += n;
    if (esDickies(r.it)) p.dickies += n; else p.segurmex += n;
  });
  return p;
}
function aplicaMayoreo() { return paresPorMarca().segurmex >= CFG.minimo_pares_mayoreo; }
function calcularMinimo(it, base, pp) {
  if (esDickies(it)) return base; // Dickies: precio fijo, sin descuentos
  let f = 1;
  if (pp) f *= (1 - CFG.descuento_pronto_pago);
  if (aplicaMayoreo()) f *= (1 - CFG.descuento_mayoreo);
  return Math.round(base * f * 100) / 100;
}
function fotoHTML(it) {
  return it.foto
    ? `<img src="${FOTOS_URL}${encodeURIComponent(it.foto)}" alt="" loading="lazy" class="nc-foto">`
    : '<span class="nc-foto nc-foto--vacia"><i class="bi bi-image"></i></span>';
}

async function cargarConfig() {
  const res = await fetch('../api/config_cotizador.php');
  const data = await res.json();
  if (data.ok) CFG = { ...CFG, ...data.config };
  const vDefault = CFG.vigencia_default_dias || 15;
  document.getElementById('input-vigencia').value = vDefault;
  document.querySelectorAll('#chips-vigencia .nc-chip').forEach(b => {
    b.classList.toggle('active', b.dataset.valor === String(vDefault));
  });
}

function actualizarBadgeEtapa() {
  const sel = document.getElementById('select-cliente');
  const badge = document.getElementById('badge-etapa-cliente');
  const op = sel.options[sel.selectedIndex];
  const etapa = op ? op.dataset.etapa : '';
  if (!etapa) { badge.style.display = 'none'; return; }
  const esCliente = etapa === 'convertido';
  badge.className = 'v26-pill ' + (esCliente ? 'v26-pill--etapa-convertido' : 'v26-pill--etapa-prospecto_agregado');
  badge.textContent = esCliente ? 'Cliente' : 'Prospecto';
  badge.style.display = 'inline-flex';
}
document.getElementById('select-cliente').addEventListener('change', actualizarBadgeEtapa);

async function cargarSelectClientes() {
  const sel = document.getElementById('select-cliente');
  const res = await fetch('../api/clientes.php');
  const data = await res.json();
  if (!data.ok || data.clientes.length === 0) {
    sel.innerHTML = '<option value="">No tienes clientes registrados aún</option>';
    return;
  }
  sel.innerHTML = '<option value="">Selecciona un cliente</option>' +
    data.clientes.map(c => `<option value="${c.id}" data-etapa="${escHtml(c.etapa || '')}" ${CLIENTE_PRELLENADO === c.id ? 'selected' : ''}>${escHtml(c.nombre)}</option>`).join('');
  actualizarBadgeEtapa();
}

// Recalcula lista, mínimo y precio de cada renglón (como recalcTodo() del
// ERP): el precio arranca en el de lista; si el vendedor lo tecleó y quedó
// por debajo del mínimo (p. ej. al cambiar de lista), se sube al mínimo.
function recalcularTodo() {
  renglones.forEach(r => {
    r.base = precioLista(r.it, tipoLista);
    r.minimo = calcularMinimo(r.it, r.base, prontoPago);
    if (r.auto || r.precio_final == null) r.precio_final = r.base;
    else if (r.precio_final < r.minimo) r.precio_final = r.minimo;
  });
  renderRenglones();
  marcarEstilosEnCarrito();
  recalcularSoloTotales();
}

function renderRenglones() {
  const cont = document.getElementById('nc-seleccionados');
  const tbody = document.getElementById('tbody-renglones');
  cont.style.display = renglones.length ? 'block' : 'none';
  tbody.innerHTML = renglones.map((r, i) => {
    const it = r.it;
    let extra = '';
    if (it.colores.length > 1) {
      extra = `<br><select class="nc-color sel-color ${r.color ? '' : 'falta'}" data-i="${i}"><option value="">Color…</option>`
            + it.colores.map(c => `<option value="${escHtml(c)}" ${c === r.color ? 'selected' : ''}>${escHtml(c)}</option>`).join('') + '</select>';
    } else if (it.colores.length === 1) {
      extra = `<br><span class="nc-color-fijo">${escHtml(it.colores[0])}</span>`;
    }
    if (esDickies(it)) extra += '<br><span class="nc-dickies">Dickies · precio fijo</span>';
    return `
    <tr>
      <td><strong>${escHtml(it.clave)}</strong><br><span class="text-muted" style="font-size:.72rem;">${escHtml(it.nombre)}</span>${extra}</td>
      <td>
        <div class="nc-stepper">
          <button type="button" class="nc-menos" data-i="${i}">−</button>
          <span class="cant">${r.cantidad}</span>
          <button type="button" class="nc-mas" data-i="${i}">+</button>
        </div>
      </td>
      <td><input type="number" step="0.01" min="${r.minimo}" value="${r.precio_final}" data-i="${i}" class="nc-precio input-precio" title="Lista: ${money(r.base)} · Mínimo: ${money(r.minimo)}"></td>
      <td class="nc-importe" data-i="${i}">${money(r.cantidad * r.precio_final)}</td>
      <td><button type="button" class="nc-quitar" data-i="${i}"><i class="bi bi-x-circle"></i></button></td>
    </tr>`;
  }).join('');

  tbody.querySelectorAll('.nc-menos').forEach(btn => btn.addEventListener('click', e => {
    const i = +e.currentTarget.dataset.i;
    renglones[i].cantidad = Math.max(1, (parseInt(renglones[i].cantidad) || 1) - 1);
    recalcularTodo();
  }));
  tbody.querySelectorAll('.nc-mas').forEach(btn => btn.addEventListener('click', e => {
    const i = +e.currentTarget.dataset.i;
    renglones[i].cantidad = (parseInt(renglones[i].cantidad) || 1) + 1;
    recalcularTodo();
  }));
  tbody.querySelectorAll('.input-precio').forEach(inp => {
    inp.addEventListener('input', e => {
      const r = renglones[+e.target.dataset.i];
      r.auto = e.target.value.trim() === '';
      r.precio_final = Math.max(0, parseFloat(e.target.value) || 0);
      recalcularSoloTotales();
    });
    // Al salir del campo: vacío = vuelve al de lista; abajo del mínimo = sube al mínimo.
    inp.addEventListener('change', () => recalcularTodo());
  });
  tbody.querySelectorAll('.sel-color').forEach(sel => sel.addEventListener('change', e => {
    renglones[+e.target.dataset.i].color = e.target.value;
    e.target.classList.toggle('falta', !e.target.value);
    recalcularSoloTotales();
  }));
  tbody.querySelectorAll('.nc-quitar').forEach(btn => btn.addEventListener('click', e => {
    renglones.splice(+e.currentTarget.dataset.i, 1);
    recalcularTodo();
  }));
}

// Totales, avisos y lo que se manda al guardar. No toca los precios: el
// vendedor puede ir tecleando sin que se le muevan a media escritura.
function recalcularSoloTotales() {
  let subtotal = 0;
  renglones.forEach((r, i) => {
    r.importe = Math.round(r.cantidad * r.precio_final * 100) / 100;
    subtotal += r.importe;
    const celda = document.querySelector(`.nc-importe[data-i="${i}"]`);
    if (celda) celda.textContent = money(r.importe);
  });
  const iva = Math.round(subtotal * CFG.tasa_iva * 100) / 100;
  const total = Math.round((subtotal + iva) * 100) / 100;
  document.getElementById('tot-subtotal').textContent = money(subtotal);
  document.getElementById('tot-iva').textContent = money(iva);
  document.getElementById('tot-total').textContent = money(total);
  pintarAvisos();
  document.getElementById('input-renglones').value = JSON.stringify(
    renglones.map(r => ({ item: r.it.item, cantidad: r.cantidad, precio_final: r.precio_final, color: r.color || '' }))
  );
}

function pintarAvisos() {
  const p = paresPorMarca();
  const avM = document.getElementById('aviso-mayoreo');
  const avD = document.getElementById('aviso-dickies');
  // Solo Dickies (sin pares SEGURMEX): el mayoreo no aplica, el aviso sobra.
  if (!renglones.length || (p.segurmex === 0 && p.dickies > 0)) {
    avM.textContent = '';
  } else if (p.segurmex >= CFG.minimo_pares_mayoreo) {
    avM.textContent = `Mayoreo aplicado (${p.segurmex} pares SEGURMEX, mínimo ${CFG.minimo_pares_mayoreo}).`;
    avM.classList.add('ok');
  } else {
    avM.textContent = `Faltan ${CFG.minimo_pares_mayoreo - p.segurmex} pares SEGURMEX para el mayoreo (mínimo ${CFG.minimo_pares_mayoreo}; Dickies no cuenta).`;
    avM.classList.remove('ok');
  }
  avD.textContent = (p.dickies > 0 && p.dickies < CFG.minimo_pares_dickies)
    ? `Dickies: pedido mínimo de ${CFG.minimo_pares_dickies} pares (llevas ${p.dickies}). Puedes guardar la cotización, pero avísale al cliente.`
    : '';
}

// --- Catálogo: el mismo del cotizador del ERP, completo y agrupado por marca · línea ---
let catalogo = [];

function renderListaEstilos(lista) {
  const cont = document.getElementById('nc-lista-estilos');
  if (!lista.length) {
    cont.innerHTML = '<div class="v26-empty" style="padding:24px 0;"><p style="margin:0;font-size:.82rem;">Sin resultados.</p></div>';
    return;
  }
  let grupo = null;
  cont.innerHTML = lista.map(it => {
    const enCarrito = renglones.some(r => r.it.item === it.item);
    let html = '';
    if (it.grupo !== grupo) { grupo = it.grupo; html += `<div class="nc-grupo">${escHtml(grupo)}</div>`; }
    return html + `
    <div class="nc-estilo-item ${enCarrito ? 'en-carrito' : ''}" data-item="${escHtml(it.item)}">
      <div class="info">
        ${fotoHTML(it)}
        <div style="min-width:0;">
          <div class="clave">${escHtml(it.clave)}</div>
          <div class="nombre">${escHtml(it.nombre)}</div>
        </div>
      </div>
      <div class="agregar"><i class="bi ${enCarrito ? 'bi-check-circle-fill' : 'bi-plus-circle'}"></i></div>
    </div>`;
  }).join('');

  cont.querySelectorAll('.nc-estilo-item').forEach(el => el.addEventListener('click', () => {
    const it = catalogo.find(c => c.item === el.dataset.item);
    if (!it) return;
    // Un modelo con varios colores agrega otro renglón (puede ir en otro color);
    // los demás solo suman un par al renglón que ya está.
    const existente = it.colores.length > 1 ? null : renglones.find(r => r.it.item === it.item);
    if (existente) {
      existente.cantidad++;
    } else {
      renglones.push({ it, cantidad: 1, precio_final: null, auto: true, color: it.colores.length === 1 ? it.colores[0] : '' });
    }
    recalcularTodo();
  }));
}

function marcarEstilosEnCarrito() {
  document.querySelectorAll('#nc-lista-estilos .nc-estilo-item').forEach(el => {
    const en = renglones.some(r => r.it.item === el.dataset.item);
    el.classList.toggle('en-carrito', en);
    const icon = el.querySelector('.agregar i');
    if (icon) icon.className = 'bi ' + (en ? 'bi-check-circle-fill' : 'bi-plus-circle');
  });
}

function filtrarCatalogo(q) {
  q = q.toLowerCase();
  renderListaEstilos(!q ? catalogo : catalogo.filter(it =>
    (it.clave + ' ' + it.nombre + ' ' + it.grupo).toLowerCase().includes(q)
  ));
}

async function cargarEstilos() {
  const res = await fetch('../api/estilos_erp.php');
  const data = await res.json();
  catalogo = data.ok ? data.catalogo : [];
  filtrarCatalogo(document.getElementById('input-buscar-estilo').value.trim());
}

document.getElementById('input-buscar-estilo').addEventListener('input', (e) => {
  filtrarCatalogo(e.target.value.trim());
});

// --- Segmentados: tipo de lista / pronto pago ---
document.querySelectorAll('#seg-tipo-lista .v26-seg-btn').forEach(btn => btn.addEventListener('click', () => {
  document.querySelectorAll('#seg-tipo-lista .v26-seg-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  tipoLista = btn.dataset.valor;
  recalcularTodo();
}));
document.querySelectorAll('#seg-pronto-pago .v26-seg-btn').forEach(btn => btn.addEventListener('click', () => {
  document.querySelectorAll('#seg-pronto-pago .v26-seg-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  prontoPago = btn.dataset.valor === '1';
  recalcularTodo();
}));
// "No" es el estado inicial por defecto.
document.querySelector('#seg-pronto-pago .v26-seg-btn[data-valor="0"]').classList.add('active');

// --- Chips: vigencia ---
document.querySelectorAll('#chips-vigencia .nc-chip').forEach(chip => chip.addEventListener('click', () => {
  document.querySelectorAll('#chips-vigencia .nc-chip').forEach(c => c.classList.remove('active'));
  chip.classList.add('active');
  document.getElementById('input-vigencia').value = chip.dataset.valor;
}));

// --- Chips con opción "Otro" (entrega, forma de pago) ---
function wireChipsConOtro(gridId, otroWrapId, otroInputId, hiddenId) {
  const grid = document.getElementById(gridId);
  const otroWrap = document.getElementById(otroWrapId);
  const otroInput = document.getElementById(otroInputId);
  const hidden = document.getElementById(hiddenId);

  grid.querySelectorAll('.nc-chip').forEach(chip => chip.addEventListener('click', () => {
    grid.querySelectorAll('.nc-chip').forEach(c => c.classList.remove('active'));
    chip.classList.add('active');
    if (chip.dataset.otro) {
      otroWrap.classList.remove('oculto');
      hidden.value = otroInput.value.trim();
      otroInput.focus();
    } else {
      otroWrap.classList.add('oculto');
      hidden.value = chip.dataset.valor;
    }
  }));
  otroInput.addEventListener('input', () => { hidden.value = otroInput.value.trim(); });
}
wireChipsConOtro('chips-entrega', 'otro-entrega-wrap', 'input-entrega-otro', 'input-tiempo-entrega');
wireChipsConOtro('chips-forma-pago', 'otro-forma-wrap', 'input-forma-otro', 'input-forma-pago');

document.getElementById('form-cotizacion').addEventListener('submit', async (e) => {
  e.preventDefault();
  const msg = document.getElementById('msg-cotizacion');
  msg.innerHTML = '';
  if (!renglones.length) {
    msg.innerHTML = '<div class="alert alert-danger py-2">Agrega al menos un modelo.</div>';
    return;
  }
  const sinColor = renglones.filter(r => r.it.colores.length > 1 && !r.color).map(r => r.it.clave);
  if (sinColor.length) {
    msg.innerHTML = `<div class="alert alert-danger py-2">Elige el color de: ${escHtml(sinColor.join(', '))}.</div>`;
    return;
  }
  recalcularTodo();
  const fd = new FormData(e.target);
  fd.set('tipo_lista', tipoLista);
  fd.set('pronto_pago', prontoPago ? '1' : '0');
  const res = await fetch('../api/cotizaciones.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.ok) {
    window.location.href = 'cotizaciones.php';
  } else {
    msg.innerHTML = `<div class="alert alert-danger py-2">${escHtml(data.error)}</div>`;
  }
});

cargarConfig();
cargarSelectClientes();
cargarEstilos();
</script>
</body>
</html>
