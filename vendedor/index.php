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
<title>Mis visitas</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-dark" style="background:#111827">
  <div class="container-fluid">
    <span class="navbar-brand navbar-brand-custom">Control de Visitas</span>
    <div class="d-flex align-items-center gap-2">
      <span class="text-white small"><?= htmlspecialchars($u['nombre']) ?></span>
      <a href="../logout.php" class="btn btn-sm btn-outline-light">Salir</a>
    </div>
  </div>
</nav>
<div class="container py-4" style="max-width:720px">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Mis visitas de hoy</h5>
    <div class="d-flex gap-2">
      <a href="calendario.php" class="btn btn-sm btn-outline-secondary">📅 Calendario</a>
      <a href="clientes.php" class="btn btn-sm btn-outline-secondary">Clientes</a>
      <a href="nueva_cita.php" class="btn btn-sm btn-brand">+ Nueva cita</a>
    </div>
  </div>
  <div id="lista-citas" class="d-flex flex-column gap-2"></div>
  <p class="text-muted small mt-4">📍 Tu ubicación se comparte automáticamente mientras esta página esté abierta, para que la empresa pueda verificar tu recorrido.</p>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/vendedor.js"></script>
<script>
  cargarCitas();
  iniciarTrackingPeriodico();
</script>
</body>
</html>
