<?php
// Información agregada de un estado de México para el mapa del panel admin:
// vendedores asignados a ese estado (con si están en línea), clientes
// registrados ahí (según su CP), y citas totales/próximas/verificadas de
// las visitas a esos clientes.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireRole('admin');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

$estado = trim($_GET['estado'] ?? '');
if ($estado === '') {
    jsonResponse(['ok' => false, 'error' => 'Falta el estado'], 400);
}

$stmtV = $db->prepare(
    "SELECT u.id, u.nombre, u.telefono,
            CASE WHEN t.fecha_hora IS NOT NULL AND t.fecha_hora >= NOW() - INTERVAL '15 minutes'
                 THEN 1 ELSE 0 END AS en_linea
     FROM usuarios u
     LEFT JOIN LATERAL (
         SELECT fecha_hora FROM tracking_ubicaciones
         WHERE vendedor_id = u.id ORDER BY fecha_hora DESC LIMIT 1
     ) t ON true
     WHERE u.rol = 'vendedor' AND u.activo = 1 AND u.estado_operacion = ?
     ORDER BY u.nombre"
);
$stmtV->execute([$estado]);
$vendedores = $stmtV->fetchAll();

$stmtC = $db->prepare('SELECT COUNT(*) FROM clientes WHERE estado = ?');
$stmtC->execute([$estado]);
$totalClientes = (int)$stmtC->fetchColumn();

$stmtCitas = $db->prepare(
    "SELECT COUNT(*) AS total,
            COUNT(*) FILTER (WHERE c.fecha_hora >= NOW()) AS proximas,
            COUNT(*) FILTER (WHERE EXISTS (
                SELECT 1 FROM checkins ch WHERE ch.cita_id = c.id AND ch.tipo = 'entrada' AND ch.verificado = 1
            )) AS verificadas
     FROM citas c JOIN clientes cl ON cl.id = c.cliente_id
     WHERE cl.estado = ?"
);
$stmtCitas->execute([$estado]);
$citas = $stmtCitas->fetch();

jsonResponse([
    'ok' => true,
    'estado' => $estado,
    'vendedores' => $vendedores,
    'total_clientes' => $totalClientes,
    'citas' => [
        'total' => (int)$citas['total'],
        'proximas' => (int)$citas['proximas'],
        'verificadas' => (int)$citas['verificadas'],
    ],
]);
