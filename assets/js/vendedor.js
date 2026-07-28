// Lógica común de las páginas del vendedor: listado de citas del día
// y tracking periódico de ubicación mientras la app está abierta.

function badgeEstado(cita) {
  if (cita.retrasada) return '<span class="badge badge-pendiente">Retrasada</span>';
  const map = {
    pendiente: 'Pendiente',
    en_curso: 'En curso',
    completada: 'Completada',
    no_realizada: 'No realizada',
  };
  return `<span class="badge badge-${cita.estado}">${map[cita.estado] || cita.estado}</span>`;
}

function badgeVerificado(cita) {
  if (cita.checkin_verificado === null || cita.checkin_verificado === undefined) return '';
  return cita.checkin_verificado == 1
    ? '<span class="badge badge-verificado">GPS verificado</span>'
    : '<span class="badge badge-noverificado">Fuera de zona</span>';
}

async function cargarCitas() {
  const cont = document.getElementById('lista-citas');
  if (!cont) return;
  cont.innerHTML = '<p class="text-muted">Cargando...</p>';
  try {
    const res = await fetch('../api/citas.php?fecha=' + new Date().toISOString().slice(0, 10));
    const data = await res.json();
    if (!data.ok) {
      cont.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
      return;
    }
    if (data.citas.length === 0) {
      cont.innerHTML = '<p class="text-muted">No tienes citas programadas para hoy.</p>';
      return;
    }
    cont.innerHTML = data.citas.map(c => `
      <div class="card card-cita shadow-sm">
        <div class="card-body d-flex justify-content-between align-items-start flex-wrap gap-2">
          <div>
            <h6 class="mb-1">${c.cliente_nombre}</h6>
            <div class="text-muted small">${c.direccion}</div>
            <div class="text-muted small">🕒 ${c.fecha_hora}</div>
            <div class="mt-1">${badgeEstado(c)} ${badgeVerificado(c)}</div>
          </div>
          <a href="checkin.php?cita_id=${c.id}" class="btn btn-sm btn-brand align-self-center">
            ${c.tiene_entrada == 0 ? 'Check-in' : (c.tiene_salida == 0 ? 'Check-out' : 'Ver')}
          </a>
        </div>
      </div>
    `).join('');
  } catch (e) {
    cont.innerHTML = '<div class="alert alert-danger">No se pudo cargar la información.</div>';
  }
}

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
