<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$u  = requireRole('vendedor');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare('SELECT * FROM clientes WHERE vendedor_id = ? ORDER BY nombre');
    $stmt->execute([$u['id']]);
    jsonResponse(['ok' => true, 'clientes' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre        = trim($_POST['nombre'] ?? '');
    $calle         = trim($_POST['calle'] ?? $_POST['direccion'] ?? '');
    $codigoPostal  = trim($_POST['codigo_postal'] ?? '');
    $colonia       = trim($_POST['colonia'] ?? '');
    $municipio     = trim($_POST['municipio'] ?? '');
    $estado        = trim($_POST['estado'] ?? '');
    $lat           = $_POST['lat'] ?? null;
    $lng           = $_POST['lng'] ?? null;
    $telefono      = trim($_POST['telefono'] ?? '');

    if ($nombre === '' || $calle === '') {
        jsonResponse(['ok' => false, 'error' => 'Nombre y dirección son obligatorios'], 400);
    }

    // Dirección completa y legible, compuesta a partir de las partes
    // capturadas (para no tener que tocar las pantallas que ya muestran
    // "direccion" tal cual, como el check-in o el panel del dueño).
    $partes = array_filter([$calle, $colonia, $municipio, $estado, $codigoPostal]);
    $direccion = implode(', ', $partes);

    $stmt = $db->prepare(
        'INSERT INTO clientes (vendedor_id, nombre, direccion, lat, lng, telefono, codigo_postal, estado, municipio, colonia)
         VALUES (?,?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $u['id'], $nombre, $direccion,
        $lat !== '' ? $lat : null, $lng !== '' ? $lng : null,
        $telefono,
        $codigoPostal ?: null, $estado ?: null, $municipio ?: null, $colonia ?: null,
    ]);
    jsonResponse(['ok' => true, 'id' => $db->lastInsertId()]);
}

jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
