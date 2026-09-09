<?php
// Catálogos para el formulario de "Solicitar muestra" del vendedor -- ver
// vendedor/solicitar_muestra.php y api/muestra_solicitar.php.
//
// "Clientes" son los PROPIOS clientes/prospectos de este vendedor en
// Visitas (no el catálogo del ERP -- un vendedor externo debe poder pedir
// una muestra para ganarse a un prospecto que todavía ni siquiera es
// cliente formal allá; api/muestra_solicitar.php se encarga de crear ese
// registro en el ERP si hace falta). "Estilos" sí es el catálogo real de
// productos del ERP -- eso no tiene equivalente de "prospecto".

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$u  = requireRole('vendedor');
$db = getDB();

$stmt = $db->prepare(
    "SELECT id, nombre, etapa FROM clientes WHERE vendedor_id = ? ORDER BY nombre"
);
$stmt->execute([$u['id']]);
$clientes = $stmt->fetchAll();

$baseUrl = envConfig('ERP_API_URL');
$secreto = envConfig('ERP_API_SECRET');
$estilos = [];
$errorEstilos = null;
if (!$baseUrl || !$secreto) {
    $errorEstilos = 'La solicitud de muestras no está configurada todavía. Avisa a Sistemas.';
} else {
    try {
        $url = rtrim($baseUrl, '/') . '/api/visitas_catalogos.php?' . http_build_query(['secreto' => $secreto]);
        $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 5]]);
        $resp = @file_get_contents($url, false, $ctx);
        $data = $resp ? json_decode($resp, true) : null;
        if ($data['ok'] ?? false) {
            $estilos = $data['estilos'] ?? [];
        } else {
            $errorEstilos = 'No se pudo conectar con el ERP. Intenta más tarde.';
        }
    } catch (Throwable $e) {
        error_log('[VISITAS] muestra_catalogos: ' . $e->getMessage());
        $errorEstilos = 'No se pudo conectar con el ERP. Intenta más tarde.';
    }
}

jsonResponse(['ok' => true, 'clientes' => $clientes, 'estilos' => $estilos, 'error_estilos' => $errorEstilos]);
