<?php
// Devuelve las citas en el formato de "eventos" que espera FullCalendar,
// filtrando por el rango de fechas visible en el calendario (start/end,
// que FullCalendar manda automáticamente al cambiar de mes/semana).

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$u  = requireLogin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

$inicio = $_GET['start'] ?? date('Y-m-01');
$fin    = $_GET['end'] ?? date('Y-m-t');
// FullCalendar puede mandar fechas con hora (ISO); nos quedamos solo con la parte de fecha.
$inicio = substr($inicio, 0, 10);
$fin    = substr($fin, 0, 10);

$sql = "SELECT c.id, c.fecha_hora, c.estado, cl.nombre AS cliente_nombre, cl.direccion,
               (SELECT verificado FROM checkins ch WHERE ch.cita_id = c.id AND ch.tipo='entrada' ORDER BY ch.id DESC LIMIT 1) AS checkin_verificado
        FROM citas c
        JOIN clientes cl ON cl.id = c.cliente_id
        WHERE c.fecha_hora >= ? AND c.fecha_hora < ?::date + INTERVAL '1 day'";

$params = [$inicio, $fin];
if ($u['rol'] !== 'admin') {
    $sql .= ' AND c.vendedor_id = ?';
    $params[] = $u['id'];
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$citas = $stmt->fetchAll();

$colores = [
    'pendiente'     => '#F5A623',
    'en_curso'      => '#2563eb',
    'completada'    => '#16a34a',
    'no_realizada'  => '#dc2626',
];

$eventos = array_map(function ($c) use ($colores) {
    return [
        'id'    => $c['id'],
        'title' => $c['cliente_nombre'],
        'start' => str_replace(' ', 'T', $c['fecha_hora']),
        'color' => $colores[$c['estado']] ?? '#6b7280',
        'extendedProps' => [
            'direccion'  => $c['direccion'],
            'estado'     => $c['estado'],
            'verificado' => $c['checkin_verificado'],
        ],
    ];
}, $citas);

jsonResponse(['ok' => true, 'eventos' => $eventos]);
