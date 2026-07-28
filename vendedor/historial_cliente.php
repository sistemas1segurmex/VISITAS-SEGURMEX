<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
$u = requireRole('vendedor');
$clienteId = (int)($_GET['id'] ?? 0);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Historial del cliente</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/vendedor-2026.css">
<style>
  .v26-cliente-card { margin-bottom: 16px; }
  .v26-cliente-card h2 { font-size: 1.1rem; font-weight: 800; margin-bottom: 4px; }
  .v26-cliente-card .dir { font-size: .82rem; color: var(--v26-ink-soft); margin-bottom: 10px; }
  .v26-resumen-mini { display: flex; flex-wrap: wrap; gap: 6px; }
  .v26-evidencia { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
  .v26-evidencia a { display: flex; align-items: center; gap: 4px; font-size: .72rem; font-weight: 700; }
  .v26-evidencia img.miniatura { width: 44px; height: 44px; object-fit: cover; border-radius: var(--v26-r-md); border: 1px solid var(--v26-border); }
</style>
</head>
<body class="v26">
  <div class="v26-header">
    <div class="v26-topbar">
      <div class="v26-topbar-left">
        <a href="clientes.php" class="v26-back v26-tip v26-tip--bottom" data-tip="Volver a mis clientes" aria-label="Volver"><i class="bi bi-arrow-left"></i></a>
        <div class="v26-greeting">
          <div class="hi">Cartera</div>
          <div class="name">Historial del cliente</div>
        </div>
      </div>
      <div class="v26-topbar-right">
        <img src="../logo.png" alt="Segurmex" class="v26-logo">
      </div>
    </div>
  </div>

  <div class="v26-wrap">
    <div id="contenido">
      <div class="v26-skel"></div>
      <div class="v26-skel"></div>
      <div class="v26-skel"></div>
    </div>
  </div>

<script src="../assets/js/vendedor.js"></script>
<script>
const clienteId = <?= json_encode($clienteId) ?>;

function fechaLarga(fechaHora) {
  const d = new Date((fechaHora || '').replace(' ', 'T'));
  if (isNaN(d)) return fechaHora;
  return d.toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' }) +
    ' · ' + d.toLocaleTimeString('es-MX', { hour: 'numeric', minute: '2-digit' });
}

function renderEvidencia(checkins) {
  if (!checkins || checkins.length === 0) return '';
  return '<div class="v26-evidencia">' + checkins.map(chk => {
    const etiqueta = chk.tipo === 'entrada' ? 'Entrada' : 'Salida';
    const pill = chk.verificado == 1
      ? '<span class="v26-pill v26-pill--verificado">GPS ok</span>'
      : '<span class="v26-pill v26-pill--noverificado">Fuera de zona</span>';
    const foto = chk.foto_path
      ? `<a href="../${chk.foto_path}" target="_blank" rel="noopener"><img class="miniatura" src="../${chk.foto_path}" alt="Foto ${etiqueta}"></a>`
      : '';
    return `<div class="v26-evidencia-item">
      ${foto}
      <div>
        <div style="font-size:.72rem;font-weight:700;">${etiqueta} · ${fechaLarga(chk.fecha_hora)}</div>
        <div class="badges">${pill}${chk.distancia_metros !== null ? ` <span class="text-muted" style="font-size:.7rem;">(${Math.round(chk.distancia_metros)} m)</span>` : ''}</div>
      </div>
    </div>`;
  }).join('') + '</div>';
}

async function cargarHistorial() {
  const cont = document.getElementById('contenido');
  if (!clienteId) {
    cont.innerHTML = '<div class="alert alert-danger">Cliente no especificado.</div>';
    return;
  }
  try {
    const res = await fetch(`../api/historial_cliente.php?cliente_id=${clienteId}`);
    const data = await res.json();
    if (!data.ok) {
      cont.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
      return;
    }
    const c = data.cliente;
    const r = data.resumen;

    const direccionMostrar = [c.calle_numero, c.colonia, c.municipio, c.estado, c.cp ? `CP ${c.cp}` : '']
      .filter(Boolean).join(', ') || c.direccion;

    let html = `
      <div class="v26-card v26-cliente-card">
        <h2>${c.nombre}</h2>
        <div class="dir"><i class="bi bi-geo-alt"></i> ${direccionMostrar}</div>
        ${c.telefono ? `<div class="dir"><i class="bi bi-telephone"></i> ${c.telefono}</div>` : ''}
        <div class="v26-resumen-mini">
          <span class="v26-pill v26-pill--completada">${r.completada} completadas</span>
          <span class="v26-pill v26-pill--no_realizada">${r.no_realizada} no realizadas</span>
          <span class="v26-pill v26-pill--cancelada">${r.cancelada} canceladas</span>
          <span class="v26-pill v26-pill--pendiente">${r.pendiente} pendientes</span>
        </div>
      </div>`;

    if (data.citas.length === 0) {
      html += `
        <div class="v26-empty">
          <div class="icon"><i class="bi bi-calendar2-x"></i></div>
          <p>Este cliente todavía no tiene citas registradas.</p>
        </div>`;
    } else {
      html += '<div class="v26-timeline">' + data.citas.map(cita => `
        <div class="v26-cita ${cita.estado === 'cancelada' ? 'v26-cita--cancelada' : ''}" style="cursor:default;">
          <span class="v26-timeline-dot ${cita.estado}"></span>
          <div class="info">
            <div class="cliente">${fechaLarga(cita.fecha_hora)}</div>
            <div class="badges">${badgeEstado(cita)}</div>
            ${cita.notas ? `<div class="v26-motivo"><i class="bi bi-sticky"></i> ${cita.notas}</div>` : ''}
            ${cita.motivo ? `<div class="v26-motivo">"${cita.motivo}"</div>` : ''}
            ${renderEvidencia(cita.checkins)}
          </div>
        </div>
      `).join('') + '</div>';
    }

    cont.innerHTML = html;
  } catch (e) {
    cont.innerHTML = '<div class="alert alert-danger">No se pudo cargar el historial.</div>';
  }
}

cargarHistorial();
</script>
</body>
</html>
