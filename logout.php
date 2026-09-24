<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

if (!empty($_SESSION['usuario_id'])) {
    cerrarSesionActual(getDB(), (int)$_SESSION['usuario_id']);
}
olvidarUsuarioEnSesion();
if (session_status() === PHP_SESSION_ACTIVE) {
    session_regenerate_id(true);
}
header('Location: login.php');
exit;
