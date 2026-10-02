// Dashboard del dueño/admin: mapa en vivo + listado de citas del día + alertas.

let mapa, marcadoresVendedores = {}, tooltipsVendedores = {};
let capaRutas = null;               // L.layerGroup con las polylines del día
const PALETA_RUTAS = ['#4F46E5', '#F5A623', '#16A34A', '#E11D48', '#0EA5E9', '#9333EA', '#D97706'];
const nombresVendedores = {};       // vendedor_id -> nombre, para las etiquetas del recorrido
let filtroVendedorMapaId = 0;       // 0 = todos los vendedores

function initMapa() {
  mapa = L.map('mapa', { zoomControl: true }).setView([23.6345, -102.5528], 5);
  // Mapa (OpenStreetMap) / Satélite (Esri) -- ver assets/js/capas_mapa.js.
  agregarCapasBase(mapa);
  capaRutas = L.layerGroup().addTo(mapa);
}

// estadoConexion: 'en_linea' | 'desconectado' | 'perdida'
function iconoVendedor(estadoConexion) {
  const clase = estadoConexion === 'en_linea' ? '' : (estadoConexion === 'perdida' ? ' is-lost' : ' is-offline');
  return L.divIcon({
    className: 'visitas-marker-icon',
    html: `<span class="visitas-marker${clase}"><span class="visitas-marker-pulse"></span><span class="visitas-marker-dot"></span></span>`,
    iconSize: [18, 18],
    iconAnchor: [9, 9],
    popupAnchor: [0, -9],
  });
}

function aplicarEstiloEstado(marker, estadoConexion) {
  marker.setIcon(iconoVendedor(estadoConexion));
}

// Badge de eficiencia (citas completadas/total de hoy) como tooltip flotante,
// siempre visible, junto al marcador. Sin dato (nadie tenía citas hoy) → no
// se muestra nada, para no ensuciar el mapa con "Eficiencia: —" de sobra.
function contenidoTooltipVendedor(u) {
  const partes = [];
  if (u.eficiencia_pct !== null && u.eficiencia_pct !== undefined) {
    const pct = Math.round(u.eficiencia_pct);
    const nivel = pct >= 75 ? 'alta' : (pct >= 40 ? 'media' : 'baja');
    partes.push(`<span class="visitas-eficiencia-badge ${nivel}">Eficiencia: ${pct}%</span>`);
  }
  if (u.estado_conexion === 'perdida') {
    partes.push(`<span class="visitas-lost-tag">CONEXIÓN PERDIDA</span>`);
  }
  return partes.join('<br>');
}

// Llena el select de "Todos los vendedores / uno en particular" una sola
// vez (no en cada ciclo de 20s, para no perder la selección del admin a
// medio uso). Se llama desde actualizarUbicaciones() con la lista completa.
function poblarFiltroVendedorMapa(ubicaciones) {
  const sel = document.getElementById('filtro-vendedor-mapa');
  if (!sel || sel.dataset.poblado) return;
  sel.dataset.poblado = '1';
  const opciones = ubicaciones
    .slice()
    .sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'))
    .map(u => `<option value="${u.vendedor_id}">${u.nombre}</option>`)
    .join('');
  sel.insertAdjacentHTML('beforeend', opciones);
}
document.getElementById('filtro-vendedor-mapa')?.addEventListener('change', function () {
  filtroVendedorMapaId = Number(this.value);
  actualizarUbicaciones();
  dibujarRutasDia();
});

function fechaSeleccionadaEsHoy() {
  const fecha = document.getElementById('filtro-fecha')?.value || new Date().toISOString().slice(0, 10);
  return fecha === new Date().toISOString().slice(0, 10);
}

async function actualizarUbicaciones() {
  // Los pines de "en vivo" (en_linea/desconectado/perdida) son siempre la
  // ubicación MÁS RECIENTE de cada vendedor, sin importar qué día se esté
  // revisando en el filtro de "Visitas" de abajo -- eso confundía porque un
  // pin de HOY aparecía encimado sobre la ruta de un día anterior. Si no se
  // está viendo el día de hoy, se ocultan en vez de mostrar algo que no
  // corresponde al día seleccionado.
  if (!fechaSeleccionadaEsHoy()) {
    Object.values(marcadoresVendedores).forEach(m => mapa.removeLayer(m));
    marcadoresVendedores = {};
    const resumenEl = document.getElementById('resumen-vendedores');
    if (resumenEl) resumenEl.textContent = 'Ubicación en vivo oculta -- estás viendo un día anterior';
    ['conteo-en-linea', 'conteo-desconectado', 'conteo-perdida'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.textContent = '–';
    });
    return;
  }
  try {
    const res = await fetch('../api/tracking.php');
    const data = await res.json();
    if (!data.ok) return;

    // Nombres para el select y para las etiquetas del recorrido -- de la
    // lista COMPLETA, antes de filtrar, así el combo siempre tiene a todos
    // aunque ahorita se esté viendo solo a uno.
    data.ubicaciones.forEach(u => { nombresVendedores[u.vendedor_id] = u.nombre; });
    poblarFiltroVendedorMapa(data.ubicaciones);

    const listaFiltrada = filtroVendedorMapaId === 0
      ? data.ubicaciones
      : data.ubicaciones.filter(u => u.vendedor_id === filtroVendedorMapaId);

    const vistos = new Set();
    const sinUbicacion = [];
    let enLineaCount = 0, perdidaCount = 0;

    listaFiltrada.forEach(u => {
      if (u.lat === null || u.lng === null) {
        sinUbicacion.push(u.nombre);
        return;
      }
      vistos.add(u.vendedor_id);
      if (u.estado_conexion === 'en_linea') enLineaCount++;
      if (u.estado_conexion === 'perdida') perdidaCount++;

      // Ciudad/municipio real según el GPS (nombreLugarGPS en
      // includes/helpers.php), no el territorio asignado a mano al
      // vendedor -- si Nominatim no responde o no cachea todavía, se omite
      // en vez de mostrar algo vacío o incorrecto.
      const lugarTxt = u.lugar ? `: ${u.lugar}` : '';
      const estadoTxt = u.estado_conexion === 'en_linea'
        ? `<span class="text-success">🟢 En línea${lugarTxt}</span>`
        : u.estado_conexion === 'perdida'
          ? `<span class="text-danger">⚫ Conexión perdida${lugarTxt} — última señal: ${formatearFechaUTC(u.fecha_hora)}</span>`
          : `<span class="text-muted">⚪ Desconectado${lugarTxt} — última señal: ${formatearFechaUTC(u.fecha_hora)}</span>`;
      const eficienciaTxt = (u.eficiencia_pct !== null && u.eficiencia_pct !== undefined)
        ? `<br>Eficiencia hoy: <strong>${Math.round(u.eficiencia_pct)}%</strong> (${u.citas_completadas}/${u.citas_total} citas)`
        : '';
      const popup = `<strong>${u.nombre}</strong><br>${estadoTxt}${eficienciaTxt}`;

      let marker = marcadoresVendedores[u.vendedor_id];
      if (marker) {
        marker.setLatLng([u.lat, u.lng]).setPopupContent(popup);
      } else {
        marker = L.marker([u.lat, u.lng], { icon: iconoVendedor(u.estado_conexion) }).addTo(mapa).bindPopup(popup);
        marcadoresVendedores[u.vendedor_id] = marker;
        // Clic en el marcador → zoom animado a su ubicación exacta (útil
        // sobre todo cuando varios vendedores quedan encimados en el mismo
        // punto, como los de "conexión perdida"). Se lee getLatLng() al
        // momento del clic (no la posición de cuando se creó el marcador),
        // para que siempre apunte a donde está ahora, no donde estaba.
        marker.on('click', () => {
          mapa.flyTo(marker.getLatLng(), 16, { duration: 1.1 });
        });
      }
      aplicarEstiloEstado(marker, u.estado_conexion);

      const contenidoTooltip = contenidoTooltipVendedor(u);
      if (contenidoTooltip) {
        if (marker.getTooltip()) {
          marker.setTooltipContent(contenidoTooltip);
        } else {
          // permanent:false -- antes quedaba siempre visible encima del pin y
          // estorbaba las calles del mapa; ahora solo aparece al pasar el
          // cursor (el popup con el detalle completo se sigue abriendo al
          // dar clic, eso no cambia).
          marker.bindTooltip(contenidoTooltip, { permanent: false, direction: 'top', offset: [0, -10], className: 'visitas-tooltip-limpio' });
        }
      } else if (marker.getTooltip()) {
        marker.unbindTooltip();
      }
    });

    // Quita del mapa a quien ya no está en la lista (ej. se desactivó su cuenta).
    Object.keys(marcadoresVendedores).forEach(id => {
      if (!vistos.has(Number(id))) {
        mapa.removeLayer(marcadoresVendedores[id]);
        delete marcadoresVendedores[id];
      }
    });

    const totalConUbicacion = listaFiltrada.length - sinUbicacion.length;
    let resumen = `${enLineaCount} en línea de ${totalConUbicacion} con ubicación registrada`;
    if (sinUbicacion.length > 0) {
      resumen += ` · sin ubicación aún: ${sinUbicacion.join(', ')}`;
    }
    document.getElementById('resumen-vendedores').textContent = resumen;

    // Contadores de la leyenda del mapa: "En línea"/"Conexión perdida" son
    // los grupos especiales; "Desconectado" es el resto de vendedores
    // activos, para que los 3 números siempre sumen el total de activos.
    const totalVendedores = listaFiltrada.length;
    const conteoEnLinea = document.getElementById('conteo-en-linea');
    const conteoDesconectado = document.getElementById('conteo-desconectado');
    const conteoPerdida = document.getElementById('conteo-perdida');
    if (conteoEnLinea) conteoEnLinea.textContent = enLineaCount;
    if (conteoPerdida) conteoPerdida.textContent = perdidaCount;
    if (conteoDesconectado) conteoDesconectado.textContent = totalVendedores - enLineaCount - perdidaCount;
  } catch (e) { /* silencioso: se reintenta en el próximo ciclo */ }
}

// Solo la hora (sin fecha) de un timestamp UTC de tracking_ubicaciones, para
// las etiquetas de cada punto del recorrido -- formatearFechaUTC
// (fecha_utils.js) trae fecha completa, aquí basta la hora porque el
// recorrido ya está filtrado a un solo día con el selector de fecha.
function horaSoloUTC(fechaStr) {
  if (!fechaStr) return '';
  const iso = String(fechaStr).replace(' ', 'T') + (String(fechaStr).endsWith('Z') ? '' : 'Z');
  const d = new Date(iso);
  return isNaN(d.getTime()) ? fechaStr : d.toLocaleTimeString('es-MX', { hour: 'numeric', minute: '2-digit', timeZone: 'America/Mexico_City' });
}

// Calle+colonia exacta de un punto, bajo demanda (nunca de entrada para
// toda la ruta -- ver api/geocodificar_punto.php). Caché en memoria del
// navegador aparte de la caché en disco del servidor: un mismo punto que se
// vuelve a pasar el cursor en esta sesión no vuelve a pedirse.
const direccionesCache = {};
function claveGeocode(lat, lng) { return `${lat.toFixed(5)},${lng.toFixed(5)}`; }
async function obtenerDireccionPunto(lat, lng) {
  const clave = claveGeocode(lat, lng);
  if (clave in direccionesCache) return direccionesCache[clave];
  try {
    const res = await fetch(`../api/geocodificar_punto.php?lat=${lat}&lng=${lng}`);
    const data = await res.json();
    direccionesCache[clave] = data.ok ? data.direccion : null;
  } catch (e) {
    direccionesCache[clave] = null;
  }
  return direccionesCache[clave];
}

// Distancia en metros entre dos coordenadas (Haversine) -- para agrupar
// puntos del recorrido que están casi en el mismo lugar (ver abajo).
function distanciaMetrosMapa(lat1, lng1, lat2, lng2) {
  const R = 6371000;
  const dLat = (lat2 - lat1) * Math.PI / 180;
  const dLng = (lng2 - lng1) * Math.PI / 180;
  const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

// Cuando el vendedor se queda parado en un lugar, el tracking sigue mandando
// un punto cada ~30s -- en un par de horas eso son cientos de puntos
// dispersos alrededor del mismo lugar, ilegible en el mapa. El GPS bajo
// techo/en zona urbana no solo tiembla: el "multipath" (rebote de la señal
// en edificios) suele alargar ese ruido EN LA DIRECCIÓN DE LA CALLE, así
// que aunque nadie se haya movido, se ve como si hubieran caminado una ruta
// real. 20m no bastaba para que un día completo parado en la oficina
// colapsara en un solo punto. Como las paradas reales de un vendedor
// (visitas a clientes distintos) normalmente están a cientos de metros o
// kilómetros entre sí, se usa una escala de "misma cuadra/edificio" en vez
// de "mismo punto exacto": se agrupan los puntos consecutivos que están a
// menos de 100m del último ya agrupado.
const DISTANCIA_MIN_NUEVO_MARCADOR_M = 100;
function agruparPuntosCercanos(puntos) {
  const grupos = [];
  puntos.forEach(p => {
    const ultimo = grupos[grupos.length - 1];
    if (ultimo && distanciaMetrosMapa(ultimo.lat, ultimo.lng, p.lat, p.lng) < DISTANCIA_MIN_NUEVO_MARCADOR_M) {
      ultimo.fin = p;
      // Centroide incremental: el punto del grupo se va acercando al
      // promedio real de la nube en vez de quedarse fijo en el primer ping.
      // Si comparáramos siempre contra el primer punto, un GPS que tiembla
      // más de 20m del ancla original (aunque siga siendo el mismo lugar)
      // abre un grupo nuevo, y al regresar puede rebotar entre grupos --
      // eso es lo que dibujaba las líneas cruzadas encimadas en un solo
      // punto (ej. la oficina).
      ultimo.n++;
      ultimo.lat += (p.lat - ultimo.lat) / ultimo.n;
      ultimo.lng += (p.lng - ultimo.lng) / ultimo.n;
    } else {
      grupos.push({ lat: p.lat, lng: p.lng, inicio: p, fin: p, n: 1 });
    }
  });
  return grupos;
}

// ── Líneas de recorrido del día seleccionado ──────────────────────────────
async function dibujarRutasDia() {
  const fecha = document.getElementById('filtro-fecha')?.value || new Date().toISOString().slice(0, 10);
  // Si se está viendo el día de hoy, el pin en vivo del vendedor (arriba,
  // marcadoresVendedores) ya está parado casi exacto donde termina la ruta
  // -- se veían 2 puntos encimados en el mismo lugar. Para días pasados sí
  // tiene caso marcar "Última posición" (el pin en vivo está en otro lado).
  const esHoy = fecha === new Date().toISOString().slice(0, 10);
  try {
    const res = await fetch('../api/ruta_dia.php?fecha=' + fecha);
    const data = await res.json();
    if (!data.ok) return;

    capaRutas.clearLayers();
    const rutasFiltradas = filtroVendedorMapaId === 0
      ? data.rutas
      : data.rutas.filter(r => r.vendedor_id === filtroVendedorMapaId);
    rutasFiltradas.forEach((r, i) => {
      if (r.puntos.length < 2) return; // no hay recorrido que dibujar con 1 solo punto
      const color = PALETA_RUTAS[i % PALETA_RUTAS.length];

      // La línea se dibuja con los puntos YA AGRUPADOS (mismo agrupamiento
      // de 20m que ya se usaba para los marcadores), no con cada ping
      // crudo. El GPS "tiembla" unos metros en cada lectura aunque el
      // vendedor esté parado en el mismo lugar todo el día (ej. en la
      // oficina), y conectar esos pings crudos en orden de tiempo dibuja un
      // enredo de rayones en ese punto en vez de una ruta legible. Al usar
      // los grupos, la línea solo avanza cuando hay un movimiento real de
      // más de 20m.
      const nombreVendedor = nombresVendedores[r.vendedor_id] || 'Vendedor';
      const grupos = agruparPuntosCercanos(r.puntos);
      if (grupos.length >= 2) {
        const latlngs = grupos.map(g => [g.lat, g.lng]);
        L.polyline(latlngs, { color, weight: 3, opacity: 0.65, lineJoin: 'round' }).addTo(capaRutas);
      }

      // Un marcador por grupo de posiciones cercanas (no por cada ping
      // crudo) -- inicio y última posición del día más grandes para verlos
      // de un vistazo, las paradas intermedias con su rango de horas
      // completo, los pasos rápidos chicos. Todos responden con su hora al
      // pasar el cursor y hacen el mismo zoom animado que el pin en vivo del
      // vendedor si se les da clic.
      grupos.forEach((g, idx) => {
        const esInicio = idx === 0;
        const esFin = idx === grupos.length - 1;
        // Si es el único grupo del día (el vendedor no se ha movido de un
        // lugar) igual se muestra -- "Inicio del día" no es redundante con
        // el pin en vivo, que no dice desde cuándo está ahí.
        if (esFin && esHoy && !esInicio) return; // ese lugar ya lo marca el pin en vivo de arriba
        const destacado = esInicio || esFin;
        const esRango = g.fin !== g.inicio;
        // Un grupo de un solo ping que no es inicio/fin ni una parada real
        // (esRango) es solo un punto de paso mientras el vendedor iba
        // manejando de un lugar a otro -- ej. el camino de su casa a la
        // empresa. La línea de la ruta (arriba) ya representa ese tramo;
        // poner un círculo por cada uno de esos pasos satura el mapa de
        // puntos sin aportar información nueva, así que solo se dibuja
        // marcador para inicio/fin del día y paradas donde sí se quedó.
        if (!destacado && !esRango) return;
        const etiqueta = esInicio ? 'Inicio del día' : esFin ? 'Última posición' : null;
        const horaTexto = esRango
          ? `${horaSoloUTC(g.inicio.fecha_hora)} – ${horaSoloUTC(g.fin.fecha_hora)}`
          : horaSoloUTC(g.inicio.fecha_hora);
        const tooltipBase = `
          <span class="vendedor"><i class="bi bi-signpost-2-fill"></i> ${nombreVendedor}</span>
          <span class="detalle">${horaTexto}${etiqueta ? ` — <span class="destacado">${etiqueta}</span>` : ''}</span>`;
        const punto = L.circleMarker([g.lat, g.lng], {
          radius: destacado ? 7 : (esRango ? 5 : 3),
          color: '#fff',
          weight: destacado ? 2 : 1,
          fillColor: color,
          fillOpacity: destacado ? 1 : 0.75,
        })
          .bindTooltip(tooltipBase, { direction: 'top', offset: [0, -6], className: 'visitas-ruta-tooltip' })
          .on('click', function () { mapa.flyTo(this.getLatLng(), 16, { duration: 1.1 }); })
          .addTo(capaRutas);

        // Calle/colonia exacta solo al pasar el cursor -- con un pequeño
        // retraso (que se cancela si el cursor ya se fue) para no disparar
        // una consulta por cada punto que el mouse solo atraviesa de paso.
        const claveCache = claveGeocode(g.lat, g.lng);
        let timerDireccion = null;
        punto.on('mouseover', () => {
          if (claveCache in direccionesCache) {
            if (direccionesCache[claveCache]) {
              punto.setTooltipContent(tooltipBase + `<span class="direccion"><i class="bi bi-geo-alt-fill"></i> ${direccionesCache[claveCache]}</span>`);
            }
            return;
          }
          timerDireccion = setTimeout(async () => {
            punto.setTooltipContent(tooltipBase + '<span class="direccion cargando">Buscando dirección…</span>');
            const direccion = await obtenerDireccionPunto(g.lat, g.lng);
            punto.setTooltipContent(direccion ? tooltipBase + `<span class="direccion"><i class="bi bi-geo-alt-fill"></i> ${direccion}</span>` : tooltipBase);
          }, 350);
        });
        punto.on('mouseout', () => clearTimeout(timerDireccion));
      });
    });
  } catch (e) { /* silencioso */ }
}

// ── Calendario de fecha (Flatpickr) en vez del <input type=date> nativo ──
function initCalendarioFecha() {
  if (typeof flatpickr === 'undefined') return; // CDN no cargó: se queda el input nativo, sigue funcionando
  flatpickr('#filtro-fecha', {
    locale: 'es',
    dateFormat: 'Y-m-d',
    altInput: true,
    altFormat: 'j \\d\\e F, Y',
    onChange: () => { cargarCitasHoy(); dibujarRutasDia(); actualizarUbicaciones(); },
  });
}

// ── Mini-gráfica de tendencia (citas de los últimos 7 días) ───────────────
async function initSparkline() {
  const canvas = document.getElementById('sparkline-visitas');
  if (!canvas || typeof Chart === 'undefined') return;
  try {
    const res = await fetch('../api/citas_semana.php');
    const data = await res.json();
    if (!data.ok) return;
    new Chart(canvas, {
      type: 'line',
      data: {
        labels: data.dias.map(d => d.fecha.slice(5)),
        datasets: [{
          data: data.dias.map(d => d.total),
          borderColor: '#FFD23F', backgroundColor: 'rgba(255,210,63,.12)',
          fill: true, tension: 0.35, pointRadius: 2, borderWidth: 2,
        }],
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { enabled: true } },
        scales: { x: { display: false }, y: { display: false, beginAtZero: true } },
      },
    });
  } catch (e) { /* silencioso */ }
}

function badgeEstado(cita) {
  if (cita.retrasada) return '<span class="v26-pill v26-pill--retrasada">Retrasada</span>';
  const map = { pendiente: 'Pendiente', en_curso: 'En curso', completada: 'Completada', no_realizada: 'No realizada', cancelada: 'Cancelada' };
  return `<span class="v26-pill v26-pill--${cita.estado}">${map[cita.estado] || cita.estado}</span>`;
}

// Mismo criterio de etiquetas que INTERES_CLIENTE en assets/js/vendedor.js
// -- duplicado aquí porque admin y vendedor son bundles de JS separados.
const INTERES_CLIENTE_ADMIN = {
  bajo: 'Poco interesado', medio: 'Interés medio', interesado: 'Interesado', muy_interesado: 'Muy interesado',
};
function badgeInteres(interes) {
  if (!interes || !INTERES_CLIENTE_ADMIN[interes]) return '';
  return `<span class="v26-pill v26-pill--interes-${interes}">${INTERES_CLIENTE_ADMIN[interes]}</span>`;
}

// Una línea por check-in (entrada/salida) en vez de una sola píldora
// ambigua -- antes "Fuera de zona" no decía si fue al llegar o al salir.
// correccion: estado de la corrección del pin que hizo esa entrada (ver
// admin/ubicaciones.php); distancia es entonces la del pin anterior.
function lineaCheckin(etiqueta, verificado, fechaHora, distancia, correccion) {
  if (verificado === null || verificado === undefined) return '';
  const claseDot = verificado == 1 ? 'ok' : 'no';
  const estadoTxt = verificado == 1
    ? (correccion === 'por_revisar' ? 'Ubicación por revisar' : correccion === 'aprobada' ? 'Ubicación corregida por admin' : correccion ? 'Ubicación corregida por la app' : 'GPS verificado')
    : `Fuera de zona${distancia !== null && distancia !== undefined ? ' (' + Math.round(distancia) + ' m)' : ''}`;
  return `<div class="v26-checkin-linea"><span class="dot ${claseDot}"></span> <b>${etiqueta}</b> ${horaSoloUTC(fechaHora)} · ${estadoTxt}</div>`;
}
// Menos de esto entre entrada y salida se marca para revisar -- no da
// tiempo de una visita real (ej. Marcela: tomó la foto de salida por error
// justo después de la de entrada, 21 seg de diferencia).
const DURACION_MINIMA_SOSPECHOSA_SEG = 120;

function formatoDuracion(segundos) {
  if (segundos < 60) return `${Math.round(segundos)} seg`;
  const minutos = Math.round(segundos / 60);
  if (minutos < 60) return `${minutos} min`;
  const horas = Math.floor(minutos / 60);
  const minRestantes = minutos % 60;
  return `${horas} h${minRestantes ? ' ' + minRestantes + ' min' : ''}`;
}

function lineaDuracion(entradaFechaHora, salidaFechaHora) {
  if (!entradaFechaHora || !salidaFechaHora) return '';
  const inicio = new Date(String(entradaFechaHora).replace(' ', 'T') + (String(entradaFechaHora).endsWith('Z') ? '' : 'Z'));
  const fin = new Date(String(salidaFechaHora).replace(' ', 'T') + (String(salidaFechaHora).endsWith('Z') ? '' : 'Z'));
  if (isNaN(inicio.getTime()) || isNaN(fin.getTime())) return '';
  const segundos = (fin.getTime() - inicio.getTime()) / 1000;
  if (segundos < 0) return ''; // dato inconsistente -- mejor no mostrar nada confuso
  const esCorta = segundos < DURACION_MINIMA_SOSPECHOSA_SEG;
  const icono = esCorta ? 'bi-exclamation-triangle-fill' : 'bi-clock';
  const texto = esCorta ? `Visita de ${formatoDuracion(segundos)} -- revisar` : `Visita de ${formatoDuracion(segundos)}`;
  return `<div class="v26-duracion ${esCorta ? 'corta' : 'normal'}"><i class="bi ${icono}"></i> ${texto}</div>`;
}

function lineasCheckin(c) {
  const partes = [
    lineaCheckin('Entrada', c.checkin_verificado, c.entrada_fecha_hora, c.entrada_distancia_metros, c.checkin_correccion),
    lineaCheckin('Salida', c.checkin_verificado_salida, c.salida_fecha_hora, c.salida_distancia_metros),
    lineaDuracion(c.entrada_fecha_hora, c.salida_fecha_hora),
  ].filter(Boolean);
  return partes.length ? `<div class="v26-checkin-lineas">${partes.join('')}</div>` : '';
}

function escapeAttr(s) {
  return String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/"/g, '&quot;')
    .replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function botonFoto(checkinId, etiqueta, clienteNombre) {
  const url = `../api/foto.php?checkin_id=${checkinId}`;
  const titulo = `Foto de ${etiqueta.toLowerCase()} — ${clienteNombre || ''}`;
  return `<button type="button" class="v26-foto-thumb" data-foto-url="${url}" data-foto-titulo="${escapeAttr(titulo)}" title="Ver foto de ${etiqueta.toLowerCase()}">
    <img src="${url}" alt="${etiqueta}" loading="lazy">
    <span class="v26-foto-tag">${etiqueta}</span>
  </button>`;
}

function fotosCita(c) {
  const partes = [];
  if (c.foto_entrada_id) partes.push(botonFoto(c.foto_entrada_id, 'Entrada', c.cliente_nombre));
  if (c.foto_salida_id) partes.push(botonFoto(c.foto_salida_id, 'Salida', c.cliente_nombre));
  return partes.length ? `<div class="v26-foto-group">${partes.join('')}</div>` : '<span class="v26-foto-vacio">Sin fotos</span>';
}

function verFoto(url, titulo) {
  const img = document.getElementById('foto-modal-img');
  const tit = document.getElementById('foto-modal-titulo');
  if (img) img.src = url;
  if (tit) tit.textContent = titulo || '';
  const modalEl = document.getElementById('modalFoto');
  if (modalEl && window.bootstrap) bootstrap.Modal.getOrCreateInstance(modalEl).show();
}

document.addEventListener('click', (e) => {
  const btn = e.target.closest('.v26-foto-thumb');
  if (!btn) return;
  verFoto(btn.dataset.fotoUrl, btn.dataset.fotoTitulo);
});

// La hora de la cita ya viene en texto local de CDMX tal cual la capturó el
// vendedor (ver api/citas.php) — se toma el substring directo, sin pasar por
// Date/toLocaleTimeString, para no arriesgar un corrimiento de zona horaria.
function horaDeCita(fechaHora) {
  return String(fechaHora).slice(11, 16) || fechaHora;
}

function tarjetaVisita(c) {
  const claseEstado = c.retrasada ? 'retrasada' : c.estado;
  const reprog = piezasReprogramacionCita(c); // assets/js/admin_historial_cita.js
  const avatarVendedor = c.vendedor_foto
    ? `<img class="v26-mini-avatar" src="../${c.vendedor_foto}" alt="">`
    : '<i class="bi bi-person-badge-fill"></i>';
  return `
    <div class="v26-cita-card v26-cita-card--${claseEstado}">
      <div class="v26-cita-hora-solo"><span class="hora">${horaDeCita(c.fecha_hora)}</span></div>
      <div class="v26-cita-info">
        <div class="vendedor">${avatarVendedor} ${c.vendedor_nombre}</div>
        <div class="cliente">${c.cliente_nombre} ${badgeInteres(c.interes)}</div>
        <div class="direccion"><i class="bi bi-geo-alt"></i> ${c.direccion || 'Sin dirección'}</div>
        ${c.notas ? `<div class="notas"><i class="bi bi-chat-left-text"></i> ${c.notas}</div>` : ''}
        ${reprog.linea}
        ${lineasCheckin(c)}
      </div>
      <div class="v26-cita-estado">
        ${badgeEstado(c)}
        ${reprog.pills}
      </div>
      <div class="v26-cita-fotos">
        ${fotosCita(c)}
      </div>
    </div>
  `;
}

async function cargarCitasHoy() {
  const cont = document.getElementById('tabla-citas');
  const fecha = document.getElementById('filtro-fecha').value;
  const conteo = document.getElementById('conteo-citas-dia');
  cont.innerHTML = '<p class="text-muted small px-2 mb-0">Cargando...</p>';
  try {
    const res = await fetch('../api/citas.php?fecha=' + fecha);
    const data = await res.json();
    if (!data.ok) { cont.innerHTML = `<p class="text-danger small px-2">${data.error}</p>`; if (conteo) conteo.textContent = ''; return; }
    if (conteo) conteo.textContent = data.citas.length ? String(data.citas.length) : '';
    if (data.citas.length === 0) {
      cont.innerHTML = `
        <div class="v26-empty">
          <div class="icon"><i class="bi bi-calendar2-x"></i></div>
          <p>Sin visitas registradas para esta fecha.</p>
        </div>`;
      return;
    }
    cont.innerHTML = data.citas.map(tarjetaVisita).join('');
  } catch (e) {
    cont.innerHTML = '<p class="text-danger small px-2">Error al cargar citas.</p>';
  }
}

// ---------------------------------------------------------------------
// Alertas (recuadro derecho) -- rediseño etapa 1, 30-sep-2026. El servidor
// (includes/alertas.php) genera las alertas, les pone prioridad y cierra
// solas las que ya no aplican; aquí solo se filtran, se agrupan por
// vendedor y se les ponen botones según el tipo.
// ---------------------------------------------------------------------
const ICONO_ALERTA = {
  ubicacion_por_revisar: 'bi-geo-alt-fill',
  visita_sin_cerrar: 'bi-door-open',
  fuera_de_zona: 'bi-bullseye',
  sin_gps: 'bi-reception-0',
  problemas_acceso: 'bi-shield-exclamation',
  visita_corta: 'bi-stopwatch',
  interesado_sin_cotizacion: 'bi-star-fill',
  reprogramaciones: 'bi-arrow-left-right',
  cita_perdida: 'bi-calendar-x',
  sin_seguimiento: 'bi-arrow-repeat',
  sin_actividad: 'bi-person-dash',
};
const ORDEN_PRIORIDAD = { crit: 0, warn: 1, info: 2 };
const ETIQUETA_PRIORIDAD = { crit: 'atender', warn: 'revisar', info: 'info' };

let alertasCache = [];
let alertasSolasCache = [];
let filtroAlertas = 'todas';
let vistaAlertas = 'grupo';
const gruposAlertaCerrados = new Set(); // vendedor_id que el admin plegó (se respeta en cada refresco)

function urlDetalleVendedor(a, tab) {
  const extra = tab === 'prospeccion' ? `&dia=${encodeURIComponent(a.dia)}` : '';
  return `vendedor_detalle.php?id=${a.vendedor_id}&tab=${tab}${extra}`;
}

function botonesAlerta(a) {
  const b = [];
  const link = (texto, href, prim) => `<a class="v26-al-btn${prim ? ' prim' : ''}" href="${href}">${texto}</a>`;
  const foto = (id, etiqueta, prim) => `<button type="button" class="v26-al-btn${prim ? ' prim' : ''}" data-foto-alerta="${id}" data-foto-titulo="${escapeAttr(`Foto de ${etiqueta} — ${a.cliente_nombre || ''}`)}">Ver foto de ${etiqueta}</button>`;
  switch (a.tipo) {
    case 'ubicacion_por_revisar':
      if (a.correccion_id) {
        b.push(`<button type="button" class="v26-al-btn prim" data-ubicacion="aprobar" data-correccion="${a.correccion_id}">Aprobar ubicación</button>`);
        b.push(`<button type="button" class="v26-al-btn peligro" data-ubicacion="revertir" data-correccion="${a.correccion_id}">Revertir</button>`);
      }
      b.push(link('Ver en Ubicaciones', 'ubicaciones.php'));
      break;
    case 'fuera_de_zona':
      if (a.foto_entrada_id) b.push(foto(a.foto_entrada_id, 'entrada', true));
      b.push(link('Ver recorrido', urlDetalleVendedor(a, 'prospeccion')));
      break;
    case 'visita_corta':
      if (a.foto_entrada_id) b.push(foto(a.foto_entrada_id, 'entrada', true));
      if (a.foto_salida_id) b.push(foto(a.foto_salida_id, 'salida'));
      b.push(link('Ver cita', urlDetalleVendedor(a, 'todas')));
      break;
    case 'visita_sin_cerrar':
    case 'cita_perdida':
    case 'reprogramaciones':
      b.push(link('Ver cita', urlDetalleVendedor(a, 'todas'), true));
      if (a.tipo === 'visita_sin_cerrar') b.push(link('Ver recorrido', urlDetalleVendedor(a, 'prospeccion')));
      // Línea de tiempo con cada reprogramación y su motivo (assets/js/admin_historial_cita.js).
      else if (a.cita_id) b.push(`<button type="button" class="v26-al-btn" data-historial-cita="${a.cita_id}">Ver historial</button>`);
      break;
    case 'sin_gps':
    case 'sin_actividad':
      b.push(link('Ver su día', urlDetalleVendedor(a, 'prospeccion'), true));
      break;
    case 'problemas_acceso':
      b.push(link('Ver accesos', `bitacora.php?tab=accesos&vendedor=${a.vendedor_id}`, true));
      break;
    case 'interesado_sin_cotizacion':
    case 'sin_seguimiento':
      b.push(link('Ver clientes', urlDetalleVendedor(a, 'clientes'), true));
      break;
  }
  return b.join('');
}

function tarjetaAlerta(a, conVendedor) {
  const icono = ICONO_ALERTA[a.tipo] || 'bi-exclamation-circle';
  return `
    <div class="v26-al-item ${a.prioridad}">
      <span class="v26-al-ico"><i class="bi ${icono}"></i></span>
      <div class="v26-al-txt">
        <div class="v26-al-tipo">${a.titulo}</div>
        ${a.cliente_nombre ? `<div class="v26-al-tit">${a.cliente_nombre}</div>` : ''}
        ${conVendedor ? `<div class="v26-al-vend">${a.vendedor_nombre}</div>` : ''}
        <div class="v26-al-det">${a.mensaje}</div>
        <div class="v26-al-acciones">${botonesAlerta(a)}</div>
        <div class="v26-al-pie">
          <span>${formatearFechaUTC(a.created_at)}</span>
          <button type="button" class="v26-al-resolver" data-resolver="${a.id}">Marcar resuelta</button>
        </div>
      </div>
    </div>`;
}

function renderizarAlertas() {
  const cont = document.getElementById('lista-alertas');
  const cuenta = (p) => alertasCache.filter(a => a.prioridad === p).length;
  ['crit', 'warn', 'info'].forEach(p => { document.getElementById(`alertas-n-${p}`).textContent = cuenta(p); });
  document.getElementById('alertas-n-todas').textContent = alertasCache.length;
  document.querySelectorAll('.v26-al-filtros .opt').forEach(b => b.classList.toggle('active', b.dataset.filtro === filtroAlertas));
  document.querySelectorAll('.v26-al-vista .opt').forEach(b => b.classList.toggle('active', b.dataset.vista === vistaAlertas));

  const visibles = alertasCache
    .filter(a => filtroAlertas === 'todas' || a.prioridad === filtroAlertas)
    .sort((x, y) => ORDEN_PRIORIDAD[x.prioridad] - ORDEN_PRIORIDAD[y.prioridad]);

  if (visibles.length === 0) {
    cont.innerHTML = `<p class="text-muted small mb-0">${alertasCache.length ? 'No hay alertas con este filtro.' : 'Sin alertas por ahora.'}</p>`;
  } else if (vistaAlertas === 'lista') {
    cont.innerHTML = `<div class="v26-al-grupo"><div class="v26-al-cuerpo">${visibles.map(a => tarjetaAlerta(a, true)).join('')}</div></div>`;
  } else {
    const grupos = new Map();
    visibles.forEach(a => {
      if (!grupos.has(a.vendedor_id)) grupos.set(a.vendedor_id, []);
      grupos.get(a.vendedor_id).push(a);
    });
    const peor = (lista) => Math.min(...lista.map(a => ORDEN_PRIORIDAD[a.prioridad]));
    const ordenados = [...grupos.entries()].sort((x, y) => peor(x[1]) - peor(y[1]) || y[1].length - x[1].length);
    cont.innerHTML = ordenados.map(([vid, lista]) => {
      const v = lista[0];
      const chips = ['crit', 'warn', 'info'].map(p => {
        const n = lista.filter(a => a.prioridad === p).length;
        return n ? `<span class="v26-al-chip ${p}">${n} ${ETIQUETA_PRIORIDAD[p]}</span>` : '';
      }).join('');
      // Misma ruta de foto que las tarjetas de visitas (ver tarjetaVisita).
      const avatar = v.vendedor_foto
        ? `<img class="v26-al-avatar" src="../${v.vendedor_foto}" alt="">`
        : `<span class="v26-al-avatar">${inicialesAlerta(v.vendedor_nombre)}</span>`;
      return `
        <details class="v26-al-grupo" data-vendedor="${vid}" ${gruposAlertaCerrados.has(String(vid)) ? '' : 'open'}>
          <summary>
            ${avatar}
            <span class="v26-al-quien"><b>${v.vendedor_nombre}</b><span>${lista.length} alerta${lista.length === 1 ? '' : 's'}</span></span>
            <span class="v26-al-chips">${chips}</span>
            <i class="bi bi-chevron-down v26-al-flecha"></i>
          </summary>
          <div class="v26-al-cuerpo">${lista.map(a => tarjetaAlerta(a, false)).join('')}</div>
        </details>`;
    }).join('');
  }

  const solasWrap = document.getElementById('alertas-solas-wrap');
  solasWrap.hidden = alertasSolasCache.length === 0;
  document.getElementById('alertas-n-solas').textContent = alertasSolasCache.length;
  document.getElementById('alertas-solas').innerHTML = alertasSolasCache.map(s => `
    <div class="v26-al-sola">
      <div><b>${s.titulo}${s.cliente_nombre ? ' · ' + s.cliente_nombre : ''}</b>
        <span>${s.vendedor_nombre}: ${s.mensaje}</span></div>
    </div>`).join('');
}

function inicialesAlerta(nombre) {
  const partes = String(nombre || '?').trim().split(/\s+/);
  return ((partes[0] || '?')[0] + (partes[1] ? partes[1][0] : '')).toUpperCase();
}

async function cargarAlertas() {
  try {
    const res = await fetch('../api/alertas.php');
    const data = await res.json();
    if (!data.ok) return;
    alertasCache = data.alertas || [];
    alertasSolasCache = data.resueltas_solas || [];
    const rev = document.getElementById('alertas-revisadas');
    if (rev) rev.innerHTML = `<span class="dot"></span> Revisadas a las ${new Date().toLocaleTimeString('es-MX', { hour: 'numeric', minute: '2-digit' })}`;
    renderizarAlertas();
  } catch (e) { /* silencioso */ }
}

async function resolverAlerta(id) {
  const fd = new FormData();
  fd.append('id', id);
  await fetch('../api/alertas.php', { method: 'POST', body: fd });
  cargarAlertas();
}

// Aprobar / revertir la corrección de ubicación desde la misma alerta (misma
// API que admin/ubicaciones.php); la alerta se cierra sola en la siguiente
// revisión del servidor, aquí se quita de una vez para que se note.
async function accionUbicacionAlerta(accion, correccionId, btn) {
  if (accion === 'revertir' && !confirm('¿Revertir? El cliente regresa a la ubicación registrada y esa visita queda "Fuera de zona".')) return;
  btn.disabled = true;
  const fd = new FormData();
  fd.append('action', accion);
  fd.append('id', correccionId);
  try {
    const res = await fetch('../api/admin_ubicaciones.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.ok) { alert(data.error || 'No se pudo guardar.'); btn.disabled = false; return; }
    alertasCache = alertasCache.filter(a => String(a.correccion_id) !== String(correccionId));
    renderizarAlertas();
    cargarAlertas();
  } catch (e) {
    alert('No se pudo guardar. Revisa tu conexión.');
    btn.disabled = false;
  }
}

document.addEventListener('click', (e) => {
  const filtro = e.target.closest('[data-filtro]');
  if (filtro && filtro.closest('.v26-al')) { filtroAlertas = filtro.dataset.filtro; renderizarAlertas(); return; }
  const vista = e.target.closest('.v26-al-vista [data-vista]');
  if (vista) { vistaAlertas = vista.dataset.vista; renderizarAlertas(); return; }
  const res = e.target.closest('[data-resolver]');
  if (res) { res.disabled = true; resolverAlerta(res.dataset.resolver); return; }
  const ubi = e.target.closest('[data-ubicacion]');
  if (ubi) { accionUbicacionAlerta(ubi.dataset.ubicacion, ubi.dataset.correccion, ubi); return; }
  const foto = e.target.closest('[data-foto-alerta]');
  if (foto) { verFoto(`../api/foto.php?checkin_id=${foto.dataset.fotoAlerta}`, foto.dataset.fotoTitulo); }
});

// Recordar qué grupos plegó el admin, para que el refresco de cada 20 s no
// los vuelva a abrir.
document.addEventListener('toggle', (e) => {
  const g = e.target;
  if (!g.classList || !g.classList.contains('v26-al-grupo') || !g.dataset.vendedor) return;
  if (g.open) gruposAlertaCerrados.delete(g.dataset.vendedor);
  else gruposAlertaCerrados.add(g.dataset.vendedor);
}, true);

function refrescarTodo() {
  actualizarUbicaciones();
  cargarCitasHoy();
  cargarAlertas();
  dibujarRutasDia();
  cargarEmbudo();
}

// ---------------------------------------------------------------------
// Embudo de ventas de todo el equipo (tarjeta arriba del dashboard).
// Mismas etapas que ETAPAS_CLIENTE en assets/js/vendedor.js -- duplicadas
// aquí porque admin y vendedor son bundles de JS separados.
// ---------------------------------------------------------------------
const ETAPAS_EMBUDO = [
  { valor: 'prospecto_agregado',   etiqueta: 'Prospecto',               icono: 'bi-person-plus' },
  { valor: 'contacto_establecido', etiqueta: 'Contacto establecido',    icono: 'bi-telephone' },
  { valor: 'reunion_presentacion', etiqueta: 'Reunión de presentación', icono: 'bi-people' },
  { valor: 'propuesta_enviada',    etiqueta: 'Propuesta enviada',       icono: 'bi-file-earmark-text' },
  { valor: 'convertido',           etiqueta: 'Convertido',              icono: 'bi-trophy' },
  { valor: 'perdido',              etiqueta: 'Perdido',                 icono: 'bi-x-circle' },
];

async function cargarEmbudo() {
  const cont = document.getElementById('embudo-pills');
  const tasaEl = document.getElementById('embudo-tasa');
  if (!cont) return;
  try {
    const res = await fetch('../api/embudo.php');
    const data = await res.json();
    if (!data.ok) { cont.innerHTML = `<p class="text-danger small mb-0">${data.error}</p>`; return; }
    if (data.total === 0) {
      cont.innerHTML = '<p class="text-muted small mb-0">Todavía no hay prospectos ni clientes registrados.</p>';
      tasaEl.textContent = '';
      return;
    }
    cont.innerHTML = ETAPAS_EMBUDO.map(e => `
      <span class="v26-pill v26-pill--etapa-${e.valor}"><i class="bi ${e.icono}"></i> ${e.etiqueta} · ${data.conteos[e.valor] ?? 0}</span>
    `).join('');
    tasaEl.textContent = `${data.tasa_conversion}% de conversión (${data.conteos.convertido} de ${data.total})`;
  } catch (e) {
    cont.innerHTML = '<p class="text-danger small mb-0">No se pudo cargar el embudo.</p>';
  }
}
