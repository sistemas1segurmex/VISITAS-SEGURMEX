<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
// OJO: NO destruir toda la sesion (session_destroy()) -- se comparte con el
// ERP (mismo dominio, misma cookie, ver bridgeDesdeERP() en includes/auth.php).
// Aqui solo se cierra la sesion de VISITAS; el login del ERP sigue igual.
// Si esta cuenta llego por el puente, al volver a entrar aqui se reconecta
// sola mientras la sesion del ERP siga viva -- igual que cualquier SSO real.
if (!empty($_SESSION['usuario_id'])) {
    cerrarSesionActual(getDB()); // libera el lugar para el límite de sesiones concurrentes
}
unset($_SESSION['usuario_id'], $_SESSION['usuario_nombre'], $_SESSION['usuario_rol']);
header('Location: login.php');
