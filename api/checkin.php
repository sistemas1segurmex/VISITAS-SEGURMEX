<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$u  = requireRole('vendedor');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

$citaId = (int)($_POST['cita_id'] ?? 0);
$tipo   = $_POST['tipo'] ?? 'entrada';
$lat    = isset($_POST['lat']) ? (float)$_POST['lat'] : 0.0;
$lng    = isset($_POST['lng']) ? (float)$_POST['lng'] : 0.0;

if (!$citaId || !$lat || !$lng || !in_array($tipo, ['entrada', 'salida'], true)) {
    jsonResponse(['ok' => false, 'error' => 'Datos incompletos (cita, GPS o tipo)'], 400);
}

$stmt = $db->prepare(
    'SELECT c.*, cl.lat AS cliente_lat, cl.lng AS cliente_lng
     FROM citas c JOIN clientes cl ON cl.id = c.cliente_id
     WHERE c.id = ? AND c.vendedor_id = ?'
);
$stmt->execute([$citaId, $u['id']]);
$cita = $stmt->fetch();
if (!$cita) {
    jsonResponse(['ok' => false, 'error' => 'Cita no encontrada'], 404);
}

$distancia  = null;
$verificado = 0;
if ($cita['cliente_lat'] !== null && $cita['cliente_lng'] !== null) {
    $distancia  = haversineDistance($lat, $lng, (float)$cita['cliente_lat'], (float)$cita['cliente_lng']);
    $verificado = $distancia <= RADIO_VERIFICACION_METROS ? 1 : 0;
}

$fotoPath = null;
if (!empty($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
    $tmp  = $_FILES['foto']['tmp_name'];
    $info = @getimagesize($tmp);
    if ($info === false) {
        jsonResponse(['ok' => false, 'error' => 'El archivo no es una imagen válida'], 400);
    }
    $ext    = image_type_to_extension($info[2], false) ?: 'jpg';
    $nombre = 'checkin_' . $citaId . '_' . $tipo . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destino = __DIR__ . '/../uploads/checkins/' . $nombre;
    if (!move_uploaded_file($tmp, $destino)) {
        jsonResponse(['ok' => false, 'error' => 'No se pudo guardar la foto'], 500);
    }
    $fotoPath = 'uploads/checkins/' . $nombre;
}

$stmt = $db->prepare(
    'INSERT INTO checkins (cita_id, tipo, lat, lng, distancia_metros, foto_path, verificado) VALUES (?,?,?,?,?,?,?)'
);
$stmt->execute([$citaId, $tipo, $lat, $lng, $distancia, $fotoPath, $verificado]);

$nuevoEstado = $tipo === 'entrada' ? 'en_curso' : 'completada';
$db->prepare('UPDATE citas SET estado = ? WHERE id = ?')->execute([$nuevoEstado, $citaId]);

if ($tipo === 'entrada' && !$verificado && $distancia !== null) {
    $db->prepare('INSERT INTO alertas (vendedor_id, cita_id, tipo, mensaje) VALUES (?,?,?,?)')
       ->execute([
           $u['id'],
           $citaId,
           'fuera_de_zona',
           'Check-in a ' . round($distancia) . ' m del cliente (fuera del margen permitido de ' . RADIO_VERIFICACION_METROS . ' m).',
       ]);
}

jsonResponse([
    'ok'               => true,
    'verificado'       => (bool)$verificado,
    'distancia_metros' => $distancia !== null ? round($distancia) : null,
    'foto'             => $fotoPath,
]);
