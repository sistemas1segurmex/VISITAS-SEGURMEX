<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$u  = requireRole('admin');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->query(
        "SELECT a.*, u.nombre AS vendedor_nombre
         FROM alertas a JOIN usuarios u ON u.id = a.vendedor_id
         WHERE a.resuelta = 0
         ORDER BY a.created_at DESC
         LIMIT 50"
    );
    jsonResponse(['ok' => true, 'alertas' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        jsonResponse(['ok' => false, 'error' => 'Id inválido'], 400);
    }
    $db->prepare('UPDATE alertas SET resuelta = 1 WHERE id = ?')->execute([$id]);
    jsonResponse(['ok' => true]);
}

jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
