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
  // Botón visible para abrir el historial completo (antes solo se abría
  // tocando la etiqueta "Reprogramada", y casi nadie lo notaba).
  if (n || c.estado === 'cancelada' || c.estado === 'no_realizada') {
    linea += `<button type="button" class="v26-hist-link" data-historial-cita="${c.id}"><i class="bi bi-clock-history"></i> Ver historial de la cita</button>`;
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

// ── Día (hora de México) de una fecha UTC o local, para agrupar ──────────
const HIST_TZ = 'America/Mexico_City';
function histDiaUTC(fechaUtc) {
  const d = new Date(String(fechaUtc).replace(' ', 'T') + (String(fechaUtc).endsWith('Z') ? '' : 'Z'));
  return isNaN(d) ? '' : d.toLocaleDateString('en-CA', { timeZone: HIST_TZ }); // YYYY-MM-DD
}
function histHoraUTC(fechaUtc) {
  const d = new Date(String(fechaUtc).replace(' ', 'T') + (String(fechaUtc).endsWith('Z') ? '' : 'Z'));
  return isNaN(d) ? '' : d.toLocaleTimeString('es-MX', { hour: 'numeric', minute: '2-digit', timeZone: HIST_TZ });
}
function histEpochUTC(fechaUtc) {
  return new Date(String(fechaUtc).replace(' ', 'T') + (String(fechaUtc).endsWith('Z') ? '' : 'Z')).getTime();
}
function histTituloDia(ymd) {
  const d = new Date(ymd + 'T12:00:00');
  const t = d.toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'short' }).replace(/\./g, '').replace(',', '');
  return t.charAt(0).toUpperCase() + t.slice(1);
}
function histMetros(m) {
  if (m === null || m === undefined || m === '') return '';
  m = Number(m);
  return m >= 1000 ? `${(m / 1000).toFixed(1)} km` : `${Math.round(m)} m`;
}
function histSegundos(s) {
  if (s < 60) return `${Math.round(s)} seg`;
  const m = Math.round(s / 60);
  return m < 60 ? `${m} min` : `${Math.floor(m / 60)} h${m % 60 ? ' ' + (m % 60) + ' min' : ''}`;
}
const HIST_TIPO_TXT = { entrada: 'entrada', salida: 'salida' };
const HIST_FALLO_TXT = {
  gps_impreciso: 'GPS impreciso',
  rechazo_servidor: 'el sistema no lo aceptó',
  error_envio: 'sin conexión al enviar',
};

// Intentos y check-ins -> eventos con { t (epoch), dia, tipo, icono, titulo, detalle }.
function histEventosCampo(intentos, checkins) {
  const out = [];
  const registrado = {}; // "entrada|2026-10-21" -> epoch del registro
  for (const ch of checkins) {
    const k = ch.tipo + '|' + histDiaUTC(ch.fecha_hora);
    if (!(k in registrado)) registrado[k] = histEpochUTC(ch.fecha_hora);
  }
  // Pantalla abierta: solo si ese día NO terminó en registro (si sí, es ruido).
  // Varias aperturas el mismo día sin registro -> una sola línea con el conteo.
  const aperturas = {};
  for (const it of intentos) {
    const dia = histDiaUTC(it.creado_en), t = histEpochUTC(it.creado_en), tipo = HIST_TIPO_TXT[it.tipo] || it.tipo;
    if (it.etapa === 'abrio_pantalla') {
      if ((it.tipo + '|' + dia) in registrado) continue;
      const k = it.tipo + '|' + dia;
      if (aperturas[k]) { aperturas[k].veces++; continue; }
      aperturas[k] = { t, dia, tipoCita: it.tipo, veces: 1, hora: histHoraUTC(it.creado_en) };
      continue;
    }
    if (it.etapa === 'pregunta_ubicacion') {
      out.push({ t, dia, tipo: 'campo-aviso', icono: 'bi-question-circle', titulo: `La app le preguntó si estaba en el cliente`,
        detalle: `Al registrar la ${tipo}, el GPS lo ubicaba${it.distancia_metros ? ' a ' + histMetros(it.distancia_metros) : ''} lejos del cliente.` });
      continue;
    }
    const causa = HIST_FALLO_TXT[it.etapa] || it.etapa;
    const prec = it.etapa === 'gps_impreciso' && it.accuracy ? ` (precisión de ${Math.round(it.accuracy)} m)` : '';
    out.push({ t, dia, tipo: 'campo-fallo', icono: 'bi-exclamation-triangle', titulo: `Intento de ${tipo} fallido`,
      detalle: `${histEsc(causa)}${prec}${it.etapa === 'rechazo_servidor' && it.motivo ? ': ' + histEsc(it.motivo) : ''}` });
  }
  for (const a of Object.values(aperturas)) {
    const tipo = HIST_TIPO_TXT[a.tipoCita] || a.tipoCita;
    out.push({ t: a.t, dia: a.dia, tipo: 'campo-abrio', icono: 'bi-phone',
      titulo: `Abrió la pantalla de ${tipo}${a.veces > 1 ? ` (${a.veces} veces)` : ''}`,
      detalle: `No registró la ${tipo} ese día.` });
  }
  // Entradas y salidas registradas.
  let tEntrada = null;
  for (const ch of checkins) {
    const t = histEpochUTC(ch.fecha_hora), dia = histDiaUTC(ch.fecha_hora);
    let estado;
    if (Number(ch.verificado) === 1) {
      if (ch.correccion === 'por_revisar') estado = 'Pin del cliente por revisar en Ubicaciones';
      else if (ch.correccion === 'aprobada') estado = 'El pin del cliente se movió a donde estaba el vendedor (aprobado por admin)';
      else if (ch.correccion || ch.ubicacion_corregida === true || ch.ubicacion_corregida === 't') estado = 'El pin del cliente se movió a donde estaba el vendedor';
      else estado = 'GPS verificado';
      if (ch.correccion_distancia) estado += ` · el pin de la dirección registrada estaba a ${histMetros(ch.correccion_distancia)}`;
      else if (ch.distancia_metros !== null && ch.distancia_metros !== undefined && !ch.correccion) estado += ` · a ${histMetros(ch.distancia_metros)} del cliente`;
    } else {
      estado = `Fuera de zona${ch.distancia_metros ? ' · a ' + histMetros(ch.distancia_metros) + ' del cliente' : ''}`;
    }
    if (ch.tipo === 'entrada') tEntrada = t;
    if (ch.tipo === 'salida' && tEntrada !== null && t >= tEntrada) estado += ` · ${histSegundos((t - tEntrada) / 1000)} después de la entrada`;
    out.push({ t, dia, tipo: Number(ch.verificado) === 1 ? 'campo-ok' : 'campo-fuera',
      icono: ch.tipo === 'entrada' ? 'bi-box-arrow-in-right' : 'bi-box-arrow-right',
      titulo: ch.tipo === 'entrada' ? 'Entrada registrada' : 'Salida registrada', detalle: histEsc(estado) });
  }
  return out;
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
    // Eventos de la bitácora + lo que pasó en campo, en orden y agrupados por día.
    let nReprog = 0;
    const fechasCita = {}; // YYYY-MM-DD -> 'original' | 'programada'
    const bit = data.eventos.map(ev => {
      const e = histEvento(ev);
      const cambios = ev.cambios || {};
      if (e.tipo === 'reprog') {
        nReprog++;
        e.titulo = `Reprogramada (${nReprog} de ${data.max_reprogramaciones})`;
        const antes = (cambios['Fecha y hora'] || [])[0];
        if (antes && !fechasCita[String(antes).slice(0, 10)]) fechasCita[String(antes).slice(0, 10)] = 'programada';
      }
      if (e.tipo === 'alta') {
        const f = (cambios['Fecha y hora'] || [])[1];
        if (f) fechasCita[String(f).slice(0, 10)] = 'original';
      }
      return { ...e, t: histEpochUTC(ev.creado_en), dia: histDiaUTC(ev.creado_en) };
    });
    const campo = histEventosCampo(data.intentos || [], data.checkins || []);
    const todos = [...bit, ...campo].sort((a, b) => a.t - b.t);
    const entrada = (data.checkins || []).find(ch => ch.tipo === 'entrada');
    const diaVisita = entrada ? histDiaUTC(entrada.fecha_hora) : null;
    const etiquetaDia = (d) => d === diaVisita ? ' · día de la visita'
      : fechasCita[d] === 'original' ? ' · fecha original de la cita'
      : fechasCita[d] === 'programada' ? ' · la cita estaba para este día' : '';
    let html = '', diaActual = null;
    for (const e of todos) {
      if (e.dia !== diaActual) {
        diaActual = e.dia;
        html += `<li class="v26-hist-dia">${histEsc(histTituloDia(e.dia))}${etiquetaDia(e.dia)}</li>`;
      }
      html += `
        <li class="v26-hist-ev v26-hist-ev--${e.tipo}">
          <span class="punto"><i class="bi ${e.icono}"></i></span>
          <div class="txt">
            <div class="top"><b>${e.titulo}</b><span class="cuando">${histEsc(histHoraUTC(new Date(e.t).toISOString()))}</span></div>
            ${e.detalle ? `<div class="det">${e.detalle}</div>` : ''}
          </div>
        </li>`;
    }
    const sub = entrada && c.estado === 'completada'
      ? `se hizo el ${histEsc(histTituloDia(diaVisita).toLowerCase())}, ${histEsc(histHoraUTC(entrada.fecha_hora))}`
      : `programada para el ${histEsc(histFechaCita(c.fecha_hora))}`;
    cuerpo.innerHTML = `
      <div class="v26-hist-eyebrow">Historial de la cita</div>
      <h3 id="hist-cita-titulo">${histEsc(c.cliente_nombre)}</h3>
      <div class="v26-hist-sub"><i class="bi bi-person-badge"></i> ${histEsc(c.vendedor_nombre)} · ${sub}</div>
      <div class="v26-hist-resumen">
        <span class="v26-pill v26-pill--${histEsc(c.estado)}">${histEsc({ pendiente: 'Pendiente', en_curso: 'En curso', completada: 'Completada', no_realizada: 'No realizada', cancelada: 'Cancelada' }[c.estado] || c.estado)}</span>
        ${n ? `<span class="v26-pill v26-pill--reprogramada"><i class="bi bi-arrow-repeat"></i> Reprogramada ${n} de ${data.max_reprogramaciones} veces</span>` : ''}
      </div>
      ${todos.length ? `<ol class="v26-hist-linea">${html}</ol>`
      : '<p class="text-muted small mt-3 mb-0">Esta cita no tiene movimientos registrados.</p>'}`;
  } catch (e) {
    cuerpo.innerHTML = '<p class="text-danger small mb-0">No se pudo cargar el historial. Intenta de nuevo.</p>';
  }
}

document.addEventListener('click', e => {
  const el = e.target.closest('[data-historial-cita]');
  if (el) { e.preventDefault(); abrirHistorialCita(el.dataset.historialCita); }
});
