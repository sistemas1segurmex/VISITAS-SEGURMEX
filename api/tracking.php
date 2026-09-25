<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$u  = requireLogin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole('vendedor');

    // Lote de puntos (assets/js/vendedor.js, vaciarColaTracking): los que el
    // celular juntó sin señal, cada uno con la hora real del GPS (ts, ms).
    // Mismos filtros que un punto suelto; los inválidos se saltan sin
    // tumbar el lote. La hora se acota a las últimas 24 h (reloj del
    // celular mal puesto) -- fuera de eso se usa la del servidor.
    if (isset($_POST['puntos'])) {
        $puntos = json_decode((string)$_POST['puntos'], true);
        if (!is_array($puntos)) {
            jsonResponse(['ok' => false, 'error' => 'Lote inválido'], 400);
        }
        $ins = $db->prepare(
            'INSERT INTO tracking_ubicaciones (vendedor_id, lat, lng, accuracy, fecha_hora)
             VALUES (?, ?, ?, ?, COALESCE(to_timestamp(?::double precision), CURRENT_TIMESTAMP))'
        );
        $ahora = time();
        $guardados = 0;
        foreach (array_slice($puntos, -300) as $p) {
            $lat = (float)($p['lat'] ?? 0);
            $lng = (float)($p['lng'] ?? 0);
            $acc = isset($p['accuracy']) && is_numeric($p['accuracy']) ? (float)$p['accuracy'] : null;
            if (!$lat || !$lng || ($acc !== null && $acc > 500)) continue;
            $ts = isset($p['ts']) && is_numeric($p['ts']) ? (float)$p['ts'] / 1000 : null;
            if ($ts !== null && ($ts < $ahora - 86400 || $ts > $ahora + 300)) $ts = null;
            $ins->execute([$u['id'], $lat, $lng, $acc, $ts]);
            $guardados++;
        }
        jsonResponse(['ok' => true, 'guardados' => $guardados]);
    }

    $lat = isset($_POST['lat']) ? (float)$_POST['lat'] : 0.0;
    $lng = isset($_POST['lng']) ? (float)$_POST['lng'] : 0.0;
    $accuracy = isset($_POST['accuracy']) && $_POST['accuracy'] !== '' ? (float)$_POST['accuracy'] : null;
    if (!$lat || !$lng) {
        jsonResponse(['ok' => false, 'error' => 'GPS inválido'], 400);
    }
    // Filtro también aquí (no solo en el JS del vendedor) por si llega una
    // versión vieja de la app o alguien pega directo al endpoint: un fix sin
    // GPS real (red/Wi-Fi/IP) trae accuracy de varios km y puede marcar al
    // vendedor en otra ciudad aunque no se haya movido.
    if ($accuracy !== null && $accuracy > 500) {
        jsonResponse(['ok' => false, 'error' => 'Ubicación descartada por baja precisión'], 200);
    }
    $db->prepare('INSERT INTO tracking_ubicaciones (vendedor_id, lat, lng, accuracy) VALUES (?,?,?,?)')
       ->execute([$u['id'], $lat, $lng, $accuracy]);
    jsonResponse(['ok' => true]);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    requireRole('admin');
    // Todos los vendedores activos, con su última ubicación conocida (aunque
    // sea de días atrás), su estado de conexión y su eficiencia del día
    // (citas completadas / citas totales de hoy). Los que nunca han
    // reportado salen con lat/lng en null.
    //
    // estado_conexion tiene 3 valores (antes solo había en_linea/desconectado):
    //   'en_linea'      → reportó hace <=15 min
    //   'perdida'       → nunca reportó, o su última señal es de hace más de
    //                     CONEXION_PERDIDA_HORAS (ver constante abajo) — se
    //                     pinta distinto en el mapa para saltar a la vista.
    //   'desconectado'  → el resto (reportó hoy, pero hace rato)
    $stmt = $db->query(
        "SELECT u.id AS vendedor_id, u.nombre, u.estado_operacion, t.lat, t.lng, t.fecha_hora,
                CASE WHEN t.fecha_hora IS NOT NULL AND t.fecha_hora >= NOW() - INTERVAL '15 minutes'
                     THEN 1 ELSE 0 END AS en_linea,
                CASE
                    WHEN t.fecha_hora IS NOT NULL AND t.fecha_hora >= NOW() - INTERVAL '15 minutes' THEN 'en_linea'
                    WHEN t.fecha_hora IS NULL OR t.fecha_hora < NOW() - INTERVAL '" . CONEXION_PERDIDA_HORAS . " hours' THEN 'perdida'
                    ELSE 'desconectado'
                END AS estado_conexion,
                COALESCE(c.total, 0) AS citas_total,
                COALESCE(c.completadas, 0) AS citas_completadas,
                CASE WHEN COALESCE(c.total, 0) > 0
                     THEN ROUND(100.0 * c.completadas / c.total)
                     ELSE NULL END AS eficiencia_pct
         FROM usuarios u
         LEFT JOIN LATERAL (
             SELECT lat, lng, fecha_hora FROM tracking_ubicaciones
             WHERE vendedor_id = u.id AND (accuracy IS NULL OR accuracy <= 500)
             ORDER BY fecha_hora DESC LIMIT 1
         ) t ON true
         LEFT JOIN LATERAL (
             SELECT COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE estado = 'completada') AS completadas
             FROM citas
             WHERE vendedor_id = u.id AND DATE(fecha_hora) = CURRENT_DATE
         ) c ON true
         WHERE u.rol = 'vendedor' AND u.activo = 1
         ORDER BY u.nombre"
    );
    $ubicaciones = $stmt->fetchAll();
    // Ciudad/municipio real según el GPS (no el territorio asignado a mano)
    // para mostrar en el popup del mapa -- ver nombreLugarGPS() en helpers.php.
    foreach ($ubicaciones as &$row) {
        $row['lugar'] = ($row['lat'] !== null && $row['lng'] !== null)
            ? nombreLugarGPS((float)$row['lat'], (float)$row['lng'])
            : null;
    }
    unset($row);
    jsonResponse(['ok' => true, 'ubicaciones' => $ubicaciones]);
}

jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
