// Dashboard del dueño/admin: mapa en vivo + listado de citas del día + alertas.

let mapa, marcadoresVendedores = {};

function initMapa() {
  mapa = L.map('mapa', { zoomControl: true }).setView([23.6345, -102.5528], 5);
  L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> © <a href="https://carto.com/attributions">CARTO</a>',
    subdomains: 'abcd',
    maxZoom: 19,
  }).addTo(mapa);
}

function iconoVendedor(enLinea) {
  return L.divIcon({
    className: 'visitas-marker-icon',
    html: `<span class="visitas-marker${enLinea ? '' : ' is-offline'}"><span class="visitas-marker-pulse"></span><span class="visitas-marker-dot"></span></span>`,
    iconSize: [18, 18],
    iconAnchor: [9, 9],
    popupAnchor: [0, -9],
  });
}

function aplicarEstiloEstado(marker, enLinea) {
  marker.setIcon(iconoVendedor(enLinea));
}

async function actualizarUbicaciones() {
  try {
    const res = await fetch('../api/tracking.php');
    const data = await res.json();
    if (!data.ok) return;

    const vistos = new Set();
    const sinUbicacion = [];
    let enLineaCount = 0;

    data.ubicaciones.forEach(u => {
      if (u.lat === null || u.lng === null) {
        sinUbicacion.push(u.nombre);
        return;
      }
      vistos.add(u.vendedor_id);
      if (u.en_linea == 1) enLineaCount++;
      const estadoTxt = u.en_linea == 1
        ? '<span class="text-success">🟢 En línea</span>'
        : `<span class="text-muted">⚪ Desconectado — última señal: ${formatearFechaUTC(u.fecha_hora)}</span>`;
      const popup = `<strong>${u.nombre}</strong><br>${u.estado_operacion || ''}<br>${estadoTxt}`;

      let marker = marcadoresVendedores[u.vendedor_id];
      if (marker) {
        marker.setLatLng([u.lat, u.lng]).setPopupContent(popup);
      } else {
        marker = L.marker([u.lat, u.lng], { icon: iconoVendedor(u.en_linea == 1) }).addTo(mapa).bindPopup(popup);
        marcadoresVendedores[u.vendedor_id] = marker;
      }
      aplicarEstiloEstado(marker, u.en_linea == 1);
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

    // Contadores de la leyenda del mapa: "En línea" son los que reportaron
    // hace ≤15 min; "Desconectado" es todo el resto de vendedores activos
    // (con señal vieja o sin ninguna ubicación registrada todavía), para que
    // ambos números siempre sumen el total de vendedores activos.
    const totalVendedores = data.ubicaciones.length;
    const conteoEnLinea = document.getElementById('conteo-en-linea');
    const conteoDesconectado = document.getElementById('conteo-desconectado');
    if (conteoEnLinea) conteoEnLinea.textContent = enLineaCount;
    if (conteoDesconectado) conteoDesconectado.textContent = totalVendedores - enLineaCount;
  } catch (e) { /* silencioso: se reintenta en el próximo ciclo */ }
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
  return `
    <div class="v26-cita-card v26-cita-card--${claseEstado}">
      <div class="v26-cita-hora-solo"><span class="hora">${horaDeCita(c.fecha_hora)}</span></div>
      <div class="v26-cita-info">
        <div class="vendedor"><i class="bi bi-person-badge-fill"></i> ${c.vendedor_nombre}</div>
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

async function cargarAlertas() {
  const cont = document.getElementById('lista-alertas');
  try {
    const res = await fetch('../api/alertas.php');
    const data = await res.json();
    if (!data.ok) return;
    if (data.alertas.length === 0) { cont.innerHTML = '<p class="text-muted small mb-0">Sin alertas pendientes.</p>'; return; }
    cont.innerHTML = data.alertas.map(a => `
      <div class="alert alert-warning py-2 d-flex justify-content-between align-items-start">
        <div><strong>${a.vendedor_nombre}</strong> — ${a.mensaje}<br><span class="text-muted small">${formatearFechaUTC(a.created_at)}</span></div>
        <button class="btn btn-sm btn-outline-secondary" onclick="resolverAlerta(${a.id})">Marcar vista</button>
      </div>
    `).join('');
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
}
