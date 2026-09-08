<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$u  = requireRole('vendedor');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Se suman visitas completadas y la fecha de la última, para que la
    // tarjeta de cada cliente en "Mis clientes" pueda mostrar qué tan
    // reciente es la relación sin tener que golpear otro endpoint.
    // El interés ya no vive en el cliente -- se califica por cita, al
    // registrar la salida (ver api/checkin.php). Aquí se trae el de la
    // visita completada más reciente que sí tenga uno capturado.
    $stmt = $db->prepare(
        "SELECT c.*,
                (SELECT COUNT(*) FROM citas ci WHERE ci.cliente_id = c.id AND ci.estado = 'completada') AS total_visitas,
                (SELECT MAX(fecha_hora) FROM citas ci WHERE ci.cliente_id = c.id AND ci.estado = 'completada') AS ultima_visita,
                (SELECT ci.interes FROM citas ci WHERE ci.cliente_id = c.id AND ci.interes IS NOT NULL ORDER BY ci.fecha_hora DESC LIMIT 1) AS ultimo_interes
         FROM clientes c
         WHERE c.vendedor_id = ?
         ORDER BY c.nombre"
    );
    $stmt->execute([$u['id']]);
    jsonResponse(['ok' => true, 'clientes' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre        = trim($_POST['nombre'] ?? '');
    $calleNumero   = trim($_POST['calle_numero'] ?? '');
    $codigoPostal  = trim($_POST['codigo_postal'] ?? '');
    $colonia       = trim($_POST['colonia'] ?? '');
    $municipio     = trim($_POST['municipio'] ?? '');
    $estado        = trim($_POST['estado'] ?? '');
    $lat           = $_POST['lat'] ?? null;
    $lng           = $_POST['lng'] ?? null;
    $telefono      = trim($_POST['telefono'] ?? '');
    $nombreContacto = trim($_POST['nombre_contacto'] ?? '');
    // Si ya es un cliente conocido (no un prospecto nuevo) se registra
    // directo como "convertido", para no forzarlo a pasar por todo el
    // embudo de ventas.
    $etapaInicial  = ($_POST['ya_es_cliente'] ?? '') === '1' ? 'convertido' : 'prospecto_agregado';
    $tipoCliente   = ($_POST['tipo_cliente'] ?? '') === 'persona' ? 'persona' : 'organizacion';

    if ($nombre === '' || $calleNumero === '') {
        jsonResponse(['ok' => false, 'error' => 'Nombre y dirección son obligatorios'], 400);
    }
    // La ubicación GPS es obligatoria sin excepción: sin ella no se puede
    // verificar el check-in por distancia ni ordenar la cartera por cercanía.
    if ($lat === null || $lat === '' || $lng === null || $lng === '') {
        jsonResponse(['ok' => false, 'error' => 'Marca la ubicación del cliente en el mapa antes de guardar'], 400);
    }

    // Dirección completa y legible, compuesta a partir de las partes
    // capturadas (para no tener que tocar las pantallas que ya muestran
    // "direccion" tal cual, como el check-in o el panel del dueño).
    $partes = array_filter([$calleNumero, $colonia, $municipio, $estado, $codigoPostal]);
    $direccion = implode(', ', $partes);

    $stmt = $db->prepare(
        'INSERT INTO clientes (vendedor_id, nombre, direccion, calle_numero, lat, lng, telefono, codigo_postal, estado, municipio, colonia, etapa, etapa_actualizada_en, tipo_cliente, nombre_contacto)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP,?,?)'
    );
    $stmt->execute([
        $u['id'], $nombre, $direccion, $calleNumero,
        $lat !== '' ? $lat : null, $lng !== '' ? $lng : null,
        $telefono,
        $codigoPostal ?: null, $estado ?: null, $municipio ?: null, $colonia ?: null,
        $etapaInicial, $tipoCliente, $nombreContacto ?: null,
    ]);
    jsonResponse(['ok' => true, 'id' => $db->lastInsertId()]);
}

jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
