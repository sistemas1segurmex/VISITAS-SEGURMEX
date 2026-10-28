<?php
/**
 * Cotizaciones para vendedores foráneos -- ver
 * docs/arquitectura-cotizaciones-foraneos.md (repo del ERP) para el diseño
 * completo. Lee/escribe directo en el esquema "public" del ERP (misma base
 * de Supabase que VISITAS, otro esquema) usando getDBErp().
 *
 * GET  -> lista "mis cotizaciones" (las del vendedor espejo del foráneo logueado).
 * POST -> crea una cotización nueva, con las mismas reglas de precio que
 *         usa un vendedor interno (ver includes/cotizador_helpers.php).
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/cotizador_helpers.php';

// 'muestras' (la responsable de muestras) también cotiza, a nombre del
// vendedor que elija, y ve las cotizaciones de todos los vendedores.
$u  = requireAnyRole(['vendedor', 'muestras']);
$db = getDB(); // esquema "visitas" -- para leer cliente/cita/prospección del foráneo
$esMuestras = $u['rol'] === 'muestras';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $esMuestras) {
    if (isset($_GET['vendedores'])) {
        // Vendedores a cuyo nombre se puede cotizar, con cuántos clientes tiene cada uno.
        $stmt = $db->query(
            "SELECT u.id, u.nombre, COUNT(c.id) AS clientes
             FROM usuarios u LEFT JOIN clientes c ON c.vendedor_id = u.id
             WHERE u.rol = 'vendedor' AND u.activo = 1
             GROUP BY u.id, u.nombre ORDER BY u.nombre"
        );
        jsonResponse(['ok' => true, 'vendedores' => $stmt->fetchAll()]);
    }
    // Solo las que capturó ella, a nombre de cualquier vendedor. La tabla del
    // ERP no guarda quién capturó; se toma de la bitácora de VISITAS (el
    // 'alta' que anota el POST de abajo con su id).
    $stmt = $db->prepare(
        "SELECT co.id, co.folio, co.cliente_nombre, co.estado, co.total, co.total_pares, co.vigencia_dias, co.created_at,
                u.id AS vendedor_id, u.nombre AS vendedor_nombre
         FROM public.cotizaciones co
         JOIN public.usuarios eu ON eu.id = co.id_vendedor
         JOIN usuarios u ON LOWER(u.email) = LOWER(eu.email) AND u.rol = 'vendedor'
         WHERE co.id IN (SELECT entidad_id FROM bitacora_cambios
                         WHERE vendedor_id = ? AND entidad = 'cotizacion' AND accion = 'alta')
         ORDER BY co.created_at DESC LIMIT 500"
    );
    $stmt->execute([$u['id']]);
    jsonResponse(['ok' => true, 'cotizaciones' => $stmt->fetchAll()]);
}

// Vendedor a cuyo nombre queda la cotización: el logueado, o el que eligió
// la responsable de muestras. Su correo liga (o crea) el vendedor espejo del ERP.
$vend = vendedorQueCotiza($db, $u, (int)($_POST['vendedor_id'] ?? 0));
if (!$vend) {
    jsonResponse(['ok' => false, 'error' => $esMuestras ? 'Elige el vendedor.' : 'No se encontró tu correo registrado.'], $esMuestras ? 400 : 500);
}
$email = $vend['email'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $idVendedorErp = obtenerOCrearVendedorErp($email, $vend['nombre']);
    $stmt = getDBErp()->prepare(
        "SELECT id, folio, cliente_nombre, estado, total, total_pares, vigencia_dias, created_at, visitas_cita_id,
                id_cliente, id_prospecto
         FROM cotizaciones WHERE id_vendedor = ? ORDER BY created_at DESC LIMIT 200"
    );
    $stmt->execute([$idVendedorErp]);
    jsonResponse(['ok' => true, 'cotizaciones' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clienteId = (int)($_POST['cliente_id'] ?? 0);
    $citaId        = !$esMuestras && !empty($_POST['visitas_cita_id']) ? (int)$_POST['visitas_cita_id'] : null;
    $prospeccionId = !$esMuestras && !empty($_POST['visitas_prospeccion_id']) ? (int)$_POST['visitas_prospeccion_id'] : null;
    $tipoLista  = ($_POST['tipo_lista'] ?? '') === 'distribuidor' ? 'distribuidor' : 'industria';
    $prontoPago = isset($_POST['pronto_pago']) && $_POST['pronto_pago'] !== '' && $_POST['pronto_pago'] !== '0';
    $vigenciaDias  = max(1, (int)($_POST['vigencia_dias'] ?? 15));
    $tiempoEntrega = trim($_POST['tiempo_entrega'] ?? '');
    $formaPago     = trim($_POST['forma_pago'] ?? '');
    $notas         = trim($_POST['notas'] ?? '');
    $clienteContacto = trim($_POST['cliente_contacto'] ?? '');
    $clienteEmail    = trim($_POST['cliente_email'] ?? '');
    $aplicaEnvio = ($_POST['aplicar_gasto_envio'] ?? '0') === '1';
    $gastoEnvio  = $aplicaEnvio ? max(0.0, round((float)str_replace([',', '$', ' '], '', (string)($_POST['gasto_envio'] ?? '')), 2)) : 0.0;

    $renglones = json_decode($_POST['renglones'] ?? '[]', true);
    if (!is_array($renglones)) $renglones = [];

    if (!$clienteId) {
        jsonResponse(['ok' => false, 'error' => 'Selecciona a qué cliente le vas a cotizar.'], 400);
    }
    if ($clienteEmail !== '' && !filter_var($clienteEmail, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['ok' => false, 'error' => 'El correo no tiene un formato válido.'], 400);
    }

    // Cliente de VISITAS: debe ser del vendedor a cuyo nombre se cotiza
    // (mismo criterio de propiedad que ya usa api/citas.php).
    $stmtC = $db->prepare('SELECT * FROM clientes WHERE id = ? AND vendedor_id = ?');
    $stmtC->execute([$clienteId, $vend['id']]);
    $clienteVisitas = $stmtC->fetch();
    if (!$clienteVisitas) {
        jsonResponse(['ok' => false, 'error' => 'Cliente no válido.'], 400);
    }

    // Si viene de una cita, confirmar que también es del vendedor logueado.
    if ($citaId) {
        $chkCita = $db->prepare('SELECT 1 FROM citas WHERE id = ? AND vendedor_id = ?');
        $chkCita->execute([$citaId, $vend['id']]);
        if (!$chkCita->fetchColumn()) $citaId = null;
    }

    if (!$renglones) {
        jsonResponse(['ok' => false, 'error' => 'Agrega al menos un modelo con cantidad.'], 400);
    }
    if ($aplicaEnvio && $gastoEnvio <= 0) {
        jsonResponse(['ok' => false, 'error' => 'Captura el costo del gasto de envío o marca «No».'], 400);
    }

    $dbErp = getDBErp();
    $cfg   = configCotizadorErp();

    // ── Validar y calcular cada renglón con las reglas del cotizador del ERP ──
    $res = resolverRenglonesCotizacionErp(catalogoCotizableErp($cfg), $renglones, $tipoLista, $prontoPago, $cfg);
    if ($res['errores']) {
        jsonResponse(['ok' => false, 'error' => implode('. ', $res['errores']) . '.'], 400);
    }
    $lineas        = $res['lineas'];
    $subtotal      = $res['subtotal'];
    $totalPares    = $res['total_pares'];
    $mayoreoAplica = $res['aplica_mayoreo'];

    $tasaIva = (float)($cfg['tasa_iva'] ?? 0.16);
    ['iva' => $iva, 'total' => $total] = totalesCotizacionErp($res['subtotal_exacto'], $tasaIva, $gastoEnvio);

    try {
        $dbErp->beginTransaction();

        $idVendedorErp = obtenerOCrearVendedorErp($email, $vend['nombre']);
        [$idProspecto, $prospectoEsNuevo] = obtenerOCrearProspectoErp(
            $clienteId, $clienteVisitas['nombre'], $clienteVisitas['telefono'] ?? null, $vend['nombre']
        );

        $dbErp->prepare("INSERT INTO cotizaciones
            (id_prospecto, cliente_nombre, cliente_contacto, cliente_telefono, cliente_email, cliente_direccion,
             id_vendedor, tipo_lista, pronto_pago, aplica_mayoreo, total_pares,
             desc_mayoreo_aplicado, desc_pronto_pago_aplicado, tasa_iva,
             subtotal, gasto_envio, iva, total, vigencia_dias, tiempo_entrega, forma_pago, notas, estado,
             visitas_cita_id, visitas_prospeccion_id)
            VALUES (?,?,?,?,?,?, ?,?,?,?,?, ?,?,?, ?,?,?,?, ?,?,?,?, 'pendiente', ?,?)")
           ->execute([
                $idProspecto, $clienteVisitas['nombre'], $clienteContacto ?: null,
                $clienteVisitas['telefono'] ?: null, $clienteEmail ?: null, $clienteVisitas['direccion'] ?: null,
                $idVendedorErp, $tipoLista, $prontoPago ? 1 : 0, $mayoreoAplica ? 1 : 0, $totalPares,
                $mayoreoAplica ? (float)($cfg['descuento_mayoreo'] ?? 0) : 0,
                $prontoPago ? (float)($cfg['descuento_pronto_pago'] ?? 0) : 0,
                $tasaIva, $subtotal, $gastoEnvio, $iva, $total, $vigenciaDias,
                $tiempoEntrega ?: null, $formaPago ?: null, $notas ?: null,
                $citaId, $prospeccionId,
           ]);
        $newId = (int)$dbErp->lastInsertId();
        $folio = folioCotizacionErp($newId);
        $dbErp->prepare('UPDATE cotizaciones SET folio=? WHERE id=?')->execute([$folio, $newId]);

        // El link para el cliente se genera desde que se crea (no hasta que
        // se marque "Enviada"): así el vendedor foráneo puede copiarlo y
        // mandarlo primero, y marcar el estado como confirmación de que ya
        // lo hizo -- en vez de al revés. Es idempotente (generarTokenPublicoCotizacionErp
        // no pisa un token si ya existe), así que no afecta el flujo normal
        // de cambiarEstadoCotizacionErp() al pasar a "enviada" más adelante.
        generarTokenPublicoCotizacionErp($dbErp, $newId);

        $insDet = $dbErp->prepare("INSERT INTO cotizacion_detalle
            (id_cotizacion, id_estilo, id_modelo_legacy, clave_estilo, nombre_estilo, color, cantidad, precio_lista, precio_minimo, precio_final, importe, orden, extra_concepto, extra_precio)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        foreach ($lineas as $ord => $l) {
            $insDet->execute([$newId, $l['id_estilo'], $l['id_modelo_legacy'], $l['clave_estilo'], $l['nombre_estilo'], $l['color'],
                $l['cantidad'], $l['precio_lista'], $l['precio_minimo'], $l['precio_final'], $l['importe'], $ord,
                $l['extra_concepto'] ?? null, (float)($l['extra_precio'] ?? 0)]);
        }

        $dbErp->prepare("INSERT INTO cotizacion_historial (id_cotizacion, estado_anterior, estado_nuevo, id_usuario) VALUES (?,NULL,'pendiente',?)")
              ->execute([$newId, $idVendedorErp]);

        $dbErp->commit();

        if ($prospectoEsNuevo) {
            registrarActividadSeguimientoErp($idVendedorErp, 'prospecto_nuevo', $idProspecto, null);
        }

        // Si al cotizar corrigió el contacto o el correo, se guarda también en
        // la ficha del cliente. Un campo que dejó vacío no borra lo que ya
        // había. Va aparte: si falla, la cotización ya quedó hecha.
        $nuevosDatos = ['nombre_contacto' => $clienteContacto, 'correo' => $clienteEmail];
        $diffCliente = [];
        foreach ($nuevosDatos as $col => $valor) {
            $valor = mb_substr($valor, 0, 150);
            $antes = trim((string)($clienteVisitas[$col] ?? ''));
            if ($valor !== '' && $valor !== $antes) $diffCliente[$col] = [$antes ?: null, $valor];
        }
        if ($diffCliente) {
            try {
                $sets = implode(', ', array_map(fn($c) => "$c = ?", array_keys($diffCliente)));
                $db->prepare("UPDATE clientes SET $sets WHERE id = ?")
                   ->execute([...array_column($diffCliente, 1), $clienteId]);
                $etiquetas = ['nombre_contacto' => 'Contacto', 'correo' => 'Correo'];
                $diff = [];
                foreach ($diffCliente as $col => $par) $diff[$etiquetas[$col]] = $par;
                registrarCambio($db, $u['id'], 'cliente', $clienteId, 'edicion', "Actualizó los datos de {$clienteVisitas['nombre']} al cotizar ({$folio})", $diff);
            } catch (Throwable $e) {
                error_log('[VISITAS] api/cotizaciones.php (datos del cliente): ' . $e->getMessage());
            }
        }

        // El detalle completo (líneas, cambios de estado) ya vive en
        // cotizacion_historial del ERP -- aquí solo se anota el alta para
        // que aparezca junto con clientes/citas en la bitácora del admin.
        registrarCambio($db, $u['id'], 'cotizacion', $newId, 'alta', "Generó la cotización {$folio} para {$clienteVisitas['nombre']}"
            . ($esMuestras ? " a nombre de {$vend['nombre']}" : ''), [
            'Total' => [null, money($total)],
        ]);

        jsonResponse(['ok' => true, 'id' => $newId, 'folio' => $folio]);
    } catch (Throwable $e) {
        if ($dbErp->inTransaction()) $dbErp->rollBack();
        error_log('[VISITAS] api/cotizaciones.php: ' . $e->getMessage());
        jsonResponse(['ok' => false, 'error' => 'Ocurrió un error al guardar la cotización. Intenta de nuevo.'], 500);
    }
}

jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
