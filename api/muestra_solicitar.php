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

// Cada estilo tiene que estar en el catálogo de la Nueva cotización (modelo
// del cotizador anterior o estilo del ERP); el nombre, los colores y las
// tallas válidas salen de ahí, no de lo que mande el navegador.
try {
    $catalogo = [];
    foreach (catalogoParaMuestraErp() as $it) $catalogo[$it['id']] = $it;
} catch (Throwable $e) {
    error_log('[VISITAS] muestra_solicitar (estilos): ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'No se pudo validar el estilo. Intenta más tarde.'], 503);
}

$estilos   = [];   // ya validados
$totalPares = 0;
$vistos    = [];   // item|color repetidos
foreach (array_values($estilosEntrada) as $i => $en) {
    $n = $i + 1;
    $item = trim((string)($en['item'] ?? ''));
    if (!preg_match('/^[me]:\d+$/', $item)) {
        jsonResponse(['ok' => false, 'error' => "Elige el estilo $n"], 400);
    }
    $estilo = $catalogo[$item] ?? null;
    if (!$estilo) {
        jsonResponse(['ok' => false, 'error' => "El estilo $n ya no está disponible. Elige otro."], 400);
    }
    $nombre = $estilo['clave'];

    // Color: igual que en la Nueva cotización -- si el modelo tiene varios
    // hay que elegir uno de ellos; si tiene uno solo, va ese.
    $colores = $estilo['colores'] ?? [];
    $color   = trim((string)($en['color'] ?? ''));
    if (count($colores) > 1) {
        if (!in_array($color, $colores, true)) {
            jsonResponse(['ok' => false, 'error' => "Elige el color del estilo $nombre"], 400);
        }
    } else {
        $color = $colores[0] ?? '';
    }
    $llave = $item . '|' . $color;
    if (isset($vistos[$llave])) {
        jsonResponse(['ok' => false, 'error' => "El estilo $nombre" . ($color !== '' ? " en $color" : '') . ' está repetido. Pon todas sus tallas en una sola tarjeta.'], 400);
    }
    $vistos[$llave] = true;

    // Tipo y cambios (por estilo).
    $tipo = ($en['tipo'] ?? '') === 'variante' ? 'variante' : 'identico';
    $cambios = [];
    if ($tipo === 'variante') {
        foreach (is_array($en['adendum'] ?? null) ? $en['adendum'] : [] as $c) {
            $cat  = (string)($c['categoria'] ?? '');
            $desc = mb_substr(trim((string)($c['descripcion_cliente'] ?? $c['descripcion'] ?? '')), 0, 500);
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
            jsonResponse(['ok' => false, 'error' => "Describe al menos un cambio de la variante del estilo $nombre"], 400);
        }
    }

    // Tallas: de la lista del estilo (numeración del ERP o la fija de 20 a 35).
    $tallasEntrada = is_array($en['tallas'] ?? null) ? array_values($en['tallas']) : [];
    if (!$tallasEntrada) {
        jsonResponse(['ok' => false, 'error' => "Agrega al menos una talla al estilo $nombre"], 400);
    }
    if (count($tallasEntrada) > MUESTRAS_MAX_TALLAS_POR_ESTILO) {
        jsonResponse(['ok' => false, 'error' => "El estilo $nombre puede llevar hasta " . MUESTRAS_MAX_TALLAS_POR_ESTILO . ' tallas'], 400);
    }
    $tallas = [];
    foreach ($tallasEntrada as $t) {
        $talla = trim((string)($t['talla'] ?? ''));
        if ($talla === '') {
            jsonResponse(['ok' => false, 'error' => "Elige la talla en el estilo $nombre"], 400);
        }
        if (is_numeric($talla)) $talla = textoTallaMuestra($talla);
        if (!in_array($talla, $estilo['tallas'], true)) {
            jsonResponse(['ok' => false, 'error' => "La talla $talla no es válida para el estilo $nombre"], 400);
        }
        if (isset($tallas[$talla])) {
            jsonResponse(['ok' => false, 'error' => "La talla $talla está repetida en el estilo $nombre"], 400);
        }
        $pares = $t['pares'] ?? null;
        if (!is_numeric($pares) || (int)$pares != $pares || (int)$pares < 1) {
            jsonResponse(['ok' => false, 'error' => "Pon cuántos pares de la talla $talla del estilo $nombre (al menos 1)"], 400);
        }
        $tallas[$talla] = [
            'talla' => $talla,
            'pares' => (int)$pares,
            'nota'  => mb_substr(trim((string)($t['nota'] ?? '')), 0, 500),
        ];
        $totalPares += (int)$pares;
    }

    $estilos[] = [
        'nombre'           => mb_substr($estilo['nombre'], 0, 200),
        'id_estilo'        => $estilo['id_estilo'],
        'id_modelo_legacy' => $estilo['id_modelo_legacy'],
        'color'            => $color,
        'tipo'             => $tipo,
        'cambios'          => $cambios,
        'tallas'           => array_values($tallas),
    ];
}
if ($totalPares > MUESTRAS_TOPE_PARES_CLIENTE_MES) {
    jsonResponse(['ok' => false, 'error' => 'Son ' . $totalPares . ' pares y el máximo por cliente es de '
        . MUESTRAS_TOPE_PARES_CLIENTE_MES . ' pares al mes. Quita ' . ($totalPares - MUESTRAS_TOPE_PARES_CLIENTE_MES)
        . ($totalPares - MUESTRAS_TOPE_PARES_CLIENTE_MES === 1 ? ' par.' : ' pares.')], 400);
}

// Resumen que se guarda en la solicitud (bandeja, búsqueda): ver la
// migración 20261022120000_muestras_varios_estilos.sql.
$primero      = $estilos[0];
$unaTalla     = count($estilos) === 1 && count($primero['tallas']) === 1;
$resumenNombre = $primero['nombre'] . (count($estilos) > 1
    ? ' y ' . (count($estilos) - 1) . (count($estilos) === 2 ? ' estilo más' : ' estilos más') : '');
$hayVariante  = false;
foreach ($estilos as $e) if ($e['tipo'] === 'variante') $hayVariante = true;

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

    $insE = $db->prepare(
        'INSERT INTO muestras_solicitudes_estilos (solicitud_id, orden, id_estilo_erp, id_modelo_legacy, estilo_nombre, color, tipo, cambios)
         VALUES (?,?,?,?,?,?,?,?::jsonb) RETURNING id'
    );
    $insT = $db->prepare('INSERT INTO muestras_solicitudes_tallas (estilo_id, orden, talla, pares, nota) VALUES (?,?,?,?,?)');
    foreach ($estilos as $i => $e) {
        $insE->execute([$id, $i + 1, $e['id_estilo'], $e['id_modelo_legacy'], $e['nombre'],
                        $e['color'] !== '' ? mb_substr($e['color'], 0, 40) : null, $e['tipo'],
                        json_encode($e['cambios'], JSON_UNESCAPED_UNICODE)]);
        $estiloId = (int)$insE->fetchColumn();
        foreach ($e['tallas'] as $j => $t) {
            $insT->execute([$estiloId, $j + 1, $t['talla'], $t['pares'], $t['nota'] !== '' ? $t['nota'] : null]);
        }
    }

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

registrarCambio($db, (int)$u['id'], 'muestra', $id, 'alta', "Solicitó la muestra {$folio} (" . textoParesMuestra($totalPares) . ") para {$cliente['nombre']}");
if ($llenarFicha) {
    $etiquetas = ['nombre_contacto' => 'Contacto', 'telefono' => 'Teléfono'];
    $diff = [];
    foreach ($llenarFicha as $col => $valor) $diff[$etiquetas[$col]] = [null, $valor];
    registrarCambio($db, (int)$u['id'], 'cliente', $clienteId, 'edicion', "Completó la ficha de {$cliente['nombre']} al pedir la muestra {$folio}", $diff);
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
