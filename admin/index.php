<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
$u = requireRole('admin');
$hoy = date('Y-m-d');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Panel — Control de Visitas</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-dark" style="background:#111827">
  <div class="container-fluid">
    <span class="navbar-brand navbar-brand-custom">Control de Visitas — Panel</span>
    <div class="d-flex align-items-center gap-2">
      <a href="usuarios.php" class="btn btn-sm btn-outline-light">Vendedores</a>
      <span class="text-white small"><?= htmlspecialchars($u['nombre']) ?></span>
      <a href="../logout.php" class="btn btn-sm btn-outline-light">Salir</a>
    </div>
  </div>
</nav>

<div class="container-fluid py-4">
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card shadow-sm mb-3">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0">Ubicación en vivo de los vendedores</h6>
            <span id="resumen-vendedores" class="text-muted small"></span>
          </div>
          <div id="mapa"></div>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <h6 class="mb-0">Visitas</h6>
            <input type="date" id="filtro-fecha" class="form-control form-control-sm" style="width:160px" value="<?= $hoy ?>">
          </div>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead>
                <tr><th>Vendedor</th><th>Cliente</th><th>Hora</th><th>Estado</th><th>Verificación</th></tr>
              </thead>
              <tbody id="tabla-citas"><tr><td colspan="5" class="text-muted">Cargando...</td></tr></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card shadow-sm mb-3 d-none" id="panel-estado-wrap">
        <div class="card-body" id="panel-estado"></div>
      </div>

      <div class="card shadow-sm">
        <div class="card-body">
          <h6 class="mb-2">Alertas</h6>
          <div id="lista-alertas"><p class="text-muted small">Cargando...</p></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="../assets/js/estados_mx.js"></script>
<script src="../assets/js/admin.js"></script>
<script src="../assets/js/admin_mapa_estados.js"></script>
<script>
  initMapa();
  initCapaEstados();
  refrescarTodo();
  document.getElementById('filtro-fecha').addEventListener('change', cargarCitasHoy);
  setInterval(refrescarTodo, 20000); // refresco automático cada 20s
</script>
</body>
</html>
