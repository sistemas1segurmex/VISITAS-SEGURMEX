<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
$u = requireRole('vendedor');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Nuevo cliente</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/vendedor-2026.css">
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
</style>
</head>
<body class="v26 v26-compacto">
  <div class="v26-header">
    <div class="v26-topbar">
      <div class="v26-topbar-left">
        <a href="clientes.php" class="v26-back v26-tip v26-tip--bottom" data-tip="Volver a mis clientes" aria-label="Volver"><i class="bi bi-arrow-left"></i></a>
        <div class="v26-greeting">
          <div class="hi">Cartera</div>
          <div class="name">Nuevo cliente</div>
        </div>
      </div>
      <div class="v26-topbar-right">
        <img src="../logo.png" alt="Segurmex" class="v26-logo">
      </div>
    </div>
  </div>

  <div class="v26-wrap">
    <div class="v26-card">
      <form id="form-cliente">
        <div class="v26-grid-2">
          <div class="v26-field">
            <label>Nombre del cliente / negocio</label>
            <input type="text" name="nombre" class="v26-input" required>
          </div>
          <div class="v26-field">
            <label>Teléfono (opcional)</label>
            <input type="text" name="telefono" class="v26-input">
          </div>
        </div>

        <div class="v26-buscar-row">
          <div class="v26-search">
            <i class="bi bi-search"></i>
            <input type="text" id="buscar-direccion" class="v26-input" placeholder="Escribe la dirección y se busca sola...">
          </div>
        </div>
        <div id="resultados-busqueda"></div>

        <div class="v26-field">
          <div id="mapa-cliente" class="v26-map v26-map-chica"></div>
          <div class="v26-map-float v26-tip" id="btn-mi-ubicacion" data-tip="Detecta tu posición GPS y la marca en el mapa"><i class="bi bi-crosshair"></i> Usar mi ubicación</div>
          <input type="hidden" name="lat" id="lat">
          <input type="hidden" name="lng" id="lng">
        </div>

        <div class="v26-field">
          <label>Calle y número</label>
          <input type="text" name="calle_numero" id="calle-numero" class="v26-input" placeholder="Ej. Av. Reforma 245" required>
        </div>

        <div class="v26-grid-2">
          <div class="v26-field">
            <label>Estado</label>
            <select id="select-estado" class="v26-select" required>
              <option value="">Cargando...</option>
            </select>
          </div>
          <div class="v26-field">
            <label>Municipio</label>
            <select id="select-municipio" class="v26-select" required disabled>
              <option value="">Elige el estado</option>
            </select>
          </div>
        </div>

        <div class="v26-grid-2">
          <div class="v26-field">
            <label>Colonia</label>
            <select id="select-colonia" class="v26-select" required disabled>
              <option value="">Elige el municipio</option>
            </select>
          </div>
          <div class="v26-field">
            <label>Código postal</label>
            <input type="text" id="input-cp" class="v26-input" readonly placeholder="Automático">
          </div>
        </div>

        <div id="msg-cliente"></div>
        <button type="submit" class="v26-btn v26-btn-primary v26-btn-block mt-1">Guardar cliente</button>
      </form>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="../assets/js/v26-modal.js"></script>
<script>
const mapa = L.map('mapa-cliente').setView([23.6345, -102.5528], 5); // centro de México por defecto
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(mapa);
let marcador = null;

function ponerMarcador(lat, lng, autocompletar = true) {
  document.getElementById('lat').value = lat;
  document.getElementById('lng').value = lng;
  if (marcador) mapa.removeLayer(marcador);
  marcador = L.marker([lat, lng]).addTo(mapa);
  mapa.setView([lat, lng], 16);
  if (autocompletar) autocompletarDesdeCoordenadas(lat, lng);
}

mapa.on('click', (e) => ponerMarcador(e.latlng.lat, e.latlng.lng));

document.getElementById('btn-mi-ubicacion').addEventListener('click', () => {
  if (!('geolocation' in navigator)) return alert('Tu navegador no soporta geolocalización.');
  navigator.geolocation.getCurrentPosition(
    (pos) => ponerMarcador(pos.coords.latitude, pos.coords.longitude),
    () => alert('No se pudo obtener tu ubicación. Revisa los permisos del navegador.')
  );
});

// --- Nominatim: buscar dirección automáticamente mientras escribe ---
let buscandoDireccion = false;
let temporizadorBusqueda = null;

document.getElementById('buscar-direccion').addEventListener('input', (e) => {
  clearTimeout(temporizadorBusqueda);
  const q = e.target.value.trim();
  const cont = document.getElementById('resultados-busqueda');
  if (q.length < 4) { cont.innerHTML = ''; return; }
  temporizadorBusqueda = setTimeout(buscarDireccion, 600);
});

async function buscarDireccion() {
  const campo = document.getElementById('buscar-direccion');
  const q = campo.value.trim();
  const cont = document.getElementById('resultados-busqueda');
  if (q.length < 4 || buscandoDireccion) return;
  buscandoDireccion = true;
  cont.innerHTML = '<div class="v26-resultados-busqueda"><button type="button" disabled>Buscando...</button></div>';
  try {
    const url = `https://nominatim.openstreetmap.org/search?format=json&addressdetails=1&countrycodes=mx&limit=6&q=${encodeURIComponent(q)}`;
    const res = await fetch(url);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const data = await res.json();
    if (!data.length) {
      cont.innerHTML = '<div class="v26-resultados-busqueda"><button type="button" disabled>Sin resultados. Prueba con más detalle (calle, ciudad).</button></div>';
      return;
    }
    cont.innerHTML = '<div class="v26-resultados-busqueda">' +
      data.map((r, i) => `<button type="button" data-i="${i}">${r.display_name}</button>`).join('') +
      '</div>';
    cont.querySelectorAll('button[data-i]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const r = data[btn.dataset.i];
        ponerMarcador(parseFloat(r.lat), parseFloat(r.lon));
        cont.innerHTML = '';
        campo.value = '';
      });
    });
  } catch (e) {
    console.error('Error buscando dirección en Nominatim:', e);
    cont.innerHTML = '<div class="v26-resultados-busqueda"><button type="button" disabled>No se pudo buscar (revisa tu conexión a internet). Puedes marcar el punto directo en el mapa.</button></div>';
  } finally {
    buscandoDireccion = false;
  }
}

// --- Catálogo SEPOMEX: selects en cascada ---
const selectEstado = document.getElementById('select-estado');
const selectMunicipio = document.getElementById('select-municipio');
const selectColonia = document.getElementById('select-colonia');
const inputCp = document.getElementById('input-cp');

async function cargarEstados() {
  try {
    const res = await fetch('../api/sepomex.php?tipo=estados');
    const data = await res.json();
    if (!data.ok || data.estados.length === 0) {
      selectEstado.innerHTML = '<option value="">Catálogo no disponible</option>';
      return;
    }
    selectEstado.innerHTML = '<option value="">Selecciona un estado</option>' +
      data.estados.map(e => `<option value="${e}">${e}</option>`).join('');
  } catch (e) {
    selectEstado.innerHTML = '<option value="">Error al cargar</option>';
  }
}

async function cargarMunicipios(estado) {
  selectMunicipio.disabled = true;
  selectColonia.disabled = true;
  selectColonia.innerHTML = '<option value="">Elige el municipio</option>';
  inputCp.value = '';
  if (!estado) {
    selectMunicipio.innerHTML = '<option value="">Elige el estado</option>';
    return;
  }
  selectMunicipio.innerHTML = '<option value="">Cargando...</option>';
  const res = await fetch('../api/sepomex.php?tipo=municipios&estado=' + encodeURIComponent(estado));
  const data = await res.json();
  if (!data.ok) { selectMunicipio.innerHTML = '<option value="">Error al cargar</option>'; return; }
  selectMunicipio.innerHTML = '<option value="">Selecciona un municipio</option>' +
    data.municipios.map(m => `<option value="${m}">${m}</option>`).join('');
  selectMunicipio.disabled = false;
}

async function cargarColonias(estado, municipio) {
  selectColonia.disabled = true;
  inputCp.value = '';
  if (!municipio) {
    selectColonia.innerHTML = '<option value="">Elige el municipio</option>';
    return;
  }
  selectColonia.innerHTML = '<option value="">Cargando...</option>';
  const url = `../api/sepomex.php?tipo=colonias&estado=${encodeURIComponent(estado)}&municipio=${encodeURIComponent(municipio)}`;
  const res = await fetch(url);
  const data = await res.json();
  if (!data.ok) { selectColonia.innerHTML = '<option value="">Error al cargar</option>'; return; }
  selectColonia.innerHTML = '<option value="">Selecciona una colonia</option>' +
    data.colonias.map(c => `<option value="${c.asentamiento}" data-cp="${c.cp}">${c.asentamiento} (CP ${c.cp})</option>`).join('');
  selectColonia.disabled = false;
}

selectEstado.addEventListener('change', () => cargarMunicipios(selectEstado.value));
selectMunicipio.addEventListener('change', () => cargarColonias(selectEstado.value, selectMunicipio.value));
selectColonia.addEventListener('change', () => {
  const opt = selectColonia.selectedOptions[0];
  inputCp.value = opt ? (opt.dataset.cp || '') : '';
});

const estadosListos = cargarEstados();

// --- Autocompletado desde el mapa: calle/número + Estado/Municipio/Colonia ---
function normalizar(s) {
  const marcasDiacriticas = new RegExp('[̀-ͯ]', 'g');
  const sinAcentos = (s || '').toString().toLowerCase().normalize('NFD').replace(marcasDiacriticas, '');
  return sinAcentos.replace(/[^a-z0-9]+/g, ' ').trim();
}

function buscarCoincidencia(select, texto) {
  const objetivo = normalizar(texto);
  if (!objetivo) return null;
  const opciones = Array.from(select.options).map(o => o.value).filter(Boolean);
  let match = opciones.find(o => normalizar(o) === objetivo);
  if (!match) match = opciones.find(o => normalizar(o).includes(objetivo) || objetivo.includes(normalizar(o)));
  return match || null;
}

async function autocompletarDesdeCoordenadas(lat, lng) {
  const campoCalle = document.getElementById('calle-numero');
  try {
    const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&addressdetails=1`;
    const res = await fetch(url);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const data = await res.json();
    const a = data.address || {};

    const calle = a.road || a.pedestrian || a.suburb || '';
    const numero = a.house_number || '';
    if (calle) campoCalle.value = numero ? `${calle} ${numero}` : calle;

    await estadosListos;
    const estadoMatch = buscarCoincidencia(selectEstado, a.state);
    if (!estadoMatch) return;
    selectEstado.value = estadoMatch;
    await cargarMunicipios(estadoMatch);

    const municipioTexto = a.county || a.city_district || a.municipality || a.town || a.city || '';
    const municipioMatch = buscarCoincidencia(selectMunicipio, municipioTexto);
    if (!municipioMatch) return;
    selectMunicipio.value = municipioMatch;
    await cargarColonias(estadoMatch, municipioMatch);

    const coloniaTexto = a.suburb || a.neighbourhood || a.quarter || a.residential || '';
    const coloniaMatch = buscarCoincidencia(selectColonia, coloniaTexto);
    if (coloniaMatch) {
      selectColonia.value = coloniaMatch;
      selectColonia.dispatchEvent(new Event('change'));
    }
  } catch (e) {
    console.error('Error en autocompletado desde el mapa:', e);
  }
}

// --- Detección de posibles clientes duplicados ---
async function buscarPosiblesDuplicados({ nombre, telefono, lat, lng, colonia, calleNumero }) {
  const params = new URLSearchParams({ nombre, telefono, colonia, calle_numero: calleNumero });
  if (lat) params.set('lat', lat);
  if (lng) params.set('lng', lng);
  try {
    const res = await fetch('../api/verificar_duplicado.php?' + params.toString());
    const data = await res.json();
    return data.ok ? data.candidatos : [];
  } catch (e) {
    return [];
  }
}

// --- Envío del formulario ---
document.getElementById('form-cliente').addEventListener('submit', async (e) => {
  e.preventDefault();
  const msg = document.getElementById('msg-cliente');
  msg.innerHTML = '';

  if (!selectEstado.value || !selectMunicipio.value || !selectColonia.value) {
    msg.innerHTML = '<div class="alert alert-danger py-2">Elige estado, municipio y colonia de las listas (así evitamos colonias que no existen).</div>';
    return;
  }

  const nombre = document.querySelector('input[name="nombre"]').value.trim();
  const telefono = document.querySelector('input[name="telefono"]').value.trim();
  const calleNumero = document.getElementById('calle-numero').value.trim();
  const colonia = selectColonia.value;
  const municipio = selectMunicipio.value;
  const estado = selectEstado.value;
  const cp = inputCp.value;
  const lat = document.getElementById('lat').value;
  const lng = document.getElementById('lng').value;
  const direccionCompleta = `${calleNumero}, ${colonia}, ${municipio}, ${estado}, CP ${cp}`;

  const candidatos = await buscarPosiblesDuplicados({ nombre, telefono, lat, lng, colonia, calleNumero });
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

  const fd = new FormData(e.target);
  fd.set('direccion', direccionCompleta);
  fd.append('colonia', colonia);
  fd.append('municipio', municipio);
  fd.append('estado', estado);
  fd.append('cp', cp);

  const res = await fetch('../api/clientes.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.ok) {
    window.location.href = 'clientes.php';
  } else {
    msg.innerHTML = `<div class="alert alert-danger py-2">${data.error}</div>`;
  }
});
</script>
</body>
</html>
