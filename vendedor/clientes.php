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
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/vendedor-2026.css">
</head>
<body class="v26">
  <div class="v26-header">
    <div class="v26-topbar">
      <div class="v26-topbar-left">
        <a href="index.php" class="v26-back v26-tip v26-tip--bottom" data-tip="Volver a mis visitas" aria-label="Volver"><i class="bi bi-arrow-left"></i></a>
        <div class="v26-greeting">
          <div class="hi">Cartera</div>
          <div class="name">Mis clientes</div>
        </div>
      </div>
      <div class="v26-topbar-right">
        <img src="../logo.png" alt="Segurmex" class="v26-logo">
      </div>
    </div>
    <div class="v26-tabbar">
      <a href="index.php"><i class="bi bi-house-fill"></i>Inicio</a>
      <a href="calendario.php"><i class="bi bi-calendar3"></i>Calendario</a>
      <a href="clientes.php" class="active"><i class="bi bi-people-fill"></i>Clientes</a>
      <a href="reporte.php"><i class="bi bi-bar-chart-fill"></i>Reporte</a>
    </div>
  </div>

  <div class="v26-wrap">
    <a href="nuevo_cliente.php" class="v26-cta">
      <span class="v26-cta-icon"><i class="bi bi-person-plus"></i></span>
      <span class="v26-cta-text">
        <strong>Nuevo cliente</strong>
        <small>Agrega un cliente a tu cartera</small>
      </span>
      <i class="bi bi-chevron-right chev"></i>
    </a>

    <div class="v26-search">
      <i class="bi bi-search"></i>
      <input type="text" id="buscar-cliente" class="v26-input" placeholder="Buscar por nombre o dirección...">
    </div>
    <div id="lista-clientes">
      <div class="v26-skel"></div>
      <div class="v26-skel"></div>
      <div class="v26-skel"></div>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let todosLosClientes = [];

function renderClientes(lista) {
  const cont = document.getElementById('lista-clientes');
  if (lista.length === 0) {
    cont.innerHTML = `
      <div class="v26-empty">
        <div class="icon"><i class="bi bi-search"></i></div>
        <p>No se encontraron clientes.</p>
      </div>`;
    return;
  }
  cont.innerHTML = lista.map(c => `
    <div class="v26-cita">
      <div class="info">
        <div class="cliente">${c.nombre}</div>
        <div class="direccion">${c.direccion}</div>
        ${c.telefono ? `<div class="hora"><i class="bi bi-telephone"></i> ${c.telefono}</div>` : ''}
        <div class="badges">
          ${c.lat ? '<span class="v26-pill v26-pill--verificado">GPS ok</span>' : '<span class="v26-pill v26-pill--pendiente">Sin ubicación</span>'}
        </div>
      </div>
      <a href="historial_cliente.php?id=${c.id}" class="accion v26-tip secundaria" data-tip="Ver historial de visitas" aria-label="Ver historial"><i class="bi bi-clock-history"></i></a>
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
    document.getElementById('lista-clientes').innerHTML = `
      <div class="v26-empty">
        <div class="icon"><i class="bi bi-person-plus"></i></div>
        <p>Aún no tienes clientes registrados.<br>Usa "Nuevo cliente" arriba para agregar el primero.</p>
      </div>`;
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
