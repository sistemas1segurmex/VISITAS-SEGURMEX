<?php
// Resumen mensual de desempeño del vendedor: citas por estado, % de
// cumplimiento y % de check-ins verificados dentro del rango del mes.
//
// GET ?mes=YYYY-MM  (opcional, por defecto el mes actual)

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$u  = requireRole('vendedor');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

$mes = trim($_GET['mes'] ?? '');
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $mes = date('Y-m');
}

$inicio = $mes . '-01 00:00:00';
$finTs  = strtotime($inicio . ' +1 month');
$fin    = date('Y-m-d 00:00:00', $finTs);

$stmt = $db->prepare(
    'SELECT estado, COUNT(*) AS n FROM citas
     WHERE vendedor_id = ? AND fecha_hora >= ? AND fecha_hora < ?
     GROUP BY estado'
);
$stmt->execute([$u['id'], $inicio, $fin]);

$conteo = ['pendiente' => 0, 'en_curso' => 0, 'completada' => 0, 'no_realizada' => 0, 'cancelada' => 0];
foreach ($stmt->fetchAll() as $fila) {
    if (isset($conteo[$fila['estado']])) $conteo[$fila['estado']] = (int)$fila['n'];
}
$totalCitas = array_sum($conteo);

$stmtChk = $db->prepare(
    "SELECT
        COUNT(*) FILTER (WHERE chk.verificado = 1) AS verificados,
        COUNT(*) AS total
     FROM checkins chk
     JOIN citas c ON c.id = chk.cita_id
     WHERE c.vendedor_id = ? AND chk.tipo = 'entrada'
       AND c.fecha_hora >= ? AND c.fecha_hora < ?"
);
$stmtChk->execute([$u['id'], $inicio, $fin]);
$chk = $stmtChk->fetch();
$totalEntradas = (int)($chk['total'] ?? 0);
$verificados   = (int)($chk['verificados'] ?? 0);

$stmtClientes = $db->prepare(
    "SELECT COUNT(DISTINCT c.cliente_id) AS n
     FROM citas c
     JOIN checkins chk ON chk.cita_id = c.id AND chk.tipo = 'entrada'
     WHERE c.vendedor_id = ? AND c.fecha_hora >= ? AND c.fecha_hora < ?"
);
$stmtClientes->execute([$u['id'], $inicio, $fin]);
$clientesVisitados = (int)($stmtClientes->fetch()['n'] ?? 0);

// --- Desglose diario del mes, para la gráfica de tendencia ---
$totalDias = (int)date('t', strtotime($inicio));
$dias = [];
for ($d = 1; $d <= $totalDias; $d++) {
    $dias[] = $mes . '-' . str_pad((string)$d, 2, '0', STR_PAD_LEFT);
}

$estadosClave = ['pendiente', 'en_curso', 'completada', 'no_realizada', 'cancelada'];
$serieCitas = [];
foreach ($estadosClave as $e) $serieCitas[$e] = array_fill(0, $totalDias, 0);

$stmtDiario = $db->prepare(
    'SELECT DATE(fecha_hora) AS dia, estado, COUNT(*) AS n
     FROM citas WHERE vendedor_id = ? AND fecha_hora >= ? AND fecha_hora < ?
     GROUP BY DATE(fecha_hora), estado'
);
$stmtDiario->execute([$u['id'], $inicio, $fin]);
foreach ($stmtDiario->fetchAll() as $fila) {
    $idx = (int)substr($fila['dia'], 8, 2) - 1;
    if ($idx >= 0 && $idx < $totalDias && isset($serieCitas[$fila['estado']])) {
        $serieCitas[$fila['estado']][$idx] = (int)$fila['n'];
    }
}

$serieVerificados = array_fill(0, $totalDias, 0);
$serieNoVerificados = array_fill(0, $totalDias, 0);
$stmtDiarioChk = $db->prepare(
    "SELECT DATE(chk.fecha_hora) AS dia, chk.verificado, COUNT(*) AS n
     FROM checkins chk
     JOIN citas c ON c.id = chk.cita_id
     WHERE c.vendedor_id = ? AND chk.tipo = 'entrada'
       AND chk.fecha_hora >= ? AND chk.fecha_hora < ?
     GROUP BY DATE(chk.fecha_hora), chk.verificado"
);
$stmtDiarioChk->execute([$u['id'], $inicio, $fin]);
foreach ($stmtDiarioChk->fetchAll() as $fila) {
    $idx = (int)substr($fila['dia'], 8, 2) - 1;
    if ($idx < 0 || $idx >= $totalDias) continue;
    if ((int)$fila['verificado'] === 1) $serieVerificados[$idx] = (int)$fila['n'];
    else $serieNoVerificados[$idx] = (int)$fila['n'];
}

jsonResponse([
    'ok' => true,
    'mes' => $mes,
    'resumen' => [
        'total'               => $totalCitas,
        'pendiente'           => $conteo['pendiente'],
        'en_curso'            => $conteo['en_curso'],
        'completada'          => $conteo['completada'],
        'no_realizada'        => $conteo['no_realizada'],
        'cancelada'           => $conteo['cancelada'],
        'porcentaje_cumplimiento' => $totalCitas > 0 ? round(($conteo['completada'] / $totalCitas) * 100) : 0,
        'checkins_verificados'    => $verificados,
        'checkins_totales'        => $totalEntradas,
        'porcentaje_verificado'   => $totalEntradas > 0 ? round(($verificados / $totalEntradas) * 100) : 0,
        'clientes_visitados'      => $clientesVisitados,
    ],
    'diario' => [
        'dias'                    => $dias,
        'citas'                   => $serieCitas,
        'checkins_verificados'    => $serieVerificados,
        'checkins_no_verificados' => $serieNoVerificados,
    ],
]);
