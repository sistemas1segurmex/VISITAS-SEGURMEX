<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function currentUser(): ?array {
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
