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
<title>Ubicaciones corregidas — Control de Visitas</title>
<link rel="icon" type="image/png" href="../assets/img/Favv-Segu.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css<?= assetVer(__DIR__ . '/../assets/css/style.css') ?>">
<link rel="stylesheet" href="../assets/css/admin-2026.css<?= assetVer(__DIR__ . '/../assets/css/admin-2026.css') ?>">
<style>
  .corr-card { display: flex; gap: 14px; align-items: flex-start; }
  .corr-foto { width: 84px; height: 84px; border-radius: 12px; object-fit: cover; flex: none; background: #eee; }
  .corr-datos { flex: 1; min-width: 0; }
  .corr-datos .linea { font-size: .8rem; color: var(--v26-ink-soft); margin-top: 3px; }
  .corr-acciones { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
  .corr-acciones .v26-btn { width: auto; padding: 6px 14px; font-size: .8rem; }
</style>
</head>
<body class="v26">
<div class="v26-header">
  <div class="v26-topbar">
    <div class="v26-topbar-left">
      <a href="index.php" class="v26-back" aria-label="Volver"><i class="bi bi-arrow-left"></i></a>
      <div class="v26-greeting">
        <div class="hi">Control de Visitas</div>
        <div class="name">Ubicaciones corregidas</div>
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
      <h5 class="mb-0">Pines de clientes corregidos en visita</h5>
      <p class="v26-subtitulo mb-0">Cuando el pin estaba lejos y el vendedor confirmó que estaba en el lugar</p>
    </div>
    <div class="v26-segmented" id="filtro-estado">
      <button type="button" class="opt active" data-estado="por_revisar"><i class="bi bi-exclamation-circle"></i> Por revisar</button>
      <button type="button" class="opt" data-estado="todas"><i class="bi bi-list-ul"></i> Todas</button>
    </div>
  </div>
  <div id="msg" class="mb-2"></div>
  <div id="lista"><p class="text-muted small">Cargando...</p></div>
</div>

<script src="../assets/js/fecha_utils.js<?= assetVer(__DIR__ . '/../assets/js/fecha_utils.js') ?>"></script>
<script>
const ESTADOS = {
  aplicada:    ['v26-pill--verificado', 'Corregida'],
  por_revisar: ['v26-pill--pendiente', 'Por revisar'],
  aprobada:    ['v26-pill--verificado', 'Aprobada'],
  revertida:   ['v26-pill--noverificado', 'Revertida'],
};
let estadoFiltro = 'por_revisar';

const esc = (t) => String(t ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const mapa = (lat, lng) => `https://www.google.com/maps?q=${lat},${lng}`;
const metros = (m) => m >= 1000 ? (m / 1000).toFixed(1) + ' km' : Math.round(m) + ' m';

async function cargar() {
  const res = await fetch('../api/admin_ubicaciones.php?estado=' + estadoFiltro);
  const data = await res.json();
  const lista = document.getElementById('lista');
  if (!data.ok) { lista.innerHTML = `<div class="alert alert-danger py-2">${esc(data.error)}</div>`; return; }
  document.querySelector('[data-estado="por_revisar"]').innerHTML = `<i class="bi bi-exclamation-circle"></i> Por revisar (${data.por_revisar})`;
  if (!data.correcciones.length) {
    lista.innerHTML = `<p class="text-muted small">${estadoFiltro === 'por_revisar' ? 'No hay correcciones pendientes de revisar.' : 'Todavía no hay correcciones.'}</p>`;
    return;
  }
  lista.innerHTML = data.correcciones.map(c => {
    const [clase, texto] = ESTADOS[c.estado] || ['', c.estado];
    const revisable = c.estado === 'aplicada' || c.estado === 'por_revisar';
    return `
      <div class="v26-card mb-2 corr-card">
        <a href="../api/foto.php?checkin_id=${c.checkin_id}" target="_blank" rel="noopener"><img class="corr-foto" src="../api/foto.php?checkin_id=${c.checkin_id}" alt="Foto de entrada" loading="lazy"></a>
        <div class="corr-datos">
          <strong>${esc(c.cliente_nombre)}</strong> <span class="v26-pill ${clase}" style="margin-left:6px;">${texto}</span>
          <div class="linea">${esc(c.direccion)}</div>
          <div class="linea"><i class="bi bi-person"></i> ${esc(c.vendedor_nombre)} · ${formatearFechaUTC(c.creado_en)}</div>
          <div class="linea"><i class="bi bi-arrows-move"></i> El pin anterior estaba a <b>${metros(c.distancia_metros)}</b> · GPS del vendedor ±${Math.round(c.accuracy)} m</div>
          ${c.nota ? `<div class="linea text-danger"><i class="bi bi-exclamation-triangle"></i> ${esc(c.nota)}</div>` : ''}
          ${c.revisado_por_nombre ? `<div class="linea">Revisó: ${esc(c.revisado_por_nombre)} · ${formatearFechaUTC(c.revisado_en)}</div>` : ''}
          <div class="corr-acciones">
            <a class="v26-btn v26-btn-ghost" href="${mapa(c.lat_anterior, c.lng_anterior)}" target="_blank" rel="noopener"><i class="bi bi-geo"></i> Pin anterior</a>
            <a class="v26-btn v26-btn-ghost" href="${mapa(c.lat_nueva, c.lng_nueva)}" target="_blank" rel="noopener"><i class="bi bi-geo-alt-fill"></i> Donde estaba el vendedor</a>
            ${revisable ? `
              <button type="button" class="v26-btn v26-btn-primary" data-accion="aprobar" data-id="${c.id}"><i class="bi bi-check-lg"></i> Aprobar</button>
              <button type="button" class="v26-btn v26-btn-ghost text-danger" data-accion="revertir" data-id="${c.id}"><i class="bi bi-arrow-counterclockwise"></i> Revertir</button>` : ''}
          </div>
        </div>
      </div>`;
  }).join('');
}

document.getElementById('lista').addEventListener('click', async (e) => {
  const btn = e.target.closest('[data-accion]');
  if (!btn) return;
  const accion = btn.dataset.accion;
  if (accion === 'revertir' && !confirm('¿Revertir? El cliente regresa al pin anterior y esa visita queda "Fuera de zona".')) return;
  btn.disabled = true;
  const fd = new FormData();
  fd.append('action', accion);
  fd.append('id', btn.dataset.id);
  const msg = document.getElementById('msg');
  try {
    const data = await (await fetch('../api/admin_ubicaciones.php', { method: 'POST', body: fd })).json();
    if (!data.ok) throw new Error(data.error);
    msg.innerHTML = accion === 'revertir' && data.pin_restaurado === false
      ? '<div class="alert alert-warning py-2">Revertida. El pin del cliente no se tocó porque ya se había movido después.</div>'
      : '';
    cargar();
  } catch (err) {
    msg.innerHTML = `<div class="alert alert-danger py-2">${esc(err.message || 'Error de conexión')}</div>`;
    btn.disabled = false;
  }
});

document.querySelectorAll('#filtro-estado .opt').forEach(b => b.addEventListener('click', () => {
  document.querySelectorAll('#filtro-estado .opt').forEach(x => x.classList.remove('active'));
  b.classList.add('active');
  estadoFiltro = b.dataset.estado;
  cargar();
}));

cargar();
</script>
</body>
</html>
