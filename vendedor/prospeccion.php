<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$u = requireRole('vendedor');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Prospección — Control de Visitas</title>
<link rel="icon" type="image/png" href="../assets/img/Favv-Segu.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css<?= assetVer(__DIR__ . '/../assets/css/style.css') ?>">
<link rel="stylesheet" href="../assets/css/vendedor-2026.css<?= assetVer(__DIR__ . '/../assets/css/vendedor-2026.css') ?>">
<style>
  #seg-tipo-parada { display: flex; width: 100%; }
  #seg-tipo-parada .v26-seg-btn { flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 5px; }
</style>
</head>
<body class="v26">
  <div class="v26-header">
    <div class="v26-topbar">
      <div class="v26-topbar-left">
        <a href="index.php" class="v26-back" aria-label="Volver"><i class="bi bi-arrow-left"></i></a>
        <div class="v26-greeting">
          <div class="hi">Control de visitas</div>
          <div class="name">Prospección</div>
        </div>
      </div>
      <div class="v26-topbar-right">
        <img src="../logo.png" alt="Segurmex" class="v26-logo">
      </div>
    </div>
  </div>

  <div class="v26-wrap">

    <!-- Se llena por JS según haya o no una parada abierta -->
    <div id="banner-parada-abierta" class="v26-banner v26-banner--ok mb-3 d-none">
      <i class="bi bi-signpost-2-fill icon"></i>
      <div class="txt">
        <strong id="txt-parada-nombre"></strong>
        <p id="txt-parada-detalle"></p>
      </div>
    </div>

    <!-- ---------- Formulario: nueva parada (persona/empresa + dirección) ---------- -->
    <div class="v26-card mb-3" id="form-nueva-parada">
      <h6 class="mb-3">Nueva parada</h6>
      <div class="v26-field">
        <label>¿Qué estás visitando?</label>
        <div class="v26-seg" id="seg-tipo-parada">
          <button type="button" class="v26-seg-btn active" data-tipo="organizacion"><i class="bi bi-building"></i> Empresa</button>
          <button type="button" class="v26-seg-btn" data-tipo="persona"><i class="bi bi-person"></i> Persona</button>
        </div>
        <input type="hidden" id="tipo-parada" value="organizacion">
      </div>
      <div class="v26-field">
        <label>Nombre</label>
        <input type="text" id="nombre-parada" class="v26-input" placeholder="Ej. Ferretería López">
      </div>
      <div class="v26-field">
        <label>Dirección (opcional)</label>
        <input type="text" id="direccion-parada" class="v26-input" placeholder="Calle, colonia, referencia...">
      </div>
    </div>

    <!-- ---------- Cámara + GPS, compartida para entrada y salida ---------- -->
    <div class="v26-card" id="card-camara">
      <h6 class="mb-3" id="titulo-camara">Registrar entrada</h6>
      <div class="mb-3">
        <div id="estado-gps" class="small text-muted">Obteniendo tu ubicación GPS...</div>
      </div>
      <div class="mb-3">
        <label class="v26-field label" style="display:block;font-size:.78rem;font-weight:700;color:var(--v26-ink-soft);margin-bottom:6px;" id="label-foto">Foto de evidencia (se toma aquí mismo, en el lugar)</label>
        <div class="v26-camera" id="v26-camera-container">
          <video id="video-camara" autoplay playsinline muted></video>
          <img id="preview-foto" class="d-none" alt="Evidencia">
          <div id="camara-placeholder" class="v26-camera-placeholder d-none">
            <i class="bi bi-camera-fill"></i>
            <span>Toca aquí para tomar foto con la cámara del celular</span>
          </div>
          <div class="frame"></div>
          <div class="v26-shutter-wrap">
            <button type="button" id="btn-tomar-foto" class="v26-shutter" disabled title="Tomar foto"></button>
            <button type="button" id="btn-repetir-foto" class="v26-retake d-none" title="Repetir foto"><i class="bi bi-arrow-counterclockwise"></i></button>
          </div>
        </div>
        <canvas id="canvas-foto" class="d-none"></canvas>
        <div id="estado-camara" class="small text-muted mt-2">Activando la cámara...</div>

        <input type="file" id="input-archivo-foto" accept="image/*" capture="environment" class="d-none">
        <button type="button" id="btn-abrir-archivo" class="v26-btn v26-btn-ghost v26-btn-block mt-2" style="font-size:.82rem;">
          <i class="bi bi-camera me-1"></i> <span id="txt-btn-archivo">Tomar con cámara del celular / Subir foto</span>
        </button>
      </div>

      <!-- Solo aplica al cerrar la parada (salida) -->
      <div class="mb-3 d-none" id="bloque-interes">
        <label class="v26-field label" style="display:block;font-size:.78rem;font-weight:700;color:var(--v26-ink-soft);margin-bottom:6px;">¿Qué tan interesado se mostró?</label>
        <div class="v26-chip-group" id="chips-interes">
          <button type="button" class="v26-chip" data-interes="bajo">Poco interesado</button>
          <button type="button" class="v26-chip" data-interes="medio">Interés medio</button>
          <button type="button" class="v26-chip" data-interes="interesado">Interesado</button>
          <button type="button" class="v26-chip" data-interes="muy_interesado">Muy interesado</button>
        </div>
      </div>

      <div id="msg-parada"></div>
      <button id="btn-registrar" class="v26-btn v26-btn-primary v26-btn-block" disabled>Obteniendo GPS...</button>
    </div>

    <a href="mis_paradas.php" class="v26-link-listado-paradas">Ver mis paradas de hoy <i class="bi bi-chevron-right"></i></a>
  </div>

<script src="../assets/js/v26-modal.js<?= assetVer(__DIR__ . '/../assets/js/v26-modal.js') ?>"></script>
<script src="../assets/js/vendedor.js<?= assetVer(__DIR__ . '/../assets/js/vendedor.js') ?>"></script>
<script>
iniciarTrackingPeriodico();

let lat = null, lng = null, accuracy = null;
let fotoBlob = null;
let streamCamara = null;
let modo = null;       // 'entrada' | 'salida', se define al cargar el estado
let paradaAbiertaId = null;
let interesSel = null;

const estadoGps = document.getElementById('estado-gps');
const btn = document.getElementById('btn-registrar');

// ---------- Toggle persona/empresa ----------
document.querySelectorAll('#seg-tipo-parada .v26-seg-btn').forEach(b => {
  b.addEventListener('click', () => {
    document.querySelectorAll('#seg-tipo-parada .v26-seg-btn').forEach(x => x.classList.remove('active'));
    b.classList.add('active');
    document.getElementById('tipo-parada').value = b.dataset.tipo;
  });
});

// ---------- GPS ----------
// Mismo criterio que vendedor/checkin.php: el primer fix suele ser el peor
// (arranque en frío, dentro de un local), así que se escuchan varias
// lecturas unos segundos y se queda la más precisa. Se puede registrar en
// cuanto hay una aceptable; api/prospeccion.php rechaza las peores que
// PRECISION_MINIMA_CHECKIN_METROS.
const PRECISION_BUENA_M = 30;
const PRECISION_MINIMA_ACEPTABLE_M = 500;
const TIEMPO_MAX_ESPERA_MS = 15000;
let watchId = null;
let direccionAutocompletada = false;

function botonReintentarGps() {
  return ' <button type="button" id="btn-reintentar-gps" class="v26-btn v26-btn-ghost mt-2" style="padding:6px 14px;font-size:.8rem;width:auto;display:inline-block;">Reintentar</button>';
}

function iniciarGps() {
  if (!('geolocation' in navigator)) { estadoGps.innerHTML = 'Tu navegador no soporta geolocalización.'; return; }
  if (watchId !== null) navigator.geolocation.clearWatch(watchId);
  lat = null; lng = null; accuracy = null;
  revisarListoParaEnviar();
  estadoGps.innerHTML = '<i class="bi bi-geo-alt"></i> Obteniendo tu ubicación...';
  const inicio = Date.now();
  watchId = navigator.geolocation.watchPosition(
    (pos) => {
      const acc = pos.coords.accuracy;
      if (accuracy === null || acc < accuracy) {
        lat = pos.coords.latitude;
        lng = pos.coords.longitude;
        accuracy = acc;
      }
      const aceptable = accuracy <= PRECISION_MINIMA_ACEPTABLE_M;
      const termino = accuracy <= PRECISION_BUENA_M || (Date.now() - inicio) >= TIEMPO_MAX_ESPERA_MS;
      if (termino) {
        navigator.geolocation.clearWatch(watchId);
        watchId = null;
      }
      if (!aceptable) {
        estadoGps.innerHTML = termino
          // Peor que 1 km casi siempre es "Ubicación exacta" apagada (vendedor.js).
          ? (accuracy > UMBRAL_UBICACION_APROXIMADA_M ? htmlUbicacionAproximada(accuracy)
            : `<span class="text-danger">⚠️ No se pudo obtener una ubicación confiable (±${Math.round(accuracy)} m). Sal a espacio abierto o acércate a una ventana.</span>` + botonReintentarGps())
          : `<i class="bi bi-geo-alt"></i> Mejorando precisión... (±${Math.round(accuracy)} m)`;
      } else {
        estadoGps.innerHTML = `<i class="bi bi-geo-alt-fill text-success"></i> Ubicación obtenida (precisión ±${Math.round(accuracy)} m)` + (termino ? '' : ' · mejorando precisión...');
        if (!direccionAutocompletada) { direccionAutocompletada = true; autocompletarDireccion(); }
      }
      document.getElementById('btn-reintentar-gps')?.addEventListener('click', iniciarGps);
      revisarListoParaEnviar();
    },
    (err) => {
      if (watchId !== null) navigator.geolocation.clearWatch(watchId);
      watchId = null;
      // Si ya había una lectura aceptable, el vendedor puede seguir con esa.
      if (lat && accuracy <= PRECISION_MINIMA_ACEPTABLE_M) return;
      // Solo llegó una lectura aproximada y el teléfono no mandó más.
      if (lat && accuracy > UMBRAL_UBICACION_APROXIMADA_M) {
        estadoGps.innerHTML = htmlUbicacionAproximada(accuracy);
        document.getElementById('btn-reintentar-gps')?.addEventListener('click', iniciarGps);
        return;
      }
      let msg = '⚠️ No se pudo obtener tu ubicación. Activa el GPS y los permisos de ubicación del navegador.';
      if (err.code === 1) msg = '⚠️ Permiso de ubicación denegado en el navegador.';
      else if (err.code === 2) msg = '⚠️ Posición GPS no disponible.';
      else if (err.code === 3) msg = '⚠️ Tiempo de espera agotado al obtener GPS.';
      // Bloqueada en el teléfono: pasos para activarla (ver vendedor.js).
      // Apagada o sin señal (2/3): cómo activarla o salir a espacio abierto.
      estadoGps.innerHTML = err.code === 1 ? htmlUbicacionBloqueada()
        : (err.code === 2 || err.code === 3) ? htmlUbicacionNoDisponible(err.code)
        : msg + botonReintentarGps();
      document.getElementById('btn-reintentar-gps')?.addEventListener('click', iniciarGps);
    },
    { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }
  );
}

// Sugiere la dirección en cuanto llega el GPS, usando el mismo endpoint que
// ya usa el mapa del admin (api/geocodificar_punto.php) -- nunca pisa lo que
// el vendedor ya haya escrito a mano, y si falla (sin internet, Nominatim
// caído) el campo se queda vacío como siempre, se sigue pudiendo escribir.
async function autocompletarDireccion() {
  const campo = document.getElementById('direccion-parada');
  const formNuevaParada = document.getElementById('form-nueva-parada');
  if (!campo || !formNuevaParada || formNuevaParada.classList.contains('d-none')) return;
  if (campo.value.trim() !== '') return;
  try {
    const res = await fetch(`../api/geocodificar_punto.php?lat=${lat}&lng=${lng}`);
    const data = await res.json();
    if (data.ok && data.direccion && campo.value.trim() === '') campo.value = data.direccion;
  } catch (e) {
    // silencioso -- el campo se queda vacío, se escribe a mano
  }
}

function revisarListoParaEnviar() {
  // modo se define hasta que cargarEstado() resuelve -- sin él no sabemos
  // si esto es una entrada o una salida, así que el botón se queda
  // deshabilitado (evita una condición de carrera si el GPS/foto ya están
  // listos antes de que se sepa el estado del día).
  if (!modo) { btn.disabled = true; btn.textContent = 'Cargando...'; return; }
  const nombre = document.getElementById('nombre-parada').value.trim();
  const faltaNombre = modo === 'entrada' && !nombre;
  const faltaInteres = modo === 'salida' && !interesSel;
  const gpsListo = lat && lng && accuracy !== null && accuracy <= PRECISION_MINIMA_ACEPTABLE_M;
  if (gpsListo && fotoBlob && !faltaNombre && !faltaInteres) {
    btn.disabled = false;
    btn.textContent = modo === 'entrada' ? 'Registrar entrada' : 'Registrar salida';
  } else {
    btn.disabled = true;
    btn.textContent = !gpsListo ? 'Obteniendo GPS...'
      : (!fotoBlob ? 'Toma la foto para continuar'
      : (faltaNombre ? 'Escribe a quién visitas' : 'Elige qué tan interesado se mostró'));
  }
}
document.getElementById('nombre-parada').addEventListener('input', revisarListoParaEnviar);

// ---------- Cámara (mismo componente que vendedor/checkin.php) ----------
const video          = document.getElementById('video-camara');
const preview        = document.getElementById('preview-foto');
const canvas         = document.getElementById('canvas-foto');
const estadoCamara   = document.getElementById('estado-camara');
const btnTomarFoto   = document.getElementById('btn-tomar-foto');
const btnRepetirFoto = document.getElementById('btn-repetir-foto');
const placeholder    = document.getElementById('camara-placeholder');
const inputArchivo   = document.getElementById('input-archivo-foto');
const btnAbrirArchivo= document.getElementById('btn-abrir-archivo');
const txtBtnArchivo  = document.getElementById('txt-btn-archivo');

function dataURItoBlob(dataURI) {
  const byteString = atob(dataURI.split(',')[1]);
  const mimeString = dataURI.split(',')[0].split(':')[1].split(';')[0];
  const ab = new ArrayBuffer(byteString.length);
  const ia = new Uint8Array(ab);
  for (let i = 0; i < byteString.length; i++) ia[i] = byteString.charCodeAt(i);
  return new Blob([ab], { type: mimeString });
}

function procesarYGuardarBlob(origen, anchoOriginal, altoOriginal) {
  // 800px/0.7 (~60-80 KB) en vez de 1024px/0.82 (~150 KB): con señal débil
  // la subida tardaba cerca de 50 s. Mismo criterio que vendedor/checkin.php.
  const maxDim = 800;
  let w = anchoOriginal || 640, h = altoOriginal || 480;
  if (w > maxDim || h > maxDim) {
    if (w > h) { h = Math.round((h * maxDim) / w); w = maxDim; }
    else { w = Math.round((w * maxDim) / h); h = maxDim; }
  }
  canvas.width = w; canvas.height = h;
  canvas.getContext('2d').drawImage(origen, 0, 0, w, h);

  const finalizarConBlob = (blob) => {
    fotoBlob = blob;
    try { preview.src = URL.createObjectURL(blob); } catch (err) { preview.src = canvas.toDataURL('image/jpeg', 0.7); }
    preview.classList.remove('d-none');
    video.classList.add('d-none');
    if (placeholder) placeholder.classList.add('d-none');
    if (btnTomarFoto) btnTomarFoto.classList.add('d-none');
    if (btnRepetirFoto) btnRepetirFoto.classList.remove('d-none');
    const kb = Math.round(blob.size / 1024);
    estadoCamara.innerHTML = `<span class="text-success"><i class="bi bi-check-circle-fill"></i> Foto lista (${kb} KB).</span>`;
    revisarListoParaEnviar();
  };
  try {
    canvas.toBlob((blob) => blob ? finalizarConBlob(blob) : finalizarConBlob(dataURItoBlob(canvas.toDataURL('image/jpeg', 0.7))), 'image/jpeg', 0.7);
  } catch (err) {
    finalizarConBlob(dataURItoBlob(canvas.toDataURL('image/jpeg', 0.7)));
  }
}

function procesarArchivoSeleccionado(file) {
  if (!file) return;
  estadoCamara.innerHTML = '<span class="text-primary"><i class="bi bi-hourglass-split"></i> Procesando foto...</span>';
  const blobUrl = URL.createObjectURL(file);
  const img = new Image();
  img.onload = () => { URL.revokeObjectURL(blobUrl); procesarYGuardarBlob(img, img.naturalWidth || img.width, img.naturalHeight || img.height); };
  img.onerror = () => {
    URL.revokeObjectURL(blobUrl);
    const reader = new FileReader();
    reader.onload = (e) => {
      const img2 = new Image();
      img2.onload = () => procesarYGuardarBlob(img2, img2.naturalWidth || img2.width, img2.naturalHeight || img2.height);
      img2.onerror = () => { estadoCamara.innerHTML = '<span class="text-danger">⚠️ Formato no compatible. Por favor toma otra foto.</span>'; };
      img2.src = e.target.result;
    };
    reader.readAsDataURL(file);
  };
  img.src = blobUrl;
}

async function iniciarCamara() {
  const esContextoSeguro = window.isSecureContext || location.hostname === 'localhost' || location.hostname === '127.0.0.1';
  if (!esContextoSeguro || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    if (placeholder) placeholder.classList.remove('d-none');
    if (video) video.classList.add('d-none');
    if (btnTomarFoto) btnTomarFoto.classList.add('d-none');
    if (btnAbrirArchivo) { btnAbrirArchivo.classList.remove('v26-btn-ghost'); btnAbrirArchivo.classList.add('v26-btn-primary'); }
    if (txtBtnArchivo) txtBtnArchivo.textContent = 'Tomar foto con cámara del celular';
    estadoCamara.innerHTML = '<span class="text-muted">Toca el recuadro o el botón de abajo para tomar la foto con tu celular.</span>';
    return;
  }
  try {
    streamCamara = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
    video.srcObject = streamCamara;
    video.play().catch(() => {});
    estadoCamara.textContent = 'Encuadra la evidencia y presiona el botón.';
    if (btnTomarFoto) btnTomarFoto.disabled = false;
    // Con la cámara en vivo funcionando, se quita la opción de subir una
    // foto ya existente -- la evidencia debe tomarse ahí mismo, no vale
    // subir una foto vieja del celular. Solo reaparece como respaldo real
    // si la cámara en vivo falla (ver el catch de abajo).
    if (btnAbrirArchivo) btnAbrirArchivo.classList.add('d-none');
  } catch (e) {
    if (placeholder) placeholder.classList.remove('d-none');
    if (video) video.classList.add('d-none');
    if (btnTomarFoto) btnTomarFoto.classList.add('d-none');
    if (btnAbrirArchivo) { btnAbrirArchivo.classList.remove('v26-btn-ghost'); btnAbrirArchivo.classList.add('v26-btn-primary'); }
    if (txtBtnArchivo) txtBtnArchivo.textContent = 'Tomar foto con cámara del celular';
    estadoCamara.innerHTML = '<span class="text-muted">No se pudo activar la cámara web. Usa el botón de abajo para tomar la foto con tu celular.</span>';
  }
}

if (btnTomarFoto) btnTomarFoto.addEventListener('click', () => procesarYGuardarBlob(video, video.videoWidth || 640, video.videoHeight || 480));
if (btnRepetirFoto) btnRepetirFoto.addEventListener('click', () => {
  fotoBlob = null;
  if (inputArchivo) inputArchivo.value = '';
  preview.classList.add('d-none');
  btnRepetirFoto.classList.add('d-none');
  if (streamCamara && streamCamara.active) {
    video.classList.remove('d-none'); btnTomarFoto.classList.remove('d-none');
    estadoCamara.textContent = 'Encuadra la evidencia y presiona el botón.';
  } else {
    if (placeholder) placeholder.classList.remove('d-none');
    estadoCamara.innerHTML = '<span class="text-muted">Toca el recuadro o el botón para tomar la foto con tu celular.</span>';
  }
  revisarListoParaEnviar();
});
if (inputArchivo) inputArchivo.addEventListener('change', (e) => { if (e.target.files && e.target.files[0]) procesarArchivoSeleccionado(e.target.files[0]); });
if (btnAbrirArchivo) btnAbrirArchivo.addEventListener('click', () => inputArchivo && inputArchivo.click());
if (placeholder) placeholder.addEventListener('click', () => inputArchivo && inputArchivo.click());

// ---------- Nivel de interés (solo en salida) ----------
document.querySelectorAll('#chips-interes .v26-chip').forEach(chip => {
  chip.addEventListener('click', () => {
    document.querySelectorAll('#chips-interes .v26-chip').forEach(c => c.classList.remove('active'));
    interesSel = chip.dataset.interes;
    chip.classList.add('active');
    revisarListoParaEnviar();
  });
});

// ---------- Enviar ----------
async function enviarParada() {
  const msg = document.getElementById('msg-parada');
  msg.innerHTML = '';
  if (!lat || !lng) { msg.innerHTML = '<div class="alert alert-danger py-2">Espera a que se obtenga tu ubicación GPS.</div>'; return; }
  if (!fotoBlob) { msg.innerHTML = '<div class="alert alert-danger py-2">Toma la foto de evidencia.</div>'; return; }

  btn.disabled = true;
  btn.textContent = 'Enviando...';
  const fd = new FormData();
  fd.append('lat', lat);
  fd.append('lng', lng);
  if (accuracy !== null) fd.append('accuracy', accuracy);
  fd.append('foto', fotoBlob, 'evidencia.jpg');

  if (modo === 'entrada') {
    const nombre = document.getElementById('nombre-parada').value.trim();
    if (!nombre) { msg.innerHTML = '<div class="alert alert-danger py-2">Escribe a quién visitas.</div>'; btn.disabled = false; revisarListoParaEnviar(); return; }
    fd.append('action', 'iniciar');
    fd.append('tipo', document.getElementById('tipo-parada').value);
    fd.append('nombre', nombre);
    fd.append('direccion', document.getElementById('direccion-parada').value.trim());
  } else {
    if (!interesSel) { msg.innerHTML = '<div class="alert alert-danger py-2">Elige qué tan interesado se mostró.</div>'; btn.disabled = false; revisarListoParaEnviar(); return; }
    fd.append('action', 'finalizar');
    fd.append('parada_id', paradaAbiertaId);
    fd.append('interes', interesSel);
  }

  envioEnCurso = true; // pausa el tracking mientras sube (ver vendedor.js)
  try {
    const data = await enviarConTiempoLimite('../api/prospeccion.php', { method: 'POST', body: fd }, 90000);
    if (data.ok) {
      if (streamCamara) streamCamara.getTracks().forEach(t => t.stop());
      window.location.reload();
    } else {
      msg.innerHTML = `<div class="alert alert-danger py-2">${data.error}</div>`;
      btn.disabled = false;
      revisarListoParaEnviar();
    }
  } catch (e) {
    msg.innerHTML = `<div class="alert alert-danger py-2">${mensajeErrorEnvio(e)}</div>`;
    btn.disabled = false;
    revisarListoParaEnviar();
  } finally {
    envioEnCurso = false;
  }
}
btn.addEventListener('click', enviarParada);

// ---------- Estado inicial: ¿hay una parada abierta? ----------
// horaCorta() ya viene de assets/js/vendedor.js (cargado arriba).

async function cargarEstado() {
  const res = await fetch('../api/prospeccion.php');
  const data = await res.json();
  if (!data.ok) return;
  const paradas = data.paradas || [];
  const abierta = paradas.find(p => !p.hora_fin);

  if (abierta) {
    modo = 'salida';
    paradaAbiertaId = abierta.id;
    document.getElementById('form-nueva-parada').classList.add('d-none');
    document.getElementById('bloque-interes').classList.remove('d-none');
    document.getElementById('titulo-camara').textContent = 'Registrar salida';
    document.getElementById('label-foto').textContent = 'Foto de salida (se toma aquí mismo, en el lugar)';
    const banner = document.getElementById('banner-parada-abierta');
    banner.classList.remove('d-none');
    document.getElementById('txt-parada-nombre').textContent = `En prospección: ${abierta.nombre}`;
    document.getElementById('txt-parada-detalle').textContent = `Desde las ${horaCortaUTC(abierta.hora_inicio)}${abierta.direccion ? ' · ' + abierta.direccion : ''}`;
  } else {
    modo = 'entrada';
  }
  revisarListoParaEnviar();
}

cargarEstado();
iniciarGps();
iniciarCamara();
</script>
</body>
</html>
