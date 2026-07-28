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
<title>Mi calendario de citas</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<style>
  .fc-event { cursor: pointer; }
  #calendario { background: #fff; border-radius: 8px; padding: 8px; }
</style>
</head>
<body>
<nav class="navbar navbar-dark" style="background:#111827">
  <div class="container-fluid">
    <a href="index.php" class="navbar-brand navbar-brand-custom text-decoration-none">← Control de Visitas</a>
  </div>
</nav>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Mi calendario de citas</h5>
    <a href="nueva_cita.php" class="btn btn-sm btn-brand">+ Nueva cita</a>
  </div>
  <div id="calendario"></div>
  <p class="text-muted small mt-3">
    <span class="badge" style="background:#F5A623">&nbsp;</span> Pendiente
    <span class="badge" style="background:#2563eb">&nbsp;</span> En curso
    <span class="badge" style="background:#16a34a">&nbsp;</span> Completada
    <span class="badge" style="background:#dc2626">&nbsp;</span> No realizada
  </p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const calendarEl = document.getElementById('calendario');
  const calendar = new FullCalendar.Calendar(calendarEl, {
    locale: 'es',
    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,dayGridWeek,listWeek' },
    initialView: 'dayGridMonth',
    height: 'auto',
    events: async function (info, successCallback, failureCallback) {
      try {
        const url = `../api/citas_calendario.php?start=${info.startStr}&end=${info.endStr}`;
        const res = await fetch(url);
        const data = await res.json();
        if (!data.ok) { failureCallback(data.error); return; }
        successCallback(data.eventos);
      } catch (e) {
        failureCallback(e);
      }
    },
    eventClick: function (info) {
      window.location.href = `checkin.php?cita_id=${info.event.id}`;
    },
    eventDidMount: function (info) {
      info.el.title = `${info.event.title} — ${info.event.extendedProps.direccion}`;
    }
  });
  calendar.render();
});
</script>
</body>
</html>
