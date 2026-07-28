<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$u  = requireLogin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole('vendedor');
    $lat = isset($_POST['lat']) ? (float)$_POST['lat'] : 0.0;
    $lng = isset($_POST['lng']) ? (float)$_POST['lng'] : 0.0;
    if (!$lat || !$lng) {
        jsonResponse(['ok' => false, 'error' => 'GPS inválido'], 400);
    }
    $db->prepare('INSERT INTO tracking_ubicaciones (vendedor_id, lat, lng) VALUES (?,?,?)')
       ->execute([$u['id'], $lat, $lng]);
    jsonResponse(['ok' => true]);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    requireRole('admin');
    // Última ubicación conocida de cada vendedor que haya reportado hoy.
    $stmt = $db->query(
        "SELECT t.vendedor_id, u.nombre, u.estado_operacion, t.lat, t.lng, t.fecha_hora
         FROM tracking_ubicaciones t
         JOIN usuarios u ON u.id = t.vendedor_id
         WHERE t.id IN (
             SELECT MAX(id) FROM tracking_ubicaciones
             WHERE DATE(fecha_hora) = CURRENT_DATE
             GROUP BY vendedor_id
         )"
    );
    jsonResponse(['ok' => true, 'ubicaciones' => $stmt->fetchAll()]);
}

jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
