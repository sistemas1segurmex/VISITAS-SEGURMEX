<?php
// Lista de las solicitudes de muestra que este vendedor ya ha pedido --
// se piden y se procesan del lado del ERP (ver api/muestra_solicitar.php),
// así que aquí solo se consulta de vuelta, vía el mismo endpoint protegido
// por secreto compartido (api/visitas_muestras_lista.php del ERP).

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$u  = requireRole('vendedor');
$db = getDB();

$baseUrl = envConfig('ERP_API_URL');
$secreto = envConfig('ERP_API_SECRET');
if (!$baseUrl || !$secreto) {
    jsonResponse(['ok' => false, 'error' => 'La solicitud de muestras no está configurada todavía. Avisa a Sistemas.'], 503);
}

$stmt = $db->prepare('SELECT email FROM usuarios WHERE id = ?');
$stmt->execute([$u['id']]);
$email = $stmt->fetchColumn();
if (!$email) {
    jsonResponse(['ok' => false, 'error' => 'No se encontró tu correo en el sistema.'], 500);
}

try {
    $url = rtrim($baseUrl, '/') . '/api/visitas_muestras_lista.php?' . http_build_query([
        'secreto' => $secreto,
        'email'   => $email,
    ]);
    $ctx  = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 6]]);
    $resp = @file_get_contents($url, false, $ctx);
    $data = $resp ? json_decode($resp, true) : null;
    if (!($data['ok'] ?? false)) {
        jsonResponse(['ok' => false, 'error' => 'No se pudo conectar con el ERP. Intenta más tarde.'], 503);
    }
    jsonResponse(['ok' => true, 'muestras' => $data['muestras'] ?? []]);
} catch (Throwable $e) {
    error_log('[VISITAS] muestras_listar: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'No se pudo conectar con el ERP. Intenta más tarde.'], 503);
}
