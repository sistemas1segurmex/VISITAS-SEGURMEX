<?php
/**
 * Prospectos de VISITAS ↔ ERP (12-nov-2026).
 *
 *  · crearProspectoErpDeAlta(): el prospecto aparece en ERP › Ventas ›
 *    Prospectos desde que el vendedor lo da de alta (antes solo aparecía al
 *    cotizarle). Solo altas nuevas «Prospecto nuevo»; si quedó en revisión
 *    se crea hasta que se decide «Son distintos»; si se descarta, nunca.
 *  · sincronizarProspectoErpEdicion(): al editar nombre/teléfono/contacto en
 *    VISITAS, el prospecto del ERP (si existe y no es cliente) se actualiza.
 *  · reasignarEnErp(): «Pasa al nuevo vendedor» en Coincidencias. El
 *    prospecto pasa al vendedor nuevo, el enlace de registro sin llenar se
 *    cancela, el ya llenado (sin aprobar) queda con el vendedor nuevo y, si
 *    ya es cliente del ERP, se le cambia el agente. Lo de antes (cotizaciones)
 *    se queda con el vendedor anterior.
 *
 * Nada de esto debe tumbar lo que se hace en VISITAS: cualquier falla del
 * ERP se anota en el log y VISITAS sigue.
 */

require_once __DIR__ . '/cotizador_helpers.php';
require_once __DIR__ . '/db_erp.php';

/** Marca en las notas del enlace (la reconoce el ERP: config.php MARCA_INVITACION_CANCELADA). */
const MARCA_ENLACE_CANCELADO = '[Cancelado: ';

/**
 * Usuario del ERP de un vendedor de VISITAS (cruce por correo, igual que al
 * cotizar; si no existe lo crea como en obtenerOCrearVendedorErp()).
 * Devuelve ['id' => int, 'usuario' => string, 'nombre' => string] o null.
 */
function usuarioErpDeVendedorVisitas(PDO $db, int $vendedorId): ?array {
    $st = $db->prepare("SELECT id, nombre, email FROM usuarios WHERE id = ? AND rol = 'vendedor'");
    $st->execute([$vendedorId]);
    $v = $st->fetch();
    if (!$v || empty($v['email'])) return null;
    $dbErp = getDBErp();
    $st = $dbErp->prepare('SELECT id, usuario FROM usuarios WHERE LOWER(email) = LOWER(?) ORDER BY id LIMIT 1');
    $st->execute([(string)$v['email']]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
        $id = obtenerOCrearVendedorErp((string)$v['email'], (string)$v['nombre']);
        $st = $dbErp->prepare('SELECT id, usuario FROM usuarios WHERE id = ?');
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
    }
    return $r ? ['id' => (int)$r['id'], 'usuario' => (string)$r['usuario'], 'nombre' => (string)$v['nombre']] : null;
}

/**
 * Crea (o reutiliza) el prospecto del ERP de un cliente de VISITAS recién
 * dado de alta como «Prospecto nuevo». No hace nada si se registró como
 * «Ya es mi cliente», si está en revisión/descartado o si ya es cliente.
 */
function crearProspectoErpDeAlta(PDO $db, int $clienteId): void {
    try {
        $st = $db->prepare('SELECT id, vendedor_id, nombre, telefono, nombre_contacto, etapa, etapa_inicial,
                                   estado_revision, id_cliente_erp
                              FROM clientes WHERE id = ?');
        $st->execute([$clienteId]);
        $c = $st->fetch();
        if (!$c || $c['etapa_inicial'] !== 'prospecto_agregado' || !empty($c['id_cliente_erp'])
            || $c['estado_revision'] !== null || $c['etapa'] === 'convertido') {
            return;
        }
        $ue = usuarioErpDeVendedorVisitas($db, (int)$c['vendedor_id']);
        if (!$ue) {
            error_log("[VISITAS] crearProspectoErpDeAlta: el vendedor {$c['vendedor_id']} no tiene correo; no se creó en el ERP");
            return;
        }
        // Sin cotización todavía, el ERP lo muestra al vendedor por creado_por =
        // su usuario del ERP (cotizacion/prospectos.php, filtroProspectosVisibles).
        obtenerOCrearProspectoErp($clienteId, (string)$c['nombre'], $c['telefono'] ?: null, $ue['usuario'],
                                  $c['nombre_contacto'] ?: null);
    } catch (Throwable $e) {
        error_log('[VISITAS] crearProspectoErpDeAlta: ' . $e->getMessage());
    }
}

/** Al editar en VISITAS: nombre, teléfono y contacto del prospecto del ERP (si aún no es cliente). */
function sincronizarProspectoErpEdicion(int $clienteId, string $nombre, ?string $telefono, ?string $contacto): void {
    try {
        $nombre = mb_substr($nombre, 0, 150);
        getDBErp()->prepare('UPDATE cotizador_prospectos
                                SET nombre = ?, empresa = ?, telefono = ?, contacto = ?
                              WHERE visitas_cliente_id = ? AND convertido_a_cliente_id IS NULL')
            ->execute([$nombre, $nombre, $telefono ? mb_substr($telefono, 0, 30) : null,
                       $contacto ? mb_substr($contacto, 0, 150) : null, $clienteId]);
    } catch (Throwable $e) {
        error_log('[VISITAS] sincronizarProspectoErpEdicion: ' . $e->getMessage());
    }
}

/**
 * «Pasa al nuevo vendedor»: deja el ERP igual que VISITAS para el cliente
 * $existenteId (que ya cambió de vendedor en VISITAS). $quien = nombre de
 * quien decidió (responsable de muestras o Sistemas).
 * Devuelve lo que se hizo, para la bitácora: ['prospecto'=>n,'enlaces_cancelados'=>n,'enlaces_movidos'=>n,'cliente_erp'=>n].
 */
function reasignarEnErp(PDO $db, int $existenteId, int $vendedorNuevoId, string $quien): array {
    $hecho = ['prospecto' => 0, 'enlaces_cancelados' => 0, 'enlaces_movidos' => 0, 'cliente_erp' => 0];
    try {
        $ue = usuarioErpDeVendedorVisitas($db, $vendedorNuevoId);
        if (!$ue) {
            error_log("[VISITAS] reasignarEnErp: el vendedor $vendedorNuevoId no tiene correo; el ERP no se actualizó");
            return $hecho;
        }
        $st = $db->prepare('SELECT id_cliente_erp FROM clientes WHERE id = ?');
        $st->execute([$existenteId]);
        $idClienteErp = (int)$st->fetchColumn();

        $dbErp = getDBErp();
        $dbErp->beginTransaction();
        try {
            // Prospectos del ERP de este cliente (sin convertir y convertidos).
            $st = $dbErp->prepare('SELECT id, convertido_a_cliente_id FROM cotizador_prospectos WHERE visitas_cliente_id = ?');
            $st->execute([$existenteId]);
            $prospectos = $st->fetchAll(PDO::FETCH_ASSOC);
            $idsProsp = array_map(fn($p) => (int)$p['id'], $prospectos);
            foreach ($prospectos as $p) {
                if (!$idClienteErp && !empty($p['convertido_a_cliente_id'])) $idClienteErp = (int)$p['convertido_a_cliente_id'];
            }

            // 1) El prospecto (si aún no es cliente) pasa al vendedor nuevo.
            $st = $dbErp->prepare('UPDATE cotizador_prospectos SET creado_por = ?
                                    WHERE visitas_cliente_id = ? AND convertido_a_cliente_id IS NULL');
            $st->execute([$ue['usuario'], $existenteId]);
            $hecho['prospecto'] = $st->rowCount();

            if ($idsProsp) {
                $qs = implode(',', array_fill(0, count($idsProsp), '?'));
                // 2) Enlace sin llenar: se cancela (el vendedor nuevo manda otro).
                $nota = MARCA_ENLACE_CANCELADO . 'el cliente pasó a ' . $ue['nombre'] . '; decidió ' . $quien
                      . ' el ' . date('d/m/Y H:i') . ']';
                $st = $dbErp->prepare("UPDATE invitaciones_cliente
                                          SET estado = 'expirada', notas = CONCAT_WS(E'\\n', NULLIF(notas, ''), ?::text)
                                        WHERE id_prospecto_origen IN ($qs) AND estado = 'pendiente'");
                $st->execute(array_merge([$nota], $idsProsp));
                $hecho['enlaces_cancelados'] = $st->rowCount();

                // 3) Enlace ya llenado y sin aprobar: se respeta y queda con el vendedor nuevo.
                $st = $dbErp->prepare("UPDATE invitaciones_cliente i SET id_agente = ?
                                        WHERE i.id_prospecto_origen IN ($qs) AND i.estado = 'completada'
                                          AND EXISTS (SELECT 1 FROM clientes_solicitudes s WHERE s.token = i.token
                                                         AND s.estado IN ('pendiente', 'aprobada_ventas'))");
                $st->execute(array_merge([$ue['id']], $idsProsp));
                $hecho['enlaces_movidos'] = $st->rowCount();
                $dbErp->prepare("UPDATE clientes_solicitudes s SET id_agente = ?
                                   FROM invitaciones_cliente i
                                  WHERE s.token = i.token AND i.id_prospecto_origen IN ($qs)
                                    AND s.estado = 'aprobada_ventas' AND s.id_agente IS NOT NULL")
                      ->execute(array_merge([$ue['id']], $idsProsp));
            }

            // 4) Ya es cliente del ERP: cambia su agente.
            if ($idClienteErp) {
                $st = $dbErp->prepare('UPDATE clientes SET id_agente = ? WHERE id = ? AND id_agente IS DISTINCT FROM ?');
                $st->execute([$ue['id'], $idClienteErp, $ue['id']]);
                $hecho['cliente_erp'] = $st->rowCount();
            }
            $dbErp->commit();
        } catch (Throwable $e) {
            $dbErp->rollBack();
            throw $e;
        }
    } catch (Throwable $e) {
        error_log('[VISITAS] reasignarEnErp: ' . $e->getMessage());
    }
    return $hecho;
}
