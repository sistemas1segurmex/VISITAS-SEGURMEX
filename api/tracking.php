<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$u  = requireLogin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole('vendedor');
    $lat = isset($_POST['lat']) ? (float)$_POST['lat'] : 0.0;
    $lng = isset($_POST['lng']) ? (float)$_POST['lng'] : 0.0;
    if (!$lat || !$lng) {
        jsonResponse(['ok' => false, 'error' => 'GPS inválido'], 400);
    }
    $db->prepare('INSERT INTO tracking_ubicaciones (vendedor_id, lat, lng) VALUES (?,?,?)')
       ->execute([$u['id'], $lat, $lng]);
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
             WHERE vendedor_id = u.id
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
    jsonResponse(['ok' => true, 'ubicaciones' => $stmt->fetchAll()]);
}

jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
