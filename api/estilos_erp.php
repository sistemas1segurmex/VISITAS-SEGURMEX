<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/cotizador_helpers.php';

requireRole('vendedor');

$q = trim($_GET['q'] ?? '');
jsonResponse(['ok' => true, 'estilos' => buscarEstilosErp($q)]);
