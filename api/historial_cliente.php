<?php
// Historial completo de un cliente: sus datos + todas sus citas (pasadas y
// futuras) con la evidencia de check-in (entrada/salida) de cada una.
//
// GET ?cliente_id=123

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$u  = requireRole('vendedor');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

$clienteId = (int)($_GET['cliente_id'] ?? 0);
if ($clienteId <= 0) {
    jsonResponse(['ok' => false, 'error' => 'Falta el cliente'], 400);
}

$stmtCliente = $db->prepare('SELECT * FROM clientes WHERE id = ? AND vendedor_id = ?');
$stmtCliente->execute([$clienteId, $u['id']]);
$cliente = $stmtCliente->fetch();

if (!$cliente) {
    jsonResponse(['ok' => false, 'error' => 'Cliente no encontrado'], 404);
}

$stmtCitas = $db->prepare(
    'SELECT id, fecha_hora, estado, notas, motivo
     FROM citas
     WHERE cliente_id = ? AND vendedor_id = ?
     ORDER BY fecha_hora DESC'
);
$stmtCitas->execute([$clienteId, $u['id']]);
$citas = $stmtCitas->fetchAll();

if (!empty($citas)) {
    $idsCitas = array_column($citas, 'id');
    $marcadores = implode(',', array_fill(0, count($idsCitas), '?'));
    $stmtCheckins = $db->prepare(
        "SELECT cita_id, tipo, lat, lng, distancia_metros, foto_path, verificado, fecha_hora
         FROM checkins WHERE cita_id IN ($marcadores) ORDER BY fecha_hora ASC"
    );
    $stmtCheckins->execute($idsCitas);
    $checkinsPorCita = [];
    foreach ($stmtCheckins->fetchAll() as $chk) {
        $checkinsPorCita[$chk['cita_id']][] = $chk;
    }
    foreach ($citas as &$cita) {
        $cita['checkins'] = $checkinsPorCita[$cita['id']] ?? [];
    }
    unset($cita);
}

// Resumen rápido para mostrar arriba del historial.
$resumen = ['total' => count($citas), 'completada' => 0, 'no_realizada' => 0, 'cancelada' => 0, 'pendiente' => 0, 'en_curso' => 0];
foreach ($citas as $c) {
    if (isset($resumen[$c['estado']])) $resumen[$c['estado']]++;
}

jsonResponse(['ok' => true, 'cliente' => $cliente, 'citas' => $citas, 'resumen' => $resumen]);
