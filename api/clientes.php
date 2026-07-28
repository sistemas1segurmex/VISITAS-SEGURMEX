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
    $nombre    = trim($_POST['nombre'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $lat       = $_POST['lat'] ?? null;
    $lng       = $_POST['lng'] ?? null;
    $telefono  = trim($_POST['telefono'] ?? '');

    if ($nombre === '' || $direccion === '') {
        jsonResponse(['ok' => false, 'error' => 'Nombre y dirección son obligatorios'], 400);
    }

    $stmt = $db->prepare('INSERT INTO clientes (vendedor_id, nombre, direccion, lat, lng, telefono) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$u['id'], $nombre, $direccion, $lat !== '' ? $lat : null, $lng !== '' ? $lng : null, $telefono]);
    jsonResponse(['ok' => true, 'id' => $db->lastInsertId()]);
}

jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
