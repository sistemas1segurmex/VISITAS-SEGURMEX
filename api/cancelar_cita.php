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

$stmt = $db->prepare('SELECT id, estado FROM citas WHERE id = ? AND vendedor_id = ?');
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

jsonResponse(['ok' => true]);
