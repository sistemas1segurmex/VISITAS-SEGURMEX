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

function renderResumenPendientes(citas) {
  const cont = document.getElementById('resumen-pendientes');
  if (!cont) return;

  const retrasadas = citas.filter(c => c.retrasada);
  const salidasPendientes = citas.filter(c => c.tiene_entrada > 0 && c.tiene_salida == 0 && c.estado !== 'no_realizada');

  if (retrasadas.length === 0 && salidasPendientes.length === 0) {
    cont.innerHTML = `
      <div class="v26-banner v26-banner--ok">
        <i class="bi bi-check-circle-fill icon"></i>
        <div class="txt"><p>Vas al día, no tienes pendientes por ahora.</p></div>
      </div>`;
    return;
  }

  const items = [];
  if (retrasadas.length > 0) {
    items.push(`${retrasadas.length} cita${retrasadas.length > 1 ? 's' : ''} retrasada${retrasadas.length > 1 ? 's' : ''} (${retrasadas.map(c => c.cliente_nombre).join(', ')})`);
  }
  if (salidasPendientes.length > 0) {
    items.push(`${salidasPendientes.length} salida${salidasPendientes.length > 1 ? 's' : ''} por registrar (${salidasPendientes.map(c => c.cliente_nombre).join(', ')})`);
  }

  cont.innerHTML = `
    <div class="v26-banner">
      <i class="bi bi-exclamation-circle-fill icon"></i>
      <div class="txt">
        <strong>Tienes pendientes por resolver</strong>
        <ul>${items.map(i => `<li>${i}</li>`).join('')}</ul>
      </div>
    </div>`;
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
    if (esManana) {
      const resumen = document.getElementById('resumen-pendientes');
      if (resumen) resumen.innerHTML = '';
    } else {
      renderResumenPendientes(data.citas);
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
