<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
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
<link rel="stylesheet" href="../assets/css/style.css<?= assetVer(__DIR__ . '/../assets/css/style.css') ?>">
<link rel="stylesheet" href="../assets/css/vendedor-2026.css<?= assetVer(__DIR__ . '/../assets/css/vendedor-2026.css') ?>">
</head>
<body class="v26">
  <div class="v26-header">
    <div class="v26-topbar">
      <div class="v26-topbar-left">
        <div class="v26-avatar-ring v26-avatar-ring--header" data-tour="perfil">
          <div class="inner"><?= htmlspecialchars(mb_strtoupper(mb_substr($u['nombre'], 0, 1))) ?></div>
          <span class="v26-status-dot pulso" id="dot-ubicacion" title="Compartiendo ubicación"></span>
        </div>
        <div class="v26-greeting">
          <div class="hi" id="saludo-hora">Hola</div>
          <div class="name"><?= htmlspecialchars($u['nombre']) ?></div>
        </div>
      </div>
      <div class="v26-topbar-right">
        <img src="../logo.png" alt="Segurmex" class="v26-logo">
        <button type="button" class="v26-icon-btn v26-tip v26-tip--bottom" data-tip="Ver el recorrido de nuevo" aria-label="Ayuda" id="btn-tour-ayuda"><i class="bi bi-question-lg"></i></button>
        <a href="../logout.php" class="v26-icon-btn v26-tip v26-tip--bottom" data-tip="Cerrar sesión" aria-label="Salir"><i class="bi bi-box-arrow-right"></i></a>
      </div>
    </div>
    <div class="v26-tabbar" data-tour="tabbar">
      <a href="index.php" class="active"><i class="bi bi-house-fill"></i>Inicio</a>
      <a href="calendario.php"><i class="bi bi-calendar3"></i>Calendario</a>
      <a href="clientes.php"><i class="bi bi-people-fill"></i>Clientes</a>
      <a href="cotizaciones.php"><i class="bi bi-file-earmark-text-fill"></i>Cotizar</a>
      <a href="reporte.php"><i class="bi bi-bar-chart-fill"></i>Reporte</a>
    </div>
  </div>

  <div class="v26-wrap">
    <a href="nueva_cita.php" class="v26-cta" data-tour="cta">
      <span class="v26-cta-icon"><i class="bi bi-calendar-plus"></i></span>
      <span class="v26-cta-text">
        <strong>Nueva cita</strong>
        <small>Programa tu próxima visita</small>
      </span>
      <i class="bi bi-chevron-right chev"></i>
    </a>

    <div class="v26-stats-row" id="stats-inicio" data-tour="stats"></div>

    <div class="v26-seg" id="seg-dia">
      <button type="button" class="v26-seg-btn active" data-dia="hoy">Hoy</button>
      <button type="button" class="v26-seg-btn" data-dia="manana">Mañana</button>
    </div>

    <div id="resumen-pendientes"></div>

    <div id="cta-prospeccion" class="mb-3"></div>

    <div id="lista-citas" class="v26-timeline"></div>

    <button type="button" class="v26-btn v26-btn-ghost v26-btn-block mt-2" id="btn-tour-guiado"><i class="bi bi-signpost-split"></i> Tour guiado</button>

    <div class="v26-ubicacion-live"><span class="punto"></span> Compartiendo tu ubicación en vivo, para que la empresa pueda verificar tu recorrido.</div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/v26-modal.js<?= assetVer(__DIR__ . '/../assets/js/v26-modal.js') ?>"></script>
<script src="../assets/js/v26-tour.js<?= assetVer(__DIR__ . '/../assets/js/v26-tour.js') ?>"></script>
<script src="../assets/js/vendedor.js<?= assetVer(__DIR__ . '/../assets/js/vendedor.js') ?>"></script>
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

// Recorrido guiado de bienvenida -- ver assets/js/v26-tour.js. Se muestra
// solo la primera vez que este vendedor entra a Inicio (y siempre que toque
// el ícono "?"). Espera a que carguen las citas de hoy para poder señalar
// el resumen del día y la primera cita real (si no hay ninguna hoy, ese
// paso se omite solo).
const NOMBRE_VENDEDOR = <?= json_encode(explode(' ', trim($u['nombre']))[0]) ?>;
const PASOS_TOUR_INICIO = [
  { selector: '[data-tour="perfil"]', texto: 'Aquí ves tu perfil y el punto verde que confirma que estás compartiendo tu ubicación en vivo.' },
  { selector: '[data-tour="tabbar"]', texto: 'Desde aquí te mueves entre tus 5 secciones: Inicio, Calendario, Clientes, Cotizar y Reporte.' },
  { selector: '[data-tour="cta"]', texto: 'Agenda tu próxima visita en un toque, aquí mismo.' },
  { selector: '[data-tour="stats"]', texto: 'Tu resumen de hoy siempre a la vista: citas, completadas y pendientes.' },
  { selector: '#lista-citas .v26-cita', texto: 'Toca una visita para hacer check-in con GPS al llegar -- así la empresa confirma tu recorrido.' },
];
const OPCIONES_TOUR_INICIO = {
  storageKey: 'v26_tour_inicio_visto',
  saludoTitulo: `¡Bienvenida, ${NOMBRE_VENDEDOR}!`,
  saludoTexto: 'Te enseñamos en unos pasos rápidos dónde está todo antes de que empieces.',
  finalTexto: 'Repite este recorrido cuando quieras tocando el ícono ? de arriba.',
};
document.getElementById('btn-tour-ayuda').addEventListener('click', () => V26Tour.reiniciar(PASOS_TOUR_INICIO, OPCIONES_TOUR_INICIO));
document.getElementById('btn-tour-guiado').addEventListener('click', () => V26Tour.reiniciar(PASOS_TOUR_INICIO, OPCIONES_TOUR_INICIO));

cargarCitas('hoy').then(() => V26Tour.iniciar(PASOS_TOUR_INICIO, OPCIONES_TOUR_INICIO));
iniciarTrackingPeriodico();
</script>
</body>
</html>
