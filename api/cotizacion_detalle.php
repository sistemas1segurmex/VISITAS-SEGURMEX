<?php
/**
 * Detalle de una cotización del vendedor foráneo -- ver
 * docs/arquitectura-cotizaciones-foraneos.md (repo del ERP). Lee/escribe
 * directo en el esquema "public" del ERP igual que api/cotizaciones.php.
 *
 * GET  ?id=X                          -> ficha completa (cotización, renglones,
 *                                        historial, link público si existe).
 * GET  ?id=X&para_corregir=1         -> además, los datos para llenar Nueva
 *                                        cotización al «Corregir» (cliente de
 *                                        VISITAS, renglones, condiciones).
 * POST accion=cambiar_estado, id, nuevo_estado -> mueve el estado y regresa la
 *                                        ficha actualizada.
 *
 * Propiedad: solo puede ver/mover una cotización cuyo id_vendedor sea el
 * vendedor espejo del foráneo logueado (mismo correo).
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/cotizador_helpers.php';

$u  = requireAnyRole(['vendedor', 'muestras']);
$db = getDB();
$dbErp = getDBErp();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$id) {
    jsonResponse(['ok' => false, 'error' => 'Cotización no encontrada.'], 404);
}

if ($u['rol'] === 'muestras') {
    // La responsable de muestras ve y mueve las cotizaciones de cualquier
    // vendedor de VISITAS (mismo cruce por correo que api/cotizaciones.php).
    $stmtV = $db->prepare(
        "SELECT co.id_vendedor FROM public.cotizaciones co
         JOIN public.usuarios eu ON eu.id = co.id_vendedor
         JOIN usuarios u ON LOWER(u.email) = LOWER(eu.email) AND u.rol = 'vendedor'
         WHERE co.id = ?"
    );
    $stmtV->execute([$id]);
    $idVendedorErp = (int)$stmtV->fetchColumn();
} else {
    $stmtU = $db->prepare('SELECT email FROM usuarios WHERE id = ?');
    $stmtU->execute([$u['id']]);
    $email = $stmtU->fetchColumn();
    if (!$email) {
        jsonResponse(['ok' => false, 'error' => 'No se encontró tu correo registrado.'], 500);
    }
    $idVendedorErp = obtenerOCrearVendedorErp($email, $u['nombre']);
}

function cargarCotizacion(PDO $dbErp, int $id, int $idVendedorErp): ?array {
    $stmt = $dbErp->prepare('SELECT * FROM cotizaciones WHERE id = ? AND id_vendedor = ?');
    $stmt->execute([$id, $idVendedorErp]);
    return $stmt->fetch() ?: null;
}

$cot = cargarCotizacion($dbErp, $id, $idVendedorErp);
if (!$cot) {
    jsonResponse(['ok' => false, 'error' => 'Cotización no encontrada.'], 404);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cambiar_estado') {
    $nuevoEstado = trim($_POST['nuevo_estado'] ?? '');
    $res = cambiarEstadoCotizacionErp($dbErp, $cot, $nuevoEstado, $idVendedorErp);
    if (!$res['ok']) {
        jsonResponse(['ok' => false, 'error' => $res['msg']], 400);
    }
    $cot = cargarCotizacion($dbErp, $id, $idVendedorErp);
}

$detalle = $dbErp->prepare('SELECT * FROM cotizacion_detalle WHERE id_cotizacion = ? ORDER BY orden');
$detalle->execute([$id]);
$detalle = $detalle->fetchAll();

// Foto y plazo de entrega de cada modelo (los del catálogo anterior; los Dickies traen plazo).
$detalle = agregarFotoYEntregaDetalleErp($dbErp, $detalle);

$historial = $dbErp->prepare("
    SELECT h.estado_anterior, h.estado_nuevo, h.origen, h.created_at, u.nombre, u.apellidos
    FROM cotizacion_historial h
    LEFT JOIN usuarios u ON u.id = h.id_usuario
    WHERE h.id_cotizacion = ?
    ORDER BY h.created_at DESC, h.id DESC
");
$historial->execute([$id]);
$historial = $historial->fetchAll();

$urlPublica = null;
$urlPdf = null;
if (!empty($cot['token_publico'])) {
    $urlPublica = urlPublicaCotizacionErp($cot['token_publico']);
    $urlPdf     = urlPdfCotizacionVisitas($cot['token_publico']);
}

// «Corregir» (30-oct-2026): qué hace el botón y con qué folio quedaría, más
// la relación con la anterior / la que la reemplazó.
$modoCorregir = modoCorregirCotizacionErp((string)$cot['estado']);
$corregir = null;
if (!empty($_GET['para_corregir']) && $modoCorregir) {
    // Cliente de VISITAS de la cotización (por el prospecto espejo del ERP).
    $visitasClienteId = 0;
    if (!empty($cot['id_prospecto'])) {
        $stP = $dbErp->prepare('SELECT visitas_cliente_id FROM cotizador_prospectos WHERE id = ?');
        $stP->execute([(int)$cot['id_prospecto']]);
        $visitasClienteId = (int)$stP->fetchColumn();
    }
    // Vendedor de VISITAS a cuyo nombre está (lo necesita la responsable de muestras).
    $stVend = $db->prepare(
        "SELECT u.id FROM public.usuarios eu
         JOIN usuarios u ON LOWER(u.email) = LOWER(eu.email) AND u.rol = 'vendedor'
         WHERE eu.id = ? LIMIT 1"
    );
    $stVend->execute([(int)$cot['id_vendedor']]);
    $corregir = [
        'modo'               => $modoCorregir,
        'cliente_id'         => $visitasClienteId,
        'vendedor_id'        => (int)$stVend->fetchColumn(),
        'renglones'          => renglonesDesdeDetalleErp($detalle),
        'folio_nuevo'        => $modoCorregir === 'corregir' ? folioCorreccionCotizacionErp((string)$cot['folio']) : $cot['folio'],
    ];
}

jsonResponse([
    'ok'           => true,
    'cotizacion'   => $cot,
    'modo_corregir'   => $modoCorregir,
    'folio_correccion'=> $modoCorregir === 'corregir' ? folioCorreccionCotizacionErp((string)$cot['folio']) : null,
    'reemplaza'       => cotizacionAnteriorDeErp($dbErp, $cot),
    'reemplazada_por' => $cot['estado'] === 'cancelada' ? cotizacionReemplazadaPorErp($dbErp, (int)$cot['id']) : null,
    'corregir'        => $corregir,
    'detalle'      => $detalle,
    'atributos'    => leyendaAtributosDeLineasErp($detalle),
    'aviso_entrega'=> avisoTiempoEntregaErp($cot['tiempo_entrega'] ?? null, entregaRequeridaDeLineasErp($detalle)),
    'historial'    => $historial,
    'vencida'      => cotizacionVencidaErp($cot),
    'transiciones' => transicionesPermitidasErp($cot['estado']),
    'url_publica'  => $urlPublica,
    'url_pdf'      => $urlPdf,
]);
