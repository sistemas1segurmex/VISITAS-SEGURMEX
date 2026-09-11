<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$admin = requireRole('admin');
$db    = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // es_autoregistro: se registró solo vía link de invitación (ver
    // registro_vendedor.php). Combinado con activo=0 en el frontend, marca
    // "pendiente de aprobar" -- distinto de una cuenta real que un admin
    // desactivó a propósito.
    $stmt = $db->query(
        "SELECT u.id, u.nombre, u.email, u.rol, u.telefono, u.estado_operacion, u.activo, u.created_at, u.foto_path,
                EXISTS(SELECT 1 FROM invitaciones_vendedor iv WHERE iv.usuario_creado_id = u.id) AS es_autoregistro
         FROM usuarios u ORDER BY u.rol, u.nombre"
    );
    jsonResponse(['ok' => true, 'usuarios' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

$accion = $_POST['accion'] ?? 'crear';

// Sube la foto de perfil (opcional) a uploads/usuarios y regresa la ruta
// relativa a guardar en foto_path — mismo patrón de validación que ya usa
// api/checkin.php para las fotos de evidencia (valida por contenido real de
// la imagen con getimagesize(), no por la extensión del nombre de archivo).
function subirFotoUsuario(int $usuarioId): ?string {
    if (empty($_FILES['foto']) || $_FILES['foto']['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // no mandaron foto, no es error
    }
    if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['ok' => false, 'error' => 'Error al subir la foto'], 400);
    }
    if ($_FILES['foto']['size'] > 5 * 1024 * 1024) {
        jsonResponse(['ok' => false, 'error' => 'La foto no debe pesar más de 5 MB'], 400);
    }
    $tmp  = $_FILES['foto']['tmp_name'];
    $info = @getimagesize($tmp);
    if ($info === false) {
        jsonResponse(['ok' => false, 'error' => 'El archivo no es una imagen válida'], 400);
    }
    $ext    = image_type_to_extension($info[2], false) ?: 'jpg';
    $nombre = 'usuario_' . $usuarioId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destinoDir = __DIR__ . '/../uploads/usuarios';
    if (!is_dir($destinoDir)) @mkdir($destinoDir, 0775, true);
    $destino = $destinoDir . '/' . $nombre;

    // Mismo respaldo que api/checkin.php y api/registro_vendedor.php:
    // move_uploaded_file() a veces no basta (permisos, PHP-FPM, etc.).
    $guardado = @move_uploaded_file($tmp, $destino);
    if (!$guardado) $guardado = @copy($tmp, $destino);
    if (!$guardado && is_readable($tmp)) {
        $contenido = @file_get_contents($tmp);
        if ($contenido !== false) $guardado = (@file_put_contents($destino, $contenido) !== false);
    }
    if (!$guardado) {
        $lastErr = error_get_last();
        $msgErr = !empty($lastErr['message']) ? ' (' . $lastErr['message'] . ')' : '';
        jsonResponse(['ok' => false, 'error' => 'No se pudo guardar la foto en el servidor' . $msgErr], 500);
    }
    return 'uploads/usuarios/' . $nombre;
}

if ($accion === 'crear') {
    $nombre   = trim($_POST['nombre'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rol      = ($_POST['rol'] ?? 'vendedor') === 'admin' ? 'admin' : 'vendedor';
    $telefono = trim($_POST['telefono'] ?? '');
    $estado   = trim($_POST['estado_operacion'] ?? '');

    if ($nombre === '' || $email === '' || strlen($password) < 6) {
        jsonResponse(['ok' => false, 'error' => 'Nombre, correo y una contraseña de al menos 6 caracteres son obligatorios'], 400);
    }

    try {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare(
            'INSERT INTO usuarios (nombre, email, password_hash, rol, telefono, estado_operacion, activo)
             VALUES (?,?,?,?,?,?,1)'
        );
        $stmt->execute([$nombre, $email, $hash, $rol, $telefono ?: null, $estado ?: null]);
        $nuevoId = (int)$db->lastInsertId();

        $fotoPath = subirFotoUsuario($nuevoId);
        if ($fotoPath) {
            $db->prepare('UPDATE usuarios SET foto_path = ? WHERE id = ?')->execute([$fotoPath, $nuevoId]);
        }

        jsonResponse(['ok' => true, 'id' => $nuevoId]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23505' || stripos($e->getMessage(), 'unique') !== false) {
            jsonResponse(['ok' => false, 'error' => 'Ya existe un usuario con ese correo'], 409);
        }
        throw $e;
    }
}

if ($accion === 'actualizar') {
    $id       = (int)($_POST['id'] ?? 0);
    $nombre   = trim($_POST['nombre'] ?? '');
    $rol      = ($_POST['rol'] ?? 'vendedor') === 'admin' ? 'admin' : 'vendedor';
    $telefono = trim($_POST['telefono'] ?? '');
    $estado   = trim($_POST['estado_operacion'] ?? '');

    if (!$id || $nombre === '') {
        jsonResponse(['ok' => false, 'error' => 'Datos incompletos'], 400);
    }

    $stmt = $db->prepare(
        'UPDATE usuarios SET nombre = ?, rol = ?, telefono = ?, estado_operacion = ? WHERE id = ?'
    );
    $stmt->execute([$nombre, $rol, $telefono ?: null, $estado ?: null, $id]);

    // Reemplaza la foto solo si mandaron una nueva; si no, se queda la que ya tenía.
    $fotoPath = subirFotoUsuario($id);
    if ($fotoPath) {
        $anterior = $db->prepare('SELECT foto_path FROM usuarios WHERE id = ?');
        $anterior->execute([$id]);
        $rutaAnterior = $anterior->fetchColumn();
        $db->prepare('UPDATE usuarios SET foto_path = ? WHERE id = ?')->execute([$fotoPath, $id]);
        if ($rutaAnterior) {
            @unlink(__DIR__ . '/../' . $rutaAnterior);
        }
    }

    jsonResponse(['ok' => true]);
}

if ($accion === 'cambiar_estado') {
    $id     = (int)($_POST['id'] ?? 0);
    $activo = (int)($_POST['activo'] ?? 1) ? 1 : 0;
    if (!$id) jsonResponse(['ok' => false, 'error' => 'Id inválido'], 400);
    if ($id === (int)$admin['id'] && !$activo) {
        jsonResponse(['ok' => false, 'error' => 'No puedes desactivar tu propia cuenta'], 400);
    }
    $db->prepare('UPDATE usuarios SET activo = ? WHERE id = ?')->execute([$activo, $id]);
    jsonResponse(['ok' => true]);
}

if ($accion === 'resetear_password') {
    $id       = (int)($_POST['id'] ?? 0);
    $password = $_POST['password'] ?? '';
    if (!$id || strlen($password) < 6) {
        jsonResponse(['ok' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres'], 400);
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $db->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')->execute([$hash, $id]);
    jsonResponse(['ok' => true]);
}

if ($accion === 'rechazar') {
    // Solo aplica a registros que todavía están pendientes de aprobar
    // (activo=0) -- nunca borra una cuenta real que un admin desactivó a
    // propósito, esa se reactiva o se queda desactivada, no se elimina.
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) jsonResponse(['ok' => false, 'error' => 'Id inválido'], 400);

    $stmt = $db->prepare('SELECT foto_path FROM usuarios WHERE id = ? AND activo = 0');
    $stmt->execute([$id]);
    $usuario = $stmt->fetch();
    if (!$usuario) {
        jsonResponse(['ok' => false, 'error' => 'No se encontró un registro pendiente con ese id'], 404);
    }

    $db->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
    if ($usuario['foto_path']) {
        @unlink(__DIR__ . '/../' . $usuario['foto_path']);
    }
    jsonResponse(['ok' => true]);
}

jsonResponse(['ok' => false, 'error' => 'Acción no reconocida'], 400);
