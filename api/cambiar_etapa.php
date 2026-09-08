<?php
// Actualiza la etapa del embudo de ventas de un cliente/prospecto propio.
// (El nivel de interés ya no vive aquí -- se captura por cita, al
// registrar la salida, en api/checkin.php.)
//
// POST cliente_id, etapa, motivo_perdido? (opcional, solo tiene sentido si etapa=perdido)

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$u  = requireRole('vendedor');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

// Misma lista que ETAPAS_CLIENTE + ETAPA_PERDIDO en assets/js/vendedor.js.
$ETAPAS_VALIDAS = [
    'prospecto_agregado', 'contacto_establecido', 'reunion_presentacion',
    'propuesta_enviada', 'convertido', 'perdido',
];

$clienteId = (int)($_POST['cliente_id'] ?? 0);
$etapa     = $_POST['etapa'] ?? '';

if (!$clienteId) {
    jsonResponse(['ok' => false, 'error' => 'Cliente no válido'], 400);
}
if (!in_array($etapa, $ETAPAS_VALIDAS, true)) {
    jsonResponse(['ok' => false, 'error' => 'Etapa no válida'], 400);
}

$stmt = $db->prepare('SELECT id FROM clientes WHERE id = ? AND vendedor_id = ?');
$stmt->execute([$clienteId, $u['id']]);
if (!$stmt->fetch()) {
    jsonResponse(['ok' => false, 'error' => 'Cliente no encontrado'], 404);
}

// El motivo de "perdido" solo tiene sentido si de verdad se está marcando
// (o ya estaba marcado) como perdido; si se mueve a otra etapa se limpia
// para no dejar un motivo viejo colgado.
$motivoPerdido = $etapa === 'perdido' ? (trim($_POST['motivo_perdido'] ?? '') ?: null) : null;

$db->prepare(
    'UPDATE clientes SET etapa = ?, etapa_actualizada_en = CURRENT_TIMESTAMP, etapa_perdido_motivo = ?
     WHERE id = ? AND vendedor_id = ?'
)->execute([$etapa, $motivoPerdido, $clienteId, $u['id']]);

jsonResponse(['ok' => true]);
