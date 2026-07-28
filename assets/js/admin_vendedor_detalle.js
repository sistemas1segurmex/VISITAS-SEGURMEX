// Detalle de un vendedor visto desde el panel del dueño.

const params = new URLSearchParams(window.location.search);
const vendedorId = params.get('id');

function badgeEstado(estado) {
  const map = { pendiente: 'Pendiente', en_curso: 'En curso', completada: 'Completada', no_realizada: 'No realizada' };
  return `<span class="badge badge-${estado}">${map[estado] || estado}</span>`;
}

function badgeVerificado(v) {
  if (v === null || v === undefined) return '<span class="text-muted small">Sin check-in</span>';
  return v == 1
    ? '<span class="badge badge-verificado">GPS verificado</span>'
    : '<span class="badge badge-noverificado">Fuera de zona</span>';
}

async function cargarResumen() {
  const res = await fetch(`../api/admin_vendedor.php?vendedor_id=${vendedorId}&accion=resumen`);
  const data = await res.json();
  if (!data.ok) {
    document.getElementById('encabezado-vendedor').innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
    return;
  }
  document.getElementById('encabezado-vendedor').innerHTML = `
    <h5 class="mb-1">${data.vendedor.nombre}</h5>
    <div class="text-muted small mb-3">${data.vendedor.email} ${data.vendedor.telefono ? '· ' + data.vendedor.telefono : ''} ${data.vendedor.estado_operacion ? '· ' + data.vendedor.estado_operacion : ''}</div>
    <div class="row g-2 mb-3">
      <div class="col-6 col-md-3"><div class="card card-cita"><div class="card-body py-2"><div class="text-muted small">Clientes registrados</div><div class="fs-4">${data.total_clientes}</div></div></div></div>
      <div class="col-6 col-md-3"><div class="card card-cita"><div class="card-body py-2"><div class="text-muted small">Citas totales</div><div class="fs-4">${data.total_citas}</div></div></div></div>
      <div class="col-6 col-md-3"><div class="card card-cita"><div class="card-body py-2"><div class="text-muted small">Próximas citas</div><div class="fs-4">${data.proximas_citas}</div></div></div></div>
      <div class="col-6 col-md-3"><div class="card card-cita"><div class="card-body py-2"><div class="text-muted small">Check-ins verificados</div><div class="fs-4">${data.checkins_verificados}</div></div></div></div>
    </div>
  `;
}

function filaCita(c) {
  return `
    <tr>
      <td>${c.fecha_hora}</td>
      <td>${c.cliente_nombre}<br><span class="text-muted small">${c.direccion}</span></td>
      <td>${badgeEstado(c.estado)}</td>
      <td>${badgeVerificado(c.checkin_verificado)}</td>
      <td class="text-muted small">${c.notas || ''}</td>
    </tr>
  `;
}

async function cargarCitas(accion, tablaId) {
  const cuerpo = document.getElementById(tablaId);
  cuerpo.innerHTML = '<tr><td colspan="5" class="text-muted">Cargando...</td></tr>';
  const res = await fetch(`../api/admin_vendedor.php?vendedor_id=${vendedorId}&accion=${accion}`);
  const data = await res.json();
  if (!data.ok) { cuerpo.innerHTML = `<tr><td colspan="5" class="text-danger">${data.error}</td></tr>`; return; }
  if (data.citas.length === 0) { cuerpo.innerHTML = '<tr><td colspan="5" class="text-muted">Sin citas.</td></tr>'; return; }
  cuerpo.innerHTML = data.citas.map(filaCita).join('');
}

async function cargarClientes() {
  const cont = document.getElementById('lista-clientes-vendedor');
  cont.innerHTML = '<p class="text-muted">Cargando...</p>';
  const res = await fetch(`../api/admin_vendedor.php?vendedor_id=${vendedorId}&accion=clientes`);
  const data = await res.json();
  if (!data.ok) { cont.innerHTML = `<div class="alert alert-danger">${data.error}</div>`; return; }
  if (data.clientes.length === 0) { cont.innerHTML = '<p class="text-muted">Este vendedor aún no tiene clientes registrados.</p>'; return; }
  cont.innerHTML = data.clientes.map(c => `
    <div class="card mb-2"><div class="card-body py-2 d-flex justify-content-between align-items-center">
      <div><strong>${c.nombre}</strong><br><span class="text-muted small">${c.direccion}</span></div>
      ${c.lat ? '<span class="badge badge-verificado">GPS ok</span>' : '<span class="badge bg-warning text-dark">Sin ubicación</span>'}
    </div></div>
  `).join('');
}

cargarResumen();
cargarCitas('citas_proximas', 'tabla-proximas');

document.getElementById('tab-todas').addEventListener('shown.bs.tab', () => cargarCitas('citas_todas', 'tabla-todas'), { once: true });
document.getElementById('tab-clientes').addEventListener('shown.bs.tab', () => cargarClientes(), { once: true });
