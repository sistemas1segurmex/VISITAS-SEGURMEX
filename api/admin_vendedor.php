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

$chk = $db->prepare("SELECT id, nombre, email, telefono, estado_operacion, activo, foto_path FROM usuarios WHERE id = ? AND rol = 'vendedor'");
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

    // Última vez que el celular del vendedor reportó GPS, sea hoy o hace
    // días -- para poder decir "se le vio por última vez..." aunque ya no
    // esté activo en este momento (ver dot de estado en el mapa en vivo).
    $stmt = $db->prepare('SELECT MAX(fecha_hora) FROM tracking_ubicaciones WHERE vendedor_id = ?');
    $stmt->execute([$vendedorId]);
    $ultimaConexion = $stmt->fetchColumn() ?: null;

    jsonResponse([
        'ok' => true,
        'vendedor' => $vendedor,
        'total_clientes' => $totalClientes,
        'total_citas' => $totalCitas,
        'proximas_citas' => $proximas,
        'checkins_verificados' => $verificadas,
        'ultima_conexion' => $ultimaConexion,
    ]);
}

if ($accion === 'citas_proximas') {
    $stmt = $db->prepare(
        "SELECT c.id, c.fecha_hora, c.estado, c.notas, c.interes, cl.nombre AS cliente_nombre, cl.direccion,
                (SELECT verificado FROM checkins ch WHERE ch.cita_id = c.id AND ch.tipo='entrada' ORDER BY ch.id DESC LIMIT 1) AS checkin_verificado,
                (SELECT id FROM checkins ch WHERE ch.cita_id = c.id AND ch.tipo='entrada' AND ch.foto_path IS NOT NULL ORDER BY ch.id DESC LIMIT 1) AS foto_entrada_id,
                (SELECT id FROM checkins ch WHERE ch.cita_id = c.id AND ch.tipo='salida' AND ch.foto_path IS NOT NULL ORDER BY ch.id DESC LIMIT 1) AS foto_salida_id
         FROM citas c JOIN clientes cl ON cl.id = c.cliente_id
         WHERE c.vendedor_id = ? AND c.fecha_hora >= NOW()
         ORDER BY c.fecha_hora ASC LIMIT 100"
    );
    $stmt->execute([$vendedorId]);
    jsonResponse(['ok' => true, 'citas' => $stmt->fetchAll()]);
}

if ($accion === 'citas_todas') {
    $stmt = $db->prepare(
        "SELECT c.id, c.fecha_hora, c.estado, c.notas, c.interes, cl.nombre AS cliente_nombre, cl.direccion,
                (SELECT verificado FROM checkins ch WHERE ch.cita_id = c.id AND ch.tipo='entrada' ORDER BY ch.id DESC LIMIT 1) AS checkin_verificado,
                (SELECT id FROM checkins ch WHERE ch.cita_id = c.id AND ch.tipo='entrada' AND ch.foto_path IS NOT NULL ORDER BY ch.id DESC LIMIT 1) AS foto_entrada_id,
                (SELECT id FROM checkins ch WHERE ch.cita_id = c.id AND ch.tipo='salida' AND ch.foto_path IS NOT NULL ORDER BY ch.id DESC LIMIT 1) AS foto_salida_id
         FROM citas c JOIN clientes cl ON cl.id = c.cliente_id
         WHERE c.vendedor_id = ?
         ORDER BY c.fecha_hora DESC LIMIT 200"
    );
    $stmt->execute([$vendedorId]);
    jsonResponse(['ok' => true, 'citas' => $stmt->fetchAll()]);
}

if ($accion === 'clientes') {
    // El interés se califica por cita, no en el cliente (ver
    // api/checkin.php) -- aquí se trae el de la visita completada más
    // reciente que sí tenga uno capturado.
    $stmt = $db->prepare(
        "SELECT c.*,
                (SELECT ci.interes FROM citas ci WHERE ci.cliente_id = c.id AND ci.interes IS NOT NULL ORDER BY ci.fecha_hora DESC LIMIT 1) AS ultimo_interes
         FROM clientes c WHERE c.vendedor_id = ? ORDER BY c.nombre"
    );
    $stmt->execute([$vendedorId]);
    jsonResponse(['ok' => true, 'clientes' => $stmt->fetchAll()]);
}

if ($accion === 'prospeccion') {
    $vista = $_GET['vista'] ?? 'mes';

    if ($vista === 'semana') {
        $semana = $_GET['semana'] ?? '';
        if (!preg_match('/^(\d{4})-W(\d{2})$/', $semana, $m)) {
            jsonResponse(['ok' => false, 'error' => 'Semana inválida'], 400);
        }
        jsonResponse(['ok' => true, 'vista' => 'semana'] + resumenProspeccionSemana($db, $vendedorId, (int)$m[1], (int)$m[2], $vendedor['email']));
    }

    $mes = $_GET['mes'] ?? (new DateTime('now', new DateTimeZone('America/Mexico_City')))->format('Y-m');
    if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
        jsonResponse(['ok' => false, 'error' => 'Mes inválido'], 400);
    }
    jsonResponse(['ok' => true, 'vista' => 'mes'] + resumenProspeccionMes($db, $vendedorId, $mes, $vendedor['email']));
}

if ($accion === 'prospeccion_dia') {
    $fecha = $_GET['fecha'] ?? (new DateTime('now', new DateTimeZone('America/Mexico_City')))->format('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        jsonResponse(['ok' => false, 'error' => 'Fecha inválida'], 400);
    }
    jsonResponse(['ok' => true] + resumenDiaVendedor($db, $vendedorId, $fecha));
}

jsonResponse(['ok' => false, 'error' => 'Acción no reconocida'], 400);
