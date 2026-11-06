<?php
// «Cliente ya ganado» (06-nov-2026): los clientes del ERP de este vendedor
// que todavía no están en VISITAS. Ver clientesErpSinVisitas() en
// includes/cotizador_helpers.php. Solo lectura.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/cotizador_helpers.php';

$u  = requireRole('vendedor');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

$vend = vendedorQueCotiza($db, $u, 0);
if (!$vend) {
    jsonResponse(['ok' => true, 'clientes' => []]);
}

try {
    $clientes = array_map(fn($c) => [
        'id'           => (int)$c['id'],
        'razon_social' => trim((string)$c['razon_social']),
        'clave'        => (string)($c['cinterno'] ?? ''),
    ], clientesErpSinVisitas($db, $vend));
    jsonResponse(['ok' => true, 'clientes' => $clientes]);
} catch (Throwable $e) {
    error_log('[VISITAS] clientes_erp_disponibles: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'No se pudo cargar tu lista de clientes. Revisa tu conexión e intenta de nuevo.'], 500);
}
