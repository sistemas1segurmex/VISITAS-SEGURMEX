<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/alertas.php';

$u  = requireRole('admin');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Genera las nuevas y cierra solas las que ya no aplican (como máximo
    // cada minuto, ver revisarAlertas() en includes/alertas.php).
    revisarAlertas($db);
    jsonResponse(['ok' => true] + alertasParaPanel($db));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        jsonResponse(['ok' => false, 'error' => 'Id inválido'], 400);
    }
    // 1 = la marcó el admin (2 es "se resolvió sola", ver includes/alertas.php).
    $db->prepare('UPDATE alertas SET resuelta = 1 WHERE id = ? AND resuelta = 0')->execute([$id]);
    jsonResponse(['ok' => true]);
}

jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
