<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/cotizador_helpers.php';

requireRole('vendedor');

// Catálogo completo, el mismo del cotizador del ERP: son pocas decenas de
// modelos, así que se manda de una vez y el filtro se hace en pantalla.
jsonResponse(['ok' => true, 'catalogo' => array_values(catalogoCotizableErp(configCotizadorErp()))]);
