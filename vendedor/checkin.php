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
$siguienteTipo = !$tieneEntrada ? 'entrada' : (!$tieneSalida ? 'salida' : null);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Check-in de visita</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-dark" style="background:#111827">
  <div class="container-fluid">
    <a href="index.php" class="navbar-brand navbar-brand-custom text-decoration-none">← Control de Visitas</a>
  </div>
</nav>
<div class="container py-4" style="max-width:520px">
  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <h6 class="mb-1"><?= htmlspecialchars($cita['cliente_nombre']) ?></h6>
      <div class="text-muted small mb-1"><?= htmlspecialchars($cita['direccion']) ?></div>
      <div class="text-muted small">🕒 <?= htmlspecialchars($cita['fecha_hora']) ?></div>
    </div>
  </div>

  <?php foreach ($checkins as $ch): ?>
    <div class="alert <?= $ch['verificado'] ? 'alert-success' : 'alert-warning' ?> py-2">
      <strong><?= $ch['tipo'] === 'entrada' ? 'Entrada' : 'Salida' ?> registrada</strong> — <?= htmlspecialchars($ch['fecha_hora']) ?><br>
      <?= $ch['distancia_metros'] !== null ? 'Distancia al cliente: ' . round($ch['distancia_metros']) . ' m' : 'Cliente sin coordenadas registradas' ?>
      — <?= $ch['verificado'] ? '✅ Verificado' : '⚠️ Fuera de zona' ?>
    </div>
  <?php endforeach; ?>

  <?php if ($siguienteTipo): ?>
    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="mb-3">Registrar <?= $siguienteTipo === 'entrada' ? 'entrada' : 'salida' ?></h6>
        <div class="mb-3">
          <div id="estado-gps" class="small text-muted">Obteniendo tu ubicación GPS...</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Foto de evidencia (se toma aquí mismo, en el lugar de la visita — no se puede subir una foto existente)</label>
          <div id="camara-wrap" class="border rounded overflow-hidden bg-dark position-relative" style="aspect-ratio:4/3">
            <video id="video-camara" autoplay playsinline muted class="w-100 h-100" style="object-fit:cover"></video>
            <img id="preview-foto" class="w-100 h-100 d-none" style="object-fit:cover">
          </div>
          <canvas id="canvas-foto" class="d-none"></canvas>
          <div id="estado-camara" class="small text-muted mt-1">Activando la cámara...</div>
          <div class="d-flex gap-2 mt-2">
            <button type="button" id="btn-tomar-foto" class="btn btn-outline-secondary w-100" disabled>📷 Tomar foto</button>
            <button type="button" id="btn-repetir-foto" class="btn btn-outline-secondary w-100 d-none">🔄 Repetir foto</button>
          </div>
        </div>
        <div id="msg-checkin"></div>
        <button id="btn-registrar" class="btn btn-brand w-100" disabled>Obteniendo GPS...</button>
      </div>
    </div>
  <?php else: ?>
    <p class="text-muted">Esta visita ya quedó completa (entrada y salida registradas).</p>
  <?php endif; ?>
</div>

<script>
const citaId = <?= (int)$citaId ?>;
const tipo = '<?= $siguienteTipo ?>';
let lat = null, lng = null;
let fotoBlob = null;
let streamCamara = null;

const estadoGps = document.getElementById('estado-gps');
const btn = document.getElementById('btn-registrar');

if (tipo && 'geolocation' in navigator) {
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      lat = pos.coords.latitude;
      lng = pos.coords.longitude;
      estadoGps.innerHTML = `📍 Ubicación obtenida (precisión ±${Math.round(pos.coords.accuracy)} m)`;
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
    btn.textContent = 'Registrar ' + (tipo === 'entrada' ? 'entrada' : 'salida');
  } else {
    btn.disabled = true;
    btn.textContent = !lat ? 'Obteniendo GPS...' : 'Toma la foto para continuar';
  }
}

// --- Cámara en vivo (sin selector de archivos: la foto se captura aquí mismo) ---
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
    estadoCamara.textContent = 'Encuadra la evidencia y presiona "Tomar foto".';
    btnTomarFoto.disabled = false;
  } catch (e) {
    estadoCamara.innerHTML = '⚠️ No se pudo acceder a la cámara. Revisa los permisos del navegador y vuelve a intentar.';
  }
}

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

btnRepetirFoto.addEventListener('click', () => {
  fotoBlob = null;
  preview.classList.add('d-none');
  video.classList.remove('d-none');
  btnRepetirFoto.classList.add('d-none');
  btnTomarFoto.classList.remove('d-none');
  revisarListoParaEnviar();
});

iniciarCamara();

if (btn) {
  btn.addEventListener('click', async () => {
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

    try {
      const res = await fetch('../api/checkin.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.ok) {
        if (streamCamara) streamCamara.getTracks().forEach(t => t.stop());
        window.location.reload();
      } else {
        msg.innerHTML = `<div class="alert alert-danger py-2">${data.error}</div>`;
        btn.disabled = false;
        btn.textContent = 'Reintentar';
      }
    } catch (e) {
      msg.innerHTML = '<div class="alert alert-danger py-2">Error de conexión. Intenta de nuevo.</div>';
      btn.disabled = false;
      btn.textContent = 'Reintentar';
    }
  });
}
</script>
</body>
</html>
