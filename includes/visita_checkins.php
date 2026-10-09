<?php
/**
 * Intentos de check-in y "tiempo en el lugar" según el GPS (9-oct-2026).
 *
 * Caso que lo motivó: Norma Figueroa en Grupo corporativo papelero. La
 * entrada y la salida quedaron a las 3:04 y 3:05 p.m. ("Visita de 1 min --
 * revisar"), pero el tracking la ubica a menos de 120 m del cliente desde
 * las 2:14 p.m. Faltaban dos cosas:
 *   1. Saber qué pasó antes de la entrada: cada intento que no terminó en
 *      registro queda en checkin_intentos (ver la migración
 *      20261009230000_checkin_intentos.sql).
 *   2. Medir la visita con el GPS y no solo con la hora de las dos fotos:
 *      gpsEnSitioParaCitas() calcula cuánto tiempo estuvo cerca del pin.
 *
 * Todo aquí es "best effort": si la tabla nueva aún no existe o una
 * consulta falla, se omite el dato y el check-in / el panel siguen igual.
 */

require_once __DIR__ . '/helpers.php';

const CHECKIN_INTENTO_ETAPAS = ['abrio_pantalla', 'gps_impreciso', 'pregunta_ubicacion', 'rechazo_servidor', 'error_envio'];
// Las que cuentan como "intento fallido" en el panel.
const CHECKIN_INTENTO_FALLIDOS = ['gps_impreciso', 'rechazo_servidor', 'error_envio'];

define('GPS_SITIO_RADIO_M', 150);          // cerca del pin = dentro de esto (+ precisión, máx. 100 m)
define('GPS_SITIO_PRECISION_MAX_M', 100);  // lecturas peores se ignoran (ni cuentan ni cortan)
define('GPS_SITIO_ANTES_MIN', 120);        // se busca hasta 2 h antes de la entrada
define('GPS_SITIO_DESPUES_MIN', 30);       // y 30 min después de la salida
define('GPS_SITIO_HUECO_MIN', 15);         // más de esto sin puntos = "sin señal"
define('GPS_SITIO_MINIMO_REAL_MIN', 10);   // con esto en el lugar ya no es "visita muy corta"
define('VISITA_CORTA_SEG', 120);           // mismo umbral que DURACION_MINIMA_SOSPECHOSA_SEG (admin.js)

function existeTablaCheckinIntentos(PDO $db): bool {
    static $existe = null;
    if ($existe === null) {
        try {
            $existe = (bool)$db->query("SELECT to_regclass('checkin_intentos') IS NOT NULL")->fetchColumn();
        } catch (Throwable $e) {
            $existe = false;
        }
    }
    return $existe;
}

/**
 * Guarda un intento. Solo si la cita es de ese vendedor (el INSERT ... SELECT
 * no inserta nada si no). Nunca lanza excepción.
 */
function registrarIntentoCheckin(PDO $db, int $citaId, int $vendedorId, string $tipo, string $etapa,
                                 ?string $motivo = null, ?float $lat = null, ?float $lng = null,
                                 ?float $accuracy = null, ?float $distancia = null): void {
    if (!$citaId || !in_array($etapa, CHECKIN_INTENTO_ETAPAS, true) || !in_array($tipo, ['entrada', 'salida'], true)) return;
    if (!existeTablaCheckinIntentos($db)) return;
    try {
        $db->prepare(
            'INSERT INTO checkin_intentos (cita_id, vendedor_id, tipo, etapa, motivo, lat, lng, accuracy, distancia_metros)
             SELECT c.id, c.vendedor_id, ?, ?, ?, ?, ?, ?, ?
             FROM citas c WHERE c.id = ? AND c.vendedor_id = ?'
        )->execute([
            $tipo, $etapa,
            $motivo !== null ? mb_substr($motivo, 0, 250) : null,
            $lat ?: null, $lng ?: null, $accuracy, $distancia !== null ? round($distancia, 2) : null,
            $citaId, $vendedorId,
        ]);
    } catch (Throwable $e) {
        error_log('[VISITAS] registrarIntentoCheckin: ' . $e->getMessage());
    }
}

/**
 * Columnas para el SELECT de citas (alias "c"): primer intento de entrada,
 * cuántos fallaron y el último fallo. Sin coma al final. Si la tabla aún no
 * existe devuelve NULLs para no tumbar la consulta.
 */
function sqlIntentosCheckin(PDO $db): string {
    if (!existeTablaCheckinIntentos($db)) {
        return "NULL AS entrada_primer_intento, 0 AS entrada_intentos_fallidos, NULL AS entrada_ultimo_fallo";
    }
    $fallidos = "'" . implode("','", CHECKIN_INTENTO_FALLIDOS) . "'";
    return "(SELECT MIN(ci.creado_en) FROM checkin_intentos ci WHERE ci.cita_id = c.id AND ci.tipo = 'entrada') AS entrada_primer_intento,
            (SELECT COUNT(*) FROM checkin_intentos ci WHERE ci.cita_id = c.id AND ci.tipo = 'entrada' AND ci.etapa IN ($fallidos)) AS entrada_intentos_fallidos,
            (SELECT ci.etapa || '|' || COALESCE(ci.motivo, '') FROM checkin_intentos ci
              WHERE ci.cita_id = c.id AND ci.tipo = 'entrada' AND ci.etapa IN ($fallidos)
              ORDER BY ci.id DESC LIMIT 1) AS entrada_ultimo_fallo";
}

function epochUtc(string $ts): float {
    $d = new DateTime($ts, new DateTimeZone('UTC'));
    return (float)$d->format('U.u');
}

function fechaUtc(float $epoch): string {
    return gmdate('Y-m-d H:i:s', (int)round($epoch));
}

/**
 * Tiempo en el lugar según el GPS, para las citas con entrada y salida a
 * menos de VISITA_CORTA_SEG (las demás no lo necesitan y así no se carga el
 * panel). Devuelve [cita_id => info]; info:
 *   minutos       tiempo continuo cerca del pin alrededor de la visita
 *   desde, hasta  primer y último punto cerca (UTC)
 *   hueco_desde, hueco_hasta  el hueco sin puntos más largo dentro de ese
 *                 tramo, si pasa de GPS_SITIO_HUECO_MIN (UTC); si no, null
 * Usa el pin ACTUAL del cliente (ya corregido si la entrada lo corrigió).
 */
function gpsEnSitioParaCitas(PDO $db, array $citas): array {
    $ids = [];
    foreach ($citas as $c) {
        if (empty($c['entrada_fecha_hora']) || empty($c['salida_fecha_hora'])) continue;
        try {
            $seg = epochUtc($c['salida_fecha_hora']) - epochUtc($c['entrada_fecha_hora']);
        } catch (Throwable $e) {
            continue;
        }
        if ($seg >= 0 && $seg < VISITA_CORTA_SEG) $ids[] = (int)$c['id'];
    }
    $res = [];
    foreach ($ids as $id) {
        try {
            $info = gpsEnSitioCita($db, $id);
            if ($info !== null) $res[$id] = $info;
        } catch (Throwable $e) {
            error_log('[VISITAS] gpsEnSitioCita ' . $id . ': ' . $e->getMessage());
        }
    }
    return $res;
}

function gpsEnSitioCita(PDO $db, int $citaId): ?array {
    $stmt = $db->prepare(
        "SELECT c.vendedor_id, cl.lat AS pin_lat, cl.lng AS pin_lng,
                e.fecha_hora AS e_fecha, e.lat AS e_lat, e.lng AS e_lng, e.accuracy AS e_acc,
                s.fecha_hora AS s_fecha, s.lat AS s_lat, s.lng AS s_lng, s.accuracy AS s_acc
         FROM citas c
         JOIN clientes cl ON cl.id = c.cliente_id
         JOIN LATERAL (SELECT fecha_hora, lat, lng, accuracy FROM checkins ch WHERE ch.cita_id = c.id AND ch.tipo = 'entrada' ORDER BY ch.id DESC LIMIT 1) e ON true
         JOIN LATERAL (SELECT fecha_hora, lat, lng, accuracy FROM checkins ch WHERE ch.cita_id = c.id AND ch.tipo = 'salida' ORDER BY ch.id DESC LIMIT 1) s ON true
         WHERE c.id = ?"
    );
    $stmt->execute([$citaId]);
    $r = $stmt->fetch();
    if (!$r || $r['pin_lat'] === null || $r['pin_lng'] === null) return null;

    $tEntrada = epochUtc($r['e_fecha']);
    $tSalida  = epochUtc($r['s_fecha']);

    $stmt = $db->prepare(
        "SELECT fecha_hora, lat, lng, accuracy FROM tracking_ubicaciones
         WHERE vendedor_id = ?
           AND fecha_hora BETWEEN ?::timestamp - make_interval(mins => ?) AND ?::timestamp + make_interval(mins => ?)
         ORDER BY fecha_hora ASC"
    );
    $stmt->execute([$r['vendedor_id'], $r['e_fecha'], GPS_SITIO_ANTES_MIN, $r['s_fecha'], GPS_SITIO_DESPUES_MIN]);

    $pinLat = (float)$r['pin_lat'];
    $pinLng = (float)$r['pin_lng'];
    $cerca = function ($lat, $lng, $acc) use ($pinLat, $pinLng): bool {
        $margen = GPS_SITIO_RADIO_M + min($acc !== null ? (float)$acc : 0, GPS_SITIO_PRECISION_MAX_M);
        return haversineDistance((float)$lat, (float)$lng, $pinLat, $pinLng) <= $margen;
    };

    // Puntos útiles (precisión aceptable), más los dos check-ins.
    $puntos = [];
    foreach ($stmt->fetchAll() as $p) {
        if ($p['accuracy'] !== null && (float)$p['accuracy'] > GPS_SITIO_PRECISION_MAX_M) continue;
        $puntos[] = ['t' => epochUtc($p['fecha_hora']), 'cerca' => $cerca($p['lat'], $p['lng'], $p['accuracy'])];
    }
    $puntos[] = ['t' => $tEntrada, 'cerca' => $cerca($r['e_lat'], $r['e_lng'], $r['e_acc'])];
    $puntos[] = ['t' => $tSalida,  'cerca' => $cerca($r['s_lat'], $r['s_lng'], $r['s_acc'])];
    usort($puntos, fn($a, $b) => $a['t'] <=> $b['t']);

    // Si ni la entrada ni la salida están cerca del pin no hay "en el lugar"
    // que medir (eso ya lo cubre "Fuera de zona").
    $iEntrada = null; $iSalida = null;
    foreach ($puntos as $i => $p) {
        if ($iEntrada === null && $p['t'] >= $tEntrada && $p['cerca']) $iEntrada = $i;
        if ($p['t'] <= $tSalida && $p['cerca']) $iSalida = $i;
    }
    if ($iEntrada === null || $iSalida === null || $iSalida < $iEntrada) {
        return ['minutos' => 0, 'desde' => null, 'hasta' => null, 'hueco_desde' => null, 'hueco_hasta' => null];
    }

    // Tramo continuo cerca del pin: hacia atrás desde la entrada y hacia
    // adelante desde la salida, hasta el primer punto lejos.
    $ini = $iEntrada;
    while ($ini > 0 && $puntos[$ini - 1]['cerca']) $ini--;
    $fin = $iSalida;
    while ($fin < count($puntos) - 1 && $puntos[$fin + 1]['cerca']) $fin++;

    $hueco = null; $huecoSeg = GPS_SITIO_HUECO_MIN * 60;
    for ($i = $ini + 1; $i <= $fin; $i++) {
        $d = $puntos[$i]['t'] - $puntos[$i - 1]['t'];
        if ($d > $huecoSeg) { $huecoSeg = $d; $hueco = [$puntos[$i - 1]['t'], $puntos[$i]['t']]; }
    }

    return [
        'minutos'     => (int)round(($puntos[$fin]['t'] - $puntos[$ini]['t']) / 60),
        'desde'       => fechaUtc($puntos[$ini]['t']),
        'hasta'       => fechaUtc($puntos[$fin]['t']),
        'hueco_desde' => $hueco ? fechaUtc($hueco[0]) : null,
        'hueco_hasta' => $hueco ? fechaUtc($hueco[1]) : null,
    ];
}

/** Agrega gps_en_sitio (o null) a cada cita del arreglo. */
function agregarGpsEnSitio(PDO $db, array &$citas): void {
    $gps = gpsEnSitioParaCitas($db, $citas);
    foreach ($citas as &$c) {
        $c['gps_en_sitio'] = $gps[(int)$c['id']] ?? null;
    }
    unset($c);
}
