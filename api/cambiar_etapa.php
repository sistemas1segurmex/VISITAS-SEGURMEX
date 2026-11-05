<?php
// Actualiza la etapa del embudo de ventas de un cliente/prospecto propio.
// (El nivel de interés ya no vive aquí -- se captura por cita, al
// registrar la salida, en api/checkin.php.)
//
// POST cliente_id, etapa, motivo_perdido? (opcional, solo tiene sentido si etapa=perdido)

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/coincidencias.php';

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

$stmt = $db->prepare('SELECT nombre, etapa, id_cliente_erp FROM clientes WHERE id = ? AND vendedor_id = ?');
$stmt->execute([$clienteId, $u['id']]);
$cliente = $stmt->fetch();
if (!$cliente) {
    jsonResponse(['ok' => false, 'error' => 'Cliente no encontrado'], 404);
}
exigirClienteTrabajable($db, $clienteId); // en revisión / descartado

// «Convertido» ya no se marca a mano (05-nov-2026): se pone solo cuando CxC
// aprueba su registro en el ERP (erp/includes/prospectos.php,
// trasladarProspectoACliente). Así el embudo y las métricas son reales.
if ($etapa === 'convertido' && $cliente['etapa'] !== 'convertido') {
    jsonResponse(['ok' => false, 'error' => '«Convertido» se marca solo cuando CxC aprueba su registro en el ERP. Para hacerlo cliente entra al ERP › Prospectos › Hacer cliente.'], 400);
}
if ($cliente['etapa'] === 'convertido' && !empty($cliente['id_cliente_erp']) && $etapa !== 'convertido') {
    jsonResponse(['ok' => false, 'error' => 'Ya es cliente en el ERP, así que se queda como «Convertido». Si hay un error, avisa a Sistemas.'], 400);
}

// El motivo de "perdido" solo tiene sentido si de verdad se está marcando
// (o ya estaba marcado) como perdido; si se mueve a otra etapa se limpia
// para no dejar un motivo viejo colgado.
$motivoPerdido = $etapa === 'perdido' ? (trim($_POST['motivo_perdido'] ?? '') ?: null) : null;

$db->prepare(
    'UPDATE clientes SET etapa = ?, etapa_actualizada_en = CURRENT_TIMESTAMP, etapa_perdido_motivo = ?
     WHERE id = ? AND vendedor_id = ?'
)->execute([$etapa, $motivoPerdido, $clienteId, $u['id']]);

// Bitácora del admin: "baja" cuando se marca perdido, "edición" para
// cualquier otro movimiento del embudo (incluyendo llegar a "convertido" --
// sigue siendo el mismo registro, no uno nuevo).
$accionBitacora = $etapa === 'perdido' ? 'baja' : 'edicion';
$resumenBitacora = $etapa === 'perdido'
    ? "Marcó como perdido al cliente {$cliente['nombre']}"
    : "Cambió de etapa al cliente {$cliente['nombre']}";
$diff = ['Etapa' => [etiquetaEtapa($cliente['etapa']), etiquetaEtapa($etapa)]];
if ($motivoPerdido) $diff['Motivo'] = [null, $motivoPerdido];
registrarCambio($db, $u['id'], 'cliente', $clienteId, $accionBitacora, $resumenBitacora, $diff);

jsonResponse(['ok' => true]);
