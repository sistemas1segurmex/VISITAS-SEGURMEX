// Gestión de usuarios/vendedores desde el panel del dueño.

let usuarioEditandoId = null;

document.getElementById('estado_operacion').innerHTML =
  '<option value="">Sin asignar</option>' + ESTADOS_MX.map(e => `<option value="${e}">${e}</option>`).join('');

function abrirModalCrear() {
  usuarioEditandoId = null;
  document.getElementById('modalUsuarioTitulo').textContent = 'Nuevo vendedor';
  document.getElementById('form-usuario').reset();
  document.getElementById('campo-password').classList.remove('d-none');
  document.getElementById('password').required = true;
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

async function cargarUsuarios() {
  const cuerpo = document.getElementById('tabla-usuarios');
  const res = await fetch('../api/usuarios.php');
  const data = await res.json();
  if (!data.ok) { cuerpo.innerHTML = `<tr><td colspan="6" class="text-danger">${data.error}</td></tr>`; return; }
  cuerpo.innerHTML = data.usuarios.map(u => `
    <tr>
      <td>${u.nombre}</td>
      <td>${u.email}</td>
      <td><span class="badge ${u.rol === 'admin' ? 'bg-dark' : 'bg-secondary'}">${u.rol}</span></td>
      <td>${u.estado_operacion || '—'}</td>
      <td>${u.activo == 1 ? '<span class="badge badge-verificado">Activo</span>' : '<span class="badge badge-noverificado">Inactivo</span>'}</td>
      <td class="text-end">
        ${u.rol === 'vendedor' ? `<a href="vendedor_detalle.php?id=${u.id}" class="btn btn-sm btn-brand">Ver detalle</a>` : ''}
        <button class="btn btn-sm btn-outline-secondary" onclick='abrirModalEditar(${JSON.stringify(u).replace(/'/g, "&#39;")})'>Editar</button>
        <button class="btn btn-sm btn-outline-secondary" onclick="resetearPassword(${u.id})">Contraseña</button>
        <button class="btn btn-sm ${u.activo == 1 ? 'btn-outline-danger' : 'btn-outline-success'}" onclick="cambiarEstado(${u.id}, ${u.activo == 1 ? 0 : 1})">
          ${u.activo == 1 ? 'Desactivar' : 'Activar'}
        </button>
      </td>
    </tr>
  `).join('');
}
cargarUsuarios();
