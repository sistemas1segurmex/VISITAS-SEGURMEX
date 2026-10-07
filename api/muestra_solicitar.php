<?php
// Un vendedor externo pide una muestra para uno de SUS clientes/prospectos.
//
// Etapa "solo aviso" (07-oct-2026): la solicitud se guarda AQUÍ, en Visitas
// (muestras_solicitudes), y se le avisa a la responsable de muestras por
// correo y en su campanita -- ya NO se manda al ERP (antes se creaba allá con
// folio, cuenta "sombra" del vendedor y cliente mínimo, y entraba al flujo
// de autorización de Dirección; ese flujo todavía no se usa para externos).
// Ver includes/muestras.php y la migración 20261007120000_muestras_solo_aviso.sql.
// El catálogo de estilos sí se lee del ERP (misma base, esquema public).
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/muestras.php';
require_once __DIR__ . '/../includes/db_erp.php';

$u = requireRole('vendedor');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

$clienteId    = (int)($_POST['cliente_id'] ?? 0);
$itemEstilo   = trim((string)($_POST['id_estilo_base'] ?? '')); // "m:ID" o "e:ID"
$direccion    = trim($_POST['destino_direccion'] ?? '');
$talla        = mb_substr(trim($_POST['talla'] ?? ''), 0, 30);
$fechaPromesa = trim($_POST['fecha_promesa'] ?? '');
$tipo         = ($_POST['tipo'] ?? '') === 'variante' ? 'variante' : 'identico';

if (!$clienteId || !preg_match('/^[me]:\d+$/', $itemEstilo)) {
    jsonResponse(['ok' => false, 'error' => 'Selecciona un cliente y un estilo'], 400);
}
if ($direccion === '') {
    jsonResponse(['ok' => false, 'error' => 'Indica la dirección de entrega'], 400);
}
if ($fechaPromesa !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaPromesa)) {
    jsonResponse(['ok' => false, 'error' => 'La fecha promesa no es válida'], 400);
}

$db = getDB();

// El cliente/prospecto tiene que ser de ESTE vendedor -- nadie pide muestra
// a nombre de un cliente ajeno solo cambiando el id en la petición.
$stmt = $db->prepare('SELECT id, nombre FROM clientes WHERE id = ? AND vendedor_id = ?');
$stmt->execute([$clienteId, $u['id']]);
$cliente = $stmt->fetch();
if (!$cliente) {
    jsonResponse(['ok' => false, 'error' => 'Cliente no encontrado'], 404);
}

// El estilo tiene que estar en el catálogo de la Nueva cotización (modelo
// del cotizador anterior o estilo del ERP); el nombre que se guarda sale de
// ahí, no de lo que mande el navegador.
try {
    $estilo = catalogoParaMuestraErp($itemEstilo);
} catch (Throwable $e) {
    error_log('[VISITAS] muestra_solicitar (estilo): ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'No se pudo validar el estilo. Intenta más tarde.'], 503);
}
if (!$estilo) {
    jsonResponse(['ok' => false, 'error' => 'Ese estilo ya no está disponible. Elige otro.'], 400);
}
$estiloNombre = $estilo['nombre'];

// Color: igual que en la Nueva cotización -- si el modelo tiene varios hay
// que elegir uno de ellos; si tiene uno solo, va ese.
$colores = $estilo['colores'] ?? [];
$color   = trim((string)($_POST['color'] ?? ''));
if (count($colores) > 1) {
    if (!in_array($color, $colores, true)) {
        jsonResponse(['ok' => false, 'error' => 'Elige el color del estilo'], 400);
    }
} else {
    $color = $colores[0] ?? '';
}

// Cambios pedidos (solo variante): mismas categorías que el formulario.
$cambios = [];
if ($tipo === 'variante') {
    $entrada = json_decode($_POST['adendum'] ?? '[]', true);
    foreach (is_array($entrada) ? $entrada : [] as $c) {
        $cat   = (string)($c['categoria'] ?? '');
        $desc  = mb_substr(trim((string)($c['descripcion_cliente'] ?? $c['descripcion'] ?? '')), 0, 500);
        if ($desc === '') continue;
        if ($cat === 'otro') {
            $nombreOtro = mb_substr(trim((string)($c['categoria_otro'] ?? '')), 0, 60);
            if ($nombreOtro === '') continue;
            $cambios[] = ['categoria' => 'otro', 'categoria_otro' => $nombreOtro, 'descripcion' => $desc];
        } elseif (isset(MUESTRA_CATEGORIAS_CAMBIO[$cat])) {
            $cambios[] = ['categoria' => $cat, 'descripcion' => $desc];
        }
    }
    if (!$cambios) {
        jsonResponse(['ok' => false, 'error' => 'Describe al menos un cambio de la variante'], 400);
    }
}

try {
    $db->beginTransaction();
    // Un candado por vendedor para que dos envíos al mismo tiempo no se
    // salten el tope.
    $db->prepare('SELECT pg_advisory_xact_lock(hashtext(?))')->execute(['muestras_vendedor_' . (int)$u['id']]);
    if (muestrasDelMesVendedor($db, (int)$u['id']) >= MUESTRAS_TOPE_MES) {
        $db->rollBack();
        jsonResponse(['ok' => false, 'error' => 'Ya pediste ' . MUESTRAS_TOPE_MES . ' muestras este mes. Podrás pedir otra a partir del día 1 del próximo mes.'], 409);
    }

    $ins = $db->prepare(
        'INSERT INTO muestras_solicitudes
            (vendedor_id, cliente_id, cliente_nombre, id_estilo_erp, id_modelo_legacy, estilo_nombre, color, talla, fecha_promesa, tipo, cambios, destino_direccion)
         VALUES (?,?,?,?,?,?,?,?,?,?,?::jsonb,?) RETURNING id'
    );
    $ins->execute([
        (int)$u['id'], $clienteId, mb_substr($cliente['nombre'], 0, 200), $estilo['id_estilo'], $estilo['id_modelo_legacy'], mb_substr($estiloNombre, 0, 200),
        $color !== '' ? mb_substr($color, 0, 40) : null, $talla ?: null, $fechaPromesa ?: null, $tipo, json_encode($cambios, JSON_UNESCAPED_UNICODE), $direccion,
    ]);
    $id = (int)$ins->fetchColumn();
    $folio = sprintf('MV-%04d', $id);
    $db->prepare('UPDATE muestras_solicitudes SET folio = ? WHERE id = ?')->execute([$folio, $id]);
    $db->prepare("INSERT INTO muestras_solicitudes_historial (solicitud_id, estado, usuario_id) VALUES (?, 'enviada', ?)")
       ->execute([$id, (int)$u['id']]);
    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[VISITAS] muestra_solicitar: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'No se pudo guardar la solicitud. Intenta de nuevo.'], 500);
}

registrarCambio($db, (int)$u['id'], 'muestra', $id, 'alta', "Solicitó la muestra {$folio} para {$cliente['nombre']}");

$s = cargarSolicitudMuestra($db, $id);
notificarNuevaSolicitudMuestra($db, $s);

jsonResponse([
    'ok'          => true,
    'id'          => $id,
    'folio'       => $folio,
    'restantes'   => max(0, MUESTRAS_TOPE_MES - muestrasDelMesVendedor($db, (int)$u['id'])),
]);
