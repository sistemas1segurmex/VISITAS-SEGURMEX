<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
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
          <div class="hi">Agenda</div>
          <div class="name">Nueva cita</div>
        </div>
      </div>
      <div class="v26-topbar-right">
        <img src="../logo.png" alt="Segurmex" class="v26-logo">
      </div>
    </div>
  </div>

  <div class="v26-wrap">
    <div class="v26-card">
      <form id="form-cita">
        <div class="v26-field">
          <label>Cliente</label>
          <select name="cliente_id" id="select-cliente" class="v26-select" required>
            <option value="">Cargando clientes...</option>
          </select>
          <div class="form-text mt-1" style="font-size:.76rem;">¿No está en la lista? <a href="nuevo_cliente.php">Regístralo primero</a>.</div>
        </div>
        <div class="v26-field">
          <label>Fecha y hora de la visita</label>
          <input type="datetime-local" name="fecha_hora" id="input-fecha-hora" class="v26-input" required>
        </div>
        <div class="v26-field">
          <label>Notas (opcional)</label>
          <textarea name="notas" class="v26-textarea" rows="2"></textarea>
        </div>
        <div id="msg-cita"></div>
        <button type="submit" class="v26-btn v26-btn-primary v26-btn-block">Guardar cita</button>
      </form>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// No se pueden agendar citas en fechas pasadas.
const hoyISO = new Date().toISOString().slice(0, 10);
const inputFecha = document.getElementById('input-fecha-hora');
inputFecha.min = hoyISO + 'T00:00';
const fechaPrellenada = <?= json_encode($fechaPrellenada) ?>;
if (fechaPrellenada) inputFecha.value = fechaPrellenada + 'T09:00';

async function cargarSelectClientes() {
  const sel = document.getElementById('select-cliente');
  const res = await fetch('../api/clientes.php');
  const data = await res.json();
  if (!data.ok || data.clientes.length === 0) {
    sel.innerHTML = '<option value="">No tienes clientes registrados aún</option>';
    return;
  }
  sel.innerHTML = '<option value="">Selecciona un cliente</option>' +
    data.clientes.map(c => `<option value="${c.id}">${c.nombre} — ${c.direccion}</option>`).join('');
}
cargarSelectClientes();

document.getElementById('form-cita').addEventListener('submit', async (e) => {
  e.preventDefault();
  const msg = document.getElementById('msg-cita');
  msg.innerHTML = '';
  const fd = new FormData(e.target);
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
