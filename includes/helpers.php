<?php
// Margen de tolerancia (en metros) entre el GPS reportado y la dirección
// registrada del cliente para considerar una visita "verificada".
define('RADIO_VERIFICACION_METROS', 150);

function jsonResponse($data, int $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Para romper el caché del navegador en CSS/JS propios: agrega ?v=<fecha de
 * modificación del archivo> a la URL, así el navegador solo vuelve a pedir
 * el archivo cuando de verdad cambió (no en cada carga, y sin depender de
 * que el usuario haga Ctrl+F5 a mano cada vez que se actualiza el sistema).
 * Uso: <script src="../assets/js/admin.js<?= assetVer(__DIR__.'/../assets/js/admin.js') ?>">
 */
function assetVer(string $rutaAbsoluta): string {
    $mtime = @filemtime($rutaAbsoluta);
    return $mtime ? ('?v=' . $mtime) : '';
}

/**
 * Distancia en metros entre dos coordenadas GPS (fórmula de Haversine).
 */
function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $R = 6371000; // radio de la Tierra en metros
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $R * $c;
}

// Hora local (CDMX) a partir de la cual se considera que ya "debería" haber
// actividad si el vendedor de verdad salió a trabajar.
define('HORA_CORTE_SIN_ACTIVIDAD', 11);

// Horas sin reportar GPS para pasar de "desconectado" a "conexión perdida"
// (pin distinto y más urgente en el mapa del admin, ver api/tracking.php).
define('CONEXION_PERDIDA_HORAS', 2);

/**
 * Revisa, a partir de la hora de corte (11:00 hora CDMX), qué vendedores
 * activos no tuvieron NINGUNA señal de actividad hoy: sin citas, sin GPS
 * reportado, sin clientes nuevos registrados y sin haber marcado una jornada
 * de prospección libre. A esos les genera una alerta 'sin_actividad' (una
 * sola vez por vendedor por día, aunque se llame varias veces).
 *
 * No depende de un cron real: se llama de forma "perezosa" cada vez que el
 * admin carga sus alertas (api/alertas.php), que ya se refresca solo cada
 * pocos segundos mientras el panel está abierto.
 */
function generarAlertasSinActividad(PDO $db): void {
    $tzMx    = new DateTimeZone('America/Mexico_City');
    $ahoraMx = new DateTime('now', $tzMx);
    if ((int)$ahoraMx->format('H') < HORA_CORTE_SIN_ACTIVIDAD) {
        return; // aún no llega la hora de corte
    }

    $hoyMx     = $ahoraMx->format('Y-m-d');
    $inicioUtc = (new DateTime($hoyMx . ' 00:00:00', $tzMx))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $finUtc    = (new DateTime($hoyMx . ' 23:59:59', $tzMx))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

    try {
        $stmt = $db->prepare(
            "SELECT u.id
             FROM usuarios u
             WHERE u.rol = 'vendedor' AND u.activo = 1
               AND NOT EXISTS (
                   SELECT 1 FROM citas c WHERE c.vendedor_id = u.id AND DATE(c.fecha_hora) = ?
               )
               AND NOT EXISTS (
                   SELECT 1 FROM tracking_ubicaciones t
                   WHERE t.vendedor_id = u.id AND t.fecha_hora BETWEEN ? AND ?
               )
               AND NOT EXISTS (
                   SELECT 1 FROM clientes cl
                   WHERE cl.vendedor_id = u.id AND cl.created_at BETWEEN ? AND ?
               )
               AND NOT EXISTS (
                   SELECT 1 FROM prospecciones p WHERE p.vendedor_id = u.id AND p.fecha = ?
               )
               AND NOT EXISTS (
                   SELECT 1 FROM alertas a
                   WHERE a.vendedor_id = u.id AND a.tipo = 'sin_actividad'
                     AND a.created_at BETWEEN ? AND ?
               )"
        );
        $stmt->execute([$hoyMx, $inicioUtc, $finUtc, $inicioUtc, $finUtc, $hoyMx, $inicioUtc, $finUtc]);
        $vendedores = $stmt->fetchAll();
        if (!$vendedores) {
            return;
        }

        $insert = $db->prepare(
            "INSERT INTO alertas (vendedor_id, tipo, mensaje) VALUES (?, 'sin_actividad', ?)"
        );
        foreach ($vendedores as $v) {
            $insert->execute([
                $v['id'],
                'Sin citas, sin ubicación reportada y sin clientes nuevos hoy (después de las ' . HORA_CORTE_SIN_ACTIVIDAD . ':00).',
            ]);
        }
    } catch (Throwable $e) {
        // No tumbamos el panel de alertas si, por ejemplo, todavía no se ha
        // corrido db_seed/migrar_prospeccion.php en este entorno.
        error_log('[VISITAS] generarAlertasSinActividad: ' . $e->getMessage());
    }
}

// Días sin actividad después de la última visita antes de avisarle al
// vendedor que le falta dar seguimiento a ese cliente.
define('DIAS_SIN_SEGUIMIENTO', 5);

/**
 * Detecta clientes cuya visita más reciente ya se completó hace más de
 * DIAS_SIN_SEGUIMIENTO días, sin que se les haya agendado una cita nueva
 * ni se haya cerrado la relación (convertido/perdido). Genera una alerta
 * 'sin_seguimiento' por cliente (una sola vez mientras no se resuelva o
 * cambie la situación -- se identifica por cita_id, igual que
 * 'fuera_de_zona').
 *
 * Se llama de forma perezosa cada vez que el admin carga sus alertas
 * (api/alertas.php), igual que generarAlertasSinActividad.
 */
function generarAlertasSinSeguimiento(PDO $db): void {
    $tzMx   = new DateTimeZone('America/Mexico_City');
    $cutoff = (new DateTime('now', $tzMx))->modify('-' . DIAS_SIN_SEGUIMIENTO . ' days')->format('Y-m-d H:i:s');

    try {
        $stmt = $db->prepare(
            "SELECT c.id AS cita_id, c.vendedor_id, c.fecha_hora, c.interes, cl.nombre AS cliente_nombre
             FROM citas c
             JOIN clientes cl ON cl.id = c.cliente_id
             WHERE c.estado = 'completada'
               AND c.fecha_hora < ?
               AND cl.etapa NOT IN ('convertido', 'perdido')
               AND NOT EXISTS (
                   SELECT 1 FROM citas c2 WHERE c2.cliente_id = c.cliente_id AND c2.fecha_hora > c.fecha_hora
               )
               AND NOT EXISTS (
                   SELECT 1 FROM alertas a WHERE a.cita_id = c.id AND a.tipo = 'sin_seguimiento'
               )"
        );
        $stmt->execute([$cutoff]);
        $pendientes = $stmt->fetchAll();
        if (!$pendientes) {
            return;
        }

        $mapaInteres = ['bajo' => 'poco interesado', 'medio' => 'con interés medio', 'interesado' => 'interesado', 'muy_interesado' => 'muy interesado'];
        $insert = $db->prepare(
            "INSERT INTO alertas (vendedor_id, cita_id, tipo, mensaje) VALUES (?, ?, 'sin_seguimiento', ?)"
        );
        foreach ($pendientes as $p) {
            $dias = floor((time() - strtotime($p['fecha_hora'])) / 86400);
            $extraInteres = isset($mapaInteres[$p['interes']]) ? " (quedó {$mapaInteres[$p['interes']]})" : '';
            $insert->execute([
                $p['vendedor_id'],
                $p['cita_id'],
                "Sin seguimiento con {$p['cliente_nombre']} desde hace {$dias} días{$extraInteres}. No tiene otra cita agendada.",
            ]);
        }
    } catch (Throwable $e) {
        error_log('[VISITAS] generarAlertasSinSeguimiento: ' . $e->getMessage());
    }
}

/**
 * Detalle completo de UN día para un vendedor: citas, jornada de
 * prospección, clientes nuevos y ubicación GPS. Se usa tanto para la vista
 * "Día" de la pestaña Prospección (todo lo que hizo) como para calcular el
 * banner "Hoy: ..." (que siempre se pide para la fecha de hoy, sin
 * importar qué rango esté viendo el admin en el resto de la pestaña).
 *
 * Misma prioridad de categoría que el resto del sistema (ver
 * generarAlertasSinActividad): cita > prospección > cliente > ubicación >
 * sin actividad. "prospeccion_activa" solo aplica si $fecha es hoy y la
 * jornada sigue sin cerrar (hora_fin nula).
 *
 * @param string $fecha 'YYYY-MM-DD'
 */
function resumenDiaVendedor(PDO $db, int $vendedorId, string $fecha): array {
    $tzMx = new DateTimeZone('America/Mexico_City');
    $utc  = new DateTimeZone('UTC');
    $hoyFecha = (new DateTime('now', $tzMx))->format('Y-m-d');

    $inicioUtc = (DateTime::createFromFormat('!Y-m-d H:i:s', $fecha . ' 00:00:00', $tzMx))
        ->setTimezone($utc)->format('Y-m-d H:i:s');
    $finUtc    = (DateTime::createFromFormat('!Y-m-d H:i:s', $fecha . ' 23:59:59', $tzMx))
        ->setTimezone($utc)->format('Y-m-d H:i:s');

    // citas.fecha_hora es texto local de CDMX (no UTC) — se compara por
    // fecha directa, igual que en el resto del sistema.
    $stmt = $db->prepare(
        "SELECT c.id, c.fecha_hora, c.estado, c.notas, cl.nombre AS cliente_nombre, cl.direccion,
                (SELECT verificado FROM checkins ch WHERE ch.cita_id = c.id AND ch.tipo='entrada' ORDER BY ch.id DESC LIMIT 1) AS checkin_verificado,
                (SELECT id FROM checkins ch WHERE ch.cita_id = c.id AND ch.tipo='entrada' AND ch.foto_path IS NOT NULL ORDER BY ch.id DESC LIMIT 1) AS foto_entrada_id,
                (SELECT id FROM checkins ch WHERE ch.cita_id = c.id AND ch.tipo='salida' AND ch.foto_path IS NOT NULL ORDER BY ch.id DESC LIMIT 1) AS foto_salida_id
         FROM citas c JOIN clientes cl ON cl.id = c.cliente_id
         WHERE c.vendedor_id = ? AND DATE(c.fecha_hora) = ?
         ORDER BY c.fecha_hora ASC"
    );
    $stmt->execute([$vendedorId, $fecha]);
    $citas = $stmt->fetchAll();

    $stmt = $db->prepare('SELECT fecha, hora_inicio, hora_fin FROM prospecciones WHERE vendedor_id = ? AND fecha = ?');
    $stmt->execute([$vendedorId, $fecha]);
    $prospeccion = $stmt->fetch() ?: null;

    $stmt = $db->prepare(
        'SELECT id, nombre, direccion, created_at FROM clientes
         WHERE vendedor_id = ? AND created_at BETWEEN ? AND ? ORDER BY created_at ASC'
    );
    $stmt->execute([$vendedorId, $inicioUtc, $finUtc]);
    $clientes = $stmt->fetchAll();

    $stmt = $db->prepare(
        'SELECT COUNT(*) AS n, MIN(fecha_hora) AS primera, MAX(fecha_hora) AS ultima
         FROM tracking_ubicaciones WHERE vendedor_id = ? AND fecha_hora BETWEEN ? AND ?'
    );
    $stmt->execute([$vendedorId, $inicioUtc, $finUtc]);
    $ubic = $stmt->fetch();

    if (count($citas) > 0) {
        $categoria = 'cita';
        $detalle   = count($citas) . ' cita(s)';
    } elseif ($prospeccion) {
        $activa    = !$prospeccion['hora_fin'] && $fecha === $hoyFecha;
        $categoria = $activa ? 'prospeccion_activa' : 'prospeccion';
        $detalle   = substr($prospeccion['hora_inicio'], 11, 5)
                   . ($prospeccion['hora_fin'] ? '–' . substr($prospeccion['hora_fin'], 11, 5) : ' (en curso)');
    } elseif (count($clientes) > 0) {
        $categoria = 'cliente';
        $detalle   = count($clientes) . ' cliente(s) nuevo(s)';
    } elseif ((int)$ubic['n'] > 0) {
        $categoria = 'ubicacion';
        $detalle   = 'Solo ubicación GPS registrada';
    } else {
        $categoria = 'sin_actividad';
        $detalle   = null;
    }

    return [
        'fecha'       => $fecha,
        'categoria'   => $categoria,
        'detalle'     => $detalle,
        'citas'       => $citas,
        'prospeccion' => $prospeccion,
        'clientes'    => $clientes,
        'ubicaciones' => ['total' => (int)$ubic['n'], 'primera' => $ubic['primera'], 'ultima' => $ubic['ultima']],
    ];
}

/**
 * Tira de días (para las vistas "Mes" y "Semana" de la pestaña
 * Prospección): a cada día de [$desde, $hasta] le asigna UNA categoría (la
 * más fuerte de las que aplique), con la misma lógica y prioridad que
 * resumenDiaVendedor() pero calculada en bloque (4 consultas agregadas en
 * vez de 4 por día) para que ver un mes completo no dispare ~120 queries.
 * Los días futuros del rango no se categorizan ni cuentan para el % de
 * cobertura.
 */
function resumenProspeccionRango(PDO $db, int $vendedorId, string $desde, string $hasta, ?string $emailVendedor = null): array {
    $tzMx     = new DateTimeZone('America/Mexico_City');
    $utc      = new DateTimeZone('UTC');
    $hoyMx    = new DateTime('now', $tzMx);
    $hoyFecha = $hoyMx->format('Y-m-d');

    // No categorizamos ni contamos días futuros del rango pedido.
    $finFecha  = ($hasta > $hoyFecha) ? $hoyFecha : $hasta;
    $finRealMx = ($finFecha === $hoyFecha) ? $hoyMx : DateTime::createFromFormat('!Y-m-d H:i:s', $finFecha . ' 23:59:59', $tzMx);

    $inicioUtc = (DateTime::createFromFormat('!Y-m-d H:i:s', $desde . ' 00:00:00', $tzMx))->setTimezone($utc)->format('Y-m-d H:i:s');
    $finUtc    = (clone $finRealMx)->setTimezone($utc)->format('Y-m-d H:i:s');

    // citas.fecha_hora es texto local de CDMX (no UTC) — se compara por fecha directa.
    $citasPorDia = [];
    $stmt = $db->prepare(
        "SELECT DATE(fecha_hora) AS dia, COUNT(*) AS n
         FROM citas WHERE vendedor_id = ? AND DATE(fecha_hora) BETWEEN ? AND ?
         GROUP BY DATE(fecha_hora)"
    );
    $stmt->execute([$vendedorId, $desde, $finFecha]);
    foreach ($stmt->fetchAll() as $r) { $citasPorDia[$r['dia']] = (int)$r['n']; }

    $prospeccionPorDia = [];
    $stmt = $db->prepare('SELECT fecha, hora_inicio, hora_fin FROM prospecciones WHERE vendedor_id = ? AND fecha BETWEEN ? AND ?');
    $stmt->execute([$vendedorId, $desde, $finFecha]);
    foreach ($stmt->fetchAll() as $r) { $prospeccionPorDia[$r['fecha']] = $r; }

    $clientesPorDia = [];
    $stmt = $db->prepare(
        "SELECT DATE(created_at) AS dia, COUNT(*) AS n
         FROM clientes WHERE vendedor_id = ? AND created_at BETWEEN ? AND ?
         GROUP BY DATE(created_at)"
    );
    $stmt->execute([$vendedorId, $inicioUtc, $finUtc]);
    foreach ($stmt->fetchAll() as $r) { $clientesPorDia[$r['dia']] = (int)$r['n']; }

    $ubicacionPorDia = [];
    $stmt = $db->prepare(
        "SELECT DATE(fecha_hora) AS dia, COUNT(*) AS n
         FROM tracking_ubicaciones WHERE vendedor_id = ? AND fecha_hora BETWEEN ? AND ?
         GROUP BY DATE(fecha_hora)"
    );
    $stmt->execute([$vendedorId, $inicioUtc, $finUtc]);
    foreach ($stmt->fetchAll() as $r) { $ubicacionPorDia[$r['dia']] = (int)$r['n']; }

    // Cotizaciones creadas en el ERP (tabla `public.cotizaciones`, esquema
    // aparte pero MISMO proyecto de Supabase que Visitas — se cruza por
    // correo, ya que son dos tablas de usuarios completamente separadas,
    // una por app). Si el vendedor de Visitas no tiene correo, o el ERP
    // todavía no tiene esta tabla en algún entorno, simplemente no se
    // cuentan cotizaciones — no se cae el resto de la pantalla.
    $cotizacionesPorDia = [];
    if ($emailVendedor) {
        try {
            $stmt = $db->prepare(
                "SELECT DATE(c.created_at) AS dia, COUNT(*) AS n
                 FROM public.cotizaciones c
                 JOIN public.usuarios u ON u.id = c.id_vendedor
                 WHERE u.email = ? AND DATE(c.created_at) BETWEEN ? AND ?
                 GROUP BY DATE(c.created_at)"
            );
            $stmt->execute([$emailVendedor, $desde, $finFecha]);
            foreach ($stmt->fetchAll() as $r) { $cotizacionesPorDia[$r['dia']] = (int)$r['n']; }
        } catch (Throwable $e) {
            error_log('[VISITAS] cotizacionesPorDia: ' . $e->getMessage());
        }
    }

    // Cada día ahora puede traer VARIAS categorías (antes se quedaba solo con
    // la de mayor prioridad y las demás se perdían — ej. si hoy hay una
    // jornada de prospección Y se registró un cliente nuevo, antes solo se
    // veía "prospección"). "categoria"/"detalle" (singular) se conservan
    // como la de mayor prioridad, por si algo más los sigue leyendo así.
    $dias = [];
    $cur = DateTime::createFromFormat('!Y-m-d', $desde, $tzMx);
    while ($cur->format('Y-m-d') <= $hasta) {
        $f = $cur->format('Y-m-d');
        if ($f > $finFecha) {
            $dias[] = ['fecha' => $f, 'categorias' => [['categoria' => 'futuro', 'detalle' => null]], 'categoria' => 'futuro', 'detalle' => null];
            $cur->modify('+1 day');
            continue;
        }

        $cats = [];
        if (!empty($citasPorDia[$f])) {
            $cats[] = ['categoria' => 'cita', 'detalle' => $citasPorDia[$f] . ' cita(s)'];
        }
        if (isset($prospeccionPorDia[$f])) {
            $p = $prospeccionPorDia[$f];
            $activa = !$p['hora_fin'] && $f === $hoyFecha;
            $rango  = substr($p['hora_inicio'], 11, 5) . ($p['hora_fin'] ? '–' . substr($p['hora_fin'], 11, 5) : ' (en curso)');
            $cats[] = ['categoria' => $activa ? 'prospeccion_activa' : 'prospeccion', 'detalle' => $rango];
        }
        if (!empty($clientesPorDia[$f])) {
            $cats[] = ['categoria' => 'cliente', 'detalle' => $clientesPorDia[$f] . ' cliente(s) nuevo(s)'];
        }
        if (!empty($ubicacionPorDia[$f])) {
            $cats[] = ['categoria' => 'ubicacion', 'detalle' => 'Solo ubicación GPS registrada'];
        }
        if (!empty($cotizacionesPorDia[$f])) {
            $cats[] = ['categoria' => 'cotizacion', 'detalle' => $cotizacionesPorDia[$f] . ' cotización(es)'];
        }
        if (!$cats) {
            $cats[] = ['categoria' => 'sin_actividad', 'detalle' => null];
        }

        $dias[] = ['fecha' => $f, 'categorias' => $cats, 'categoria' => $cats[0]['categoria'], 'detalle' => $cats[0]['detalle']];
        $cur->modify('+1 day');
    }

    // --- Cobertura: días ya transcurridos con alguna señal real ---
    $diasConsiderados  = array_values(array_filter($dias, fn($d) => $d['categoria'] !== 'futuro'));
    $totalConsiderados = count($diasConsiderados);
    $cubiertos         = count(array_filter($diasConsiderados, fn($d) => $d['categoria'] !== 'sin_actividad'));
    $pctCobertura      = $totalConsiderados > 0 ? (int)round(($cubiertos / $totalConsiderados) * 100) : 0;

    // Los conteos ahora suman TODAS las categorías del día, no solo la
    // primera — un día con prospección + cliente nuevo cuenta en ambas.
    $conteo = ['cita' => 0, 'prospeccion' => 0, 'cliente' => 0, 'ubicacion' => 0, 'cotizacion' => 0, 'sin_actividad' => 0];
    foreach ($diasConsiderados as $d) {
        foreach ($d['categorias'] as $c) {
            $cat = ($c['categoria'] === 'prospeccion_activa') ? 'prospeccion' : $c['categoria'];
            $conteo[$cat]++;
        }
    }

    return [
        'desde'                   => $desde,
        'hasta'                   => $hasta,
        'dias'                    => $dias,
        'conteo'                  => $conteo,
        'total_dias_considerados' => $totalConsiderados,
        'dias_cubiertos'          => $cubiertos,
        'porcentaje_cobertura'    => $pctCobertura,
    ];
}

/**
 * Vista "Mes" de la pestaña Prospección: la tira de días del mes completo
 * (capada a hoy si es el mes en curso) más el banner de estado de hoy.
 *
 * @param string $mes 'YYYY-MM'
 */
function resumenProspeccionMes(PDO $db, int $vendedorId, string $mes, ?string $emailVendedor = null): array {
    $tzMx = new DateTimeZone('America/Mexico_City');
    $inicioMes = DateTime::createFromFormat('!Y-m-d', $mes . '-01', $tzMx);
    if (!$inicioMes) {
        $inicioMes = new DateTime('first day of this month', $tzMx);
    }
    $desde = $inicioMes->format('Y-m-d');
    $hasta = (clone $inicioMes)->modify('last day of this month')->format('Y-m-d');

    $out = resumenProspeccionRango($db, $vendedorId, $desde, $hasta, $emailVendedor);
    $out['mes']        = $mes;
    $out['estado_hoy'] = resumenDiaVendedor($db, $vendedorId, (new DateTime('now', $tzMx))->format('Y-m-d'));
    return $out;
}

/**
 * Vista "Semana" de la pestaña Prospección: recibe año + número de semana
 * ISO-8601 (el formato nativo de <input type="week">, ej. "2026-W35") y
 * regresa la tira de esos 7 días (lunes a domingo) más el banner de hoy.
 */
function resumenProspeccionSemana(PDO $db, int $vendedorId, int $anioIso, int $semanaIso, ?string $emailVendedor = null): array {
    $tzMx  = new DateTimeZone('America/Mexico_City');
    $lunes = new DateTime('now', $tzMx);
    $lunes->setISODate($anioIso, $semanaIso, 1); // día 1 = lunes en ISO-8601
    $domingo = (clone $lunes)->modify('+6 days');

    $out = resumenProspeccionRango($db, $vendedorId, $lunes->format('Y-m-d'), $domingo->format('Y-m-d'), $emailVendedor);
    $out['anio_iso']   = $anioIso;
    $out['semana_iso'] = $semanaIso;
    $out['estado_hoy'] = resumenDiaVendedor($db, $vendedorId, (new DateTime('now', $tzMx))->format('Y-m-d'));
    return $out;
}
