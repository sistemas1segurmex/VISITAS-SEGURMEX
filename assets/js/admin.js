// Dashboard del dueño/admin: mapa en vivo + listado de citas del día + alertas.

let mapa, marcadoresVendedores = {}, tooltipsVendedores = {};
let capaRutas = null;               // L.layerGroup con las polylines del día
const PALETA_RUTAS = ['#4F46E5', '#F5A623', '#16A34A', '#E11D48', '#0EA5E9', '#9333EA', '#D97706'];
const nombresVendedores = {};       // vendedor_id -> nombre, para las etiquetas del recorrido

function initMapa() {
  mapa = L.map('mapa', { zoomControl: true }).setView([23.6345, -102.5528], 5);
  // CARTO empezó a exigir API key en su CDN de mapas base (antes era libre);
  // se cambia a los tiles de OpenStreetMap, gratis y sin key -- mismos que ya
  // usa el lado del vendedor (nuevo_cliente.php, editar_cliente.php).
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 19,
  }).addTo(mapa);
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

async function actualizarUbicaciones() {
  try {
    const res = await fetch('../api/tracking.php');
    const data = await res.json();
    if (!data.ok) return;

    const vistos = new Set();
    const sinUbicacion = [];
    let enLineaCount = 0, perdidaCount = 0;

    data.ubicaciones.forEach(u => {
      nombresVendedores[u.vendedor_id] = u.nombre;
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

    const totalConUbicacion = data.ubicaciones.length - sinUbicacion.length;
    let resumen = `${enLineaCount} en línea de ${totalConUbicacion} con ubicación registrada`;
    if (sinUbicacion.length > 0) {
      resumen += ` · sin ubicación aún: ${sinUbicacion.join(', ')}`;
    }
    document.getElementById('resumen-vendedores').textContent = resumen;

    // Contadores de la leyenda del mapa: "En línea"/"Conexión perdida" son
    // los grupos especiales; "Desconectado" es el resto de vendedores
    // activos, para que los 3 números siempre sumen el total de activos.
    const totalVendedores = data.ubicaciones.length;
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
  return isNaN(d.getTime()) ? fechaStr : d.toLocaleTimeString('es-MX', { hour: 'numeric', minute: '2-digit' });
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
// encimados casi en el mismo lugar, ilegible en el mapa. Se agrupan los
// puntos consecutivos que están a menos de 20 m del último ya agrupado: la
// LÍNEA sigue dibujando el trazo completo (sin perder precisión), pero solo
// se pone un marcador visible por grupo, con el rango de horas completo de
// esa parada en vez de un punto por cada ping individual.
const DISTANCIA_MIN_NUEVO_MARCADOR_M = 20;
function agruparPuntosCercanos(puntos) {
  const grupos = [];
  puntos.forEach(p => {
    const ultimo = grupos[grupos.length - 1];
    if (ultimo && distanciaMetrosMapa(ultimo.lat, ultimo.lng, p.lat, p.lng) < DISTANCIA_MIN_NUEVO_MARCADOR_M) {
      ultimo.fin = p;
    } else {
      grupos.push({ lat: p.lat, lng: p.lng, inicio: p, fin: p });
    }
  });
  return grupos;
}

// ── Líneas de recorrido del día seleccionado ──────────────────────────────
async function dibujarRutasDia() {
  const fecha = document.getElementById('filtro-fecha')?.value || new Date().toISOString().slice(0, 10);
  try {
    const res = await fetch('../api/ruta_dia.php?fecha=' + fecha);
    const data = await res.json();
    if (!data.ok) return;

    capaRutas.clearLayers();
    data.rutas.forEach((r, i) => {
      if (r.puntos.length < 2) return; // no hay recorrido que dibujar con 1 solo punto
      const color = PALETA_RUTAS[i % PALETA_RUTAS.length];
      const latlngs = r.puntos.map(p => [p.lat, p.lng]);
      L.polyline(latlngs, { color, weight: 3, opacity: 0.65, lineJoin: 'round' }).addTo(capaRutas);

      // Un marcador por grupo de posiciones cercanas (no por cada ping
      // crudo) -- inicio y última posición del día más grandes para verlos
      // de un vistazo, las paradas intermedias con su rango de horas
      // completo, los pasos rápidos chicos. Todos responden con su hora al
      // pasar el cursor y hacen el mismo zoom animado que el pin en vivo del
      // vendedor si se les da clic.
      const nombreVendedor = nombresVendedores[r.vendedor_id] || 'Vendedor';
      const grupos = agruparPuntosCercanos(r.puntos);
      grupos.forEach((g, idx) => {
        const esInicio = idx === 0;
        const esFin = idx === grupos.length - 1;
        const destacado = esInicio || esFin;
        const esRango = g.fin !== g.inicio;
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
    onChange: () => { cargarCitasHoy(); dibujarRutasDia(); },
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
  const map = { pendiente: 'Pendiente', en_curso: 'En curso', completada: 'Completada', no_realizada: 'No realizada' };
  return `<span class="v26-pill v26-pill--${cita.estado}">${map[cita.estado] || cita.estado}</span>`;
}

function badgeVerificado(cita) {
  if (cita.checkin_verificado === null || cita.checkin_verificado === undefined) return '<span class="text-muted small">Sin check-in aún</span>';
  return cita.checkin_verificado == 1
    ? '<span class="v26-pill v26-pill--verificado">GPS verificado</span>'
    : '<span class="v26-pill v26-pill--noverificado">Fuera de zona</span>';
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
  const avatarVendedor = c.vendedor_foto
    ? `<img class="v26-mini-avatar" src="../${c.vendedor_foto}" alt="">`
    : '<i class="bi bi-person-badge-fill"></i>';
  return `
    <div class="v26-cita-card v26-cita-card--${claseEstado}">
      <div class="v26-cita-hora-solo"><span class="hora">${horaDeCita(c.fecha_hora)}</span></div>
      <div class="v26-cita-info">
        <div class="vendedor">${avatarVendedor} ${c.vendedor_nombre}</div>
        <div class="cliente">${c.cliente_nombre}</div>
        <div class="direccion"><i class="bi bi-geo-alt"></i> ${c.direccion || 'Sin dirección'}</div>
        ${c.notas ? `<div class="notas"><i class="bi bi-chat-left-text"></i> ${c.notas}</div>` : ''}
      </div>
      <div class="v26-cita-estado">
        ${badgeEstado(c)}
        ${badgeVerificado(c)}
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

// Clasificación de alertas en 2 pestañas — el dato base (a.tipo) ya viene de
// la BD; esta agrupación es una convención nuestra, no algo definido antes:
//   Críticas    → fuera_de_zona, sin_actividad (algo urgente, requiere acción ya)
//   Sin solución → retraso, sin_checkin (para dar seguimiento, menos urgente)
// Pestañas ocultas por mientras (ver "d-none" en admin/index.php) -- la
// lista se muestra completa, sin filtrar. Para reactivarlas: quitar el
// "d-none" del HTML y regresar MOSTRAR_TABS_ALERTAS a true.
const TIPOS_ALERTA_CRITICAS = ['fuera_de_zona', 'sin_actividad'];
const MOSTRAR_TABS_ALERTAS = false;

let alertasCache = [];
let tabAlertaActiva = 'criticas';

function cambiarTabAlertas(tab) {
  tabAlertaActiva = tab;
  document.querySelectorAll('.v26-alert-tab').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.tab === tab);
  });
  renderizarAlertas();
}

function renderizarAlertas() {
  const cont = document.getElementById('lista-alertas');
  const filtradas = !MOSTRAR_TABS_ALERTAS ? alertasCache : alertasCache.filter(a =>
    tabAlertaActiva === 'criticas'
      ? TIPOS_ALERTA_CRITICAS.includes(a.tipo)
      : !TIPOS_ALERTA_CRITICAS.includes(a.tipo)
  );

  const nCriticas = alertasCache.filter(a => TIPOS_ALERTA_CRITICAS.includes(a.tipo)).length;
  const nSeguimiento = alertasCache.length - nCriticas;
  const elCriticas = document.getElementById('conteo-tab-criticas');
  const elSeguimiento = document.getElementById('conteo-tab-seguimiento');
  if (elCriticas) elCriticas.textContent = nCriticas ? `(${nCriticas})` : '';
  if (elSeguimiento) elSeguimiento.textContent = nSeguimiento ? `(${nSeguimiento})` : '';

  if (filtradas.length === 0) {
    cont.innerHTML = '<p class="text-muted small mb-0">Sin alertas por ahora.</p>';
    return;
  }
  cont.innerHTML = filtradas.map(a => `
    <div class="alert alert-warning py-2 d-flex justify-content-between align-items-start">
      <div><strong>${a.vendedor_nombre}</strong> — ${a.mensaje}<br><span class="text-muted small">${formatearFechaUTC(a.created_at)}</span></div>
      <button class="btn btn-sm btn-outline-secondary" onclick="resolverAlerta(${a.id})">Marcar vista</button>
    </div>
  `).join('');
}

async function cargarAlertas() {
  try {
    const res = await fetch('../api/alertas.php');
    const data = await res.json();
    if (!data.ok) return;
    alertasCache = data.alertas;
    renderizarAlertas();
  } catch (e) { /* silencioso */ }
}

async function resolverAlerta(id) {
  const fd = new FormData();
  fd.append('id', id);
  await fetch('../api/alertas.php', { method: 'POST', body: fd });
  cargarAlertas();
}

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
