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
