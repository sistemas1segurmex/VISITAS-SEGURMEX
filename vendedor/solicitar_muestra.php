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
      <a href="solicitar_muestra.php" class="v26-btn v26-btn-ghost v26-btn-block mt-2" id="btn-pedir-otra"><i class="bi bi-plus-lg"></i> Pedir otra</a>
    </div>

    <div id="pantalla-form">
      <section class="mu-hero mu-hero--sol">
        <div class="mu-hero-txt">
          <div class="mu-hero-eyebrow"><i class="bi bi-box-seam"></i> Nueva solicitud</div>
          <h1 class="mu-hero-titulo">¿Qué muestra necesita tu cliente?</h1>
          <p class="mu-hero-sub">Llena los 3 pasos. La recibe el equipo de Segurmex y te avisamos cuando esté en preparación y cuando se embarque.</p>
        </div>
        <div class="mu-cupo">
          <div class="mu-cupo-txt"><span id="cupo-n">–</span> de <span id="cupo-tope">5</span><small>disponibles este mes</small></div>
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
          </section>

          <section class="v26-card mu-paso">
            <div class="mu-paso-head"><span class="mu-paso-n">2</span><div><strong>¿Qué muestra?</strong><small>El estilo y, si aplica, los cambios</small></div></div>
            <div class="v26-field">
              <label>Estilo</label>
              <div class="v26-search mb-2">
                <i class="bi bi-search"></i>
                <input type="text" id="buscar-estilo" class="v26-input" placeholder="Escribe la clave o el nombre del estilo...">
              </div>
              <select id="id_estilo_base" class="v26-select" required>
                <option value="">Cargando estilos...</option>
              </select>
            </div>
            <div class="v26-field">
              <label>Talla <span class="mu-opc">(opcional)</span></label>
              <input type="text" id="talla" class="v26-input" maxlength="30" placeholder="Ej. 27">
            </div>
            <div class="v26-field">
              <label>Tipo de muestra</label>
              <div class="mu-tipos" id="seg-tipo">
                <button type="button" class="v26-seg-btn mu-tipo active" data-tipo="identico">
                  <i class="bi bi-check2-square"></i><strong>Idéntico al estilo</strong><small>Tal cual el catálogo</small>
                </button>
                <button type="button" class="v26-seg-btn mu-tipo" data-tipo="variante">
                  <i class="bi bi-shuffle"></i><strong>Variante</strong><small>Con cambios que pide el cliente</small>
                </button>
              </div>
            </div>
            <div id="bloque-adendum" class="d-none v26-field mb-0">
              <label>Cambios que pide el cliente <span class="mu-opc">— marca y describe cada uno</span></label>
              <div id="adendum-categorias" class="mu-cambios-grid"></div>
              <div id="adendum-otros"></div>
              <button type="button" class="v26-btn v26-btn-ghost" id="btn-agregar-otro"><i class="bi bi-plus-lg"></i> Agregar otro cambio</button>
            </div>
          </section>

          <section class="v26-card mu-paso">
            <div class="mu-paso-head"><span class="mu-paso-n">3</span><div><strong>¿Cuándo y a dónde?</strong><small>La fecha que le prometiste y dónde entregarla</small></div></div>
            <div class="v26-field">
              <label>Fecha promesa <span class="mu-opc">(opcional)</span></label>
              <input type="date" id="fecha_promesa" class="v26-input">
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
            <dt>Estilo</dt><dd id="r-estilo">—</dd>
            <dt>Talla</dt><dd id="r-talla">—</dd>
            <dt>Tipo</dt><dd id="r-tipo">Idéntico al estilo</dd>
            <dt>Promesa</dt><dd id="r-fecha">—</dd>
            <dt>Entregar en</dt><dd id="r-dir">—</dd>
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

// Tope de muestras por mes (ver MUESTRAS_TOPE_MES en includes/muestras.php):
// se avisa cuántas le quedan y, si ya no le queda ninguna, no se deja enviar.
function pintarTope(data) {
  const caja = document.getElementById('aviso-tope');
  if (data.tope_mes == null) return;
  const quedan = Math.max(0, data.tope_mes - (data.usadas_mes || 0));
  document.getElementById('cupo-n').textContent = quedan;
  document.getElementById('cupo-tope').textContent = data.tope_mes;
  document.getElementById('cupo-barra').style.width = Math.round((quedan / data.tope_mes) * 100) + '%';
  document.querySelector('.mu-cupo').classList.toggle('agotado', quedan === 0);
  caja.classList.toggle('d-none', quedan !== 0);
  if (quedan === 0) {
    caja.innerHTML = `<div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle"></i> Ya pediste ${data.tope_mes} muestras este mes, que es el máximo. Podrás pedir otra a partir del día 1 del próximo mes.</div>`;
    document.getElementById('btn-enviar-muestra').disabled = true;
  }
}

// ---- Resumen en vivo, dirección del cliente y buscador de estilos ----
let clientesCat = [];
let estilosCat = [];
let direccionAutollenada = null; // la que pusimos sola, para no pisar lo que escriba el vendedor

function textoSel(sel) { return sel.value ? sel.options[sel.selectedIndex].text : '—'; }
function pintarResumen() {
  const val = (id) => document.getElementById(id).value.trim();
  const tipo = document.querySelector('#seg-tipo .v26-seg-btn.active').dataset.tipo;
  document.getElementById('r-cliente').textContent = textoSel(document.getElementById('id_cliente'));
  document.getElementById('r-estilo').textContent = textoSel(document.getElementById('id_estilo_base'));
  document.getElementById('r-talla').textContent = val('talla') || '—';
  document.getElementById('r-tipo').textContent = tipo === 'variante' ? 'Variante (con cambios)' : 'Idéntico al estilo';
  const f = val('fecha_promesa');
  document.getElementById('r-fecha').textContent = f ? new Date(f + 'T12:00:00').toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';
  document.getElementById('r-dir').textContent = val('destino_direccion') || '—';
}
['talla', 'fecha_promesa', 'destino_direccion'].forEach(id => document.getElementById(id).addEventListener('input', pintarResumen));
document.getElementById('id_estilo_base').addEventListener('change', pintarResumen);
document.getElementById('id_cliente').addEventListener('change', (e) => {
  const c = clientesCat.find(x => String(x.id) === e.target.value);
  const dir = document.getElementById('destino_direccion');
  const nota = document.getElementById('nota-direccion');
  if (c && c.direccion && (dir.value.trim() === '' || dir.value === direccionAutollenada)) {
    dir.value = c.direccion; direccionAutollenada = c.direccion; nota.classList.remove('d-none');
  } else if (!c && dir.value === direccionAutollenada) {
    dir.value = ''; direccionAutollenada = null; nota.classList.add('d-none');
  }
  pintarResumen();
});
document.getElementById('destino_direccion').addEventListener('input', (e) => {
  if (e.target.value !== direccionAutollenada) document.getElementById('nota-direccion').classList.add('d-none');
});
function pintarEstilos(filtro) {
  const sel = document.getElementById('id_estilo_base');
  const actual = sel.value;
  const f = (filtro || '').trim().toLowerCase();
  const lista = f ? estilosCat.filter(e => e.nombre.toLowerCase().includes(f)) : estilosCat;
  sel.innerHTML = `<option value="">${lista.length ? (f ? lista.length + ' estilo(s) encontrados — elige uno' : 'Elige un estilo...') : 'Ningún estilo coincide'}</option>` +
    lista.map(e => `<option value="${e.id}"${String(e.id) === actual ? ' selected' : ''}>${escHtml(e.nombre)}</option>`).join('');
  if (lista.length === 1 && f) sel.value = lista[0].id;
  pintarResumen();
}
document.getElementById('buscar-estilo').addEventListener('input', (e) => pintarEstilos(e.target.value));
document.getElementById('fecha_promesa').min = new Date().toLocaleDateString('en-CA', { timeZone: 'America/Mexico_City' });
let otrosContador = 0;

function pintarCategorias() {
  document.getElementById('adendum-categorias').innerHTML = Object.entries(CATEGORIAS_ADENDUM).map(([clave, etiqueta]) => `
    <div class="v26-adendum-cat">
      <div class="form-check">
        <input class="form-check-input adendum-check" type="checkbox" value="${clave}" id="chk-${clave}">
        <label class="form-check-label" for="chk-${clave}">${etiqueta}</label>
      </div>
      <textarea class="v26-textarea d-none" id="txt-${clave}" rows="2" placeholder="Describe el cambio de ${etiqueta.toLowerCase()}..."></textarea>
    </div>`).join('');

  document.querySelectorAll('.adendum-check').forEach(chk => {
    chk.addEventListener('change', () => {
      document.getElementById('txt-' + chk.value).classList.toggle('d-none', !chk.checked);
    });
  });
}
pintarCategorias();

document.getElementById('btn-agregar-otro').addEventListener('click', () => {
  const id = otrosContador++;
  const div = document.createElement('div');
  div.className = 'v26-adendum-otro';
  div.dataset.otroId = id;
  div.innerHTML = `
    <input type="text" class="v26-input otro-nombre" placeholder="Nombre del cambio">
    <textarea class="v26-textarea otro-texto" rows="2" placeholder="Descripción"></textarea>
    <button type="button" class="v26-icon-btn" onclick="this.closest('.v26-adendum-otro').remove()"><i class="bi bi-trash"></i></button>`;
  document.getElementById('adendum-otros').appendChild(div);
});

document.querySelectorAll('#seg-tipo .v26-seg-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('#seg-tipo .v26-seg-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const esVariante = btn.dataset.tipo === 'variante';
    document.getElementById('bloque-adendum').classList.toggle('d-none', !esVariante);
    pintarResumen();
  });
});

async function cargarCatalogos() {
  const selCliente = document.getElementById('id_cliente');
  const selEstilo = document.getElementById('id_estilo_base');
  try {
    const res = await fetch('../api/muestra_catalogos.php');
    const data = await res.json();
    if (!data.ok) {
      selCliente.innerHTML = `<option value="">${escHtml(data.error)}</option>`;
      selEstilo.innerHTML = `<option value="">${escHtml(data.error)}</option>`;
      return;
    }
    clientesCat = data.clientes;
    selCliente.innerHTML = '<option value="">Elige un cliente o prospecto...</option>' +
      data.clientes.map(c => {
        const etiqueta = c.etapa === 'convertido' ? 'Cliente' : 'Prospecto';
        return `<option value="${c.id}">${escHtml(c.nombre)} (${etiqueta})</option>`;
      }).join('');
    pintarTope(data);
    if (data.error_estilos) {
      selEstilo.innerHTML = `<option value="">${escHtml(data.error_estilos)}</option>`;
    } else {
      estilosCat = data.estilos;
      pintarEstilos('');
    }
  } catch (e) {
    selCliente.innerHTML = '<option value="">Error al cargar</option>';
    selEstilo.innerHTML = '<option value="">Error al cargar</option>';
  }
}
cargarCatalogos();

document.getElementById('form-muestra').addEventListener('submit', async (e) => {
  e.preventDefault();
  const msg = document.getElementById('msg-muestra');
  const btn = document.getElementById('btn-enviar-muestra');
  msg.innerHTML = '';

  const tipo = document.querySelector('#seg-tipo .v26-seg-btn.active').dataset.tipo;
  const adendum = [];
  if (tipo === 'variante') {
    Object.keys(CATEGORIAS_ADENDUM).forEach(clave => {
      const chk = document.getElementById('chk-' + clave);
      const texto = document.getElementById('txt-' + clave).value.trim();
      if (chk.checked && texto) adendum.push({ categoria: clave, descripcion_cliente: texto });
    });
    document.querySelectorAll('.v26-adendum-otro').forEach(div => {
      const nombre = div.querySelector('.otro-nombre').value.trim();
      const texto = div.querySelector('.otro-texto').value.trim();
      if (nombre && texto) adendum.push({ categoria: 'otro', categoria_otro: nombre, descripcion_cliente: texto });
    });
    if (adendum.length === 0) {
      msg.innerHTML = '<div class="alert alert-danger py-2">Describe al menos un cambio de la variante.</div>';
      return;
    }
  }

  btn.disabled = true;
  btn.textContent = 'Enviando...';
  try {
    const fd = new FormData();
    fd.append('cliente_id', document.getElementById('id_cliente').value);
    const selEstilo = document.getElementById('id_estilo_base');
    fd.append('id_estilo_base', selEstilo.value);
    fd.append('talla', document.getElementById('talla').value);
    fd.append('fecha_promesa', document.getElementById('fecha_promesa').value);
    fd.append('tipo', tipo);
    fd.append('destino_direccion', document.getElementById('destino_direccion').value);
    fd.append('adendum', JSON.stringify(adendum));

    const res = await fetch('../api/muestra_solicitar.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) {
      document.getElementById('pantalla-form').classList.add('d-none');
      window.scrollTo({ top: 0 });
      document.getElementById('folio-exito').textContent = data.folio || '';
      if (data.restantes === 0) document.getElementById('btn-pedir-otra').classList.add('d-none');
      document.getElementById('aviso-tope').classList.add('d-none');
      document.getElementById('pantalla-exito').classList.remove('d-none');
    } else {
      msg.innerHTML = `<div class="alert alert-danger py-2">${escHtml(data.error)}</div>`;
      btn.disabled = false;
      btn.textContent = 'Enviar solicitud';
    }
  } catch (err) {
    msg.innerHTML = '<div class="alert alert-danger py-2">No se pudo enviar. Intenta de nuevo.</div>';
    btn.disabled = false;
    btn.textContent = 'Enviar solicitud';
  }
});
</script>
</body>
</html>
