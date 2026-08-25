// Detalle de un vendedor visto desde el panel del dueño.

const params = new URLSearchParams(window.location.search);
const vendedorId = params.get('id');

function badgeEstado(estado) {
  const map = { pendiente: 'Pendiente', en_curso: 'En curso', completada: 'Completada', no_realizada: 'No realizada' };
  return `<span class="v26-pill v26-pill--${estado}">${map[estado] || estado}</span>`;
}

function badgeVerificado(v) {
  if (v === null || v === undefined) return '<span class="text-muted small">Sin check-in</span>';
  return v == 1
    ? '<span class="v26-pill v26-pill--verificado">GPS verificado</span>'
    : '<span class="v26-pill v26-pill--noverificado">Fuera de zona</span>';
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

const MESES_CORTOS = ['ENE','FEB','MAR','ABR','MAY','JUN','JUL','AGO','SEP','OCT','NOV','DIC'];

function partesFecha(fechaHora) {
  const d = new Date(String(fechaHora).replace(' ', 'T'));
  if (isNaN(d.getTime())) return { dia: '--', mes: '', hora: fechaHora };
  const hora = d.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
  return { dia: String(d.getDate()).padStart(2, '0'), mes: MESES_CORTOS[d.getMonth()] || '', hora };
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

function tarjetaCita(c) {
  const f = partesFecha(c.fecha_hora);
  return `
    <div class="v26-cita-card">
      <div class="v26-cita-fecha">
        <span class="dia">${f.dia}</span>
        <span class="mes">${f.mes}</span>
        <span class="hora">${f.hora}</span>
      </div>
      <div class="v26-cita-info">
        <div class="cliente">${c.cliente_nombre}</div>
        <div class="direccion"><i class="bi bi-geo-alt"></i> ${c.direccion || 'Sin dirección'}</div>
        ${c.notas ? `<div class="notas"><i class="bi bi-chat-left-text"></i> ${c.notas}</div>` : ''}
      </div>
      <div class="v26-cita-estado">
        ${badgeEstado(c.estado)}
        ${badgeVerificado(c.checkin_verificado)}
      </div>
      <div class="v26-cita-fotos">
        ${fotosCita(c)}
      </div>
    </div>
  `;
}

async function cargarCitas(accion, contenedorId) {
  const cont = document.getElementById(contenedorId);
  cont.innerHTML = '<p class="text-muted small px-2">Cargando...</p>';
  const res = await fetch(`../api/admin_vendedor.php?vendedor_id=${vendedorId}&accion=${accion}`);
  const data = await res.json();
  if (!data.ok) { cont.innerHTML = `<p class="text-danger small px-2">${data.error}</p>`; return; }
  if (data.citas.length === 0) { cont.innerHTML = '<p class="text-muted small px-2">Sin citas.</p>'; return; }
  cont.innerHTML = data.citas.map(tarjetaCita).join('');
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
      ${c.lat ? '<span class="v26-pill v26-pill--verificado">GPS ok</span>' : '<span class="v26-pill v26-pill--pendiente">Sin ubicación</span>'}
    </div></div>
  `).join('');
}

// ---------------------------------------------------------------------
// Pestaña Prospección: estado de hoy + % de cobertura del mes + tira de
// días (cita / prospección / cliente nuevo / solo GPS / sin actividad).
// ---------------------------------------------------------------------

const CATEGORIAS_PROSPECCION = {
  cita:              { label: 'Cita agendada',          icon: 'bi-calendar-check-fill',       clase: 'cita' },
  prospeccion:       { label: 'Prospección registrada', icon: 'bi-signpost-2-fill',           clase: 'prospeccion' },
  prospeccion_activa:{ label: 'En prospección ahora',   icon: 'bi-signpost-2-fill',           clase: 'prospeccion', activa: true },
  cliente:           { label: 'Cliente nuevo',          icon: 'bi-person-plus-fill',          clase: 'cliente' },
  ubicacion:         { label: 'Solo ubicación GPS',     icon: 'bi-geo-alt-fill',              clase: 'ubicacion' },
  sin_actividad:     { label: 'Sin actividad',          icon: 'bi-exclamation-triangle-fill', clase: 'sin-actividad' },
  futuro:            { label: 'Aún no llega',           icon: '',                              clase: 'futuro' },
};

function bannerHoy(estadoHoy) {
  if (!estadoHoy) return '';
  const c = CATEGORIAS_PROSPECCION[estadoHoy.categoria] || CATEGORIAS_PROSPECCION.sin_actividad;
  const detalle = estadoHoy.detalle ? ` — ${estadoHoy.detalle}` : '';
  const texto = estadoHoy.categoria === 'sin_actividad'
    ? 'Sin actividad registrada hoy todavía'
    : `${c.label}${detalle}`;
  return `
    <div class="v26-prosp-banner v26-prosp-banner--${c.clase}${c.activa ? ' activa' : ''}">
      <i class="bi ${c.icon}"></i>
      <span>Hoy: <strong>${texto}</strong></span>
    </div>`;
}

function tiraDias(dias) {
  const celdas = dias.map(d => {
    const c = CATEGORIAS_PROSPECCION[d.categoria] || CATEGORIAS_PROSPECCION.sin_actividad;
    const dia = Number(d.fecha.slice(-2));
    const titulo = d.categoria === 'futuro'
      ? `${d.fecha}`
      : `${d.fecha} — ${c.label}${d.detalle ? ': ' + d.detalle : ''}`;
    return `
      <div class="v26-prosp-celda v26-prosp-celda--${c.clase}${c.activa ? ' activa' : ''}" title="${titulo}">
        <span class="num">${dia}</span>
        ${c.icon ? `<i class="bi ${c.icon}"></i>` : ''}
      </div>`;
  }).join('');
  return `<div class="v26-prosp-tira">${celdas}</div>`;
}

function leyendaProspeccion() {
  const orden = ['cita', 'prospeccion', 'cliente', 'ubicacion', 'sin_actividad'];
  return `<div class="v26-prosp-leyenda">${orden.map(k => {
    const c = CATEGORIAS_PROSPECCION[k];
    return `<span class="item"><i class="bi ${c.icon} v26-prosp-celda--${c.clase}"></i>${c.label}</span>`;
  }).join('')}</div>`;
}

function resumenProspeccion(data, vista) {
  const conteo = data.conteo;
  const etiquetaPeriodo = vista === 'semana' ? 'esta semana' : 'este mes';
  return `
    <div class="v26-prosp-resumen">
      <div class="v26-prosp-pct">
        <div class="valor">${data.porcentaje_cobertura}%</div>
        <div class="etiqueta">de cobertura ${etiquetaPeriodo}<br>(${data.dias_cubiertos} de ${data.total_dias_considerados} días transcurridos)</div>
      </div>
      <div class="v26-prosp-conteos">
        <div><i class="bi bi-calendar-check-fill v26-prosp-celda--cita"></i> ${conteo.cita} con cita</div>
        <div><i class="bi bi-signpost-2-fill v26-prosp-celda--prospeccion"></i> ${conteo.prospeccion} en prospección</div>
        <div><i class="bi bi-person-plus-fill v26-prosp-celda--cliente"></i> ${conteo.cliente} solo cliente nuevo</div>
        <div><i class="bi bi-geo-alt-fill v26-prosp-celda--ubicacion"></i> ${conteo.ubicacion} solo GPS</div>
        <div><i class="bi bi-exclamation-triangle-fill v26-prosp-celda--sin-actividad"></i> ${conteo.sin_actividad} sin actividad</div>
      </div>
    </div>`;
}

async function cargarProspeccionMesOSemana(vista) {
  const cont = document.getElementById('contenido-prospeccion');
  cont.innerHTML = '<p class="text-muted small">Cargando...</p>';
  try {
    let url = `../api/admin_vendedor.php?vendedor_id=${vendedorId}&accion=prospeccion&vista=${vista}`;
    url += (vista === 'semana')
      ? `&semana=${document.getElementById('semana-prospeccion').value}`
      : `&mes=${document.getElementById('mes-prospeccion').value}`;
    const res = await fetch(url);
    const data = await res.json();
    if (!data.ok) { cont.innerHTML = `<p class="text-danger small">${data.error}</p>`; return; }
    cont.innerHTML = `
      ${bannerHoy(data.estado_hoy)}
      ${resumenProspeccion(data, vista)}
      ${tiraDias(data.dias)}
      ${leyendaProspeccion()}
    `;
  } catch (e) {
    cont.innerHTML = '<p class="text-danger small">Error al cargar prospección.</p>';
  }
}

// Vista "Día": aquí sí se ve el detalle completo (citas, jornada de
// prospección, clientes nuevos y GPS), no solo la categoría del día.
function renderDiaDetalle(data) {
  const c = CATEGORIAS_PROSPECCION[data.categoria] || CATEGORIAS_PROSPECCION.sin_actividad;
  const hayAlgo = data.citas.length || data.prospeccion || data.clientes.length || data.ubicaciones.total > 0;
  const partes = [];

  partes.push(`
    <div class="v26-prosp-banner v26-prosp-banner--${c.clase}">
      <i class="bi ${c.icon || 'bi-calendar2'}"></i>
      <span>${data.categoria === 'sin_actividad' ? 'Sin ninguna actividad registrada este día' : `${c.label}${data.detalle ? ' — ' + data.detalle : ''}`}</span>
    </div>`);

  if (data.citas.length) {
    partes.push(`<h6 class="mt-4 mb-2">Citas (${data.citas.length})</h6>`);
    partes.push(`<div class="v26-citas-list">${data.citas.map(tarjetaCita).join('')}</div>`);
  }

  if (data.prospeccion) {
    const ini = String(data.prospeccion.hora_inicio).slice(11, 16);
    const fin = data.prospeccion.hora_fin ? String(data.prospeccion.hora_fin).slice(11, 16) : null;
    partes.push(`
      <h6 class="mt-4 mb-2">Jornada de prospección</h6>
      <div class="v26-prosp-banner v26-prosp-banner--prospeccion">
        <i class="bi bi-signpost-2-fill"></i>
        <span>${ini}${fin ? ' – ' + fin : ' (sin cerrar todavía)'}</span>
      </div>`);
  }

  if (data.clientes.length) {
    partes.push(`<h6 class="mt-4 mb-2">Clientes nuevos (${data.clientes.length})</h6>`);
    partes.push(data.clientes.map(cl => `
      <div class="card mb-2"><div class="card-body py-2">
        <strong>${cl.nombre}</strong><br><span class="text-muted small">${cl.direccion || 'Sin dirección'}</span>
      </div></div>`).join(''));
  }

  if (data.ubicaciones.total > 0) {
    partes.push(`
      <h6 class="mt-4 mb-2">Ubicación GPS</h6>
      <p class="text-muted small">${data.ubicaciones.total} reporte(s) de ubicación, de ${String(data.ubicaciones.primera).slice(11, 16)} a ${String(data.ubicaciones.ultima).slice(11, 16)} (hora UTC del servidor).</p>`);
  }

  if (!hayAlgo) {
    partes.push(`
      <div class="v26-empty">
        <div class="icon"><i class="bi bi-calendar2-x"></i></div>
        <p>Sin ninguna actividad registrada este día.</p>
      </div>`);
  }

  return partes.join('');
}

async function cargarProspeccionDia(fecha) {
  const cont = document.getElementById('contenido-prospeccion');
  cont.innerHTML = '<p class="text-muted small">Cargando...</p>';
  try {
    const res = await fetch(`../api/admin_vendedor.php?vendedor_id=${vendedorId}&accion=prospeccion_dia&fecha=${fecha}`);
    const data = await res.json();
    if (!data.ok) { cont.innerHTML = `<p class="text-danger small">${data.error}</p>`; return; }
    cont.innerHTML = renderDiaDetalle(data);
  } catch (e) {
    cont.innerHTML = '<p class="text-danger small">Error al cargar el día.</p>';
  }
}

let vistaProspeccionActual = 'mes';

function cargarVistaProspeccionActual() {
  if (vistaProspeccionActual === 'dia') {
    cargarProspeccionDia(document.getElementById('dia-prospeccion').value);
  } else {
    cargarProspeccionMesOSemana(vistaProspeccionActual);
  }
}

// Número de semana ISO-8601 de una fecha, en el mismo formato que usa
// <input type="week"> ("YYYY-Www") — para dejarlo preseleccionado en "hoy".
function isoWeekString(d) {
  const date = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
  const diaIso = date.getUTCDay() || 7; // domingo (0) -> 7
  date.setUTCDate(date.getUTCDate() + 4 - diaIso);
  const inicioAnio = new Date(Date.UTC(date.getUTCFullYear(), 0, 1));
  const numSemana = Math.ceil((((date - inicioAnio) / 86400000) + 1) / 7);
  return `${date.getUTCFullYear()}-W${String(numSemana).padStart(2, '0')}`;
}

const inputMesProspeccion = document.getElementById('mes-prospeccion');
const inputSemanaProspeccion = document.getElementById('semana-prospeccion');
const inputDiaProspeccion = document.getElementById('dia-prospeccion');

if (inputMesProspeccion && inputSemanaProspeccion && inputDiaProspeccion) {
  const hoy = new Date();
  inputMesProspeccion.value = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}`;
  inputSemanaProspeccion.value = isoWeekString(hoy);
  inputDiaProspeccion.value = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-${String(hoy.getDate()).padStart(2, '0')}`;

  document.querySelectorAll('#vista-prospeccion-tabs button').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#vista-prospeccion-tabs button').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      vistaProspeccionActual = btn.dataset.vista;
      inputMesProspeccion.classList.toggle('d-none', vistaProspeccionActual !== 'mes');
      inputSemanaProspeccion.classList.toggle('d-none', vistaProspeccionActual !== 'semana');
      inputDiaProspeccion.classList.toggle('d-none', vistaProspeccionActual !== 'dia');
      cargarVistaProspeccionActual();
    });
  });

  inputMesProspeccion.addEventListener('change', cargarVistaProspeccionActual);
  inputSemanaProspeccion.addEventListener('change', cargarVistaProspeccionActual);
  inputDiaProspeccion.addEventListener('change', cargarVistaProspeccionActual);
}

cargarResumen();
cargarCitas('citas_proximas', 'tabla-proximas');

document.getElementById('tab-todas').addEventListener('shown.bs.tab', () => cargarCitas('citas_todas', 'tabla-todas'), { once: true });
document.getElementById('tab-clientes').addEventListener('shown.bs.tab', () => cargarClientes(), { once: true });
document.getElementById('tab-prospeccion').addEventListener('shown.bs.tab', () => cargarVistaProspeccionActual(), { once: true });
