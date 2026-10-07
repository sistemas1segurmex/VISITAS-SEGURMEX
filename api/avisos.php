<?php
// Avisos de la campanita (tabla avisos) del usuario con sesión -- ver
// assets/js/avisos.js. Hoy los generan las solicitudes de muestra
// (includes/muestras.php): la responsable recibe "nueva solicitud" y el
// vendedor recibe cada cambio de estado de las suyas.
//   GET                      -> {no_leidos, avisos: [...30 más recientes]}
//   POST accion=leer&id=N    -> marca uno como leído
//   POST accion=leer_todos   -> marca todos como leídos
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$u  = requireLogin();
$db = getDB();
$uid = (int)$u['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare('SELECT COUNT(*) FROM avisos WHERE usuario_id = ? AND leido_en IS NULL');
    $stmt->execute([$uid]);
    $noLeidos = (int)$stmt->fetchColumn();

    $stmt = $db->prepare(
        'SELECT id, tipo, titulo, mensaje, enlace, leido_en, creado_en
         FROM avisos WHERE usuario_id = ? ORDER BY creado_en DESC, id DESC LIMIT 30'
    );
    $stmt->execute([$uid]);
    jsonResponse(['ok' => true, 'no_leidos' => $noLeidos, 'avisos' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

$accion = $_POST['accion'] ?? '';
if ($accion === 'leer') {
    $id = (int)($_POST['id'] ?? 0);
    $db->prepare('UPDATE avisos SET leido_en = NOW() WHERE id = ? AND usuario_id = ? AND leido_en IS NULL')->execute([$id, $uid]);
    jsonResponse(['ok' => true]);
}
if ($accion === 'leer_todos') {
    $db->prepare('UPDATE avisos SET leido_en = NOW() WHERE usuario_id = ? AND leido_en IS NULL')->execute([$uid]);
    jsonResponse(['ok' => true]);
}
jsonResponse(['ok' => false, 'error' => 'Acción no reconocida'], 400);
