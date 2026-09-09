// Gestión de usuarios/vendedores desde el panel del dueño.

let usuarioEditandoId = null;

document.getElementById('estado_operacion').innerHTML =
  '<option value="">Sin asignar</option>' + ESTADOS_MX.map(e => `<option value="${e}">${e}</option>`).join('');

function mostrarPreviewFoto(url) {
  const img = document.getElementById('foto-preview');
  const vacia = document.getElementById('foto-preview-vacia');
  if (url) {
    img.src = url;
    img.classList.remove('d-none');
    vacia.classList.add('d-none');
  } else {
    img.src = '';
    img.classList.add('d-none');
    vacia.classList.remove('d-none');
  }
}

document.getElementById('foto').addEventListener('change', (e) => {
  const archivo = e.target.files[0];
  if (!archivo) return;
  const lector = new FileReader();
  lector.onload = (ev) => mostrarPreviewFoto(ev.target.result);
  lector.readAsDataURL(archivo);
});

function abrirModalCrear() {
  usuarioEditandoId = null;
  document.getElementById('modalUsuarioTitulo').textContent = 'Nuevo vendedor';
  document.getElementById('form-usuario').reset();
  document.getElementById('campo-password').classList.remove('d-none');
  document.getElementById('password').required = true;
  mostrarPreviewFoto(null);
  new bootstrap.Modal(document.getElementById('modalUsuario')).show();
}

function abrirModalEditar(u) {
  usuarioEditandoId = u.id;
  document.getElementById('modalUsuarioTitulo').textContent = 'Editar usuario';
  document.getElementById('nombre').value = u.nombre;
  document.getElementById('email').value = u.email;
  document.getElementById('email').readOnly = true;
  document.getElementById('rol').value = u.rol;
  document.getElementById('telefono').value = u.telefono || '';
  document.getElementById('estado_operacion').value = u.estado_operacion || '';
  document.getElementById('campo-password').classList.add('d-none');
  document.getElementById('password').required = false;
  document.getElementById('foto').value = '';
  mostrarPreviewFoto(u.foto_path ? '../' + u.foto_path : null);
  new bootstrap.Modal(document.getElementById('modalUsuario')).show();
}

document.getElementById('form-usuario').addEventListener('submit', async (e) => {
  e.preventDefault();
  const msg = document.getElementById('msg-usuario');
  msg.innerHTML = '';
  const fd = new FormData(e.target);
  fd.append('accion', usuarioEditandoId ? 'actualizar' : 'crear');
  if (usuarioEditandoId) fd.append('id', usuarioEditandoId);

  const res = await fetch('../api/usuarios.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.ok) {
    bootstrap.Modal.getInstance(document.getElementById('modalUsuario')).hide();
    document.getElementById('email').readOnly = false;
    cargarUsuarios();
  } else {
    msg.innerHTML = `<div class="alert alert-danger py-2">${data.error}</div>`;
  }
});

async function cambiarEstado(id, activo) {
  const fd = new FormData();
  fd.append('accion', 'cambiar_estado');
  fd.append('id', id);
  fd.append('activo', activo ? 1 : 0);
  const res = await fetch('../api/usuarios.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.ok) {
    cargarUsuarios();
  } else {
    alert(data.error);
  }
}

async function resetearPassword(id) {
  const nueva = prompt('Escribe la nueva contraseña (mínimo 6 caracteres):');
  if (!nueva) return;
  const fd = new FormData();
  fd.append('accion', 'resetear_password');
  fd.append('id', id);
  fd.append('password', nueva);
  const res = await fetch('../api/usuarios.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.ok) {
    alert('Contraseña actualizada.');
  } else {
    alert(data.error);
  }
}

// ---------------------------------------------------------------------
// Invitar vendedor nuevo por link (registro_vendedor.php) -- ver
// api/invitaciones.php. El link se arma en el navegador a partir de la URL
// actual (no en el servidor), así funciona igual en localhost, en el
// servidor de la oficina o detrás de cualquier proxy/alias.
// ---------------------------------------------------------------------
let invitacionActual = null; // { id, link }

function abrirModalInvitar() {
  invitacionActual = null;
  document.getElementById('form-invitar').reset();
  document.getElementById('form-invitar').classList.remove('d-none');
  document.getElementById('resultado-invitacion').classList.add('d-none');
  document.getElementById('msg-invitar').innerHTML = '';
  new bootstrap.Modal(document.getElementById('modalInvitar')).show();
}

document.getElementById('form-invitar').addEventListener('submit', async (e) => {
  e.preventDefault();
  const msg = document.getElementById('msg-invitar');
  const btn = document.getElementById('btn-generar-link');
  msg.innerHTML = '';
  btn.disabled = true;
  btn.textContent = 'Generando...';
  try {
    const fd = new FormData();
    fd.append('accion', 'crear');
    fd.append('email', document.getElementById('invitar-email').value.trim());
    fd.append('dias', document.getElementById('invitar-dias').value);
    const res = await fetch('../api/invitaciones.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.ok) {
      msg.innerHTML = `<div class="alert alert-danger py-2">${data.error}</div>`;
      return;
    }
    const base = location.origin + location.pathname.replace(/admin\/usuarios\.php.*/, '');
    const link = base + 'registro_vendedor.php?token=' + data.token;
    invitacionActual = { id: data.id, link };

    document.getElementById('invitar-link').value = link;
    const fecha = new Date(String(data.expira_en).replace(' ', 'T'));
    document.getElementById('invitar-vence-txt').textContent = 'Vence el ' +
      fecha.toLocaleDateString('es-MX', { day: 'numeric', month: 'short' }) + ', ' +
      fecha.toLocaleTimeString('es-MX', { hour: 'numeric', minute: '2-digit' });

    // El formulario se queda visible junto con el resultado (no se oculta)
    // -- así se puede cambiar el correo/vigencia y generar otro sin cerrar
    // el modal, y coincide con el maquetado que se aprobó (todo junto).
    document.getElementById('resultado-invitacion').classList.remove('d-none');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Generar link';
  }
});

function copiarLinkInvitacion() {
  const input = document.getElementById('invitar-link');
  input.select();
  navigator.clipboard?.writeText(input.value).catch(() => document.execCommand('copy'));
}

async function enviarInvitacionPorCorreo() {
  if (!invitacionActual) return;
  const btn = document.getElementById('btn-enviar-correo');
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Enviando...';
  const fd = new FormData();
  fd.append('accion', 'enviar');
  fd.append('id', invitacionActual.id);
  fd.append('link', invitacionActual.link);
  const res = await fetch('../api/invitaciones.php', { method: 'POST', body: fd });
  const data = await res.json();
  btn.disabled = false;
  btn.innerHTML = '<i class="bi bi-envelope"></i> Enviar por correo';
  if (!data.ok) {
    alert(data.error || 'No se pudo enviar el correo. Copia el link y mándalo a mano.');
  } else {
    alert('Correo enviado.');
  }
}

// ---------------------------------------------------------------------
// Aprobar / rechazar registros de vendedores que llegaron solos por link
// ---------------------------------------------------------------------
async function aprobarRegistro(id) {
  const fd = new FormData();
  fd.append('accion', 'cambiar_estado');
  fd.append('id', id);
  fd.append('activo', 1);
  const res = await fetch('../api/usuarios.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.ok) cargarUsuarios(); else alert(data.error);
}

async function rechazarRegistro(id) {
  if (!confirm('¿Rechazar este registro? No se podrá recuperar.')) return;
  const fd = new FormData();
  fd.append('accion', 'rechazar');
  fd.append('id', id);
  const res = await fetch('../api/usuarios.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.ok) cargarUsuarios(); else alert(data.error);
}

function iniciales(nombre) {
  const partes = String(nombre).trim().split(/\s+/).filter(Boolean);
  const letras = partes.length > 1 ? partes[0][0] + partes[1][0] : (partes[0] || '?').slice(0, 2);
  return letras.toUpperCase();
}

// Color de avatar determinístico por id (solo cuando no hay foto), tomado
// de una paleta fija, así cada persona siempre tiene el mismo color.
const PALETA_AVATAR = ['#4F46E5', '#F5A623', '#16A34A', '#E11D48', '#0EA5E9', '#9333EA', '#D97706', '#0891B2'];
function colorAvatar(id) { return PALETA_AVATAR[id % PALETA_AVATAR.length]; }

function animarNumero(el, valorFinal) {
  const inicio = parseInt(el.dataset.valor || '0', 10);
  el.dataset.valor = valorFinal;
  if (inicio === valorFinal) { el.textContent = valorFinal; return; }
  const duracion = 600, t0 = performance.now();
  function paso(t) {
    const p = Math.min((t - t0) / duracion, 1);
    const suavizado = 1 - Math.pow(1 - p, 3); // ease-out cubic
    el.textContent = Math.round(inicio + (valorFinal - inicio) * suavizado);
    if (p < 1) requestAnimationFrame(paso);
  }
  requestAnimationFrame(paso);
}

let usuariosCache = [];
let filtroRolActivo = '';

function tarjetaUsuario(u, i) {
  const activo = u.activo == 1;
  const pendiente = u.es_autoregistro && !activo;
  const avatarContenido = u.foto_path ? `<img src="../${u.foto_path}" alt="">` : iniciales(u.nombre);
  const estiloAvatar = u.foto_path ? '' : `background:${colorAvatar(u.id)};color:#fff`;
  return `
    <div class="v26-user-card ${u.rol === 'admin' ? 'rol-admin' : ''} ${activo ? '' : 'inactivo'}" style="animation-delay:${(i * 0.04).toFixed(2)}s">
      <div class="v26-user-top">
        <div class="v26-avatar-ring">
          <div class="inner" style="${estiloAvatar}">${avatarContenido}</div>
          <span class="v26-status-dot ${activo ? 'pulso' : 'off'}"></span>
        </div>
        <div class="flex-grow-1" style="min-width:0">
          <div class="v26-user-nombre">${u.nombre}</div>
          <div class="v26-user-correo">${u.email}</div>
        </div>
      </div>
      <div class="v26-user-meta">
        <span class="v26-pill v26-pill--${u.rol}">${u.rol}</span>
        ${u.telefono ? `<span class="v26-user-region"><i class="bi bi-telephone"></i> ${u.telefono}</span>` : ''}
        ${u.estado_operacion ? `<span class="v26-user-region"><i class="bi bi-geo-alt"></i> ${u.estado_operacion}</span>` : ''}
        ${pendiente ? '<span class="v26-pill v26-pill--pendiente"><i class="bi bi-hourglass-split"></i> Pendiente de aprobar</span>' : ''}
      </div>
      <div class="v26-user-acciones">
        ${pendiente ? `
          <button class="v26-icon-btn exito" title="Aprobar" onclick="aprobarRegistro(${u.id})"><i class="bi bi-check-circle"></i> Aprobar</button>
          <button class="v26-icon-btn peligro" title="Rechazar" onclick="rechazarRegistro(${u.id})"><i class="bi bi-x-circle"></i> Rechazar</button>
        ` : `
          ${u.rol === 'vendedor' ? `<a href="vendedor_detalle.php?id=${u.id}" class="v26-user-ver-detalle"><i class="bi bi-eye"></i> Ver detalle</a>` : ''}
          <button class="v26-icon-btn" title="Editar" onclick='abrirModalEditar(${JSON.stringify(u).replace(/'/g, "&#39;")})'><i class="bi bi-pencil"></i></button>
          <button class="v26-icon-btn" title="Restablecer contraseña" onclick="resetearPassword(${u.id})"><i class="bi bi-key"></i></button>
          <button class="v26-icon-btn ${activo ? 'peligro' : 'exito'}" title="${activo ? 'Desactivar' : 'Activar'}" onclick="cambiarEstado(${u.id}, ${activo ? 0 : 1})">
            <i class="bi ${activo ? 'bi-slash-circle' : 'bi-check-circle'}"></i>
          </button>
        `}
      </div>
    </div>
  `;
}

function actualizarStats() {
  animarNumero(document.getElementById('stat-total'), usuariosCache.length);
  animarNumero(document.getElementById('stat-activos'), usuariosCache.filter(u => u.activo == 1).length);
  animarNumero(document.getElementById('stat-vendedores'), usuariosCache.filter(u => u.rol === 'vendedor').length);
  animarNumero(document.getElementById('stat-admins'), usuariosCache.filter(u => u.rol === 'admin').length);
  animarNumero(document.getElementById('stat-pendientes'), usuariosCache.filter(u => u.es_autoregistro && u.activo != 1).length);
}

function renderizarUsuarios() {
  const grid = document.getElementById('grid-usuarios');
  const sinResultados = document.getElementById('sin-resultados');
  const texto = (document.getElementById('buscar-usuario').value || '').trim().toLowerCase();

  const filtrados = usuariosCache.filter(u => {
    if (filtroRolActivo === 'pendientes') {
      if (!(u.es_autoregistro && u.activo != 1)) return false;
    } else if (filtroRolActivo && u.rol !== filtroRolActivo) {
      return false;
    }
    if (!texto) return true;
    return u.nombre.toLowerCase().includes(texto)
        || u.email.toLowerCase().includes(texto)
        || (u.estado_operacion || '').toLowerCase().includes(texto);
  });

  if (filtrados.length === 0) {
    grid.innerHTML = '';
    sinResultados.classList.remove('d-none');
    return;
  }
  sinResultados.classList.add('d-none');
  grid.innerHTML = filtrados.map(tarjetaUsuario).join('');
}

function posicionarSlider(btn) {
  const slider = document.getElementById('filtro-slider');
  if (!slider || !btn) return;
  slider.style.left = btn.offsetLeft + 'px';
  slider.style.width = btn.offsetWidth + 'px';
}

document.getElementById('buscar-usuario').addEventListener('input', renderizarUsuarios);
document.querySelectorAll('#filtro-rol .opt').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('#filtro-rol .opt').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    posicionarSlider(btn);
    filtroRolActivo = btn.dataset.rol;
    renderizarUsuarios();
  });
});
window.addEventListener('resize', () => posicionarSlider(document.querySelector('#filtro-rol .opt.active')));

async function cargarUsuarios() {
  const grid = document.getElementById('grid-usuarios');
  const res = await fetch('../api/usuarios.php');
  const data = await res.json();
  if (!data.ok) { grid.innerHTML = `<p class="text-danger small">${data.error}</p>`; return; }
  usuariosCache = data.usuarios;
  actualizarStats();
  renderizarUsuarios();
  posicionarSlider(document.querySelector('#filtro-rol .opt.active'));
}
cargarUsuarios();
