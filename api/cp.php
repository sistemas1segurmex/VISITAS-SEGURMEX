<?php
// api/cp.php?cp=XXXXX
// Devuelve estado, municipio y colonias para un código postal (índice SEPOMEX
// local, tomado del mismo catálogo que usa el ERP). Solo requiere sesión
// iniciada (vendedor o admin), no hace falta ser admin.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
requireLogin();

$cp = preg_replace('/\D/', '', $_GET['cp'] ?? '');
if (strlen($cp) !== 5) {
    jsonResponse(['ok' => false, 'error' => 'CP inválido'], 400);
}

$jsonFile = __DIR__ . '/sepomex.json';
if (!file_exists($jsonFile)) {
    jsonResponse(['ok' => false, 'error' => 'Índice SEPOMEX no encontrado'], 500);
}

$data = json_decode(file_get_contents($jsonFile), true);

if (!isset($data[$cp])) {
    jsonResponse(['ok' => false, 'error' => 'CP no encontrado']);
}

$entry = $data[$cp];
jsonResponse([
    'ok'        => true,
    'municipio' => $entry['municipio'],
    'estado'    => $entry['estado'],
    'ciudad'    => $entry['ciudad'] ?? '',
    'colonias'  => $entry['colonias'],
]);
