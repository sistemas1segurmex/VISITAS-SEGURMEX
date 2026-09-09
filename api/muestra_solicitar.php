<?php
// Un vendedor externo (solo tiene cuenta en Visitas, nunca en el ERP) pide
// una muestra desde aquí. La solicitud se crea DENTRO del ERP -- misma
// tabla, mismo folio, mismo flujo de autorización/Diseño/Producción que
// cualquier otra -- vía api/visitas_muestra.php del ERP (mismo servidor de
// oficina), protegido con un secreto compartido.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$u = requireRole('vendedor');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

$baseUrl = envConfig('ERP_API_URL');
$secreto = envConfig('ERP_API_SECRET');
if (!$baseUrl || !$secreto) {
    jsonResponse(['ok' => false, 'error' => 'La solicitud de muestras no está configurada todavía. Avisa a Sistemas.'], 503);
}

$idCliente = (int)($_POST['id_cliente'] ?? 0);
$idEstilo  = (int)($_POST['id_estilo_base'] ?? 0);
$direccion = trim($_POST['destino_direccion'] ?? '');
if (!$idCliente || !$idEstilo) {
    jsonResponse(['ok' => false, 'error' => 'Selecciona un cliente y un estilo'], 400);
}
if ($direccion === '') {
    jsonResponse(['ok' => false, 'error' => 'Indica la dirección de entrega'], 400);
}

// El correo real del vendedor (currentUser() solo trae id/nombre/rol de la
// sesión, no el correo) -- es lo que liga su cuenta "sombra" en el ERP.
$db = getDB();
$stmt = $db->prepare('SELECT email FROM usuarios WHERE id = ?');
$stmt->execute([$u['id']]);
$email = $stmt->fetchColumn();
if (!$email) {
    jsonResponse(['ok' => false, 'error' => 'No se encontró tu correo en el sistema.'], 500);
}

$adendum = [];
if (($_POST['tipo'] ?? '') === 'variante') {
    $categorias = json_decode($_POST['adendum'] ?? '[]', true);
    if (is_array($categorias)) $adendum = $categorias;
}

$payload = [
    'secreto'           => $secreto,
    'email'             => $email,
    'nombre'            => $u['nombre'],
    'id_cliente'        => $idCliente,
    'id_estilo_base'    => $idEstilo,
    'talla'             => trim($_POST['talla'] ?? ''),
    'fecha_promesa'     => trim($_POST['fecha_promesa'] ?? ''),
    'tipo'              => ($_POST['tipo'] ?? '') === 'variante' ? 'variante' : 'identico',
    'destino_direccion' => $direccion,
    'adendum'           => $adendum,
];

try {
    $url = rtrim($baseUrl, '/') . '/api/visitas_muestra.php';
    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\n",
        'content' => json_encode($payload),
        'timeout' => 8,
    ]]);
    $resp = @file_get_contents($url, false, $ctx);
    if (!$resp) {
        jsonResponse(['ok' => false, 'error' => 'No se pudo conectar con el ERP. Intenta más tarde.'], 503);
    }
    $data = json_decode($resp, true);
    jsonResponse($data ?: ['ok' => false, 'error' => 'Respuesta inválida del ERP.']);
} catch (Throwable $e) {
    error_log('[VISITAS] muestra_solicitar: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'No se pudo conectar con el ERP. Intenta más tarde.'], 503);
}
