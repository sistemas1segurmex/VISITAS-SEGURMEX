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
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
      <h5 class="mb-0">Vendedores y administradores</h5>
      <p class="v26-subtitulo mb-0">Tu equipo de campo, en un vistazo</p>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-secondary btn-sm" onclick="abrirModalInvitar()"><i class="bi bi-link-45deg"></i> Invitar vendedor</button>
      <button class="btn btn-brand btn-sm v26-btn-glow" onclick="abrirModalCrear()"><i class="bi bi-plus-lg"></i> Nuevo vendedor</button>
    </div>
  </div>

  <div class="v26-stats-row mb-3" id="stats-usuarios">
    <div class="v26-stat-card">
      <div class="v26-stat-icon"><i class="bi bi-people-fill"></i></div>
      <div><div class="v26-stat-num" data-valor="0" id="stat-total">0</div><div class="v26-stat-label">Total</div></div>
    </div>
    <div class="v26-stat-card v26-stat-card--verde">
      <div class="v26-stat-icon"><i class="bi bi-lightning-charge-fill"></i></div>
      <div><div class="v26-stat-num" data-valor="0" id="stat-activos">0</div><div class="v26-stat-label">Activos</div></div>
    </div>
    <div class="v26-stat-card v26-stat-card--azul">
      <div class="v26-stat-icon"><i class="bi bi-signpost-split-fill"></i></div>
      <div><div class="v26-stat-num" data-valor="0" id="stat-vendedores">0</div><div class="v26-stat-label">Vendedores</div></div>
    </div>
    <div class="v26-stat-card v26-stat-card--morado">
      <div class="v26-stat-icon"><i class="bi bi-shield-lock-fill"></i></div>
      <div><div class="v26-stat-num" data-valor="0" id="stat-admins">0</div><div class="v26-stat-label">Administradores</div></div>
    </div>
    <div class="v26-stat-card">
      <div class="v26-stat-icon"><i class="bi bi-hourglass-split"></i></div>
      <div><div class="v26-stat-num" data-valor="0" id="stat-pendientes">0</div><div class="v26-stat-label">Pendientes de aprobar</div></div>
    </div>
  </div>

  <div class="v26-toolbar mb-3">
    <div class="v26-search-box">
      <i class="bi bi-search"></i>
      <input type="text" id="buscar-usuario" placeholder="Buscar por nombre, correo o región...">
    </div>
    <div class="v26-segmented" id="filtro-rol">
      <div class="v26-segmented-slider" id="filtro-slider"></div>
      <button type="button" class="opt active" data-rol="">Todos</button>
      <button type="button" class="opt" data-rol="vendedor">Vendedores</button>
      <button type="button" class="opt" data-rol="admin">Administradores</button>
      <button type="button" class="opt" data-rol="pendientes">Pendientes</button>
    </div>
  </div>

  <div class="v26-grid-usuarios" id="grid-usuarios">
    <p class="text-muted small">Cargando...</p>
  </div>
  <p class="text-muted small d-none" id="sin-resultados">Ningún usuario coincide con la búsqueda.</p>
</div>

<!-- Modal crear/editar -->
<div class="modal fade" id="modalUsuario" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="form-usuario" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title" id="modalUsuarioTitulo">Nuevo vendedor</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div id="msg-usuario"></div>
          <div class="mb-3 text-center">
            <div class="v26-foto-preview-wrap">
              <img id="foto-preview" src="" alt="Foto de perfil" class="v26-foto-preview d-none">
              <div id="foto-preview-vacia" class="v26-foto-preview v26-foto-preview--vacia"><i class="bi bi-person"></i></div>
            </div>
            <label class="btn btn-sm btn-outline-secondary mt-2">
              <i class="bi bi-camera"></i> Elegir foto
              <input type="file" name="foto" id="foto" accept="image/*" class="d-none">
            </label>
            <div class="form-text">Opcional, máximo 5 MB.</div>
          </div>
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

<!-- Modal invitar vendedor -->
<div class="modal fade" id="modalInvitar" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Invitar vendedor nuevo</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="msg-invitar"></div>
        <form id="form-invitar">
          <div class="mb-3">
            <label class="form-label">Correo del nuevo vendedor</label>
            <input type="email" id="invitar-email" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Vence en</label>
            <select id="invitar-dias" class="form-select">
              <option value="2" selected>2 días (recomendado)</option>
              <option value="1">1 día</option>
              <option value="7">7 días</option>
            </select>
          </div>
          <button type="submit" class="btn btn-brand w-100" id="btn-generar-link">Generar link</button>
        </form>

        <div id="resultado-invitacion" class="d-none mt-3">
          <label class="form-label">Link de registro</label>
          <div class="input-group mb-2">
            <input type="text" id="invitar-link" class="form-control form-control-sm" readonly>
            <button class="btn btn-outline-secondary btn-sm" type="button" onclick="copiarLinkInvitacion()"><i class="bi bi-clipboard"></i> Copiar</button>
          </div>
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="v26-pill v26-pill--pendiente" id="invitar-vence-txt"></span>
            <button class="btn btn-brand btn-sm" type="button" id="btn-enviar-correo" onclick="enviarInvitacionPorCorreo()">
              <i class="bi bi-envelope"></i> Enviar por correo
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/estados_mx.js<?= assetVer(__DIR__ . '/../assets/js/estados_mx.js') ?>"></script>
<script src="../assets/js/admin_usuarios.js<?= assetVer(__DIR__ . '/../assets/js/admin_usuarios.js') ?>"></script>
</body>
</html>
