<?php
// api/foto.php?checkin_id=X  -- foto de evidencia de un check-in de cita
// api/foto.php?parada_id=X&tipo=entrada|salida -- foto de una parada de
//   prospección (ver vendedor/prospeccion.php)
// api/foto.php?cancelacion=ID_CITA -- evidencia opcional que se adjuntó al
//   cancelar la cita (ruta guardada en la bitácora, ver api/cancelar_cita.php)
// No expone la carpeta uploads directamente: solo el admin, o el vendedor
// dueño de esa visita/parada, pueden verla, y hay que estar con sesión
// iniciada.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$u = requireLogin();
$db = getDB();

$checkinId    = (int)($_GET['checkin_id'] ?? 0);
$paradaId     = (int)($_GET['parada_id'] ?? 0);
$cancelacionId = (int)($_GET['cancelacion'] ?? 0);

if ($cancelacionId) {
    $stmt = $db->prepare(
        "SELECT b.cambios->'" . LLAVE_EVIDENCIA_CANCELACION . "'->>1 AS foto_path, c.vendedor_id
         FROM citas c
         JOIN bitacora_cambios b ON b.entidad = 'cita' AND b.entidad_id = c.id AND b.accion = 'baja'
         WHERE c.id = ? AND c.estado = 'cancelada'
         ORDER BY b.id DESC LIMIT 1"
    );
    $stmt->execute([$cancelacionId]);
    $row = $stmt->fetch();
    // La ruta sale de la bitácora: solo se acepta si cae en la carpeta de
    // evidencias, con un nombre de archivo simple.
    if ($row && $row['foto_path'] && !preg_match('#^' . DIR_EVIDENCIA_CANCELACION . '/[A-Za-z0-9_.-]+$#', $row['foto_path'])) {
        $row['foto_path'] = null;
    }
} elseif ($paradaId) {
    $tipo = ($_GET['tipo'] ?? '') === 'salida' ? 'salida' : 'entrada';
    $stmt = $db->prepare('SELECT vendedor_id, foto_entrada_path, foto_salida_path FROM prospecciones WHERE id = ?');
    $stmt->execute([$paradaId]);
    $parada = $stmt->fetch();
    $row = $parada ? ['foto_path' => $tipo === 'salida' ? $parada['foto_salida_path'] : $parada['foto_entrada_path'], 'vendedor_id' => $parada['vendedor_id']] : null;
} elseif ($checkinId) {
    $stmt = $db->prepare(
        'SELECT ch.foto_path, c.vendedor_id
         FROM checkins ch JOIN citas c ON c.id = ch.cita_id
         WHERE ch.id = ?'
    );
    $stmt->execute([$checkinId]);
    $row = $stmt->fetch();
} else {
    http_response_code(400);
    die('Falta checkin_id, parada_id o cancelacion');
}

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
