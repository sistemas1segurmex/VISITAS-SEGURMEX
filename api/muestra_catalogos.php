<?php
// Catálogo de clientes/estilos del ERP, para el formulario de "Solicitar
// muestra" del vendedor -- ver vendedor/solicitar_muestra.php y
// api/muestra_solicitar.php. Le pregunta al ERP directo (mismo servidor de
// oficina), con un secreto compartido (ERP_API_SECRET aquí,
// VISITAS_INTEGRACION_SECRET en el .env del ERP).

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('vendedor');

$baseUrl = envConfig('ERP_API_URL');
$secreto = envConfig('ERP_API_SECRET');
if (!$baseUrl || !$secreto) {
    jsonResponse(['ok' => false, 'error' => 'La solicitud de muestras no está configurada todavía. Avisa a Sistemas.'], 503);
}

try {
    $url = rtrim($baseUrl, '/') . '/api/visitas_catalogos.php?' . http_build_query(['secreto' => $secreto]);
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 5]]);
    $resp = @file_get_contents($url, false, $ctx);
    if (!$resp) {
        jsonResponse(['ok' => false, 'error' => 'No se pudo conectar con el ERP. Intenta más tarde.'], 503);
    }
    $data = json_decode($resp, true);
    if (!($data['ok'] ?? false)) {
        jsonResponse(['ok' => false, 'error' => 'El ERP no pudo responder los catálogos.'], 502);
    }
    jsonResponse(['ok' => true, 'clientes' => $data['clientes'] ?? [], 'estilos' => $data['estilos'] ?? []]);
} catch (Throwable $e) {
    error_log('[VISITAS] muestra_catalogos: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'No se pudo conectar con el ERP. Intenta más tarde.'], 503);
}
