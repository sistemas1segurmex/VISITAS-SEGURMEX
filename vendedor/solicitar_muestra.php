<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$u = requireRole('vendedor');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Solicitar muestra</title>
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
        <a href="muestras.php" class="v26-back v26-tip v26-tip--bottom" data-tip="Volver a mis muestras" aria-label="Volver"><i class="bi bi-arrow-left"></i></a>
        <div class="v26-greeting">
          <div class="hi">Ventas</div>
          <div class="name">Solicitar muestra</div>
        </div>
      </div>
      <div class="v26-topbar-right">
        <img src="../logo.png" alt="Segurmex" class="v26-logo">
        <a href="../logout.php" class="v26-icon-btn v26-tip v26-tip--bottom" data-tip="Cerrar sesión" aria-label="Salir"><i class="bi bi-box-arrow-right"></i></a>
      </div>
    </div>
  </div>

  <div class="v26-wrap mu-wrap--sol">
    <div id="pantalla-exito" class="d-none mu-exito">
      <div class="mu-exito-ico"><i class="bi bi-check-lg"></i></div>
      <h1 class="mu-exito-titulo">¡Solicitud enviada!</h1>
      <div class="mu-exito-folio" id="folio-exito"></div>
      <p class="mu-exito-txt" id="texto-exito">Tu solicitud ya está con el equipo de Segurmex.</p>
      <ol class="mu-exito-pasos">
        <li class="hecho"><span><i class="bi bi-send-fill"></i></span><div><strong>Enviada</strong><small>Ya la recibió el equipo de Segurmex</small></div></li>
        <li><span><i class="bi bi-box-seam"></i></span><div><strong>En preparación</strong><small>Te avisamos aquí y por correo</small></div></li>
        <li><span><i class="bi bi-truck"></i></span><div><strong>Embarcada</strong><small>Con la paquetería y la guía</small></div></li>
      </ol>
      <a href="muestras.php" class="v26-btn v26-btn-primary v26-btn-block"><i class="bi bi-box-seam"></i> Ver mis muestras</a>
      <a href="#" class="v26-btn v26-btn-ghost v26-btn-block mt-2 d-none" id="btn-pdf-exito" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf"></i> Ver PDF</a>
      <a href="solicitar_muestra.php" class="v26-btn v26-btn-ghost v26-btn-block mt-2" id="btn-pedir-otra"><i class="bi bi-plus-lg"></i> Pedir otra</a>
    </div>

    <div id="pantalla-form">
      <section class="mu-hero mu-hero--sol">
        <div class="mu-hero-txt">
          <div class="mu-hero-eyebrow"><i class="bi bi-box-seam"></i> Nueva solicitud</div>
          <h1 class="mu-hero-titulo">¿Qué muestra necesita tu cliente?</h1>
          <p class="mu-hero-sub">Llena los 3 pasos. Puedes pedir varios estilos y tallas en la misma solicitud. La recibe el equipo de Segurmex y te avisamos cuando esté en preparación y cuando se embarque.</p>
        </div>
        <div class="mu-cupo">
          <div class="mu-cupo-txt"><span id="cupo-n">6</span> de <span id="cupo-tope">6</span><small id="cupo-sub">pares por cliente al mes</small></div>
          <div class="mu-cupo-barra"><span id="cupo-barra" style="width:0%"></span></div>
        </div>
      </section>

      <div id="msg-muestra"></div>
      <div id="aviso-tope" class="d-none"></div>

      <div class="mu-sol-grid">
        <form id="form-muestra" class="mu-sol-form">
          <section class="v26-card mu-paso">
            <div class="mu-paso-head"><span class="mu-paso-n">1</span><div><strong>¿Para quién es?</strong><small>Uno de tus clientes o prospectos</small></div></div>
            <div class="v26-field mb-0">
              <label>Cliente o prospecto</label>
              <select id="id_cliente" class="v26-select" required>
                <option value="">Cargando tus clientes y prospectos...</option>
              </select>
            </div>
            <!-- Contacto del cliente: sale de su ficha; si le falta algo se pide aquí y se guarda también en la ficha -->
            <div id="bloque-contacto" class="d-none mt-3">
              <div id="contacto-lectura" class="mu-nota mb-0"></div>
              <div id="contacto-faltan" class="d-none">
                <div class="mu-nota mb-2"><i class="bi bi-info-circle"></i> A la ficha de este cliente le falta este dato. Escríbelo y lo guardamos también en su ficha.</div>
                <div class="v26-field" id="campo-contacto-nombre">
                  <label>Nombre de contacto</label>
                  <input type="text" id="contacto_nombre" class="v26-input" maxlength="150" placeholder="Ej. Antonio de León" autocomplete="off">
                </div>
                <div class="v26-field mb-0" id="campo-contacto-tel">
                  <label>Teléfono de contacto</label>
                  <input type="tel" id="contacto_telefono" class="v26-input" maxlength="20" inputmode="tel" placeholder="Ej. 477 123 4567" autocomplete="off">
                </div>
              </div>
            </div>
          </section>

          <section class="v26-card mu-paso">
            <div class="mu-paso-head"><span class="mu-paso-n">2</span><div><strong>¿Qué muestras?</strong><small>Uno o varios estilos, cada uno con sus tallas y pares</small></div></div>
            <div id="mu-estilos"></div>
            <button type="button" class="v26-btn v26-btn-ghost v26-btn-block" id="btn-agregar-estilo"><i class="bi bi-plus-lg"></i> Agregar otro estilo</button>
            <div class="mu-total-pares" id="mu-total-pares"></div>
            <div class="v26-field mt-3">
              <label>Motivo de la muestra</label>
              <textarea id="motivo" class="v26-textarea" rows="2" maxlength="500" required placeholder="Ej. Prueba de uso en planta, licitación, presentación al área de compras…"></textarea>
            </div>
            <div class="v26-field mb-0">
              <label>Notas para planta <span class="mu-opc">(opcional)</span></label>
              <textarea id="notas_planta" class="v26-textarea" rows="3" maxlength="2000" placeholder="Lo que deba saber quien la prepara: contexto del cliente, competencia, volumen del proyecto…"></textarea>
            </div>
          </section>

          <section class="v26-card mu-paso">
            <div class="mu-paso-head"><span class="mu-paso-n">3</span><div><strong>¿Cuándo y a dónde?</strong><small>La fecha que le prometiste y dónde entregarla</small></div></div>
            <div class="row g-2">
              <div class="col-sm-6 v26-field">
                <label>Fecha promesa <span class="mu-opc">(opcional)</span></label>
                <input type="date" id="fecha_promesa" class="v26-input">
              </div>
              <div class="col-sm-6 v26-field">
                <label>Tiempo de prueba <span class="mu-opc">(días, opcional)</span></label>
                <input type="number" id="tiempo_prueba_dias" class="v26-input" min="1" max="365" step="1" inputmode="numeric" placeholder="Ej. 15">
              </div>
            </div>
            <div class="v26-field">
              <label>¿A quién se entrega?</label>
              <div class="mu-tipos" id="seg-entrega">
                <button type="button" class="v26-seg-btn mu-tipo active" data-entrega="cliente">
                  <i class="bi bi-building"></i><strong>Al cliente</strong><small>En la dirección del cliente</small>
                </button>
                <button type="button" class="v26-seg-btn mu-tipo" data-entrega="vendedor">
                  <i class="bi bi-person"></i><strong>A mí</strong><small>Mi domicilio o una paquetería</small>
                </button>
              </div>
            </div>
            <div class="v26-field mb-0">
              <label>Dirección de entrega</label>
              <textarea id="destino_direccion" class="v26-textarea" rows="3" required placeholder="Calle, número, colonia, ciudad..."></textarea>
              <div class="mu-nota mt-2 mb-0 d-none" id="nota-direccion"><i class="bi bi-magic"></i> Usamos la dirección del cliente. Cámbiala si se entrega en otro lado.</div>
            </div>
          </section>

          <div class="mu-sol-enviar">
            <button type="submit" class="v26-btn v26-btn-primary v26-btn-block" id="btn-enviar-muestra">Enviar solicitud</button>
          </div>
        </form>

        <aside class="mu-resumen v26-card">
          <div class="mu-sec-titulo"><i class="bi bi-receipt"></i> Resumen</div>
          <dl class="mu-resumen-dl">
            <dt>Cliente</dt><dd id="r-cliente">—</dd>
            <dt>Contacto</dt><dd id="r-contacto">—</dd>
            <dt>Muestras</dt><dd id="r-estilos">—</dd>
            <dt>Total</dt><dd id="r-total">—</dd>
            <dt>Motivo</dt><dd id="r-motivo">—</dd>
            <dt>Promesa</dt><dd id="r-fecha">—</dd>
            <dt>Prueba</dt><dd id="r-prueba">—</dd>
            <dt>Entregar a</dt><dd id="r-entrega">Al cliente</dd>
            <dt>Dirección</dt><dd id="r-dir">—</dd>
          </dl>
          <p class="mu-nota mb-0"><i class="bi bi-info-circle"></i> Al enviarla le llega al equipo de Segurmex.</p>
        </aside>
      </div>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/avisos.js<?= assetVer(__DIR__ . '/../assets/js/avisos.js') ?>"></script>
<script>
const CATEGORIAS_ADENDUM = { casco: 'Casco', suela: 'Suela', piel: 'Piel', forro: 'Forro' };
function escHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function paresTxt(n) { return n + (n === 1 ? ' par' : ' pares'); }

// ---- Estado ----
// Una solicitud puede llevar varios estilos y cada estilo varias tallas
// (22-oct-2026, igual que el cotizador anterior). Tope: TOPE_PARES pares por
// cliente al mes, sumando todas sus solicitudes (las canceladas no cuentan).
// Ver api/muestra_solicitar.php e includes/muestras.php.
let clientesCat = [];
let estilosCat = [];
let ATRIBUTOS = {};
let TOPE_PARES = 6, MAX_ESTILOS = 6, MAX_TALLAS = 6;
let direccionAutollenada = null; // la que pusimos sola, para no pisar lo que escriba el vendedor
let uidSig = 1;
// [{uid, item, color, tipo, cambios:{casco:{on,txt}...}, otros:[{nombre,txt}], tallas:[{talla,pares,nota}]}]
let lineas = [];
let eligiendo = null; // uid de la tarjeta con el catálogo abierto

function nuevaLinea() {
  const cambios = {};
  Object.keys(CATEGORIAS_ADENDUM).forEach(k => { cambios[k] = { on: false, txt: '' }; });
  return { uid: uidSig++, item: '', color: '', tipo: 'identico', cambios, otros: [], tallas: [{ talla: '', pares: 1, nota: '' }] };
}
function lineaPorUid(uid) { return lineas.find(l => l.uid === Number(uid)) || null; }
function estiloDe(l) { return l && l.item ? estilosCat.find(e => String(e.id) === l.item) || null : null; }
function totalPares() { return lineas.reduce((s, l) => s + l.tallas.reduce((a, t) => a + (parseInt(t.pares, 10) || 0), 0), 0); }
function clienteElegido() {
  return clientesCat.find(x => String(x.id) === document.getElementById('id_cliente').value) || null;
}
function paresDisponiblesCliente() {
  const c = clienteElegido();
  return c ? Math.max(0, TOPE_PARES - (c.pares_muestra_mes || 0)) : TOPE_PARES;
}

// ---- Cupo del cliente (arriba) y total de pares ----
function pintarCupo() {
  const c = clienteElegido();
  const disp = paresDisponiblesCliente();
  const total = totalPares();
  document.getElementById('cupo-tope').textContent = TOPE_PARES;
  document.getElementById('cupo-n').textContent = c ? disp : TOPE_PARES;
  document.getElementById('cupo-sub').textContent = c ? 'pares disponibles este mes para este cliente' : 'pares por cliente al mes';
  document.getElementById('cupo-barra').style.width = Math.round(((c ? disp : TOPE_PARES) / TOPE_PARES) * 100) + '%';
  document.querySelector('.mu-cupo').classList.toggle('agotado', !!c && disp === 0);

  const caja = document.getElementById('aviso-tope');
  const tot = document.getElementById('mu-total-pares');
  let aviso = '';
  if (c && disp === 0) {
    aviso = `<i class="bi bi-exclamation-triangle"></i> <strong>${escHtml(c.nombre)}</strong> ya tiene ${c.pares_muestra_mes} de ${TOPE_PARES} pares de muestra este mes. Podrás pedirle más a partir del día 1 del próximo mes.`;
  } else if (total > disp) {
    const sobran = total - disp;
    aviso = c
      ? `<i class="bi bi-exclamation-triangle"></i> A <strong>${escHtml(c.nombre)}</strong> le ${disp === 1 ? 'queda 1 par' : 'quedan ' + disp + ' pares'} de muestra este mes y llevas ${paresTxt(total)}. Quita ${paresTxt(sobran)}.`
      : `<i class="bi bi-exclamation-triangle"></i> El máximo es de ${TOPE_PARES} pares por cliente al mes y llevas ${paresTxt(total)}. Quita ${paresTxt(sobran)}.`;
  }
  caja.innerHTML = aviso ? `<div class="alert alert-warning py-2 small">${aviso}</div>` : '';
  caja.classList.toggle('d-none', !aviso);
  tot.innerHTML = `<span>Total de la solicitud</span><strong>${paresTxt(total)}</strong>`
    + (!c ? `<small>Máximo ${TOPE_PARES} pares por cliente al mes</small>`
       : total <= disp ? `<small>Este cliente tiene ${paresTxt(disp)} disponibles este mes. Con esta solicitud le ${disp - total === 1 ? 'queda 1 par' : 'quedan ' + (disp - total) + ' pares'}.</small>`
       : `<small>Este cliente solo tiene ${paresTxt(disp)} disponibles este mes.</small>`);
  tot.classList.toggle('excede', total > disp);
  document.getElementById('btn-enviar-muestra').disabled = (c && disp === 0) || total > disp;
}

// ---- Resumen en vivo ----
function textoSel(sel) { return sel.value ? sel.options[sel.selectedIndex].text : '—'; }
function entregaElegida() { return document.querySelector('#seg-entrega .v26-seg-btn.active').dataset.entrega; }
function pintarResumen() {
  const val = (id) => document.getElementById(id).value.trim();
  document.getElementById('r-cliente').textContent = textoSel(document.getElementById('id_cliente'));
  const cr = clienteElegido();
  const nomC = cr ? ((cr.nombre_contacto || '').trim() || val('contacto_nombre')) : '';
  const telC = cr ? ((cr.telefono || '').trim() || val('contacto_telefono')) : '';
  document.getElementById('r-contacto').textContent = [nomC, telC].filter(Boolean).join(' · ') || '—';
  const conEstilo = lineas.filter(l => l.item);
  document.getElementById('r-estilos').innerHTML = conEstilo.length ? conEstilo.map(l => {
    const e = estiloDe(l);
    const tallas = l.tallas.filter(t => t.talla).map(t => `${escHtml(t.talla)} × ${parseInt(t.pares, 10) || 0}`).join(', ');
    return `<div class="mu-r-est"><b>${escHtml(e ? e.clave : '')}</b>${l.color ? ' · ' + escHtml(l.color) : ''}${l.tipo === 'variante' ? ' · Variante' : ''}`
      + `<small>${tallas ? 'Tallas: ' + tallas : 'Sin talla elegida'}</small></div>`;
  }).join('') : '—';
  document.getElementById('r-total').textContent = paresTxt(totalPares());
  document.getElementById('r-motivo').textContent = val('motivo') || '—';
  const tp = parseInt(val('tiempo_prueba_dias'), 10);
  document.getElementById('r-prueba').textContent = tp > 0 ? tp + (tp === 1 ? ' día' : ' días') : '—';
  const f = val('fecha_promesa');
  document.getElementById('r-fecha').textContent = f ? new Date(f + 'T12:00:00').toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';
  document.getElementById('r-entrega').textContent = entregaElegida() === 'vendedor' ? 'A mí (vendedor)' : 'Al cliente';
  document.getElementById('r-dir').textContent = val('destino_direccion') || '—';
  pintarCupo();
}

// Contacto del cliente: si la ficha tiene los dos datos, solo se muestran;
// si le falta alguno, se pide ese (obligatorio) y el servidor lo guarda
// también en la ficha.
function pintarContacto() {
  const c = clienteElegido();
  const bloque = document.getElementById('bloque-contacto');
  bloque.classList.toggle('d-none', !c);
  if (!c) return;
  const nom = (c.nombre_contacto || '').trim();
  const tel = (c.telefono || '').trim();
  const lectura = [];
  if (nom) lectura.push(escHtml(nom));
  if (tel) lectura.push(escHtml(tel));
  const lec = document.getElementById('contacto-lectura');
  lec.innerHTML = lectura.length ? `<i class="bi bi-person-badge"></i> Contacto: <strong>${lectura.join(' · ')}</strong>` : '';
  lec.classList.toggle('d-none', !lectura.length);
  lec.classList.toggle('mb-2', lectura.length && (!nom || !tel));
  document.getElementById('contacto-faltan').classList.toggle('d-none', !!(nom && tel));
  document.getElementById('campo-contacto-nombre').classList.toggle('d-none', !!nom);
  document.getElementById('campo-contacto-tel').classList.toggle('d-none', !!tel);
  document.getElementById('contacto_nombre').required = !nom;
  document.getElementById('contacto_telefono').required = !tel;
}
['contacto_nombre', 'contacto_telefono'].forEach(id => document.getElementById(id).addEventListener('input', () => pintarResumen()));
['motivo', 'fecha_promesa', 'tiempo_prueba_dias', 'destino_direccion'].forEach(id => document.getElementById(id).addEventListener('input', pintarResumen));
document.getElementById('id_cliente').addEventListener('change', (e) => {
  pintarContacto();
  const c = clientesCat.find(x => String(x.id) === e.target.value);
  const dir = document.getElementById('destino_direccion');
  const nota = document.getElementById('nota-direccion');
  if (entregaElegida() === 'cliente') { // la de "a mí" la escribe el vendedor
    if (c && c.direccion && (dir.value.trim() === '' || dir.value === direccionAutollenada)) {
      dir.value = c.direccion; direccionAutollenada = c.direccion; nota.classList.remove('d-none');
    } else if (!c && dir.value === direccionAutollenada) {
      dir.value = ''; direccionAutollenada = null; nota.classList.add('d-none');
    }
  }
  pintarResumen();
});
document.getElementById('destino_direccion').addEventListener('input', (e) => {
  if (e.target.value !== direccionAutollenada) document.getElementById('nota-direccion').classList.add('d-none');
});
// A quién se entrega: "Al cliente" llena sola la dirección del cliente; "A mí"
// la deja en blanco para que el vendedor escriba su domicilio o la paquetería
// (cambia cada vez). Solo se borra/rellena lo que pusimos solos, nunca lo que
// él escribió.
const PH_DIR_CLIENTE  = 'Calle, número, colonia, ciudad...';
const PH_DIR_VENDEDOR = 'Tu domicilio o la sucursal de paquetería donde la recoges';
document.querySelectorAll('#seg-entrega .v26-seg-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    if (btn.classList.contains('active')) return;
    document.querySelectorAll('#seg-entrega .v26-seg-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const dir = document.getElementById('destino_direccion');
    const nota = document.getElementById('nota-direccion');
    if (btn.dataset.entrega === 'vendedor') {
      if (direccionAutollenada !== null && dir.value === direccionAutollenada) dir.value = '';
      direccionAutollenada = null;
      nota.classList.add('d-none');
      dir.placeholder = PH_DIR_VENDEDOR;
      dir.focus();
    } else {
      dir.placeholder = PH_DIR_CLIENTE;
      const c = clienteElegido();
      if (c && c.direccion && dir.value.trim() === '') {
        dir.value = c.direccion; direccionAutollenada = c.direccion; nota.classList.remove('d-none');
      }
    }
    pintarResumen();
  });
});
document.getElementById('fecha_promesa').min = new Date().toLocaleDateString('en-CA', { timeZone: 'America/Mexico_City' });

// ---- Tarjetas de estilo ----
const FOTOS_URL = '/erp/assets/img/cotizador/';
function norm(t) { return String(t || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }
function fotoEstilo(e) {
  return e.foto
    ? `<img src="${FOTOS_URL}${encodeURIComponent(e.foto)}" alt="" loading="lazy" class="mu-est-foto" onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'mu-est-foto vacia',innerHTML:'<i class=\'bi bi-image\'></i>'}))">`
    : '<span class="mu-est-foto vacia"><i class="bi bi-image"></i></span>';
}
function attrEstilo(e) {
  return e.atributo ? `<span class="mu-est-attr" title="${escHtml(ATRIBUTOS[e.atributo] || '')}">${escHtml(e.atributo)}</span>` : '';
}
// Color, igual que en la Nueva cotización: si el modelo tiene varios hay que
// elegir uno («Elige color…» en rojo hasta elegirlo); si tiene uno, va solo.
function colorHTML(l, e) {
  const cs = e.colores || [];
  if (cs.length > 1) {
    return `<select class="mu-color ${l.color ? '' : 'falta'}" data-accion="color" aria-label="Color"><option value="">Elige color…</option>`
      + cs.map(c => `<option value="${escHtml(c)}" ${c === l.color ? 'selected' : ''}>${escHtml(c)}</option>`).join('') + '</select>';
  }
  return cs.length === 1 ? `<span class="mu-color-tag">${escHtml(cs[0])}</span>` : '';
}

function listaEstilosHTML(l, filtro) {
  const q = norm((filtro || '').trim());
  const lista = q ? estilosCat.filter(e => norm(e.clave + ' ' + e.descripcion + ' ' + e.grupo).includes(q)) : estilosCat;
  if (!lista.length) return `<div class="mu-est-sin">${estilosCat.length ? 'Ningún estilo coincide.' : 'No hay estilos en el catálogo.'}</div>`;
  let grupo = null;
  return lista.map(e => {
    let html = '';
    if (e.grupo !== grupo) { grupo = e.grupo; html += `<div class="mu-est-grupo">${escHtml(grupo)}</div>`; }
    const sel = String(e.id) === l.item;
    return html + `
    <div class="mu-est-item${sel ? ' sel' : ''}" data-accion="elegir" data-id="${escHtml(e.id)}" role="option" aria-selected="${sel}" tabindex="0">
      ${fotoEstilo(e)}
      <div class="mu-est-info">
        <div class="mu-est-clave">${escHtml(e.clave)}${attrEstilo(e)}</div>
        <div class="mu-est-desc">${escHtml(e.descripcion)}</div>
      </div>
      <span class="mu-est-check"><i class="bi ${sel ? 'bi-check-circle-fill' : 'bi-circle'}"></i></span>
    </div>`;
  }).join('');
}

function tallasHTML(l, e) {
  const opciones = (e && e.tallas) || [];
  const usadas = l.tallas.map(t => t.talla);
  const filas = l.tallas.map((t, i) => `
    <div class="mu-talla" data-i="${i}">
      <select class="v26-select mu-talla-sel${t.talla ? '' : ' falta'}" data-accion="talla" aria-label="Talla">
        <option value="">Talla…</option>
        ${opciones.map(o => `<option value="${escHtml(o)}" ${o === t.talla ? 'selected' : ''} ${o !== t.talla && usadas.includes(o) ? 'disabled' : ''}>${escHtml(o)}</option>`).join('')}
      </select>
      <div class="mu-pares" role="group" aria-label="Pares">
        <button type="button" data-accion="menos" aria-label="Un par menos"><i class="bi bi-dash-lg"></i></button>
        <input type="number" class="mu-pares-inp" data-accion="pares" min="1" max="${TOPE_PARES}" step="1" inputmode="numeric" value="${escHtml(t.pares)}" aria-label="Pares">
        <button type="button" data-accion="mas" aria-label="Un par más"><i class="bi bi-plus-lg"></i></button>
      </div>
      <button type="button" class="v26-icon-btn mu-talla-quitar" data-accion="quitar-talla" aria-label="Quitar talla" ${l.tallas.length === 1 ? 'disabled' : ''}><i class="bi bi-trash"></i></button>
      <input type="text" class="v26-input mu-talla-nota" data-accion="nota" maxlength="500" placeholder="Nota de esta talla (opcional)" value="${escHtml(t.nota)}">
    </div>`).join('');
  const puedeMas = l.tallas.length < MAX_TALLAS && l.tallas.length < opciones.length;
  return `<div class="mu-tallas-head"><span>Tallas y pares</span>${e && !e.tallas_del_estilo ? '<small>Este modelo no tiene numeración: elige de 20 a 35</small>' : ''}</div>
    ${filas}
    <button type="button" class="mu-link-btn" data-accion="agregar-talla" ${puedeMas ? '' : 'disabled'}><i class="bi bi-plus-circle"></i> Agregar talla</button>`;
}

function cambiosHTML(l) {
  if (l.tipo !== 'variante') return '';
  return `<div class="v26-field mb-0 mt-2">
    <label>Cambios que pide el cliente <span class="mu-opc">— marca y describe cada uno</span></label>
    <div class="mu-cambios-grid">
      ${Object.entries(CATEGORIAS_ADENDUM).map(([k, et]) => `
        <div class="v26-adendum-cat">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" data-accion="cambio-on" data-cat="${k}" id="chk-${l.uid}-${k}" ${l.cambios[k].on ? 'checked' : ''}>
            <label class="form-check-label" for="chk-${l.uid}-${k}">${et}</label>
          </div>
          <textarea class="v26-textarea ${l.cambios[k].on ? '' : 'd-none'}" data-accion="cambio-txt" data-cat="${k}" rows="2" placeholder="Describe el cambio de ${et.toLowerCase()}...">${escHtml(l.cambios[k].txt)}</textarea>
        </div>`).join('')}
    </div>
    ${l.otros.map((o, i) => `
      <div class="v26-adendum-otro" data-o="${i}">
        <input type="text" class="v26-input" data-accion="otro-nombre" placeholder="Nombre del cambio" value="${escHtml(o.nombre)}">
        <textarea class="v26-textarea" data-accion="otro-txt" rows="2" placeholder="Descripción">${escHtml(o.txt)}</textarea>
        <button type="button" class="v26-icon-btn" data-accion="quitar-otro" aria-label="Quitar cambio"><i class="bi bi-trash"></i></button>
      </div>`).join('')}
    <button type="button" class="v26-btn v26-btn-ghost" data-accion="agregar-otro"><i class="bi bi-plus-lg"></i> Agregar otro cambio</button>
  </div>`;
}

function tarjetaHTML(l, n) {
  const e = estiloDe(l);
  const abierto = eligiendo === l.uid || !e;
  const subtotal = l.tallas.reduce((a, t) => a + (parseInt(t.pares, 10) || 0), 0);
  const quitar = lineas.length > 1 ? `<button type="button" class="v26-icon-btn mu-lin-quitar" data-accion="quitar-estilo" aria-label="Quitar estilo ${n}"><i class="bi bi-x-lg"></i></button>` : '';
  let cuerpo;
  if (abierto) {
    cuerpo = `
      <div class="v26-search mb-2">
        <i class="bi bi-search"></i>
        <input type="search" class="v26-input" data-accion="buscar" placeholder="Buscar modelo, línea o marca…" autocomplete="off" enterkeyhint="search">
      </div>
      <div class="mu-est-lista" role="listbox" aria-label="Catálogo de estilos">${estilosCat.length ? listaEstilosHTML(l, '') : '<div class="mu-est-sin">Cargando estilos…</div>'}</div>
      ${e ? '<button type="button" class="v26-btn v26-btn-ghost v26-btn-block mt-2" data-accion="cancelar-cambio">Dejar el que estaba</button>' : ''}`;
  } else {
    cuerpo = `
      <div class="mu-est-elegido">
        ${fotoEstilo(e)}
        <div class="mu-est-info">
          <div class="mu-est-eyebrow">${escHtml(e.grupo)}</div>
          <div class="mu-est-clave">${escHtml(e.clave)}${attrEstilo(e)}</div>
          <div class="mu-est-desc">${escHtml(e.descripcion)}</div>
          ${colorHTML(l, e) ? `<div class="mu-est-color">${colorHTML(l, e)}</div>` : ''}
        </div>
        <button type="button" class="v26-btn v26-btn-ghost mu-est-cambiar" data-accion="cambiar"><i class="bi bi-arrow-repeat"></i> Cambiar</button>
      </div>
      <div class="mu-tallas">${tallasHTML(l, e)}</div>
      <div class="v26-field mb-0 mt-3">
        <label>Tipo de muestra</label>
        <div class="mu-tipos">
          <button type="button" class="v26-seg-btn mu-tipo ${l.tipo === 'identico' ? 'active' : ''}" data-accion="tipo" data-tipo="identico">
            <i class="bi bi-check2-square"></i><strong>Idéntico al estilo</strong><small>Tal cual el catálogo</small>
          </button>
          <button type="button" class="v26-seg-btn mu-tipo ${l.tipo === 'variante' ? 'active' : ''}" data-accion="tipo" data-tipo="variante">
            <i class="bi bi-shuffle"></i><strong>Variante</strong><small>Con cambios que pide el cliente</small>
          </button>
        </div>
      </div>
      <div class="mu-cambios-box">${cambiosHTML(l)}</div>`;
  }
  return `<div class="mu-lin" data-uid="${l.uid}">
    <div class="mu-lin-head">
      <span class="mu-lin-n">${n}</span>
      <strong>Estilo ${n}</strong>
      ${e && !abierto ? `<span class="mu-lin-sub">${paresTxt(subtotal)}</span>` : ''}
      ${quitar}
    </div>
    ${cuerpo}
  </div>`;
}

function pintarLineas() {
  document.getElementById('mu-estilos').innerHTML = lineas.map((l, i) => tarjetaHTML(l, i + 1)).join('');
  document.getElementById('btn-agregar-estilo').disabled = lineas.length >= MAX_ESTILOS || lineas.some(l => !l.item);
  pintarResumen();
}
function repintarLinea(l) {
  const div = document.querySelector(`.mu-lin[data-uid="${l.uid}"]`);
  if (!div) return pintarLineas();
  div.outerHTML = tarjetaHTML(l, lineas.indexOf(l) + 1);
  document.getElementById('btn-agregar-estilo').disabled = lineas.length >= MAX_ESTILOS || lineas.some(x => !x.item);
  pintarResumen();
}

function elegirEstilo(l, id) {
  const cambio = l.item !== id;
  l.item = id;
  const e = estiloDe(l);
  if (cambio) {
    const cs = (e && e.colores) || [];
    l.color = cs.length === 1 ? cs[0] : '';
    // Las tallas que no existan en el nuevo estilo se vacían.
    l.tallas.forEach(t => { if (t.talla && !(e.tallas || []).includes(t.talla)) t.talla = ''; });
  }
  eligiendo = null;
  document.getElementById('msg-muestra').innerHTML = '';
  repintarLinea(l);
}

const cont = document.getElementById('mu-estilos');
cont.addEventListener('click', (ev) => {
  const el = ev.target.closest('[data-accion]');
  const linDiv = ev.target.closest('.mu-lin');
  if (!el || !linDiv) return;
  const l = lineaPorUid(linDiv.dataset.uid);
  if (!l) return;
  const filaT = el.closest('.mu-talla');
  const t = filaT ? l.tallas[Number(filaT.dataset.i)] : null;
  switch (el.dataset.accion) {
    case 'elegir': elegirEstilo(l, el.dataset.id); break;
    case 'cambiar': eligiendo = l.uid; repintarLinea(l); break;
    case 'cancelar-cambio': eligiendo = null; repintarLinea(l); break;
    case 'quitar-estilo':
      lineas = lineas.filter(x => x !== l);
      if (eligiendo === l.uid) eligiendo = null;
      pintarLineas();
      break;
    case 'agregar-talla': l.tallas.push({ talla: '', pares: 1, nota: '' }); repintarLinea(l); break;
    case 'quitar-talla': if (l.tallas.length > 1) { l.tallas.splice(Number(filaT.dataset.i), 1); repintarLinea(l); } break;
    case 'menos': if (t) { t.pares = Math.max(1, (parseInt(t.pares, 10) || 1) - 1); filaT.querySelector('.mu-pares-inp').value = t.pares; actualizarSubtotal(l); } break;
    case 'mas': if (t) { t.pares = Math.min(TOPE_PARES, (parseInt(t.pares, 10) || 0) + 1); filaT.querySelector('.mu-pares-inp').value = t.pares; actualizarSubtotal(l); } break;
    case 'tipo':
      if (l.tipo !== el.dataset.tipo) {
        l.tipo = el.dataset.tipo;
        linDiv.querySelectorAll('[data-accion="tipo"]').forEach(b => b.classList.toggle('active', b === el));
        linDiv.querySelector('.mu-cambios-box').innerHTML = cambiosHTML(l);
        pintarResumen();
      }
      break;
    case 'agregar-otro': l.otros.push({ nombre: '', txt: '' }); linDiv.querySelector('.mu-cambios-box').innerHTML = cambiosHTML(l); break;
    case 'quitar-otro': l.otros.splice(Number(el.closest('.v26-adendum-otro').dataset.o), 1); linDiv.querySelector('.mu-cambios-box').innerHTML = cambiosHTML(l); break;
  }
});
cont.addEventListener('keydown', (ev) => {
  const it = ev.target.closest('[data-accion="elegir"]');
  if (it && (ev.key === 'Enter' || ev.key === ' ')) { ev.preventDefault(); it.click(); }
  if (ev.target.dataset.accion === 'buscar' && ev.key === 'Enter') ev.preventDefault();
});
function actualizarSubtotal(l) {
  const sub = document.querySelector(`.mu-lin[data-uid="${l.uid}"] .mu-lin-sub`);
  if (sub) sub.textContent = paresTxt(l.tallas.reduce((a, t) => a + (parseInt(t.pares, 10) || 0), 0));
  pintarResumen();
}
function onCampo(ev) {
  const el = ev.target;
  const linDiv = el.closest('.mu-lin');
  if (!linDiv || !el.dataset.accion) return;
  const l = lineaPorUid(linDiv.dataset.uid);
  if (!l) return;
  const filaT = el.closest('.mu-talla');
  const t = filaT ? l.tallas[Number(filaT.dataset.i)] : null;
  const esChange = ev.type === 'change';
  switch (el.dataset.accion) {
    case 'buscar': if (!esChange) linDiv.querySelector('.mu-est-lista').innerHTML = listaEstilosHTML(l, el.value); break;
    case 'color': if (esChange) { l.color = el.value; el.classList.toggle('falta', !l.color); pintarResumen(); } break;
    case 'talla': if (esChange) { t.talla = el.value; repintarLinea(l); } break;
    case 'pares': t.pares = el.value === '' ? '' : Math.max(0, parseInt(el.value, 10) || 0); actualizarSubtotal(l); break;
    case 'nota': t.nota = el.value; break;
    case 'cambio-on':
      if (esChange) {
        l.cambios[el.dataset.cat].on = el.checked;
        linDiv.querySelector(`textarea[data-accion="cambio-txt"][data-cat="${el.dataset.cat}"]`).classList.toggle('d-none', !el.checked);
      }
      break;
    case 'cambio-txt': l.cambios[el.dataset.cat].txt = el.value; break;
    case 'otro-nombre': l.otros[Number(el.closest('.v26-adendum-otro').dataset.o)].nombre = el.value; break;
    case 'otro-txt': l.otros[Number(el.closest('.v26-adendum-otro').dataset.o)].txt = el.value; break;
  }
}
cont.addEventListener('input', onCampo);
cont.addEventListener('change', onCampo);

document.getElementById('btn-agregar-estilo').addEventListener('click', () => {
  if (lineas.length >= MAX_ESTILOS) return;
  const l = nuevaLinea();
  lineas.push(l);
  pintarLineas();
  const div = document.querySelector(`.mu-lin[data-uid="${l.uid}"]`);
  if (div) div.scrollIntoView({ behavior: 'smooth', block: 'start' });
});

async function cargarCatalogos() {
  const selCliente = document.getElementById('id_cliente');
  try {
    const res = await fetch('../api/muestra_catalogos.php');
    const data = await res.json();
    if (!data.ok) {
      selCliente.innerHTML = `<option value="">${escHtml(data.error)}</option>`;
      document.getElementById('mu-estilos').innerHTML = `<div class="mu-est-sin">${escHtml(data.error)}</div>`;
      return;
    }
    TOPE_PARES = data.tope_pares_cliente || 6;
    MAX_ESTILOS = data.max_estilos || 6;
    MAX_TALLAS = data.max_tallas || 6;
    clientesCat = data.clientes;
    selCliente.innerHTML = '<option value="">Elige un cliente o prospecto...</option>' +
      data.clientes.map(c => {
        const etiqueta = c.etapa === 'convertido' ? 'Cliente' : 'Prospecto';
        return `<option value="${c.id}">${escHtml(c.nombre)} (${etiqueta})</option>`;
      }).join('');
    ATRIBUTOS = data.atributos || {};
    if (data.error_estilos) {
      document.getElementById('mu-estilos').innerHTML = `<div class="mu-est-sin">${escHtml(data.error_estilos)}</div>`;
      document.getElementById('btn-agregar-estilo').disabled = true;
      return;
    }
    estilosCat = data.estilos;
    pintarLineas();
  } catch (e) {
    selCliente.innerHTML = '<option value="">Error al cargar</option>';
    document.getElementById('mu-estilos').innerHTML = '<div class="mu-est-sin">Error al cargar</div>';
  }
}
lineas = [nuevaLinea()];
pintarLineas();
cargarCatalogos();

// ---- Enviar ----
function errorEn(msgTxt, el) {
  document.getElementById('msg-muestra').innerHTML = `<div class="alert alert-danger py-2">${escHtml(msgTxt)}</div>`;
  if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); if (el.focus) el.focus({ preventScroll: true }); }
  else window.scrollTo({ top: 0, behavior: 'smooth' });
  return false;
}
function adendumDe(l) {
  const a = [];
  Object.keys(CATEGORIAS_ADENDUM).forEach(k => {
    const c = l.cambios[k];
    if (c.on && c.txt.trim()) a.push({ categoria: k, descripcion_cliente: c.txt.trim() });
  });
  l.otros.forEach(o => { if (o.nombre.trim() && o.txt.trim()) a.push({ categoria: 'otro', categoria_otro: o.nombre.trim(), descripcion_cliente: o.txt.trim() }); });
  return a;
}
function validarLineas() {
  for (let i = 0; i < lineas.length; i++) {
    const l = lineas[i], n = i + 1;
    let div = document.querySelector(`.mu-lin[data-uid="${l.uid}"]`);
    const e = estiloDe(l);
    if (!e) return errorEn(`Elige el estilo ${n}.`, div);
    if (eligiendo === l.uid) { eligiendo = null; repintarLinea(l); div = document.querySelector(`.mu-lin[data-uid="${l.uid}"]`); }
    if ((e.colores || []).length > 1 && !l.color) return errorEn(`Elige el color del estilo ${e.clave}.`, div.querySelector('[data-accion="color"]'));
    for (const t of l.tallas) {
      if (!t.talla) return errorEn(`Elige la talla en el estilo ${e.clave}.`, div.querySelector('.mu-talla-sel.falta') || div);
      if (!(parseInt(t.pares, 10) >= 1)) return errorEn(`Pon cuántos pares de la talla ${t.talla} del estilo ${e.clave} (al menos 1).`, div);
    }
    if (l.tipo === 'variante' && !adendumDe(l).length) return errorEn(`Describe al menos un cambio de la variante del estilo ${e.clave}.`, div.querySelector('.mu-cambios-box'));
    if (lineas.some((x, j) => j < i && x.item === l.item && x.color === l.color)) {
      return errorEn(`El estilo ${e.clave}${l.color ? ' en ' + l.color : ''} está repetido. Pon todas sus tallas en una sola tarjeta.`, div);
    }
  }
  return true;
}

document.getElementById('form-muestra').addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = document.getElementById('btn-enviar-muestra');
  document.getElementById('msg-muestra').innerHTML = '';
  if (!validarLineas()) return;
  if (totalPares() > paresDisponiblesCliente()) {
    errorEn('Esta solicitud pasa del máximo de pares de muestra de este cliente este mes. Quita pares.', document.getElementById('mu-total-pares'));
    return;
  }

  btn.disabled = true;
  btn.textContent = 'Enviando...';
  try {
    const fd = new FormData();
    fd.append('cliente_id', document.getElementById('id_cliente').value);
    fd.append('contacto_nombre', document.getElementById('contacto_nombre').value);
    fd.append('contacto_telefono', document.getElementById('contacto_telefono').value);
    fd.append('estilos', JSON.stringify(lineas.map(l => ({
      item: l.item, color: l.color, tipo: l.tipo, adendum: l.tipo === 'variante' ? adendumDe(l) : [],
      tallas: l.tallas.map(t => ({ talla: t.talla, pares: parseInt(t.pares, 10) || 0, nota: (t.nota || '').trim() })),
    }))));
    fd.append('motivo', document.getElementById('motivo').value);
    fd.append('notas_planta', document.getElementById('notas_planta').value);
    fd.append('tiempo_prueba_dias', document.getElementById('tiempo_prueba_dias').value);
    fd.append('fecha_promesa', document.getElementById('fecha_promesa').value);
    fd.append('entregar_a', entregaElegida());
    fd.append('destino_direccion', document.getElementById('destino_direccion').value);

    const res = await fetch('../api/muestra_solicitar.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) {
      document.getElementById('pantalla-form').classList.add('d-none');
      window.scrollTo({ top: 0 });
      document.getElementById('folio-exito').textContent = data.folio || '';
      document.getElementById('texto-exito').textContent = `Tu solicitud de ${paresTxt(data.pares || totalPares())} ya está con el equipo de Segurmex.`
        + (data.pares_restantes != null ? ` A este cliente le ${data.pares_restantes === 1 ? 'queda 1 par' : 'quedan ' + data.pares_restantes + ' pares'} de muestra este mes.` : '');
      const pdf = document.getElementById('btn-pdf-exito');
      pdf.href = '../muestra_pdf.php?id=' + encodeURIComponent(data.id);
      pdf.classList.remove('d-none');
      document.getElementById('aviso-tope').classList.add('d-none');
      document.getElementById('pantalla-exito').classList.remove('d-none');
    } else {
      errorEn(data.error || 'No se pudo enviar. Intenta de nuevo.');
      btn.disabled = false;
      btn.textContent = 'Enviar solicitud';
    }
  } catch (err) {
    errorEn('No se pudo enviar. Intenta de nuevo.');
    btn.disabled = false;
    btn.textContent = 'Enviar solicitud';
  }
});
</script>
</body>
</html>
