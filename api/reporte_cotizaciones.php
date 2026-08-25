<?php
// Resumen mensual de cotizaciones del vendedor foráneo -- mismo rango de mes
// que api/reporte_mensual.php (citas), pero leyendo de "public.cotizaciones"
// en el ERP (ver docs/arquitectura-cotizaciones-foraneos.md, repo del ERP).
//
// GET ?mes=YYYY-MM  (opcional, por defecto el mes actual)

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/cotizador_helpers.php';

$u  = requireRole('vendedor');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

$stmtU = $db->prepare('SELECT email FROM usuarios WHERE id = ?');
$stmtU->execute([$u['id']]);
$email = $stmtU->fetchColumn();
if (!$email) {
    jsonResponse(['ok' => false, 'error' => 'No se encontró tu correo registrado.'], 500);
}

$mes = trim($_GET['mes'] ?? '');
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $mes = date('Y-m');
}
$inicio = $mes . '-01 00:00:00';
$finTs  = strtotime($inicio . ' +1 month');
$fin    = date('Y-m-d 00:00:00', $finTs);

$dbErp = getDBErp();
$idVendedorErp = obtenerOCrearVendedorErp($email, $u['nombre']);

$stmt = $dbErp->prepare(
    'SELECT estado, COUNT(*) AS n, COALESCE(SUM(total), 0) AS monto
     FROM cotizaciones
     WHERE id_vendedor = ? AND created_at >= ? AND created_at < ?
     GROUP BY estado'
);
$stmt->execute([$idVendedorErp, $inicio, $fin]);

$estadosClave = ['pendiente', 'enviada', 'en_negociacion', 'aceptada', 'rechazada', 'facturada', 'entregada', 'cancelada'];
$conteo = array_fill_keys($estadosClave, 0);
$monto  = array_fill_keys($estadosClave, 0.0);
foreach ($stmt->fetchAll() as $fila) {
    if (isset($conteo[$fila['estado']])) {
        $conteo[$fila['estado']] = (int)$fila['n'];
        $monto[$fila['estado']]  = (float)$fila['monto'];
    }
}

$estadosAceptados = ['aceptada', 'facturada', 'entregada'];
$totalCotizaciones = array_sum($conteo);
$totalSinCancelar  = $totalCotizaciones - $conteo['cancelada'];
$totalCotizado     = array_sum($monto) - $monto['cancelada'];
$totalAceptado     = array_sum(array_intersect_key($monto, array_flip($estadosAceptados)));
$conteoAceptado    = array_sum(array_intersect_key($conteo, array_flip($estadosAceptados)));

// Además de sus propias cotizaciones, cuántas nacieron directo de una visita
// (visitas_cita_id) vs. sueltas -- solo informativo, no afecta los montos.
$stmtOrigen = $dbErp->prepare(
    "SELECT COUNT(*) FILTER (WHERE visitas_cita_id IS NOT NULL) AS de_visita, COUNT(*) AS total
     FROM cotizaciones WHERE id_vendedor = ? AND created_at >= ? AND created_at < ?"
);
$stmtOrigen->execute([$idVendedorErp, $inicio, $fin]);
$origen = $stmtOrigen->fetch();

jsonResponse([
    'ok'  => true,
    'mes' => $mes,
    'resumen' => [
        'total'                 => $totalCotizaciones,
        'por_estado'            => $conteo,
        'monto_por_estado'      => $monto,
        'total_cotizado'        => round($totalCotizado, 2),
        'total_aceptado'        => round($totalAceptado, 2),
        'porcentaje_conversion' => $totalSinCancelar > 0 ? round(($conteoAceptado / $totalSinCancelar) * 100) : 0,
        'promedio_por_cotizacion' => $totalCotizaciones > 0 ? round((array_sum($monto)) / $totalCotizaciones, 2) : 0,
        'de_visita'             => (int)($origen['de_visita'] ?? 0),
    ],
]);
