<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$u = requireRole('admin');
$hoy = (new DateTime('now', new DateTimeZone('America/Mexico_City')))->format('Y-m-d');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Métricas — Control de Visitas</title>
<link rel="icon" type="image/png" href="../assets/img/Favv-Segu.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css<?= assetVer(__DIR__ . '/../assets/css/style.css') ?>">
<link rel="stylesheet" href="../assets/css/admin-2026.css<?= assetVer(__DIR__ . '/../assets/css/admin-2026.css') ?>">
<link rel="stylesheet" href="../assets/css/admin-metricas.css<?= assetVer(__DIR__ . '/../assets/css/admin-metricas.css') ?>">
</head>
<body class="v26">
<div class="v26-header">
  <div class="v26-topbar">
    <div class="v26-topbar-left">
      <a href="index.php" class="v26-back" aria-label="Volver"><i class="bi bi-arrow-left"></i></a>
      <div class="v26-greeting">
        <div class="hi">Control de Visitas</div>
        <div class="name">Métricas del equipo</div>
      </div>
    </div>
    <div class="v26-topbar-right">
      <span class="v26-user"><?= htmlspecialchars($u['nombre']) ?></span>
      <a href="../logout.php" class="v26-icon-btn v26-tip v26-tip--bottom" data-tip="Cerrar sesión" aria-label="Salir"><i class="bi bi-box-arrow-right"></i></a>
    </div>
  </div>
</div>

<div class="v26-wrap mt-wrap">
  <!-- Filtros: todos en una fila -->
  <div class="mt-filtros">
    <div class="mt-periodos" role="group" aria-label="Periodo">
      <button type="button" data-p="semana" class="active">Esta semana</button>
      <button type="button" data-p="semana_ant">Semana pasada</button>
      <button type="button" data-p="mes">Este mes</button>
      <button type="button" data-p="mes_ant">Mes pasado</button>
      <button type="button" data-p="30">Últimos 30 días</button>
      <button type="button" data-p="rango">Elegir fechas</button>
    </div>
    <div class="mt-rango" id="rango" hidden>
      <input type="date" id="desde" class="form-control form-control-sm" max="<?= $hoy ?>" aria-label="Desde">
      <span>a</span>
      <input type="date" id="hasta" class="form-control form-control-sm" max="<?= $hoy ?>" aria-label="Hasta">
    </div>
    <select id="vendedor" class="form-select form-select-sm mt-select" aria-label="Vendedor"><option value="0">Todo el equipo</option></select>
  </div>
  <p class="mt-periodo-txt" id="periodo-txt">Cargando...</p>

  <!-- Números clave -->
  <div class="mt-kpis" id="kpis"></div>

  <!-- Tendencia -->
  <section class="mt-card">
    <div class="mt-card-cab">
      <div><h6>Visitas y cotizaciones por día</h6><p>Visitas realizadas y cotizaciones generadas cada día del periodo</p></div>
      <button type="button" class="mt-link" data-tabla="serie"><i class="bi bi-table"></i> Ver tabla</button>
    </div>
    <div class="mt-leyenda"><span><i style="background:var(--mt-s1)"></i>Visitas realizadas</span><span><i style="background:var(--mt-s2)"></i>Cotizaciones</span></div>
    <div class="mt-chart"><canvas id="ch-serie" aria-label="Gráfica de visitas y cotizaciones por día" role="img"></canvas></div>
    <div class="mt-tabla-alt" id="tabla-serie" hidden></div>
  </section>

  <!-- Comparativa por vendedor -->
  <section class="mt-card">
    <div class="mt-card-cab">
      <div><h6>Comparativa por vendedor</h6><p>Toca un encabezado para ordenar. <span class="mt-sem ok"><i class="bi bi-check-circle-fill"></i></span> arriba del promedio · <span class="mt-sem warn"><i class="bi bi-dash-circle-fill"></i></span> cerca · <span class="mt-sem mal"><i class="bi bi-exclamation-circle-fill"></i></span> abajo del 70 % del promedio</p></div>
    </div>
    <div class="mt-tabs" role="tablist" id="tabs-tabla">
      <button type="button" class="active" data-t="visitas">Visitas</button>
      <button type="button" data-t="ventas">Ventas</button>
      <button type="button" data-t="campo">En campo</button>
      <button type="button" data-t="calidad">Calidad</button>
    </div>
    <div class="mt-tabla-wrap"><table class="mt-tabla" id="tabla-vend"></table></div>
  </section>

  <div class="mt-grid2">
    <section class="mt-card">
      <div class="mt-card-cab"><div><h6>¿Qué tan interesado quedó el cliente?</h6><p>Lo que marcó el vendedor al cerrar cada visita</p></div></div>
      <div id="interes" class="mt-barras"></div>
    </section>
    <section class="mt-card">
      <div class="mt-card-cab"><div><h6>¿Por qué no se hicieron las citas?</h6><p>Citas no realizadas o canceladas</p></div></div>
      <div id="motivos" class="mt-barras"></div>
    </section>
  </div>

  <section class="mt-card">
    <div class="mt-card-cab"><div><h6>¿A qué hora visitan?</h6><p>Visitas realizadas por día de la semana y hora de la cita. Más oscuro = más visitas.</p></div></div>
    <div class="mt-calor-wrap"><div id="calor" class="mt-calor"></div></div>
  </section>

  <div class="mt-grid2">
    <section class="mt-card">
      <div class="mt-card-cab"><div><h6>De la visita a la venta</h6><p>Cotizaciones del periodo</p></div></div>
      <div id="venta" class="mt-mini-kpis"></div>
      <div class="mt-sub">Modelos más cotizados</div>
      <div id="modelos" class="mt-barras"></div>
    </section>
    <section class="mt-card">
      <div class="mt-card-cab"><div><h6>Visitas por estado</h6><p>Estado de la República del cliente visitado</p></div></div>
      <div id="estados" class="mt-barras"></div>
    </section>
  </div>

  <section class="mt-card">
    <div class="mt-card-cab"><div><h6>Embudo de cada vendedor</h6><p>En qué etapa están hoy sus prospectos y clientes (no depende del periodo)</p></div></div>
    <div class="mt-leyenda" id="embudo-leyenda"></div>
    <div id="embudo" class="mt-embudo"></div>
  </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>const HOY = <?= json_encode($hoy) ?>;</script>
<script src="../assets/js/admin_metricas.js<?= assetVer(__DIR__ . '/../assets/js/admin_metricas.js') ?>"></script>
</body>
</html>
