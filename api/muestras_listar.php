<?php
// Solicitudes de muestra del vendedor con sesión, para "Mis cotizaciones"
// (vendedor/cotizaciones.php). Desde la etapa "solo aviso" (07-oct-2026)
// viven en Visitas (muestras_solicitudes) -- ver includes/muestras.php.
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/muestras.php';

$u  = requireRole('vendedor');
$db = getDB();

$stmt = $db->prepare(
    "SELECT s.*, NULL AS vendedor_nombre
     FROM muestras_solicitudes s
     WHERE s.vendedor_id = ?
     ORDER BY s.created_at DESC
     LIMIT 100"
);
$stmt->execute([$u['id']]);
$muestras = array_map(function ($s) {
    $j = solicitudMuestraParaJson($s);
    unset($j['vendedor_email'], $j['vendedor_telefono']);
    return $j;
}, $stmt->fetchAll());

$responsables = responsablesMuestras($db);
jsonResponse([
    'ok'          => true,
    'muestras'    => $muestras,
    'responsable' => $responsables ? $responsables[0]['nombre'] : null,
    'usadas_mes'  => muestrasDelMesVendedor($db, (int)$u['id']),
    'tope_mes'    => MUESTRAS_TOPE_MES,
]);
