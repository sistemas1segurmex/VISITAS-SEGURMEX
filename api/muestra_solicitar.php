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
// A quién se entrega: en la dirección del cliente o al propio vendedor (su
// domicilio o una paquetería -- la escribe él). La dirección va en ambos casos.
$entregarA    = ($_POST['entregar_a'] ?? '') === 'vendedor' ? 'vendedor' : 'cliente';
$motivo       = mb_substr(trim((string)($_POST['motivo'] ?? '')), 0, 500);
$notasPlanta  = mb_substr(trim((string)($_POST['notas_planta'] ?? '')), 0, 2000);
$cantidad     = (int)($_POST['cantidad'] ?? 1);
$tiempoPrueba = trim((string)($_POST['tiempo_prueba_dias'] ?? ''));

if (!$clienteId || !preg_match('/^[me]:\d+$/', $itemEstilo)) {
    jsonResponse(['ok' => false, 'error' => 'Selecciona un cliente y un estilo'], 400);
}
if ($motivo === '') {
    jsonResponse(['ok' => false, 'error' => 'Escribe el motivo de la muestra'], 400);
}
if ($cantidad < 1 || $cantidad > 99) {
    jsonResponse(['ok' => false, 'error' => 'La cantidad de pares debe ser de 1 a 99'], 400);
}
if ($tiempoPrueba !== '' && (!ctype_digit($tiempoPrueba) || (int)$tiempoPrueba < 1 || (int)$tiempoPrueba > 365)) {
    jsonResponse(['ok' => false, 'error' => 'El tiempo de prueba debe ser de 1 a 365 días'], 400);
}
$tiempoPrueba = $tiempoPrueba === '' ? null : (int)$tiempoPrueba;
if ($direccion === '') {
    jsonResponse(['ok' => false, 'error' => 'Indica la dirección de entrega'], 400);
}
if ($fechaPromesa !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaPromesa)) {
    jsonResponse(['ok' => false, 'error' => 'La fecha promesa no es válida'], 400);
}

$db = getDB();

// El cliente/prospecto tiene que ser de ESTE vendedor -- nadie pide muestra
// a nombre de un cliente ajeno solo cambiando el id en la petición.
$stmt = $db->prepare('SELECT id, nombre, nombre_contacto, telefono FROM clientes WHERE id = ? AND vendedor_id = ?');
$stmt->execute([$clienteId, $u['id']]);
$cliente = $stmt->fetch();
if (!$cliente) {
    jsonResponse(['ok' => false, 'error' => 'Cliente no encontrado'], 404);
}

// Contacto del cliente: sale de su ficha. Si a la ficha le falta el nombre
// de contacto o el teléfono, el vendedor lo escribe aquí (obligatorio) y se
// guarda también en la ficha -- solo lo que faltaba, nunca se pisa un dato
// que ya tenía. Lo que mande el navegador para un dato que la ficha ya tiene
// se ignora.
$fichaContacto = trim((string)($cliente['nombre_contacto'] ?? ''));
$fichaTelefono = trim((string)($cliente['telefono'] ?? ''));
$llenarFicha   = []; // columna => valor nuevo
$contactoNombre = $fichaContacto;
if ($contactoNombre === '') {
    $contactoNombre = mb_substr(trim((string)($_POST['contacto_nombre'] ?? '')), 0, 150);
    if ($contactoNombre === '') {
        jsonResponse(['ok' => false, 'error' => 'Escribe el nombre de contacto del cliente'], 400);
    }
    $llenarFicha['nombre_contacto'] = $contactoNombre;
}
$contactoTelefono = $fichaTelefono;
if ($contactoTelefono === '') {
    $contactoTelefono = normalizarTelefonoMx((string)($_POST['contacto_telefono'] ?? ''));
    if ($contactoTelefono === '') {
        jsonResponse(['ok' => false, 'error' => 'Escribe el teléfono de contacto del cliente'], 400);
    }
    if ($contactoTelefono === null) {
        jsonResponse(['ok' => false, 'error' => MSG_TELEFONO_INVALIDO], 400);
    }
    $llenarFicha['telefono'] = $contactoTelefono;
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
            (vendedor_id, cliente_id, cliente_nombre, id_estilo_erp, id_modelo_legacy, estilo_nombre, color, talla, fecha_promesa, tipo, cambios, entregar_a, destino_direccion,
             motivo, notas_planta, cantidad, tiempo_prueba_dias, contacto_nombre, contacto_telefono)
         VALUES (?,?,?,?,?,?,?,?,?,?,?::jsonb,?,?,?,?,?,?,?,?) RETURNING id'
    );
    $ins->execute([
        (int)$u['id'], $clienteId, mb_substr($cliente['nombre'], 0, 200), $estilo['id_estilo'], $estilo['id_modelo_legacy'], mb_substr($estiloNombre, 0, 200),
        $color !== '' ? mb_substr($color, 0, 40) : null, $talla ?: null, $fechaPromesa ?: null, $tipo, json_encode($cambios, JSON_UNESCAPED_UNICODE), $entregarA, $direccion,
        $motivo, $notasPlanta !== '' ? $notasPlanta : null, $cantidad, $tiempoPrueba,
        mb_substr($contactoNombre, 0, 150), mb_substr($contactoTelefono, 0, 20),
    ]);
    $id = (int)$ins->fetchColumn();
    $folio = sprintf('MV-%04d', $id);
    $db->prepare('UPDATE muestras_solicitudes SET folio = ? WHERE id = ?')->execute([$folio, $id]);
    $db->prepare("INSERT INTO muestras_solicitudes_historial (solicitud_id, estado, usuario_id) VALUES (?, 'enviada', ?)")
       ->execute([$id, (int)$u['id']]);
    // Completa la ficha del cliente con lo que le faltaba (columnas fijas,
    // no vienen del navegador).
    foreach ($llenarFicha as $col => $valor) {
        $db->prepare("UPDATE clientes SET $col = ? WHERE id = ? AND COALESCE(TRIM($col), '') = ''")
           ->execute([$valor, $clienteId]);
    }
    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[VISITAS] muestra_solicitar: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'No se pudo guardar la solicitud. Intenta de nuevo.'], 500);
}

registrarCambio($db, (int)$u['id'], 'muestra', $id, 'alta', "Solicitó la muestra {$folio} para {$cliente['nombre']}");
if ($llenarFicha) {
    $etiquetas = ['nombre_contacto' => 'Contacto', 'telefono' => 'Teléfono'];
    $diff = [];
    foreach ($llenarFicha as $col => $valor) $diff[$etiquetas[$col]] = [null, $valor];
    registrarCambio($db, (int)$u['id'], 'cliente', $clienteId, 'edicion', "Completó la ficha de {$cliente['nombre']} al pedir la muestra {$folio}", $diff);
}

$s = cargarSolicitudMuestra($db, $id);
notificarNuevaSolicitudMuestra($db, $s);

jsonResponse([
    'ok'          => true,
    'id'          => $id,
    'folio'       => $folio,
    'restantes'   => max(0, MUESTRAS_TOPE_MES - muestrasDelMesVendedor($db, (int)$u['id'])),
]);
