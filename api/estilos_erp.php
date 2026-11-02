<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/cotizador_helpers.php';

requireAnyRole(['vendedor', 'muestras']);

// Catálogo completo, el mismo del cotizador del ERP: son pocas decenas de
// modelos, así que se manda de una vez y el filtro se hace en pantalla.
// ?cotizacion=ID (al «Corregir»): incluye los Estilos del ERP que ya traía esa
// cotización aunque estén ocultos, para no perder renglones.
$conservar = !empty($_GET['cotizacion']) ? idsEstilosErpDeCotizacionErp(getDBErp(), (int)$_GET['cotizacion']) : [];
jsonResponse(['ok' => true, 'catalogo' => array_values(catalogoCotizableErp(configCotizadorErp(), $conservar)), 'atributos' => atributosSeguridadErp()]);
