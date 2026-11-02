<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Clientes y prospectos de todos los vendedores, agrupados por vendedor,
// para la pestaña "Clientes" de la responsable de muestras
// (muestras/clientes.php). Solo consulta: dar de alta o editar sigue siendo
// del vendedor.
$u  = requireAnyRole(['muestras', 'admin']);
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['ok' => false, 'error' => 'Sin permiso para esta acción'], 403);
}

// Vendedores activos, más los inactivos que todavía tengan clientes a su nombre.
$vendedores = $db->query(
    "SELECT u.id, u.nombre, u.email, u.activo
     FROM usuarios u
     WHERE u.rol = 'vendedor'
       AND (u.activo = 1 OR EXISTS (SELECT 1 FROM clientes c WHERE c.vendedor_id = u.id))
     ORDER BY u.nombre"
)->fetchAll();

$clientes = $db->query(
    "SELECT c.id, c.vendedor_id, c.nombre, c.telefono, c.nombre_contacto, c.tipo_cliente,
            c.municipio, c.estado, c.etapa, c.created_at,
            (SELECT COUNT(*) FROM citas ci WHERE ci.cliente_id = c.id AND ci.estado = 'completada') AS total_visitas,
            (SELECT MAX(fecha_hora) FROM citas ci WHERE ci.cliente_id = c.id AND ci.estado = 'completada') AS ultima_visita
     FROM clientes c
     JOIN usuarios u ON u.id = c.vendedor_id AND u.rol = 'vendedor'
     ORDER BY c.nombre"
)->fetchAll();

jsonResponse(['ok' => true, 'vendedores' => $vendedores, 'clientes' => $clientes]);
