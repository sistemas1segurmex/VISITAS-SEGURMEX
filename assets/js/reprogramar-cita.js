// Hoja "Reprogramar cita" del vendedor (Inicio). Sube desde abajo igual que
// v26Sheet (mismo backdrop/estilos) y trae adentro una barra con la agenda
// del día elegido, para que se vea dónde cae la nueva hora y con quién
// choca. Las reglas se validan aquí para apagar Guardar a tiempo, pero la
// última palabra la tiene api/reprogramar_cita.php:
//   motivo obligatorio · nada en el pasado · sin choques a menos de 1 h ·
//   máximo 3 reprogramaciones por cita.
// Uso: reprogramarCita(cita, onGuardada) -- cita es una fila de api/citas.php.

const REPROG_MAX = 3;          // = MAX_REPROGRAMACIONES en helpers.php
const REPROG_CHOQUE_MIN = 60;  // = CHOQUE_CITAS_MIN en helpers.php
const REPROG_MOTIVOS = [
  ['error', 'bi-pencil', 'Me equivoqué al asignarla'],
  ['cliente', 'bi-telephone', 'El cliente la reprogramó'],
  ['otro', 'bi-three-dots', 'Otro'],
];

function reprogFechaLocal(d) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}
function reprogMin(hhmm) { const [h, m] = hhmm.split(':').map(Number); return h * 60 + m; }
function reprogHora(hhmm) {
  let [h, m] = hhmm.split(':').map(Number);
  const ap = h >= 12 ? 'p.m.' : 'a.m.';
  h = h % 12 || 12;
  return `${h}:${String(m).padStart(2, '0')} ${ap}`;
}
function reprogDia(ymd) {
  const d = new Date(ymd + 'T00:00');
  return d.toLocaleDateString('es-MX', { weekday: 'short', day: 'numeric', month: 'short' }).replace(/\./g, '');
}
function reprogEsc(s) {
  return String(s ?? '').replace(/[&<>"]/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ch]));
}

function reprogramarCita(cita, onGuardada) {
  const origFecha = cita.fecha_hora.slice(0, 10);
  const origHora = cita.fecha_hora.slice(11, 16);
  const usadas = Number(cita.reprogramaciones || 0);

  // Abre en la fecha que ya tiene la cita (o hoy, si esa ya pasó) y su
  // misma hora; el vendedor solo cambia lo que se movió.
  const hoy = reprogFechaLocal(new Date());
  const st = { motivo: null, fecha: origFecha < hoy ? hoy : origFecha, hora: origHora };
  const agendas = {}; // cache por fecha: [{hora, nombre}]

  let backdrop = document.getElementById('reprog-backdrop');
  if (backdrop) backdrop.remove();
  backdrop = document.createElement('div');
  backdrop.id = 'reprog-backdrop';
  backdrop.className = 'v26-modal-backdrop';
  backdrop.innerHTML = `
    <div class="v26-modal reprog" role="dialog" aria-modal="true" aria-labelledby="reprog-titulo">
      <h3 id="reprog-titulo">Reprogramar cita con ${reprogEsc(cita.cliente_nombre)}</h3>
      <p class="text-muted small mb-0">Actual: ${reprogDia(origFecha)} · ${reprogHora(origHora)}</p>
      <div class="reprog-lbl">¿Por qué se mueve? <span class="req">*</span></div>
      <div class="reprog-motivos">
        ${REPROG_MOTIVOS.map(([k, ic, t]) => `<button type="button" class="reprog-motivo" data-k="${k}"><i class="bi ${ic}"></i>${t}</button>`).join('')}
      </div>
      <textarea id="reprog-texto" class="v26-textarea mt-2 d-none" rows="2" maxlength="200" placeholder="Escribe el motivo (obligatorio)"></textarea>
      <div class="reprog-lbl">Nueva fecha y hora</div>
      <div class="reprog-row2">
        <input id="reprog-fecha" type="date" class="v26-input" min="${hoy}" value="${st.fecha}">
        <input id="reprog-hora" type="time" class="v26-input" step="300" value="${st.hora}">
      </div>
      <div class="reprog-dia-head"><span class="reprog-lbl m-0" id="reprog-dia-titulo"></span><span id="reprog-dia-cuenta"></span></div>
      <div class="reprog-barra" id="reprog-barra"></div>
      <div class="reprog-ticks" id="reprog-ticks"></div>
      <div id="reprog-alerta"></div>
      <div class="reprog-cuenta">Reprogramaciones: ${usadas} de ${REPROG_MAX}${REPROG_MAX - usadas === 1 ? ' · esta es la última' : ''}</div>
      <div class="d-flex gap-2 mt-2">
        <button type="button" id="reprog-cancelar" class="v26-btn v26-btn-ghost v26-btn-block">No, volver</button>
        <button type="button" id="reprog-guardar" class="v26-btn v26-btn-primary v26-btn-block" disabled>Guardar nueva hora</button>
      </div>
      <div class="reprog-hint" id="reprog-hint"></div>
    </div>`;
  document.body.appendChild(backdrop);
  requestAnimationFrame(() => backdrop.classList.add('show'));

  const $ = id => backdrop.querySelector('#' + id);
  const elTexto = $('reprog-texto'), elFecha = $('reprog-fecha'), elHora = $('reprog-hora'), btnGuardar = $('reprog-guardar');
  let guardando = false, errorServidor = '';

  function cerrar() {
    backdrop.classList.remove('show');
    setTimeout(() => backdrop.remove(), 200);
  }

  async function cargarAgenda(fecha) {
    if (agendas[fecha]) return agendas[fecha];
    try {
      const res = await fetch('../api/citas.php?fecha=' + fecha);
      const data = await res.json();
      agendas[fecha] = (data.ok ? data.citas : [])
        .filter(c => String(c.id) !== String(cita.id) && c.estado !== 'cancelada' && c.estado !== 'no_realizada')
        .map(c => ({ hora: c.fecha_hora.slice(11, 16), nombre: c.cliente_nombre }))
        .sort((a, b) => reprogMin(a.hora) - reprogMin(b.hora));
    } catch (e) {
      agendas[fecha] = []; // sin agenda igual se puede intentar; el servidor revisa el choque
    }
    return agendas[fecha];
  }

  function pintar() {
    backdrop.querySelectorAll('.reprog-motivo').forEach(b => b.classList.toggle('on', b.dataset.k === st.motivo));
    elTexto.classList.toggle('d-none', st.motivo !== 'otro');

    const fecha = elFecha.value, hora = elHora.value;
    const eventos = agendas[fecha] || [];
    const choque = hora ? eventos.find(e => Math.abs(reprogMin(e.hora) - reprogMin(hora)) < REPROG_CHOQUE_MIN) : null;
    const pasada = fecha && hora && new Date(`${fecha}T${hora}`) <= new Date();
    const igual = fecha === origFecha && hora === origHora;

    // Barra del día: 8 a 19 h, se estira si hay citas fuera de ese rango.
    const horas = eventos.map(e => reprogMin(e.hora) / 60).concat(hora ? [reprogMin(hora) / 60] : []);
    const h0 = Math.min(8, ...horas.map(Math.floor)), h1 = Math.max(19, ...horas.map(h => Math.floor(h) + 1));
    const total = h1 - h0;
    const pos = hhmm => ((reprogMin(hhmm) / 60 - h0) / total * 100);
    const ancho = 100 / total;
    let barra = '';
    if (fecha === hoy) {
      const ahora = new Date();
      const pct = Math.max(0, Math.min(100, ((ahora.getHours() + ahora.getMinutes() / 60) - h0) / total * 100));
      barra += `<div class="reprog-pasado" style="width:${pct}%" title="Ya pasó"></div>`;
    }
    if (fecha === origFecha) barra += `<div class="reprog-blk antes" style="left:${pos(origHora)}%;width:${ancho}%">antes</div>`;
    eventos.forEach(e => {
      barra += `<div class="reprog-blk ${choque === e ? 'choque' : ''}" style="left:${pos(e.hora)}%;width:${ancho}%" title="${reprogHora(e.hora)} ${reprogEsc(e.nombre)}">${reprogEsc(e.nombre)}</div>`;
    });
    if (hora) barra += `<div class="reprog-blk nueva" style="left:${pos(hora)}%;width:${ancho}%">${reprogEsc(cita.cliente_nombre)}</div>`;
    $('reprog-barra').innerHTML = barra;
    let ticks = '';
    for (let h = h0; h <= h1; h += 2) ticks += `<span style="left:${(h - h0) / total * 100}%">${h % 12 || 12}${h < 12 ? 'am' : 'pm'}</span>`;
    $('reprog-ticks').innerHTML = ticks;
    $('reprog-dia-titulo').textContent = fecha ? `Tu agenda del ${reprogDia(fecha)}` : 'Tu agenda';
    $('reprog-dia-cuenta').textContent = !agendas[fecha] ? 'Cargando…' : (eventos.length ? `${eventos.length} cita${eventos.length > 1 ? 's' : ''} más` : 'Día libre');

    let alerta = '';
    if (pasada) alerta = 'Esa hora ya pasó. Elige una fecha y hora a partir de ahora.';
    else if (choque) alerta = `Choca con ${reprogEsc(choque.nombre)} a las ${reprogHora(choque.hora)}. Elige otra hora.`;
    else if (errorServidor) alerta = reprogEsc(errorServidor);
    $('reprog-alerta').innerHTML = alerta ? `<div class="reprog-error"><i class="bi bi-exclamation-triangle"></i> ${alerta}</div>` : '';

    let hint = '';
    if (!st.motivo) hint = 'Elige un motivo para poder guardar.';
    else if (st.motivo === 'otro' && !elTexto.value.trim()) hint = 'Escribe el motivo en "Otro".';
    else if (igual) hint = 'Cambia la hora o la fecha de la cita.';
    $('reprog-hint').textContent = hint;
    btnGuardar.disabled = guardando || !!hint || pasada || igual || !!choque || !fecha || !hora || !agendas[fecha];
  }

  async function alCambiarFecha() {
    errorServidor = '';
    pintar();
    await cargarAgenda(elFecha.value);
    pintar();
  }

  async function guardar() {
    guardando = true;
    btnGuardar.textContent = 'Guardando…';
    pintar();
    const fd = new FormData();
    fd.append('cita_id', cita.id);
    fd.append('fecha_hora', `${elFecha.value} ${elHora.value}`);
    fd.append('motivo_tipo', st.motivo);
    fd.append('motivo_texto', elTexto.value.trim());
    try {
      const res = await fetch('../api/reprogramar_cita.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.ok) {
        cerrar();
        if (onGuardada) onGuardada(data);
        return;
      }
      errorServidor = data.error || 'No se pudo reprogramar la cita.';
      delete agendas[elFecha.value]; // por si el choque fue con una cita que no teníamos
      cargarAgenda(elFecha.value).then(pintar);
    } catch (e) {
      errorServidor = 'Error de conexión. Intenta de nuevo.';
    }
    guardando = false;
    btnGuardar.textContent = 'Guardar nueva hora';
    pintar();
  }

  backdrop.addEventListener('click', e => {
    if (e.target === backdrop) return cerrar();
    const m = e.target.closest('.reprog-motivo');
    if (m) {
      st.motivo = m.dataset.k;
      errorServidor = '';
      pintar();
      if (st.motivo === 'otro') elTexto.focus();
    }
  });
  $('reprog-cancelar').addEventListener('click', cerrar);
  btnGuardar.addEventListener('click', guardar);
  elTexto.addEventListener('input', pintar);
  elHora.addEventListener('input', () => { errorServidor = ''; pintar(); });
  elFecha.addEventListener('change', alCambiarFecha);
  alCambiarFecha();
}
