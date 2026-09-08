// Lógica común de las páginas del vendedor: listado de citas del día
// y tracking periódico de ubicación mientras la app está abierta.

function badgeEstado(cita) {
  if (cita.retrasada) return '<span class="v26-pill v26-pill--retrasada">Retrasada</span>';
  const map = {
    pendiente: 'Pendiente',
    en_curso: 'En curso',
    completada: 'Completada',
    no_realizada: 'No realizada',
    cancelada: 'Cancelada',
  };
  return `<span class="v26-pill v26-pill--${cita.estado}">${map[cita.estado] || cita.estado}</span>`;
}

function badgeVerificado(cita) {
  if (cita.checkin_verificado === null || cita.checkin_verificado === undefined) return '';
  return cita.checkin_verificado == 1
    ? '<span class="v26-pill v26-pill--verificado">GPS verificado</span>'
    : '<span class="v26-pill v26-pill--noverificado">Fuera de zona</span>';
}

function horaCorta(fechaHora) {
  const d = new Date(fechaHora.replace(' ', 'T'));
  if (isNaN(d)) return fechaHora;
  return d.toLocaleTimeString('es-MX', { hour: 'numeric', minute: '2-digit' });
}

function skeletonCitas(n = 3) {
  return Array(n).fill('<div class="v26-skel"></div>').join('');
}

// Cuenta ascendente animada para tarjetas de estadística (data-cuenta="N").
// Compartida por Inicio y Mis clientes.
function animarContador(el, destino) {
  if (!destino) { el.textContent = '0'; return; }
  const duracion = 600;
  const inicio = performance.now();
  requestAnimationFrame(function paso(ahora) {
    const t = Math.min(1, (ahora - inicio) / duracion);
    el.textContent = Math.round(destino * (1 - Math.pow(1 - t, 3)));
    if (t < 1) requestAnimationFrame(paso);
  });
}

// Tarjetas de estadística del día (Citas / Completadas / Pendientes) en Inicio.
function renderStatsInicio(citas) {
  const cont = document.getElementById('stats-inicio');
  if (!cont) return;
  const total = citas.length;
  const completadas = citas.filter(c => c.estado === 'completada').length;
  const pendientes = citas.filter(c => c.estado === 'pendiente').length;
  cont.innerHTML = `
    <div class="v26-stat-card"><div class="v26-stat-num" data-cuenta="${total}">0</div><div class="v26-stat-label">Citas</div></div>
    <div class="v26-stat-card v26-stat-card--verde"><div class="v26-stat-num" data-cuenta="${completadas}">0</div><div class="v26-stat-label">Completadas</div></div>
    <div class="v26-stat-card v26-stat-card--ambar"><div class="v26-stat-num" data-cuenta="${pendientes}">0</div><div class="v26-stat-label">Pendientes</div></div>
  `;
  cont.querySelectorAll('[data-cuenta]').forEach(el => animarContador(el, parseInt(el.dataset.cuenta, 10)));
}

function renderResumenPendientes(citas) {
  const cont = document.getElementById('resumen-pendientes');
  if (!cont) return;

  if (citas.length === 0) {
    cont.innerHTML = `
      <div class="v26-banner v26-banner--ok">
        <i class="bi bi-check-circle-fill icon"></i>
        <div class="txt"><p>No tienes citas programadas hoy.</p></div>
      </div>`;
    return;
  }

  const completadas = citas.filter(c => c.estado === 'completada').length;
  const retrasadas = citas.filter(c => c.retrasada);
  const salidasPendientes = citas.filter(c => c.tiene_entrada > 0 && c.tiene_salida == 0 && c.estado !== 'no_realizada');
  const pct = Math.round((completadas / citas.length) * 100);
  const sinPendientes = retrasadas.length === 0 && salidasPendientes.length === 0;

  const items = [];
  if (retrasadas.length > 0) {
    items.push(`${retrasadas.length} cita${retrasadas.length > 1 ? 's' : ''} retrasada${retrasadas.length > 1 ? 's' : ''} (${retrasadas.map(c => c.cliente_nombre).join(', ')})`);
  }
  if (salidasPendientes.length > 0) {
    items.push(`${salidasPendientes.length} salida${salidasPendientes.length > 1 ? 's' : ''} por registrar (${salidasPendientes.map(c => c.cliente_nombre).join(', ')})`);
  }

  cont.innerHTML = `
    <div class="v26-resumen-hero">
      <div class="v26-dia-ring" id="anillo-dia">
        <div class="v26-dia-ring-inner"><span class="valor">${pct}%</span><span class="etiqueta">del día</span></div>
      </div>
      <div class="txt">
        ${sinPendientes
          ? `<strong>Vas al día</strong><p>No tienes pendientes por ahora.</p>`
          : `<strong>Tienes pendientes por resolver</strong><ul>${items.map(i => `<li>${i}</li>`).join('')}</ul>`}
      </div>
    </div>`;

  const color = pct === 100 ? 'var(--v26-green)' : (retrasadas.length > 0 ? 'var(--v26-red)' : 'var(--v26-brand-2)');
  const anillo = document.getElementById('anillo-dia');
  // Se pinta en el siguiente frame para que el navegador anime la transición
  // de "background" declarada en CSS en vez de aparecer ya lleno de golpe.
  requestAnimationFrame(() => {
    anillo.style.background = `conic-gradient(${color} ${(pct * 3.6).toFixed(1)}deg, var(--v26-border) 0deg)`;
  });
}

function fechaParaDia(dia) {
  const d = new Date();
  if (dia === 'manana') d.setDate(d.getDate() + 1);
  return d.toISOString().slice(0, 10);
}

async function cargarCitas(dia = 'hoy') {
  const cont = document.getElementById('lista-citas');
  if (!cont) return;
  const esManana = dia === 'manana';
  cont.innerHTML = skeletonCitas();
  try {
    const res = await fetch('../api/citas.php?fecha=' + fechaParaDia(dia));
    const data = await res.json();
    if (!data.ok) {
      cont.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
      return;
    }
    renderStatsInicio(data.citas);
    if (esManana) {
      const resumen = document.getElementById('resumen-pendientes');
      if (resumen) resumen.innerHTML = '';
    } else {
      renderResumenPendientes(data.citas);
    }
    // Si es "hoy" y no hay citas, ofrecemos marcar una jornada de
    // prospección libre (el vendedor salió a buscar clientes por su cuenta).
    const ctaProspeccion = document.getElementById('cta-prospeccion');
    if (ctaProspeccion) {
      if (!esManana && data.citas.length === 0) {
        cargarEstadoProspeccion();
      } else {
        ctaProspeccion.innerHTML = '';
      }
    }
    if (data.citas.length === 0) {
      cont.innerHTML = `
        <div class="v26-empty">
          <div class="icon"><i class="bi bi-calendar2-check"></i></div>
          <p>No tienes citas programadas para ${esManana ? 'mañana' : 'hoy'}.</p>
        </div>`;
      return;
    }
    const accionIcono = c => {
      if (esManana && c.estado === 'pendiente') return 'bi-calendar-event';
      if (c.estado === 'no_realizada') return 'bi-info-circle';
      if (c.estado === 'completada') return 'bi-check2';
      return c.tiene_entrada == 0 ? 'bi-camera' : 'bi-box-arrow-right';
    };
    const accionTip = c => {
      if (esManana && c.estado === 'pendiente') return 'Aún no puedes hacer check-in';
      if (c.estado === 'no_realizada') return 'Ver detalle (no realizada)';
      if (c.estado === 'completada') return 'Ver visita completa';
      return c.tiene_entrada == 0 ? 'Registrar entrada' : 'Registrar salida';
    };
    const accionClase = c => (esManana || c.estado === 'completada' || c.estado === 'no_realizada') ? 'secundaria' : '';
    const dotClase = c => c.retrasada ? 'retrasada' : c.estado;
    const esUrgente = c => !esManana && (c.retrasada || (c.tiene_entrada > 0 && c.tiene_salida == 0 && c.estado !== 'no_realizada' && c.estado !== 'cancelada'));
    const sinAccion = c => c.estado === 'cancelada';
    cont.innerHTML = data.citas.map(c => `
      <div class="v26-cita ${c.estado === 'cancelada' ? 'v26-cita--cancelada' : ''}">
        <span class="v26-timeline-dot ${dotClase(c)}"></span>
        <div class="info">
          <div class="cliente">${c.cliente_nombre}</div>
          <div class="direccion">${c.direccion}</div>
          <div class="hora"><i class="bi bi-clock"></i> ${horaCorta(c.fecha_hora)}</div>
          <div class="badges">${badgeEstado(c)} ${badgeVerificado(c)}</div>
          ${c.motivo ? `<div class="v26-motivo">"${c.motivo}"</div>` : ''}
          ${c.notas ? `<div class="v26-motivo"><i class="bi bi-sticky"></i> ${c.notas}</div>` : ''}
          ${c.estado === 'pendiente' ? `<button type="button" class="v26-link-cancelar" data-cancelar="${c.id}" data-nombre="${(c.cliente_nombre || '').replace(/"/g, '&quot;')}">Cancelar cita</button>` : ''}
        </div>
        ${sinAccion(c) ? '' : `<a href="checkin.php?cita_id=${c.id}" class="accion v26-tip ${esUrgente(c) ? 'urgente' : ''} ${accionClase(c)}" data-tip="${accionTip(c)}" aria-label="${accionTip(c)}"><i class="bi ${accionIcono(c)}"></i></a>`}
      </div>
    `).join('');
  } catch (e) {
    cont.innerHTML = '<div class="alert alert-danger">No se pudo cargar la información.</div>';
  }
}

// Cancela una cita (solo aplica mientras sigue "pendiente"); pide
// confirmación y un motivo opcional con el modal compartido v26Sheet.
async function cancelarCita(id, nombre) {
  const respuesta = await v26Sheet({
    titulo: `¿Cancelar la cita con ${nombre}?`,
    desc: 'Esta acción no se puede deshacer.',
    pedirMotivo: true,
    motivoRequerido: false,
    placeholderMotivo: 'Motivo (opcional)',
    textoConfirmar: 'Sí, cancelar',
    textoCancelar: 'No, volver',
  });
  if (!respuesta) return;

  const fd = new FormData();
  fd.append('cita_id', id);
  fd.append('motivo', respuesta.motivo);

  try {
    const res = await fetch('../api/cancelar_cita.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) {
      cargarCitas();
    } else {
      alert(data.error || 'No se pudo cancelar la cita.');
    }
  } catch (e) {
    alert('Error de conexión. Intenta de nuevo.');
  }
}

document.addEventListener('click', (e) => {
  const btn = e.target.closest('[data-cancelar]');
  if (btn) cancelarCita(btn.dataset.cancelar, btn.dataset.nombre);
});

// Envía la ubicación actual al servidor (tracking en vivo mientras la app
// esté abierta en el navegador del vendedor).
function iniciarTrackingPeriodico(intervaloMs = 30000) {
  if (!('geolocation' in navigator)) return;

  const enviar = () => {
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        const fd = new FormData();
        fd.append('lat', pos.coords.latitude);
        fd.append('lng', pos.coords.longitude);
        fetch('../api/tracking.php', { method: 'POST', body: fd }).catch(() => {});
      },
      () => {},
      { enableHighAccuracy: true, timeout: 15000 }
    );
  };

  enviar();
  setInterval(enviar, intervaloMs);
}

// ---------------------------------------------------------------------
// Jornada de prospección libre: el vendedor la marca cuando no tiene citas
// hoy pero sale a buscar clientes por su cuenta. Es una acción consciente
// (distinta del tracking GPS pasivo) que el admin puede usar como evidencia
// de que el día sí tuvo actividad, junto con los clientes nuevos del día.
// ---------------------------------------------------------------------

async function cargarEstadoProspeccion() {
  const cont = document.getElementById('cta-prospeccion');
  if (!cont) return;
  try {
    const res = await fetch('../api/prospeccion.php');
    const data = await res.json();
    if (data.ok) renderCtaProspeccion(data.prospeccion);
  } catch (e) {
    // Silencioso: no bloqueamos el resto de la pantalla por esto.
  }
}

function renderCtaProspeccion(prospeccion) {
  const cont = document.getElementById('cta-prospeccion');
  if (!cont) return;

  if (!prospeccion) {
    cont.innerHTML = `
      <button type="button" id="btn-iniciar-prospeccion" class="v26-cta" style="border:none;width:100%;cursor:pointer;">
        <span class="v26-cta-icon"><i class="bi bi-signpost-2-fill"></i></span>
        <span class="v26-cta-text">
          <strong>Salí a buscar clientes</strong>
          <small>Marca tu jornada de prospección de hoy</small>
        </span>
        <i class="bi bi-chevron-right chev"></i>
      </button>`;
    document.getElementById('btn-iniciar-prospeccion').addEventListener('click', iniciarProspeccion);
    return;
  }

  if (!prospeccion.hora_fin) {
    cont.innerHTML = `
      <div class="v26-banner v26-banner--ok">
        <i class="bi bi-signpost-2-fill icon"></i>
        <div class="txt">
          <strong>En prospección desde las ${horaCorta(prospeccion.hora_inicio)}</strong>
          <p>Cada cliente nuevo que registres hoy cuenta como evidencia de tu recorrido.</p>
        </div>
      </div>
      <button type="button" id="btn-finalizar-prospeccion" class="v26-btn v26-btn-ghost v26-btn-block mb-3">
        Finalizar jornada de prospección
      </button>`;
    document.getElementById('btn-finalizar-prospeccion').addEventListener('click', finalizarProspeccion);
    return;
  }

  cont.innerHTML = `
    <div class="v26-banner v26-banner--ok">
      <i class="bi bi-check-circle-fill icon"></i>
      <div class="txt"><p>Jornada de prospección registrada hoy (${horaCorta(prospeccion.hora_inicio)} – ${horaCorta(prospeccion.hora_fin)}).</p></div>
    </div>`;
}

async function iniciarProspeccion() {
  const fd = new FormData();
  fd.append('action', 'iniciar');
  try {
    const res = await fetch('../api/prospeccion.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) renderCtaProspeccion(data.prospeccion);
    else alert(data.error || 'No se pudo registrar tu jornada.');
  } catch (e) {
    alert('Error de conexión. Intenta de nuevo.');
  }
}

async function finalizarProspeccion() {
  const fd = new FormData();
  fd.append('action', 'finalizar');
  try {
    await fetch('../api/prospeccion.php', { method: 'POST', body: fd });
    cargarEstadoProspeccion();
  } catch (e) {
    alert('Error de conexión. Intenta de nuevo.');
  }
}

// ---------------------------------------------------------------------
// Embudo de ventas: etapa del cliente/prospecto (progreso de la relación)
// e interés (qué tan interesado se mostró en una visita puntual -- se
// califica por cita, ver checkin.php, no aquí). Único lugar donde vive la
// lista de valores -- si se agrega una etapa nueva, solo hay que tocar
// esto y la validación en api/cambiar_etapa.php (la columna es texto
// libre en la BD, sin ENUM/CHECK, a propósito).
// ---------------------------------------------------------------------
const ETAPAS_CLIENTE = [
  { valor: 'prospecto_agregado',    etiqueta: 'Prospecto',               icono: 'bi-person-plus' },
  { valor: 'contacto_establecido',  etiqueta: 'Contacto establecido',    icono: 'bi-telephone' },
  { valor: 'reunion_presentacion',  etiqueta: 'Reunión de presentación', icono: 'bi-people' },
  { valor: 'propuesta_enviada',     etiqueta: 'Propuesta enviada',       icono: 'bi-file-earmark-text' },
  { valor: 'convertido',            etiqueta: 'Convertido',              icono: 'bi-trophy' },
];
const ETAPA_PERDIDO = { valor: 'perdido', etiqueta: 'Perdido', icono: 'bi-x-circle' };

const INTERES_CLIENTE = [
  { valor: 'bajo',           etiqueta: 'Poco interesado' },
  { valor: 'medio',          etiqueta: 'Interés medio' },
  { valor: 'interesado',     etiqueta: 'Interesado' },
  { valor: 'muy_interesado', etiqueta: 'Muy interesado' },
];

function etapaInfo(valor) {
  return ETAPAS_CLIENTE.find(e => e.valor === valor) || (valor === 'perdido' ? ETAPA_PERDIDO : ETAPAS_CLIENTE[0]);
}
function interesInfo(valor) {
  return INTERES_CLIENTE.find(i => i.valor === valor) || null;
}
function pillEtapa(valor) {
  const e = etapaInfo(valor);
  return `<span class="v26-pill v26-pill--etapa-${e.valor}"><i class="bi ${e.icono}"></i> ${e.etiqueta}</span>`;
}
function pillInteres(valor) {
  const i = interesInfo(valor);
  if (!i) return '';
  return `<span class="v26-pill v26-pill--interes-${i.valor}">${i.etiqueta}</span>`;
}

// Guarda el cambio de etapa de un cliente (usado por Mis clientes y por
// el historial). El interés ya no se maneja aquí -- ver checkin.php.
async function actualizarEtapaCliente(clienteId, cambios) {
  const fd = new FormData();
  fd.append('cliente_id', clienteId);
  fd.append('etapa', cambios.etapa);
  if (cambios.motivo_perdido !== undefined) fd.append('motivo_perdido', cambios.motivo_perdido || '');
  try {
    const res = await fetch('../api/cambiar_etapa.php', { method: 'POST', body: fd });
    return await res.json();
  } catch (e) {
    return { ok: false, error: 'Error de conexión. Intenta de nuevo.' };
  }
}

// Hoja para elegir la etapa de un cliente/prospecto. Regresa una Promise
// que resuelve con { etapa, motivo_perdido } si se guarda, o null si se
// cierra sin guardar. (El interés se califica por cita, no aquí -- ver
// los chips de "¿qué tan interesado se mostró?" en checkin.php.)
function v26SheetEtapa(cliente) {
  return new Promise((resolve) => {
    let backdrop = document.getElementById('v26-sheet-etapa');
    if (!backdrop) {
      backdrop = document.createElement('div');
      backdrop.id = 'v26-sheet-etapa';
      backdrop.className = 'v26-modal-backdrop d-none';
      backdrop.innerHTML = `
        <div class="v26-modal">
          <h3>Actualizar a <span id="v26se-nombre"></span></h3>
          <p class="text-muted small mb-2">Etapa del embudo</p>
          <div class="v26-chip-group" id="v26se-etapas"></div>
          <div id="v26se-perdido-wrap" class="mt-3 d-none">
            <textarea id="v26se-perdido-motivo" class="v26-textarea" rows="2" placeholder="¿Por qué se perdió? (opcional)"></textarea>
          </div>
          <div class="d-flex gap-2 mt-3">
            <button type="button" id="v26se-cancel" class="v26-btn v26-btn-ghost v26-btn-block">Cerrar</button>
            <button type="button" id="v26se-guardar" class="v26-btn v26-btn-primary v26-btn-block">Guardar</button>
          </div>
        </div>`;
      document.body.appendChild(backdrop);
    }

    const elNombre    = backdrop.querySelector('#v26se-nombre');
    const contEtapas  = backdrop.querySelector('#v26se-etapas');
    const wrapMotivo  = backdrop.querySelector('#v26se-perdido-wrap');
    const elMotivo    = backdrop.querySelector('#v26se-perdido-motivo');
    const btnCancel   = backdrop.querySelector('#v26se-cancel');
    const btnGuardar  = backdrop.querySelector('#v26se-guardar');

    elNombre.textContent = cliente.nombre;
    let etapaSel = cliente.etapa || 'prospecto_agregado';
    elMotivo.value = cliente.etapa_perdido_motivo || '';

    function pintar() {
      const todas = [...ETAPAS_CLIENTE, ETAPA_PERDIDO];
      contEtapas.innerHTML = todas.map(e => `
        <button type="button" class="v26-chip ${e.valor === etapaSel ? 'active' : ''} ${e.valor === 'perdido' ? 'peligro' : ''}" data-etapa="${e.valor}">
          <i class="bi ${e.icono}"></i> ${e.etiqueta}
        </button>`).join('');
      wrapMotivo.classList.toggle('d-none', etapaSel !== 'perdido');
      contEtapas.querySelectorAll('[data-etapa]').forEach(b => b.addEventListener('click', () => { etapaSel = b.dataset.etapa; pintar(); }));
    }
    pintar();

    backdrop.classList.remove('d-none');
    requestAnimationFrame(() => backdrop.classList.add('show'));

    function limpiar() {
      btnCancel.removeEventListener('click', onCancel);
      btnGuardar.removeEventListener('click', onGuardar);
      backdrop.removeEventListener('click', onBackdropClick);
    }
    function cerrar(resultado) {
      backdrop.classList.remove('show');
      limpiar();
      setTimeout(() => backdrop.classList.add('d-none'), 200);
      resolve(resultado);
    }
    function onCancel() { cerrar(null); }
    function onGuardar() { cerrar({ etapa: etapaSel, motivo_perdido: elMotivo.value.trim() }); }
    function onBackdropClick(e) { if (e.target === backdrop) onCancel(); }

    btnCancel.addEventListener('click', onCancel);
    btnGuardar.addEventListener('click', onGuardar);
    backdrop.addEventListener('click', onBackdropClick);
  });
}
