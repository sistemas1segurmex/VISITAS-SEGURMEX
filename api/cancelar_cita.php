<?php
// Cancela una cita propia, solo si todavía sigue "pendiente" (nadie ha
// hecho check-in). Una vez que la visita está en curso o resuelta, ya no
// se puede cancelar desde aquí.
// Todo es opcional además de la cita: motivo_tipo=cliente ("El cliente
// canceló"), motivo (texto libre) y foto (una imagen de evidencia: captura
// de WhatsApp, correo...). El tipo y la ruta de la foto se guardan en la
// bitácora, no en citas -- ver sqlCancelacion() en helpers.php.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$u  = requireRole('vendedor');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

$citaId      = (int)($_POST['cita_id'] ?? 0);
$motivoTexto = mb_substr(trim($_POST['motivo'] ?? ''), 0, 200);
$delCliente  = ($_POST['motivo_tipo'] ?? '') === 'cliente';

if (!$citaId) {
    jsonResponse(['ok' => false, 'error' => 'Cita no válida'], 400);
}

$motivo = $delCliente
    ? CANCELACION_CLIENTE . ($motivoTexto !== '' ? ': ' . $motivoTexto : '')
    : ($motivoTexto !== '' ? $motivoTexto : null);

$stmt = $db->prepare(
    'SELECT c.id, c.estado, cl.nombre AS cliente_nombre FROM citas c
     JOIN clientes cl ON cl.id = c.cliente_id
     WHERE c.id = ? AND c.vendedor_id = ?'
);
$stmt->execute([$citaId, $u['id']]);
$cita = $stmt->fetch();

if (!$cita) {
    jsonResponse(['ok' => false, 'error' => 'Cita no encontrada'], 404);
}

if ($cita['estado'] !== 'pendiente') {
    jsonResponse(['ok' => false, 'error' => 'Esta cita ya no se puede cancelar (ya tiene actividad registrada).'], 400);
}

// Evidencia opcional: se guarda antes del UPDATE para que, si el archivo no
// se puede escribir, la cita no quede cancelada "sin" la foto que mandaron.
$fotoPath = null;
$archivo  = $_FILES['foto'] ?? null;
if ($archivo && $archivo['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($archivo['error'] === UPLOAD_ERR_INI_SIZE || $archivo['error'] === UPLOAD_ERR_FORM_SIZE
        || $archivo['size'] > MAX_MB_EVIDENCIA_CANCELACION * 1024 * 1024) {
        jsonResponse(['ok' => false, 'error' => 'La imagen es demasiado pesada (máximo ' . MAX_MB_EVIDENCIA_CANCELACION . ' MB).'], 400);
    }
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['ok' => false, 'error' => 'Error al recibir la imagen (código ' . $archivo['error'] . ')'], 400);
    }
    $info = @getimagesize($archivo['tmp_name']);
    $exts = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if ($info === false || !isset($exts[$info[2]])) {
        jsonResponse(['ok' => false, 'error' => 'La evidencia debe ser una imagen (JPG, PNG o WEBP).'], 400);
    }

    $destinoDir = __DIR__ . '/../' . DIR_EVIDENCIA_CANCELACION;
    if (!is_dir($destinoDir)) {
        @mkdir($destinoDir, 0777, true);
    }
    $nombre  = 'cita_' . $citaId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $exts[$info[2]];
    $destino = $destinoDir . '/' . $nombre;
    $guardado = @move_uploaded_file($archivo['tmp_name'], $destino) || @copy($archivo['tmp_name'], $destino);
    if (!$guardado) {
        $lastErr = error_get_last();
        error_log('[VISITAS] cancelar_cita evidencia: ' . ($lastErr['message'] ?? 'sin detalle'));
        jsonResponse(['ok' => false, 'error' => 'No se pudo guardar la imagen en el servidor. Intenta sin evidencia o más tarde.'], 500);
    }
    $fotoPath = DIR_EVIDENCIA_CANCELACION . '/' . $nombre;
}

$cambios = [
    'Estado' => ['Pendiente', 'Cancelada'],
    'Motivo' => [null, $motivo],
];
if ($delCliente) $cambios[LLAVE_TIPO_CANCELACION] = [null, CANCELACION_CLIENTE];
if ($fotoPath)   $cambios[LLAVE_EVIDENCIA_CANCELACION] = [null, $fotoPath];

// UPDATE y bitácora en la misma transacción (no registrarCambio, que se
// traga los errores): la bitácora es lo único que sabe dónde quedó la foto
// y si fue el cliente, así que sin ella no se cancela. Se vuelve a exigir
// "pendiente" por si alguien hizo check-in entre la validación y aquí.
try {
    $db->beginTransaction();
    $upd = $db->prepare("UPDATE citas SET estado = 'cancelada', motivo = ? WHERE id = ? AND vendedor_id = ? AND estado = 'pendiente'");
    $upd->execute([$motivo, $citaId, $u['id']]);
    if ($upd->rowCount() !== 1) {
        $db->rollBack();
        if ($fotoPath) @unlink(__DIR__ . '/../' . $fotoPath);
        jsonResponse(['ok' => false, 'error' => 'Esta cita ya no se puede cancelar.'], 409);
    }
    $db->prepare(
        'INSERT INTO bitacora_cambios (vendedor_id, entidad, entidad_id, accion, resumen, cambios)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([
        $u['id'], 'cita', $citaId, 'baja',
        mb_substr("Canceló la cita con {$cita['cliente_nombre']}", 0, 255),
        json_encode($cambios, JSON_UNESCAPED_UNICODE),
    ]);
    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    if ($fotoPath) @unlink(__DIR__ . '/../' . $fotoPath);
    error_log('[VISITAS] cancelar_cita: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'No se pudo cancelar la cita. Intenta de nuevo.'], 500);
}

jsonResponse(['ok' => true]);
