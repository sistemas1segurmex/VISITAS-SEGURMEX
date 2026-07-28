<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
$u = requireRole('admin');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Vendedores — Control de Visitas</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-dark" style="background:#111827">
  <div class="container-fluid">
    <a href="index.php" class="navbar-brand navbar-brand-custom text-decoration-none">← Control de Visitas</a>
    <div class="d-flex align-items-center gap-2">
      <span class="text-white small"><?= htmlspecialchars($u['nombre']) ?></span>
      <a href="../logout.php" class="btn btn-sm btn-outline-light">Salir</a>
    </div>
  </div>
</nav>

<div class="container py-4">
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
<script src="../assets/js/estados_mx.js"></script>
<script src="../assets/js/admin_usuarios.js"></script>
</body>
</html>
