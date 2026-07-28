<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
$u = requireRole('vendedor');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Nuevo cliente</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-dark" style="background:#111827">
  <div class="container-fluid">
    <a href="clientes.php" class="navbar-brand navbar-brand-custom text-decoration-none">← Mis clientes</a>
  </div>
</nav>
<div class="container py-4" style="max-width:720px">
  <h5 class="mb-3">Nuevo cliente</h5>
  <div class="card shadow-sm">
    <div class="card-body">
      <form id="form-cliente">
        <div class="mb-3">
          <label class="form-label">Nombre del cliente / negocio</label>
          <input type="text" name="nombre" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Dirección</label>
          <input type="text" name="direccion" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Teléfono (opcional)</label>
          <input type="text" name="telefono" class="form-control">
        </div>
        <div class="mb-2">
          <label class="form-label">Ubicación exacta (toca el mapa para marcar, o usa tu ubicación actual)</label>
          <div id="mapa-cliente" style="height:280px;border-radius:8px"></div>
          <button type="button" id="btn-mi-ubicacion" class="btn btn-sm btn-outline-secondary mt-2">📍 Usar mi ubicación actual</button>
          <input type="hidden" name="lat" id="lat">
          <input type="hidden" name="lng" id="lng">
        </div>
        <div id="msg-cliente"></div>
        <button type="submit" class="btn btn-brand w-100 mt-2">Guardar cliente</button>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const mapa = L.map('mapa-cliente').setView([23.6345, -102.5528], 5); // centro de México por defecto
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(mapa);
let marcador = null;

function ponerMarcador(lat, lng) {
  document.getElementById('lat').value = lat;
  document.getElementById('lng').value = lng;
  if (marcador) mapa.removeLayer(marcador);
  marcador = L.marker([lat, lng]).addTo(mapa);
  mapa.setView([lat, lng], 16);
}

mapa.on('click', (e) => ponerMarcador(e.latlng.lat, e.latlng.lng));

document.getElementById('btn-mi-ubicacion').addEventListener('click', () => {
  if (!('geolocation' in navigator)) return alert('Tu navegador no soporta geolocalización.');
  navigator.geolocation.getCurrentPosition(
    (pos) => ponerMarcador(pos.coords.latitude, pos.coords.longitude),
    () => alert('No se pudo obtener tu ubicación. Revisa los permisos del navegador.')
  );
});

document.getElementById('form-cliente').addEventListener('submit', async (e) => {
  e.preventDefault();
  const msg = document.getElementById('msg-cliente');
  msg.innerHTML = '';
  const fd = new FormData(e.target);
  const res = await fetch('../api/clientes.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.ok) {
    window.location.href = 'clientes.php';
  } else {
    msg.innerHTML = `<div class="alert alert-danger py-2">${data.error}</div>`;
  }
});
</script>
</body>
</html>
