<?php
/**
 * Detalle de una cotización del vendedor foráneo -- ver
 * docs/arquitectura-cotizaciones-foraneos.md (repo del ERP). Lee/escribe
 * directo en el esquema "public" del ERP igual que api/cotizaciones.php.
 *
 * GET  ?id=X                          -> ficha completa (cotización, renglones,
 *                                        historial, link público si existe).
 * POST accion=cambiar_estado, id, nuevo_estado -> mueve el estado y regresa la
 *                                        ficha actualizada.
 *
 * Propiedad: solo puede ver/mover una cotización cuyo id_vendedor sea el
 * vendedor espejo del foráneo logueado (mismo correo).
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/cotizador_helpers.php';

$u  = requireRole('vendedor');
$db = getDB();

$stmtU = $db->prepare('SELECT email FROM usuarios WHERE id = ?');
$stmtU->execute([$u['id']]);
$email = $stmtU->fetchColumn();
if (!$email) {
    jsonResponse(['ok' => false, 'error' => 'No se encontró tu correo registrado.'], 500);
}

$dbErp = getDBErp();
$idVendedorErp = obtenerOCrearVendedorErp($email, $u['nombre']);

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$id) {
    jsonResponse(['ok' => false, 'error' => 'Cotización no encontrada.'], 404);
}

function cargarCotizacion(PDO $dbErp, int $id, int $idVendedorErp): ?array {
    $stmt = $dbErp->prepare('SELECT * FROM cotizaciones WHERE id = ? AND id_vendedor = ?');
    $stmt->execute([$id, $idVendedorErp]);
    return $stmt->fetch() ?: null;
}

$cot = cargarCotizacion($dbErp, $id, $idVendedorErp);
if (!$cot) {
    jsonResponse(['ok' => false, 'error' => 'Cotización no encontrada.'], 404);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cambiar_estado') {
    $nuevoEstado = trim($_POST['nuevo_estado'] ?? '');
    $res = cambiarEstadoCotizacionErp($dbErp, $cot, $nuevoEstado, $idVendedorErp);
    if (!$res['ok']) {
        jsonResponse(['ok' => false, 'error' => $res['msg']], 400);
    }
    $cot = cargarCotizacion($dbErp, $id, $idVendedorErp);
}

$detalle = $dbErp->prepare('SELECT * FROM cotizacion_detalle WHERE id_cotizacion = ? ORDER BY orden');
$detalle->execute([$id]);
$detalle = $detalle->fetchAll();

$historial = $dbErp->prepare("
    SELECT h.estado_anterior, h.estado_nuevo, h.origen, h.created_at, u.nombre, u.apellidos
    FROM cotizacion_historial h
    LEFT JOIN usuarios u ON u.id = h.id_usuario
    WHERE h.id_cotizacion = ?
    ORDER BY h.created_at DESC, h.id DESC
");
$historial->execute([$id]);
$historial = $historial->fetchAll();

$urlPublica = null;
if (!empty($cot['token_publico'])) {
    $urlPublica = urlPublicaCotizacionErp($cot['token_publico']);
}

jsonResponse([
    'ok'           => true,
    'cotizacion'   => $cot,
    'detalle'      => $detalle,
    'historial'    => $historial,
    'vencida'      => cotizacionVencidaErp($cot),
    'transiciones' => transicionesPermitidasErp($cot['estado']),
    'url_publica'  => $urlPublica,
]);
