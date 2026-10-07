<?php
/**
 * Cotizaciones de los vendedores, vistas por el admin (solo lectura).
 *
 * La cotización vive en el ERP (public.cotizaciones). El vendedor de VISITAS
 * se liga con su "vendedor espejo" del ERP por correo (ver
 * obtenerOCrearVendedorErp en includes/cotizador_helpers.php). Mismo
 * servidor de Postgres, otro esquema: se lee con getDB() prefijando
 * "public.", igual que sqlTieneCotizacionErp() en includes/alertas.php.
 *
 * GET ?desde&hasta (o ?fecha)[&vendedor&estado&q&offset] -> resumen y lista
 *     del periodo (hora de México); ver el bloque "Lista por periodo".
 * GET ?id=X -> ficha completa de una cotización (renglones, historial, PDF).
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/cotizador_helpers.php';

requireRole('admin');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

// Liga cotización -> vendedor de VISITAS (por correo del vendedor espejo).
const SQL_COT_VENDEDOR = "FROM public.cotizaciones co
    JOIN public.usuarios eu ON eu.id = co.id_vendedor
    JOIN usuarios u ON LOWER(u.email) = LOWER(eu.email) AND u.rol = 'vendedor'";

// ── Ficha de una cotización ──────────────────────────────────────────────
$id = (int)($_GET['id'] ?? 0);
if ($id) {
    $stmt = $db->prepare("SELECT co.*, u.id AS vendedor_id, u.nombre AS vendedor_nombre " . SQL_COT_VENDEDOR . " WHERE co.id = ?");
    $stmt->execute([$id]);
    $cot = $stmt->fetch();
    if (!$cot) {
        jsonResponse(['ok' => false, 'error' => 'Cotización no encontrada.'], 404);
    }

    $dbErp = getDBErp();
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

    // El token no se manda al navegador: solo el link al PDF.
    $urlPdf = (!empty($cot['token_publico']) && $cot['estado'] !== 'cancelada')
        ? urlPdfCotizacionVisitas($cot['token_publico']) : null;
    unset($cot['token_publico']);

    jsonResponse([
        'ok'         => true,
        'cotizacion' => $cot,
        'detalle'    => $detalle,
        'atributos'     => leyendaAtributosDeLineasErp($detalle),
        'aviso_entrega' => avisoTiempoEntregaErp($cot['tiempo_entrega'] ?? null, entregaRequeridaDeLineasErp($detalle)),
        'historial'  => $historial->fetchAll(),
        'vencida'    => cotizacionVencidaErp($cot),
        'url_pdf'    => $urlPdf,
    ]);
}

// ── Lista por periodo (hora de México) ───────────────────────────────────
// GET [desde=YYYY-MM-DD&hasta=YYYY-MM-DD | fecha=YYYY-MM-DD] [&vendedor=ID]
//     [&estado=...] [&q=folio o cliente] [&offset=N]
// Sin fechas = hoy (lo usa el recuadro "Cotizaciones de hoy" de admin/index.php).
// Regresa:
//   total / monto / vendedores / de_visita -> con TODOS los filtros
//   por_vendedor -> periodo + estado + q (sin filtro de vendedor), para los botones
//   por_estado   -> periodo + vendedor + q (sin filtro de estado), para los botones
//   cotizaciones -> página de COT_POR_PAGINA con todos los filtros; hay_mas
$tzMx  = new DateTimeZone('America/Mexico_City');
$hoy   = (new DateTime('now', $tzMx))->format('Y-m-d');
$esFecha = fn($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v);
$fecha = trim($_GET['fecha'] ?? '');
$desde = trim($_GET['desde'] ?? '');
$hasta = trim($_GET['hasta'] ?? '');
if ($esFecha($fecha)) { $desde = $hasta = $fecha; }
if (!$esFecha($desde)) $desde = $hoy;
if (!$esFecha($hasta)) $hasta = $desde;
if ($hasta < $desde) [$desde, $hasta] = [$hasta, $desde];
$vendedorId = (int)($_GET['vendedor'] ?? 0);
$estado = trim($_GET['estado'] ?? '');
$estadosValidos = ['pendiente', 'enviada', 'en_negociacion', 'aceptada', 'rechazada', 'cancelada', 'facturada', 'entregada'];
if (!in_array($estado, $estadosValidos, true)) $estado = '';
$q = trim($_GET['q'] ?? '');
$offset = max(0, (int)($_GET['offset'] ?? 0));
const COT_POR_PAGINA = 50;

// Fragmentos de WHERE reutilizables.
$fPeriodo = ["(co.created_at AT TIME ZONE 'America/Mexico_City')::date BETWEEN ?::date AND ?::date", [$desde, $hasta]];
$fVend    = $vendedorId ? ['u.id = ?', [$vendedorId]] : null;
$fEstado  = $estado ? ['co.estado = ?', [$estado]] : null;
$fQ       = $q !== '' ? ['(co.folio ILIKE ? OR co.cliente_nombre ILIKE ?)', ['%' . $q . '%', '%' . $q . '%']] : null;
$armar = function (array $filtros): array {
    $w = []; $p = [];
    foreach ($filtros as $f) { if (!$f) continue; $w[] = $f[0]; array_push($p, ...$f[1]); }
    return [implode(' AND ', $w), $p];
};

try {
    // Botones de vendedor: los activos salen aunque lleven 0; los inactivos solo si cotizaron.
    [$w, $p] = $armar([$fPeriodo, $fEstado, $fQ]);
    $stmt = $db->prepare(
        "SELECT u.id, u.nombre, u.foto_path, COALESCE(c.n, 0) AS n, COALESCE(c.monto, 0) AS monto,
                TO_CHAR(c.ultima AT TIME ZONE 'America/Mexico_City', 'HH24:MI') AS ultima
         FROM usuarios u
         LEFT JOIN (
             SELECT u.id AS vendedor_id, COUNT(*) AS n,
                    SUM(co.total) FILTER (WHERE co.estado <> 'cancelada') AS monto,
                    MAX(co.created_at) AS ultima
             " . SQL_COT_VENDEDOR . "
             WHERE $w
             GROUP BY u.id
         ) c ON c.vendedor_id = u.id
         WHERE u.rol = 'vendedor' AND (u.activo = 1 OR c.n > 0)
         ORDER BY COALESCE(c.n, 0) DESC, u.nombre"
    );
    $stmt->execute($p);
    $porVendedor = $stmt->fetchAll();
    foreach ($porVendedor as &$v) {
        $v['id'] = (int)$v['id'];
        $v['n'] = (int)$v['n'];
        $v['monto'] = round((float)$v['monto'], 2);
    }
    unset($v);

    // Botones de estado.
    [$w, $p] = $armar([$fPeriodo, $fVend, $fQ]);
    $stmt = $db->prepare("SELECT co.estado, COUNT(*) AS n " . SQL_COT_VENDEDOR . " WHERE $w GROUP BY co.estado");
    $stmt->execute($p);
    $porEstado = [];
    foreach ($stmt->fetchAll() as $r) $porEstado[$r['estado']] = (int)$r['n'];

    // Números de arriba, con todos los filtros.
    [$w, $p] = $armar([$fPeriodo, $fVend, $fEstado, $fQ]);
    $stmt = $db->prepare(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(co.total) FILTER (WHERE co.estado <> 'cancelada'), 0) AS monto,
                COUNT(DISTINCT u.id) AS vendedores,
                COUNT(*) FILTER (WHERE co.visitas_cita_id IS NOT NULL) AS de_visita
         " . SQL_COT_VENDEDOR . " WHERE $w"
    );
    $stmt->execute($p);
    $resumen = $stmt->fetch();

    $stmt = $db->prepare(
        "SELECT co.id, co.folio, co.cliente_nombre, co.estado, co.total, co.total_pares,
                co.visitas_cita_id, co.created_at,
                TO_CHAR(co.created_at AT TIME ZONE 'America/Mexico_City', 'HH24:MI') AS hora,
                TO_CHAR(co.created_at AT TIME ZONE 'America/Mexico_City', 'YYYY-MM-DD') AS dia,
                u.id AS vendedor_id, u.nombre AS vendedor_nombre, u.foto_path AS vendedor_foto
         " . SQL_COT_VENDEDOR . "
         WHERE $w
         ORDER BY co.created_at DESC, co.id DESC
         LIMIT " . (COT_POR_PAGINA + 1) . " OFFSET " . $offset
    );
    $stmt->execute($p);
    $cotizaciones = $stmt->fetchAll();
    $hayMas = count($cotizaciones) > COT_POR_PAGINA;
    if ($hayMas) array_pop($cotizaciones);
} catch (Throwable $e) {
    error_log('[VISITAS] admin_cotizaciones.php: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'No se pudieron cargar las cotizaciones.'], 500);
}

jsonResponse([
    'ok'           => true,
    'fecha'        => $desde,          // compatibilidad: el recuadro de hoy lo usa
    'desde'        => $desde,
    'hasta'        => $hasta,
    'hoy'          => $hoy,
    'es_hoy'       => $desde === $hoy && $hasta === $hoy,
    'total'        => (int)$resumen['total'],
    'monto'        => round((float)$resumen['monto'], 2),
    'vendedores'   => (int)$resumen['vendedores'],
    'de_visita'    => (int)$resumen['de_visita'],
    'por_vendedor' => $porVendedor,
    'por_estado'   => $porEstado,
    'cotizaciones' => $cotizaciones,
    'offset'       => $offset,
    'hay_mas'      => $hayMas,
]);
