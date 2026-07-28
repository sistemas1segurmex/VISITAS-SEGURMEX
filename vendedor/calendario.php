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
<title>Mi calendario</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/vendedor-2026.css">
<style>
  #calendario-wrap { background: var(--v26-surface-solid); border-radius: var(--v26-r-lg); padding: 10px; box-shadow: var(--v26-shadow-sm); border: 1px solid var(--v26-border); }
  .fc-event { cursor: pointer; border: none !important; }
  .v26-evento-atenuado { opacity: .45; }
  .fc-day-past { background: rgba(107,114,128,.05); }
  .fc-daygrid-day.fc-day-past .fc-daygrid-day-number { color: var(--v26-ink-soft); }
  .v26-filtros { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }
  .v26-filtro {
    border: 1px solid var(--v26-border); background: var(--v26-surface-solid);
    border-radius: var(--v26-r-pill); padding: 6px 13px; font-size: .74rem; font-weight: 700;
    color: var(--v26-ink-soft); cursor: pointer; transition: all .15s var(--v26-ease);
  }
  .v26-filtro.active { color: #fff; border-color: transparent; }
  .v26-filtro[data-estado="pendiente"].active { background: var(--v26-gray); }
  .v26-filtro[data-estado="en_curso"].active { background: var(--v26-blue); }
  .v26-filtro[data-estado="completada"].active { background: var(--v26-green); }
  .v26-filtro[data-estado="no_realizada"].active { background: var(--v26-red); }
  .v26-filtro[data-estado="cancelada"].active { background: var(--v26-ink-soft); }

  /* ---------- FullCalendar con la paleta de la marca ---------- */
  .fc { font-family: inherit; }
  .fc .fc-toolbar-title { font-size: 1.05rem; font-weight: 800; color: var(--v26-ink); text-transform: capitalize; }
  .fc .fc-button-primary {
    background: var(--v26-surface-solid); border: 1px solid var(--v26-border); color: var(--v26-ink-soft);
    font-weight: 700; text-transform: capitalize; box-shadow: none; transition: all .15s var(--v26-ease);
  }
  .fc .fc-button-primary:hover { background: rgba(245,166,35,.1); color: var(--v26-brand-2); border-color: var(--v26-border); }
  .fc .fc-button-primary:not(:disabled):active,
  .fc .fc-button-primary:not(:disabled).fc-button-active {
    background: var(--v26-brand-grad); border-color: transparent; color: #fff; box-shadow: var(--v26-shadow-brand);
  }
  .fc .fc-button-primary:disabled { opacity: .4; }
  .fc .fc-button:focus, .fc .fc-button-primary:focus { box-shadow: 0 0 0 3px rgba(245,166,35,.25); }
  .fc .fc-today-button { text-transform: capitalize; }
  .fc-col-header-cell-cushion, .fc-daygrid-day-number, .fc-list-day-cushion {
    color: var(--v26-ink) !important; text-decoration: none !important; font-weight: 700;
  }
  .fc-col-header-cell-cushion { color: var(--v26-ink-soft) !important; font-size: .74rem; text-transform: uppercase; }
  .fc-daygrid-day.fc-day-today, .fc-list-day.fc-day-today .fc-list-day-cushion {
    background: rgba(245,166,35,.1) !important;
  }
  .fc-daygrid-day.fc-day-today .fc-daygrid-day-number { color: var(--v26-brand-2) !important; }
  .fc-theme-standard td, .fc-theme-standard th, .fc-theme-standard .fc-scrollgrid { border-color: var(--v26-border); }
  .fc-list-event:hover td { background: rgba(245,166,35,.06); }
  .fc-list-event-dot { border-width: 5px !important; }
</style>
</head>
<body class="v26">
  <div class="v26-header">
    <div class="v26-topbar">
      <div class="v26-topbar-left">
        <a href="index.php" class="v26-back v26-tip v26-tip--bottom" data-tip="Volver a mis visitas" aria-label="Volver"><i class="bi bi-arrow-left"></i></a>
        <div class="v26-greeting">
          <div class="hi">Agenda</div>
          <div class="name">Mi calendario</div>
        </div>
      </div>
      <div class="v26-topbar-right">
        <img src="../logo.png" alt="Segurmex" class="v26-logo">
      </div>
    </div>
    <div class="v26-tabbar">
      <a href="index.php"><i class="bi bi-house-fill"></i>Inicio</a>
      <a href="calendario.php" class="active"><i class="bi bi-calendar3"></i>Calendario</a>
      <a href="clientes.php"><i class="bi bi-people-fill"></i>Clientes</a>
      <a href="reporte.php"><i class="bi bi-bar-chart-fill"></i>Reporte</a>
    </div>
  </div>

  <div class="v26-wrap">
    <div class="v26-filtros" id="filtros-estado">
      <button type="button" class="v26-filtro active" data-estado="pendiente">Pendiente</button>
      <button type="button" class="v26-filtro active" data-estado="en_curso">En curso</button>
      <button type="button" class="v26-filtro active" data-estado="completada">Completada</button>
      <button type="button" class="v26-filtro active" data-estado="no_realizada">No realizada</button>
      <button type="button" class="v26-filtro active" data-estado="cancelada">Cancelada</button>
    </div>

    <div id="calendario-wrap">
      <div id="calendario"></div>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script>
const hoyStr = new Date().toISOString().slice(0, 10);
let filtrosActivos = new Set(['pendiente', 'en_curso', 'completada', 'no_realizada', 'cancelada']);
let calendar;

document.addEventListener('DOMContentLoaded', () => {
  const esMovil = window.innerWidth < 640;
  const calendarEl = document.getElementById('calendario');
  calendar = new FullCalendar.Calendar(calendarEl, {
    locale: 'es',
    headerToolbar: { left: 'prev,next today', center: 'title', right: esMovil ? 'listWeek,dayGridMonth' : 'dayGridMonth,dayGridWeek,listWeek' },
    initialView: esMovil ? 'listWeek' : 'dayGridMonth',
    height: 'auto',
    events: async function (info, successCallback, failureCallback) {
      try {
        const url = `../api/citas_calendario.php?start=${info.startStr}&end=${info.endStr}`;
        const res = await fetch(url);
        const data = await res.json();
        if (!data.ok) { failureCallback(data.error); return; }
        successCallback(data.eventos.filter(ev => filtrosActivos.has(ev.extendedProps.estado)));
      } catch (e) {
        failureCallback(e);
      }
    },
    dateClick: function (info) {
      if (info.dateStr < hoyStr) return; // días pasados: solo consulta
      window.location.href = `nueva_cita.php?fecha=${info.dateStr}`;
    },
    eventClick: function (info) {
      window.location.href = `checkin.php?cita_id=${info.event.id}`;
    },
    dayCellClassNames: function (arg) {
      const fechaStr = arg.date.toISOString().slice(0, 10);
      return fechaStr < hoyStr ? ['fc-day-past'] : [];
    },
    eventDidMount: function (info) {
      const p = info.event.extendedProps;
      const partes = [info.event.title, p.direccion];
      if (p.retrasada) partes.push('⚠ Retrasada');
      if (p.notas) partes.push('Nota: ' + p.notas);
      if (p.motivo) partes.push('Motivo: ' + p.motivo);
      info.el.classList.add('v26-tip', 'v26-tip--rico');
      info.el.setAttribute('data-tip', partes.join('\n'));
    }
  });
  calendar.render();
});

document.querySelectorAll('#filtros-estado .v26-filtro').forEach(btn => {
  btn.addEventListener('click', () => {
    const estado = btn.dataset.estado;
    btn.classList.toggle('active');
    if (btn.classList.contains('active')) filtrosActivos.add(estado);
    else filtrosActivos.delete(estado);
    calendar.refetchEvents();
  });
});
</script>
</body>
</html>
