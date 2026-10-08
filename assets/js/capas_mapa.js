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
  vigilarCapasBase(mapa, capaMapa, capaSatelite);
  return { capaMapa, capaSatelite };
}

// Si el mapa normal no carga (8-oct-2026: una red de oficina o un antivirus
// que bloquea openstreetmap.org deja el mapa en gris y el vendedor pone el
// pin a ciegas) se cambia solo a Satélite, que viene de otro servidor, y se
// avisa. Si tampoco carga el satélite, se avisa que el mapa no está cargando.
function vigilarCapasBase(mapa, capaMapa, capaSatelite) {
  const MIN_FALLAS = 4;
  const cuenta = { mapa: { ok: 0, mal: 0 }, sat: { ok: 0, mal: 0 } };
  let cambiado = false;
  let avisadoSinMapa = false;

  const imagenSat = capaSatelite.getLayers()[0];
  capaMapa.on('tileload', () => { cuenta.mapa.ok++; });
  imagenSat.on('tileload', () => { cuenta.sat.ok++; });

  capaMapa.on('tileerror', () => {
    cuenta.mapa.mal++;
    if (cambiado || !mapa.hasLayer(capaMapa)) return;
    if (cuenta.mapa.mal < MIN_FALLAS || cuenta.mapa.mal <= cuenta.mapa.ok * 2) return;
    cambiado = true;
    mapa.removeLayer(capaMapa);
    capaSatelite.addTo(mapa);
    avisoEnMapa(mapa, 'El mapa normal no cargó en esta conexión; te pusimos Satélite.');
  });
  imagenSat.on('tileerror', () => {
    cuenta.sat.mal++;
    if (avisadoSinMapa || cuenta.sat.mal < MIN_FALLAS || cuenta.sat.mal <= cuenta.sat.ok * 2) return;
    if ((cuenta.mapa.mal > 0 && cuenta.mapa.ok === 0) || !mapa.hasLayer(capaMapa)) {
      avisadoSinMapa = true;
      avisoEnMapa(mapa, 'El mapa no está cargando (revisa el internet). Si te mandaron la ubicación, pega el link.');
    }
  });
}

function avisoEnMapa(mapa, texto) {
  if (mapa._avisoCapas) mapa.removeControl(mapa._avisoCapas);
  // Arriba (bajo el + / −): abajo se encima con los créditos en celular.
  const aviso = L.control({ position: 'topleft' });
  aviso.onAdd = () => {
    const div = L.DomUtil.create('div');
    div.setAttribute('role', 'status');
    div.style.cssText = 'background:#fff7e0;border:1px solid #e8a400;color:#5c4300;border-radius:8px;' +
      'padding:6px 10px;font-size:.74rem;font-weight:600;max-width:240px;box-shadow:0 2px 6px rgba(0,0,0,.15);cursor:pointer;';
    div.textContent = texto + ' ✕';
    L.DomEvent.disableClickPropagation(div);
    L.DomEvent.on(div, 'click', () => { mapa.removeControl(aviso); mapa._avisoCapas = null; });
    return div;
  };
  aviso.addTo(mapa);
  mapa._avisoCapas = aviso;
}
