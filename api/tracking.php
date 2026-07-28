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
    // Todos los vendedores activos, con su última ubicación conocida (aunque
    // sea de días atrás) y si están "en línea" (reportaron en los últimos 15
    // minutos). Los que nunca han reportado salen con lat/lng en null.
    $stmt = $db->query(
        "SELECT u.id AS vendedor_id, u.nombre, u.estado_operacion, t.lat, t.lng, t.fecha_hora,
                CASE WHEN t.fecha_hora IS NOT NULL AND t.fecha_hora >= NOW() - INTERVAL '15 minutes'
                     THEN 1 ELSE 0 END AS en_linea
         FROM usuarios u
         LEFT JOIN LATERAL (
             SELECT lat, lng, fecha_hora FROM tracking_ubicaciones
             WHERE vendedor_id = u.id
             ORDER BY fecha_hora DESC LIMIT 1
         ) t ON true
         WHERE u.rol = 'vendedor' AND u.activo = 1
         ORDER BY u.nombre"
    );
    jsonResponse(['ok' => true, 'ubicaciones' => $stmt->fetchAll()]);
}

jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
