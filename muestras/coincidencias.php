<?php
// Coincidencias de clientes entre vendedores, para la responsable de muestras
// (rol 'muestras') y, desde el 12-nov-2026, Sistemas/Dirección (rol 'admin'):
// decide si son distintos, se queda con quien ya lo tenía o pasa a quien lo
// registró. Datos: api/muestras_coincidencias.php; lógica: includes/coincidencias.php.
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
<title>Coincidencias de clientes</title>
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
          <div class="hi">Control de Visitas</div>
          <div class="name">Coincidencias de clientes</div>
        </div>
      </div>
      <div class="v26-topbar-right">
        <img src="../logo.png" alt="Segurmex" class="v26-logo">
        <a href="../logout.php" class="v26-icon-btn v26-tip v26-tip--bottom" data-tip="Cerrar sesión" aria-label="Salir"><i class="bi bi-box-arrow-right"></i></a>
      </div>
    </div>
  </div>

  <div class="v26-wrap mu-wrap mu-wrap--bandeja">
    <nav class="mu-tabs" aria-label="Secciones">
      <div class="mu-tabs-in">
        <a class="mu-tab" href="index.php"><i class="bi bi-box-seam"></i> Muestras</a>
        <?php if (!$esAdmin): ?>
        <a class="mu-tab" href="cotizaciones.php"><i class="bi bi-file-earmark-text"></i> Cotizaciones</a>
        <a class="mu-tab" href="clientes.php"><i class="bi bi-people"></i> Clientes</a>
        <?php endif; ?>
        <a class="mu-tab activa" href="coincidencias.php" aria-current="page"><i class="bi bi-intersect"></i> Coincidencias</a>
      </div>
    </nav>

    <section class="mu-hero">
      <div class="mu-hero-txt">
        <div class="mu-hero-eyebrow"><i class="bi bi-intersect"></i> Clientes repetidos entre vendedores</div>
        <h1 class="mu-hero-titulo" id="hero-titulo">Cargando...</h1>
        <p class="mu-hero-sub">Cuando un vendedor registra un cliente que parece ser el de otro vendedor del mismo municipio, queda en revisión y no lo puede trabajar hasta que decidas aquí. La misma empresa en otro municipio (sucursal) no se marca.</p>
      </div>
    </section>

    <div class="mu-toolbar">
      <div class="v26-funnel-filtro mb-0" id="filtro-estado">
        <div class="v26-funnel-chip active" data-estado="pendientes">Por validar <span class="n" id="n-pendientes">0</span></div>
        <div class="v26-funnel-chip" data-estado="resueltas">Resueltas</div>
      </div>
    </div>

    <div id="lista" class="mu-vends">
      <div class="v26-skel"></div>
      <div class="v26-skel"></div>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/avisos.js<?= assetVer(__DIR__ . '/../assets/js/avisos.js') ?>"></script>
<script src="../assets/js/muestras.js<?= assetVer(__DIR__ . '/../assets/js/muestras.js') ?>"></script>
<script src="../assets/js/v26-modal.js<?= assetVer(__DIR__ . '/../assets/js/v26-modal.js') ?>"></script>
<script>
const NOMBRE = <?= json_encode(strtok($u['nombre'], ' ')) ?>;
const ETAPAS = {
  prospecto_agregado: 'Prospecto', contacto_establecido: 'Contacto establecido', reunion_presentacion: 'Reunión de presentación',
  propuesta_enviada: 'Propuesta enviada', convertido: 'Convertido', perdido: 'Perdido',
};
let estado = 'pendientes';
let grupos = [];

function fechaMx(valor) {
  if (!valor) return '';
  const d = new Date(String(valor).replace(' ', 'T') + (/[zZ]|[+-]\d{2}:?\d{2}$/.test(valor) ? '' : 'Z'));
  return isNaN(d) ? '' : d.toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'America/Mexico_City' });
}

function tarjeta(c, titulo, extra = '') {
  const etapa = c.etapa || 'prospecto_agregado';
  const datos = [
    `<span><i class="bi bi-person-badge"></i> ${escHtml(c.vendedor)}</span>`,
    `<span><i class="bi bi-geo-alt"></i> ${escHtml(c.direccion || '')}</span>`,
    c.contacto ? `<span><i class="bi bi-person"></i> ${escHtml(c.contacto)}</span>` : '',
    c.telefono ? `<a href="tel:${escHtml(c.telefono)}"><i class="bi bi-telephone"></i> ${escHtml(c.telefono)}</a>` : '',
    c.creado ? `<span><i class="bi bi-calendar-plus"></i> Registrado ${fechaMx(c.creado)}</span>` : '',
    extra,
  ].filter(Boolean).join('');
  return `
    <div class="mu-cli">
      <div class="mu-cli-top">
        <span class="mu-cli-nom"><small class="text-muted d-block">${escHtml(titulo)}</small>${escHtml(c.nombre)}</span>
        <span class="v26-pill v26-pill--etapa-${escHtml(etapa)}">${escHtml(ETAPAS[etapa] || etapa)}</span>
      </div>
      <div class="mu-cli-datos">${datos}</div>
    </div>`;
}

function bloquePendiente(g, i) {
  const n = g.nuevo;
  const filas = g.coincidencias.map(c => {
    const extra = `<span><i class="bi bi-calendar-check"></i> ${c.visitas} ${c.visitas === 1 ? 'visita' : 'visitas'}${c.citas_pendientes ? ' · ' + c.citas_pendientes + ' cita(s) agendada(s)' : ''}</span>`;
    return `
      <div class="mu-cli" style="border-left:3px solid #E8A400">
        <div class="mu-cli-datos"><span><i class="bi bi-exclamation-triangle"></i> <b>${escHtml(c.razon)}</b></span></div>
      </div>
      ${tarjeta(c, 'Cliente que ya tenía ' + c.vendedor, extra)}
      <div class="d-flex flex-wrap gap-2 px-3 pb-3">
        <button type="button" class="v26-btn v26-btn-ghost" data-accion="se_queda_existente" data-nuevo="${n.id}" data-existente="${c.id}">
          <i class="bi bi-person-check"></i> Se queda con ${escHtml(c.vendedor)}</button>
        <button type="button" class="v26-btn v26-btn-ghost" data-accion="pasa_a_nuevo" data-nuevo="${n.id}" data-existente="${c.id}">
          <i class="bi bi-arrow-left-right"></i> Pasa a ${escHtml(n.vendedor)}</button>
      </div>`;
  }).join('');
  return `
    <details class="mu-vend" open style="animation-delay:${Math.min(i, 10) * 0.04}s">
      <summary class="mu-vend-cab">
        <span class="mu-vend-txt">
          <span class="mu-vend-nom">${escHtml(n.nombre)}</span>
          <span class="mu-vend-mail">${escHtml(n.vendedor)} · ${escHtml(n.municipio || '')} · ${g.origen === 'edicion' ? 'al editar' : 'alta'} ${fechaMx(g.creado_en)}</span>
        </span>
        <span class="mu-badges"><span class="mu-badge mu-badge--ambar"><i class="bi bi-hourglass-split"></i> Por validar</span></span>
        <i class="bi bi-chevron-down mu-vend-flecha"></i>
      </summary>
      <div class="mu-vend-cuerpo">
        ${tarjeta(n, 'Lo registró ' + n.vendedor + ' (en revisión)')}
        ${filas}
        <div class="px-3 pb-3">
          <button type="button" class="v26-btn v26-btn-primary" data-accion="distintos" data-nuevo="${n.id}">
            <i class="bi bi-check2-circle"></i> Son clientes distintos (liberar a ${escHtml(n.vendedor)})</button>
        </div>
      </div>
    </details>`;
}

function bloqueResuelta(g) {
  const n = g.nuevo, c = g.coincidencias[0];
  return `
    <div class="mu-cli">
      <div class="mu-cli-top">
        <span class="mu-cli-nom">${escHtml(n.nombre)} <small class="text-muted">(${escHtml(n.vendedor)})</small> ↔ ${escHtml(c.nombre)} <small class="text-muted">(${escHtml(c.vendedor)})</small></span>
        <span class="v26-pill">${escHtml(c.decision || c.estado)}</span>
      </div>
      <div class="mu-cli-datos">
        <span><i class="bi bi-exclamation-triangle"></i> ${escHtml(c.razon)}</span>
        <span><i class="bi bi-person-check"></i> ${escHtml(c.resuelto_por || '')} · ${fechaMx(c.resuelto_en)}</span>
        ${c.nota ? `<span><i class="bi bi-chat-left-text"></i> ${escHtml(c.nota)}</span>` : ''}
      </div>
    </div>`;
}

function pintar(pendientes) {
  document.getElementById('n-pendientes').textContent = pendientes;
  document.getElementById('hero-titulo').innerHTML = pendientes
    ? `Hola, ${escHtml(NOMBRE)}. Tienes <span class="mu-hero-n">${pendientes}</span> ${pendientes === 1 ? 'coincidencia' : 'coincidencias'} por validar`
    : `Hola, ${escHtml(NOMBRE)}. No hay coincidencias por validar`;
  const cont = document.getElementById('lista');
  if (!grupos.length) {
    cont.innerHTML = `<div class="mu-vacio"><div class="mu-vacio-ico"><i class="bi bi-intersect"></i></div><strong>${estado === 'pendientes' ? 'Todo al día' : 'Sin historial'}</strong><p>${estado === 'pendientes' ? 'No hay clientes en revisión.' : 'Todavía no se ha resuelto ninguna coincidencia.'}</p></div>`;
    return;
  }
  cont.innerHTML = estado === 'pendientes' ? grupos.map(bloquePendiente).join('') : grupos.map(bloqueResuelta).join('');
}

async function cargar() {
  try {
    const r = await fetch('../api/muestras_coincidencias.php?estado=' + estado, { cache: 'no-store' });
    const data = await r.json();
    if (!data.ok) throw new Error(data.error || 'Error');
    grupos = data.grupos;
    pintar(data.pendientes);
  } catch (e) {
    document.getElementById('lista').innerHTML = '<div class="alert alert-danger">No se pudieron cargar las coincidencias. Revisa tu conexión e intenta de nuevo.</div>';
  }
}

const TEXTOS = {
  distintos: (n) => ({ titulo: 'Son clientes distintos', desc: `${n.vendedor} podrá trabajar a "${n.nombre}" normalmente y esta pareja ya no se volverá a marcar.`, boton: 'Liberar' }),
  se_queda_existente: (n, c) => ({ titulo: `Se queda con ${c.vendedor}`, desc: `El registro de ${n.vendedor} quedará descartado (no se borra, queda como evidencia).`, boton: 'Descartar registro nuevo' }),
  pasa_a_nuevo: (n, c) => ({ titulo: `Pasa a ${n.vendedor}`, desc: `"${c.nombre}" pasará a la cartera de ${n.vendedor} y su registro duplicado quedará descartado. ${c.vendedor} conserva su historial y sus citas ya agendadas (${c.citas_pendientes}), pero ya no podrá reprogramarlas, agendar, cotizar ni pedir muestras. En el ERP también pasa a ${n.vendedor}: si tenía un enlace de registro sin llenar se cancela (${n.vendedor} tendrá que enviar uno nuevo); si ya lo llenó, queda con ${n.vendedor}; y si ya es cliente, cambia de agente.`, boton: 'Reasignar' }),
};

document.getElementById('lista').addEventListener('click', async (e) => {
  const b = e.target.closest('[data-accion]');
  if (!b) return;
  const g = grupos.find(x => String(x.nuevo.id) === b.dataset.nuevo);
  if (!g) return;
  const c = g.coincidencias.find(x => String(x.id) === b.dataset.existente) || g.coincidencias[0];
  const t = TEXTOS[b.dataset.accion](g.nuevo, c);
  const r = await v26Sheet({ titulo: t.titulo, desc: t.desc, pedirMotivo: true, placeholderMotivo: 'Nota para el vendedor (opcional)', textoConfirmar: t.boton, textoCancelar: 'Volver' });
  if (!r) return;
  const fd = new FormData();
  fd.append('cliente_nuevo_id', g.nuevo.id);
  fd.append('decision', b.dataset.accion);
  if (b.dataset.existente) fd.append('existente_id', b.dataset.existente);
  fd.append('nota', r.motivo || '');
  b.disabled = true;
  try {
    const res = await fetch('../api/muestras_coincidencias.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.ok) alert(data.error || 'No se pudo guardar la decisión.');
  } catch (err) {
    alert('No se pudo guardar la decisión. Revisa tu conexión.');
  }
  cargar();
});

document.getElementById('filtro-estado').addEventListener('click', (e) => {
  const chip = e.target.closest('.v26-funnel-chip');
  if (!chip) return;
  estado = chip.dataset.estado;
  document.querySelectorAll('#filtro-estado .v26-funnel-chip').forEach(x => x.classList.toggle('active', x === chip));
  cargar();
});
setInterval(() => { if (!document.hidden) cargar(); }, 120000);
cargar();
</script>
</body>
</html>
