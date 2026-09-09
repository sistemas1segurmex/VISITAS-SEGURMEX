<?php
// Un vendedor externo (solo tiene cuenta en Visitas, nunca en el ERP) pide
// una muestra desde aquí, para uno de SUS clientes/prospectos de Visitas.
// La solicitud se crea DENTRO del ERP -- misma tabla, mismo folio, mismo
// flujo de autorización/Diseño/Producción que cualquier otra -- vía
// api/visitas_muestra.php del ERP (mismo servidor de oficina), protegido
// con un secreto compartido.
//
// El cliente/prospecto de Visitas no necesariamente existe todavía como
// cliente real en el ERP -- la primera vez, el ERP lo crea allá (registro
// mínimo, solo el nombre) y regresa su id; aquí se guarda ese enlace
// (clientes.id_cliente_erp) para no volver a crearlo cada vez.

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

$clienteId = (int)($_POST['cliente_id'] ?? 0);
$idEstilo  = (int)($_POST['id_estilo_base'] ?? 0);
$direccion = trim($_POST['destino_direccion'] ?? '');
if (!$clienteId || !$idEstilo) {
    jsonResponse(['ok' => false, 'error' => 'Selecciona un cliente y un estilo'], 400);
}
if ($direccion === '') {
    jsonResponse(['ok' => false, 'error' => 'Indica la dirección de entrega'], 400);
}

$db = getDB();

// El cliente/prospecto tiene que ser de ESTE vendedor -- mismo criterio
// que el resto del sistema (nadie pide muestra a nombre de un cliente
// ajeno solo cambiando el id en la petición).
$stmt = $db->prepare('SELECT id, nombre, id_cliente_erp FROM clientes WHERE id = ? AND vendedor_id = ?');
$stmt->execute([$clienteId, $u['id']]);
$cliente = $stmt->fetch();
if (!$cliente) {
    jsonResponse(['ok' => false, 'error' => 'Cliente no encontrado'], 404);
}

// El correo real del vendedor (currentUser() solo trae id/nombre/rol de la
// sesión, no el correo) -- es lo que liga su cuenta "sombra" en el ERP.
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
    'id_estilo_base'    => $idEstilo,
    'talla'             => trim($_POST['talla'] ?? ''),
    'fecha_promesa'     => trim($_POST['fecha_promesa'] ?? ''),
    'tipo'              => ($_POST['tipo'] ?? '') === 'variante' ? 'variante' : 'identico',
    'destino_direccion' => $direccion,
    'adendum'           => $adendum,
];
// Si ya sabemos su id real en el ERP, se manda directo; si no, se manda su
// nombre para que el ERP lo cree allá y nos regrese el id nuevo.
if ($cliente['id_cliente_erp']) {
    $payload['id_cliente'] = (int)$cliente['id_cliente_erp'];
} else {
    $payload['cliente_nombre'] = $cliente['nombre'];
}

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
    $data = json_decode($resp, true) ?: ['ok' => false, 'error' => 'Respuesta inválida del ERP.'];

    // Primera vez que este cliente/prospecto de Visitas pide una muestra:
    // el ERP acaba de crear su registro allá -- guardamos el enlace para
    // no volver a crearlo la próxima vez.
    if (($data['ok'] ?? false) && empty($cliente['id_cliente_erp']) && !empty($data['id_cliente_erp'])) {
        $db->prepare('UPDATE clientes SET id_cliente_erp = ? WHERE id = ?')
           ->execute([(int)$data['id_cliente_erp'], $clienteId]);
    }

    jsonResponse($data);
} catch (Throwable $e) {
    error_log('[VISITAS] muestra_solicitar: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'No se pudo conectar con el ERP. Intenta más tarde.'], 503);
}
