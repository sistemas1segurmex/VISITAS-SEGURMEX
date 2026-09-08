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

function iniciales(nombre) {
  const partes = String(nombre).trim().split(/\s+/).filter(Boolean);
  const letras = partes.length > 1 ? partes[0][0] + partes[1][0] : (partes[0] || '?').slice(0, 2);
  return letras.toUpperCase();
}
const PALETA_AVATAR = ['#4F46E5', '#F5A623', '#16A34A', '#E11D48', '#0EA5E9', '#9333EA', '#D97706', '#0891B2'];
function colorAvatar(id) { return PALETA_AVATAR[id % PALETA_AVATAR.length]; }

function animarNumero(el, valorFinal) {
  if (!el) return;
  const inicio = parseInt(el.dataset.valor || '0', 10);
  el.dataset.valor = valorFinal;
  if (inicio === valorFinal) { el.textContent = valorFinal; return; }
  const duracion = 700, t0 = performance.now();
  function paso(t) {
    const p = Math.min((t - t0) / duracion, 1);
    const suavizado = 1 - Math.pow(1 - p, 3);
    el.textContent = Math.round(inicio + (valorFinal - inicio) * suavizado);
    if (p < 1) requestAnimationFrame(paso);
  }
  requestAnimationFrame(paso);
}

async function cargarResumen() {
  const res = await fetch(`../api/admin_vendedor.php?vendedor_id=${vendedorId}&accion=resumen`);
  const data = await res.json();
  if (!data.ok) {
    document.getElementById('encabezado-vendedor').innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
    return;
  }
  const v = data.vendedor;
  const avatarContenido = v.foto_path ? `<img src="../${v.foto_path}" alt="">` : iniciales(v.nombre);
  const estiloAvatar = v.foto_path ? '' : `background:${colorAvatar(v.id)};color:#fff`;

  document.getElementById('encabezado-vendedor').innerHTML = `
    <div class="v26-vendedor-header">
      <div class="v26-avatar-ring v26-avatar-ring--lg">
        <div class="inner" style="${estiloAvatar}">${avatarContenido}</div>
        <span class="v26-status-dot ${v.activo == 1 ? 'pulso' : 'off'}"></span>
      </div>
      <div>
        <h5 class="mb-0">${v.nombre}</h5>
        <div class="v26-subtitulo">${v.email}${v.telefono ? ' · ' + v.telefono : ''}${v.estado_operacion ? ' · ' + v.estado_operacion : ''}</div>
      </div>
    </div>
    <div class="v26-stats-row mb-3">
      <div class="v26-stat-card">
        <div class="v26-stat-icon"><i class="bi bi-person-lines-fill"></i></div>
        <div><div class="v26-stat-num" id="stat-clientes" data-valor="0">0</div><div class="v26-stat-label">Clientes registrados</div></div>
      </div>
      <div class="v26-stat-card v26-stat-card--azul">
        <div class="v26-stat-icon"><i class="bi bi-calendar3"></i></div>
        <div><div class="v26-stat-num" id="stat-citas" data-valor="0">0</div><div class="v26-stat-label">Citas totales</div></div>
      </div>
      <div class="v26-stat-card v26-stat-card--verde">
        <div class="v26-stat-icon"><i class="bi bi-calendar-event"></i></div>
        <div><div class="v26-stat-num" id="stat-proximas" data-valor="0">0</div><div class="v26-stat-label">Próximas citas</div></div>
      </div>
      <div class="v26-stat-card v26-stat-card--morado">
        <div class="v26-stat-icon"><i class="bi bi-patch-check-fill"></i></div>
        <div><div class="v26-stat-num" id="stat-checkins" data-valor="0">0</div><div class="v26-stat-label">Check-ins verificados</div></div>
      </div>
    </div>
  `;
  animarNumero(document.getElementById('stat-clientes'), data.total_clientes);
  animarNumero(document.getElementById('stat-citas'), data.total_citas);
  animarNumero(document.getElementById('stat-proximas'), data.proximas_citas);
  animarNumero(document.getElementById('stat-checkins'), data.checkins_verificados);
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
        ${pillInteresAdmin(c.interes)}
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

// Misma lista de etapas/interés que ETAPAS_CLIENTE/INTERES_CLIENTE en
// assets/js/vendedor.js -- duplicada aquí porque son dos archivos JS
// distintos sin módulo compartido entre admin y vendedor. El admin solo
// necesita mostrarlas (no las edita), así que basta con las etiquetas.
const ETAPAS_CLIENTE_ADMIN = {
  prospecto_agregado:   { etiqueta: 'Prospecto',               icono: 'bi-person-plus' },
  contacto_establecido: { etiqueta: 'Contacto establecido',    icono: 'bi-telephone' },
  reunion_presentacion: { etiqueta: 'Reunión de presentación', icono: 'bi-people' },
  propuesta_enviada:    { etiqueta: 'Propuesta enviada',       icono: 'bi-file-earmark-text' },
  convertido:           { etiqueta: 'Convertido',              icono: 'bi-trophy' },
  perdido:              { etiqueta: 'Perdido',                 icono: 'bi-x-circle' },
};
const INTERES_CLIENTE_ADMIN = {
  bajo: 'Poco interesado', medio: 'Interés medio', interesado: 'Interesado', muy_interesado: 'Muy interesado',
};
function pillEtapaAdmin(valor) {
  const e = ETAPAS_CLIENTE_ADMIN[valor] || ETAPAS_CLIENTE_ADMIN.prospecto_agregado;
  const clave = ETAPAS_CLIENTE_ADMIN[valor] ? valor : 'prospecto_agregado';
  return `<span class="v26-pill v26-pill--etapa-${clave}"><i class="bi ${e.icono}"></i> ${e.etiqueta}</span>`;
}
function pillInteresAdmin(valor) {
  if (!valor || !INTERES_CLIENTE_ADMIN[valor]) return '';
  return `<span class="v26-pill v26-pill--interes-${valor}">${INTERES_CLIENTE_ADMIN[valor]}</span>`;
}

function tarjetaCliente(c, i) {
  const tieneGps = !!c.lat;
  const iniciales = iniciales2(c.nombre);
  return `
    <div class="v26-cliente-card" style="animation-delay:${(i * 0.04).toFixed(2)}s">
      <div class="v26-user-top">
        <div class="v26-avatar-ring">
          <div class="inner" style="background:${colorAvatar(c.id)};color:#fff">${iniciales}</div>
          <span class="v26-status-dot ${tieneGps ? '' : 'off'} ${tieneGps ? 'pulso' : ''}"></span>
        </div>
        <div class="flex-grow-1" style="min-width:0">
          <div class="v26-user-nombre"><i class="bi ${c.tipo_cliente === 'persona' ? 'bi-person' : 'bi-building'}"></i> ${c.nombre}</div>
          ${c.nombre_contacto ? `<div class="v26-user-correo"><i class="bi bi-person-badge"></i> ${c.nombre_contacto}</div>` : ''}
          <div class="v26-user-correo"><i class="bi bi-geo-alt"></i> ${c.direccion || 'Sin dirección'}</div>
        </div>
      </div>
      <div class="v26-user-meta d-none">
        ${pillEtapaAdmin(c.etapa)}
      </div>
      <div class="v26-user-meta">
        ${pillInteresAdmin(c.ultimo_interes)}
      </div>
      <div class="v26-user-meta">
        ${tieneGps ? '<span class="v26-pill v26-pill--verificado">GPS ok</span>' : '<span class="v26-pill v26-pill--pendiente">Sin ubicación</span>'}
        ${c.telefono ? `<span class="v26-user-region"><i class="bi bi-telephone"></i> ${c.telefono}</span>` : ''}
      </div>
    </div>`;
}

// Mismas iniciales/color que ya usa admin_usuarios.js, duplicadas aquí (son
// dos archivos JS distintos, sin módulo compartido entre ambos).
function iniciales2(nombre) {
  const partes = String(nombre).trim().split(/\s+/).filter(Boolean);
  return (partes.length > 1 ? partes[0][0] + partes[1][0] : (partes[0] || '?').slice(0, 2)).toUpperCase();
}
const PALETA_AVATAR_CLIENTE = ['#4F46E5', '#F5A623', '#16A34A', '#E11D48', '#0EA5E9', '#9333EA', '#D97706', '#0891B2'];
function colorAvatar(id) { return PALETA_AVATAR_CLIENTE[id % PALETA_AVATAR_CLIENTE.length]; }

// Un solo fetch para las pestañas "Clientes" y "Prospectos" -- ambas
// vienen del mismo endpoint, solo se separan por etapa del lado del cliente.
let clientesVendedorCache = null;
async function fetchClientesVendedor() {
  if (clientesVendedorCache) return clientesVendedorCache;
  const res = await fetch(`../api/admin_vendedor.php?vendedor_id=${vendedorId}&accion=clientes`);
  clientesVendedorCache = await res.json();
  return clientesVendedorCache;
}

async function cargarClientes() {
  const cont = document.getElementById('lista-clientes-vendedor');
  cont.innerHTML = '<p class="text-muted">Cargando...</p>';
  const data = await fetchClientesVendedor();
  if (!data.ok) { cont.innerHTML = `<div class="alert alert-danger">${data.error}</div>`; return; }
  const lista = data.clientes.filter(c => (c.etapa || 'prospecto_agregado') === 'convertido');
  if (lista.length === 0) { cont.innerHTML = '<p class="text-muted">Este vendedor aún no tiene clientes convertidos.</p>'; return; }
  cont.innerHTML = `<div class="v26-grid-usuarios">${lista.map(tarjetaCliente).join('')}</div>`;
}

async function cargarProspectos() {
  const cont = document.getElementById('lista-prospectos-vendedor');
  cont.innerHTML = '<p class="text-muted">Cargando...</p>';
  const data = await fetchClientesVendedor();
  if (!data.ok) { cont.innerHTML = `<div class="alert alert-danger">${data.error}</div>`; return; }
  const lista = data.clientes.filter(c => (c.etapa || 'prospecto_agregado') !== 'convertido');
  if (lista.length === 0) { cont.innerHTML = '<p class="text-muted">Este vendedor no tiene prospectos pendientes.</p>'; return; }
  cont.innerHTML = `<div class="v26-grid-usuarios">${lista.map(tarjetaCliente).join('')}</div>`;
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
  cotizacion:        { label: 'Cotización generada',    icon: 'bi-file-earmark-text-fill',    clase: 'cotizacion' },
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

// Línea de tiempo horizontal: un solo carril con un ícono flotante por día
// que tuvo algo que contar (conectado a la línea con un pequeño tallo), un
// punto sobre el carril para el resto de los días, y una marca vertical de
// "HOY" que cruza todo. Mismas 5 categorías de siempre, solo cambia cómo se
// dibujan (antes: cuadrícula de casillas por día).
const DIAS_SEMANA_CORTOS = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];

// Tarjeta flotante con el detalle completo del día, en vez del tooltip feo
// nativo del navegador — aparece arriba de la columna al pasar el mouse.
// "anclaje" evita que se salga de la pantalla cerca de los bordes del mes:
// centrada en medio, pegada a la izquierda/derecha en los primeros/últimos
// días (antes se centraba siempre y en los extremos quedaba fuera de vista).
function tooltipDia(fecha, categorias, offsetPx, anclaje) {
  const f = new Date(fecha + 'T00:00:00');
  const fechaBonita = `${DIAS_SEMANA_CORTOS[f.getDay()]} ${f.getDate()} ${MESES_CORTOS[f.getMonth()]}`;
  const filas = categorias
    .filter(cat => cat.categoria !== 'futuro')
    .map(cat => {
      const c = CATEGORIAS_PROSPECCION[cat.categoria] || CATEGORIAS_PROSPECCION.sin_actividad;
      return `<div class="v26-tl-tt-fila v26-prosp-celda--${c.clase}"><i class="bi ${c.icon}"></i><span>${c.label}${cat.detalle ? ' — ' + cat.detalle : ''}</span></div>`;
    }).join('');
  return `
    <div class="v26-tl-tooltip v26-tl-tooltip--${anclaje}" style="bottom:${offsetPx}px">
      <div class="v26-tl-tt-fecha">${fechaBonita}</div>
      ${filas || '<div class="v26-tl-tt-fila muted">Todavía no llega</div>'}
    </div>`;
}

function tiraDias(dias) {
  const hoyISO = new Date().toISOString().slice(0, 10);
  let mesAnterior = null;

  // El contenedor recorta vertical (overflow-x:auto obliga a overflow-y
  // computado también auto — no hay forma de tener solo scroll horizontal
  // sin esto) así que si no le damos suficiente alto de sobra, los íconos
  // apilados y la tarjeta flotante de un día con varias categorías quedan
  // cortados/invisibles. Se calcula el alto necesario según el día con MÁS
  // categorías simultáneas (normalmente 1, a veces 2-3), en vez de dejar un
  // padding fijo que le quede chico a esos días o de sobra al resto.
  const maxCategorias = Math.max(1, ...dias.map(d =>
    (d.categorias || [{ categoria: d.categoria }])
      .filter(cat => cat.categoria !== 'futuro' && (CATEGORIAS_PROSPECCION[cat.categoria] || {}).icon).length
  ));
  const paddingTop = Math.max(160, 100 + maxCategorias * 65);

  const cols = dias.map((d, idx) => {
    // "categorias" (plural) es lo nuevo: TODO lo que pasó ese día, no solo
    // lo de mayor prioridad. Si el backend todavía no la manda (versión
    // vieja en caché), se arma una de un solo elemento con lo de siempre.
    const categorias = d.categorias || [{ categoria: d.categoria, detalle: d.detalle }];
    const primera = CATEGORIAS_PROSPECCION[categorias[0].categoria] || CATEGORIAS_PROSPECCION.sin_actividad;
    const dia = Number(d.fecha.slice(-2));
    const mes = d.fecha.slice(0, 7);
    const esHoy = d.fecha === hoyISO;
    const esInicioDeMes = mes !== mesAnterior;
    mesAnterior = mes;

    const etiquetaMes = esInicioDeMes
      ? `<div class="v26-tl-mes">${MESES_CORTOS[Number(mes.slice(-2)) - 1]}</div>` : '';
    const marcaHoy = esHoy ? `<div class="v26-tl-hoy-linea"></div><div class="v26-tl-hoy-etq">HOY</div>` : '';

    // Un ícono flotante por cada categoría real del día (excluye "futuro"),
    // apilados uno encima del otro sobre un solo tallo compartido.
    const conIcono = categorias.filter(cat => cat.categoria !== 'futuro' && (CATEGORIAS_PROSPECCION[cat.categoria] || {}).icon);
    let badges = '';
    if (conIcono.length) {
      const alturaTallo = 16 + (conIcono.length - 1) * 28;
      const colorTallo = (CATEGORIAS_PROSPECCION[conIcono[0].categoria] || {}).clase;
      badges += `<div class="v26-tl-tallo v26-prosp-celda--${colorTallo}" style="height:${alturaTallo}px"></div>`;
      badges += conIcono.map((cat, i) => {
        const c = CATEGORIAS_PROSPECCION[cat.categoria] || CATEGORIAS_PROSPECCION.sin_actividad;
        return `<div class="v26-tl-badge v26-prosp-celda--${c.clase}${c.activa ? ' activa' : ''}" style="bottom:${38 + i * 28}px"><i class="bi ${c.icon}"></i></div>`;
      }).join('');
    }

    const offsetTooltip = conIcono.length ? 66 + conIcono.length * 28 : 46;
    const anclaje = idx < 3 ? 'inicio' : (idx >= dias.length - 3 ? 'fin' : 'centro');

    return `
      <div class="v26-tl-col" style="animation-delay:${(idx * 0.012).toFixed(3)}s">
        ${etiquetaMes}
        ${marcaHoy}
        ${badges}
        <div class="v26-tl-punto v26-tl-punto--${primera.clase} v26-prosp-celda--${primera.clase}"><span class="num">${dia}</span></div>
        ${tooltipDia(d.fecha, categorias, offsetTooltip, anclaje)}
      </div>`;
  }).join('');

  return `<div class="v26-timeline" style="padding-top:${paddingTop}px">${cols}</div>`;
}

function leyendaProspeccion() {
  const orden = ['cita', 'prospeccion', 'cliente', 'ubicacion', 'cotizacion', 'sin_actividad'];
  return `<div class="v26-prosp-leyenda">${orden.map(k => {
    const c = CATEGORIAS_PROSPECCION[k];
    return `<span class="item"><i class="bi ${c.icon} v26-prosp-celda--${c.clase}"></i>${c.label}</span>`;
  }).join('')}</div>`;
}

function colorCobertura(pct) {
  if (pct >= 70) return 'var(--v26-green)';
  if (pct >= 40) return 'var(--v26-brand-1)';
  return 'var(--v26-red)';
}

function animarAnillo(el, pctFinal) {
  if (!el) return;
  const color = colorCobertura(pctFinal);
  const duracion = 900, t0 = performance.now();
  function paso(t) {
    const p = Math.min((t - t0) / duracion, 1);
    const actual = pctFinal * (1 - Math.pow(1 - p, 3));
    el.style.background = `conic-gradient(${color} ${(actual * 3.6).toFixed(1)}deg, var(--v26-bg) 0deg)`;
    if (p < 1) requestAnimationFrame(paso);
  }
  requestAnimationFrame(paso);
}

function resumenProspeccion(data, vista) {
  const conteo = data.conteo;
  const etiquetaPeriodo = vista === 'semana' ? 'esta semana' : 'este mes';
  return `
    <div class="v26-prosp-resumen">
      <div class="v26-prosp-pct">
        <div class="v26-prosp-ring" id="anillo-cobertura">
          <div class="v26-prosp-ring-inner"><span class="valor">${data.porcentaje_cobertura}%</span></div>
        </div>
        <div class="etiqueta">de cobertura ${etiquetaPeriodo}<br>(${data.dias_cubiertos} de ${data.total_dias_considerados} días transcurridos)</div>
      </div>
      <div class="v26-prosp-conteos">
        <div><i class="bi bi-calendar-check-fill v26-prosp-celda--cita"></i> ${conteo.cita} con cita</div>
        <div><i class="bi bi-signpost-2-fill v26-prosp-celda--prospeccion"></i> ${conteo.prospeccion} en prospección</div>
        <div><i class="bi bi-person-plus-fill v26-prosp-celda--cliente"></i> ${conteo.cliente} solo cliente nuevo</div>
        <div><i class="bi bi-geo-alt-fill v26-prosp-celda--ubicacion"></i> ${conteo.ubicacion} solo GPS</div>
        <div><i class="bi bi-file-earmark-text-fill v26-prosp-celda--cotizacion"></i> ${conteo.cotizacion} cotización(es)</div>
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
    animarAnillo(document.getElementById('anillo-cobertura'), data.porcentaje_cobertura);
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

  const vistaProspSlider = document.getElementById('vista-prosp-slider');
  document.querySelectorAll('#vista-prospeccion-tabs .opt').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#vista-prospeccion-tabs .opt').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      posicionarSlider(vistaProspSlider, btn);
      vistaProspeccionActual = btn.dataset.vista;
      inputMesProspeccion.classList.toggle('d-none', vistaProspeccionActual !== 'mes');
      inputSemanaProspeccion.classList.toggle('d-none', vistaProspeccionActual !== 'semana');
      inputDiaProspeccion.classList.toggle('d-none', vistaProspeccionActual !== 'dia');
      cargarVistaProspeccionActual();
    });
  });
  requestAnimationFrame(() => posicionarSlider(vistaProspSlider, document.querySelector('#vista-prospeccion-tabs .opt.active')));

  inputMesProspeccion.addEventListener('change', cargarVistaProspeccionActual);
  inputSemanaProspeccion.addEventListener('change', cargarVistaProspeccionActual);
  inputDiaProspeccion.addEventListener('change', cargarVistaProspeccionActual);
}

function posicionarSlider(sliderEl, btn) {
  if (!sliderEl || !btn) return;
  sliderEl.style.left = btn.offsetLeft + 'px';
  sliderEl.style.width = btn.offsetWidth + 'px';
}

// Slider de las pestañas principales (Próximas / Todas / Clientes / Prospección)
const tabsSlider = document.getElementById('tabs-slider');
const contTabs = document.getElementById('tabsVendedor');
if (tabsSlider && contTabs) {
  contTabs.querySelectorAll('.opt').forEach(btn => {
    btn.addEventListener('shown.bs.tab', () => posicionarSlider(tabsSlider, btn));
  });
  requestAnimationFrame(() => posicionarSlider(tabsSlider, contTabs.querySelector('.opt.active')));
  window.addEventListener('resize', () => posicionarSlider(tabsSlider, contTabs.querySelector('.opt.active')));
}

cargarResumen();
cargarCitas('citas_proximas', 'tabla-proximas');

document.getElementById('tab-todas').addEventListener('shown.bs.tab', () => cargarCitas('citas_todas', 'tabla-todas'), { once: true });
document.getElementById('tab-clientes').addEventListener('shown.bs.tab', () => cargarClientes(), { once: true });
document.getElementById('tab-prospectos').addEventListener('shown.bs.tab', () => cargarProspectos(), { once: true });
document.getElementById('tab-prospeccion').addEventListener('shown.bs.tab', () => cargarVistaProspeccionActual(), { once: true });
