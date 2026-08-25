<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
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
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css<?= assetVer(__DIR__ . '/../assets/css/style.css') ?>">
<link rel="stylesheet" href="../assets/css/admin-2026.css<?= assetVer(__DIR__ . '/../assets/css/admin-2026.css') ?>">
</head>
<body class="v26">
<div class="v26-header">
  <div class="v26-topbar">
    <div class="v26-topbar-left">
      <a href="usuarios.php" class="v26-back" aria-label="Volver"><i class="bi bi-arrow-left"></i></a>
      <div class="v26-greeting">
        <div class="hi">Control de Visitas</div>
        <div class="name">Detalle de vendedor</div>
      </div>
    </div>
    <div class="v26-topbar-right">
      <span class="v26-user"><?= htmlspecialchars($u['nombre']) ?></span>
    </div>
  </div>
</div>

<div class="v26-wrap">
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
    <li class="nav-item">
      <button class="nav-link" id="tab-prospeccion" data-bs-toggle="tab" data-bs-target="#panel-prospeccion" type="button">Prospección</button>
    </li>
  </ul>

  <div class="tab-content border border-top-0 rounded-bottom p-3 bg-white">
    <div class="tab-pane fade show active" id="panel-proximas">
      <div class="v26-citas-list" id="tabla-proximas"><p class="text-muted small px-2">Cargando...</p></div>
    </div>
    <div class="tab-pane fade" id="panel-todas">
      <div class="v26-citas-list" id="tabla-todas"><p class="text-muted small px-2">Cargando...</p></div>
    </div>
    <div class="tab-pane fade" id="panel-clientes">
      <div id="lista-clientes-vendedor"><p class="text-muted">Cargando...</p></div>
    </div>
    <div class="tab-pane fade" id="panel-prospeccion">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <p class="text-muted small mb-0">Días con cita, con jornada de prospección marcada, con cliente nuevo registrado o sin ninguna señal de actividad.</p>
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <div class="btn-group btn-group-sm" role="group" id="vista-prospeccion-tabs">
            <button type="button" class="btn btn-outline-secondary active" data-vista="mes">Mes</button>
            <button type="button" class="btn btn-outline-secondary" data-vista="semana">Semana</button>
            <button type="button" class="btn btn-outline-secondary" data-vista="dia">Día</button>
          </div>
          <input type="month" id="mes-prospeccion" class="form-control form-control-sm" style="width:150px">
          <input type="week" id="semana-prospeccion" class="form-control form-control-sm d-none" style="width:150px">
          <input type="date" id="dia-prospeccion" class="form-control form-control-sm d-none" style="width:150px">
        </div>
      </div>
      <div id="contenido-prospeccion"><p class="text-muted small">Cargando...</p></div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalFoto" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered v26-foto-modal-dialog">
    <div class="modal-content v26-foto-modal-content">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title small text-white-50" id="foto-modal-titulo"></h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body text-center pt-2">
        <img id="foto-modal-img" src="" alt="Foto de evidencia" class="v26-foto-modal-img">
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/admin_vendedor_detalle.js<?= assetVer(__DIR__ . '/../assets/js/admin_vendedor_detalle.js') ?>"></script>
</body>
</html>
