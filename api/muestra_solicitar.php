<?php
// Un vendedor externo pide una muestra para uno de SUS clientes/prospectos.
// Desde el 22-oct-2026 una solicitud puede llevar varios estilos y cada
// estilo varias tallas (ver la migración 20261022120000_muestras_varios_estilos.sql);
// tope: MUESTRAS_TOPE_PARES_CLIENTE_MES pares por cliente al mes.
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
require_once __DIR__ . '/../includes/coincidencias.php';
require_once __DIR__ . '/../includes/db_erp.php';

$u = requireRole('vendedor');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

$clienteId    = (int)($_POST['cliente_id'] ?? 0);
$direccion    = trim($_POST['destino_direccion'] ?? '');
$fechaPromesa = trim($_POST['fecha_promesa'] ?? '');
// A quién se entrega: en la dirección del cliente o al propio vendedor (su
// domicilio o una paquetería -- la escribe él). La dirección va en ambos casos.
$entregarA    = ($_POST['entregar_a'] ?? '') === 'vendedor' ? 'vendedor' : 'cliente';
$motivo       = mb_substr(trim((string)($_POST['motivo'] ?? '')), 0, 500);
$notasPlanta  = mb_substr(trim((string)($_POST['notas_planta'] ?? '')), 0, 2000);
$tiempoPrueba = trim((string)($_POST['tiempo_prueba_dias'] ?? ''));
// Varios estilos con sus tallas (22-oct-2026), JSON:
//   [{item:"m:12"|"e:34", color, tipo:"identico"|"variante",
//     adendum:[{categoria, categoria_otro?, descripcion_cliente}],
//     tallas:[{talla, pares, nota}]}]
$estilosEntrada = json_decode((string)($_POST['estilos'] ?? '[]'), true);
if (!is_array($estilosEntrada)) $estilosEntrada = [];

if (!$clienteId) {
    jsonResponse(['ok' => false, 'error' => 'Elige el cliente o prospecto'], 400);
}
if (!$estilosEntrada) {
    jsonResponse(['ok' => false, 'error' => 'Agrega al menos un estilo'], 400);
}
if (count($estilosEntrada) > MUESTRAS_MAX_ESTILOS) {
    jsonResponse(['ok' => false, 'error' => 'Puedes pedir hasta ' . MUESTRAS_MAX_ESTILOS . ' estilos en una solicitud'], 400);
}
if ($motivo === '') {
    jsonResponse(['ok' => false, 'error' => 'Escribe el motivo de la muestra'], 400);
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
exigirClienteTrabajable($db, $clienteId); // en revisión / descartado

// Contacto del cliente: el formulario lo prellena con su ficha y el vendedor
// lo puede corregir (los dos son obligatorios). Lo que cambió respecto a la
// ficha se guarda también en ella.
$fichaContacto = trim((string)($cliente['nombre_contacto'] ?? ''));
$fichaTelefono = trim((string)($cliente['telefono'] ?? ''));
$llenarFicha   = []; // columna => [valor anterior, valor nuevo]
$contactoNombre = mb_substr(trim((string)($_POST['contacto_nombre'] ?? '')), 0, 150);
if ($contactoNombre === '') {
    jsonResponse(['ok' => false, 'error' => 'Escribe el nombre de contacto del cliente'], 400);
}
if ($contactoNombre !== $fichaContacto) {
    $llenarFicha['nombre_contacto'] = [$fichaContacto ?: null, $contactoNombre];
}
$contactoTelefono = normalizarTelefonoMx((string)($_POST['contacto_telefono'] ?? ''));
if ($contactoTelefono === '') {
    jsonResponse(['ok' => false, 'error' => 'Escribe el teléfono de contacto del cliente'], 400);
}
if ($contactoTelefono === null) {
    jsonResponse(['ok' => false, 'error' => MSG_TELEFONO_INVALIDO], 400);
}
if ($contactoTelefono !== $fichaTelefono) {
    $llenarFicha['telefono'] = [$fichaTelefono ?: null, $contactoTelefono];
}
// Cada estilo tiene que estar en el catálogo de la Nueva cotización (ver
// validarEstilosMuestraOSalir() en includes/muestras.php; la usa también la
// corrección de la responsable). Si algo está mal, contesta el error y sale.
['estilos' => $estilos, 'total' => $totalPares, 'resumen' => $resumenNombre, 'variante' => $hayVariante]
    = validarEstilosMuestraOSalir($estilosEntrada);
$primero  = $estilos[0];
$unaTalla = count($estilos) === 1 && count($primero['tallas']) === 1;

try {
    $db->beginTransaction();
    // Un candado por cliente para que dos envíos al mismo tiempo no se
    // salten el tope de pares del mes.
    $db->prepare('SELECT pg_advisory_xact_lock(hashtext(?))')->execute(['muestras_cliente_' . $clienteId]);
    $usados = paresMuestraDelMesCliente($db, $clienteId);
    if ($usados + $totalPares > MUESTRAS_TOPE_PARES_CLIENTE_MES) {
        $db->rollBack();
        $quedan = max(0, MUESTRAS_TOPE_PARES_CLIENTE_MES - $usados);
        jsonResponse(['ok' => false, 'error' => 'Este cliente ya tiene ' . $usados . ' de ' . MUESTRAS_TOPE_PARES_CLIENTE_MES
            . ' pares de muestra este mes. ' . ($quedan === 0
                ? 'Ya no le quedan pares: podrás pedir más a partir del día 1 del próximo mes.'
                : 'Con esta solicitud serían ' . ($usados + $totalPares) . ': quita ' . ($usados + $totalPares - MUESTRAS_TOPE_PARES_CLIENTE_MES)
                  . ($usados + $totalPares - MUESTRAS_TOPE_PARES_CLIENTE_MES === 1 ? ' par' : ' pares') . ' o pídela el próximo mes.')], 409);
    }

    $ins = $db->prepare(
        'INSERT INTO muestras_solicitudes
            (vendedor_id, cliente_id, cliente_nombre, id_estilo_erp, id_modelo_legacy, estilo_nombre, color, talla, fecha_promesa, tipo, cambios, entregar_a, destino_direccion,
             motivo, notas_planta, cantidad, tiempo_prueba_dias, contacto_nombre, contacto_telefono)
         VALUES (?,?,?,?,?,?,?,?,?,?,?::jsonb,?,?,?,?,?,?,?,?) RETURNING id'
    );
    $ins->execute([
        (int)$u['id'], $clienteId, mb_substr($cliente['nombre'], 0, 200), $primero['id_estilo'], $primero['id_modelo_legacy'], mb_substr($resumenNombre, 0, 200),
        count($estilos) === 1 && $primero['color'] !== '' ? mb_substr($primero['color'], 0, 40) : null,
        $unaTalla ? $primero['tallas'][0]['talla'] : null, $fechaPromesa ?: null, $hayVariante ? 'variante' : 'identico',
        json_encode(count($estilos) === 1 ? $primero['cambios'] : [], JSON_UNESCAPED_UNICODE), $entregarA, $direccion,
        $motivo, $notasPlanta !== '' ? $notasPlanta : null, $totalPares, $tiempoPrueba,
        mb_substr($contactoNombre, 0, 150), mb_substr($contactoTelefono, 0, 20),
    ]);
    $id = (int)$ins->fetchColumn();
    $folio = sprintf('MV-%04d', $id);
    $db->prepare('UPDATE muestras_solicitudes SET folio = ? WHERE id = ?')->execute([$folio, $id]);

    guardarEstilosMuestra($db, $id, $estilos);

    $db->prepare("INSERT INTO muestras_solicitudes_historial (solicitud_id, estado, usuario_id) VALUES (?, 'enviada', ?)")
       ->execute([$id, (int)$u['id']]);
    // Actualiza la ficha del cliente con lo que corrigió o le faltaba
    // (columnas fijas, no vienen del navegador).
    foreach ($llenarFicha as $col => [, $valor]) {
        $db->prepare("UPDATE clientes SET $col = ? WHERE id = ?")
           ->execute([$valor, $clienteId]);
    }
    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[VISITAS] muestra_solicitar: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'No se pudo guardar la solicitud. Intenta de nuevo.'], 500);
}

registrarCambio($db, (int)$u['id'], 'muestra', $id, 'alta', "Solicitó la muestra {$folio} (" . textoParesMuestra($totalPares) . ") para {$cliente['nombre']}");
if ($llenarFicha) {
    $etiquetas = ['nombre_contacto' => 'Contacto', 'telefono' => 'Teléfono'];
    $diff = [];
    foreach ($llenarFicha as $col => $par) $diff[$etiquetas[$col]] = $par;
    registrarCambio($db, (int)$u['id'], 'cliente', $clienteId, 'edicion', "Actualizó la ficha de {$cliente['nombre']} al pedir la muestra {$folio}", $diff);
}

$s = cargarSolicitudMuestra($db, $id);
notificarNuevaSolicitudMuestra($db, $s);

jsonResponse([
    'ok'                => true,
    'id'                => $id,
    'folio'             => $folio,
    'pares'             => $totalPares,
    'pares_restantes'   => max(0, MUESTRAS_TOPE_PARES_CLIENTE_MES - paresMuestraDelMesCliente($db, $clienteId)),
]);
