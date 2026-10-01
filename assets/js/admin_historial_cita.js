// Reprogramaciones y "no realizadas automáticas" en el panel del admin.
// Compartido por admin/index.php, vendedor_detalle.php y bitacora.php:
//   - piezasReprogramacionCita(c): etiqueta "↻ Reprogramada n/3" (se puede
//     tocar), etiqueta "Automática" y la línea "Antes: ... · motivo" para
//     las tarjetas de cita. c es una fila de api/citas.php o
//     api/admin_vendedor.php (traen reprogramaciones, fecha_anterior,
//     motivo_reprogramacion y motivo).
//   - abrirHistorialCita(id): hoja con la línea de tiempo completa de la
//     cita (api/historial_cita.php).
// Cualquier elemento con data-historial-cita="ID" abre el historial.

const HIST_LLAVE_MOTIVO = 'Motivo de reprogramación'; // = LLAVE_MOTIVO_REPROGRAMACION en helpers.php
const HIST_MOTIVO_AUTO = 'Sin atender (automático)';  // = MOTIVO_CITA_VENCIDA en helpers.php
const HIST_MAX = 3;                                     // = MAX_REPROGRAMACIONES en helpers.php
const HIST_LLAVE_TIPO_CANC = 'Tipo de cancelación';     // = LLAVE_TIPO_CANCELACION en helpers.php
const HIST_LLAVE_EVIDENCIA = 'Evidencia';               // = LLAVE_EVIDENCIA_CANCELACION en helpers.php

// Miniatura de la evidencia que se adjuntó al cancelar (api/foto.php
// ?cancelacion=ID). Abre en pestaña nueva: no todas las páginas que usan
// este archivo tienen el #modalFoto.
function htmlEvidenciaCancelacion(citaId) {
  const url = `../api/foto.php?cancelacion=${encodeURIComponent(citaId)}`;
  return `<a class="v26-evid-canc" href="${url}" target="_blank" rel="noopener" title="Ver evidencia de la cancelación">
      <img src="${url}" alt="Evidencia de la cancelación" loading="lazy"><span><i class="bi bi-paperclip"></i> Evidencia</span>
    </a>`;
}

function histEsc(s) {
  return String(s ?? '').replace(/[&<>"]/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ch]));
}

// citas.fecha_hora (y lo que se guarda de ella en la bitácora) es hora local
// de CDMX tal cual -- se formatea sin convertir zona.
function histFechaCita(fechaHora) {
  if (!fechaHora) return '';
  const d = new Date(String(fechaHora).slice(0, 16).replace(' ', 'T'));
  if (isNaN(d)) return fechaHora;
  const dia = d.toLocaleDateString('es-MX', { weekday: 'short', day: 'numeric', month: 'short' }).replace(/\./g, '');
  return `${dia}, ${d.toLocaleTimeString('es-MX', { hour: 'numeric', minute: '2-digit' })}`;
}

// bitacora_cambios.creado_en sí es UTC (sesión de Postgres en UTC).
function histFechaUTC(fechaUtc) {
  const d = new Date(String(fechaUtc).replace(' ', 'T') + 'Z');
  if (isNaN(d)) return fechaUtc;
  return d.toLocaleString('es-MX', { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit', timeZone: 'America/Mexico_City' }).replace(/\./g, '');
}

function esNoRealizadaAuto(c) {
  return c.estado === 'no_realizada' && c.motivo === HIST_MOTIVO_AUTO;
}

function piezasReprogramacionCita(c) {
  const n = Number(c.reprogramaciones || 0);
  const pills = [];
  if (n) {
    pills.push(`<button type="button" class="v26-pill v26-pill--reprogramada v26-pill-btn" data-historial-cita="${c.id}" title="Ver historial de la cita"><i class="bi bi-arrow-repeat"></i> Reprogramada ${n}/${HIST_MAX}</button>`);
  }
  if (esNoRealizadaAuto(c)) {
    pills.push(`<button type="button" class="v26-pill v26-pill--auto v26-pill-btn" data-historial-cita="${c.id}" title="Pasó sola a No realizada: no hubo check-in ni se reprogramó"><i class="bi bi-robot"></i> Automática</button>`);
  }
  if (c.estado === 'cancelada' && c.tipo_cancelacion) {
    pills.push(`<button type="button" class="v26-pill v26-pill--cliente-cancelo v26-pill-btn" data-historial-cita="${c.id}" title="Ver historial de la cita"><i class="bi bi-telephone-x"></i> ${histEsc(c.tipo_cancelacion)}</button>`);
  }
  if (c.estado === 'cancelada' && c.evidencia_cancelacion) {
    pills.push(`<a class="v26-pill v26-pill--evidencia v26-pill-btn" href="../api/foto.php?cancelacion=${c.id}" target="_blank" rel="noopener" title="Ver evidencia de la cancelación"><i class="bi bi-paperclip"></i> Evidencia</a>`);
  }
  let linea = '';
  if (n && c.fecha_anterior) {
    linea = `<div class="v26-reprog-linea"><i class="bi bi-arrow-repeat"></i> Antes: <s>${histEsc(histFechaCita(c.fecha_anterior))}</s>${c.motivo_reprogramacion ? ` · <q>${histEsc(c.motivo_reprogramacion)}</q>` : ''}</div>`;
  } else if (c.motivo && !esNoRealizadaAuto(c) && (c.estado === 'cancelada' || c.estado === 'no_realizada')) {
    linea = `<div class="v26-reprog-linea"><i class="bi bi-chat-left-quote"></i> <q>${histEsc(c.motivo)}</q></div>`;
  }
  return { pills: pills.join(' '), linea };
}

// Un evento de la bitácora de la cita -> { tipo, icono, titulo, detalle }.
function histEvento(ev) {
  const cambios = ev.cambios || {};
  if (cambios[HIST_LLAVE_MOTIVO]) {
    const [antes, despues] = cambios['Fecha y hora'] || [];
    return {
      tipo: 'reprog', icono: 'bi-arrow-repeat', titulo: 'Reprogramada',
      detalle: `<span class="antes">${histEsc(histFechaCita(antes))}</span> <i class="bi bi-arrow-right"></i> <b>${histEsc(histFechaCita(despues))}</b>
                <div class="motivo"><q>${histEsc(cambios[HIST_LLAVE_MOTIVO][1])}</q></div>`,
    };
  }
  if (ev.accion === 'alta') {
    const fecha = (cambios['Fecha y hora'] || [])[1];
    return { tipo: 'alta', icono: 'bi-calendar-plus', titulo: 'Agendada', detalle: fecha ? `Para el ${histEsc(histFechaCita(fecha))}` : '' };
  }
  const motivo = (cambios['Motivo'] || [])[1];
  if (ev.accion === 'baja' && motivo === HIST_MOTIVO_AUTO) {
    return { tipo: 'auto', icono: 'bi-robot', titulo: 'Pasó sola a No realizada', detalle: 'No hubo check-in ni se reprogramó en las siguientes 24 h.' };
  }
  if (ev.accion === 'baja') {
    const delCliente = (cambios[HIST_LLAVE_TIPO_CANC] || [])[1];
    const evidencia = (cambios[HIST_LLAVE_EVIDENCIA] || [])[1];
    return {
      tipo: 'baja', icono: delCliente ? 'bi-telephone-x' : 'bi-x-circle', titulo: histEsc(ev.resumen),
      detalle: (delCliente ? `<span class="v26-pill v26-pill--cliente-cancelo"><i class="bi bi-telephone-x"></i> ${histEsc(delCliente)}</span>` : '')
        + (motivo ? `<div class="motivo"><q>${histEsc(motivo)}</q></div>` : '')
        + (evidencia ? htmlEvidenciaCancelacion(ev.entidad_id) : ''),
    };
  }
  return { tipo: 'edicion', icono: 'bi-pencil', titulo: histEsc(ev.resumen), detalle: '' };
}

async function abrirHistorialCita(citaId) {
  document.getElementById('hist-cita-backdrop')?.remove();
  const bd = document.createElement('div');
  bd.id = 'hist-cita-backdrop';
  bd.className = 'v26-hist-backdrop';
  bd.innerHTML = `<div class="v26-hist" role="dialog" aria-modal="true" aria-labelledby="hist-cita-titulo">
      <button type="button" class="v26-hist-cerrar" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
      <div id="hist-cita-cuerpo"><p class="text-muted small mb-0">Cargando historial...</p></div>
    </div>`;
  document.body.appendChild(bd);
  requestAnimationFrame(() => bd.classList.add('show'));
  const cerrar = () => { bd.classList.remove('show'); setTimeout(() => bd.remove(), 200); document.removeEventListener('keydown', onEsc); };
  const onEsc = e => { if (e.key === 'Escape') cerrar(); };
  document.addEventListener('keydown', onEsc);
  bd.addEventListener('click', e => { if (e.target === bd || e.target.closest('.v26-hist-cerrar')) cerrar(); });

  const cuerpo = bd.querySelector('#hist-cita-cuerpo');
  try {
    const res = await fetch('../api/historial_cita.php?cita_id=' + encodeURIComponent(citaId));
    const data = await res.json();
    if (!data.ok) { cuerpo.innerHTML = `<p class="text-danger small mb-0">${histEsc(data.error)}</p>`; return; }
    const c = data.cita;
    const n = Number(c.reprogramaciones || 0);
    const eventos = data.eventos.map(ev => ({ ...histEvento(ev), cuando: histFechaUTC(ev.creado_en) }));
    cuerpo.innerHTML = `
      <div class="v26-hist-eyebrow">Historial de la cita</div>
      <h3 id="hist-cita-titulo">${histEsc(c.cliente_nombre)}</h3>
      <div class="v26-hist-sub"><i class="bi bi-person-badge"></i> ${histEsc(c.vendedor_nombre)} · <i class="bi bi-clock"></i> ${histEsc(histFechaCita(c.fecha_hora))}</div>
      <div class="v26-hist-resumen">
        <span class="v26-pill v26-pill--${histEsc(c.estado)}">${histEsc({ pendiente: 'Pendiente', en_curso: 'En curso', completada: 'Completada', no_realizada: 'No realizada', cancelada: 'Cancelada' }[c.estado] || c.estado)}</span>
        ${n ? `<span class="v26-pill v26-pill--reprogramada"><i class="bi bi-arrow-repeat"></i> Reprogramada ${n}/${data.max_reprogramaciones}</span>` : ''}
      </div>
      ${eventos.length ? `<ol class="v26-hist-linea">${eventos.map(e => `
        <li class="v26-hist-ev v26-hist-ev--${e.tipo}">
          <span class="punto"><i class="bi ${e.icono}"></i></span>
          <div class="txt">
            <div class="top"><b>${e.titulo}</b><span class="cuando">${histEsc(e.cuando)}</span></div>
            ${e.detalle ? `<div class="det">${e.detalle}</div>` : ''}
          </div>
        </li>`).join('')}</ol>`
      : '<p class="text-muted small mt-3 mb-0">Esta cita no tiene movimientos registrados en la bitácora.</p>'}`;
  } catch (e) {
    cuerpo.innerHTML = '<p class="text-danger small mb-0">No se pudo cargar el historial. Intenta de nuevo.</p>';
  }
}

document.addEventListener('click', e => {
  const el = e.target.closest('[data-historial-cita]');
  if (el) { e.preventDefault(); abrirHistorialCita(el.dataset.historialCita); }
});
