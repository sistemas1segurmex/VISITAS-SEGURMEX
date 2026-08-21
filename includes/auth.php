<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Single sign-on con el ERP (21-ago-2026): el ERP y VISITAS corren en el
 * mismo servidor/dominio y comparten la MISMA cookie de sesion de PHP (el
 * ERP fija cookie path "/" en includes/auth.php; VISITAS usa el default,
 * que tambien es "/"), asi que $_SESSION['erp_user'] (armado por el login
 * del ERP) ya esta disponible aqui sin nada especial. Si esa cuenta es
 * foranea (o Sistemas) se "traduce" a una cuenta de este sistema por
 * email, sin volver a pedir contrasena. Si no existe todavia una fila en
 * visitas.usuarios con ese correo, se crea sola la primera vez.
 * No pisa una sesion ya iniciada directamente aqui (login.php propio).
 */
function bridgeDesdeERP(): void {
    if (!empty($_SESSION['usuario_id'])) return; // ya hay sesion propia de VISITAS
    $erp = $_SESSION['erp_user'] ?? null;
    if (!$erp || empty($erp['email'])) return;
    $esAdminErp = strtolower($erp['rol'] ?? '') === 'sistemas';
    if (empty($erp['es_foraneo']) && !$esAdminErp) return; // ni foraneo ni Sistemas: no aplica el puente

    $db = getDB();
    $stmt = $db->prepare('SELECT id, nombre, rol, activo FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$erp['email']]);
    $u = $stmt->fetch();

    if (!$u) {
        // Primera vez que este usuario del ERP entra a VISITAS: se crea su
        // cuenta aqui. password_hash aleatorio e inservible -- esta cuenta
        // solo puede entrar por este puente, nunca con el login propio de
        // VISITAS usando ese correo (nadie conoce esa contrasena).
        $nombre = trim(($erp['nombre'] ?? '') . ' ' . ($erp['apellidos'] ?? '')) ?: ($erp['usuario'] ?? $erp['email']);
        $rol    = $esAdminErp ? 'admin' : 'vendedor';
        $hash   = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $ins = $db->prepare('INSERT INTO usuarios (nombre, email, password_hash, rol, activo) VALUES (?,?,?,?,1) RETURNING id, nombre, rol, activo');
        $ins->execute([$nombre, $erp['email'], $hash, $rol]);
        $u = $ins->fetch();
    }
    if (!$u['activo']) return; // desactivado en VISITAS: no lo dejamos pasar aunque el ERP lo permita

    $_SESSION['usuario_id']     = $u['id'];
    $_SESSION['usuario_nombre'] = $u['nombre'];
    $_SESSION['usuario_rol']    = $u['rol'];
}

function currentUser(): ?array {
    bridgeDesdeERP();
    if (!isset($_SESSION['usuario_id'])) return null;
    return [
        'id'     => $_SESSION['usuario_id'],
        'nombre' => $_SESSION['usuario_nombre'] ?? '',
        'rol'    => $_SESSION['usuario_rol'] ?? '',
    ];
}

function esPeticionApi(): bool {
    return strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false;
}

function requireLogin(): array {
    $u = currentUser();
    if (!$u) {
        if (esPeticionApi()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            die(json_encode(['ok' => false, 'error' => 'No autenticado']));
        }
        header('Location: /visitas/login.php');
        exit;
    }
    return $u;
}

function requireRole(string $rol): array {
    $u = requireLogin();
    if ($u['rol'] !== $rol) {
        if (esPeticionApi()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            die(json_encode(['ok' => false, 'error' => 'Sin permiso para esta acción']));
        }
        http_response_code(403);
        die('No tienes permiso para ver esta página.');
    }
    return $u;
}
