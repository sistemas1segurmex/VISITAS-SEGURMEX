<?php
// Cartera de clientes por vendedor (pestaña "Clientes" de la responsable de
// muestras): piezas de SQL que comparten la lista (api/muestras_clientes.php)
// y la ficha (api/muestras_cliente_ficha.php). Solo consulta.

/**
 * Condición SQL: cotizaciones del ERP (alias "co") de un cliente de VISITAS
 * (alias "c") hechas por su vendedor (alias "u", visitas.usuarios). Mismo
 * criterio que visitasClienteDeCotizacionErp(): por el prospecto espejo, por
 * clientes.id_cliente_erp o por el cliente en que se convirtió el prospecto.
 * Los vendedores se ligan entre los dos sistemas por correo.
 */
function sqlCotizacionesDeClienteVisitas(): string {
    return "co.id_vendedor IN (SELECT eu.id FROM public.usuarios eu WHERE LOWER(eu.email) = LOWER(u.email))
        AND (co.id_prospecto IN (SELECT cp.id FROM public.cotizador_prospectos cp WHERE cp.visitas_cliente_id = c.id)
             OR co.id_cliente = c.id_cliente_erp
             OR co.id_cliente IN (SELECT cp.convertido_a_cliente_id FROM public.cotizador_prospectos cp
                                  WHERE cp.visitas_cliente_id = c.id AND cp.convertido_a_cliente_id IS NOT NULL))";
}

// ─────────────────────────────────────────────────────────────────────────
// Exportar la cartera a Excel (12-nov-2026, api/exportar_cartera.php).
// Solo la responsable de muestras y los admin (Dirección/Sistemas); los
// vendedores no exportan. Va todo lo que el vendedor tiene asignado hoy
// (clientes, prospectos y perdidos), sin descartados por duplicado; un
// cliente reasignado sale solo con su vendedor actual.
// ─────────────────────────────────────────────────────────────────────────

/**
 * Vendedores a exportar: uno ($vendedorId) o todos (los activos más los
 * inactivos que aún tengan clientes, igual que la pestaña Clientes).
 */
function carteraVendedores(PDO $db, ?int $vendedorId): array {
    if ($vendedorId) {
        $st = $db->prepare("SELECT id, nombre, email, activo FROM usuarios WHERE id = ? AND rol = 'vendedor'");
        $st->execute([$vendedorId]);
        return $st->fetchAll();
    }
    return $db->query(
        "SELECT u.id, u.nombre, u.email, u.activo
         FROM usuarios u
         WHERE u.rol = 'vendedor'
           AND (u.activo = 1 OR EXISTS (SELECT 1 FROM clientes c WHERE c.vendedor_id = u.id))
         ORDER BY u.nombre"
    )->fetchAll();
}

/**
 * Clientes y prospectos de esos vendedores con lo que lleva el Excel.
 * Fechas: mismas reglas que la pestaña Clientes (cliente_desde = fecha real
 * en que CxC aprobó el registro o, si se cargó directo, su alta). Las
 * cotizaciones no cuentan las reemplazadas con «Corregir»; las muestras no
 * cuentan las canceladas.
 */
function carteraClientesExportar(PDO $db, array $idsVendedores): array {
    if (!$idsVendedores) return [];
    $qs = implode(',', array_fill(0, count($idsVendedores), '?'));
    $st = $db->prepare(
        "SELECT c.id, c.vendedor_id, c.nombre, c.tipo_cliente, c.etapa, c.etapa_perdido_motivo,
                c.nombre_contacto, c.telefono, c.calle_numero, c.direccion, c.colonia, c.codigo_postal,
                c.municipio, c.estado, c.lat, c.lng, c.created_at,
                CASE WHEN c.etapa <> 'convertido' THEN NULL
                     WHEN apr.f IS NOT NULL THEN apr.f
                     ELSE ec.created_at END AS cliente_desde,
                vis.visitas_hechas, vis.ultima_visita, vis.proxima_visita, itr.ultimo_interes,
                cot.cotizaciones, cot.ultima_cotizacion, mue.muestras, mue.ultima_muestra
         FROM clientes c
         JOIN usuarios u ON u.id = c.vendedor_id
         LEFT JOIN public.clientes ec ON ec.id = c.id_cliente_erp
         LEFT JOIN LATERAL (
            SELECT MAX(cp.convertido_en) AS f FROM public.cotizador_prospectos cp
            WHERE cp.visitas_cliente_id = c.id AND cp.convertido_a_cliente_id IS NOT NULL
         ) apr ON true
         -- citas.fecha_hora es hora local de CDMX (la sesión está en UTC).
         LEFT JOIN LATERAL (
            SELECT COUNT(*) FILTER (WHERE ci.estado = 'completada') AS visitas_hechas,
                   MAX(ci.fecha_hora) FILTER (WHERE ci.estado = 'completada') AS ultima_visita,
                   MIN(ci.fecha_hora) FILTER (WHERE ci.estado = 'pendiente'
                        AND ci.fecha_hora >= (NOW() AT TIME ZONE 'America/Mexico_City')) AS proxima_visita
            FROM citas ci WHERE ci.cliente_id = c.id
         ) vis ON true
         LEFT JOIN LATERAL (
            SELECT ci.interes AS ultimo_interes FROM citas ci
            WHERE ci.cliente_id = c.id AND ci.interes IS NOT NULL
            ORDER BY ci.fecha_hora DESC LIMIT 1
         ) itr ON true
         LEFT JOIN LATERAL (
            SELECT COUNT(*) AS cotizaciones, MAX(co.created_at) AS ultima_cotizacion
            FROM public.cotizaciones co
            WHERE " . sqlCotizacionesDeClienteVisitas() . "
              AND NOT EXISTS (SELECT 1 FROM public.cotizaciones r WHERE r.reemplaza_a = co.id)
         ) cot ON true
         LEFT JOIN LATERAL (
            SELECT COUNT(*) FILTER (WHERE ms.estado <> 'cancelada') AS muestras,
                   MAX(ms.created_at) FILTER (WHERE ms.estado <> 'cancelada') AS ultima_muestra
            FROM muestras_solicitudes ms WHERE ms.cliente_id = c.id
         ) mue ON true
         WHERE c.vendedor_id IN ($qs)
           AND c.estado_revision IS DISTINCT FROM 'descartado'
         ORDER BY c.nombre"
    );
    $st->execute(array_values($idsVendedores));
    return $st->fetchAll();
}
