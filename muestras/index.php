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
          <div class="hi"><?= $esAdmin ? 'Control de Visitas · Consulta' : 'Control de Visitas' ?></div>
          <div class="name">Solicitudes de muestra</div>
        </div>
      </div>
      <div class="v26-topbar-right">
        <img src="../logo.png" alt="Segurmex" class="v26-logo">
        <a href="../logout.php" class="v26-icon-btn v26-tip v26-tip--bottom" data-tip="Cerrar sesión" aria-label="Salir"><i class="bi bi-box-arrow-right"></i></a>
      </div>
    </div>
  </div>

  <div class="v26-wrap mu-wrap mu-wrap--bandeja">
    <section class="mu-hero">
      <div class="mu-hero-txt">
        <div class="mu-hero-eyebrow"><i class="bi bi-box-seam"></i> <?= $esAdmin ? 'Consulta de muestras' : 'Muestras de vendedores externos' ?></div>
        <h1 class="mu-hero-titulo" id="hero-titulo">Cargando...</h1>
        <p class="mu-hero-sub"><?= $esAdmin
          ? 'Vista de solo consulta: quien cambia el estado es la responsable de muestras.'
          : 'Abre una solicitud para ver sus datos y marcar el avance. Cada vez que la marcas, al vendedor le llega un aviso.' ?></p>
      </div>
      <div class="mu-hero-pasos" aria-hidden="true">
        <span><i class="bi bi-inbox"></i> Nueva</span><i class="bi bi-chevron-right"></i>
        <span><i class="bi bi-box-seam"></i> En preparación</span><i class="bi bi-chevron-right"></i>
        <span><i class="bi bi-truck"></i> Embarcada</span>
      </div>
    </section>

    <div class="mu-stats">
      <button type="button" class="mu-stat mu-stat--nuevas" data-grupo="nuevas">
        <span class="mu-stat-ico"><i class="bi bi-inbox-fill"></i></span>
        <span><span class="mu-stat-n" id="s-nuevas">0</span><span class="mu-stat-lbl">Nuevas</span></span>
      </button>
      <button type="button" class="mu-stat mu-stat--prep" data-grupo="en_preparacion">
        <span class="mu-stat-ico"><i class="bi bi-box-seam-fill"></i></span>
        <span><span class="mu-stat-n" id="s-en_preparacion">0</span><span class="mu-stat-lbl">En preparación</span></span>
      </button>
      <button type="button" class="mu-stat mu-stat--vencidas" data-grupo="vencidas">
        <span class="mu-stat-ico"><i class="bi bi-alarm-fill"></i></span>
        <span><span class="mu-stat-n" id="s-vencidas">0</span><span class="mu-stat-lbl">Promesa vencida</span></span>
      </button>
      <button type="button" class="mu-stat mu-stat--emb" data-grupo="cerradas">
        <span class="mu-stat-ico"><i class="bi bi-truck"></i></span>
        <span><span class="mu-stat-n" id="s-embarcadas_mes">0</span><span class="mu-stat-lbl">Embarcadas este mes</span></span>
      </button>
    </div>

    <div class="mu-toolbar">
      <div class="v26-funnel-filtro mb-0" id="filtro-grupo">
        <div class="v26-funnel-chip active" data-grupo="nuevas">Nuevas <span class="n" id="n-nuevas">0</span></div>
        <div class="v26-funnel-chip" data-grupo="en_preparacion">En preparación <span class="n" id="n-en_preparacion">0</span></div>
        <div class="v26-funnel-chip" data-grupo="vencidas">Vencidas <span class="n" id="n-vencidas">0</span></div>
        <div class="v26-funnel-chip" data-grupo="cerradas">Cerradas <span class="n" id="n-cerradas">0</span></div>
        <div class="v26-funnel-chip" data-grupo="todas">Todas <span class="n" id="n-todas">0</span></div>
      </div>
      <div class="v26-search mb-0">
        <i class="bi bi-search"></i>
        <input type="text" id="buscar" class="v26-input" placeholder="Buscar folio, vendedor, cliente o estilo...">
      </div>
    </div>

    <div id="lista" class="mu-grid">
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
const ES_ADMIN = <?= $esAdmin ? 'true' : 'false' ?>;
const NOMBRE = <?= json_encode(strtok($u['nombre'], ' ')) ?>;
const VACIO = {
  nuevas: ['bi-inbox', 'Todo al día', 'No hay solicitudes nuevas. Cuando un vendedor pida una muestra te llegará un aviso.'],
  en_preparacion: ['bi-box-seam', 'Nada en preparación', 'Las muestras que marques "En preparación" aparecen aquí.'],
  vencidas: ['bi-emoji-smile', 'Sin promesas vencidas', 'Ninguna muestra abierta ha pasado su fecha promesa.'],
  cerradas: ['bi-archive', 'Sin cerradas todavía', 'Aquí quedan las muestras embarcadas y las canceladas.'],
  todas: ['bi-inbox', 'Sin solicitudes', 'Todavía no hay solicitudes de muestra.'],
};
const ICONO_ESTADO = { enviada: 'bi-inbox-fill', en_preparacion: 'bi-box-seam-fill', embarcada: 'bi-truck', cancelada: 'bi-x-circle-fill' };

function iniciales(n) { return String(n || '?').trim().split(/\s+/).slice(0, 2).map(p => p[0]).join('').toUpperCase(); }
function diasDesde(valor) { const d = fechaDeMuestra(valor); return d ? Math.floor((Date.now() - d.getTime()) / 86400000) : 0; }
function diasParaPromesa(m) {
  if (!m.fecha_promesa) return null;
  const hoy = new Date(new Date().toLocaleDateString('en-CA', { timeZone: 'America/Mexico_City' }) + 'T12:00:00');
  return Math.round((fechaDeMuestra(m.fecha_promesa) - hoy) / 86400000);
}

function badgeEspera(m) {
  if (m.estado !== 'enviada' && m.estado !== 'en_preparacion') {
    return `<span class="mu-badge"><i class="bi bi-check2-circle"></i> ${m.estado === 'embarcada' ? 'Embarcada' : 'Cancelada'} ${fechaMx(m.actualizada_en)}</span>`;
  }
  const d = diasDesde(m.created_at);
  const clase = d >= 3 ? 'mu-badge--rojo' : (d >= 1 ? 'mu-badge--ambar' : 'mu-badge--verde');
  return `<span class="mu-badge ${clase}"><i class="bi bi-hourglass-split"></i> ${d === 0 ? 'Pedida ' + haceMuestra(m.created_at) : 'Esperando ' + (d === 1 ? '1 día' : d + ' días')}</span>`;
}
function badgePromesa(m) {
  const d = diasParaPromesa(m);
  if (d === null) return '';
  const abierta = m.estado === 'enviada' || m.estado === 'en_preparacion';
  if (abierta && d < 0) return `<span class="mu-badge mu-badge--rojo"><i class="bi bi-alarm"></i> Promesa vencida (${fechaMx(m.fecha_promesa)})</span>`;
  if (abierta && d <= 2) return `<span class="mu-badge mu-badge--ambar"><i class="bi bi-alarm"></i> Promesa ${d === 0 ? 'hoy' : (d === 1 ? 'mañana' : 'en ' + d + ' días')}</span>`;
  return `<span class="mu-badge"><i class="bi bi-calendar-event"></i> Promesa ${fechaMx(m.fecha_promesa)}</span>`;
}

function tarjeta(m, i) {
  return `
    <a class="mu-card mu-card--${m.estado}" href="ver.php?id=${m.id}" style="animation-delay:${Math.min(i, 10) * 0.04}s">
      <div class="mu-card-top">
        <span class="mu-card-ico"><i class="bi ${ICONO_ESTADO[m.estado] || 'bi-box'}"></i></span>
        <div class="mu-card-id">
          <span class="mu-folio">${escHtml(m.folio)}</span>
          ${m.tipo === 'variante' ? '<span class="mu-tag"><i class="bi bi-shuffle"></i> Con cambios</span>' : ''}
        </div>
        ${pillEstadoMuestra(m.estado)}
      </div>
      <div class="mu-card-titulo">${escHtml(m.estilo_nombre)}</div>
      ${(m.color || m.talla) ? `<div class="mu-card-talla">${[m.color ? `Color <strong>${escHtml(m.color)}</strong>` : '', m.talla ? `Talla <strong>${escHtml(m.talla)}</strong>` : ''].filter(Boolean).join(' · ')}</div>` : ''}
      <div class="mu-card-quien">
        <span class="mu-avatar">${escHtml(iniciales(m.vendedor_nombre))}</span>
        <div class="mu-card-quien-txt">
          <span class="mu-card-vend">${escHtml(m.vendedor_nombre)}</span>
          <span class="mu-card-cli"><i class="bi bi-building"></i> ${escHtml(m.cliente_nombre)}</span>
        </div>
      </div>
      ${m.estado === 'embarcada' ? `<div class="mu-card-envio"><i class="bi bi-truck"></i> ${escHtml(textoEnvio(m))}</div>` : ''}
      ${m.estado === 'cancelada' ? `<div class="mu-card-envio mu-card-envio--cancel"><i class="bi bi-x-circle"></i> ${escHtml(m.motivo_cancelacion || '')}</div>` : ''}
      <div class="mu-card-pie">
        <div class="mu-badges">${badgeEspera(m)}${badgePromesa(m)}</div>
        <span class="mu-abrir">${ES_ADMIN || m.estado === 'embarcada' || m.estado === 'cancelada' ? 'Ver' : 'Atender'} <i class="bi bi-arrow-right"></i></span>
      </div>
    </a>`;
}

function pintarHero(c) {
  const t = document.getElementById('hero-titulo');
  if (ES_ADMIN) { t.textContent = `${c.todas} solicitud${c.todas === 1 ? '' : 'es'} en total`; return; }
  t.innerHTML = c.nuevas === 0
    ? `Hola, ${escHtml(NOMBRE)}. No tienes solicitudes nuevas`
    : `Hola, ${escHtml(NOMBRE)}. Tienes <span class="mu-hero-n">${c.nuevas}</span> solicitud${c.nuevas === 1 ? '' : 'es'} nueva${c.nuevas === 1 ? '' : 's'}`;
}

function activarGrupo(g) {
  grupo = g;
  document.querySelectorAll('#filtro-grupo .v26-funnel-chip').forEach(c => c.classList.toggle('active', c.dataset.grupo === g));
  document.querySelectorAll('.mu-stat').forEach(c => c.classList.toggle('activa', c.dataset.grupo === g));
  cargar();
}

async function cargar() {
  const lista = document.getElementById('lista');
  try {
    const r = await fetch(`../api/muestras_bandeja.php?grupo=${grupo}&q=${encodeURIComponent(busqueda)}`, { cache: 'no-store' });
    const data = await r.json();
    if (!data.ok) throw new Error(data.error || 'Error');
    const c = data.conteos;
    Object.entries(c).forEach(([k, v]) => {
      const n = document.getElementById('n-' + k); if (n) n.textContent = v;
      const s = document.getElementById('s-' + k); if (s) s.textContent = v;
    });
    document.querySelector('.mu-stat--vencidas').classList.toggle('alerta', c.vencidas > 0);
    pintarHero(c);
    if (data.solicitudes.length) {
      lista.innerHTML = data.solicitudes.map(tarjeta).join('');
    } else {
      const [ico, tit, txt] = busqueda ? ['bi-search', 'Sin resultados', 'Nada coincide con la búsqueda.'] : VACIO[grupo];
      lista.innerHTML = `<div class="mu-vacio"><div class="mu-vacio-ico"><i class="bi ${ico}"></i></div><strong>${tit}</strong><p>${txt}</p></div>`;
    }
  } catch (e) {
    lista.innerHTML = '<div class="alert alert-danger">No se pudieron cargar las solicitudes. Revisa tu conexión e intenta de nuevo.</div>';
  }
}

document.getElementById('filtro-grupo').addEventListener('click', (e) => {
  const chip = e.target.closest('.v26-funnel-chip');
  if (chip) activarGrupo(chip.dataset.grupo);
});
document.querySelectorAll('.mu-stat').forEach(b => b.addEventListener('click', () => activarGrupo(b.dataset.grupo)));
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
activarGrupo('nuevas');
</script>
</body>
</html>
