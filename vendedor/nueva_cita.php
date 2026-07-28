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
<title>Nueva cita</title>
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
  <h5 class="mb-3">Registrar próxima cita</h5>
  <div class="card shadow-sm">
    <div class="card-body">
      <form id="form-cita">
        <div class="mb-3">
          <label class="form-label">Cliente</label>
          <select name="cliente_id" id="select-cliente" class="form-select" required>
            <option value="">Cargando clientes...</option>
          </select>
          <div class="form-text">¿No está en la lista? <a href="nuevo_cliente.php">Regístralo primero</a>.</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Fecha y hora de la visita</label>
          <input type="datetime-local" name="fecha_hora" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Notas (opcional)</label>
          <textarea name="notas" class="form-control" rows="2"></textarea>
        </div>
        <div id="msg-cita"></div>
        <button type="submit" class="btn btn-brand w-100">Guardar cita</button>
      </form>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
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
