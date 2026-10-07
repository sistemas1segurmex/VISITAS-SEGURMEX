<?php
// Bandeja de solicitudes de muestra de los vendedores externos.
//  - Responsable de muestras (rol 'muestras'): su pantalla de inicio. Ve las
//    nuevas, las que está preparando y las cerradas, y desde el detalle
//    (ver.php) marca el avance. Le llega un aviso (campanita) y un correo
//    por cada solicitud nueva.
//  - Admin: misma bandeja, solo para consultar (sin botones de estado ni
//    avisos).
// Ver includes/muestras.php.
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$u = requireAnyRole(['muestras', 'admin']);
$esAdmin = $u['rol'] === 'admin';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Solicitudes de muestra</title>
<link rel="icon" type="image/png" href="../assets/img/Favv-Segu.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css<?= assetVer(__DIR__ . '/../assets/css/style.css') ?>">
<link rel="stylesheet" href="../assets/css/vendedor-2026.css<?= assetVer(__DIR__ . '/../assets/css/vendedor-2026.css') ?>">
<link rel="stylesheet" href="../assets/css/muestras.css<?= assetVer(__DIR__ . '/../assets/css/muestras.css') ?>">
</head>
<body class="v26">
  <div class="v26-header">
    <div class="v26-topbar">
      <div class="v26-topbar-left">
        <?php if ($esAdmin): ?>
        <a href="../admin/index.php" class="v26-back" aria-label="Volver al panel"><i class="bi bi-arrow-left"></i></a>
        <?php endif; ?>
        <div class="v26-greeting">
          <div class="hi"><?= $esAdmin ? 'Control de Visitas · Consulta' : 'Hola, ' . htmlspecialchars(strtok($u['nombre'], ' ')) ?></div>
          <div class="name">Solicitudes de muestra</div>
        </div>
      </div>
      <div class="v26-topbar-right">
        <img src="../logo.png" alt="Segurmex" class="v26-logo">
        <a href="../logout.php" class="v26-icon-btn v26-tip v26-tip--bottom" data-tip="Cerrar sesión" aria-label="Salir"><i class="bi bi-box-arrow-right"></i></a>
      </div>
    </div>
  </div>

  <div class="v26-wrap mu-wrap">
    <?php if ($esAdmin): ?>
    <div class="alert alert-light border small py-2"><i class="bi bi-eye"></i> Vista de consulta. Quien cambia el estado de las solicitudes es la responsable de muestras.</div>
    <?php else: ?>
    <p class="mu-ayuda">Aquí llegan las muestras que piden los vendedores externos. Abre una para ver todos sus datos y marcar el avance: <strong>En preparación</strong> y luego <strong>Embarcada</strong>. Cada vez que la marcas, al vendedor le llega un aviso.</p>
    <?php endif; ?>

    <div class="v26-funnel-filtro" id="filtro-grupo">
      <div class="v26-funnel-chip active" data-grupo="nuevas">Nuevas <span class="n" id="n-nuevas">0</span></div>
      <div class="v26-funnel-chip" data-grupo="en_preparacion">En preparación <span class="n" id="n-en_preparacion">0</span></div>
      <div class="v26-funnel-chip" data-grupo="cerradas">Cerradas <span class="n" id="n-cerradas">0</span></div>
      <div class="v26-funnel-chip" data-grupo="todas">Todas <span class="n" id="n-todas">0</span></div>
    </div>

    <div class="v26-search">
      <i class="bi bi-search"></i>
      <input type="text" id="buscar" class="v26-input" placeholder="Buscar por folio, vendedor, cliente o estilo...">
    </div>

    <div id="lista">
      <div class="v26-skel"></div>
      <div class="v26-skel"></div>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if (!$esAdmin): ?>
<script src="../assets/js/avisos.js<?= assetVer(__DIR__ . '/../assets/js/avisos.js') ?>"></script>
<?php endif; ?>
<script src="../assets/js/muestras.js<?= assetVer(__DIR__ . '/../assets/js/muestras.js') ?>"></script>
<script>
let grupo = 'nuevas';
let busqueda = '';
const VACIO = {
  nuevas: 'No hay solicitudes nuevas. Cuando un vendedor pida una muestra te llegará un aviso.',
  en_preparacion: 'No tienes muestras en preparación.',
  cerradas: 'Todavía no hay muestras embarcadas ni canceladas.',
  todas: 'Todavía no hay solicitudes de muestra.',
};

function tarjeta(m) {
  return `
    <a class="mu-card mu-card--${m.estado}" href="ver.php?id=${m.id}">
      <div class="mu-card-top">
        <span class="mu-folio">${escHtml(m.folio)}</span>
        ${pillEstadoMuestra(m.estado)}
      </div>
      <div class="mu-card-titulo">${escHtml(m.estilo_nombre)}${m.talla ? ' · talla ' + escHtml(m.talla) : ''}</div>
      <div class="mu-card-linea"><i class="bi bi-person"></i> ${escHtml(m.vendedor_nombre)} <span class="mu-sep">→</span> ${escHtml(m.cliente_nombre)}</div>
      <div class="mu-card-pie">
        <span><i class="bi bi-clock"></i> ${m.estado === 'enviada' || m.estado === 'en_preparacion' ? 'Pedida ' + haceMuestra(m.created_at) : fechaMx(m.actualizada_en)}</span>
        ${m.fecha_promesa ? `<span class="${promesaVencida(m) ? 'mu-vencida' : ''}"><i class="bi bi-calendar-event"></i> Promesa ${fechaMx(m.fecha_promesa)}</span>` : ''}
        ${m.tipo === 'variante' ? '<span><i class="bi bi-shuffle"></i> Con cambios</span>' : ''}
        ${m.estado === 'embarcada' ? `<span><i class="bi bi-truck"></i> ${escHtml(textoEnvio(m))}</span>` : ''}
      </div>
    </a>`;
}

async function cargar() {
  const lista = document.getElementById('lista');
  try {
    const r = await fetch(`../api/muestras_bandeja.php?grupo=${grupo}&q=${encodeURIComponent(busqueda)}`, { cache: 'no-store' });
    const data = await r.json();
    if (!data.ok) throw new Error(data.error || 'Error');
    Object.entries(data.conteos).forEach(([k, v]) => { const el = document.getElementById('n-' + k); if (el) el.textContent = v; });
    lista.innerHTML = data.solicitudes.length
      ? data.solicitudes.map(tarjeta).join('')
      : `<div class="v26-empty"><div class="icon"><i class="bi bi-inbox"></i></div><p>${busqueda ? 'Nada coincide con la búsqueda.' : VACIO[grupo]}</p></div>`;
  } catch (e) {
    lista.innerHTML = '<div class="alert alert-danger">No se pudieron cargar las solicitudes. Revisa tu conexión e intenta de nuevo.</div>';
  }
}

document.getElementById('filtro-grupo').addEventListener('click', (e) => {
  const chip = e.target.closest('.v26-funnel-chip');
  if (!chip) return;
  document.querySelectorAll('#filtro-grupo .v26-funnel-chip').forEach(c => c.classList.toggle('active', c === chip));
  grupo = chip.dataset.grupo;
  cargar();
});
let tBuscar;
document.getElementById('buscar').addEventListener('input', (e) => {
  clearTimeout(tBuscar);
  tBuscar = setTimeout(() => { busqueda = e.target.value.trim(); cargar(); }, 300);
});

// Se refresca sola: cada minuto y cuando llega un aviso nuevo.
let ultimosNoLeidos = null;
document.addEventListener('v26:avisos', (e) => {
  const n = e.detail.no_leidos || 0;
  if (ultimosNoLeidos !== null && n > ultimosNoLeidos) cargar();
  ultimosNoLeidos = n;
});
setInterval(() => { if (!document.hidden) cargar(); }, 60000);
cargar();
</script>
</body>
</html>
