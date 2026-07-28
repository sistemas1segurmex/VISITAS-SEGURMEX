<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$u  = requireLogin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $fecha = $_GET['fecha'] ?? date('Y-m-d');

    $sqlBase =
        "SELECT c.*, cl.nombre AS cliente_nombre, cl.direccion, cl.lat AS cliente_lat, cl.lng AS cliente_lng,
                u.nombre AS vendedor_nombre,
                (SELECT verificado FROM checkins ch WHERE ch.cita_id = c.id AND ch.tipo='entrada' ORDER BY ch.id DESC LIMIT 1) AS checkin_verificado,
                (SELECT COUNT(*) FROM checkins ch WHERE ch.cita_id = c.id AND ch.tipo='entrada') AS tiene_entrada,
                (SELECT COUNT(*) FROM checkins ch WHERE ch.cita_id = c.id AND ch.tipo='salida') AS tiene_salida,
                (SELECT id FROM checkins ch WHERE ch.cita_id = c.id AND ch.tipo='entrada' AND ch.foto_path IS NOT NULL ORDER BY ch.id DESC LIMIT 1) AS foto_entrada_id,
                (SELECT id FROM checkins ch WHERE ch.cita_id = c.id AND ch.tipo='salida' AND ch.foto_path IS NOT NULL ORDER BY ch.id DESC LIMIT 1) AS foto_salida_id
         FROM citas c
         JOIN clientes cl ON cl.id = c.cliente_id
         JOIN usuarios u ON u.id = c.vendedor_id
         WHERE DATE(c.fecha_hora) = ?";

    if ($u['rol'] === 'admin') {
        $stmt = $db->prepare($sqlBase . ' ORDER BY c.fecha_hora ASC');
        $stmt->execute([$fecha]);
    } else {
        $stmt = $db->prepare($sqlBase . ' AND c.vendedor_id = ? ORDER BY c.fecha_hora ASC');
        $stmt->execute([$fecha, $u['id']]);
    }

    $citas = $stmt->fetchAll();
    $ahora = new DateTime();
    foreach ($citas as &$c) {
        $hora = new DateTime($c['fecha_hora']);
        $c['retrasada'] = ($c['estado'] === 'pendiente' && $hora < $ahora);
    }
    jsonResponse(['ok' => true, 'citas' => $citas]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole('vendedor');
    $clienteId = (int)($_POST['cliente_id'] ?? 0);
    $fechaHora = trim($_POST['fecha_hora'] ?? '');
    $notas     = trim($_POST['notas'] ?? '');

    if (!$clienteId || !$fechaHora) {
        jsonResponse(['ok' => false, 'error' => 'Cliente y fecha/hora son obligatorios'], 400);
    }

    $tsFecha = strtotime(str_replace('T', ' ', $fechaHora));
    if ($tsFecha === false || $tsFecha < strtotime('today')) {
        jsonResponse(['ok' => false, 'error' => 'No puedes agendar una cita en una fecha pasada'], 400);
    }

    $chk = $db->prepare('SELECT id FROM clientes WHERE id = ? AND vendedor_id = ?');
    $chk->execute([$clienteId, $u['id']]);
    if (!$chk->fetch()) {
        jsonResponse(['ok' => false, 'error' => 'Cliente no válido'], 400);
    }

    $stmt = $db->prepare('INSERT INTO citas (vendedor_id, cliente_id, fecha_hora, notas) VALUES (?,?,?,?)');
    $stmt->execute([$u['id'], $clienteId, $fechaHora, $notas]);
    jsonResponse(['ok' => true, 'id' => $db->lastInsertId()]);
}

jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
