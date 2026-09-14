// Bitácora del admin: dos pestañas que comparten filtros y bloque de stats
// -- "Cambios" (línea de tiempo de altas/ediciones/bajas que hacen los
// vendedores) y "Accesos" (tabla de logins, correctos o no). Ver
// api/admin_bitacora.php para el contrato de datos.

function animarNumero(el, valorFinal) {
  if (!el) return;
  const inicio = parseInt(el.dataset.valor || '0', 10);
  el.dataset.valor = valorFinal;
  if (inicio === valorFinal) { el.textContent = valorFinal; return; }
  const duracion = 600, t0 = performance.now();
  function paso(t) {
    const p = Math.min((t - t0) / duracion, 1);
    const suavizado = 1 - Math.pow(1 - p, 3);
    el.textContent = Math.round(inicio + (valorFinal - inicio) * suavizado);
    if (p < 1) requestAnimationFrame(paso);
  }
  requestAnimationFrame(paso);
}

// creado_en llega "YYYY-MM-DD HH:MM:SS" en UTC (sesión de Postgres forzada a
// UTC, ver includes/db.php) -- mismo criterio que ultimaConexionTexto() en
// admin_vendedor_detalle.js: se le agrega 'Z' para que Date lo interprete
// como UTC y lo convierta solo a la hora local del navegador.
function aFechaLocal(fechaUtc) {
  const d = new Date(String(fechaUtc).replace(' ', 'T') + 'Z');
  return isNaN(d.getTime()) ? null : d;
}
function horaBonita(fechaUtc) {
  const d = aFechaLocal(fechaUtc);
  return d ? d.toLocaleTimeString('es-MX', { hour: 'numeric', minute: '2-digit' }) : '';
}
function diaEtiqueta(fechaUtc) {
  const d = aFechaLocal(fechaUtc);
  if (!d) return '';
  const hoy = new Date(); hoy.setHours(0, 0, 0, 0);
  const ayer = new Date(hoy); ayer.setDate(ayer.getDate() - 1);
  const diaEvento = new Date(d); diaEvento.setHours(0, 0, 0, 0);
  if (diaEvento.getTime() === hoy.getTime()) return 'Hoy';
  if (diaEvento.getTime() === ayer.getTime()) return 'Ayer';
  return d.toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: diaEvento.getFullYear() !== hoy.getFullYear() ? 'numeric' : undefined });
}

const PALETA_AVATAR = ['#4F46E5', '#F5A623', '#16A34A', '#E11D48', '#0EA5E9', '#9333EA', '#D97706', '#0891B2'];
function colorAvatar(id) { return PALETA_AVATAR[id % PALETA_AVATAR.length]; }
function iniciales(nombre) {
  const partes = String(nombre || '?').trim().split(/\s+/).filter(Boolean);
  return (partes.length > 1 ? partes[0][0] + partes[1][0] : (partes[0] || '?').slice(0, 2)).toUpperCase();
}

const ENTIDAD_INFO = {
  cliente:    { icono: 'bi-person-lines-fill', label: 'Cliente' },
  cita:       { icono: 'bi-calendar-event',    label: 'Cita' },
  cotizacion: { icono: 'bi-file-earmark-text', label: 'Cotización' },
  muestra:    { icono: 'bi-box-seam',          label: 'Muestra' },
  prospeccion:{ icono: 'bi-signpost-2-fill',   label: 'Prospección' },
};
const ACCION_INFO = {
  alta:    { clase: 'alta',    icono: 'bi-plus-lg' },
  edicion: { clase: 'edicion', icono: 'bi-pencil-fill' },
  baja:    { clase: 'baja',    icono: 'bi-x-lg' },
};

// ---------------------------------------------------------------------
// Segmented control Cambios/Accesos -- mismo patrón que #filtro-rol en
// admin_usuarios.js (slider posicionado por JS, no CSS puro).
// ---------------------------------------------------------------------
function posicionarSlider(btn) {
  const slider = document.getElementById('tab-slider');
  if (!slider || !btn) return;
  slider.style.left = btn.offsetLeft + 'px';
  slider.style.width = btn.offsetWidth + 'px';
}
document.querySelectorAll('#filtro-tab .opt').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('#filtro-tab .opt').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    posicionarSlider(btn);
    const tab = btn.dataset.tab;
    document.getElementById('panel-cambios').classList.toggle('d-none', tab !== 'cambios');
    document.getElementById('panel-accesos').classList.toggle('d-none', tab !== 'accesos');
  });
});
window.addEventListener('resize', () => posicionarSlider(document.querySelector('#filtro-tab .opt.active')));

// ---------------------------------------------------------------------
// Filtro de vendedor (mismo combo para las dos pestañas, se llena una vez)
// ---------------------------------------------------------------------
async function cargarVendedoresFiltro() {
  try {
    const res = await fetch('../api/usuarios.php');
    const data = await res.json();
    if (!data.ok) return;
    const vendedores = data.usuarios.filter(u => u.rol === 'vendedor');
    const opciones = vendedores.map(v => `<option value="${v.id}">${v.nombre}</option>`).join('');
    document.getElementById('filtro-cambios-vendedor').insertAdjacentHTML('beforeend', opciones);
    document.getElementById('filtro-accesos-vendedor').insertAdjacentHTML('beforeend', opciones);
  } catch (e) {
    // No bloquea el resto de la pantalla -- sin este combo poblado solo se
    // pierde el filtro por vendedor, las dos pestañas igual deben cargar.
    console.error('[Bitácora] cargarVendedoresFiltro:', e);
  }
}

// ---------------------------------------------------------------------
// Stats de arriba
// ---------------------------------------------------------------------
async function cargarResumen() {
  try {
  const res = await fetch('../api/admin_bitacora.php?accion=resumen');
  const data = await res.json();
  if (!data.ok) return;
  animarNumero(document.getElementById('stat-cambios-hoy'), data.cambios_hoy);
  animarNumero(document.getElementById('stat-altas-7d'), data.altas_7d);
  animarNumero(document.getElementById('stat-ediciones-7d'), data.ediciones_7d);
  animarNumero(document.getElementById('stat-bajas-mes'), data.bajas_mes);
  } catch (e) {
    console.error('[Bitácora] cargarResumen:', e);
  }
}

// ---------------------------------------------------------------------
// Pestaña Cambios
// ---------------------------------------------------------------------
function tarjetaEvento(ev) {
  const accion = ACCION_INFO[ev.accion] || ACCION_INFO.edicion;
  const entidad = ENTIDAD_INFO[ev.entidad] || { icono: 'bi-circle', label: ev.entidad };
  const diff = ev.cambios ? Object.entries(ev.cambios).map(([campo, valores]) => {
    const [antes, despues] = valores;
    return `<div class="v26-diff-fila"><b>${campo}</b>${
      antes ? `<span class="v26-diff-antes">${antes}</span><span class="v26-diff-flecha"><i class="bi bi-arrow-right"></i></span>` : ''
    }<span class="v26-diff-despues">${despues ?? '—'}</span></div>`;
  }).join('') : '';
  // resumen viene en tercera persona ("Editó al cliente X"), sin el nombre
  // del vendedor -- se antepone aquí y se pone en minúscula la primera
  // letra para que la frase se lea de corrido ("Oscar editó al cliente X").
  const resumenMin = ev.resumen.charAt(0).toLowerCase() + ev.resumen.slice(1);
  return `
    <div class="v26-evento v26-evento--${accion.clase}">
      <div class="v26-evento-icon"><i class="bi ${accion.icono}"></i></div>
      <div class="v26-evento-body">
        <div class="v26-evento-top">
          <div class="v26-evento-texto"><b>${ev.vendedor_nombre}</b> ${resumenMin}</div>
          <div class="v26-evento-hora">${horaBonita(ev.creado_en)}</div>
        </div>
        <div class="v26-evento-meta">
          <span class="v26-pill v26-pill--neutro"><i class="bi ${entidad.icono}"></i> ${entidad.label}</span>
          ${diff ? `<button type="button" class="v26-toggle-detalle" onclick="toggleEvento(this)">Ver detalle <i class="bi bi-chevron-down"></i></button>` : ''}
        </div>
        ${diff ? `<div class="v26-detalle-diff">${diff}</div>` : ''}
      </div>
    </div>`;
}
function toggleEvento(btn) {
  const ev = btn.closest('.v26-evento');
  const abrir = !ev.classList.contains('abierto');
  ev.classList.toggle('abierto', abrir);
  btn.firstChild.textContent = abrir ? 'Ocultar detalle ' : 'Ver detalle ';
}

function renderCambios(lista) {
  const cont = document.getElementById('lista-cambios');
  if (lista.length === 0) {
    cont.innerHTML = '<p class="v26-sin-resultados">Ningún cambio coincide con el filtro.</p>';
    return;
  }
  let html = '';
  let diaActual = null;
  for (const ev of lista) {
    const dia = diaEtiqueta(ev.creado_en);
    if (dia !== diaActual) { html += `<div class="v26-dia-header">${dia}</div>`; diaActual = dia; }
    html += tarjetaEvento(ev);
  }
  cont.innerHTML = html;
}

let timerBuscarCambios = null;
async function cargarCambios() {
  const params = new URLSearchParams({
    accion: 'cambios',
    vendedor_id: document.getElementById('filtro-cambios-vendedor').value,
    tipo_accion: document.getElementById('filtro-cambios-accion').value,
    entidad: document.getElementById('filtro-cambios-entidad').value,
    dias: document.getElementById('filtro-cambios-dias').value,
    q: document.getElementById('buscar-cambios').value.trim(),
  });
  const cont = document.getElementById('lista-cambios');
  cont.innerHTML = '<p class="text-muted small">Cargando...</p>';
  try {
    const res = await fetch('../api/admin_bitacora.php?' + params.toString());
    const data = await res.json();
    if (!data.ok) { cont.innerHTML = `<p class="text-danger small">${data.error}</p>`; return; }
    renderCambios(data.cambios);
  } catch (e) {
    console.error('[Bitácora] cargarCambios:', e);
    cont.innerHTML = '<p class="text-danger small">No se pudo cargar (revisa la consola).</p>';
  }
}

// ---------------------------------------------------------------------
// Pestaña Accesos
// ---------------------------------------------------------------------
function dispositivoInfo(userAgent) {
  const ua = userAgent || '';
  const movil = /Android|iPhone|iPad|Mobile/i.test(ua);
  const navegador = /Chrome/i.test(ua) ? 'Chrome' : /Firefox/i.test(ua) ? 'Firefox' : /Safari/i.test(ua) ? 'Safari' : 'Navegador';
  return { icono: movil ? 'bi-phone' : 'bi-display', label: `${movil ? 'Móvil' : 'Escritorio'} · ${navegador}` };
}
function filaAcceso(a) {
  const fallido = a.resultado !== 'correcto';
  const dispositivo = dispositivoInfo(a.user_agent);
  const nombre = a.vendedor_nombre || a.email_intentado;
  const sospecha = !a.usuario_id && a.resultado === 'fallido';
  return `
    <tr class="${fallido ? 'fallido' : ''}">
      <td>
        <div class="v26-vendedor-cel">
          <div class="avatar" style="background:${colorAvatar(a.usuario_id || 0)}">${iniciales(nombre)}</div>
          <div>
            <div class="fw-bold">${nombre}</div>
            ${a.vendedor_nombre ? `<div class="v26-vendedor-sub">${a.email_intentado}</div>` : ''}
          </div>
        </div>
      </td>
      <td class="v26-mono">${diaEtiqueta(a.creado_en)}, ${horaBonita(a.creado_en)}</td>
      <td>
        <span class="v26-pill-resultado ${fallido ? 'no' : 'ok'}"><i class="bi ${fallido ? 'bi-shield-x' : 'bi-shield-check'}"></i> ${a.motivo || (fallido ? 'Fallido' : 'Correcto')}</span>
        ${sospecha ? '<span class="v26-flag-sospecha"><i class="bi bi-exclamation-triangle-fill"></i> Revisar</span>' : ''}
      </td>
      <td class="v26-mono">${a.ip || '—'}</td>
      <td><i class="bi ${dispositivo.icono}"></i> ${dispositivo.label}</td>
    </tr>`;
}
function renderAccesos(lista) {
  const cont = document.getElementById('tabla-accesos-body');
  cont.innerHTML = lista.length
    ? lista.map(filaAcceso).join('')
    : '<tr><td colspan="5" class="v26-sin-resultados">Ningún acceso coincide con el filtro.</td></tr>';
}

async function cargarAccesos() {
  const params = new URLSearchParams({
    accion: 'accesos',
    vendedor_id: document.getElementById('filtro-accesos-vendedor').value,
    resultado: document.getElementById('filtro-accesos-resultado').value,
    dias: document.getElementById('filtro-accesos-dias').value,
    q: document.getElementById('buscar-accesos').value.trim(),
  });
  const cont = document.getElementById('tabla-accesos-body');
  cont.innerHTML = '<tr><td colspan="5" class="text-muted small">Cargando...</td></tr>';
  try {
    const res = await fetch('../api/admin_bitacora.php?' + params.toString());
    const data = await res.json();
    if (!data.ok) { cont.innerHTML = `<tr><td colspan="5" class="text-danger small">${data.error}</td></tr>`; return; }
    renderAccesos(data.accesos);
  } catch (e) {
    console.error('[Bitácora] cargarAccesos:', e);
    cont.innerHTML = '<tr><td colspan="5" class="text-danger small">No se pudo cargar (revisa la consola).</td></tr>';
  }
}

// ---------------------------------------------------------------------
// Eventos de filtros (buscador con debounce, selects al vuelo)
// ---------------------------------------------------------------------
['filtro-cambios-vendedor', 'filtro-cambios-accion', 'filtro-cambios-entidad', 'filtro-cambios-dias'].forEach(id =>
  document.getElementById(id).addEventListener('change', cargarCambios)
);
document.getElementById('buscar-cambios').addEventListener('input', () => {
  clearTimeout(timerBuscarCambios);
  timerBuscarCambios = setTimeout(cargarCambios, 300);
});
['filtro-accesos-vendedor', 'filtro-accesos-resultado', 'filtro-accesos-dias'].forEach(id =>
  document.getElementById(id).addEventListener('change', cargarAccesos)
);
let timerBuscarAccesos = null;
document.getElementById('buscar-accesos').addEventListener('input', () => {
  clearTimeout(timerBuscarAccesos);
  timerBuscarAccesos = setTimeout(cargarAccesos, 300);
});

// Las 4 cargas son independientes a propósito -- si una falla (por ejemplo
// el combo de vendedores) las otras tres deben renderizar igual, no
// quedarse en "Cargando..." por una promesa encadenada que nunca resuelve.
posicionarSlider(document.querySelector('#filtro-tab .opt.active'));
cargarVendedoresFiltro();
cargarResumen();
cargarCambios();
cargarAccesos();
