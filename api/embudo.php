<?php
// Resumen del embudo de ventas de TODO el equipo (todos los vendedores),
// para el panel principal del admin. Solo lectura -- el admin no cambia
// etapas desde aquí, eso lo hace cada vendedor desde su cartera.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$u  = requireRole('admin');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

// Misma lista de etapas que ETAPAS_CLIENTE + ETAPA_PERDIDO en
// assets/js/vendedor.js -- se listan todas explícitamente (aunque tengan 0)
// para que el front no tenga que adivinar cuáles existen.
$ETAPAS = [
    'prospecto_agregado', 'contacto_establecido', 'reunion_presentacion',
    'propuesta_enviada', 'convertido', 'perdido',
];

$stmt = $db->query('SELECT etapa, COUNT(*) AS n FROM clientes GROUP BY etapa');
$conteos = array_fill_keys($ETAPAS, 0);
foreach ($stmt->fetchAll() as $fila) {
    if (isset($conteos[$fila['etapa']])) {
        $conteos[$fila['etapa']] = (int)$fila['n'];
    }
}

$total = array_sum($conteos);
// Tasa de conversión = cuántos de TODOS los que se han registrado (prospectos
// y clientes por igual) ya llegaron a "convertido". Incluye a los "perdido"
// en el denominador a propósito: son leads que sí se intentaron.
$tasaConversion = $total > 0 ? round($conteos['convertido'] / $total * 100) : 0;

jsonResponse([
    'ok' => true,
    'conteos' => $conteos,
    'total' => $total,
    'tasa_conversion' => $tasaConversion,
]);
