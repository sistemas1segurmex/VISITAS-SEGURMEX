<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$u = requireRole('vendedor');
$fechaPrellenada = $_GET['fecha'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaPrellenada)) $fechaPrellenada = '';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Nueva cita</title>
<link rel="icon" type="image/png" href="../assets/img/Favv-Segu.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css<?= assetVer(__DIR__ . '/../assets/css/style.css') ?>">
<link rel="stylesheet" href="../assets/css/vendedor-2026.css<?= assetVer(__DIR__ . '/../assets/css/vendedor-2026.css') ?>">
</head>
<body class="v26">
  <div class="v26-header">
    <div class="v26-topbar">
      <div class="v26-topbar-left">
        <a href="index.php" class="v26-back v26-tip v26-tip--bottom" data-tip="Volver a mis visitas" aria-label="Volver"><i class="bi bi-arrow-left"></i></a>
        <div class="v26-greeting">
          <div class="hi">Agenda</div>
          <div class="name">Nueva cita</div>
        </div>
      </div>
      <div class="v26-topbar-right">
        <img src="../logo.png" alt="Segurmex" class="v26-logo">
        <button type="button" class="v26-icon-btn v26-tip v26-tip--bottom" data-tip="Ver el recorrido de nuevo" aria-label="Ayuda" id="btn-tour-ayuda"><i class="bi bi-question-lg"></i></button>
      </div>
    </div>
  </div>

  <div class="v26-wrap">
    <div class="v26-card">
      <form id="form-cita">
        <div class="v26-field" data-tour="cliente">
          <label>Cliente</label>
          <select name="cliente_id" id="select-cliente" class="v26-select" required>
            <option value="">Cargando clientes...</option>
          </select>
          <div class="form-text mt-1" style="font-size:.76rem;">¿No está en la lista? <a href="nuevo_cliente.php">Regístralo primero</a>.</div>
        </div>

        <div class="v26-direccion-info" id="direccion-info" data-tour="direccion">
          <i class="bi bi-geo-alt-fill"></i>
          <span>Vas a visitar: <strong id="direccion-texto"></strong></span>
        </div>

        <div class="v26-field" data-tour="fechahora">
          <label>Fecha y hora de la visita</label>
          <div class="v26-fila-2">
            <input type="date" name="fecha" id="input-fecha" class="v26-input" required>
            <input type="time" name="hora" id="input-hora" class="v26-input" required>
          </div>
        </div>
        <div class="v26-field" data-tour="notas">
          <label>Notas (opcional)</label>
          <textarea name="notas" class="v26-textarea" rows="2"></textarea>
        </div>
        <div id="msg-cita"></div>
        <button type="submit" class="v26-btn v26-btn-primary v26-btn-block" data-tour="guardar">Guardar cita</button>
      </form>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/v26-tour.js<?= assetVer(__DIR__ . '/../assets/js/v26-tour.js') ?>"></script>
<script src="../assets/js/vendedor.js<?= assetVer(__DIR__ . '/../assets/js/vendedor.js') ?>"></script>
<script>
iniciarTrackingPeriodico();
// No se pueden agendar citas en fechas pasadas.
const hoyISO = new Date().toISOString().slice(0, 10);
const inputFecha = document.getElementById('input-fecha');
const inputHora = document.getElementById('input-hora');
inputFecha.min = hoyISO;
const fechaPrellenada = <?= json_encode($fechaPrellenada) ?>;
if (fechaPrellenada) { inputFecha.value = fechaPrellenada; inputHora.value = '09:00'; }

const PASOS_TOUR_NUEVA_CITA = [
  { selector: '[data-tour="cliente"]', texto: 'Elige el cliente que vas a visitar.' },
  { selector: '[data-tour="direccion"].show', texto: 'Aquí ves la dirección para confirmar que es el lugar correcto -- ya no hace falta buscarla en el desplegable.' },
  { selector: '[data-tour="fechahora"]', texto: 'Elige el día y la hora en la que planeas llegar.' },
  { selector: '[data-tour="notas"]', texto: 'Agrega cualquier detalle que quieras recordar de esta visita.' },
  { selector: '[data-tour="guardar"]', texto: 'Guarda para agendarla en tu calendario.' },
];
const OPCIONES_TOUR_NUEVA_CITA = {
  storageKey: 'v26_tour_nueva_cita_visto',
  saludoTitulo: 'Así se agenda una cita',
  saludoTexto: 'Un par de cosas rápidas antes de que la uses.',
  finalTexto: 'Repite este recorrido cuando quieras tocando el ícono ? de arriba.',
};
document.getElementById('btn-tour-ayuda').addEventListener('click', () => V26Tour.reiniciar(PASOS_TOUR_NUEVA_CITA, OPCIONES_TOUR_NUEVA_CITA));

// La dirección solo aparece como dato informativo (no editable) una vez que
// se elige un cliente -- ya no se ve concatenada dentro del propio select.
const selCliente = document.getElementById('select-cliente');
const direccionInfo = document.getElementById('direccion-info');
const direccionTexto = document.getElementById('direccion-texto');
function actualizarDireccion() {
  const op = selCliente.options[selCliente.selectedIndex];
  const dir = op ? op.dataset.dir : '';
  if (dir) { direccionTexto.textContent = dir; direccionInfo.classList.add('show'); }
  else { direccionInfo.classList.remove('show'); }
}
selCliente.addEventListener('change', actualizarDireccion);

async function cargarSelectClientes() {
  const sel = document.getElementById('select-cliente');
  const res = await fetch('../api/clientes.php');
  const data = await res.json();
  if (!data.ok || data.clientes.length === 0) {
    sel.innerHTML = '<option value="">No tienes clientes registrados aún</option>';
    return;
  }
  sel.innerHTML = '<option value="">Selecciona un cliente</option>' +
    data.clientes.map(c => `<option value="${c.id}" data-dir="${c.direccion.replace(/"/g, '&quot;')}">${c.nombre}</option>`).join('');
}

cargarSelectClientes().then(() => V26Tour.iniciar(PASOS_TOUR_NUEVA_CITA, OPCIONES_TOUR_NUEVA_CITA));

document.getElementById('form-cita').addEventListener('submit', async (e) => {
  e.preventDefault();
  const msg = document.getElementById('msg-cita');
  msg.innerHTML = '';
  const fd = new FormData(e.target);
  fd.set('fecha_hora', `${inputFecha.value}T${inputHora.value}`);
  const res = await fetch('../api/citas.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.ok) {
    window.location.href = 'index.php';
  } else {
    msg.innerHTML = `<div class="alert alert-danger py-2">${data.error}</div>`;
  }
});
</script>
</body>
</html>
