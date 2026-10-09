<?php
/**
 * Alertas del admin (recuadro "Alertas" de admin/index.php) -- etapa 1 del
 * rediseño acordado el 30-sep-2026.
 *
 * Todo vive en la tabla `alertas` que ya existía, SIN cambios de esquema:
 *   - tipo (VARCHAR libre): los tipos nuevos de ALERTA_TIPOS.
 *   - resuelta: 0 = abierta, 1 = la marcó el admin, 2 = se resolvió sola
 *     (cambió lo que la provocó). Al resolverse sola, `mensaje` se reemplaza
 *     por el motivo ("Registró la salida a las ...") para poder mostrarlo en
 *     "Se resolvieron solas" -- el texto original ya no hace falta.
 *   - la prioridad (atender / revisar / info) no se guarda: sale del tipo.
 *
 * No hay cron todavía (etapa 2): igual que generarAlertasSinActividad(), se
 * generan de forma perezosa cuando el admin carga sus alertas, pero como el
 * panel se refresca cada 20 s la revisión completa corre como máximo cada
 * ALERTAS_REVISION_CADA_SEG (ver revisarAlertas()).
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/visita_checkins.php';

define('ALERTAS_REVISION_CADA_SEG', 60);
define('ALERTAS_MARCA_REVISION', __DIR__ . '/../uploads/cache/alertas_ultima_revision.txt');

define('VISITA_SIN_CERRAR_HORAS', 3);          // entrada sin salida pasado esto
define('SIN_GPS_HORAS', 2);                    // sin mandar ubicación con citas pendientes
define('SIN_GPS_HORARIO', [8, 19]);            // solo en horario laboral (hora CDMX)
define('REPROGRAMACIONES_ALERTA', 2);          // la misma cita movida esto o más veces
define('INTERESADO_SIN_COTIZAR_DIAS', 3);      // "muy interesado" sin cotización en el ERP
define('ALERTAS_DIAS_ATRAS', 7);               // no se revisa historia más vieja que esto
define('ACCESO_ENTRADAS_SEGUIDAS', 3);         // entradas correctas en ACCESO_VENTANA_MIN = algo la saca
define('ACCESO_VENTANA_MIN', 60);

// tipo => [prioridad, título corto]. Prioridad: 'crit' (atender hoy),
// 'warn' (revisar), 'info' (informativa).
const ALERTA_TIPOS = [
    'ubicacion_por_revisar'     => ['crit', 'Ubicación por revisar'],
    'visita_sin_cerrar'         => ['crit', 'Visita sin cerrar'],
    'fuera_de_zona'             => ['crit', 'Fuera de zona'],
    'sin_gps'                   => ['crit', 'Sin GPS en horario laboral'],
    'problemas_acceso'          => ['crit', 'Problemas para entrar'],
    'visita_corta'              => ['warn', 'Visita muy corta'],
    'visita_corta_gps'          => ['info', 'Entrada y salida juntas'],
    'interesado_sin_cotizacion' => ['warn', 'Muy interesado sin cotización'],
    'reprogramaciones'          => ['warn', 'Reprogramaciones repetidas'],
    'cita_perdida'              => ['warn', 'Cita perdida'],
    'sin_seguimiento'           => ['info', 'Sin seguimiento'],
    'sin_actividad'             => ['info', 'Sin actividad'],
];

function prioridadAlerta(string $tipo): string {
    return ALERTA_TIPOS[$tipo][0] ?? 'info';
}

function tituloAlerta(string $tipo): string {
    return ALERTA_TIPOS[$tipo][1] ?? ucfirst(str_replace('_', ' ', $tipo));
}

/** Hora corta de México ("11:48 a.m.") a partir de un timestamp UTC de la BD. */
function horaMxDesdeUtc(string $utc): string {
    $d = (new DateTime($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Mexico_City'));
    return $d->format('g:i') . ($d->format('G') < 12 ? ' a.m.' : ' p.m.');
}

/** "28 sep, 3:00 p.m." a partir de citas.fecha_hora (ya es hora local de CDMX). */
function fechaCitaCorta(string $local): string {
    $meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    $d = new DateTime($local);
    return $d->format('j') . ' ' . $meses[(int)$d->format('n') - 1] . ', ' . $d->format('g:i') . ($d->format('G') < 12 ? ' a.m.' : ' p.m.');
}

function recortarMensaje(string $m): string {
    $m = preg_replace('/(a|p)\.m\.\.$/', '$1.m.', $m); // "a las 9:51 a.m." + punto final
    return mb_strlen($m) > 250 ? mb_substr($m, 0, 247) . '...' : $m;
}

/**
 * Punto de entrada: genera las alertas nuevas y cierra solas las que ya no
 * aplican, como máximo cada ALERTAS_REVISION_CADA_SEG. Nunca tumba el panel.
 */
function revisarAlertas(PDO $db): void {
    $marca = ALERTAS_MARCA_REVISION;
    $ultima = @filemtime($marca);
    if ($ultima && (time() - $ultima) < ALERTAS_REVISION_CADA_SEG) return;
    @touch($marca);

    // Las que ya existían.
    generarAlertasSinActividad($db);
    generarAlertasSinSeguimiento($db);
    marcarCitasVencidas($db);

    foreach ([
        'generarAlertaUbicacionPorRevisar',
        'generarAlertaVisitaSinCerrar',
        'generarAlertaVisitaCorta',
        'generarAlertaCitaPerdida',
        'generarAlertaReprogramaciones',
        'generarAlertaSinGps',
        'generarAlertaInteresadoSinCotizacion',
        'generarAlertaProblemasAcceso',
        'autoResolverAlertas',
    ] as $fn) {
        try {
            $fn($db);
        } catch (Throwable $e) {
            error_log("[VISITAS] $fn: " . $e->getMessage());
        }
    }
}

function insertarAlerta(PDO $db, int $vendedorId, ?int $citaId, string $tipo, string $mensaje): void {
    $db->prepare('INSERT INTO alertas (vendedor_id, cita_id, tipo, mensaje) VALUES (?,?,?,?)')
       ->execute([$vendedorId, $citaId, $tipo, recortarMensaje($mensaje)]);
}

// Una alerta por (tipo, cita) aunque ya se haya resuelto: si el admin la
// marcó, no vuelve a salir por la misma cita.
function sqlSinAlertaPrevia(string $tipo): string {
    return "NOT EXISTS (SELECT 1 FROM alertas a WHERE a.cita_id = c.id AND a.tipo = '$tipo')";
}

// ── 1. Ubicación por revisar (corrección de pin a más de 3 km) ───────────
function generarAlertaUbicacionPorRevisar(PDO $db): void {
    $stmt = $db->query(
        "SELECT c.id, cu.vendedor_id, cu.distancia_metros, cu.nota, cl.nombre AS cliente_nombre
         FROM correcciones_ubicacion cu
         JOIN checkins ch ON ch.id = cu.checkin_id
         JOIN citas c ON c.id = ch.cita_id
         JOIN clientes cl ON cl.id = cu.cliente_id
         WHERE cu.estado = 'por_revisar' AND " . sqlSinAlertaPrevia('ubicacion_por_revisar')
    );
    foreach ($stmt->fetchAll() as $r) {
        $km = number_format(((float)$r['distancia_metros']) / 1000, 1);
        if (str_starts_with((string)$r['nota'], CORRECCION_NOTA_AVISO)) {
            insertarAlerta($db, (int)$r['vendedor_id'], (int)$r['id'], 'ubicacion_por_revisar',
                "Avisó que sí está en {$r['cliente_nombre']}, pero la ubicación guardada está a {$km} km. El pin no se movió: apruébalo para moverlo a donde estuvo o revierte.");
            continue;
        }
        insertarAlerta($db, (int)$r['vendedor_id'], (int)$r['id'], 'ubicacion_por_revisar',
            "Movió el pin de {$r['cliente_nombre']} al hacer check-in. El pin anterior estaba a {$km} km, más de los 3 km que se corrigen solos.");
    }
}

// ── 3. Visita sin cerrar (entrada sin salida después de 3 h) ─────────────
function generarAlertaVisitaSinCerrar(PDO $db): void {
    $stmt = $db->prepare(
        "SELECT c.id, c.vendedor_id, cl.nombre AS cliente_nombre, e.fecha_hora AS entrada
         FROM citas c
         JOIN clientes cl ON cl.id = c.cliente_id
         JOIN LATERAL (SELECT fecha_hora FROM checkins ch WHERE ch.cita_id = c.id AND ch.tipo = 'entrada' ORDER BY ch.id DESC LIMIT 1) e ON true
         WHERE c.estado = 'en_curso'
           AND e.fecha_hora < NOW() - make_interval(hours => ?)
           AND e.fecha_hora > NOW() - make_interval(days => ?)
           AND NOT EXISTS (SELECT 1 FROM checkins s WHERE s.cita_id = c.id AND s.tipo = 'salida')
           AND " . sqlSinAlertaPrevia('visita_sin_cerrar')
    );
    $stmt->execute([VISITA_SIN_CERRAR_HORAS, 2]);
    foreach ($stmt->fetchAll() as $r) {
        insertarAlerta($db, (int)$r['vendedor_id'], (int)$r['id'], 'visita_sin_cerrar',
            "Entrada a las " . horaMxDesdeUtc($r['entrada']) . " en {$r['cliente_nombre']} y no registra salida. Puede que olvidara cerrar la visita.");
    }
}

// ── 2. Visita muy corta (menos de 2 min entre entrada y salida) ──────────
// Desde el 9-oct-2026 también se mira el GPS: si el tracking lo ubica en el
// lugar al menos GPS_SITIO_MINIMO_REAL_MIN (caso Norma en Grupo corporativo
// papelero: entrada y salida en 1 min, pero ~53 min cerca del cliente), la
// alerta baja a informativa ("Entrada y salida juntas") en vez de "Revisar".
// Ver gpsEnSitioCita() en includes/visita_checkins.php.
function generarAlertaVisitaCorta(PDO $db): void {
    $stmt = $db->prepare(
        "SELECT c.id, c.vendedor_id, cl.nombre AS cliente_nombre,
                EXTRACT(EPOCH FROM (s.fecha_hora - e.fecha_hora)) AS segundos
         FROM citas c
         JOIN clientes cl ON cl.id = c.cliente_id
         JOIN LATERAL (SELECT fecha_hora FROM checkins ch WHERE ch.cita_id = c.id AND ch.tipo = 'entrada' ORDER BY ch.id DESC LIMIT 1) e ON true
         JOIN LATERAL (SELECT fecha_hora FROM checkins ch WHERE ch.cita_id = c.id AND ch.tipo = 'salida' ORDER BY ch.id DESC LIMIT 1) s ON true
         WHERE s.fecha_hora > NOW() - make_interval(days => ?)
           AND s.fecha_hora >= e.fecha_hora
           AND s.fecha_hora - e.fecha_hora < make_interval(secs => ?)
           AND " . sqlSinAlertaPrevia('visita_corta') . "
           AND " . sqlSinAlertaPrevia('visita_corta_gps')
    );
    $stmt->execute([ALERTAS_DIAS_ATRAS, VISITA_CORTA_SEG]);
    foreach ($stmt->fetchAll() as $r) {
        $seg = (int)round((float)$r['segundos']);
        $gps = null;
        try {
            $gps = gpsEnSitioCita($db, (int)$r['id']);
        } catch (Throwable $e) {
            error_log('[VISITAS] gpsEnSitioCita ' . $r['id'] . ': ' . $e->getMessage());
        }

        if ($gps && $gps['minutos'] >= GPS_SITIO_MINIMO_REAL_MIN) {
            $hueco = $gps['hueco_desde']
                ? ' Sin señal de ' . horaMxDesdeUtc($gps['hueco_desde']) . ' a ' . horaMxDesdeUtc($gps['hueco_hasta']) . '.'
                : '';
            insertarAlerta($db, (int)$r['vendedor_id'], (int)$r['id'], 'visita_corta_gps',
                "En {$r['cliente_nombre']} registró entrada y salida con {$seg} seg de diferencia, pero el GPS lo ubica ~{$gps['minutos']} min en el lugar ("
                . horaMxDesdeUtc($gps['desde']) . '–' . horaMxDesdeUtc($gps['hasta']) . ").{$hueco}");
            continue;
        }

        $extra = $gps === null ? '' : ($gps['minutos'] > 0
            ? " El GPS tampoco lo ubica más tiempo ahí (~{$gps['minutos']} min)."
            : ' El GPS tampoco lo ubica en el lugar.');
        insertarAlerta($db, (int)$r['vendedor_id'], (int)$r['id'], 'visita_corta',
            "En {$r['cliente_nombre']} la salida se registró {$seg} seg después de la entrada. No da tiempo de una visita real.{$extra}");
    }
}

// ── 4. Cita perdida (pasó sola a No realizada por no atenderse) ──────────
function generarAlertaCitaPerdida(PDO $db): void {
    $stmt = $db->prepare(
        "SELECT c.id, c.vendedor_id, c.fecha_hora, cl.nombre AS cliente_nombre
         FROM citas c
         JOIN clientes cl ON cl.id = c.cliente_id
         WHERE c.estado = 'no_realizada' AND c.motivo = ?
           AND c.fecha_hora > (NOW() AT TIME ZONE 'America/Mexico_City') - make_interval(days => ?)
           AND NOT EXISTS (SELECT 1 FROM citas c2 WHERE c2.cliente_id = c.cliente_id AND c2.fecha_hora > c.fecha_hora)
           AND " . sqlSinAlertaPrevia('cita_perdida')
    );
    $stmt->execute([MOTIVO_CITA_VENCIDA, ALERTAS_DIAS_ATRAS]);
    foreach ($stmt->fetchAll() as $r) {
        insertarAlerta($db, (int)$r['vendedor_id'], (int)$r['id'], 'cita_perdida',
            "La cita del " . fechaCitaCorta($r['fecha_hora']) . " con {$r['cliente_nombre']} nadie la atendió y pasó sola a No realizada. No tiene cita nueva.");
    }
}

// ── 5. Reprogramaciones repetidas ────────────────────────────────────────
function generarAlertaReprogramaciones(PDO $db): void {
    $stmt = $db->prepare(
        "SELECT * FROM (
            SELECT c.id, c.vendedor_id, c.estado, cl.nombre AS cliente_nombre, " . sqlReprogramaciones() . "
            FROM citas c JOIN clientes cl ON cl.id = c.cliente_id
            WHERE c.estado IN ('pendiente', 'en_curso')
              AND " . sqlSinAlertaPrevia('reprogramaciones') . "
         ) x WHERE x.reprogramaciones >= ?"
    );
    $stmt->execute([REPROGRAMACIONES_ALERTA]);
    foreach ($stmt->fetchAll() as $r) {
        $motivo = $r['motivo_reprogramacion'] ? " Último motivo: \"{$r['motivo_reprogramacion']}\"." : '';
        insertarAlerta($db, (int)$r['vendedor_id'], (int)$r['id'], 'reprogramaciones',
            "La cita con {$r['cliente_nombre']} se ha movido {$r['reprogramaciones']} veces.{$motivo}");
    }
}

// ── 6. Sin GPS en horario laboral, con citas pendientes hoy ──────────────
function generarAlertaSinGps(PDO $db): void {
    $tzMx = new DateTimeZone('America/Mexico_City');
    $ahora = new DateTime('now', $tzMx);
    $hora = (int)$ahora->format('G');
    [$desde, $hasta] = SIN_GPS_HORARIO;
    if ($hora < $desde || $hora >= $hasta) return;

    $hoy = $ahora->format('Y-m-d');
    $inicioUtc = (new DateTime($hoy . ' 00:00:00', $tzMx))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

    // Solo quien sí mandó GPS hoy (empezó su día) y dejó de mandarlo: si nunca
    // abrió la app ya lo cubre "Sin actividad". Y solo con una cita pendiente
    // que ya pasó o está por empezar (en la próxima hora).
    $stmt = $db->prepare(
        "SELECT u.id, t.ultima
         FROM usuarios u
         JOIN LATERAL (SELECT MAX(fecha_hora) AS ultima FROM tracking_ubicaciones tu WHERE tu.vendedor_id = u.id AND tu.fecha_hora >= ?) t ON true
         WHERE u.rol = 'vendedor' AND u.activo = 1
           AND t.ultima IS NOT NULL
           AND t.ultima < NOW() - make_interval(hours => ?)
           AND EXISTS (
               SELECT 1 FROM citas c WHERE c.vendedor_id = u.id AND DATE(c.fecha_hora) = ?
                 AND c.estado IN ('pendiente', 'en_curso')
                 AND c.fecha_hora <= (NOW() AT TIME ZONE 'America/Mexico_City') + make_interval(hours => 1)
           )
           AND NOT EXISTS (
               SELECT 1 FROM alertas a WHERE a.vendedor_id = u.id AND a.tipo = 'sin_gps' AND a.created_at >= ?
           )"
    );
    $stmt->execute([$inicioUtc, SIN_GPS_HORAS, $hoy, $inicioUtc]);
    foreach ($stmt->fetchAll() as $r) {
        $min = (int)floor((time() - (new DateTime($r['ultima'], new DateTimeZone('UTC')))->getTimestamp()) / 60);
        $dur = intdiv($min, 60) . ' h' . ($min % 60 ? ' ' . ($min % 60) . ' min' : '');
        insertarAlerta($db, (int)$r['id'], null, 'sin_gps',
            "No manda ubicación desde las " . horaMxDesdeUtc($r['ultima']) . " ({$dur}) y tiene citas pendientes hoy.");
    }
}

// ── 7. Muy interesado sin cotización en el ERP ───────────────────────────
// La cotización vive en el ERP (public.cotizaciones), ligada al cliente de
// VISITAS por cotizador_prospectos.visitas_cliente_id (ver
// obtenerOCrearProspectoErp en includes/cotizador_helpers.php). Mismo
// servidor de Postgres, otro esquema.
function sqlTieneCotizacionErp(): string {
    return "EXISTS (SELECT 1 FROM public.cotizaciones co
                    JOIN public.cotizador_prospectos p ON p.id = co.id_prospecto
                    WHERE p.visitas_cliente_id = cl.id)";
}

function generarAlertaInteresadoSinCotizacion(PDO $db): void {
    $stmt = $db->prepare(
        "SELECT c.id, c.vendedor_id, c.fecha_hora, cl.nombre AS cliente_nombre
         FROM citas c
         JOIN clientes cl ON cl.id = c.cliente_id
         WHERE c.estado = 'completada' AND c.interes = 'muy_interesado'
           AND c.fecha_hora < (NOW() AT TIME ZONE 'America/Mexico_City') - make_interval(days => ?)
           AND c.fecha_hora > (NOW() AT TIME ZONE 'America/Mexico_City') - make_interval(days => 30)
           AND cl.etapa NOT IN ('convertido', 'perdido')
           AND NOT " . sqlTieneCotizacionErp() . "
           AND " . sqlSinAlertaPrevia('interesado_sin_cotizacion')
    );
    $stmt->execute([INTERESADO_SIN_COTIZAR_DIAS]);
    foreach ($stmt->fetchAll() as $r) {
        $dias = (int)floor((time() - strtotime($r['fecha_hora'])) / 86400);
        insertarAlerta($db, (int)$r['vendedor_id'], (int)$r['id'], 'interesado_sin_cotizacion',
            "{$r['cliente_nombre']} quedó \"Muy interesado\" el " . fechaCitaCorta($r['fecha_hora']) . " y en {$dias} días no hay cotización en el ERP.");
    }
}

// ── Problemas para entrar (2-oct-2026) ───────────────────────────────────
// La app Android se cerraba sola al entrar y cada cierre dejaba una sesión
// ocupada: el vendedor entraba una y otra vez y terminaba bloqueado por
// SESION_MAX_ACTIVAS, sin que nadie se enterara (Oscar el 24-sep, Marcela
// el 2-oct). Señales, sacadas de usuarios_accesos_historial (hoy, hora MX):
//   - algún bloqueo por límite de sesiones, o
//   - ACCESO_ENTRADAS_SEGUIDAS entradas correctas en ACCESO_VENTANA_MIN
//     (quien usa la app normal entra una vez al día, no cada pocos minutos).
// Una por vendedor por día; se sigue mostrando solo la de hoy (alertasParaPanel).
function generarAlertaProblemasAcceso(PDO $db): void {
    $tzMx = new DateTimeZone('America/Mexico_City');
    $inicioUtc = (new DateTime('today', $tzMx))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

    $stmt = $db->prepare(
        "SELECT u.id,
                COUNT(*) FILTER (WHERE h.resultado = 'bloqueado_limite') AS bloqueos,
                COUNT(*) FILTER (WHERE h.resultado = 'correcto') AS correctas,
                MAX(h.creado_en) AS ultima,
                BOOL_OR(h.user_agent LIKE '%; wv)%') AS desde_app,
                (SELECT MAX(n) FROM (
                    SELECT COUNT(*) OVER (ORDER BY h2.creado_en
                                          RANGE BETWEEN INTERVAL '" . (int)ACCESO_VENTANA_MIN . " minutes' PRECEDING AND CURRENT ROW) AS n
                    FROM usuarios_accesos_historial h2
                    WHERE h2.usuario_id = u.id AND h2.resultado = 'correcto' AND h2.creado_en >= ?
                 ) v) AS max_en_ventana
         FROM usuarios u
         JOIN usuarios_accesos_historial h ON h.usuario_id = u.id AND h.creado_en >= ?
         WHERE u.rol = 'vendedor' AND u.activo = 1
           AND NOT EXISTS (SELECT 1 FROM alertas a WHERE a.vendedor_id = u.id AND a.tipo = 'problemas_acceso' AND a.created_at >= ?)
         GROUP BY u.id"
    );
    $stmt->execute([$inicioUtc, $inicioUtc, $inicioUtc]);
    foreach ($stmt->fetchAll() as $r) {
        $bloqueos = (int)$r['bloqueos'];
        $seguidas = (int)$r['max_en_ventana'];
        if ($bloqueos === 0 && $seguidas < ACCESO_ENTRADAS_SEGUIDAS) continue;

        $partes = [];
        if ($seguidas >= ACCESO_ENTRADAS_SEGUIDAS) $partes[] = "entró {$seguidas} veces en menos de una hora";
        if ($bloqueos > 0) $partes[] = "quedó bloqueado {$bloqueos} " . ($bloqueos === 1 ? 'vez' : 'veces') . " por sesiones abiertas";
        $donde = $r['desde_app'] === true || $r['desde_app'] === 't' ? ' desde la app' : '';
        insertarAlerta($db, (int)$r['id'], null, 'problemas_acceso',
            'Hoy ' . implode(' y ', $partes) . "{$donde} (último intento a las " . horaMxDesdeUtc($r['ultima']) . '). Puede que la app se le esté cerrando; llámale para ver si pudo trabajar.');
    }
}

// ── 9. Se resuelven solas ────────────────────────────────────────────────
function cerrarSola(PDO $db, int $id, string $motivo): void {
    $db->prepare('UPDATE alertas SET resuelta = 2, mensaje = ? WHERE id = ? AND resuelta = 0')
       ->execute([recortarMensaje($motivo), $id]);
}

function autoResolverAlertas(PDO $db): void {
    // Ubicación: el admin ya la aprobó o la revirtió (aquí o en Ubicaciones).
    $stmt = $db->query(
        "SELECT a.id, cu.estado, u.nombre AS revisor
         FROM alertas a
         JOIN checkins ch ON ch.cita_id = a.cita_id
         JOIN correcciones_ubicacion cu ON cu.checkin_id = ch.id
         LEFT JOIN usuarios u ON u.id = cu.revisado_por
         WHERE a.resuelta = 0 AND a.tipo = 'ubicacion_por_revisar' AND cu.estado <> 'por_revisar'"
    );
    foreach ($stmt->fetchAll() as $r) {
        $quien = $r['revisor'] ? " por {$r['revisor']}" : '';
        cerrarSola($db, (int)$r['id'], ($r['estado'] === 'revertida' ? 'Revertida' : 'Aprobada') . $quien . '.');
    }

    // Visita sin cerrar: ya registró la salida (o la cita cambió de estado).
    $stmt = $db->query(
        "SELECT a.id, s.fecha_hora AS salida
         FROM alertas a
         JOIN citas c ON c.id = a.cita_id
         LEFT JOIN LATERAL (SELECT fecha_hora FROM checkins ch WHERE ch.cita_id = c.id AND ch.tipo = 'salida' ORDER BY ch.id DESC LIMIT 1) s ON true
         WHERE a.resuelta = 0 AND a.tipo = 'visita_sin_cerrar' AND (s.fecha_hora IS NOT NULL OR c.estado <> 'en_curso')"
    );
    foreach ($stmt->fetchAll() as $r) {
        cerrarSola($db, (int)$r['id'], $r['salida'] ? 'Registró la salida a las ' . horaMxDesdeUtc($r['salida']) . '.' : 'La cita ya no está en curso.');
    }

    // Reprogramaciones: la visita ya se hizo (o se canceló).
    $stmt = $db->query(
        "SELECT a.id, c.estado FROM alertas a JOIN citas c ON c.id = a.cita_id
         WHERE a.resuelta = 0 AND a.tipo = 'reprogramaciones' AND c.estado NOT IN ('pendiente', 'en_curso')"
    );
    foreach ($stmt->fetchAll() as $r) {
        cerrarSola($db, (int)$r['id'], $r['estado'] === 'completada' ? 'La visita ya se realizó.' : 'La cita ya se cerró.');
    }

    // Cita perdida y sin seguimiento: ya hay otra cita con ese cliente, o el
    // cliente ya se cerró (convertido / perdido).
    $stmt = $db->query(
        "SELECT a.id, cl.etapa,
                (SELECT MIN(c2.fecha_hora) FROM citas c2 WHERE c2.cliente_id = c.cliente_id AND c2.fecha_hora > c.fecha_hora) AS nueva
         FROM alertas a JOIN citas c ON c.id = a.cita_id JOIN clientes cl ON cl.id = c.cliente_id
         WHERE a.resuelta = 0 AND a.tipo IN ('cita_perdida', 'sin_seguimiento')"
    );
    foreach ($stmt->fetchAll() as $r) {
        if ($r['nueva']) {
            cerrarSola($db, (int)$r['id'], 'Agendó una cita nueva para el ' . fechaCitaCorta($r['nueva']) . '.');
        } elseif (in_array($r['etapa'], ['convertido', 'perdido'], true)) {
            cerrarSola($db, (int)$r['id'], 'El cliente ya se marcó como ' . etiquetaEtapa($r['etapa']) . '.');
        }
    }

    // Sin GPS: volvió a mandar ubicación después de la alerta.
    $stmt = $db->query(
        "SELECT a.id, (SELECT MIN(t.fecha_hora) FROM tracking_ubicaciones t WHERE t.vendedor_id = a.vendedor_id AND t.fecha_hora > a.created_at) AS volvio
         FROM alertas a WHERE a.resuelta = 0 AND a.tipo = 'sin_gps'"
    );
    foreach ($stmt->fetchAll() as $r) {
        if ($r['volvio']) cerrarSola($db, (int)$r['id'], 'Volvió a mandar ubicación a las ' . horaMxDesdeUtc($r['volvio']) . '.');
    }

    // Sin actividad: ese mismo día ya registró algo (GPS, cliente nuevo o prospección).
    $stmt = $db->query(
        "SELECT a.id FROM alertas a
         WHERE a.resuelta = 0 AND a.tipo = 'sin_actividad'
           AND (EXISTS (SELECT 1 FROM tracking_ubicaciones t WHERE t.vendedor_id = a.vendedor_id AND t.fecha_hora > a.created_at AND t.fecha_hora::date = a.created_at::date)
             OR EXISTS (SELECT 1 FROM clientes cl WHERE cl.vendedor_id = a.vendedor_id AND cl.created_at > a.created_at AND cl.created_at::date = a.created_at::date))"
    );
    foreach ($stmt->fetchAll() as $r) {
        cerrarSola($db, (int)$r['id'], 'Ya registró actividad ese día.');
    }

    // Muy interesado sin cotización: ya se le cotizó en el ERP.
    $stmt = $db->query(
        "SELECT a.id FROM alertas a JOIN citas c ON c.id = a.cita_id JOIN clientes cl ON cl.id = c.cliente_id
         WHERE a.resuelta = 0 AND a.tipo = 'interesado_sin_cotizacion' AND " . sqlTieneCotizacionErp()
    );
    foreach ($stmt->fetchAll() as $r) {
        cerrarSola($db, (int)$r['id'], 'Ya tiene cotización en el ERP.');
    }
}

/**
 * Datos para el recuadro: abiertas (con lo necesario para sus botones) y
 * las que se resolvieron solas en los últimos 2 días.
 */
function alertasParaPanel(PDO $db): array {
    $sqlCampos =
        "a.id, a.vendedor_id, a.cita_id, a.tipo, a.mensaje, a.created_at,
         u.nombre AS vendedor_nombre, u.foto_path AS vendedor_foto,
         c.fecha_hora AS cita_fecha, cl.nombre AS cliente_nombre,
         (SELECT cu.id FROM correcciones_ubicacion cu JOIN checkins ch ON ch.id = cu.checkin_id
            WHERE ch.cita_id = a.cita_id AND cu.estado = 'por_revisar' ORDER BY cu.id DESC LIMIT 1) AS correccion_id,
         (SELECT ch.id FROM checkins ch WHERE ch.cita_id = a.cita_id AND ch.tipo = 'entrada' AND ch.foto_path IS NOT NULL ORDER BY ch.id DESC LIMIT 1) AS foto_entrada_id,
         (SELECT ch.id FROM checkins ch WHERE ch.cita_id = a.cita_id AND ch.tipo = 'salida' AND ch.foto_path IS NOT NULL ORDER BY ch.id DESC LIMIT 1) AS foto_salida_id";
    $from = "FROM alertas a
             JOIN usuarios u ON u.id = a.vendedor_id
             LEFT JOIN citas c ON c.id = a.cita_id
             LEFT JOIN clientes cl ON cl.id = c.cliente_id";

    // "Sin actividad", "Sin GPS" y "Problemas para entrar" hablan de un día
    // concreto: las de días anteriores ya no se pueden atender, así que solo
    // se muestran las de hoy (se quedan en la tabla sin tocar).
    $tzMx = new DateTimeZone('America/Mexico_City');
    $inicioHoyUtc = (new DateTime('today', $tzMx))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $stmt = $db->prepare(
        "SELECT $sqlCampos $from
         WHERE a.resuelta = 0
           AND NOT (a.tipo IN ('sin_actividad', 'sin_gps', 'problemas_acceso') AND a.created_at < ?)
         ORDER BY a.created_at DESC LIMIT 150"
    );
    $stmt->execute([$inicioHoyUtc]);
    $abiertas = $stmt->fetchAll();
    $solas = $db->query(
        "SELECT a.id, a.tipo, a.mensaje, a.created_at, u.nombre AS vendedor_nombre, cl.nombre AS cliente_nombre
         $from WHERE a.resuelta = 2 AND a.created_at > NOW() - INTERVAL '2 days'
         ORDER BY a.created_at DESC LIMIT 30"
    )->fetchAll();

    foreach ($abiertas as &$a) {
        $a['prioridad'] = prioridadAlerta($a['tipo']);
        $a['titulo'] = tituloAlerta($a['tipo']);
        // Día (hora de México) para "Ver recorrido": el de la cita si hay,
        // si no el de la alerta.
        $a['dia'] = $a['cita_fecha']
            ? substr($a['cita_fecha'], 0, 10)
            : (new DateTime($a['created_at'], new DateTimeZone('UTC')))->setTimezone($tzMx)->format('Y-m-d');
    }
    unset($a);
    foreach ($solas as &$s) {
        $s['titulo'] = tituloAlerta($s['tipo']);
    }
    unset($s);

    return ['alertas' => $abiertas, 'resueltas_solas' => $solas];
}
