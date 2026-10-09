<?php
// Intentos de check-in que nunca llegan a api/checkin.php: abrir la
// pantalla, GPS que no logra precisión y envíos que fallan por señal o
// tiempo agotado. Lo manda vendedor/checkin.php con navigator.sendBeacon
// (ver reportarIntento()). Solo registra; nunca bloquea al vendedor.
// Ver includes/visita_checkins.php.
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/visita_checkins.php';

$u  = requireRole('vendedor');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

// Las demás etapas (rechazo_servidor, pregunta_ubicacion) las registra el
// propio api/checkin.php.
const ETAPAS_DESDE_CELULAR = ['abrio_pantalla', 'gps_impreciso', 'error_envio'];
// La misma etapa repetida en este lapso no se vuelve a guardar (abrir y
// cerrar la pantalla, o el botón "Reintentar" GPS varias veces seguidas).
const INTENTO_REPETIDO_SEG = ['abrio_pantalla' => 300, 'gps_impreciso' => 120, 'error_envio' => 0];

$citaId   = (int)($_POST['cita_id'] ?? 0);
$tipo     = $_POST['tipo'] ?? '';
$etapa    = $_POST['etapa'] ?? '';
$motivo   = trim((string)($_POST['motivo'] ?? '')) ?: null;
$lat      = isset($_POST['lat']) && $_POST['lat'] !== '' ? (float)$_POST['lat'] : null;
$lng      = isset($_POST['lng']) && $_POST['lng'] !== '' ? (float)$_POST['lng'] : null;
$accuracy = isset($_POST['accuracy']) && $_POST['accuracy'] !== '' ? (float)$_POST['accuracy'] : null;

if (!$citaId || !in_array($etapa, ETAPAS_DESDE_CELULAR, true) || !in_array($tipo, ['entrada', 'salida'], true)) {
    jsonResponse(['ok' => false, 'error' => 'Datos incompletos'], 400);
}
if (!existeTablaCheckinIntentos($db)) {
    jsonResponse(['ok' => true, 'omitido' => true]);
}

try {
    $espera = INTENTO_REPETIDO_SEG[$etapa];
    if ($espera > 0) {
        $stmt = $db->prepare(
            'SELECT 1 FROM checkin_intentos
             WHERE cita_id = ? AND vendedor_id = ? AND tipo = ? AND etapa = ?
               AND creado_en > NOW() - make_interval(secs => ?) LIMIT 1'
        );
        $stmt->execute([$citaId, $u['id'], $tipo, $etapa, $espera]);
        if ($stmt->fetchColumn()) {
            jsonResponse(['ok' => true, 'repetido' => true]);
        }
    }

    $distancia = null;
    if ($lat !== null && $lng !== null) {
        $stmt = $db->prepare('SELECT cl.lat, cl.lng FROM citas c JOIN clientes cl ON cl.id = c.cliente_id WHERE c.id = ? AND c.vendedor_id = ?');
        $stmt->execute([$citaId, $u['id']]);
        $pin = $stmt->fetch();
        if ($pin && $pin['lat'] !== null && $pin['lng'] !== null) {
            $distancia = haversineDistance($lat, $lng, (float)$pin['lat'], (float)$pin['lng']);
        }
    }
} catch (Throwable $e) {
    error_log('[VISITAS] checkin_intento: ' . $e->getMessage());
    jsonResponse(['ok' => true, 'omitido' => true]);
}

registrarIntentoCheckin($db, $citaId, (int)$u['id'], $tipo, $etapa, $motivo, $lat, $lng, $accuracy, $distancia);
jsonResponse(['ok' => true]);
