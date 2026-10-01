<?php
// Historial de una cita para el admin: sus datos actuales y todo lo que
// quedó en la bitácora sobre ella (alta, reprogramaciones con motivo,
// cancelación, no realizada automática...), del más viejo al más nuevo.
// Lo usa assets/js/admin_historial_cita.js (panel, detalle del vendedor,
// bitácora y alertas).

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

$citaId = (int)($_GET['cita_id'] ?? 0);
if (!$citaId) {
    jsonResponse(['ok' => false, 'error' => 'Falta cita_id'], 400);
}

$stmt = $db->prepare(
    'SELECT c.id, c.fecha_hora, c.estado, c.motivo, c.notas, cl.nombre AS cliente_nombre, cl.direccion,
            u.nombre AS vendedor_nombre,
            ' . sqlReprogramaciones() . ',
            ' . sqlCancelacion() . '
     FROM citas c
     JOIN clientes cl ON cl.id = c.cliente_id
     JOIN usuarios u ON u.id = c.vendedor_id
     WHERE c.id = ?'
);
$stmt->execute([$citaId]);
$cita = $stmt->fetch();
if (!$cita) {
    jsonResponse(['ok' => false, 'error' => 'Cita no encontrada'], 404);
}

$stmt = $db->prepare(
    "SELECT bc.id, bc.entidad_id, bc.accion, bc.resumen, bc.cambios, bc.creado_en
     FROM bitacora_cambios bc
     WHERE bc.entidad = 'cita' AND bc.entidad_id = ?
     ORDER BY bc.creado_en ASC, bc.id ASC"
);
$stmt->execute([$citaId]);
$eventos = $stmt->fetchAll();
foreach ($eventos as &$e) {
    $e['cambios'] = $e['cambios'] ? json_decode($e['cambios'], true) : null;
}
unset($e);

jsonResponse([
    'ok' => true,
    'cita' => $cita,
    'eventos' => $eventos,
    'llave_motivo' => LLAVE_MOTIVO_REPROGRAMACION,
    'max_reprogramaciones' => MAX_REPROGRAMACIONES,
]);
