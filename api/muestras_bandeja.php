<?php
// Bandeja de solicitudes de muestra -- responsable de muestras (rol
// 'muestras') y admin (solo consulta). Ver muestras/index.php.
//   GET ?grupo=nuevas|en_preparacion|vencidas|cerradas|todas&q=texto
//   -> {solicitudes:[...], conteos:{nuevas, en_preparacion, vencidas,
//       cerradas, todas, embarcadas_mes}}
// "vencidas" = abiertas (nuevas o en preparación) con fecha promesa ya
// pasada, en hora de CDMX.
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/muestras.php';

requireAnyRole(['muestras', 'admin']);
$db = getDB();

const GRUPOS_BANDEJA_MUESTRAS = [
    'nuevas'         => "s.estado = 'enviada'",
    'en_preparacion' => "s.estado = 'en_preparacion'",
    'vencidas'       => "s.estado IN ('enviada', 'en_preparacion') AND s.fecha_promesa < (now() AT TIME ZONE 'America/Mexico_City')::date",
    'cerradas'       => "s.estado IN ('embarcada', 'cancelada')",
    'todas'          => 'TRUE',
];

$grupo = $_GET['grupo'] ?? 'nuevas';
if (!isset(GRUPOS_BANDEJA_MUESTRAS[$grupo])) $grupo = 'nuevas';
$q = trim($_GET['q'] ?? '');

$where  = [GRUPOS_BANDEJA_MUESTRAS[$grupo]];
$params = [];
if ($q !== '') {
    $where[] = '(s.folio ILIKE ? OR s.cliente_nombre ILIKE ? OR s.estilo_nombre ILIKE ? OR u.nombre ILIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}

// Nuevas y en preparación: lo más viejo primero (lo que lleva más tiempo
// esperando). Cerradas y todas: lo más reciente primero.
$orden = $grupo === 'vencidas' ? 's.fecha_promesa ASC'
       : (in_array($grupo, ['nuevas', 'en_preparacion'], true) ? 's.created_at ASC' : 's.actualizada_en DESC');

$stmt = $db->prepare(
    'SELECT s.*, u.nombre AS vendedor_nombre, u.email AS vendedor_email, u.telefono AS vendedor_telefono
     FROM muestras_solicitudes s
     JOIN usuarios u ON u.id = s.vendedor_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY ' . $orden . '
     LIMIT 200'
);
$stmt->execute($params);
$solicitudes = array_map('solicitudMuestraParaJson', $stmt->fetchAll());

$conteos = $db->query(
    "SELECT COUNT(*) FILTER (WHERE estado = 'enviada') AS nuevas,
            COUNT(*) FILTER (WHERE estado = 'en_preparacion') AS en_preparacion,
            COUNT(*) FILTER (WHERE estado IN ('enviada', 'en_preparacion')
                             AND fecha_promesa < (now() AT TIME ZONE 'America/Mexico_City')::date) AS vencidas,
            COUNT(*) FILTER (WHERE estado IN ('embarcada', 'cancelada')) AS cerradas,
            COUNT(*) AS todas,
            COUNT(*) FILTER (WHERE estado = 'embarcada' AND actualizada_en >= " . sqlInicioMesMx() . ") AS embarcadas_mes
     FROM muestras_solicitudes"
)->fetch();

jsonResponse(['ok' => true, 'solicitudes' => $solicitudes, 'conteos' => array_map('intval', $conteos)]);
