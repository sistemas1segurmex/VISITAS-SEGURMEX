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
<title>Agregar a mi cartera</title>
<link rel="icon" type="image/png" href="../assets/img/Favv-Segu.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="../assets/css/style.css<?= assetVer(__DIR__ . '/../assets/css/style.css') ?>">
<link rel="stylesheet" href="../assets/css/vendedor-2026.css<?= assetVer(__DIR__ . '/../assets/css/vendedor-2026.css') ?>">
<style>
  .v26-compacto .v26-wrap { padding-top: 10px; padding-bottom: 4px; }
  .v26-compacto .v26-card { padding: 14px; }
  .v26-compacto .v26-field { margin-bottom: 10px; }
  .v26-compacto .v26-field label { margin-bottom: 3px; font-size: .68rem; }
  .v26-compacto .v26-input, .v26-compacto .v26-select { padding: 9px 12px; font-size: .87rem; }
  .v26-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px; }
  .v26-grid-2 .v26-field { margin-bottom: 0; }
  .v26-map-chica { height: 160px; }
  .v26-buscar-row { display: flex; gap: 8px; margin-bottom: 4px; }
  .v26-buscar-row .v26-search { flex: 1; margin-bottom: 0; }
  .v26-resultados-busqueda {
    position: relative; z-index: 20; margin-top: 6px; margin-bottom: 8px;
    background: var(--v26-surface-solid); border: 1px solid var(--v26-border);
    border-radius: var(--v26-r-md); box-shadow: var(--v26-shadow-md); overflow: hidden;
    max-height: 180px; overflow-y: auto;
  }
  .v26-resultados-busqueda button {
    display: block; width: 100%; text-align: left; border: none; background: none;
    padding: 9px 12px; font-size: .78rem; border-bottom: 1px solid var(--v26-border);
  }
  .v26-resultados-busqueda button:last-child { border-bottom: none; }
  .v26-resultados-busqueda button:hover { background: rgba(245,166,35,.08); }
  .v26-ubicacion-badge { font-size: .68rem; font-weight: 700; text-transform: none; letter-spacing: 0; display: inline-flex; align-items: center; gap: 3px; }
  .v26-ubicacion-badge.pendiente { color: var(--v26-red); }
  .v26-ubicacion-badge.lista { color: var(--v26-green); }
  #seg-tipo-registro, #seg-tipo-cliente { display: flex; width: 100%; }
  #seg-tipo-registro .v26-seg-btn, #seg-tipo-cliente .v26-seg-btn { flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 5px; }
  .v26-nota-erp { margin-top: 8px; padding: 9px 11px; border-radius: var(--v26-r-md); background: #FFF7E6; border: 1px solid #F5D08A; font-size: .78rem; color: #6B4E00; line-height: 1.35; }
  .v26-ayuda-erp { margin-top: 4px; font-size: .7rem; color: var(--v26-muted, #6B7280); }
</style>
</head>
<body class="v26 v26-compacto">
  <div class="v26-header">
    <div class="v26-topbar">
      <div class="v26-topbar-left">
        <a href="clientes.php" class="v26-back v26-tip v26-tip--bottom" data-tip="Volver a mis clientes" aria-label="Volver"><i class="bi bi-arrow-left"></i></a>
        <div class="v26-greeting">
          <div class="hi">Cartera</div>
          <div class="name">Agregar a mi cartera</div>
        </div>
      </div>
      <div class="v26-topbar-right">
        <img src="../logo.png" alt="Segurmex" class="v26-logo">
        <a href="../logout.php" class="v26-icon-btn v26-tip v26-tip--bottom" data-tip="Cerrar sesión" aria-label="Salir"><i class="bi bi-box-arrow-right"></i></a>
      </div>
    </div>
  </div>

  <div class="v26-wrap">
    <div class="v26-card">
      <form id="form-cliente">
        <div class="v26-field">
          <label>¿Qué estás registrando?</label>
          <div class="v26-seg" id="seg-tipo-registro">
            <button type="button" class="v26-seg-btn active" data-tipo="prospecto"><i class="bi bi-person-plus"></i> Prospecto nuevo</button>
            <button type="button" class="v26-seg-btn" data-tipo="cliente"><i class="bi bi-trophy"></i> Ya es mi cliente</button>
          </div>
          <input type="hidden" name="ya_es_cliente" id="ya-es-cliente" value="0">
          <div class="v26-nota-erp" id="aviso-hacer-cliente">Para hacerlo cliente, entra al <strong>ERP › Ventas › Prospectos</strong> y usa <strong>Hacer cliente</strong>.</div>
          <div class="v26-nota-erp d-none" id="nota-cliente-erp"></div>
        </div>

        <div class="v26-field d-none" id="campo-cliente-erp">
          <label>¿Cuál de tus clientes es?</label>
          <select name="id_cliente_erp" id="sel-cliente-erp" class="v26-select">
            <option value="">Elige el cliente…</option>
          </select>
          <div class="v26-ayuda-erp">Aparecen tus clientes que todavía no tienes en tu cartera. Si no aparece, regístralo como «Prospecto nuevo».</div>
        </div>

        <div class="v26-field">
          <label>¿Es persona u organización?</label>
          <div class="v26-seg" id="seg-tipo-cliente">
            <button type="button" class="v26-seg-btn" data-tipo="persona"><i class="bi bi-person"></i> Persona</button>
            <button type="button" class="v26-seg-btn active" data-tipo="organizacion"><i class="bi bi-building"></i> Organización</button>
          </div>
          <input type="hidden" name="tipo_cliente" id="tipo-cliente" value="organizacion">
        </div>

        <div class="v26-grid-2">
          <div class="v26-field">
            <label id="label-nombre">Nombre del negocio</label>
            <input type="text" name="nombre" class="v26-input" required>
          </div>
          <div class="v26-field">
            <label>Teléfono (opcional)</label>
            <input type="text" name="telefono" class="v26-input">
          </div>
        </div>

        <div class="v26-field" id="campo-contacto">
          <label>Persona de contacto (opcional)</label>
          <input type="text" name="nombre_contacto" class="v26-input" placeholder="¿Con quién tratas ahí?">
        </div>

        <?php include __DIR__ . '/../includes/form_direccion_cliente.php'; ?>

        <div id="msg-cliente"></div>
        <button type="submit" class="v26-btn v26-btn-primary v26-btn-block mt-1" id="btn-guardar">Guardar prospecto</button>
      </form>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="../assets/js/v26-modal.js<?= assetVer(__DIR__ . '/../assets/js/v26-modal.js') ?>"></script>
<script src="../assets/js/vendedor.js<?= assetVer(__DIR__ . '/../assets/js/vendedor.js') ?>"></script>
<script src="../assets/js/avisos.js<?= assetVer(__DIR__ . '/../assets/js/avisos.js') ?>"></script>
<script>window.DIRECCION_CFG = { googleKey: <?= json_encode(envConfig('GOOGLE_MAPS_API_KEY', '') ?? '') ?> };</script>
<script src="../assets/js/capas_mapa.js<?= assetVer(__DIR__ . '/../assets/js/capas_mapa.js') ?>"></script>
<script src="../assets/js/direccion-cliente.js<?= assetVer(__DIR__ . '/../assets/js/direccion-cliente.js') ?>"></script>
<script>
// Teléfono a 10 dígitos desde el inicio (ver activarCampoTelefono en vendedor.js).
const campoTelefono = activarCampoTelefono(document.querySelector('input[name="telefono"]'));
iniciarTrackingPeriodico();
DireccionCliente.init();

// «Ya es mi cliente» (antes «Cliente ya ganado», 06-nov-2026): se elige de SUS clientes del ERP que
// todavía no están en Visitas; así el registro nace ligado al ERP. Si no hay
// ninguno, se explica que se registre como prospecto y se haga cliente desde
// el ERP (Prospectos › Hacer cliente).
const selClienteErp = document.getElementById('sel-cliente-erp');
const notaClienteErp = document.getElementById('nota-cliente-erp');
const inputNombre = document.querySelector('input[name="nombre"]');
let nombreAutoErp = '';

function elegirTipoRegistro(tipo) {
  document.querySelectorAll('#seg-tipo-registro .v26-seg-btn').forEach(b => b.classList.toggle('active', b.dataset.tipo === tipo));
  document.getElementById('ya-es-cliente').value = tipo === 'cliente' ? '1' : '0';
  document.getElementById('campo-cliente-erp').classList.toggle('d-none', tipo !== 'cliente');
  // 12-nov-2026: el botón dice lo que de verdad se guarda, y el aviso de cómo
  // hacerlo cliente solo se ve con «Prospecto nuevo».
  document.getElementById('aviso-hacer-cliente').classList.toggle('d-none', tipo === 'cliente');
  document.getElementById('btn-guardar').textContent = tipo === 'cliente' ? 'Agregar cliente' : 'Guardar prospecto';
  if (tipo !== 'cliente') {
    selClienteErp.value = '';
    if (nombreAutoErp && inputNombre.value === nombreAutoErp) inputNombre.value = '';
    nombreAutoErp = '';
  }
}

async function cargarClientesErp() {
  try {
    const res = await fetch('../api/clientes_erp_disponibles.php');
    const data = await res.json();
    return data.ok ? data.clientes : null;
  } catch (e) {
    return null;
  }
}

document.querySelectorAll('#seg-tipo-registro .v26-seg-btn').forEach(btn => {
  btn.addEventListener('click', async () => {
    notaClienteErp.classList.add('d-none');
    if (btn.dataset.tipo !== 'cliente') { elegirTipoRegistro('prospecto'); return; }

    const clientes = await cargarClientesErp();
    if (clientes === null) {
      elegirTipoRegistro('prospecto');
      notaClienteErp.textContent = 'No se pudo cargar tu lista de clientes. Revisa tu conexión e intenta de nuevo.';
      notaClienteErp.classList.remove('d-none');
      return;
    }
    if (!clientes.length) {
      elegirTipoRegistro('prospecto');
      notaClienteErp.innerHTML = 'No tienes clientes pendientes por agregar.<br>Si ya te compra pero no aparece, regístralo como <strong>«Prospecto nuevo»</strong>. Para hacerlo cliente, entra al <strong>ERP › Ventas › Prospectos</strong> y usa <strong>Hacer cliente</strong>.';
      notaClienteErp.classList.remove('d-none');
      return;
    }
    selClienteErp.innerHTML = '<option value="">Elige el cliente…</option>' + clientes.map(c => {
      const op = document.createElement('option');
      op.value = c.id;
      op.textContent = (c.clave ? c.clave + ' · ' : '') + c.razon_social;
      op.dataset.nombre = c.razon_social;
      return op.outerHTML;
    }).join('');
    elegirTipoRegistro('cliente');
  });
});

selClienteErp.addEventListener('change', () => {
  const op = selClienteErp.selectedOptions[0];
  const nombreErp = op && op.value ? op.dataset.nombre : '';
  // Se llena el nombre solo si está vacío o si lo había puesto esta lista.
  if (!inputNombre.value.trim() || inputNombre.value === nombreAutoErp) {
    inputNombre.value = nombreErp;
    nombreAutoErp = nombreErp;
  }
});

document.querySelectorAll('#seg-tipo-cliente .v26-seg-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('#seg-tipo-cliente .v26-seg-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tipo-cliente').value = btn.dataset.tipo;
    document.getElementById('label-nombre').textContent = btn.dataset.tipo === 'persona' ? 'Nombre completo' : 'Nombre del negocio';
    // Una persona ya ES el contacto -- el campo solo tiene sentido para organización.
    document.getElementById('campo-contacto').classList.toggle('d-none', btn.dataset.tipo === 'persona');
  });
});

// --- Detección de posibles clientes duplicados ---
async function buscarPosiblesDuplicados({ nombre, telefono, lat, lng, colonia, calleNumero, municipio, cp }) {
  const params = new URLSearchParams({ nombre, telefono, colonia, calle_numero: calleNumero });
  if (lat) params.set('lat', lat);
  if (lng) params.set('lng', lng);
  if (municipio) params.set('municipio', municipio);
  if (cp) params.set('codigo_postal', cp);
  try {
    const res = await fetch('../api/verificar_duplicado.php?' + params.toString());
    const data = await res.json();
    // otro_vendedor: coincide con un cliente de OTRO vendedor (se guardará en revisión).
    return data.ok ? data : { candidatos: [] };
  } catch (e) {
    return { candidatos: [] };
  }
}

// --- Envío del formulario ---
document.getElementById('form-cliente').addEventListener('submit', async (e) => {
  e.preventDefault();
  const msg = document.getElementById('msg-cliente');
  msg.innerHTML = '';

  if (document.getElementById('ya-es-cliente').value === '1' && !selClienteErp.value) {
    msg.innerHTML = '<div class="alert alert-danger py-2">Elige de la lista cuál de tus clientes es.</div>';
    selClienteErp.scrollIntoView({ behavior: 'smooth', block: 'center' });
    return;
  }

  // Mismo orden que el formulario: el teléfono va antes que la dirección.
  if (!campoTelefono.validar()) {
    msg.innerHTML = `<div class="alert alert-danger py-2">${MSG_TELEFONO_INVALIDO}</div>`;
    campoTelefono.input.scrollIntoView({ behavior: 'smooth', block: 'center' });
    return;
  }

  // Antes de valores(): si pegaron la dirección completa en Calle, aquí se acomoda.
  const errorCalle = DireccionCliente.validarCalle(false);
  const { estado, municipio, colonia, cp, lat, lng, calleNumero } = DireccionCliente.valores();
  if (!estado || !municipio || !colonia) {
    msg.innerHTML = '<div class="alert alert-danger py-2">Falta la colonia (paso 1): escribe el código postal o el nombre de la colonia y elígela de la lista.</div>';
    document.getElementById('buscar-colonia').scrollIntoView({ behavior: 'smooth', block: 'center' });
    return;
  }
  if (errorCalle) {
    msg.innerHTML = `<div class="alert alert-danger py-2">${DireccionCliente.validarCalle()}</div>`;
    return;
  }

  const nombre = document.querySelector('input[name="nombre"]').value.trim();
  const telefono = document.querySelector('input[name="telefono"]').value.trim();

  // La ubicación es obligatoria sin excepción: sin ella no se puede
  // verificar el check-in por GPS ni ordenar la cartera por cercanía.
  if (!lat || !lng) {
    // id: DireccionCliente lo quita en cuanto se marca el pin.
    msg.innerHTML = '<div class="alert alert-danger py-2" id="alerta-ubicacion">Falta marcar la ubicación del cliente (paso 3): toca el punto en el mapa, pega el link que te mandaron o, si estás con el cliente, usa "Estoy aquí".</div>';
    document.getElementById('mapa-cliente').scrollIntoView({ behavior: 'smooth', block: 'center' });
    return;
  }
  const dup = await buscarPosiblesDuplicados({ nombre, telefono, lat, lng, colonia, calleNumero, municipio, cp });
  const candidatos = dup.candidatos || [];
  if (candidatos.length > 0) {
    const lista = candidatos.map(c => `• ${c.nombre} — ${c.direccion} (${c.razon})`).join('\n');
    const respuesta = await v26Sheet({
      titulo: 'Ya tienes un cliente parecido',
      desc: `Antes de guardar, revisa si no es el mismo:\n${lista}`,
      pedirMotivo: false,
      textoConfirmar: 'Guardar de todas formas',
      textoCancelar: 'Revisar de nuevo',
    });
    if (!respuesta) return;
  }
  // Coincide con el cliente de otro vendedor del mismo municipio: se puede
  // guardar, pero queda en revisión (ver includes/coincidencias.php).
  if (dup.otro_vendedor) {
    const seguir = await v26Sheet({
      titulo: 'Este cliente ya lo atiende otro vendedor',
      desc: 'Coincide con un cliente de otro vendedor en el mismo municipio. Si lo guardas, quedará en revisión y no podrás trabajarlo (citas, cotizaciones ni muestras) hasta que la responsable de muestras lo valide.',
      pedirMotivo: false,
      textoConfirmar: 'Guardar y enviar a revisión',
      textoCancelar: 'Revisar de nuevo',
    });
    if (!seguir) return;
  }

  const fd = new FormData(e.target);
  fd.append('colonia', colonia);
  fd.append('municipio', municipio);
  fd.append('estado', estado);
  fd.append('codigo_postal', cp);

  const res = await fetch('../api/clientes.php', { method: 'POST', body: fd });
  if (res.status === 401) {
    msg.innerHTML = '<div class="alert alert-danger py-2">Tu sesión de Visitas se cerró (quizá entraste con tu usuario en otro lado). Sal y vuelve a entrar para guardar al cliente.</div>';
    return;
  }
  const data = await res.json();
  if (data.ok && data.en_revision) {
    msg.innerHTML = `<div class="alert alert-warning py-2">${data.mensaje}</div>`;
    setTimeout(() => { window.location.href = 'clientes.php'; }, 4000);
  } else if (data.ok) {
    window.location.href = 'clientes.php';
  } else {
    msg.innerHTML = `<div class="alert alert-danger py-2">${data.error}</div>`;
  }
});
</script>
</body>
</html>
