<?php
// api/foto.php?checkin_id=X
// Sirve la foto de evidencia de un check-in. No expone la carpeta uploads
// directamente: solo el admin, o el vendedor dueño de esa visita, pueden
// verla, y hay que estar con sesión iniciada.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$u = requireLogin();
$db = getDB();

$checkinId = (int)($_GET['checkin_id'] ?? 0);
if (!$checkinId) {
    http_response_code(400);
    die('Falta checkin_id');
}

$stmt = $db->prepare(
    'SELECT ch.foto_path, c.vendedor_id
     FROM checkins ch JOIN citas c ON c.id = ch.cita_id
     WHERE ch.id = ?'
);
$stmt->execute([$checkinId]);
$row = $stmt->fetch();

if (!$row || !$row['foto_path']) {
    http_response_code(404);
    die('Foto no encontrada');
}

if ($u['rol'] !== 'admin' && (int)$row['vendedor_id'] !== (int)$u['id']) {
    http_response_code(403);
    die('Sin permiso');
}

$ruta = __DIR__ . '/../' . $row['foto_path'];
if (!is_file($ruta)) {
    http_response_code(404);
    die('Archivo no encontrado en el servidor');
}

$info = @getimagesize($ruta);
$mime = $info['mime'] ?? 'image/jpeg';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($ruta));
header('Cache-Control: private, max-age=86400');
readfile($ruta);
