<?php
// Bandeja de solicitudes de muestra -- responsable de muestras (rol
// 'muestras') y admin (solo consulta). Ver muestras/index.php.
//   GET ?grupo=peticiones|nuevas|en_preparacion|vencidas|cerradas|todas&q=texto
//       &vendedor=id&desde=AAAA-MM-DD&hasta=AAAA-MM-DD&campo=solicitud|promesa|embarque
//   -> además: conteos_filtro (los mismos conteos con vendedor/periodo
//      aplicados, para los chips) y vendedores:[{id, nombre, n}].
// "peticiones" = abiertas en las que el vendedor pide corregir o cancelar (06-nov-2026).
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

// Sin la migración 20261106120000_muestras_peticiones.sql la bandeja no carga: avisarlo claro.
try {
    $db->query('SELECT peticion_tipo FROM muestras_solicitudes LIMIT 1');
} catch (Throwable $e) {
    jsonResponse(['ok' => false, 'error' => 'Falta correr en la base de datos la migración 20261106120000_muestras_peticiones.sql (pedir corrección de muestras). Avisa a Sistemas.'], 503);
}

const GRUPOS_BANDEJA_MUESTRAS = [
    'peticiones'     => "s.peticion_tipo IS NOT NULL AND s.estado IN ('enviada', 'en_preparacion')",
    'nuevas'         => "s.estado = 'enviada'",
    'en_preparacion' => "s.estado = 'en_preparacion'",
    'vencidas'       => "s.estado IN ('enviada', 'en_preparacion') AND s.fecha_promesa < (now() AT TIME ZONE 'America/Mexico_City')::date",
    'cerradas'       => "s.estado IN ('embarcada', 'cancelada')",
    'todas'          => 'TRUE',
];

// Fecha (en CDMX) que se compara contra el periodo elegido. created_at y
// actualizada_en se guardan en UTC; fecha_promesa ya es solo fecha. El
// embarque es actualizada_en de las que están embarcadas (igual que en ver.php).
const CAMPOS_FECHA_BANDEJA_MUESTRAS = [
    'solicitud' => "((s.created_at AT TIME ZONE 'UTC') AT TIME ZONE 'America/Mexico_City')::date",
    'promesa'   => 's.fecha_promesa',
    'embarque'  => "(CASE WHEN s.estado = 'embarcada' THEN ((s.actualizada_en AT TIME ZONE 'UTC') AT TIME ZONE 'America/Mexico_City')::date END)",
];

$grupo = $_GET['grupo'] ?? 'nuevas';
if (!isset(GRUPOS_BANDEJA_MUESTRAS[$grupo])) $grupo = 'nuevas';
$q = trim($_GET['q'] ?? '');

// Filtros de vendedor y periodo: se aplican a la lista y a los conteos de
// los chips (conteos_filtro); los conteos generales (hero y tarjetas de
// arriba) no cambian.
$fechaValida = fn($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
$vendedorId  = (int)($_GET['vendedor'] ?? 0);
$desde       = $fechaValida($_GET['desde'] ?? null);
$hasta       = $fechaValida($_GET['hasta'] ?? null);
$campoFecha  = $_GET['campo'] ?? 'solicitud';
if (!isset(CAMPOS_FECHA_BANDEJA_MUESTRAS[$campoFecha])) $campoFecha = 'solicitud';

$whereFiltro  = [];
$paramsFiltro = [];
if ($vendedorId > 0) {
    $whereFiltro[]  = 's.vendedor_id = ?';
    $paramsFiltro[] = $vendedorId;
}
if ($desde) {
    $whereFiltro[]  = CAMPOS_FECHA_BANDEJA_MUESTRAS[$campoFecha] . ' >= ?::date';
    $paramsFiltro[] = $desde;
}
if ($hasta) {
    $whereFiltro[]  = CAMPOS_FECHA_BANDEJA_MUESTRAS[$campoFecha] . ' <= ?::date';
    $paramsFiltro[] = $hasta;
}

$where  = array_merge([GRUPOS_BANDEJA_MUESTRAS[$grupo]], $whereFiltro);
$params = $paramsFiltro;
if ($q !== '') {
    $where[] = '(s.folio ILIKE ? OR s.cliente_nombre ILIKE ? OR s.estilo_nombre ILIKE ? OR u.nombre ILIKE ?
                 OR EXISTS (SELECT 1 FROM muestras_solicitudes_estilos x WHERE x.solicitud_id = s.id AND x.estilo_nombre ILIKE ?))';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
}

// Nuevas y en preparación: lo más viejo primero (lo que lleva más tiempo
// esperando). Cerradas y todas: lo más reciente primero.
$orden = $grupo === 'peticiones' ? 's.peticion_en ASC'
       : ($grupo === 'vencidas' ? 's.fecha_promesa ASC'
       : (in_array($grupo, ['nuevas', 'en_preparacion'], true) ? 's.created_at ASC' : 's.actualizada_en DESC'));

$stmt = $db->prepare(
    'SELECT s.*, u.nombre AS vendedor_nombre, u.email AS vendedor_email, u.telefono AS vendedor_telefono
     FROM muestras_solicitudes s
     JOIN usuarios u ON u.id = s.vendedor_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY ' . $orden . '
     LIMIT 200'
);
$stmt->execute($params);
$solicitudes = array_map('solicitudMuestraParaJson', adjuntarEstilosSolicitudesMuestra($db, $stmt->fetchAll()));

$sqlConteos = "SELECT COUNT(*) FILTER (WHERE s.peticion_tipo IS NOT NULL AND s.estado IN ('enviada', 'en_preparacion')) AS peticiones,
            COUNT(*) FILTER (WHERE s.estado = 'enviada') AS nuevas,
            COUNT(*) FILTER (WHERE s.estado = 'en_preparacion') AS en_preparacion,
            COUNT(*) FILTER (WHERE s.estado IN ('enviada', 'en_preparacion')
                             AND s.fecha_promesa < (now() AT TIME ZONE 'America/Mexico_City')::date) AS vencidas,
            COUNT(*) FILTER (WHERE s.estado IN ('embarcada', 'cancelada')) AS cerradas,
            COUNT(*) AS todas,
            COUNT(*) FILTER (WHERE s.estado = 'embarcada' AND s.actualizada_en >= " . sqlInicioMesMx() . ") AS embarcadas_mes
     FROM muestras_solicitudes s";

$conteos = $db->query($sqlConteos)->fetch();

$conteosFiltro = $conteos;
if ($whereFiltro) {
    $st = $db->prepare($sqlConteos . ' WHERE ' . implode(' AND ', $whereFiltro));
    $st->execute($paramsFiltro);
    $conteosFiltro = $st->fetch();
}

// Vendedores que tienen al menos una solicitud, para el filtro.
$vendedores = $db->query(
    'SELECT u.id, u.nombre, COUNT(*) AS n
     FROM muestras_solicitudes s
     JOIN usuarios u ON u.id = s.vendedor_id
     GROUP BY u.id, u.nombre
     ORDER BY u.nombre'
)->fetchAll();

jsonResponse([
    'ok'             => true,
    'solicitudes'    => $solicitudes,
    'conteos'        => array_map('intval', $conteos),
    'conteos_filtro' => array_map('intval', $conteosFiltro),
    'vendedores'     => array_map(fn($v) => ['id' => (int)$v['id'], 'nombre' => $v['nombre'], 'n' => (int)$v['n']], $vendedores),
]);
