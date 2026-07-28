<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
$u = requireRole('vendedor');

$citaId = (int)($_GET['cita_id'] ?? 0);
$stmt = getDB()->prepare(
    'SELECT c.*, cl.nombre AS cliente_nombre, cl.direccion
     FROM citas c JOIN clientes cl ON cl.id = c.cliente_id
     WHERE c.id = ? AND c.vendedor_id = ?'
);
$stmt->execute([$citaId, $u['id']]);
$cita = $stmt->fetch();
if (!$cita) {
    http_response_code(404);
    die('Cita no encontrada.');
}

$checkinsStmt = getDB()->prepare('SELECT * FROM checkins WHERE cita_id = ? ORDER BY id ASC');
$checkinsStmt->execute([$citaId]);
$checkins = $checkinsStmt->fetchAll();
$tieneEntrada = false;
$tieneSalida = false;
foreach ($checkins as $ch) {
    if ($ch['tipo'] === 'entrada') $tieneEntrada = true;
    if ($ch['tipo'] === 'salida') $tieneSalida = true;
}

$esFuturo = strtotime($cita['fecha_hora']) > time();
$estadoResuelto = in_array($cita['estado'], ['cancelada', 'no_realizada', 'completada'], true);
$puedeCancelar = $cita['estado'] === 'pendiente' && !$tieneEntrada;
$siguienteTipo = (!$estadoResuelto && !$esFuturo) ? (!$tieneEntrada ? 'entrada' : (!$tieneSalida ? 'salida' : null)) : null;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Check-in de visita</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/vendedor-2026.css">
</head>
<body class="v26">
  <div class="v26-header">
    <div class="v26-topbar">
      <div class="v26-topbar-left">
        <a href="index.php" class="v26-back v26-tip v26-tip--bottom" data-tip="Volver a mis visitas" aria-label="Volver"><i class="bi bi-arrow-left"></i></a>
        <div class="v26-greeting">
          <div class="hi">Visita</div>
          <div class="name"><?= htmlspecialchars($cita['cliente_nombre']) ?></div>
        </div>
      </div>
      <div class="v26-topbar-right">
        <img src="../logo.png" alt="Segurmex" class="v26-logo">
      </div>
    </div>
  </div>

  <div class="v26-wrap">
    <div class="v26-card mb-3">
      <div class="cliente" style="font-weight:700;font-size:.95rem;"><?= htmlspecialchars($cita['cliente_nombre']) ?></div>
      <div class="direccion" style="font-size:.8rem;color:var(--v26-ink-soft);"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($cita['direccion']) ?></div>
      <div class="hora" style="font-size:.8rem;color:var(--v26-ink-soft);margin-top:4px;"><i class="bi bi-clock"></i> <span class="fecha-cita" data-utc="<?= htmlspecialchars($cita['fecha_hora']) ?>"><?= htmlspecialchars($cita['fecha_hora']) ?></span></div>
      <?php if ($cita['motivo']): ?>
        <div class="v26-motivo">"<?= htmlspecialchars($cita['motivo']) ?>"</div>
      <?php endif; ?>
    </div>

    <?php foreach ($checkins as $ch): ?>
      <div class="v26-card mb-2" style="padding:12px 14px;">
        <strong style="font-size:.85rem;"><?= $ch['tipo'] === 'entrada' ? 'Entrada' : 'Salida' ?> registrada</strong>
        <span class="v26-pill <?= $ch['verificado'] ? 'v26-pill--verificado' : 'v26-pill--noverificado' ?>" style="margin-left:6px;"><?= $ch['verificado'] ? 'GPS verificado' : 'Fuera de zona' ?></span>
        <div style="font-size:.76rem;color:var(--v26-ink-soft);margin-top:4px;">
          <?= $ch['distancia_metros'] !== null ? 'Distancia al cliente: ' . round($ch['distancia_metros']) . ' m' : 'Cliente sin coordenadas registradas' ?>
        </div>
      </div>
    <?php endforeach; ?>

    <?php if ($esFuturo): ?>
      <div class="v26-empty">
        <div class="icon"><i class="bi bi-hourglass-split"></i></div>
        <p>Esta visita todavía no llega a su hora. Podrás hacer check-in en cuanto sea el momento.</p>
      </div>
    <?php elseif ($cita['estado'] === 'cancelada'): ?>
      <div class="v26-empty">
        <div class="icon"><i class="bi bi-x-circle"></i></div>
        <p>Esta cita fue cancelada.</p>
      </div>
    <?php elseif ($cita['estado'] === 'no_realizada'): ?>
      <div class="v26-empty">
        <div class="icon"><i class="bi bi-info-circle"></i></div>
        <p>Esta visita quedó marcada como "cliente no llegó".</p>
      </div>
    <?php elseif ($siguienteTipo): ?>
      <div class="v26-card">
        <h6 class="mb-3">Registrar <?= $siguienteTipo === 'entrada' ? 'entrada' : 'salida' ?></h6>
        <div class="mb-3">
          <div id="estado-gps" class="small text-muted">Obteniendo tu ubicación GPS...</div>
        </div>
        <div class="mb-3">
          <label class="v26-field label" style="display:block;font-size:.78rem;font-weight:700;color:var(--v26-ink-soft);margin-bottom:6px;">Foto de evidencia (se toma aquí mismo, en el lugar de la visita)</label>
          <div class="v26-camera">
            <video id="video-camara" autoplay playsinline muted></video>
            <img id="preview-foto" class="d-none">
            <div class="frame"></div>
            <div class="v26-shutter-wrap">
              <button type="button" id="btn-tomar-foto" class="v26-shutter" disabled></button>
              <button type="button" id="btn-repetir-foto" class="v26-retake d-none"><i class="bi bi-arrow-counterclockwise"></i></button>
            </div>
          </div>
          <canvas id="canvas-foto" class="d-none"></canvas>
          <div id="estado-camara" class="small text-muted mt-2">Activando la cámara...</div>
        </div>
        <div id="msg-checkin"></div>
        <button id="btn-registrar" class="v26-btn v26-btn-primary v26-btn-block" disabled>Obteniendo GPS...</button>

        <?php if ($siguienteTipo === 'entrada'): ?>
          <button type="button" id="btn-no-show" class="v26-btn v26-btn-ghost v26-btn-block mt-2">El cliente no llegó</button>
          <div id="aviso-no-show" class="small text-muted mt-1" style="display:none;"></div>
        <?php endif; ?>

        <?php if ($puedeCancelar): ?>
          <button type="button" id="btn-cancelar" class="v26-link-cancelar mt-2">Cancelar cita</button>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="v26-empty">
        <div class="icon"><i class="bi bi-check-circle"></i></div>
        <p>Esta visita ya quedó completa (entrada y salida registradas).</p>
      </div>
    <?php endif; ?>
  </div>

<script src="../assets/js/fecha_utils.js"></script>
<script src="../assets/js/v26-modal.js"></script>
<script>
const citaId = <?= (int)$citaId ?>;
const tipo = <?= json_encode($siguienteTipo) ?>;
const fechaCitaStr = <?= json_encode($cita['fecha_hora']) ?>;
const ESPERA_NO_SHOW_MIN = 10;
let lat = null, lng = null;
let fotoBlob = null;
let streamCamara = null;
let esReporteNoShow = false;

const estadoGps = document.getElementById('estado-gps');
const btn = document.getElementById('btn-registrar');

if (tipo && 'geolocation' in navigator) {
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      lat = pos.coords.latitude;
      lng = pos.coords.longitude;
      estadoGps.innerHTML = `<i class="bi bi-geo-alt-fill"></i> Ubicación obtenida (precisión ±${Math.round(pos.coords.accuracy)} m)`;
      revisarListoParaEnviar();
    },
    () => {
      estadoGps.innerHTML = '⚠️ No se pudo obtener tu ubicación. Activa el GPS y los permisos de ubicación del navegador.';
    },
    { enableHighAccuracy: true, timeout: 20000 }
  );
} else if (tipo) {
  estadoGps.innerHTML = 'Tu navegador no soporta geolocalización.';
}

function revisarListoParaEnviar() {
  if (!btn) return;
  if (lat && lng && fotoBlob) {
    btn.disabled = false;
    btn.textContent = esReporteNoShow ? 'Confirmar: cliente no llegó' : 'Registrar ' + (tipo === 'entrada' ? 'entrada' : 'salida');
  } else {
    btn.disabled = true;
    btn.textContent = !lat ? 'Obteniendo GPS...' : 'Toma la foto para continuar';
  }
}

// --- Cámara en vivo ---
const video          = document.getElementById('video-camara');
const preview         = document.getElementById('preview-foto');
const canvas          = document.getElementById('canvas-foto');
const estadoCamara     = document.getElementById('estado-camara');
const btnTomarFoto     = document.getElementById('btn-tomar-foto');
const btnRepetirFoto   = document.getElementById('btn-repetir-foto');

async function iniciarCamara() {
  if (!tipo) return;
  if (!('mediaDevices' in navigator) || !navigator.mediaDevices.getUserMedia) {
    estadoCamara.innerHTML = '⚠️ Tu navegador no soporta acceso a la cámara.';
    return;
  }
  try {
    streamCamara = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'environment' },
      audio: false,
    });
    video.srcObject = streamCamara;
    estadoCamara.textContent = 'Encuadra la evidencia y presiona el botón.';
    if (btnTomarFoto) btnTomarFoto.disabled = false;
  } catch (e) {
    estadoCamara.innerHTML = '⚠️ No se pudo acceder a la cámara. Revisa los permisos del navegador y vuelve a intentar.';
  }
}

if (btnTomarFoto) {
  btnTomarFoto.addEventListener('click', () => {
    const w = video.videoWidth || 640;
    const h = video.videoHeight || 480;
    canvas.width = w;
    canvas.height = h;
    canvas.getContext('2d').drawImage(video, 0, 0, w, h);
    canvas.toBlob((blob) => {
      fotoBlob = blob;
      preview.src = URL.createObjectURL(blob);
      preview.classList.remove('d-none');
      video.classList.add('d-none');
      btnTomarFoto.classList.add('d-none');
      btnRepetirFoto.classList.remove('d-none');
      revisarListoParaEnviar();
    }, 'image/jpeg', 0.85);
  });
}

if (btnRepetirFoto) {
  btnRepetirFoto.addEventListener('click', () => {
    fotoBlob = null;
    preview.classList.add('d-none');
    video.classList.remove('d-none');
    btnRepetirFoto.classList.add('d-none');
    btnTomarFoto.classList.remove('d-none');
    revisarListoParaEnviar();
  });
}

iniciarCamara();

async function enviarCheckin(motivoNoShow) {
  const msg = document.getElementById('msg-checkin');
  msg.innerHTML = '';
  if (!lat || !lng) { msg.innerHTML = '<div class="alert alert-danger py-2">Espera a que se obtenga tu ubicación GPS.</div>'; return; }
  if (!fotoBlob) { msg.innerHTML = '<div class="alert alert-danger py-2">Toma la foto de evidencia.</div>'; return; }

  btn.disabled = true;
  btn.textContent = 'Enviando...';
  const fd = new FormData();
  fd.append('cita_id', citaId);
  fd.append('tipo', tipo);
  fd.append('lat', lat);
  fd.append('lng', lng);
  fd.append('foto', fotoBlob, 'evidencia.jpg');
  if (motivoNoShow !== undefined) {
    fd.append('no_show', '1');
    fd.append('motivo', motivoNoShow);
  }

  try {
    const res = await fetch('../api/checkin.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) {
      if (streamCamara) streamCamara.getTracks().forEach(t => t.stop());
      window.location.reload();
    } else {
      msg.innerHTML = `<div class="alert alert-danger py-2">${data.error}</div>`;
      btn.disabled = false;
      revisarListoParaEnviar();
    }
  } catch (e) {
    msg.innerHTML = '<div class="alert alert-danger py-2">Error de conexión. Intenta de nuevo.</div>';
    btn.disabled = false;
    revisarListoParaEnviar();
  }
}

if (btn) {
  btn.addEventListener('click', () => enviarCheckin(esReporteNoShow ? window.__motivoNoShow : undefined));
}

// --- "El cliente no llegó": espera 10 min desde la hora programada ---
const btnNoShow = document.getElementById('btn-no-show');
const avisoNoShow = document.getElementById('aviso-no-show');

function minutosFaltantesParaNoShow() {
  const programada = new Date(fechaCitaStr.replace(' ', 'T'));
  const minutosPasados = (Date.now() - programada.getTime()) / 60000;
  return Math.max(0, Math.ceil(ESPERA_NO_SHOW_MIN - minutosPasados));
}

if (btnNoShow) {
  function actualizarEstadoBotonNoShow() {
    const faltan = minutosFaltantesParaNoShow();
    if (faltan > 0) {
      btnNoShow.disabled = true;
      avisoNoShow.style.display = 'block';
      avisoNoShow.textContent = `Podrás reportar esto en ${faltan} minuto(s), cuando ya haya pasado tiempo suficiente desde la hora programada.`;
    } else {
      btnNoShow.disabled = false;
      avisoNoShow.style.display = 'none';
    }
  }
  actualizarEstadoBotonNoShow();
  setInterval(actualizarEstadoBotonNoShow, 15000);

  btnNoShow.addEventListener('click', async () => {
    const respuesta = await v26Sheet({
      titulo: '¿El cliente no llegó?',
      desc: 'Cuéntanos brevemente qué pasó. Igual necesitamos tu foto y GPS como evidencia de que sí llegaste.',
      pedirMotivo: true,
      motivoRequerido: true,
      placeholderMotivo: 'Ej. Esperé 15 min y no contestó el teléfono',
      textoConfirmar: 'Continuar',
      textoCancelar: 'Volver',
    });
    if (!respuesta) return;
    esReporteNoShow = true;
    window.__motivoNoShow = respuesta.motivo;
    document.querySelector('h6.mb-3').textContent = 'Evidencia: el cliente no llegó';
    btnNoShow.classList.add('d-none');
    revisarListoParaEnviar();
  });
}

// --- Cancelar cita (solo si sigue pendiente y sin entrada registrada) ---
const btnCancelar = document.getElementById('btn-cancelar');
if (btnCancelar) {
  btnCancelar.addEventListener('click', async () => {
    const respuesta = await v26Sheet({
      titulo: '¿Cancelar esta cita?',
      desc: 'Esta acción no se puede deshacer.',
      pedirMotivo: true,
      motivoRequerido: false,
      placeholderMotivo: 'Motivo (opcional)',
      textoConfirmar: 'Sí, cancelar',
      textoCancelar: 'No, volver',
    });
    if (!respuesta) return;
    const fd = new FormData();
    fd.append('cita_id', citaId);
    fd.append('motivo', respuesta.motivo);
    try {
      const res = await fetch('../api/cancelar_cita.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.ok) {
        window.location.href = 'index.php';
      } else {
        alert(data.error || 'No se pudo cancelar la cita.');
      }
    } catch (e) {
      alert('Error de conexión. Intenta de nuevo.');
    }
  });
}
</script>
</body>
</html>
