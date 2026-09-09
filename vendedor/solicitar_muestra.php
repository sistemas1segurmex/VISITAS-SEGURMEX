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
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css<?= assetVer(__DIR__ . '/../assets/css/style.css') ?>">
<link rel="stylesheet" href="../assets/css/vendedor-2026.css<?= assetVer(__DIR__ . '/../assets/css/vendedor-2026.css') ?>">
<style>
  .v26-adendum-cat { border: 1px solid var(--v26-border); border-radius: var(--v26-r-md); padding: 12px; margin-bottom: 10px; }
  .v26-adendum-cat textarea { margin-top: 8px; }
  .v26-adendum-otro { display: flex; gap: 8px; align-items: flex-start; margin-bottom: 10px; }
  .v26-adendum-otro input[type="text"] { flex: none; width: 40%; }
  .v26-adendum-otro textarea { flex: 1; }
</style>
</head>
<body class="v26">
  <div class="v26-header">
    <div class="v26-topbar">
      <div class="v26-topbar-left">
        <a href="index.php" class="v26-back v26-tip v26-tip--bottom" data-tip="Volver a mis visitas" aria-label="Volver"><i class="bi bi-arrow-left"></i></a>
        <div class="v26-greeting">
          <div class="hi">Ventas</div>
          <div class="name">Solicitar muestra</div>
        </div>
      </div>
      <div class="v26-topbar-right">
        <img src="../logo.png" alt="Segurmex" class="v26-logo">
      </div>
    </div>
  </div>

  <div class="v26-wrap" style="max-width:560px">
    <div id="msg-muestra"></div>

    <div id="pantalla-exito" class="d-none v26-empty">
      <div class="icon"><i class="bi bi-check-circle-fill" style="color:var(--v26-green)"></i></div>
      <p><strong id="folio-exito"></strong> enviada a autorizar.</p>
      <p class="text-muted small">Sigue el mismo proceso que cualquier otra solicitud del ERP -- Dirección la revisa, luego pasa a Diseño y Producción.</p>
      <a href="solicitar_muestra.php" class="v26-btn v26-btn-ghost v26-btn-block mt-2"><i class="bi bi-plus-lg"></i> Pedir otra</a>
    </div>

    <div class="v26-card">
      <form id="form-muestra">
        <div class="v26-field">
          <label>Cliente</label>
          <select id="id_cliente" class="v26-select" required>
            <option value="">Cargando clientes...</option>
          </select>
        </div>
        <div class="v26-field">
          <label>Estilo</label>
          <select id="id_estilo_base" class="v26-select" required>
            <option value="">Cargando estilos...</option>
          </select>
        </div>
        <div class="v26-field">
          <label>Talla (opcional)</label>
          <input type="text" id="talla" class="v26-input">
        </div>
        <div class="v26-field">
          <label>Fecha promesa (opcional)</label>
          <input type="date" id="fecha_promesa" class="v26-input">
        </div>

        <div class="v26-field">
          <label>Tipo de muestra</label>
          <div class="v26-seg" id="seg-tipo">
            <button type="button" class="v26-seg-btn active" data-tipo="identico">Idéntico al estilo</button>
            <button type="button" class="v26-seg-btn" data-tipo="variante">Variante (con cambios)</button>
          </div>
        </div>

        <div id="bloque-adendum" class="d-none v26-field">
          <label>Adendum -- describe cada cambio</label>
          <div id="adendum-categorias"></div>
          <div id="adendum-otros"></div>
          <button type="button" class="v26-btn v26-btn-ghost" id="btn-agregar-otro"><i class="bi bi-plus-lg"></i> Agregar otro cambio</button>
        </div>

        <div class="v26-field">
          <label>Dirección de entrega</label>
          <textarea id="destino_direccion" class="v26-textarea" rows="2" required placeholder="Calle, número, colonia, ciudad..."></textarea>
        </div>

        <button type="submit" class="v26-btn v26-btn-primary v26-btn-block" id="btn-enviar-muestra">Enviar a autorizar</button>
      </form>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const CATEGORIAS_ADENDUM = { casco: 'Casco', suela: 'Suela', piel: 'Piel', forro: 'Forro' };
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
  });
});

async function cargarCatalogos() {
  const selCliente = document.getElementById('id_cliente');
  const selEstilo = document.getElementById('id_estilo_base');
  try {
    const res = await fetch('../api/muestra_catalogos.php');
    const data = await res.json();
    if (!data.ok) {
      selCliente.innerHTML = `<option value="">${data.error}</option>`;
      selEstilo.innerHTML = `<option value="">${data.error}</option>`;
      return;
    }
    selCliente.innerHTML = '<option value="">Elige un cliente...</option>' +
      data.clientes.map(c => `<option value="${c.id}">${c.nombre}</option>`).join('');
    selEstilo.innerHTML = '<option value="">Elige un estilo...</option>' +
      data.estilos.map(e => `<option value="${e.id}">${e.nombre}</option>`).join('');
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
      msg.innerHTML = '<div class="alert alert-danger py-2">Agrega al menos un cambio en el Adendum.</div>';
      return;
    }
  }

  btn.disabled = true;
  btn.textContent = 'Enviando...';
  try {
    const fd = new FormData();
    fd.append('id_cliente', document.getElementById('id_cliente').value);
    fd.append('id_estilo_base', document.getElementById('id_estilo_base').value);
    fd.append('talla', document.getElementById('talla').value);
    fd.append('fecha_promesa', document.getElementById('fecha_promesa').value);
    fd.append('tipo', tipo);
    fd.append('destino_direccion', document.getElementById('destino_direccion').value);
    fd.append('adendum', JSON.stringify(adendum));

    const res = await fetch('../api/muestra_solicitar.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) {
      document.getElementById('form-muestra').closest('.v26-card').classList.add('d-none');
      document.getElementById('folio-exito').textContent = data.folio || 'Tu solicitud';
      document.getElementById('pantalla-exito').classList.remove('d-none');
    } else {
      msg.innerHTML = `<div class="alert alert-danger py-2">${data.error}</div>`;
      btn.disabled = false;
      btn.textContent = 'Enviar a autorizar';
    }
  } catch (err) {
    msg.innerHTML = '<div class="alert alert-danger py-2">No se pudo enviar. Intenta de nuevo.</div>';
    btn.disabled = false;
    btn.textContent = 'Enviar a autorizar';
  }
});
</script>
</body>
</html>
