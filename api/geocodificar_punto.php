<?php
// Calle + colonia exacta de UN punto del recorrido, bajo demanda (se llama
// al pasar el cursor sobre un punto en el mapa en vivo, ver dibujarRutasDia()
// en admin.js) -- nunca en bloque para toda la ruta del día. Ver
// direccionExactaGPS() en includes/helpers.php para la caché y el porqué.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

$lat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
$lng = isset($_GET['lng']) ? (float)$_GET['lng'] : null;
if ($lat === null || $lng === null || $lat === 0.0 || $lng === 0.0) {
    jsonResponse(['ok' => false, 'error' => 'Faltan coordenadas'], 400);
}

jsonResponse(['ok' => true, 'direccion' => direccionExactaGPS($lat, $lng)]);
