<?php
// Revisa citas con entrada registrada pero sin salida desde hace rato, y le
// manda un push al vendedor para que no se le olvide cerrarla. Pensado para
// correr por cron cada 15-30 min (mismo servidor y misma base de datos que
// la app -- no hay nada remoto que configurar):
//
//   */15 * * * * php /var/www/visitas/api/cron_recordatorios.php >> /var/log/visitas_recordatorios.log 2>&1
//
// Solo por línea de comandos -- nunca se expone como endpoint web.
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Solo se ejecuta por línea de comandos.');
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/fcm.php';

// Cuánto tiempo sin salida (desde la entrada) antes de avisar.
define('HORAS_ANTES_DE_AVISAR', 2);

if (!fcmCredencialesDisponibles()) {
    fwrite(STDERR, "[VISITAS] cron_recordatorios: falta includes/firebase-service-account.json -- nada que hacer todavía.\n");
    exit(0);
}

$db = getDB();

$stmt = $db->query(
    "SELECT c.id, c.vendedor_id, cl.nombre AS cliente_nombre,
            (SELECT MAX(ch.fecha_hora) FROM checkins ch WHERE ch.cita_id = c.id AND ch.tipo = 'entrada') AS entrada_en
     FROM citas c
     JOIN clientes cl ON cl.id = c.cliente_id
     WHERE c.recordatorio_pendiente_enviado_en IS NULL
       AND c.estado NOT IN ('cancelada', 'no_realizada')
       AND EXISTS (SELECT 1 FROM checkins ch WHERE ch.cita_id = c.id AND ch.tipo = 'entrada')
       AND NOT EXISTS (SELECT 1 FROM checkins ch WHERE ch.cita_id = c.id AND ch.tipo = 'salida')"
);
$pendientes = $stmt->fetchAll();

$avisadas = 0;
foreach ($pendientes as $c) {
    if (!$c['entrada_en']) continue;
    $horasDesdeEntrada = (time() - strtotime($c['entrada_en'] . ' UTC')) / 3600;
    if ($horasDesdeEntrada < HORAS_ANTES_DE_AVISAR) continue;

    enviarPushFCM(
        $db,
        (int)$c['vendedor_id'],
        'Tienes una visita sin cerrar',
        'Registraste tu llegada con ' . $c['cliente_nombre'] . ' hace rato y no has registrado la salida.',
        ['tipo' => 'checkout_pendiente', 'cita_id' => (string)$c['id']]
    );
    $db->prepare('UPDATE citas SET recordatorio_pendiente_enviado_en = NOW() WHERE id = ?')->execute([$c['id']]);
    $avisadas++;
}

echo "[VISITAS] cron_recordatorios: {$avisadas} aviso(s) de check-out pendiente enviados.\n";
