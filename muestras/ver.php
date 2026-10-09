<?php
// Detalle de una solicitud de muestra. La responsable de muestras (rol
// 'muestras') ve aquí los botones para marcar el avance; el admin la ve
// igual pero solo para consultar. Ver includes/muestras.php.
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/muestras.php';
$u = requireAnyRole(['muestras', 'admin']);
$puedeCambiar = $u['rol'] === 'muestras';

$db = getDB();
$s = cargarSolicitudMuestra($db, (int)($_GET['id'] ?? 0));
if (!$s) {
    http_response_code(404);
    die('No se encontró la solicitud. <a href="index.php">Volver a la bandeja</a>');
}
$historial = historialSolicitudMuestra($db, (int)$s['id']);
$siguientes = MUESTRA_TRANSICIONES[$s['estado']] ?? [];

// Al abrirla, sus avisos de "nueva solicitud" quedan como leídos.
if ($puedeCambiar) {
    $db->prepare('UPDATE avisos SET leido_en = NOW() WHERE usuario_id = ? AND solicitud_muestra_id = ? AND leido_en IS NULL')
       ->execute([(int)$u['id'], (int)$s['id']]);
}

function e($v): string { return htmlspecialchars((string)$v); }
$telLimpio = preg_replace('/\D/', '', (string)$s['vendedor_telefono']);
$correoAsunto = 'Muestra ' . $s['folio'];
$correoTexto  = 'Hola ' . $s['vendedor_nombre'] . ', te escribo sobre tu solicitud de muestra ' . $s['folio']
              . ' (' . $s['estilo_nombre'] . (!empty($s['color']) ? ', ' . $s['color'] : '') . ') para ' . $s['cliente_nombre'] . ".\n\n";
$whats = $telLimpio ? 'https://wa.me/' . (strlen($telLimpio) === 10 ? '52' . $telLimpio : $telLimpio) : null;
$TXT_HISTORIAL = [
    'enviada'        => 'Solicitud enviada',
    'en_preparacion' => 'Marcada en preparación',
    'embarcada'      => 'Marcada como embarcada',
    'cancelada'      => 'Cancelada',
];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($s['folio']) ?> — Solicitud de muestra</title>
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
        <a href="index.php" class="v26-back" aria-label="Volver a la bandeja"><i class="bi bi-arrow-left"></i></a>
        <div class="v26-greeting">
          <div class="hi">Solicitud de muestra</div>
          <div class="name"><?= e($s['folio']) ?></div>
        </div>
      </div>
      <div class="v26-topbar-right">
        <img src="../logo.png" alt="Segurmex" class="v26-logo">
        <a href="../logout.php" class="v26-icon-btn v26-tip v26-tip--bottom" data-tip="Cerrar sesión" aria-label="Salir"><i class="bi bi-box-arrow-right"></i></a>
      </div>
    </div>
  </div>

  <div class="v26-wrap mu-wrap">
    <div id="msg"></div>

    <div class="mu-estado-actual mu-estado-actual--<?= e($s['estado']) ?>">
      <div>
        <div class="mu-estado-label">Estado</div>
        <div class="mu-estado-valor"><?= $s['estado'] === 'enviada' ? 'Nueva · sin atender' : e(etiquetaEstadoMuestra($s['estado'])) ?></div>
      </div>
      <?php if ($s['estado'] === 'en_preparacion' && textoPreparacionMuestra($s)): ?>
        <div class="mu-estado-extra"><i class="bi <?= $s['preparacion'] === 'pt' ? 'bi-box-seam' : 'bi-gear' ?>"></i> <?= e(textoPreparacionMuestra($s)) ?></div>
      <?php elseif ($s['estado'] === 'embarcada'): ?>
        <div class="mu-estado-extra"><i class="bi bi-truck"></i> <?= e(textoEnvioMuestra($s)) ?></div>
      <?php elseif ($s['estado'] === 'cancelada'): ?>
        <div class="mu-estado-extra"><i class="bi bi-x-circle"></i> <?= e($s['motivo_cancelacion']) ?></div>
      <?php endif; ?>
    </div>

    <div class="mu-botones mb-3">
      <a href="<?= e(urlPdfMuestra((int)$s['id'], '../')) ?>" class="v26-btn v26-btn-ghost" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf"></i> Ver PDF</a>
    </div>

    <?php if ($puedeCambiar && $siguientes): ?>
    <div class="v26-card mu-acciones">
      <div class="mu-sec-titulo">¿Qué sigue?</div>
      <div class="mu-botones">
        <?php if (in_array('en_preparacion', $siguientes, true)): ?>
          <button type="button" class="v26-btn v26-btn-ghost" data-abrir="form-preparacion"><i class="bi bi-box-seam"></i> Marcar en preparación</button>
        <?php endif; ?>
        <?php if ($s['estado'] === 'en_preparacion' && ($s['preparacion'] ?? '') === 'por_programar'): ?>
          <button type="button" class="v26-btn v26-btn-ghost" data-accion="pasar_pt"><i class="bi bi-box-seam"></i> Ya está en Producto Terminado</button>
        <?php endif; ?>
        <button type="button" class="v26-btn v26-btn-primary" data-abrir="form-embarcada"><i class="bi bi-truck"></i> Marcar embarcada</button>
        <button type="button" class="v26-btn mu-btn-cancelar" data-abrir="form-cancelar"><i class="bi bi-x-circle"></i> Cancelar</button>
      </div>

      <?php if (in_array('en_preparacion', $siguientes, true)): ?>
      <form id="form-preparacion" class="mu-form d-none">
        <div class="v26-field">
          <label>¿De dónde sale la muestra?</label>
          <div class="mu-tipos" id="seg-preparacion">
            <button type="button" class="v26-seg-btn mu-tipo active" data-prep="pt">
              <i class="bi bi-box-seam"></i><strong>En Producto Terminado</strong><small>Ya hay en almacén; solo falta preparar el envío</small>
            </button>
            <button type="button" class="v26-seg-btn mu-tipo" data-prep="por_programar">
              <i class="bi bi-gear"></i><strong>Por programar</strong><small>Hay que mandarla a fabricar</small>
            </button>
          </div>
        </div>
        <div class="v26-field d-none" id="campo-fecha-estimada">
          <label>Fecha estimada para tenerla lista <span class="mu-opc">(opcional)</span></label>
          <input type="date" id="fecha_estimada" class="v26-input">
        </div>
        <p class="mu-nota">Al guardar, a <?= e($s['vendedor_nombre']) ?> le llega un aviso que explica si ya hay en almacén o si se va a fabricar.</p>
        <button type="submit" class="v26-btn v26-btn-primary v26-btn-block">Guardar en preparación</button>
      </form>
      <?php endif; ?>

      <form id="form-embarcada" class="mu-form d-none">
        <div class="v26-field">
          <label>¿Cómo se envió?</label>
          <div class="v26-seg" id="seg-envio">
            <button type="button" class="v26-seg-btn active" data-modo="paqueteria">Por paquetería</button>
            <button type="button" class="v26-seg-btn" data-modo="en_persona">Entregada en persona</button>
          </div>
        </div>
        <div id="campos-paqueteria">
          <div class="v26-field">
            <label>Paquetería</label>
            <input type="text" id="paqueteria" class="v26-input" maxlength="80" placeholder="DHL, Estafeta, FedEx...">
          </div>
          <div class="v26-field">
            <label>Número de guía</label>
            <input type="text" id="guia" class="v26-input" maxlength="80">
          </div>
        </div>
        <p class="mu-nota">Al guardar, a <?= e($s['vendedor_nombre']) ?> le llega un aviso con estos datos.</p>
        <button type="submit" class="v26-btn v26-btn-primary v26-btn-block">Guardar como embarcada</button>
      </form>

      <form id="form-cancelar" class="mu-form d-none">
        <div class="v26-field">
          <label>Motivo de la cancelación</label>
          <textarea id="motivo" class="v26-textarea" rows="2" maxlength="500" placeholder="Ej. No hay ese estilo en esa talla"></textarea>
        </div>
        <p class="mu-nota">El vendedor verá este motivo. Una solicitud cancelada ya no se puede reabrir.</p>
        <button type="submit" class="v26-btn mu-btn-cancelar v26-btn-block">Cancelar solicitud</button>
      </form>
    </div>
    <?php endif; ?>

    <div class="v26-card">
      <div class="mu-sec-titulo">Muestra</div>
      <dl class="mu-datos">
        <dt>Estilo</dt><dd><?= e($s['estilo_nombre']) ?></dd>
        <?php if (!empty($s['color'])): ?><dt>Color</dt><dd><?= e($s['color']) ?></dd><?php endif; ?>
        <dt>Talla</dt><dd><?= $s['talla'] ? e($s['talla']) : '<span class="text-muted">No la indicó</span>' ?></dd>
        <dt>Cantidad</dt><dd><?= e(textoParesMuestra($s['cantidad'] ?? 1)) ?></dd>
        <dt>Tipo</dt><dd><?= $s['tipo'] === 'variante' ? 'Variante (con cambios)' : 'Idéntico al estilo' ?></dd>
        <?php if ($s['cambios']): ?>
        <dt>Cambios</dt>
        <dd>
          <ul class="mu-cambios">
            <?php foreach ($s['cambios'] as $c):
              $cat = ($c['categoria'] ?? '') === 'otro' ? ($c['categoria_otro'] ?? 'Otro') : (MUESTRA_CATEGORIAS_CAMBIO[$c['categoria'] ?? ''] ?? ($c['categoria'] ?? '')); ?>
              <li><strong><?= e($cat) ?>:</strong> <?= e($c['descripcion'] ?? '') ?></li>
            <?php endforeach; ?>
          </ul>
        </dd>
        <?php endif; ?>
        <dt>Motivo</dt><dd><?= !empty($s['motivo']) ? nl2br(e($s['motivo'])) : '<span class="text-muted">No lo indicó</span>' ?></dd>
        <?php if (!empty($s['notas_planta'])): ?><dt>Notas para planta</dt><dd><?= nl2br(e($s['notas_planta'])) ?></dd><?php endif; ?>
        <dt>Fecha promesa</dt><dd><?= $s['fecha_promesa'] ? e(date('d/m/Y', strtotime($s['fecha_promesa']))) : '<span class="text-muted">Sin fecha</span>' ?></dd>
        <?php if (!empty($s['tiempo_prueba_dias'])): ?><dt>Tiempo de prueba</dt><dd><?= e(textoTiempoPruebaMuestra($s['tiempo_prueba_dias'])) ?></dd><?php endif; ?>
        <dt>Entregar a</dt><dd><?= e(textoEntregarAMuestra($s)) ?></dd>
        <dt>Dirección</dt><dd><?= nl2br(e($s['destino_direccion'])) ?></dd>
      </dl>
    </div>

    <div class="v26-card">
      <div class="mu-sec-titulo">Quién la pide</div>
      <dl class="mu-datos">
        <dt>Cliente</dt><dd><?= e($s['cliente_nombre']) ?></dd>
        <dt>Contacto del cliente</dt><dd><?= !empty($s['cliente_contacto']) ? e($s['cliente_contacto']) : '<span class="text-muted">Sin registro</span>' ?></dd>
        <dt>Tel. del cliente</dt><dd><?= !empty($s['cliente_telefono']) ? '<a href="tel:' . e(preg_replace('/\D/', '', $s['cliente_telefono'])) . '">' . e($s['cliente_telefono']) . '</a>' : '<span class="text-muted">Sin registro</span>' ?></dd>
        <dt>Vendedor</dt><dd><?= e($s['vendedor_nombre']) ?></dd>
      </dl>
      <div class="mu-contacto">
        <?php if ($telLimpio): ?>
          <a class="v26-btn v26-btn-ghost" href="tel:<?= e($telLimpio) ?>"><i class="bi bi-telephone"></i> Llamar</a>
          <a class="v26-btn v26-btn-ghost" href="<?= e($whats) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> WhatsApp</a>
        <?php endif; ?>
        <?php if ($s['vendedor_email']): ?>
          <?php $mailto = urlMailto($s['vendedor_email'], $correoAsunto, $correoTexto); ?>
          <a class="v26-btn v26-btn-ghost correo-pc" href="<?= e(urlGmailRedactar($s['vendedor_email'], $correoAsunto, $correoTexto)) ?>" target="_blank" rel="noopener"><i class="bi bi-google"></i> Gmail</a>
          <a class="v26-btn v26-btn-ghost correo-pc" href="<?= e($mailto) ?>"><i class="bi bi-envelope"></i> Otro correo</a>
          <a class="v26-btn v26-btn-ghost correo-movil" href="<?= e($mailto) ?>"><i class="bi bi-envelope"></i> Correo</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="v26-card">
      <div class="mu-sec-titulo">Historial</div>
      <ol class="mu-historial">
        <?php foreach ($historial as $h): ?>
          <li class="mu-hist--<?= e($h['estado']) ?>">
            <div class="mu-hist-que"><?= e($TXT_HISTORIAL[$h['estado']] ?? $h['estado']) ?><?= $h['usuario_nombre'] ? ' · ' . e($h['usuario_nombre']) : '' ?></div>
            <?php if ($h['nota']): ?><div class="mu-hist-nota"><?= e($h['nota']) ?></div><?php endif; ?>
            <div class="mu-hist-cuando fecha-utc" data-utc="<?= e($h['creado_en']) ?>"><?= e($h['creado_en']) ?></div>
          </li>
        <?php endforeach; ?>
      </ol>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/fecha_utils.js<?= assetVer(__DIR__ . '/../assets/js/fecha_utils.js') ?>"></script>
<?php if ($puedeCambiar): ?>
<script src="../assets/js/avisos.js<?= assetVer(__DIR__ . '/../assets/js/avisos.js') ?>"></script>
<?php endif; ?>
<script>
aplicarFechasUTC();
<?php if ($puedeCambiar && $siguientes): ?>
const ID_SOLICITUD = <?= (int)$s['id'] ?>;
function escHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

async function cambiarEstado(estado, extra, boton) {
  const msg = document.getElementById('msg');
  msg.innerHTML = '';
  const fd = new FormData();
  fd.append('id', ID_SOLICITUD);
  fd.append('estado', estado);
  Object.entries(extra || {}).forEach(([k, v]) => fd.append(k, v));
  const textoOriginal = boton ? boton.innerHTML : '';
  if (boton) { boton.disabled = true; boton.textContent = 'Guardando...'; }
  try {
    const r = await fetch('../api/muestra_estado.php', { method: 'POST', body: fd });
    const data = await r.json();
    if (!data.ok) throw new Error(data.error || 'No se pudo guardar.');
    window.location.reload();
  } catch (e) {
    msg.innerHTML = `<div class="alert alert-danger py-2">${escHtml(e.message)}</div>`;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    if (boton) { boton.disabled = false; boton.innerHTML = textoOriginal; }
  }
}

// "Por programar" que ya se fabricó: pasa a "En Producto Terminado".
document.querySelectorAll('[data-accion="pasar_pt"]').forEach(b =>
  b.addEventListener('click', () => cambiarEstado('en_preparacion', { preparacion: 'pt' }, b)));

document.querySelectorAll('[data-abrir]').forEach(b => b.addEventListener('click', () => {
  ['form-preparacion', 'form-embarcada', 'form-cancelar'].forEach(id => {
    const f = document.getElementById(id);
    if (f) f.classList.toggle('d-none', id !== b.dataset.abrir || !f.classList.contains('d-none'));
  });
}));

// En preparación: En Producto Terminado o Por programar (con fecha estimada opcional).
let prepElegida = 'pt';
const formPrep = document.getElementById('form-preparacion');
if (formPrep) {
  const hoyMx = new Date().toLocaleDateString('en-CA', { timeZone: 'America/Mexico_City' });
  document.getElementById('fecha_estimada').min = hoyMx;
  document.querySelectorAll('#seg-preparacion .v26-seg-btn').forEach(b => b.addEventListener('click', () => {
    document.querySelectorAll('#seg-preparacion .v26-seg-btn').forEach(x => x.classList.toggle('active', x === b));
    prepElegida = b.dataset.prep;
    document.getElementById('campo-fecha-estimada').classList.toggle('d-none', prepElegida !== 'por_programar');
  }));
  formPrep.addEventListener('submit', (e) => {
    e.preventDefault();
    const extra = { preparacion: prepElegida };
    if (prepElegida === 'por_programar') extra.fecha_estimada = document.getElementById('fecha_estimada').value;
    cambiarEstado('en_preparacion', extra, e.submitter);
  });
}

let modoEnvio = 'paqueteria';
document.querySelectorAll('#seg-envio .v26-seg-btn').forEach(b => b.addEventListener('click', () => {
  document.querySelectorAll('#seg-envio .v26-seg-btn').forEach(x => x.classList.toggle('active', x === b));
  modoEnvio = b.dataset.modo;
  document.getElementById('campos-paqueteria').classList.toggle('d-none', modoEnvio !== 'paqueteria');
}));

document.getElementById('form-embarcada').addEventListener('submit', (e) => {
  e.preventDefault();
  const paqueteria = document.getElementById('paqueteria').value.trim();
  const guia = document.getElementById('guia').value.trim();
  if (modoEnvio === 'paqueteria' && (!paqueteria || !guia)) {
    document.getElementById('msg').innerHTML = '<div class="alert alert-danger py-2">Escribe la paquetería y el número de guía.</div>';
    return;
  }
  cambiarEstado('embarcada', { envio_modo: modoEnvio, paqueteria, guia }, e.submitter);
});

document.getElementById('form-cancelar').addEventListener('submit', (e) => {
  e.preventDefault();
  const motivo = document.getElementById('motivo').value.trim();
  if (!motivo) {
    document.getElementById('msg').innerHTML = '<div class="alert alert-danger py-2">Escribe el motivo de la cancelación.</div>';
    return;
  }
  cambiarEstado('cancelada', { motivo }, e.submitter);
});
<?php endif; ?>
</script>
</body>
</html>
