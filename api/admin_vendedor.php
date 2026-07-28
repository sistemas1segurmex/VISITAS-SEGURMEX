<?php
// Detalle de un vendedor específico para el panel del dueño: resumen,
// próximas citas, historial completo de citas y clientes registrados.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$admin = requireRole('admin');
$db    = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

$vendedorId = (int)($_GET['vendedor_id'] ?? 0);
$accion     = $_GET['accion'] ?? 'resumen';

if (!$vendedorId) {
    jsonResponse(['ok' => false, 'error' => 'Falta vendedor_id'], 400);
}

$chk = $db->prepare("SELECT id, nombre, email, telefono, estado_operacion, activo FROM usuarios WHERE id = ? AND rol = 'vendedor'");
$chk->execute([$vendedorId]);
$vendedor = $chk->fetch();
if (!$vendedor) {
    jsonResponse(['ok' => false, 'error' => 'Vendedor no encontrado'], 404);
}

if ($accion === 'resumen') {
    $stmt = $db->prepare('SELECT COUNT(*) FROM clientes WHERE vendedor_id = ?');
    $stmt->execute([$vendedorId]);
    $totalClientes = (int)$stmt->fetchColumn();

    $stmt = $db->prepare('SELECT COUNT(*) FROM citas WHERE vendedor_id = ? AND fecha_hora >= NOW()');
    $stmt->execute([$vendedorId]);
    $proximas = (int)$stmt->fetchColumn();

    $stmt = $db->prepare('SELECT COUNT(*) FROM citas WHERE vendedor_id = ?');
    $stmt->execute([$vendedorId]);
    $totalCitas = (int)$stmt->fetchColumn();

    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM checkins ch JOIN citas c ON c.id = ch.cita_id
         WHERE c.vendedor_id = ? AND ch.tipo = 'entrada' AND ch.verificado = 1"
    );
    $stmt->execute([$vendedorId]);
    $verificadas = (int)$stmt->fetchColumn();

    jsonResponse([
        'ok' => true,
        'vendedor' => $vendedor,
        'total_clientes' => $totalClientes,
        'total_citas' => $totalCitas,
        'proximas_citas' => $proximas,
        'checkins_verificados' => $verificadas,
    ]);
}

if ($accion === 'citas_proximas') {
    $stmt = $db->prepare(
        "SELECT c.id, c.fecha_hora, c.estado, c.notas, cl.nombre AS cliente_nombre, cl.direccion,
                (SELECT verificado FROM checkins ch WHERE ch.cita_id = c.id AND ch.tipo='entrada' ORDER BY ch.id DESC LIMIT 1) AS checkin_verificado
         FROM citas c JOIN clientes cl ON cl.id = c.cliente_id
         WHERE c.vendedor_id = ? AND c.fecha_hora >= NOW()
         ORDER BY c.fecha_hora ASC LIMIT 100"
    );
    $stmt->execute([$vendedorId]);
    jsonResponse(['ok' => true, 'citas' => $stmt->fetchAll()]);
}

if ($accion === 'citas_todas') {
    $stmt = $db->prepare(
        "SELECT c.id, c.fecha_hora, c.estado, c.notas, cl.nombre AS cliente_nombre, cl.direccion,
                (SELECT verificado FROM checkins ch WHERE ch.cita_id = c.id AND ch.tipo='entrada' ORDER BY ch.id DESC LIMIT 1) AS checkin_verificado
         FROM citas c JOIN clientes cl ON cl.id = c.cliente_id
         WHERE c.vendedor_id = ?
         ORDER BY c.fecha_hora DESC LIMIT 200"
    );
    $stmt->execute([$vendedorId]);
    jsonResponse(['ok' => true, 'citas' => $stmt->fetchAll()]);
}

if ($accion === 'clientes') {
    $stmt = $db->prepare('SELECT * FROM clientes WHERE vendedor_id = ? ORDER BY nombre');
    $stmt->execute([$vendedorId]);
    jsonResponse(['ok' => true, 'clientes' => $stmt->fetchAll()]);
}

jsonResponse(['ok' => false, 'error' => 'Acción no reconocida'], 400);
