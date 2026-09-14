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
<title>Bitácora — Control de Visitas</title>
<link rel="icon" type="image/png" href="../assets/img/Favv-Segu.png">
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
        <div class="name">Bitácora</div>
      </div>
    </div>
    <div class="v26-topbar-right">
      <span class="v26-user"><?= htmlspecialchars($u['nombre']) ?></span>
      <a href="../logout.php" class="v26-icon-btn v26-tip v26-tip--bottom" data-tip="Cerrar sesión" aria-label="Salir"><i class="bi bi-box-arrow-right"></i></a>
    </div>
  </div>
</div>

<div class="v26-wrap">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
      <h5 class="mb-0">Historial de cambios y accesos</h5>
      <p class="v26-subtitulo mb-0">Todo lo que hacen tus vendedores, en un solo lugar</p>
    </div>
    <div class="v26-segmented" id="filtro-tab">
      <div class="v26-segmented-slider" id="tab-slider"></div>
      <button type="button" class="opt active" data-tab="cambios"><i class="bi bi-arrow-left-right"></i> Cambios</button>
      <button type="button" class="opt" data-tab="accesos"><i class="bi bi-shield-lock"></i> Accesos</button>
    </div>
  </div>

  <div class="v26-stats-row mb-3" id="stats-bitacora">
    <div class="v26-stat-card"><div class="v26-stat-icon"><i class="bi bi-arrow-left-right"></i></div><div><div class="v26-stat-num" id="stat-cambios-hoy" data-valor="0">0</div><div class="v26-stat-label">Cambios hoy</div></div></div>
    <div class="v26-stat-card v26-stat-card--verde"><div class="v26-stat-icon"><i class="bi bi-plus-lg"></i></div><div><div class="v26-stat-num" id="stat-altas-7d" data-valor="0">0</div><div class="v26-stat-label">Altas (7 días)</div></div></div>
    <div class="v26-stat-card v26-stat-card--cian"><div class="v26-stat-icon"><i class="bi bi-pencil-fill"></i></div><div><div class="v26-stat-num" id="stat-ediciones-7d" data-valor="0">0</div><div class="v26-stat-label">Ediciones (7 días)</div></div></div>
    <div class="v26-stat-card v26-stat-card--morado"><div class="v26-stat-icon"><i class="bi bi-x-lg"></i></div><div><div class="v26-stat-num" id="stat-bajas-mes" data-valor="0">0</div><div class="v26-stat-label">Bajas/perdidos (mes)</div></div></div>
  </div>

  <!-- ============ Pestaña Cambios ============ -->
  <div id="panel-cambios">
    <div class="v26-toolbar mb-3">
      <div class="v26-search-box">
        <i class="bi bi-search"></i>
        <input type="text" id="buscar-cambios" placeholder="Buscar por vendedor o cliente...">
      </div>
      <select class="v26-select" id="filtro-cambios-vendedor"><option value="0">Todos los vendedores</option></select>
      <select class="v26-select" id="filtro-cambios-accion">
        <option value="">Todas las acciones</option>
        <option value="alta">Altas</option>
        <option value="edicion">Ediciones</option>
        <option value="baja">Bajas / perdidos</option>
      </select>
      <select class="v26-select" id="filtro-cambios-entidad">
        <option value="">Todas las entidades</option>
        <option value="cliente">Clientes</option>
        <option value="cita">Citas</option>
        <option value="cotizacion">Cotizaciones</option>
        <option value="muestra">Muestras</option>
      </select>
      <select class="v26-select" id="filtro-cambios-dias">
        <option value="7">Últimos 7 días</option>
        <option value="1">Hoy</option>
        <option value="30">Últimos 30 días</option>
        <option value="0">Todo</option>
      </select>
    </div>
    <div id="lista-cambios"><p class="text-muted small">Cargando...</p></div>
  </div>

  <!-- ============ Pestaña Accesos ============ -->
  <div id="panel-accesos" class="d-none">
    <div class="v26-toolbar mb-3">
      <div class="v26-search-box">
        <i class="bi bi-search"></i>
        <input type="text" id="buscar-accesos" placeholder="Buscar por vendedor o correo...">
      </div>
      <select class="v26-select" id="filtro-accesos-vendedor"><option value="0">Todos los vendedores</option></select>
      <select class="v26-select" id="filtro-accesos-resultado">
        <option value="">Todos los resultados</option>
        <option value="correcto">Correctos</option>
        <option value="fallido">Fallidos</option>
      </select>
      <select class="v26-select" id="filtro-accesos-dias">
        <option value="7">Últimos 7 días</option>
        <option value="1">Hoy</option>
        <option value="30">Últimos 30 días</option>
        <option value="0">Todo</option>
      </select>
    </div>
    <div class="v26-tabla-wrap">
      <table class="v26-tabla-accesos">
        <thead>
          <tr><th>Vendedor</th><th>Fecha y hora</th><th>Resultado</th><th>IP</th><th>Dispositivo</th></tr>
        </thead>
        <tbody id="tabla-accesos-body"><tr><td colspan="5" class="text-muted small">Cargando...</td></tr></tbody>
      </table>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/admin_bitacora.js<?= assetVer(__DIR__ . '/../assets/js/admin_bitacora.js') ?>"></script>
</body>
</html>
