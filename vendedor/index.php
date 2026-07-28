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
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/vendedor-2026.css">
</head>
<body class="v26">
  <div class="v26-header">
    <div class="v26-topbar">
      <div class="v26-topbar-left">
        <div class="v26-avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr($u['nombre'], 0, 1))) ?></div>
        <div class="v26-greeting">
          <div class="hi" id="saludo-hora">Hola</div>
          <div class="name"><?= htmlspecialchars($u['nombre']) ?></div>
        </div>
      </div>
      <div class="v26-topbar-right">
        <img src="../logo.png" alt="Segurmex" class="v26-logo">
        <a href="../logout.php" class="v26-icon-btn v26-tip v26-tip--bottom" data-tip="Cerrar sesión" aria-label="Salir"><i class="bi bi-box-arrow-right"></i></a>
      </div>
    </div>
    <div class="v26-tabbar">
      <a href="index.php" class="active"><i class="bi bi-house-fill"></i>Inicio</a>
      <a href="calendario.php"><i class="bi bi-calendar3"></i>Calendario</a>
      <a href="clientes.php"><i class="bi bi-people-fill"></i>Clientes</a>
      <a href="reporte.php"><i class="bi bi-bar-chart-fill"></i>Reporte</a>
    </div>
  </div>

  <div class="v26-wrap">
    <a href="nueva_cita.php" class="v26-cta">
      <span class="v26-cta-icon"><i class="bi bi-calendar-plus"></i></span>
      <span class="v26-cta-text">
        <strong>Nueva cita</strong>
        <small>Programa tu próxima visita</small>
      </span>
      <i class="bi bi-chevron-right chev"></i>
    </a>

    <div class="v26-seg" id="seg-dia">
      <button type="button" class="v26-seg-btn active" data-dia="hoy">Hoy</button>
      <button type="button" class="v26-seg-btn" data-dia="manana">Mañana</button>
    </div>

    <div id="resumen-pendientes"></div>

    <div id="lista-citas" class="v26-timeline"></div>

    <p class="text-muted small mt-3"><i class="bi bi-geo-alt"></i> Tu ubicación se comparte automáticamente mientras esta página esté abierta, para que la empresa pueda verificar tu recorrido.</p>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/v26-modal.js"></script>
<script src="../assets/js/vendedor.js"></script>
<script>
// Saludo según la hora local del dispositivo del vendedor (evita el bug de
// mostrar "buenas noches" a mediodía si el servidor tiene otra zona horaria).
(function () {
  const h = new Date().getHours();
  const saludo = h < 12 ? 'Buenos días' : (h < 19 ? 'Buenas tardes' : 'Buenas noches');
  document.getElementById('saludo-hora').textContent = saludo;
})();

document.querySelectorAll('#seg-dia .v26-seg-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('#seg-dia .v26-seg-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    cargarCitas(btn.dataset.dia);
  });
});

cargarCitas('hoy');
iniciarTrackingPeriodico();
</script>
</body>
</html>
