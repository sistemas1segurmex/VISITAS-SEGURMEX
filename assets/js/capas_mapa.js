// Capas base de todos los mapas de Visitas: "Mapa" (OpenStreetMap) y
// "Satélite" (Esri World Imagery, gratis y sin clave, con calles y nombres
// de Esri encima). El satélite sirve para ubicar la nave o el local cuando
// la calle no tiene nombre en el mapa (carreteras, parques industriales).
//
// Recuerda en este navegador la última capa elegida (localStorage), para
// no tener que volver a escoger Satélite cada vez.
// Uso: agregarCapasBase(mapa)  -- en lugar de L.tileLayer(...).addTo(mapa).
const CLAVE_CAPA_MAPA = 'visitas_capa_mapa';

function agregarCapasBase(mapa, opciones = {}) {
  const capaMapa = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 19,
  });
  const esri = (servicio) => `https://server.arcgisonline.com/ArcGIS/rest/services/${servicio}/MapServer/tile/{z}/{y}/{x}`;
  const capaSatelite = L.layerGroup([
    L.tileLayer(esri('World_Imagery'), { attribution: 'Imágenes © Esri, Maxar, Earthstar Geographics', maxZoom: 19 }),
    L.tileLayer(esri('Reference/World_Transportation'), { maxZoom: 19, opacity: .8 }),
    L.tileLayer(esri('Reference/World_Boundaries_and_Places'), { maxZoom: 19 }),
  ]);

  let guardada = null;
  try { guardada = localStorage.getItem(CLAVE_CAPA_MAPA); } catch (e) { /* sin almacenamiento: queda Mapa */ }
  (guardada === 'Satélite' ? capaSatelite : capaMapa).addTo(mapa);

  L.control.layers({ 'Mapa': capaMapa, 'Satélite': capaSatelite }, null, {
    position: opciones.posicion || 'topright',
    collapsed: false,
  }).addTo(mapa);
  mapa.on('baselayerchange', (e) => {
    try { localStorage.setItem(CLAVE_CAPA_MAPA, e.name); } catch (err) { /* nada */ }
  });
  return { capaMapa, capaSatelite };
}
