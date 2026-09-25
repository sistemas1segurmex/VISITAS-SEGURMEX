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
  // Entrada que corrigió el pin del cliente (api/checkin.php); si el admin
  // la revierte, verificado vuelve a 0 y se ve "Fuera de zona".
  if (cita.checkin_verificado == 1 && cita.checkin_correccion) return '<span class="v26-pill v26-pill--verificado">Ubicación corregida</span>';
  return cita.checkin_verificado == 1
    ? '<span class="v26-pill v26-pill--verificado">GPS verificado</span>'
    : '<span class="v26-pill v26-pill--noverificado">Fuera de zona</span>';
}

// citas.fecha_hora es la hora que el vendedor capturó directamente en local
// (nunca pasa por UTC) -- se muestra tal cual, sin convertir. NO usar esta
// función con timestamps que sí sean UTC (prospecciones.hora_inicio/fin,
// tracking, checkins...): para esos usar horaCortaUTC() de abajo.
function horaCorta(fechaHora) {
  const d = new Date(fechaHora.replace(' ', 'T'));
  if (isNaN(d)) return fechaHora;
  return d.toLocaleTimeString('es-MX', { hour: 'numeric', minute: '2-digit' });
}

// Para timestamps guardados en UTC (CURRENT_TIMESTAMP de Postgres, como
// prospecciones.hora_inicio/hora_fin) -- a diferencia de horaCorta() de
// arriba, esta sí convierte, y fuerza la zona de México explícita para que
// se vea igual sin importar en qué zona horaria esté configurado el celular
// de quien lo ve.
function horaCortaUTC(fechaUtc) {
  if (!fechaUtc) return '';
  const d = new Date(String(fechaUtc).replace(' ', 'T') + 'Z');
  if (isNaN(d.getTime())) return fechaUtc;
  return d.toLocaleTimeString('es-MX', { hour: 'numeric', minute: '2-digit', timeZone: 'America/Mexico_City' });
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
    programarRecordatoriosLocales(data.citas);
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

// ---------------------------------------------------------------------
// Ubicación bloqueada (error 1 de geolocalización). "Reintentar" solo no
// sirve: el bloqueo está en la configuración del teléfono, así que se le
// dan al vendedor los pasos exactos según su teléfono/navegador para que lo
// resuelva solo (antes mandaban captura por WhatsApp). La pantalla vuelve a
// pedir la ubicación SIN recargar, para no perder la foto ya tomada.
// ---------------------------------------------------------------------
function tipoDispositivoUbicacion() {
  const ua = navigator.userAgent || '';
  const esIos = /iPhone|iPad|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  if (window.Capacitor && typeof window.Capacitor.isNativePlatform === 'function' && window.Capacitor.isNativePlatform()) {
    return esIos ? 'iphone_safari' : 'android_app';
  }
  if (esIos) return /CriOS/.test(ua) ? 'iphone_chrome' : 'iphone_safari';
  if (/Android/.test(ua)) return 'android_chrome';
  return 'otro';
}

function pasosPermisoUbicacion() {
  switch (tipoDispositivoUbicacion()) {
    case 'iphone_safari':
      return { titulo: 'Tu iPhone no le está dando la ubicación a esta página.', pasos: [
        '<b>Ajustes → Privacidad y seguridad → Localización</b>: actívala.',
        'En esa misma pantalla, <b>Sitios web de Safari</b>: elige "Mientras se usa la app" y activa <b>Ubicación exacta</b>.',
        'Aquí en Safari, toca <b>aA</b> junto a la dirección → <b>Configuración del sitio web → Ubicación → Permitir</b>.',
      ] };
    case 'iphone_chrome':
      return { titulo: 'Tu iPhone no le está dando la ubicación a Chrome.', pasos: [
        '<b>Ajustes → Privacidad y seguridad → Localización</b>: actívala.',
        'En esa misma pantalla, <b>Chrome</b>: elige "Mientras se usa la app" y activa <b>Ubicación exacta</b>.',
      ] };
    case 'android_app':
      return { titulo: 'La app Visitas no tiene permiso de ubicación.', pasos: [
        'Baja la barra de notificaciones y revisa que la <b>Ubicación</b> del teléfono esté activada.',
        '<b>Ajustes → Aplicaciones → Visitas → Permisos → Ubicación</b>: elige "Permitir solo con la app en uso" y activa <b>Usar ubicación precisa</b>.',
      ] };
    case 'android_chrome':
      return { titulo: 'Chrome no le está dando la ubicación a esta página.', pasos: [
        'Baja la barra de notificaciones y revisa que la <b>Ubicación</b> del teléfono esté activada.',
        'Aquí en Chrome, toca el ícono junto a la dirección (candado o ajustes) → <b>Permisos → Ubicación → Permitir</b>.',
        'Si no aparece: <b>Ajustes → Aplicaciones → Chrome → Permisos → Ubicación</b> → "Permitir solo con la app en uso" y <b>ubicación precisa</b> activada.',
      ] };
    default:
      return { titulo: 'El navegador no le está dando la ubicación a esta página.', pasos: [
        'Toca el ícono junto a la dirección de la página y en <b>Ubicación</b> elige <b>Permitir</b>.',
      ] };
  }
}

// HTML con los pasos y un botón #btn-reintentar-gps (cada pantalla le pone
// su propio listener, igual que al "Reintentar" normal).
function htmlUbicacionBloqueada() {
  const { titulo, pasos } = pasosPermisoUbicacion();
  return `<div class="alert alert-warning py-2 mb-0" style="font-size:.84rem;">
      <div class="fw-bold mb-1">⚠️ ${titulo} Para activarla:</div>
      <ol class="mb-2 ps-3">${pasos.map(p => `<li>${p}</li>`).join('')}</ol>
      <button type="button" id="btn-reintentar-gps" class="v26-btn v26-btn-primary" style="padding:6px 14px;font-size:.8rem;width:auto;display:inline-block;">Ya lo activé, reintentar</button>
      <div class="mt-2" style="font-size:.74rem;opacity:.85;">Si después de activarlo sigue igual, recarga la página (tendrás que tomar la foto otra vez).</div>
    </div>`;
}

// Error 2 (no disponible) o 3 (tiempo agotado). En Android con la
// Ubicación del teléfono APAGADA la WebView/Chrome no contesta "permiso
// denegado": se queda esperando y termina en tiempo agotado -- igual que
// sin señal dentro de un edificio, así que no se pueden distinguir y se
// cubren los dos casos. El primer paso va según el teléfono.
function pasosUbicacionNoDisponible() {
  const tipo = tipoDispositivoUbicacion();
  const activar = tipo.startsWith('iphone')
    ? 'Revisa que la <b>Localización</b> esté activada: <b>Ajustes → Privacidad y seguridad → Localización</b>.'
    : 'Revisa que la <b>Ubicación</b> del teléfono esté activada: baja la barra de notificaciones y toca <b>Ubicación</b>.';
  return [activar, 'Si estás dentro de un edificio o vehículo, sal a espacio abierto o acércate a una ventana.'];
}

function htmlUbicacionNoDisponible(codigo) {
  const titulo = codigo === 3 ? 'No llegó tu ubicación a tiempo.' : 'El teléfono no pudo darnos tu ubicación.';
  return `<div class="alert alert-warning py-2 mb-0" style="font-size:.84rem;">
      <div class="fw-bold mb-1">⚠️ ${titulo}</div>
      <ol class="mb-2 ps-3">${pasosUbicacionNoDisponible().map(p => `<li>${p}</li>`).join('')}</ol>
      <button type="button" id="btn-reintentar-gps" class="v26-btn v26-btn-primary" style="padding:6px 14px;font-size:.8rem;width:auto;display:inline-block;">Reintentar</button>
    </div>`;
}

// Ubicación APROXIMADA: con "Ubicación exacta" (iPhone) o "Usar ubicación
// precisa" (Android) apagada, el teléfono sí da ubicación pero borrosa a
// propósito (±1-3 km). Salir a espacio abierto no la mejora -- hay que
// prender ese interruptor. Peor que esto casi siempre es eso; entre 500 m
// y 1 km sí suele ser falta de señal y sigue el mensaje de siempre.
const UMBRAL_UBICACION_APROXIMADA_M = 1000;

function pasoUbicacionExacta() {
  switch (tipoDispositivoUbicacion()) {
    case 'iphone_safari': return '<b>Ajustes → Privacidad y seguridad → Localización → Sitios web de Safari</b> → activa <b>Ubicación exacta</b>.';
    case 'iphone_chrome': return '<b>Ajustes → Privacidad y seguridad → Localización → Chrome</b> → activa <b>Ubicación exacta</b>.';
    case 'android_app':   return '<b>Ajustes → Aplicaciones → Visitas → Permisos → Ubicación</b> → activa <b>Usar ubicación precisa</b>.';
    case 'android_chrome': return '<b>Ajustes → Aplicaciones → Chrome → Permisos → Ubicación</b> → activa <b>Usar ubicación precisa</b>.';
    default: return 'En la configuración de ubicación del teléfono, activa la <b>ubicación exacta/precisa</b>.';
  }
}

function htmlUbicacionAproximada(precision) {
  return `<div class="alert alert-warning py-2 mb-0" style="font-size:.84rem;">
      <div class="fw-bold mb-1">⚠️ Tu teléfono está dando una ubicación aproximada (±${Math.round(precision)} m), no la exacta.</div>
      <ol class="mb-2 ps-3"><li>${pasoUbicacionExacta()}</li><li>Si ya está activada, sal a espacio abierto o acércate a una ventana.</li></ol>
      <button type="button" id="btn-reintentar-gps" class="v26-btn v26-btn-primary" style="padding:6px 14px;font-size:.8rem;width:auto;display:inline-block;">Ya lo activé, reintentar</button>
    </div>`;
}

function textoUbicacionAproximada(precision) {
  return `Tu teléfono está dando una ubicación aproximada (±${Math.round(precision)} m), no la exacta.\n\n1. ${pasoUbicacionExacta().replace(/<[^>]+>/g, '')}\n2. Si ya está activada, sal a espacio abierto o acércate a una ventana.\n\nDespués vuelve a tocar el botón.`;
}

// Versión en texto plano (para alert()).
function textoUbicacionNoDisponible() {
  return 'No se pudo obtener tu ubicación.\n\n' + pasosUbicacionNoDisponible().map((p, i) => `${i + 1}. ${p.replace(/<[^>]+>/g, '')}`).join('\n') + '\n\nDespués vuelve a tocar el botón.';
}

// Versión en texto plano (para alert()).
function textoUbicacionBloqueada() {
  const { titulo, pasos } = pasosPermisoUbicacion();
  return `${titulo} Para activarla:\n\n` + pasos.map((p, i) => `${i + 1}. ${p.replace(/<[^>]+>/g, '')}`).join('\n') + '\n\nDespués vuelve a tocar el botón.';
}

// ---------------------------------------------------------------------
// Envío con tiempo límite (señal débil en campo). fetch() por sí solo espera
// sin límite: con la señal congelada el botón se quedaba en "Enviando..." y
// el vendedor tenía que cerrar la app, perdiendo foto y GPS. Aquí se corta a
// los `ms` y se distingue el motivo (e.tipo): 'timeout' (no hubo respuesta),
// 'red' (sin conexión) o 'servidor' (respondió algo que no es JSON: un
// error de PHP, un 502 del proxy) -- antes los tres se veían como "Error de
// conexión". Una respuesta JSON con ok:false se regresa tal cual.
// ---------------------------------------------------------------------
async function enviarConTiempoLimite(url, opciones = {}, ms = 60000) {
  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), ms);
  try {
    let res;
    try {
      res = await fetch(url, { ...opciones, signal: ctrl.signal });
    } catch (e) {
      throw Object.assign(new Error('envio'), { tipo: ctrl.signal.aborted ? 'timeout' : 'red' });
    }
    try {
      return await res.json();
    } catch (e) {
      throw Object.assign(new Error('envio'), { tipo: ctrl.signal.aborted ? 'timeout' : 'servidor', status: res.status });
    }
  } finally {
    clearTimeout(timer);
  }
}

// Texto para el vendedor según el motivo de enviarConTiempoLimite(). Los
// reintentos son seguros: api/checkin.php y api/prospeccion.php reconocen un
// registro que ya se había guardado y no lo duplican.
function mensajeErrorEnvio(e) {
  if (e && e.tipo === 'timeout') return 'La señal está muy débil y no hubo respuesta. Tu foto y tu ubicación siguen aquí: toca el botón para reintentar (si ya se había guardado, no se duplica).';
  if (e && e.tipo === 'servidor') return `El servidor tuvo un problema (error ${e.status}). Intenta de nuevo en un momento; si sigue pasando, avisa a Sistemas.`;
  return 'Sin conexión. Tu foto y tu ubicación siguen aquí: toca el botón para reintentar cuando tengas señal.';
}

// Mientras una pantalla sube un check-in o una parada, el tracking no pide
// GPS ni manda nada (compite por el GPS y por la poca señal que haya).
let envioEnCurso = false;

// ---------------------------------------------------------------------
// Puntos de tracking que no se pudieron mandar (sin señal): se guardan en
// el celular y se mandan juntos en cuanto hay conexión, con la hora real
// del GPS -- antes se perdían y el recorrido del mapa quedaba con huecos.
// ---------------------------------------------------------------------
const COLA_TRACKING_KEY = 'visitas_tracking_pendiente';
const COLA_TRACKING_MAX = 240; // ~2 h de puntos a uno cada 30 s
let vaciandoColaTracking = false;
// Copia en memoria por si localStorage no está disponible (bloqueado o
// lleno): así el tracking sigue saliendo aunque no sobreviva a cerrar la app.
let colaTrackingMemoria = [];

function leerColaTracking() {
  try {
    const guardada = localStorage.getItem(COLA_TRACKING_KEY);
    if (guardada) return JSON.parse(guardada);
  } catch (e) {}
  return colaTrackingMemoria.slice();
}
function guardarColaTracking(cola) {
  colaTrackingMemoria = cola.slice(-COLA_TRACKING_MAX);
  try { localStorage.setItem(COLA_TRACKING_KEY, JSON.stringify(colaTrackingMemoria)); } catch (e) {}
}

async function vaciarColaTracking() {
  if (vaciandoColaTracking || envioEnCurso) return;
  const cola = leerColaTracking();
  if (!cola.length) return;
  vaciandoColaTracking = true;
  try {
    const fd = new FormData();
    fd.append('puntos', JSON.stringify(cola));
    const data = await enviarConTiempoLimite('../api/tracking.php', { method: 'POST', body: fd }, 20000);
    // Se quitan solo los que se mandaron (pudieron llegar más mientras tanto).
    // Un ok:false es un rechazo definitivo (datos inválidos), no se reintenta
    // -- salvo sesión vencida, que se reintenta tras volver a entrar.
    if (data.ok || data.error !== 'No autenticado') guardarColaTracking(leerColaTracking().slice(cola.length));
  } catch (e) {
    // sin señal todavía: se quedan en la cola
  } finally {
    vaciandoColaTracking = false;
  }
}
window.addEventListener('online', vaciarColaTracking);

// Envía la ubicación actual al servidor (tracking en vivo mientras la app
// esté abierta en el navegador del vendedor).
function iniciarTrackingPeriodico(intervaloMs = 30000) {
  registrarPushNativo(); // no-op fuera de la app nativa -- ver más abajo

  if (!('geolocation' in navigator)) return;

  // Margen máximo (ms) para aceptar una posición como "de ahorita" -- el GPS
  // del celular a veces regresa una posición en caché/vieja cuando no logra
  // una señal fresca rápido (dentro de un edificio, en movimiento, la app en
  // segundo plano). pos.timestamp es la hora REAL en que se capturó esa
  // coordenada (no la de cuándo llega la respuesta) -- si viene más vieja
  // que esto, se descarta en vez de mandarla como si fuera la ubicación
  // actual. Antes se guardaba con la hora del envío (CURRENT_TIMESTAMP del
  // servidor), no la del GPS, creando saltos imposibles en el mapa del
  // recorrido (ej. Guadalajara a León en menos de una hora).
  const MAX_ANTIGUEDAD_POSICION_MS = 2 * 60 * 1000;

  // Radio máximo aceptado (metros) para la precisión del fix. Sin GPS real
  // (ej. permiso "aproximada" en Android, o navegador de escritorio sin
  // chip GPS) el navegador resuelve la posición por red/Wi-Fi/IP, lo cual
  // puede regresar una coordenada a kilómetros de distancia (ej. marcar
  // Guadalajara estando físicamente en León). Esos fixes traen accuracy muy
  // alto, así que se descartan en vez de guardarse como si fueran válidos.
  const MAX_PRECISION_ACEPTADA_M = 500;

  const enviar = () => {
    if (envioEnCurso) return;
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        const antiguedadMs = Date.now() - pos.timestamp;
        if (antiguedadMs > MAX_ANTIGUEDAD_POSICION_MS) return; // posición vieja/caché -- se descarta
        if (pos.coords.accuracy > MAX_PRECISION_ACEPTADA_M) return; // posición imprecisa (red/IP) -- se descarta
        // Siempre por la cola: si hay señal sale de inmediato junto con lo
        // que hubiera pendiente; si no, espera ahí.
        const cola = leerColaTracking();
        cola.push({ lat: pos.coords.latitude, lng: pos.coords.longitude, accuracy: pos.coords.accuracy, ts: pos.timestamp });
        guardarColaTracking(cola);
        vaciarColaTracking();
      },
      () => {},
      { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
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
    if (data.ok) renderCtaProspeccion(data.paradas || []);
  } catch (e) {
    // Silencioso: no bloqueamos el resto de la pantalla por esto.
  }
}

// Cada parada ahora es su propia pantalla (persona/empresa, dirección, foto
// y GPS de entrada/salida -- ver vendedor/prospeccion.php), así que aquí en
// Inicio solo se decide qué tarjeta mostrar según el estado del día.
function renderCtaProspeccion(paradas) {
  const cont = document.getElementById('cta-prospeccion');
  if (!cont) return;

  const abierta = paradas.find(p => !p.hora_fin);
  // El listado de hoy ya vive en su propia pestaña del menú (Paradas ->
  // mis_paradas.php), no hace falta repetir un enlace aquí también.

  if (abierta) {
    cont.innerHTML = `
      <a href="prospeccion.php" class="v26-banner v26-banner--ok" style="text-decoration:none;display:flex;">
        <i class="bi bi-signpost-2-fill icon"></i>
        <div class="txt">
          <strong>En prospección: ${abierta.nombre}</strong>
          <p>Desde las ${horaCortaUTC(abierta.hora_inicio)} · toca para registrar tu salida</p>
        </div>
      </a>`;
    return;
  }

  cont.innerHTML = `
    <a href="prospeccion.php" class="v26-cta">
      <span class="v26-cta-icon"><i class="bi bi-signpost-2-fill"></i></span>
      <span class="v26-cta-text">
        <strong>Salí a buscar prospectos</strong>
        <small>Registra tu recorrido de hoy</small>
      </span>
      <i class="bi bi-chevron-right chev"></i>
    </a>`;
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

// ---------------------------------------------------------------------
// Notificaciones nativas -- solo existen dentro de la app Android (ver
// app-android/README.md). Abierta en un navegador normal, esAppNativa()
// corta de inmediato y estas funciones no hacen nada.
// ---------------------------------------------------------------------

function esAppNativa() {
  return !!(window.Capacitor && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform());
}

let permisoNotificacionesPedido = false;
async function asegurarPermisoNotificaciones() {
  if (!esAppNativa() || permisoNotificacionesPedido) return;
  permisoNotificacionesPedido = true;
  try { await Capacitor.Plugins.LocalNotifications.requestPermissions(); } catch (e) {}
}

// --- Recordatorio local: "tu cita es en 15 minutos" -- lo programa el
// propio celular, sigue funcionando aunque la app esté cerrada porque es
// una alarma del sistema operativo, no un proceso corriendo. ---
const MINUTOS_ANTES_RECORDATORIO_CITA = 15;

async function programarRecordatoriosLocales(citas) {
  if (!esAppNativa() || !Array.isArray(citas)) return;
  await asegurarPermisoNotificaciones();
  const LN = Capacitor.Plugins.LocalNotifications;
  try {
    // Se cancelan y se vuelven a programar en cada carga de "Mis visitas"
    // -- así nunca quedan duplicados ni un recordatorio de una cita que
    // cambió de hora o se canceló.
    await LN.cancel({ notifications: citas.map(c => ({ id: c.id })) });

    const ahora = Date.now();
    const notifs = [];
    citas.filter(c => c.estado === 'pendiente').forEach(c => {
      const horaCita = new Date(c.fecha_hora.replace(' ', 'T')).getTime();
      const disparo = horaCita - MINUTOS_ANTES_RECORDATORIO_CITA * 60000;
      if (disparo > ahora) {
        notifs.push({
          id: c.id,
          title: 'Cita próxima',
          body: `Tu cita con ${c.cliente_nombre} es en ${MINUTOS_ANTES_RECORDATORIO_CITA} minutos.`,
          schedule: { at: new Date(disparo) },
        });
      }
    });
    if (notifs.length) await LN.schedule({ notifications: notifs });
  } catch (e) {
    console.warn('No se pudieron programar los recordatorios locales', e);
  }
}

// --- Push: registra este celular para avisos que manda el servidor (ej.
// "se te olvidó cerrar una visita") -- ver api/push_registrar_token.php +
// api/cron_recordatorios.php + includes/fcm.php. ---
let pushYaRegistrado = false;
async function registrarPushNativo() {
  if (!esAppNativa() || pushYaRegistrado) return;
  pushYaRegistrado = true;
  await asegurarPermisoNotificaciones();
  const PN = Capacitor.Plugins.PushNotifications;
  try {
    const permiso = await PN.requestPermissions();
    if (permiso.receive !== 'granted') return;
    PN.addListener('registration', async (token) => {
      try {
        const fd = new FormData();
        fd.append('token', token.value);
        await fetch('../api/push_registrar_token.php', { method: 'POST', body: fd });
      } catch (e) { /* se reintenta solo la próxima vez que abra la app */ }
    });
    PN.addListener('registrationError', (err) => console.warn('Error registrando push', err));
    await PN.register();
  } catch (e) {
    console.warn('No se pudo registrar para notificaciones push', e);
  }
}
