<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$u = currentUser();
if (!$u) {
    header('Location: login.php');
    exit;
}
header('Location: ' . rutaInicioPorRol($u['rol']));
