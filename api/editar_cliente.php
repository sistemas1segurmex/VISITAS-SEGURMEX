<?php
// Actualiza un cliente ya existente (propio del vendedor). Mismas reglas de
// validación que el alta en api/clientes.php -- la ubicación GPS también es
// obligatoria aquí, así sirve para completar clientes viejos que se hayan
// quedado sin ella.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$u  = requireRole('vendedor');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    jsonResponse(['ok' => false, 'error' => 'Cliente no válido'], 400);
}

$stmt = $db->prepare('SELECT id FROM clientes WHERE id = ? AND vendedor_id = ?');
$stmt->execute([$id, $u['id']]);
if (!$stmt->fetch()) {
    jsonResponse(['ok' => false, 'error' => 'Cliente no encontrado'], 404);
}

$nombre        = trim($_POST['nombre'] ?? '');
$calleNumero   = trim($_POST['calle_numero'] ?? '');
$codigoPostal  = trim($_POST['codigo_postal'] ?? '');
$colonia       = trim($_POST['colonia'] ?? '');
$municipio     = trim($_POST['municipio'] ?? '');
$estado        = trim($_POST['estado'] ?? '');
$lat           = $_POST['lat'] ?? null;
$lng           = $_POST['lng'] ?? null;
$telefono      = trim($_POST['telefono'] ?? '');
$tipoCliente   = ($_POST['tipo_cliente'] ?? '') === 'persona' ? 'persona' : 'organizacion';
$nombreContacto = trim($_POST['nombre_contacto'] ?? '');

if ($nombre === '' || $calleNumero === '') {
    jsonResponse(['ok' => false, 'error' => 'Nombre y dirección son obligatorios'], 400);
}
// Igual que al dar de alta: la ubicación GPS es obligatoria sin excepción.
if ($lat === null || $lat === '' || $lng === null || $lng === '') {
    jsonResponse(['ok' => false, 'error' => 'Marca la ubicación del cliente en el mapa antes de guardar'], 400);
}

$partes = array_filter([$calleNumero, $colonia, $municipio, $estado, $codigoPostal]);
$direccion = implode(', ', $partes);

$stmt = $db->prepare(
    'UPDATE clientes
     SET nombre = ?, direccion = ?, calle_numero = ?, lat = ?, lng = ?, telefono = ?,
         codigo_postal = ?, estado = ?, municipio = ?, colonia = ?, tipo_cliente = ?, nombre_contacto = ?
     WHERE id = ? AND vendedor_id = ?'
);
$stmt->execute([
    $nombre, $direccion, $calleNumero,
    $lat !== '' ? $lat : null, $lng !== '' ? $lng : null,
    $telefono,
    $codigoPostal ?: null, $estado ?: null, $municipio ?: null, $colonia ?: null, $tipoCliente, $nombreContacto ?: null,
    $id, $u['id'],
]);

jsonResponse(['ok' => true]);
