<?php
/**
 * Métricas de los vendedores para el admin (admin/metricas.php). Solo lectura.
 *
 * GET ?desde=YYYY-MM-DD&hasta=YYYY-MM-DD[&vendedor=ID]
 *
 * Zonas horarias (ver assets/js/fecha_utils.js):
 *   - citas.fecha_hora ya es hora local de México: se compara contra fechas locales.
 *   - checkins, clientes, tracking, alertas: TIMESTAMP en UTC -> se comparan
 *     contra los límites del periodo convertidos a UTC.
 *   - public.cotizaciones.created_at es timestamptz (la sesión está en UTC).
 *
 * "por_vendedor" siempre trae a todo el equipo (para la tabla comparativa y el
 * promedio); el filtro ?vendedor solo afecta gráficas y números de arriba.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

$tzMx = new DateTimeZone('America/Mexico_City');
$tzUtc = new DateTimeZone('UTC');
$hoy = (new DateTime('now', $tzMx))->format('Y-m-d');
$fechaOk = fn($f) => is_string($f) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) && strtotime($f);

$hasta = $fechaOk($_GET['hasta'] ?? '') ? $_GET['hasta'] : $hoy;
$desde = $fechaOk($_GET['desde'] ?? '') ? $_GET['desde'] : (new DateTime($hasta))->modify('-6 days')->format('Y-m-d');
if ($desde > $hasta) [$desde, $hasta] = [$hasta, $desde];
$dias = (int)(new DateTime($desde))->diff(new DateTime($hasta))->days + 1;
if ($dias > 366) {
    jsonResponse(['ok' => false, 'error' => 'El periodo máximo es de un año.'], 400);
}
$vendedorId = (int)($_GET['vendedor'] ?? 0);

// Periodo anterior del mismo largo, para los "▲ 12 % vs periodo anterior".
$antHasta = (new DateTime($desde))->modify('-1 day')->format('Y-m-d');
$antDesde = (new DateTime($antHasta))->modify('-' . ($dias - 1) . ' days')->format('Y-m-d');

/** Límites de un periodo: fechas locales y su equivalente en UTC. */
function limites(string $desde, string $hasta, DateTimeZone $tzMx, DateTimeZone $tzUtc): array {
    $finLocal = (new DateTime($hasta))->modify('+1 day')->format('Y-m-d');
    return [
        'ini_local' => "$desde 00:00:00",
        'fin_local' => "$finLocal 00:00:00",
        'ini_utc'   => (new DateTime("$desde 00:00:00", $tzMx))->setTimezone($tzUtc)->format('Y-m-d H:i:s'),
        'fin_utc'   => (new DateTime("$finLocal 00:00:00", $tzMx))->setTimezone($tzUtc)->format('Y-m-d H:i:s'),
    ];
}

const SQL_A_MX = "AT TIME ZONE 'UTC' AT TIME ZONE 'America/Mexico_City'"; // TIMESTAMP UTC -> hora MX
const SQL_COT_VENDEDOR = "FROM public.cotizaciones co
    JOIN public.usuarios eu ON eu.id = co.id_vendedor
    JOIN usuarios u ON LOWER(u.email) = LOWER(eu.email) AND u.rol = 'vendedor'";

/** Métricas por vendedor de un periodo: [vendedor_id => [...]]. */
function metricasPorVendedor(PDO $db, array $L): array {
    $p = fn(array $keys) => array_map(fn($k) => $L[$k], $keys);
    $out = [];
    $fila = function (int $id) use (&$out) {
        if (!isset($out[$id])) $out[$id] = [
            'citas' => 0, 'completadas' => 0, 'no_realizadas' => 0, 'canceladas' => 0, 'pendientes' => 0,
            'verificadas' => 0, 'min_visita_total' => 0.0, 'visitas_con_duracion' => 0,
            'clientes_nuevos' => 0, 'convertidos' => 0, 'paradas' => 0,
            'dias_activos' => 0, 'min_jornada_total' => 0.0, 'dias_con_jornada' => 0,
            'primera_min_prom' => null, 'ultima_min_prom' => null, 'km' => 0.0,
            'cotizaciones' => 0, 'cot_canceladas' => 0, 'monto' => 0.0, 'aceptadas' => 0, 'monto_aceptado' => 0.0,
            'muy_interesados' => 0, 'muy_interesados_cotizados' => 0,
            'alertas' => [],
        ];
        return $id;
    };

    // Citas del periodo + duración y verificación de la visita.
    $st = $db->prepare(
        "SELECT c.vendedor_id,
                COUNT(*) AS citas,
                COUNT(*) FILTER (WHERE c.estado = 'completada') AS completadas,
                COUNT(*) FILTER (WHERE c.estado = 'no_realizada') AS no_realizadas,
                COUNT(*) FILTER (WHERE c.estado = 'cancelada') AS canceladas,
                COUNT(*) FILTER (WHERE c.estado IN ('pendiente', 'en_curso')) AS pendientes,
                COUNT(*) FILTER (WHERE c.estado = 'completada' AND e.verificado = 1) AS verificadas,
                SUM(EXTRACT(EPOCH FROM (s.fecha_hora - e.fecha_hora)) / 60)
                    FILTER (WHERE s.fecha_hora > e.fecha_hora AND s.fecha_hora - e.fecha_hora < INTERVAL '8 hours') AS min_visita_total,
                COUNT(*) FILTER (WHERE s.fecha_hora > e.fecha_hora AND s.fecha_hora - e.fecha_hora < INTERVAL '8 hours') AS visitas_con_duracion,
                COUNT(*) FILTER (WHERE c.estado = 'completada' AND c.interes = 'muy_interesado') AS muy_interesados,
                COUNT(*) FILTER (WHERE c.estado = 'completada' AND c.interes = 'muy_interesado' AND EXISTS (
                    SELECT 1 FROM public.cotizaciones co JOIN public.cotizador_prospectos pr ON pr.id = co.id_prospecto
                    WHERE pr.visitas_cliente_id = c.cliente_id)) AS muy_interesados_cotizados
         FROM citas c
         LEFT JOIN LATERAL (SELECT fecha_hora, verificado FROM checkins ch WHERE ch.cita_id = c.id AND ch.tipo = 'entrada' ORDER BY ch.id DESC LIMIT 1) e ON true
         LEFT JOIN LATERAL (SELECT fecha_hora FROM checkins ch WHERE ch.cita_id = c.id AND ch.tipo = 'salida' ORDER BY ch.id DESC LIMIT 1) s ON true
         WHERE c.fecha_hora >= ? AND c.fecha_hora < ?
         GROUP BY c.vendedor_id"
    );
    $st->execute($p(['ini_local', 'fin_local']));
    foreach ($st->fetchAll() as $r) {
        $id = $fila((int)$r['vendedor_id']);
        foreach (['citas', 'completadas', 'no_realizadas', 'canceladas', 'pendientes', 'verificadas', 'visitas_con_duracion', 'muy_interesados', 'muy_interesados_cotizados'] as $k) $out[$id][$k] = (int)$r[$k];
        $out[$id]['min_visita_total'] = (float)$r['min_visita_total'];
    }

    // Clientes nuevos y convertidos.
    $st = $db->prepare(
        "SELECT vendedor_id,
                COUNT(*) FILTER (WHERE created_at >= ? AND created_at < ?) AS nuevos,
                COUNT(*) FILTER (WHERE etapa = 'convertido' AND etapa_actualizada_en >= ? AND etapa_actualizada_en < ?) AS convertidos
         FROM clientes GROUP BY vendedor_id"
    );
    $st->execute($p(['ini_utc', 'fin_utc', 'ini_utc', 'fin_utc']));
    foreach ($st->fetchAll() as $r) {
        if (!$r['nuevos'] && !$r['convertidos']) continue;
        $id = $fila((int)$r['vendedor_id']);
        $out[$id]['clientes_nuevos'] = (int)$r['nuevos'];
        $out[$id]['convertidos'] = (int)$r['convertidos'];
    }

    // Paradas de "Salí a buscar clientes".
    $st = $db->prepare("SELECT vendedor_id, COUNT(*) AS n FROM prospecciones WHERE tipo IS NOT NULL AND fecha >= ? AND fecha < ? GROUP BY vendedor_id");
    $st->execute([substr($L['ini_local'], 0, 10), substr($L['fin_local'], 0, 10)]);
    foreach ($st->fetchAll() as $r) $out[$fila((int)$r['vendedor_id'])]['paradas'] = (int)$r['n'];

    // Jornada según el GPS: días con señal, hora de la primera y última, km.
    // Km aproximados: se ignoran lecturas imprecisas (> 100 m), saltos de
    // menos de 30 m (el GPS "tiembla" estando quieto) y velocidades
    // imposibles (> 150 km/h, brincos de señal).
    $st = $db->prepare(
        "WITH t AS (
            SELECT vendedor_id, lat::float8 AS lat, lng::float8 AS lng, fecha_hora,
                   (fecha_hora " . SQL_A_MX . ") AS hora_mx
            FROM tracking_ubicaciones
            WHERE fecha_hora >= ? AND fecha_hora < ? AND (accuracy IS NULL OR accuracy <= 100)
         ), seg AS (
            SELECT vendedor_id, hora_mx::date AS dia, hora_mx, fecha_hora,
                   2 * 6371 * ASIN(SQRT(
                       POWER(SIN(RADIANS(lat - LAG(lat) OVER w) / 2), 2) +
                       COS(RADIANS(LAG(lat) OVER w)) * COS(RADIANS(lat)) * POWER(SIN(RADIANS(lng - LAG(lng) OVER w) / 2), 2)
                   )) AS km,
                   EXTRACT(EPOCH FROM (fecha_hora - LAG(fecha_hora) OVER w)) AS seg
            FROM t
            WINDOW w AS (PARTITION BY vendedor_id, hora_mx::date ORDER BY fecha_hora)
         ), dia AS (
            SELECT vendedor_id, dia,
                   EXTRACT(EPOCH FROM MIN(hora_mx)::time) / 60 AS primera_min,
                   EXTRACT(EPOCH FROM MAX(hora_mx)::time) / 60 AS ultima_min,
                   COALESCE(SUM(km) FILTER (WHERE km >= 0.03 AND seg > 0 AND km / (seg / 3600) <= 150), 0) AS km
            FROM seg GROUP BY vendedor_id, dia
         )
         SELECT vendedor_id, COUNT(*) AS dias, AVG(primera_min) AS primera, AVG(ultima_min) AS ultima,
                SUM(ultima_min - primera_min) AS min_jornada, SUM(km) AS km
         FROM dia GROUP BY vendedor_id"
    );
    $st->execute($p(['ini_utc', 'fin_utc']));
    foreach ($st->fetchAll() as $r) {
        $id = $fila((int)$r['vendedor_id']);
        $out[$id]['dias_activos'] = (int)$r['dias'];
        $out[$id]['dias_con_jornada'] = (int)$r['dias'];
        $out[$id]['primera_min_prom'] = round((float)$r['primera']);
        $out[$id]['ultima_min_prom'] = round((float)$r['ultima']);
        $out[$id]['min_jornada_total'] = (float)$r['min_jornada'];
        $out[$id]['km'] = round((float)$r['km'], 1);
    }

    // Cotizaciones (ERP).
    try {
        $st = $db->prepare(
            "SELECT u.id AS vendedor_id, COUNT(*) AS n,
                    COUNT(*) FILTER (WHERE co.estado = 'cancelada') AS canceladas,
                    COALESCE(SUM(co.total) FILTER (WHERE co.estado <> 'cancelada'), 0) AS monto,
                    COUNT(*) FILTER (WHERE co.estado IN ('aceptada', 'facturada', 'entregada')) AS aceptadas,
                    COALESCE(SUM(co.total) FILTER (WHERE co.estado IN ('aceptada', 'facturada', 'entregada')), 0) AS monto_aceptado
             " . SQL_COT_VENDEDOR . "
             WHERE co.created_at >= ? AND co.created_at < ?
             GROUP BY u.id"
        );
        $st->execute($p(['ini_utc', 'fin_utc']));
        foreach ($st->fetchAll() as $r) {
            $id = $fila((int)$r['vendedor_id']);
            $out[$id]['cotizaciones'] = (int)$r['n'];
            $out[$id]['cot_canceladas'] = (int)$r['canceladas'];
            $out[$id]['monto'] = round((float)$r['monto'], 2);
            $out[$id]['aceptadas'] = (int)$r['aceptadas'];
            $out[$id]['monto_aceptado'] = round((float)$r['monto_aceptado'], 2);
        }
    } catch (Throwable $e) {
        error_log('[VISITAS] admin_metricas cotizaciones: ' . $e->getMessage());
    }

    // Calidad: alertas generadas en el periodo (sin importar si ya se resolvieron).
    $st = $db->prepare(
        "SELECT vendedor_id, tipo, COUNT(*) AS n FROM alertas
         WHERE created_at >= ? AND created_at < ?
           AND tipo IN ('visita_corta', 'visita_sin_cerrar', 'reprogramaciones', 'cita_perdida', 'ubicacion_por_revisar', 'sin_gps')
         GROUP BY vendedor_id, tipo"
    );
    $st->execute($p(['ini_utc', 'fin_utc']));
    foreach ($st->fetchAll() as $r) $out[$fila((int)$r['vendedor_id'])]['alertas'][$r['tipo']] = (int)$r['n'];

    return $out;
}

/** Suma de las métricas de varios vendedores (para los números de arriba). */
function sumar(array $filas): array {
    $t = ['citas' => 0, 'completadas' => 0, 'no_realizadas' => 0, 'canceladas' => 0, 'pendientes' => 0, 'verificadas' => 0,
          'min_visita_total' => 0, 'visitas_con_duracion' => 0, 'clientes_nuevos' => 0, 'convertidos' => 0, 'paradas' => 0,
          'dias_activos' => 0, 'min_jornada_total' => 0, 'km' => 0, 'cotizaciones' => 0, 'cot_canceladas' => 0, 'monto' => 0,
          'aceptadas' => 0, 'monto_aceptado' => 0, 'muy_interesados' => 0, 'muy_interesados_cotizados' => 0];
    foreach ($filas as $f) foreach ($t as $k => $_) $t[$k] += $f[$k] ?? 0;
    $t['km'] = round($t['km'], 1);
    $t['monto'] = round($t['monto'], 2);
    $t['monto_aceptado'] = round($t['monto_aceptado'], 2);
    return $t;
}

try {
    $L    = limites($desde, $hasta, $tzMx, $tzUtc);
    $Lant = limites($antDesde, $antHasta, $tzMx, $tzUtc);

    $vendedores = $db->query("SELECT id, nombre, foto_path, activo FROM usuarios WHERE rol = 'vendedor' ORDER BY nombre")->fetchAll();
    $actual   = metricasPorVendedor($db, $L);
    $anterior = metricasPorVendedor($db, $Lant);

    $porVendedor = [];
    foreach ($vendedores as $v) {
        $id = (int)$v['id'];
        if (!$v['activo'] && !isset($actual[$id])) continue; // inactivos solo si tuvieron actividad
        $m = $actual[$id] ?? metricasPorVendedor_vacia();
        $porVendedor[] = ['id' => $id, 'nombre' => $v['nombre'], 'foto_path' => $v['foto_path'], 'activo' => (int)$v['activo']] + $m;
    }

    $filtrar = fn(array $m) => $vendedorId ? array_intersect_key($m, [$vendedorId => 1]) : $m;
    $totales = sumar($filtrar($actual));
    $totalesAnt = sumar($filtrar($anterior));

    // Para las gráficas: condición de vendedor reutilizable.
    $condC  = $vendedorId ? ' AND c.vendedor_id = ' . $vendedorId : '';
    $condU  = $vendedorId ? ' AND u.id = ' . $vendedorId : '';

    // Tendencia diaria: visitas realizadas y cotizaciones.
    $st = $db->prepare(
        "SELECT d::date AS dia,
                (SELECT COUNT(*) FROM citas c WHERE c.estado = 'completada' AND c.fecha_hora::date = d::date $condC) AS visitas,
                (SELECT COUNT(*) " . SQL_COT_VENDEDOR . " WHERE (co.created_at AT TIME ZONE 'America/Mexico_City')::date = d::date $condU) AS cotizaciones
         FROM generate_series(?::date, ?::date, INTERVAL '1 day') d ORDER BY 1"
    );
    $st->execute([$desde, $hasta]);
    $serie = array_map(fn($r) => ['dia' => $r['dia'], 'visitas' => (int)$r['visitas'], 'cotizaciones' => (int)$r['cotizaciones']], $st->fetchAll());

    // ¿A qué hora visitan? Visitas realizadas por día de la semana (1=lun) y hora.
    $st = $db->prepare(
        "SELECT EXTRACT(ISODOW FROM c.fecha_hora)::int AS dow, EXTRACT(HOUR FROM c.fecha_hora)::int AS hora, COUNT(*) AS n
         FROM citas c WHERE c.estado = 'completada' AND c.fecha_hora >= ? AND c.fecha_hora < ? $condC
         GROUP BY 1, 2"
    );
    $st->execute([$L['ini_local'], $L['fin_local']]);
    $calor = array_map(fn($r) => ['dow' => (int)$r['dow'], 'hora' => (int)$r['hora'], 'n' => (int)$r['n']], $st->fetchAll());

    // Interés del cliente al cerrar la visita.
    $st = $db->prepare(
        "SELECT COALESCE(c.interes, 'sin_dato') AS interes, COUNT(*) AS n FROM citas c
         WHERE c.estado = 'completada' AND c.fecha_hora >= ? AND c.fecha_hora < ? $condC GROUP BY 1"
    );
    $st->execute([$L['ini_local'], $L['fin_local']]);
    $interes = array_column($st->fetchAll(), 'n', 'interes');

    // Por qué no se hicieron las citas (el motivo es texto libre: se agrupa).
    $st = $db->prepare(
        "SELECT CASE
                  WHEN c.estado = 'no_realizada' AND c.motivo = ? THEN 'Nadie la atendió (automático)'
                  WHEN c.motivo LIKE ? THEN 'El cliente canceló'
                  WHEN c.estado = 'cancelada' THEN 'La canceló el vendedor'
                  ELSE 'No realizada (otro motivo)'
                END AS motivo, COUNT(*) AS n
         FROM citas c
         WHERE c.estado IN ('no_realizada', 'cancelada') AND c.fecha_hora >= ? AND c.fecha_hora < ? $condC
         GROUP BY 1 ORDER BY 2 DESC"
    );
    $st->execute([MOTIVO_CITA_VENCIDA, CANCELACION_CLIENTE . '%', $L['ini_local'], $L['fin_local']]);
    $motivos = array_map(fn($r) => ['motivo' => $r['motivo'], 'n' => (int)$r['n']], $st->fetchAll());

    // Visitas realizadas por estado de la República (del cliente).
    $st = $db->prepare(
        "SELECT COALESCE(NULLIF(TRIM(cl.estado), ''), 'Sin estado') AS estado, COUNT(*) AS n
         FROM citas c JOIN clientes cl ON cl.id = c.cliente_id
         WHERE c.estado = 'completada' AND c.fecha_hora >= ? AND c.fecha_hora < ? $condC
         GROUP BY 1 ORDER BY 2 DESC LIMIT 10"
    );
    $st->execute([$L['ini_local'], $L['fin_local']]);
    $estados = array_map(fn($r) => ['estado' => $r['estado'], 'n' => (int)$r['n']], $st->fetchAll());

    // Embudo actual (foto de hoy, no del periodo) por vendedor.
    $st = $db->query("SELECT vendedor_id, etapa, COUNT(*) AS n FROM clientes GROUP BY 1, 2");
    $embudo = [];
    foreach ($st->fetchAll() as $r) $embudo[(int)$r['vendedor_id']][$r['etapa']] = (int)$r['n'];

    // De visita a cotización: días entre la visita y la cotización.
    $deVisita = ['n' => 0, 'dias_prom' => null];
    $topModelos = [];
    try {
        $st = $db->prepare(
            "SELECT COUNT(*) AS n,
                    AVG(GREATEST(0, EXTRACT(EPOCH FROM ((co.created_at AT TIME ZONE 'America/Mexico_City') - ci.fecha_hora)) / 86400)) AS dias
             " . SQL_COT_VENDEDOR . "
             JOIN citas ci ON ci.id = co.visitas_cita_id
             WHERE co.created_at >= ? AND co.created_at < ? $condU"
        );
        $st->execute([$L['ini_utc'], $L['fin_utc']]);
        $r = $st->fetch();
        $deVisita = ['n' => (int)$r['n'], 'dias_prom' => $r['dias'] !== null ? round((float)$r['dias'], 1) : null];

        $st = $db->prepare(
            "SELECT d.clave_estilo, MAX(d.nombre_estilo) AS nombre, SUM(d.cantidad) AS pares, COUNT(DISTINCT co.id) AS cotizaciones
             " . SQL_COT_VENDEDOR . "
             JOIN public.cotizacion_detalle d ON d.id_cotizacion = co.id
             WHERE co.created_at >= ? AND co.created_at < ? AND co.estado <> 'cancelada' $condU
             GROUP BY d.clave_estilo ORDER BY pares DESC LIMIT 8"
        );
        $st->execute([$L['ini_utc'], $L['fin_utc']]);
        $topModelos = array_map(fn($r) => ['clave' => $r['clave_estilo'], 'nombre' => $r['nombre'], 'pares' => (int)$r['pares'], 'cotizaciones' => (int)$r['cotizaciones']], $st->fetchAll());
    } catch (Throwable $e) {
        error_log('[VISITAS] admin_metricas cotizaciones (gráficas): ' . $e->getMessage());
    }
} catch (Throwable $e) {
    error_log('[VISITAS] admin_metricas.php: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'No se pudieron calcular las métricas.'], 500);
}

function metricasPorVendedor_vacia(): array {
    return ['citas' => 0, 'completadas' => 0, 'no_realizadas' => 0, 'canceladas' => 0, 'pendientes' => 0,
        'verificadas' => 0, 'min_visita_total' => 0, 'visitas_con_duracion' => 0, 'clientes_nuevos' => 0, 'convertidos' => 0,
        'paradas' => 0, 'dias_activos' => 0, 'min_jornada_total' => 0, 'dias_con_jornada' => 0, 'primera_min_prom' => null,
        'ultima_min_prom' => null, 'km' => 0, 'cotizaciones' => 0, 'cot_canceladas' => 0, 'monto' => 0, 'aceptadas' => 0,
        'monto_aceptado' => 0, 'muy_interesados' => 0, 'muy_interesados_cotizados' => 0, 'alertas' => []];
}

jsonResponse([
    'ok'           => true,
    'periodo'      => ['desde' => $desde, 'hasta' => $hasta, 'dias' => $dias, 'hoy' => $hoy],
    'anterior'     => ['desde' => $antDesde, 'hasta' => $antHasta],
    'vendedor'     => $vendedorId,
    'totales'      => $totales,
    'totales_ant'  => $totalesAnt,
    'por_vendedor' => $porVendedor,
    'serie'        => $serie,
    'calor'        => $calor,
    'interes'      => $interes,
    'motivos'      => $motivos,
    'estados'      => $estados,
    'embudo'       => $embudo,
    'de_visita'    => $deVisita,
    'top_modelos'  => $topModelos,
]);
