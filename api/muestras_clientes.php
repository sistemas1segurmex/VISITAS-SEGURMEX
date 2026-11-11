<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/cartera_clientes.php';

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

// Fechas (11-nov-2026):
//  - created_at          = cuando el vendedor lo registró ("Prospecto desde").
//  - cliente_desde       = la fecha real del ERP: cuando CxC aprobó el registro
//    (cotizador_prospectos.convertido_en, fuente 'aprobado'); si se cargó
//    directo al ERP sin pasar por prospecto, la alta del cliente en el ERP
//    (clientes.created_at, fuente 'alta'). Sin ninguna de las dos = NULL.
//  - ultima_visita / ultima_cotizacion / ultima_muestra: para "Última actividad"
//    y el filtro "Sin actividad 30 días" (se calcula en la pantalla).
// Las cotizaciones se ligan igual que visitasClienteDeCotizacionErp(): por su
// prospecto espejo o por el cliente del ERP, y solo las del mismo vendedor.
$clientes = $db->query(
    "SELECT c.id, c.vendedor_id, c.nombre, c.telefono, c.nombre_contacto, c.tipo_cliente,
            c.municipio, c.estado, c.etapa, c.etapa_inicial, c.etapa_actualizada_en, c.etapa_perdido_motivo,
            c.created_at, vis.total_visitas, vis.ultima_visita, cot.ultima_cotizacion, mue.ultima_muestra,
            CASE WHEN c.etapa <> 'convertido' THEN NULL
                 WHEN apr.f IS NOT NULL THEN apr.f
                 ELSE ec.created_at END AS cliente_desde,
            CASE WHEN c.etapa <> 'convertido' THEN NULL
                 WHEN apr.f IS NOT NULL THEN 'aprobado'
                 WHEN ec.created_at IS NOT NULL THEN 'alta' END AS cliente_desde_fuente
     FROM clientes c
     JOIN usuarios u ON u.id = c.vendedor_id AND u.rol = 'vendedor'
     LEFT JOIN public.clientes ec ON ec.id = c.id_cliente_erp
     LEFT JOIN LATERAL (
        SELECT MAX(cp.convertido_en) AS f FROM public.cotizador_prospectos cp
        WHERE cp.visitas_cliente_id = c.id AND cp.convertido_a_cliente_id IS NOT NULL
     ) apr ON true
     LEFT JOIN LATERAL (
        SELECT COUNT(*) FILTER (WHERE ci.estado = 'completada') AS total_visitas,
               MAX(ci.fecha_hora) FILTER (WHERE ci.estado = 'completada') AS ultima_visita
        FROM citas ci WHERE ci.cliente_id = c.id
     ) vis ON true
     LEFT JOIN LATERAL (
        SELECT MAX(co.created_at) AS ultima_cotizacion FROM public.cotizaciones co
        WHERE " . sqlCotizacionesDeClienteVisitas() . "
     ) cot ON true
     LEFT JOIN LATERAL (
        SELECT MAX(ms.created_at) AS ultima_muestra FROM muestras_solicitudes ms WHERE ms.cliente_id = c.id
     ) mue ON true
     WHERE c.estado_revision IS DISTINCT FROM 'descartado'
     ORDER BY c.nombre"
)->fetchAll();

jsonResponse(['ok' => true, 'vendedores' => $vendedores, 'clientes' => $clientes]);
