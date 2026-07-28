<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
$u = requireRole('admin');

$vendedorId = (int)($_GET['id'] ?? 0);
if (!$vendedorId) {
    header('Location: usuarios.php');
    exit;
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Detalle de vendedor — Control de Visitas</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-dark" style="background:#111827">
  <div class="container-fluid">
    <a href="usuarios.php" class="navbar-brand navbar-brand-custom text-decoration-none">← Vendedores</a>
    <div class="d-flex align-items-center gap-2">
      <span class="text-white small"><?= htmlspecialchars($u['nombre']) ?></span>
      <a href="../logout.php" class="btn btn-sm btn-outline-light">Salir</a>
    </div>
  </div>
</nav>

<div class="container py-4">
  <div id="encabezado-vendedor" class="mb-3"><p class="text-muted">Cargando...</p></div>

  <ul class="nav nav-tabs" id="tabsVendedor">
    <li class="nav-item">
      <button class="nav-link active" id="tab-proximas" data-bs-toggle="tab" data-bs-target="#panel-proximas" type="button">Próximas citas</button>
    </li>
    <li class="nav-item">
      <button class="nav-link" id="tab-todas" data-bs-toggle="tab" data-bs-target="#panel-todas" type="button">Todas las citas</button>
    </li>
    <li class="nav-item">
      <button class="nav-link" id="tab-clientes" data-bs-toggle="tab" data-bs-target="#panel-clientes" type="button">Clientes registrados</button>
    </li>
  </ul>

  <div class="tab-content border border-top-0 rounded-bottom p-3 bg-white">
    <div class="tab-pane fade show active" id="panel-proximas">
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead><tr><th>Fecha</th><th>Cliente</th><th>Estado</th><th>Verificación</th><th>Notas</th></tr></thead>
          <tbody id="tabla-proximas"><tr><td colspan="5" class="text-muted">Cargando...</td></tr></tbody>
        </table>
      </div>
    </div>
    <div class="tab-pane fade" id="panel-todas">
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead><tr><th>Fecha</th><th>Cliente</th><th>Estado</th><th>Verificación</th><th>Notas</th></tr></thead>
          <tbody id="tabla-todas"><tr><td colspan="5" class="text-muted">Cargando...</td></tr></tbody>
        </table>
      </div>
    </div>
    <div class="tab-pane fade" id="panel-clientes">
      <div id="lista-clientes-vendedor"><p class="text-muted">Cargando...</p></div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/admin_vendedor_detalle.js"></script>
</body>
</html>
