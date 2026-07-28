<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
$u = requireRole('vendedor');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mis clientes</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-dark" style="background:#111827">
  <div class="container-fluid">
    <a href="index.php" class="navbar-brand navbar-brand-custom text-decoration-none">← Control de Visitas</a>
  </div>
</nav>
<div class="container py-4" style="max-width:720px">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Mis clientes</h5>
    <a href="nuevo_cliente.php" class="btn btn-sm btn-brand">+ Nuevo cliente</a>
  </div>
  <div class="mb-3">
    <input type="text" id="buscar-cliente" class="form-control" placeholder="Buscar por nombre o dirección...">
  </div>
  <div id="lista-clientes"><p class="text-muted">Cargando...</p></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let todosLosClientes = [];

function renderClientes(lista) {
  const cont = document.getElementById('lista-clientes');
  if (lista.length === 0) {
    cont.innerHTML = '<p class="text-muted">No se encontraron clientes.</p>';
    return;
  }
  cont.innerHTML = lista.map(c => `
    <div class="card mb-2">
      <div class="card-body py-2 d-flex justify-content-between align-items-center">
        <div>
          <strong>${c.nombre}</strong><br>
          <span class="text-muted small">${c.direccion}</span>
          ${c.telefono ? `<br><span class="text-muted small">📞 ${c.telefono}</span>` : ''}
        </div>
        ${c.lat ? '<span class="badge badge-verificado">GPS ok</span>' : '<span class="badge bg-warning text-dark">Sin ubicación GPS</span>'}
      </div>
    </div>
  `).join('');
}

async function cargarClientes() {
  const res = await fetch('../api/clientes.php');
  const data = await res.json();
  if (!data.ok) {
    document.getElementById('lista-clientes').innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
    return;
  }
  if (data.clientes.length === 0) {
    document.getElementById('lista-clientes').innerHTML = '<p class="text-muted">Aún no tienes clientes registrados. Usa "+ Nuevo cliente" para agregar el primero.</p>';
    return;
  }
  todosLosClientes = data.clientes;
  renderClientes(todosLosClientes);
}

document.getElementById('buscar-cliente').addEventListener('input', (e) => {
  const q = e.target.value.toLowerCase();
  renderClientes(todosLosClientes.filter(c =>
    c.nombre.toLowerCase().includes(q) || c.direccion.toLowerCase().includes(q)
  ));
});

cargarClientes();
</script>
</body>
</html>
