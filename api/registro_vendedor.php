<?php
// Registro público de un vendedor nuevo a partir de un link de invitación
// de un solo uso (ver registro_vendedor.php). Sin login -- lo abre alguien
// que todavía no tiene cuenta. La cuenta queda creada con activo=0
// (pendiente de aprobación); el admin la activa desde admin/usuarios.php.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

$token    = trim($_POST['token'] ?? '');
$nombre   = trim($_POST['nombre'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$password = $_POST['password'] ?? '';

if ($token === '') {
    jsonResponse(['ok' => false, 'error' => 'Falta el link de invitación'], 400);
}
if ($nombre === '' || $telefono === '' || strlen($password) < 6) {
    jsonResponse(['ok' => false, 'error' => 'Llena tu nombre, teléfono y una contraseña de al menos 6 caracteres'], 400);
}
if (!preg_match('/^\d{10}$/', $telefono)) {
    jsonResponse(['ok' => false, 'error' => 'El teléfono debe ser numérico, a 10 dígitos'], 400);
}
if (empty($_FILES['foto']) || $_FILES['foto']['error'] === UPLOAD_ERR_NO_FILE) {
    jsonResponse(['ok' => false, 'error' => 'Sube tu foto de perfil'], 400);
}
if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['ok' => false, 'error' => 'Error al subir la foto'], 400);
}
if ($_FILES['foto']['size'] > 5 * 1024 * 1024) {
    jsonResponse(['ok' => false, 'error' => 'La foto no debe pesar más de 5 MB'], 400);
}
$infoFoto = @getimagesize($_FILES['foto']['tmp_name']);
if ($infoFoto === false) {
    jsonResponse(['ok' => false, 'error' => 'El archivo no es una imagen válida'], 400);
}

$db->beginTransaction();
try {
    // Reclama la invitación de forma atómica: si otra petición ya la usó (o
    // ya venció) justo antes, esta UPDATE no afecta ninguna fila y se aborta
    // aquí -- así dos envíos simultáneos con el mismo link nunca pueden
    // crear dos cuentas.
    $stmt = $db->prepare(
        "UPDATE invitaciones_vendedor SET usado_en = NOW()
         WHERE token = ? AND usado_en IS NULL AND expira_en > NOW()
         RETURNING id, email"
    );
    $stmt->execute([$token]);
    $invitacion = $stmt->fetch();
    if (!$invitacion) {
        $db->rollBack();
        jsonResponse(['ok' => false, 'error' => 'Este link ya no es válido (usado o vencido). Pide uno nuevo.'], 410);
    }

    $existe = $db->prepare('SELECT id FROM usuarios WHERE email = ?');
    $existe->execute([$invitacion['email']]);
    if ($existe->fetch()) {
        $db->rollBack();
        jsonResponse(['ok' => false, 'error' => 'Ya existe una cuenta con ese correo'], 409);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare(
        "INSERT INTO usuarios (nombre, email, password_hash, rol, telefono, activo)
         VALUES (?, ?, ?, 'vendedor', ?, 0) RETURNING id"
    );
    $stmt->execute([$nombre, $invitacion['email'], $hash, $telefono]);
    $nuevoId = (int)$stmt->fetchColumn();

    $ext    = image_type_to_extension($infoFoto[2], false) ?: 'jpg';
    $nombreArchivo = 'usuario_' . $nuevoId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destinoDir = __DIR__ . '/../uploads/usuarios';
    if (!is_dir($destinoDir)) @mkdir($destinoDir, 0775, true);
    $destino = $destinoDir . '/' . $nombreArchivo;

    // Mismo respaldo que api/checkin.php: en el navegador del celular
    // move_uploaded_file() a veces no basta (permisos, PHP-FPM, etc.), así
    // que se intenta copy() y por último leer/escribir el archivo a mano
    // antes de darse por vencido -- y si aun así falla, se dice por qué en
    // vez de un genérico "no se pudo".
    $guardado = @move_uploaded_file($_FILES['foto']['tmp_name'], $destino);
    if (!$guardado) {
        $guardado = @copy($_FILES['foto']['tmp_name'], $destino);
    }
    if (!$guardado && is_readable($_FILES['foto']['tmp_name'])) {
        $contenido = @file_get_contents($_FILES['foto']['tmp_name']);
        if ($contenido !== false) {
            $guardado = (@file_put_contents($destino, $contenido) !== false);
        }
    }
    if (!$guardado) {
        $db->rollBack();
        $lastErr = error_get_last();
        $msgErr = !empty($lastErr['message']) ? ' (' . $lastErr['message'] . ')' : '';
        jsonResponse(['ok' => false, 'error' => 'No se pudo guardar la foto en el servidor' . $msgErr], 500);
    }
    $db->prepare('UPDATE usuarios SET foto_path = ? WHERE id = ?')
       ->execute(['uploads/usuarios/' . $nombreArchivo, $nuevoId]);

    $db->prepare('UPDATE invitaciones_vendedor SET usuario_creado_id = ? WHERE id = ?')
       ->execute([$nuevoId, $invitacion['id']]);

    $db->commit();
    jsonResponse(['ok' => true]);
} catch (Throwable $e) {
    $db->rollBack();
    error_log('[VISITAS] registro_vendedor: ' . $e->getMessage());
    if (stripos($e->getMessage(), 'unique') !== false) {
        jsonResponse(['ok' => false, 'error' => 'Ya existe una cuenta con ese correo'], 409);
    }
    jsonResponse(['ok' => false, 'error' => 'No se pudo completar el registro. Intenta de nuevo.'], 500);
}
