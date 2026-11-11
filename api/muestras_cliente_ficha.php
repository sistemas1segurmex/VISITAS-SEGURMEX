<?php
// Ficha de un cliente/prospecto para la pestaña "Clientes" de la responsable
// de muestras (muestras/clientes.php): solo números y fechas de sus visitas,
// cotizaciones y muestras, más el recorrido de etapas. Solo consulta; se pide
// al abrir la ficha para no hacer pesada la lista.
//
// GET ?id=123

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/muestras.php';
require_once __DIR__ . '/../includes/cartera_clientes.php';

$u  = requireAnyRole(['muestras', 'admin']);
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['ok' => false, 'error' => 'Sin permiso para esta acción'], 403);
}
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    jsonResponse(['ok' => false, 'error' => 'Falta el cliente'], 400);
}

// Mismas fechas que la lista (ver api/muestras_clientes.php).
$st = $db->prepare(
    "SELECT c.id, c.nombre, c.etapa, c.etapa_inicial, c.etapa_actualizada_en, c.etapa_perdido_motivo, c.created_at,
            u.nombre AS vendedor_nombre,
            CASE WHEN c.etapa <> 'convertido' THEN NULL
                 WHEN apr.f IS NOT NULL THEN apr.f
                 ELSE ec.created_at END AS cliente_desde,
            CASE WHEN c.etapa <> 'convertido' THEN NULL
                 WHEN apr.f IS NOT NULL THEN 'aprobado'
                 WHEN ec.created_at IS NOT NULL THEN 'alta' END AS cliente_desde_fuente
     FROM clientes c
     JOIN usuarios u ON u.id = c.vendedor_id
     LEFT JOIN public.clientes ec ON ec.id = c.id_cliente_erp
     LEFT JOIN LATERAL (
        SELECT MAX(cp.convertido_en) AS f FROM public.cotizador_prospectos cp
        WHERE cp.visitas_cliente_id = c.id AND cp.convertido_a_cliente_id IS NOT NULL
     ) apr ON true
     WHERE c.id = ? AND c.estado_revision IS DISTINCT FROM 'descartado'"
);
$st->execute([$id]);
$cliente = $st->fetch();
if (!$cliente) {
    jsonResponse(['ok' => false, 'error' => 'Cliente no encontrado'], 404);
}

// Visitas: de cualquier vendedor (si se reasignó, cuentan las del anterior).
// "Próximas" = pendientes de hoy en adelante.
$st = $db->prepare(
    "SELECT COUNT(*) FILTER (WHERE estado = 'completada') AS hechas,
            COUNT(*) FILTER (WHERE estado = 'pendiente' AND fecha_hora >= NOW()) AS proximas,
            COUNT(*) FILTER (WHERE estado = 'no_realizada') AS no_realizadas,
            COUNT(*) FILTER (WHERE estado = 'cancelada') AS canceladas,
            MAX(fecha_hora) FILTER (WHERE estado = 'completada') AS ultima,
            MIN(fecha_hora) FILTER (WHERE estado = 'pendiente' AND fecha_hora >= NOW()) AS proxima
     FROM citas WHERE cliente_id = ?"
);
$st->execute([$id]);
$visitas = $st->fetch();

// Cotizaciones: sin contar las que se reemplazaron con «Corregir» (la
// anterior queda cancelada y la nueva es la que vale).
$st = $db->prepare(
    "SELECT COUNT(*) AS total,
            COUNT(*) FILTER (WHERE co.estado IN ('pendiente', 'enviada', 'en_negociacion')) AS abiertas,
            COUNT(*) FILTER (WHERE co.estado = 'aceptada') AS aceptadas,
            COUNT(*) FILTER (WHERE co.estado IN ('cancelada', 'rechazada')) AS canceladas,
            MIN(co.created_at) AS primera, MAX(co.created_at) AS ultima
     FROM clientes c
     JOIN usuarios u ON u.id = c.vendedor_id
     JOIN public.cotizaciones co ON " . sqlCotizacionesDeClienteVisitas() . "
     WHERE c.id = ?
       AND NOT EXISTS (SELECT 1 FROM public.cotizaciones r WHERE r.reemplaza_a = co.id)"
);
$st->execute([$id]);
$cotizaciones = $st->fetch();

$st = $db->prepare(
    "SELECT COUNT(*) AS total,
            COUNT(*) FILTER (WHERE estado IN ('enviada', 'en_preparacion')) AS en_proceso,
            COUNT(*) FILTER (WHERE estado = 'embarcada') AS embarcadas,
            COUNT(*) FILTER (WHERE estado = 'cancelada') AS canceladas,
            MAX(created_at) AS ultima
     FROM muestras_solicitudes WHERE cliente_id = ?"
);
$st->execute([$id]);
$muestras = $st->fetch();
$muestras['pares_mes'] = paresMuestraDelMesCliente($db, $id);
$muestras['tope_mes']  = MUESTRAS_TOPE_PARES_CLIENTE_MES;

// Recorrido: cambios de etapa de la bitácora (se guarda desde el 14-sep-2026).
// Si no está el alta en la bitácora, el registro se toma de created_at.
$st = $db->prepare(
    "SELECT accion, cambios, creado_en FROM bitacora_cambios
     WHERE entidad = 'cliente' AND entidad_id = ?
       AND (cambios->'Etapa' IS NOT NULL OR cambios->'Etapa inicial' IS NOT NULL)
     ORDER BY creado_en, id"
);
$st->execute([$id]);
$recorrido = [];
$hayAlta = false;
foreach ($st->fetchAll() as $b) {
    $cambios = json_decode((string)$b['cambios'], true) ?: [];
    if (isset($cambios['Etapa inicial'])) {
        $hayAlta = true;
        $recorrido[] = ['tipo' => 'alta', 'etapa' => $cambios['Etapa inicial'][1] ?? null, 'fecha' => $b['creado_en']];
    }
    if (isset($cambios['Etapa'])) {
        $recorrido[] = [
            'tipo'   => 'cambio',
            'etapa'  => $cambios['Etapa'][1] ?? null,
            'motivo' => $cambios['Motivo'][1] ?? null,
            'fecha'  => $b['creado_en'],
        ];
    }
}
if (!$hayAlta) {
    array_unshift($recorrido, [
        'tipo'  => 'alta',
        'etapa' => $cliente['etapa_inicial'] ? etiquetaEtapa($cliente['etapa_inicial']) : null,
        'fecha' => $cliente['created_at'],
    ]);
}

jsonResponse([
    'ok'           => true,
    'cliente'      => $cliente,
    'visitas'      => $visitas,
    'cotizaciones' => $cotizaciones,
    'muestras'     => $muestras,
    'recorrido'    => $recorrido,
]);
