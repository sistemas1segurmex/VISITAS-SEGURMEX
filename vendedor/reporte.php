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
<title>Mi reporte</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/vendedor-2026.css">
<style>
  .v26-mes-selector { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; }
  .v26-mes-selector input { flex: 1; }
  .v26-mini-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 12px; }
  .v26-mini-card { background: var(--v26-surface-solid); border: 1px solid var(--v26-border); border-radius: var(--v26-r-lg); padding: 14px; text-align: center; }
  .v26-mini-card .num { font-size: 1.4rem; font-weight: 800; }
  .v26-mini-card .label { font-size: .7rem; color: var(--v26-ink-soft); margin-top: 2px; }
  .v26-chart-card { position: relative; }
  .v26-chart-card h4 { font-size: .82rem; font-weight: 800; margin-bottom: 2px; }
  .v26-chart-card .sub { font-size: .74rem; color: var(--v26-ink-soft); margin-bottom: 12px; }
  .v26-chart-wrap { position: relative; height: 220px; }
  .v26-chart-wrap.chico { height: 170px; }
</style>
</head>
<body class="v26">
  <div class="v26-header">
    <div class="v26-topbar">
      <div class="v26-topbar-left">
        <div class="v26-greeting">
          <div class="hi">Desempeño</div>
          <div class="name">Mi reporte</div>
        </div>
      </div>
      <div class="v26-topbar-right">
        <img src="../logo.png" alt="Segurmex" class="v26-logo">
      </div>
    </div>
    <div class="v26-tabbar">
      <a href="index.php"><i class="bi bi-house-fill"></i>Inicio</a>
      <a href="calendario.php"><i class="bi bi-calendar3"></i>Calendario</a>
      <a href="clientes.php"><i class="bi bi-people-fill"></i>Clientes</a>
      <a href="reporte.php" class="active"><i class="bi bi-bar-chart-fill"></i>Reporte</a>
    </div>
  </div>

  <div class="v26-wrap">
    <div class="v26-mes-selector">
      <input type="month" id="input-mes" class="v26-input">
    </div>
    <div id="contenido">
      <div class="v26-skel"></div>
      <div class="v26-skel"></div>
      <div class="v26-skel"></div>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
const coloresEstado = {
  completada: '#16a34a',
  no_realizada: '#e11d48',
  cancelada: '#6b7280',
  pendiente: '#f5a623',
  en_curso: '#4f46e5',
};
const etiquetasEstado = {
  completada: 'Completadas', no_realizada: 'No realizadas', cancelada: 'Canceladas',
  pendiente: 'Pendientes', en_curso: 'En curso',
};

let chartEstados = null;
let chartVerificados = null;

// Convierte "YYYY-MM-DD" al número de día ("1", "2"...) para el eje X.
function soloDia(fechaIso) { return String(parseInt(fechaIso.slice(8, 10), 10)); }

async function cargarReporte(mes) {
  const cont = document.getElementById('contenido');
  cont.innerHTML = '<div class="v26-skel"></div><div class="v26-skel"></div><div class="v26-skel"></div>';
  if (chartEstados) { chartEstados.destroy(); chartEstados = null; }
  if (chartVerificados) { chartVerificados.destroy(); chartVerificados = null; }

  try {
    const res = await fetch('../api/reporte_mensual.php?mes=' + mes);
    const data = await res.json();
    if (!data.ok) {
      cont.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
      return;
    }
    const r = data.resumen;

    if (r.total === 0) {
      cont.innerHTML = `
        <div class="v26-empty">
          <div class="icon"><i class="bi bi-bar-chart"></i></div>
          <p>No tienes citas registradas en este mes.</p>
        </div>`;
      return;
    }

    const diario = data.diario;
    const dias = diario.dias.map(soloDia);
    const estadosConDatos = Object.keys(etiquetasEstado).filter(e => r[e] > 0);

    cont.innerHTML = `
      <div class="v26-card v26-chart-card">
        <h4>Citas por día — ${r.porcentaje_cumplimiento}% completadas en el mes</h4>
        <div class="sub">Evolución diaria por estado</div>
        <div class="v26-chart-wrap">
          <canvas id="grafica-estados"></canvas>
        </div>
      </div>

      <div class="v26-card v26-chart-card" style="margin-top:14px;">
        <h4>Check-ins con GPS — ${r.porcentaje_verificado}% verificados</h4>
        <div class="sub">Verificados vs. fuera de zona por día</div>
        <div class="v26-chart-wrap chico">
          <canvas id="grafica-verificados"></canvas>
        </div>
      </div>

      <div class="v26-mini-grid">
        <div class="v26-mini-card">
          <div class="num">${r.total}</div>
          <div class="label">Citas programadas</div>
        </div>
        <div class="v26-mini-card">
          <div class="num">${r.clientes_visitados}</div>
          <div class="label">Clientes visitados</div>
        </div>
      </div>`;

    const leyendaAbajo = {
      position: 'bottom',
      labels: { boxWidth: 10, font: { size: 10 }, usePointStyle: true, pointStyle: 'circle' },
    };

    chartEstados = new Chart(document.getElementById('grafica-estados'), {
      type: 'line',
      data: {
        labels: dias,
        datasets: estadosConDatos.map(e => ({
          label: etiquetasEstado[e],
          data: diario.citas[e],
          borderColor: coloresEstado[e],
          backgroundColor: coloresEstado[e],
          tension: 0.35,
          pointRadius: 2,
          borderWidth: 2,
        })),
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: leyendaAbajo },
        scales: {
          x: { title: { display: true, text: 'Día del mes', font: { size: 10 } }, ticks: { font: { size: 9 }, maxTicksLimit: 10 } },
          y: { beginAtZero: true, ticks: { precision: 0, font: { size: 9 } } },
        },
      },
    });

    if (r.checkins_totales > 0) {
      chartVerificados = new Chart(document.getElementById('grafica-verificados'), {
        type: 'line',
        data: {
          labels: dias,
          datasets: [
            { label: 'Verificados', data: diario.checkins_verificados, borderColor: '#16a34a', backgroundColor: 'rgba(22,163,74,.15)', fill: true, tension: 0.35, pointRadius: 2, borderWidth: 2 },
            { label: 'Fuera de zona', data: diario.checkins_no_verificados, borderColor: '#e11d48', backgroundColor: 'rgba(225,29,72,.12)', fill: true, tension: 0.35, pointRadius: 2, borderWidth: 2 },
          ],
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: leyendaAbajo },
          scales: {
            x: { ticks: { font: { size: 9 }, maxTicksLimit: 10 } },
            y: { beginAtZero: true, ticks: { precision: 0, font: { size: 9 } } },
          },
        },
      });
    } else {
      document.getElementById('grafica-verificados').replaceWith(
        Object.assign(document.createElement('div'), {
          className: 'v26-empty', style: 'padding:20px 0;',
          innerHTML: '<p style="margin:0;font-size:.8rem;">Aún no registras check-ins este mes.</p>',
        })
      );
    }
  } catch (e) {
    cont.innerHTML = '<div class="alert alert-danger">No se pudo cargar el reporte.</div>';
  }
}

const inputMes = document.getElementById('input-mes');
const hoy = new Date();
inputMes.value = hoy.toISOString().slice(0, 7);
inputMes.addEventListener('change', () => cargarReporte(inputMes.value));
cargarReporte(inputMes.value);
</script>
</body>
</html>
