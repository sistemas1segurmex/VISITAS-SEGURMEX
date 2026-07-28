<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$admin = requireRole('admin');
$db    = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->query(
        "SELECT id, nombre, email, rol, telefono, estado_operacion, activo, created_at
         FROM usuarios ORDER BY rol, nombre"
    );
    jsonResponse(['ok' => true, 'usuarios' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

$accion = $_POST['accion'] ?? 'crear';

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
        jsonResponse(['ok' => true, 'id' => $db->lastInsertId()]);
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

jsonResponse(['ok' => false, 'error' => 'Acción no reconocida'], 400);
