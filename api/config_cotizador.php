<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/cotizador_helpers.php';

requireAnyRole(['vendedor', 'muestras']);

jsonResponse(['ok' => true, 'config' => configCotizadorErp()]);
