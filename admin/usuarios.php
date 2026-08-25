<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$u = requireRole('admin');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Vendedores — Control de Visitas</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css<?= assetVer(__DIR__ . '/../assets/css/style.css') ?>">
<link rel="stylesheet" href="../assets/css/admin-2026.css<?= assetVer(__DIR__ . '/../assets/css/admin-2026.css') ?>">
</head>
<body class="v26">
<div class="v26-header">
  <div class="v26-topbar">
    <div class="v26-topbar-left">
      <a href="index.php" class="v26-back" aria-label="Volver"><i class="bi bi-arrow-left"></i></a>
      <div class="v26-greeting">
        <div class="hi">Control de Visitas</div>
        <div class="name">Vendedores</div>
      </div>
    </div>
    <div class="v26-topbar-right">
      <span class="v26-user"><?= htmlspecialchars($u['nombre']) ?></span>
    </div>
  </div>
</div>

<div class="v26-wrap">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Vendedores y administradores</h5>
    <button class="btn btn-brand btn-sm" onclick="abrirModalCrear()">+ Nuevo vendedor</button>
  </div>
  <div class="card shadow-sm">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estado/Región</th><th>Estatus</th><th class="text-end">Acciones</th></tr>
          </thead>
          <tbody id="tabla-usuarios"><tr><td colspan="6" class="text-muted">Cargando...</td></tr></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modal crear/editar -->
<div class="modal fade" id="modalUsuario" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="form-usuario">
        <div class="modal-header">
          <h5 class="modal-title" id="modalUsuarioTitulo">Nuevo vendedor</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div id="msg-usuario"></div>
          <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input type="text" name="nombre" id="nombre" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Correo</label>
            <input type="email" name="email" id="email" class="form-control" required>
          </div>
          <div class="mb-3" id="campo-password">
            <label class="form-label">Contraseña</label>
            <input type="password" name="password" id="password" class="form-control" minlength="6">
          </div>
          <div class="mb-3">
            <label class="form-label">Rol</label>
            <select name="rol" id="rol" class="form-select">
              <option value="vendedor">Vendedor</option>
              <option value="admin">Administrador</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Teléfono (opcional)</label>
            <input type="text" name="telefono" id="telefono" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label">Estado/Región donde opera (opcional)</label>
            <select name="estado_operacion" id="estado_operacion" class="form-select">
              <option value="">Sin asignar</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-brand">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/estados_mx.js<?= assetVer(__DIR__ . '/../assets/js/estados_mx.js') ?>"></script>
<script src="../assets/js/admin_usuarios.js<?= assetVer(__DIR__ . '/../assets/js/admin_usuarios.js') ?>"></script>
</body>
</html>
