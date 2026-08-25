<?php
/**
 * Reglas de precio del Cotizador, COPIADAS de erp/cotizacion/_helpers.php
 * (precioBaseEstilo, aplicaMayoreo, calcularPrecioMinimo, folioCotizacion)
 * para que un vendedor foráneo cotice con las mismas condiciones que uno
 * interno de oficina, sin que VISITAS tenga que llamar al ERP en vivo.
 *
 * IMPORTANTE (deuda técnica conocida, ver
 * docs/arquitectura-cotizaciones-foraneos.md §6 en el repo del ERP): los
 * VALORES (config_cotizador: % de descuento, IVA) se leen en vivo de la BD
 * del ERP, así que un cambio de parámetro se refleja solo. Pero la FÓRMULA
 * de abajo es una copia -- si el ERP cambia CÓMO se combinan los descuentos,
 * hay que replicar el cambio aquí a mano.
 */
require_once __DIR__ . '/db_erp.php';

if (!function_exists('money')) {
    function money($n): string { return '$' . number_format((float)$n, 2); }
}

/** Los parámetros de config_cotizador del ERP. Cache estático por request. */
function configCotizadorErp(): array {
    static $cfg = null;
    if ($cfg === null) {
        $cfg = [];
        foreach (getDBErp()->query('SELECT clave, valor FROM config_cotizador')->fetchAll() as $r) {
            $cfg[$r['clave']] = (float)$r['valor'];
        }
    }
    return $cfg;
}

/**
 * Precio de lista de un Estilo según el tipo de lista de la cotización.
 * Copia exacta de precioBaseEstilo() en erp/cotizacion/_helpers.php.
 */
function precioBaseEstiloErp(array $estilo, string $tipoLista, array $cfg): float {
    $industrial = (float)$estilo['precio_industrial'];
    if ($tipoLista === 'distribuidor') {
        $descDistribuidor = (float)($cfg['descuento_distribuidor'] ?? 0.15);
        return round($industrial * (1 - $descDistribuidor));
    }
    return $industrial;
}

/** Copia exacta de aplicaMayoreo(). */
function aplicaMayoreoErp(int $totalPares, array $cfg): bool {
    return $totalPares >= (int)($cfg['minimo_pares_mayoreo'] ?? 16);
}

/** Copia exacta de calcularPrecioMinimo(). */
function calcularPrecioMinimoErp(float $base, bool $aplicaMayoreo, bool $prontoPago, array $cfg): float {
    $f = 1.0;
    if ($prontoPago)    $f *= (1 - (float)($cfg['descuento_pronto_pago'] ?? 0.05));
    if ($aplicaMayoreo) $f *= (1 - (float)($cfg['descuento_mayoreo'] ?? 0.10));
    return round($base * $f, 2);
}

/** Copia exacta de folioCotizacion(). */
function folioCotizacionErp(int $id): string {
    return 'COT-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
}

/** Estilos cotizables -- mismo filtro que erp/cotizacion/nuevo.php. */
function buscarEstilosErp(string $q, int $limite = 30): array {
    $db = getDBErp();
    $limite = max(1, min(100, $limite));
    if ($q === '') {
        $stmt = $db->prepare(
            "SELECT id, cinterno, estilo, precio_industrial FROM estilos
             WHERE estatus = 1 AND precio_industrial IS NOT NULL
             ORDER BY cinterno LIMIT $limite"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }
    $stmt = $db->prepare(
        "SELECT id, cinterno, estilo, precio_industrial FROM estilos
         WHERE estatus = 1 AND precio_industrial IS NOT NULL
           AND (cinterno ILIKE ? OR estilo ILIKE ?)
         ORDER BY cinterno LIMIT $limite"
    );
    $like = '%' . $q . '%';
    $stmt->execute([$like, $like]);
    return $stmt->fetchAll();
}

/**
 * Busca (o crea) la fila espejo en erp.usuarios para el vendedor foráneo
 * logueado en VISITAS, ligada por correo. rol='ventas' para que caiga en
 * TODO el mismo circuito que ya usa un vendedor interno (vendedoresCotizador(),
 * Kanban, Control de actividad) sin tocar ningún query del ERP. es_foraneo=true
 * solo para que Dirección pueda filtrarlos después si lo pide.
 *
 * Esta cuenta NUNCA inicia sesión: usuario/password aleatorios e inservibles,
 * mismo patrón que ya usa visitas/includes/auth.php::bridgeDesdeERP() en la
 * dirección contraria (Dirección entrando a VISITAS).
 */
function obtenerOCrearVendedorErp(string $email, string $nombreCompleto): int {
    $db = getDBErp();
    $stmt = $db->prepare('SELECT id FROM usuarios WHERE email = ?');
    $stmt->execute([$email]);
    $id = $stmt->fetchColumn();
    if ($id) return (int)$id;

    $partes     = preg_split('/\s+/', trim($nombreCompleto), 2);
    $nombre     = $partes[0] ?? $nombreCompleto;
    $apellidos  = $partes[1] ?? '-';

    $usuarioBase = strtolower(preg_replace('/[^a-z0-9]/i', '', explode('@', $email)[0])) ?: 'foraneo';
    $usuario     = $usuarioBase;
    $chk         = $db->prepare('SELECT 1 FROM usuarios WHERE usuario = ?');
    $sufijo      = 1;
    while (true) {
        $chk->execute([$usuario]);
        if (!$chk->fetchColumn()) break;
        $sufijo++;
        $usuario = $usuarioBase . $sufijo;
    }

    $hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    $ins = $db->prepare(
        "INSERT INTO usuarios (nombre, apellidos, email, usuario, password, rol, estatus, es_foraneo)
         VALUES (?,?,?,?,?, 'ventas', 1, true)"
    );
    $ins->execute([$nombre, $apellidos, $email, $usuario, $hash]);
    return (int)$db->lastInsertId();
}

/**
 * Refleja el cliente ligero de VISITAS como prospecto del Cotizador (o
 * reusa el que ya se creó antes para el mismo cliente de VISITAS) -- ver
 * cotizador_prospectos.visitas_cliente_id. Devuelve [id_prospecto, es_nuevo].
 *
 * Si ese cliente de VISITAS ya se convirtió en cliente formal del ERP, esto
 * no tiene forma de saberlo (VISITAS no ve esa conversión); en ese caso el
 * vendedor puede cotizarle seleccionando directamente al cliente real desde
 * el buscador del ERP -- fuera del alcance de esta primera versión.
 */
function obtenerOCrearProspectoErp(int $visitasClienteId, string $nombre, ?string $telefono, string $creadoPor): array {
    $db = getDBErp();
    $stmt = $db->prepare('SELECT id FROM cotizador_prospectos WHERE visitas_cliente_id = ? AND convertido_a_cliente_id IS NULL');
    $stmt->execute([$visitasClienteId]);
    $id = $stmt->fetchColumn();
    if ($id) return [(int)$id, false];

    $ins = $db->prepare(
        "INSERT INTO cotizador_prospectos (nombre, telefono, creado_por, visitas_cliente_id)
         VALUES (?,?,?,?)"
    );
    $ins->execute([$nombre, $telefono ?: null, $creadoPor, $visitasClienteId]);
    return [(int)$db->lastInsertId(), true];
}

/**
 * Registra una actividad de sistema en el ERP (actividades_seguimiento) --
 * mismo criterio que registrarActividadSeguimiento() en
 * erp/cotizacion/_helpers.php: solo hechos reales y verificables, nunca
 * autoreportados. Falla en silencio (no debe tumbar la creación de la
 * cotización por un problema de bitácora).
 */
function registrarActividadSeguimientoErp(int $idVendedorErp, string $tipo, ?int $idProspecto = null, ?int $idCotizacion = null): void {
    try {
        getDBErp()->prepare(
            "INSERT INTO actividades_seguimiento (id_usuario, tipo, origen, id_prospecto, id_cotizacion)
             VALUES (?,?, 'sistema', ?, ?)"
        )->execute([$idVendedorErp, $tipo, $idProspecto, $idCotizacion]);
    } catch (Throwable $e) {
        error_log('[VISITAS] registrarActividadSeguimientoErp: ' . $e->getMessage());
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Ver cotización / control de estado -- COPIADO de erp/cotizacion/_helpers.php
// (cotizacionEstadosCerrados, cotizacionVencida, etiquetaEstadoCotizacion,
// iconoEstadoCotizacion, transicionesPermitidas, transicionValida,
// generarTokenPublicoCotizacion, cambiarEstadoCotizacion). El vendedor
// foráneo puede ver su cotización y moverla de estado desde VISITAS; el link
// público que se genera aquí apunta al MISMO publica.php del ERP (no hay
// vista pública propia en VISITAS) porque ambos leen la misma fila de
// cotizaciones -- ver docs/arquitectura-cotizaciones-foraneos.md §7.
// ─────────────────────────────────────────────────────────────────────────────

function cotizacionEstadosCerradosErp(): array {
    return ['aceptada', 'rechazada', 'cancelada'];
}

/** Copia exacta de cotizacionVencida(). */
function cotizacionVencidaErp(array $cot): bool {
    if (in_array($cot['estado'], cotizacionEstadosCerradosErp(), true)) return false;
    $limite = strtotime($cot['created_at']) + ((int)$cot['vigencia_dias'] * 86400);
    return $limite < time();
}

function etiquetaEstadoCotizacionErp(string $estado): string {
    $map = [
        'pendiente'      => 'Pendiente',
        'enviada'        => 'Enviada',
        'en_negociacion' => 'En negociación',
        'aceptada'       => 'Aceptada',
        'rechazada'      => 'Rechazada',
        'cancelada'      => 'Cancelada',
    ];
    return $map[$estado] ?? ucfirst($estado);
}

function iconoEstadoCotizacionErp(string $estado): string {
    $map = [
        'enviada'        => 'bi-send',
        'en_negociacion' => 'bi-chat-dots',
        'aceptada'       => 'bi-check-circle',
        'rechazada'      => 'bi-x-circle',
        'cancelada'      => 'bi-slash-circle',
    ];
    return $map[$estado] ?? 'bi-arrow-right-circle';
}

/** Copia exacta de transicionesPermitidas() -- misma matriz que el ERP. */
function transicionesPermitidasErp(string $estadoActual): array {
    $matriz = [
        'pendiente'      => ['enviada', 'cancelada'],
        'enviada'        => ['en_negociacion', 'aceptada', 'rechazada', 'cancelada'],
        'en_negociacion' => ['aceptada', 'rechazada', 'cancelada'],
        'aceptada'       => ['cancelada'],
        'rechazada'      => [],
        'cancelada'      => [],
    ];
    return $matriz[$estadoActual] ?? [];
}

function transicionValidaErp(string $de, string $a): bool {
    return in_array($a, transicionesPermitidasErp($de), true);
}

/**
 * Genera (si no existe) el token del link público de la cotización -- mismo
 * campo (cotizaciones.token_publico) que usa erp/cotizacion/ver.php, así que
 * el link que arma urlPublicaCotizacionErp() es el link real del ERP: no
 * hay que duplicar publica.php en VISITAS.
 */
function generarTokenPublicoCotizacionErp(PDO $db, int $id): string {
    $stmt = $db->prepare('SELECT token_publico FROM cotizaciones WHERE id=?');
    $stmt->execute([$id]);
    $actual = $stmt->fetchColumn();
    if ($actual) return $actual;
    $token = bin2hex(random_bytes(24));
    $db->prepare('UPDATE cotizaciones SET token_publico=? WHERE id=?')->execute([$token, $id]);
    return $token;
}

/**
 * URL pública para que el cliente acepte/rechace sin login -- apunta al ERP
 * (erp/cotizacion/publica.php), no a VISITAS, porque esa página ya existe
 * ahí y lee directo por token de la misma tabla "cotizaciones". El ERP y
 * VISITAS corren en el mismo servidor/dominio (ver includes/auth.php ::
 * bridgeDesdeERP()), así que basta con cambiar "/visitas/" por "/erp/" en
 * el host actual.
 */
function urlPublicaCotizacionErp(string $token): string {
    $esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $esquema . '://' . $_SERVER['HTTP_HOST'] . '/erp/cotizacion/publica.php?t=' . urlencode($token);
}

/**
 * Aplica un cambio de estado validado, transaccional -- copia de
 * cambiarEstadoCotizacion() del ERP, fijado a origen='usuario' (siempre es
 * el vendedor foráneo quien lo dispara desde aquí; el cliente solo puede
 * responder desde el link público, que vive en el ERP y usa su propia
 * función). Devuelve ['ok'=>bool, 'msg'=>string].
 */
function cambiarEstadoCotizacionErp(PDO $db, array $cot, string $nuevoEstado, int $idUsuario): array {
    if (!transicionValidaErp($cot['estado'], $nuevoEstado)) {
        return ['ok' => false, 'msg' => 'Esa transición de estado no está permitida (la cotización ya cambió de estado por otro medio).'];
    }
    try {
        $db->beginTransaction();
        $db->prepare('UPDATE cotizaciones SET estado=?, id_usuario_estado=? WHERE id=?')
           ->execute([$nuevoEstado, $idUsuario, $cot['id']]);
        $db->prepare('INSERT INTO cotizacion_historial (id_cotizacion, estado_anterior, estado_nuevo, id_usuario, origen) VALUES (?,?,?,?,?)')
           ->execute([$cot['id'], $cot['estado'], $nuevoEstado, $idUsuario, 'usuario']);
        if ($nuevoEstado === 'enviada') {
            generarTokenPublicoCotizacionErp($db, (int)$cot['id']);
            registrarActividadSeguimientoErp($idUsuario, 'cotizacion_enviada', $cot['id_prospecto'] ?? null, (int)$cot['id']);
        }
        $db->commit();
        return ['ok' => true, 'msg' => 'Estado actualizado a "' . etiquetaEstadoCotizacionErp($nuevoEstado) . '"'];
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[VISITAS] cambiarEstadoCotizacionErp: ' . $e->getMessage());
        return ['ok' => false, 'msg' => 'No se pudo actualizar el estado.'];
    }
}
