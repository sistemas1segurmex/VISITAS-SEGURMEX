<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$u = requireRole('vendedor');

$clienteId = (int)($_GET['id'] ?? 0);
$stmt = getDB()->prepare('SELECT * FROM clientes WHERE id = ? AND vendedor_id = ?');
$stmt->execute([$clienteId, $u['id']]);
$cliente = $stmt->fetch();
if (!$cliente) {
    http_response_code(404);
    die('Cliente no encontrado.');
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Editar cliente</title>
<link rel="icon" type="image/png" href="../assets/img/Favv-Segu.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="../assets/css/style.css<?= assetVer(__DIR__ . '/../assets/css/style.css') ?>">
<link rel="stylesheet" href="../assets/css/vendedor-2026.css<?= assetVer(__DIR__ . '/../assets/css/vendedor-2026.css') ?>">
<style>
  .v26-compacto .v26-wrap { padding-top: 10px; padding-bottom: 4px; }
  .v26-compacto .v26-card { padding: 14px; }
  .v26-compacto .v26-field { margin-bottom: 10px; }
  .v26-compacto .v26-field label { margin-bottom: 3px; font-size: .68rem; }
  .v26-compacto .v26-input, .v26-compacto .v26-select { padding: 9px 12px; font-size: .87rem; }
  .v26-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px; }
  .v26-grid-2 .v26-field { margin-bottom: 0; }
  .v26-map-chica { height: 160px; }
  .v26-buscar-row { display: flex; gap: 8px; margin-bottom: 4px; }
  .v26-buscar-row .v26-search { flex: 1; margin-bottom: 0; }
  .v26-resultados-busqueda {
    position: relative; z-index: 20; margin-top: 6px; margin-bottom: 8px;
    background: var(--v26-surface-solid); border: 1px solid var(--v26-border);
    border-radius: var(--v26-r-md); box-shadow: var(--v26-shadow-md); overflow: hidden;
    max-height: 180px; overflow-y: auto;
  }
  .v26-resultados-busqueda button {
    display: block; width: 100%; text-align: left; border: none; background: none;
    padding: 9px 12px; font-size: .78rem; border-bottom: 1px solid var(--v26-border);
  }
  .v26-resultados-busqueda button:last-child { border-bottom: none; }
  .v26-resultados-busqueda button:hover { background: rgba(245,166,35,.08); }
  .v26-ubicacion-badge { font-size: .68rem; font-weight: 700; text-transform: none; letter-spacing: 0; display: inline-flex; align-items: center; gap: 3px; }
  .v26-ubicacion-badge.pendiente { color: var(--v26-red); }
  .v26-ubicacion-badge.lista { color: var(--v26-green); }
</style>
</head>
<body class="v26 v26-compacto">
  <div class="v26-header">
    <div class="v26-topbar">
      <div class="v26-topbar-left">
        <a href="clientes.php" class="v26-back v26-tip v26-tip--bottom" data-tip="Volver a mis clientes" aria-label="Volver"><i class="bi bi-arrow-left"></i></a>
        <div class="v26-greeting">
          <div class="hi">Cartera</div>
          <div class="name">Editar cliente</div>
        </div>
      </div>
      <div class="v26-topbar-right">
        <img src="../logo.png" alt="Segurmex" class="v26-logo">
      </div>
    </div>
  </div>

  <div class="v26-wrap">
    <div class="v26-card">
      <form id="form-cliente">
        <input type="hidden" name="id" value="<?= (int)$cliente['id'] ?>">
        <div class="v26-field">
          <label>¿Es persona u organización?</label>
          <div class="v26-seg" id="seg-tipo-cliente" style="display:flex;width:100%;">
            <button type="button" class="v26-seg-btn <?= ($cliente['tipo_cliente'] ?? 'organizacion') === 'persona' ? 'active' : '' ?>" data-tipo="persona" style="flex:1;"><i class="bi bi-person"></i> Persona</button>
            <button type="button" class="v26-seg-btn <?= ($cliente['tipo_cliente'] ?? 'organizacion') === 'persona' ? '' : 'active' ?>" data-tipo="organizacion" style="flex:1;"><i class="bi bi-building"></i> Organización</button>
          </div>
          <input type="hidden" name="tipo_cliente" id="tipo-cliente" value="<?= htmlspecialchars($cliente['tipo_cliente'] ?? 'organizacion') ?>">
        </div>

        <div class="v26-grid-2">
          <div class="v26-field">
            <label id="label-nombre"><?= ($cliente['tipo_cliente'] ?? 'organizacion') === 'persona' ? 'Nombre completo' : 'Nombre del negocio' ?></label>
            <input type="text" name="nombre" class="v26-input" value="<?= htmlspecialchars($cliente['nombre']) ?>" required>
          </div>
          <div class="v26-field">
            <label>Teléfono (opcional)</label>
            <input type="text" name="telefono" class="v26-input" value="<?= htmlspecialchars($cliente['telefono'] ?? '') ?>">
          </div>
        </div>

        <div class="v26-field <?= ($cliente['tipo_cliente'] ?? 'organizacion') === 'persona' ? 'd-none' : '' ?>" id="campo-contacto">
          <label>Persona de contacto (opcional)</label>
          <input type="text" name="nombre_contacto" class="v26-input" placeholder="¿Con quién tratas ahí?" value="<?= htmlspecialchars($cliente['nombre_contacto'] ?? '') ?>">
        </div>

        <?php $calleNumeroPrevio = $cliente['calle_numero'] ?? ''; include __DIR__ . '/../includes/form_direccion_cliente.php'; ?>

        <div id="msg-cliente"></div>
        <button type="submit" class="v26-btn v26-btn-primary v26-btn-block mt-1">Guardar cambios</button>
      </form>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="../assets/js/v26-modal.js<?= assetVer(__DIR__ . '/../assets/js/v26-modal.js') ?>"></script>
<script src="../assets/js/vendedor.js<?= assetVer(__DIR__ . '/../assets/js/vendedor.js') ?>"></script>
<script>window.DIRECCION_CFG = { googleKey: <?= json_encode(envConfig('GOOGLE_MAPS_API_KEY', '') ?? '') ?> };</script>
<script src="../assets/js/direccion-cliente.js<?= assetVer(__DIR__ . '/../assets/js/direccion-cliente.js') ?>"></script>
<script>
iniciarTrackingPeriodico();

// Datos del cliente tal como quedaron guardados, para precargar el formulario.
const clientePrevio = <?= json_encode([
  'id'            => $cliente['id'],
  'lat'           => $cliente['lat'],
  'lng'           => $cliente['lng'],
  'estado'        => $cliente['estado'],
  'municipio'     => $cliente['municipio'],
  'colonia'       => $cliente['colonia'],
  'codigo_postal' => $cliente['codigo_postal'],
], JSON_UNESCAPED_UNICODE) ?>;

// Precarga de lo que el cliente ya tenía guardado.
DireccionCliente.init().then(() => DireccionCliente.precargar(clientePrevio));

document.querySelectorAll('#seg-tipo-cliente .v26-seg-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('#seg-tipo-cliente .v26-seg-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tipo-cliente').value = btn.dataset.tipo;
    document.getElementById('label-nombre').textContent = btn.dataset.tipo === 'persona' ? 'Nombre completo' : 'Nombre del negocio';
    document.getElementById('campo-contacto').classList.toggle('d-none', btn.dataset.tipo === 'persona');
  });
});

// --- Detección de posibles clientes duplicados (excluyendo al propio cliente) ---
async function buscarPosiblesDuplicados({ nombre, telefono, lat, lng, colonia, calleNumero }) {
  const params = new URLSearchParams({ nombre, telefono, colonia, calle_numero: calleNumero, excluir_id: clientePrevio.id || '' });
  if (lat) params.set('lat', lat);
  if (lng) params.set('lng', lng);
  try {
    const res = await fetch('../api/verificar_duplicado.php?' + params.toString());
    const data = await res.json();
    return data.ok ? data.candidatos : [];
  } catch (e) {
    return [];
  }
}

// --- Envío del formulario ---
document.getElementById('form-cliente').addEventListener('submit', async (e) => {
  e.preventDefault();
  const msg = document.getElementById('msg-cliente');
  msg.innerHTML = '';

  const { estado, municipio, colonia, cp, lat, lng, calleNumero } = DireccionCliente.valores();
  if (!estado || !municipio || !colonia) {
    msg.innerHTML = '<div class="alert alert-danger py-2">Escribe el código postal y elige la colonia de la lista (así evitamos colonias que no existen).</div>';
    return;
  }

  const nombre = document.querySelector('input[name="nombre"]').value.trim();
  const telefono = document.querySelector('input[name="telefono"]').value.trim();

  // La ubicación es obligatoria sin excepción: sin ella no se puede
  // verificar el check-in por GPS ni ordenar la cartera por cercanía.
  if (!lat || !lng) {
    msg.innerHTML = '<div class="alert alert-danger py-2">Falta marcar la ubicación del cliente: toca el punto en el mapa, busca la calle, pega el link que te compartieron o usa "Usar mi ubicación".</div>';
    document.getElementById('mapa-cliente').scrollIntoView({ behavior: 'smooth', block: 'center' });
    return;
  }
  const candidatos = await buscarPosiblesDuplicados({ nombre, telefono, lat, lng, colonia, calleNumero });
  if (candidatos.length > 0) {
    const lista = candidatos.map(c => `• ${c.nombre} — ${c.direccion} (${c.razon})`).join('\n');
    const respuesta = await v26Sheet({
      titulo: 'Ya tienes un cliente parecido',
      desc: `Antes de guardar, revisa si no es el mismo:\n${lista}`,
      pedirMotivo: false,
      textoConfirmar: 'Guardar de todas formas',
      textoCancelar: 'Revisar de nuevo',
    });
    if (!respuesta) return;
  }

  const fd = new FormData(e.target);
  fd.append('colonia', colonia);
  fd.append('municipio', municipio);
  fd.append('estado', estado);
  fd.append('codigo_postal', cp);

  const res = await fetch('../api/editar_cliente.php', { method: 'POST', body: fd });
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
