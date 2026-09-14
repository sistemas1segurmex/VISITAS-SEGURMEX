<?php
// Cancela una cita propia, solo si todavía sigue "pendiente" (nadie ha
// hecho check-in). Una vez que la visita está en curso o resuelta, ya no
// se puede cancelar desde aquí.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$u  = requireRole('vendedor');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

$citaId = (int)($_POST['cita_id'] ?? 0);
$motivo = trim($_POST['motivo'] ?? '');

if (!$citaId) {
    jsonResponse(['ok' => false, 'error' => 'Cita no válida'], 400);
}

$stmt = $db->prepare(
    'SELECT c.id, c.estado, cl.nombre AS cliente_nombre FROM citas c
     JOIN clientes cl ON cl.id = c.cliente_id
     WHERE c.id = ? AND c.vendedor_id = ?'
);
$stmt->execute([$citaId, $u['id']]);
$cita = $stmt->fetch();

if (!$cita) {
    jsonResponse(['ok' => false, 'error' => 'Cita no encontrada'], 404);
}

if ($cita['estado'] !== 'pendiente') {
    jsonResponse(['ok' => false, 'error' => 'Esta cita ya no se puede cancelar (ya tiene actividad registrada).'], 400);
}

$db->prepare('UPDATE citas SET estado = ?, motivo = ? WHERE id = ?')
   ->execute(['cancelada', $motivo !== '' ? $motivo : null, $citaId]);

registrarCambio($db, $u['id'], 'cita', $citaId, 'baja', "Canceló la cita con {$cita['cliente_nombre']}", [
    'Estado' => ['Pendiente', 'Cancelada'],
    'Motivo' => [null, $motivo !== '' ? $motivo : null],
]);

jsonResponse(['ok' => true]);
