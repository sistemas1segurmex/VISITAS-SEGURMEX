// Dashboard del dueño/admin: mapa en vivo + listado de citas del día + alertas.

let mapa, marcadoresVendedores = {};

function initMapa() {
  mapa = L.map('mapa').setView([23.6345, -102.5528], 5);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(mapa);
}

async function actualizarUbicaciones() {
  try {
    const res = await fetch('../api/tracking.php');
    const data = await res.json();
    if (!data.ok) return;

    const vistos = new Set();
    data.ubicaciones.forEach(u => {
      vistos.add(u.vendedor_id);
      const popup = `<strong>${u.nombre}</strong><br>${u.estado_operacion || ''}<br><span class="text-muted">Última señal: ${u.fecha_hora}</span>`;
      if (marcadoresVendedores[u.vendedor_id]) {
        marcadoresVendedores[u.vendedor_id].setLatLng([u.lat, u.lng]).setPopupContent(popup);
      } else {
        marcadoresVendedores[u.vendedor_id] = L.marker([u.lat, u.lng]).addTo(mapa).bindPopup(popup);
      }
    });

    // Quita del mapa a quien ya no está en la lista (por si algo cambia).
    Object.keys(marcadoresVendedores).forEach(id => {
      if (!vistos.has(Number(id))) {
        mapa.removeLayer(marcadoresVendedores[id]);
        delete marcadoresVendedores[id];
      }
    });

    document.getElementById('resumen-vendedores').textContent =
      data.ubicaciones.length + ' vendedor(es) reportando ubicación hoy';
  } catch (e) { /* silencioso: se reintenta en el próximo ciclo */ }
}

function badgeEstado(cita) {
  if (cita.retrasada) return '<span class="badge badge-pendiente">Retrasada</span>';
  const map = { pendiente: 'Pendiente', en_curso: 'En curso', completada: 'Completada', no_realizada: 'No realizada' };
  return `<span class="badge badge-${cita.estado}">${map[cita.estado] || cita.estado}</span>`;
}

function badgeVerificado(cita) {
  if (cita.checkin_verificado === null || cita.checkin_verificado === undefined) return '<span class="text-muted small">Sin check-in aún</span>';
  return cita.checkin_verificado == 1
    ? '<span class="badge badge-verificado">GPS verificado</span>'
    : '<span class="badge badge-noverificado">Fuera de zona</span>';
}

async function cargarCitasHoy() {
  const cuerpo = document.getElementById('tabla-citas');
  const fecha = document.getElementById('filtro-fecha').value;
  try {
    const res = await fetch('../api/citas.php?fecha=' + fecha);
    const data = await res.json();
    if (!data.ok) { cuerpo.innerHTML = `<tr><td colspan="5" class="text-danger">${data.error}</td></tr>`; return; }
    if (data.citas.length === 0) { cuerpo.innerHTML = '<tr><td colspan="5" class="text-muted">Sin citas para esta fecha.</td></tr>'; return; }
    cuerpo.innerHTML = data.citas.map(c => `
      <tr>
        <td>${c.vendedor_nombre}</td>
        <td>${c.cliente_nombre}<br><span class="text-muted small">${c.direccion}</span></td>
        <td>${c.fecha_hora}</td>
        <td>${badgeEstado(c)}</td>
        <td>${badgeVerificado(c)}</td>
      </tr>
    `).join('');
  } catch (e) {
    cuerpo.innerHTML = '<tr><td colspan="5" class="text-danger">Error al cargar citas.</td></tr>';
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
        <div><strong>${a.vendedor_nombre}</strong> — ${a.mensaje}<br><span class="text-muted small">${a.created_at}</span></div>
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
