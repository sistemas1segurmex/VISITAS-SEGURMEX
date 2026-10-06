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
 * GET ?fecha=YYYY-MM-DD[&vendedor=ID] -> resumen del día (hora de México):
 *     total, monto, cuántas lleva cada vendedor activo (también los que
 *     llevan 0) y la lista de cotizaciones.
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
        'aviso_entrega' => avisoTiempoEntregaErp($cot['tiempo_entrega'] ?? null, entregaRequeridaDeLineasErp($detalle)),
        'historial'  => $historial->fetchAll(),
        'vencida'    => cotizacionVencidaErp($cot),
        'url_pdf'    => $urlPdf,
    ]);
}

// ── Resumen del día ──────────────────────────────────────────────────────
$tzMx  = new DateTimeZone('America/Mexico_City');
$hoy   = (new DateTime('now', $tzMx))->format('Y-m-d');
$fecha = trim($_GET['fecha'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !strtotime($fecha)) {
    $fecha = $hoy;
}
$vendedorId = (int)($_GET['vendedor'] ?? 0);

try {
    // Cuántas lleva cada vendedor ese día. Los activos salen aunque lleven 0
    // (para ver quién no ha cotizado); los inactivos solo si cotizaron.
    $stmt = $db->prepare(
        "SELECT u.id, u.nombre, u.foto_path, COALESCE(c.n, 0) AS n, COALESCE(c.monto, 0) AS monto,
                TO_CHAR(c.ultima AT TIME ZONE 'America/Mexico_City', 'HH24:MI') AS ultima
         FROM usuarios u
         LEFT JOIN (
             SELECT u.id AS vendedor_id, COUNT(*) AS n,
                    SUM(co.total) FILTER (WHERE co.estado <> 'cancelada') AS monto,
                    MAX(co.created_at) AS ultima
             " . SQL_COT_VENDEDOR . "
             WHERE (co.created_at AT TIME ZONE 'America/Mexico_City')::date = ?::date
             GROUP BY u.id
         ) c ON c.vendedor_id = u.id
         WHERE u.rol = 'vendedor' AND (u.activo = 1 OR c.n > 0)
         ORDER BY COALESCE(c.n, 0) DESC, u.nombre"
    );
    $stmt->execute([$fecha]);
    $porVendedor = $stmt->fetchAll();
    foreach ($porVendedor as &$v) {
        $v['id'] = (int)$v['id'];
        $v['n'] = (int)$v['n'];
        $v['monto'] = round((float)$v['monto'], 2);
    }
    unset($v);

    $sql = "SELECT co.id, co.folio, co.cliente_nombre, co.estado, co.total, co.total_pares,
                   co.visitas_cita_id, co.created_at,
                   TO_CHAR(co.created_at AT TIME ZONE 'America/Mexico_City', 'HH24:MI') AS hora,
                   u.id AS vendedor_id, u.nombre AS vendedor_nombre, u.foto_path AS vendedor_foto
            " . SQL_COT_VENDEDOR . "
            WHERE (co.created_at AT TIME ZONE 'America/Mexico_City')::date = ?::date";
    $params = [$fecha];
    if ($vendedorId) {
        $sql .= ' AND u.id = ?';
        $params[] = $vendedorId;
    }
    $stmt = $db->prepare($sql . ' ORDER BY co.created_at DESC LIMIT 300');
    $stmt->execute($params);
    $cotizaciones = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('[VISITAS] admin_cotizaciones.php: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'No se pudieron cargar las cotizaciones.'], 500);
}

jsonResponse([
    'ok'           => true,
    'fecha'        => $fecha,
    'es_hoy'       => $fecha === $hoy,
    'total'        => array_sum(array_column($porVendedor, 'n')),
    'monto'        => round(array_sum(array_column($porVendedor, 'monto')), 2),
    'por_vendedor' => $porVendedor,
    'cotizaciones' => $cotizaciones,
]);
