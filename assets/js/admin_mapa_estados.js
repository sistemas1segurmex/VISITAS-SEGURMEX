// Capa clickeable de estados de México sobre el mapa del panel admin.
// Requiere que `mapa` (instancia de Leaflet) ya exista (ver admin.js).

const GEOJSON_ESTADOS_URL = 'https://raw.githubusercontent.com/angelnmara/geojson/master/mexicoHigh.json';

let capaEstados = null;
let capaSeleccionada = null;

const estiloBase = { color: '#374151', weight: 1, fillColor: '#9CA3AF', fillOpacity: 0.08 };
const estiloHover = { fillOpacity: 0.25 };
const estiloSeleccionado = { color: '#d98f10', weight: 2, fillColor: '#F5A623', fillOpacity: 0.35 };

function nombreEstadoDeFeature(feature) {
  const id = feature.properties && feature.properties.id;
  return ISO_A_ESTADO_MX[id] || (feature.properties && feature.properties.name) || null;
}

async function initCapaEstados() {
  try {
    const res = await fetch(GEOJSON_ESTADOS_URL);
    const geojson = await res.json();
    capaEstados = L.geoJSON(geojson, {
      style: estiloBase,
      onEachFeature: (feature, layer) => {
        layer.on('mouseover', () => { if (layer !== capaSeleccionada) layer.setStyle(estiloHover); });
        layer.on('mouseout', () => { if (layer !== capaSeleccionada) layer.setStyle(estiloBase); });
        layer.on('click', () => seleccionarEstado(layer, feature));
      },
    }).addTo(mapa);
    capaEstados.bringToBack();
  } catch (e) {
    console.warn('No se pudo cargar el mapa de estados', e);
  }
}

function seleccionarEstado(layer, feature) {
  if (capaSeleccionada) capaSeleccionada.setStyle(estiloBase);
  if (capaSeleccionada === layer) {
    // Click de nuevo sobre el mismo estado: lo deselecciona.
    capaSeleccionada = null;
    ocultarPanelEstado();
    return;
  }
  layer.setStyle(estiloSeleccionado);
  capaSeleccionada = layer;
  const nombreEstado = nombreEstadoDeFeature(feature);
  cargarDetalleEstado(nombreEstado);
}

function ocultarPanelEstado() {
  document.getElementById('panel-estado-wrap').classList.add('d-none');
}

async function cargarDetalleEstado(estado) {
  const wrap = document.getElementById('panel-estado-wrap');
  const panel = document.getElementById('panel-estado');
  wrap.classList.remove('d-none');
  panel.innerHTML = `<p class="text-muted mb-0">Cargando información de ${estado}...</p>`;

  try {
    const res = await fetch(`../api/estado_detalle.php?estado=${encodeURIComponent(estado)}`);
    const data = await res.json();
    if (!data.ok) { panel.innerHTML = `<div class="alert alert-danger mb-0">${data.error}</div>`; return; }

    const listaVendedores = data.vendedores.length === 0
      ? '<p class="text-muted small mb-0">Sin vendedores asignados a este estado.</p>'
      : data.vendedores.map(v => `
          <div class="d-flex justify-content-between align-items-center border-bottom py-1">
            <span>${v.nombre}</span>
            ${v.en_linea == 1 ? '<span class="badge badge-verificado">En línea</span>' : '<span class="badge bg-secondary">Desconectado</span>'}
          </div>
        `).join('');

    panel.innerHTML = `
      <div class="d-flex justify-content-between align-items-start mb-2">
        <h6 class="mb-0">${estado}</h6>
        <button class="btn btn-sm btn-outline-secondary" onclick="cerrarPanelEstadoManual()">✕</button>
      </div>
      <div class="row g-2 mb-3">
        <div class="col-4"><div class="card card-cita"><div class="card-body py-2"><div class="text-muted small">Clientes</div><div class="fs-5">${data.total_clientes}</div></div></div></div>
        <div class="col-4"><div class="card card-cita"><div class="card-body py-2"><div class="text-muted small">Citas totales</div><div class="fs-5">${data.citas.total}</div></div></div></div>
        <div class="col-4"><div class="card card-cita"><div class="card-body py-2"><div class="text-muted small">Verificadas</div><div class="fs-5">${data.citas.verificadas}</div></div></div></div>
      </div>
      <div class="small text-muted mb-1">Próximas citas: ${data.citas.proximas}</div>
      <h6 class="small mt-3 mb-1">Vendedores en este estado</h6>
      ${listaVendedores}
    `;
  } catch (e) {
    panel.innerHTML = '<div class="alert alert-danger mb-0">No se pudo cargar la información del estado.</div>';
  }
}

function cerrarPanelEstadoManual() {
  if (capaSeleccionada) { capaSeleccionada.setStyle(estiloBase); capaSeleccionada = null; }
  ocultarPanelEstado();
}
