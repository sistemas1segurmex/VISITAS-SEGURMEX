<?php
/**
 * Reglas de precio del Cotizador, COPIADAS de erp/cotizacion/_helpers.php
 * (precioBaseEstilo, aplicaMayoreo, calcularPrecioMinimo, folioCotizacion,
 * catalogoCotizable, resolverRenglonesCotizacion)
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

// ─────────────────────────────────────────────────────────────────────────────
// Catálogo cotizable y renglones -- COPIA de catalogoCotizable(),
// marcaSinDescuentos() y resolverRenglonesCotizacion() de
// erp/cotizacion/_helpers.php (PRs #455/#457/#458 del ERP, 5-oct-2026).
//
// Catálogo = los 53 modelos del cotizador anterior (legacy_cotizador_fb_modelos)
// + los Estilos reales del ERP con precio. Un modelo ya ligado a su estilo
// real (con precio) deja de salir. Llave de cada item: 'm:<id>' / 'e:<id>'.
//
// Reglas por marca:
//   * SEGURMEX (y todo Estilo real): mayoreo desde N pares SEGURMEX + pronto pago.
//   * DICKIES: precio fijo, sin descuentos; pedido mínimo de 16 pares (solo
//     se avisa, no bloquea -- config_cotizador.minimo_pares_dickies).
// Si el ERP cambia estas reglas, hay que replicarlas aquí y en
// vendedor/nueva_cotizacion.php (cálculo en pantalla).
// ─────────────────────────────────────────────────────────────────────────────

function catalogoCotizableErp(array $cfg): array {
    $db = getDBErp();
    $items = [];
    foreach ($db->query("
        SELECT m.id, m.modelo, m.marca, m.linea_nombre, m.suela, m.corte, m.color, m.colores,
               m.precio_industria, m.precio_distribuidor, m.foto, m.entrega_dias, m.atributo
        FROM legacy_cotizador_fb_modelos m
        WHERE m.activo
          AND NOT EXISTS (SELECT 1 FROM estilos e
                          WHERE e.id = m.id_estilo AND e.estatus = 1 AND e.precio_industrial IS NOT NULL)
        ORDER BY m.orden, m.modelo
    ")->fetchAll(PDO::FETCH_ASSOC) as $m) {
        $colores = json_decode((string)$m['colores'], true) ?: [];
        if (!$colores && $m['color'] !== null) $colores = [$m['color']];
        $items['m:' . (int)$m['id']] = [
            'item' => 'm:' . (int)$m['id'], 'id_estilo' => null, 'id_modelo_legacy' => (int)$m['id'],
            'clave' => $m['modelo'], 'marca' => $m['marca'], 'grupo' => $m['marca'] . ' · ' . ($m['linea_nombre'] ?: ''),
            'nombre' => mb_substr(trim(($m['linea_nombre'] ?: $m['marca']) . ' — ' . ($m['corte'] ?: '')), 0, 150),
            'suela' => $m['suela'], 'colores' => array_values($colores), 'foto' => $m['foto'],
            'precio_industria' => (float)$m['precio_industria'], 'precio_distribuidor' => (float)$m['precio_distribuidor'],
            'entrega_dias' => $m['entrega_dias'] !== null ? (int)$m['entrega_dias'] : null,
            'atributo' => ($m['atributo'] ?? '') !== '' ? $m['atributo'] : null,
        ];
    }
    foreach ($db->query("
        SELECT id, cinterno, estilo, precio_industrial
        FROM estilos
        WHERE estatus = 1 AND precio_industrial IS NOT NULL
        ORDER BY cinterno
    ")->fetchAll(PDO::FETCH_ASSOC) as $e) {
        $items['e:' . (int)$e['id']] = [
            'item' => 'e:' . (int)$e['id'], 'id_estilo' => (int)$e['id'], 'id_modelo_legacy' => null,
            'clave' => $e['cinterno'], 'marca' => 'SEGURMEX', 'grupo' => 'Estilos del ERP',
            'nombre' => $e['estilo'], 'suela' => null, 'colores' => [], 'foto' => null, 'entrega_dias' => null, 'atributo' => null,
            'precio_industria' => precioBaseEstiloErp($e, 'industria', $cfg),
            'precio_distribuidor' => precioBaseEstiloErp($e, 'distribuidor', $cfg),
        ];
    }
    return $items;
}

function marcaSinDescuentosErp(string $marca): bool {
    return $marca === 'DICKIES';
}

/**
 * Valida los renglones que manda nueva_cotizacion.php (JSON: item, cantidad,
 * precio_final, color) contra el catálogo y calcula lista, mínimo e importe.
 * Misma lógica que resolverRenglonesCotizacion() del ERP; solo cambia la
 * forma de la entrada (allá llegan det_item[], det_cantidad[]... del form).
 *
 * El mayoreo lo decide la suma de pares SEGURMEX (los Dickies no cuentan ni
 * reciben descuento). total_pares sigue siendo TODOS los pares.
 */
function resolverRenglonesCotizacionErp(array $catalogo, array $renglones, string $tipoLista, bool $prontoPago, array $cfg): array {
    $totalPares = 0; $paresSegurmex = 0; $paresDickies = 0;
    foreach ($renglones as $r) {
        $cant = max(0, (int)($r['cantidad'] ?? 0));
        $item = $catalogo[(string)($r['item'] ?? '')] ?? null;
        $totalPares += $cant;
        if ($item && marcaSinDescuentosErp($item['marca'])) $paresDickies += $cant; else $paresSegurmex += $cant;
    }
    $mayoreo = aplicaMayoreoErp($paresSegurmex, $cfg);

    $lineas = []; $errores = []; $subtotal = 0.0;
    foreach ($renglones as $i => $r) {
        $llave    = (string)($r['item'] ?? '');
        $cantidad = (int)($r['cantidad'] ?? 0);
        $precio   = max(0, (float)($r['precio_final'] ?? 0));
        $color    = trim((string)($r['color'] ?? ''));
        if ($llave === '' || $cantidad <= 0) continue;

        $item = $catalogo[$llave] ?? null;
        if (!$item) { $errores[] = 'El modelo del renglón #' . ($i + 1) . ' ya no está disponible'; continue; }

        if (count($item['colores']) > 1) {
            if (!in_array($color, $item['colores'], true)) { $errores[] = "{$item['clave']}: elige el color"; $color = null; }
        } else {
            $color = $item['colores'][0] ?? null;
        }

        $base   = $tipoLista === 'distribuidor' ? $item['precio_distribuidor'] : $item['precio_industria'];
        $minimo = marcaSinDescuentosErp($item['marca']) ? $base : calcularPrecioMinimoErp($base, $mayoreo, $prontoPago, $cfg);
        if ($precio < $minimo) {
            $errores[] = "{$item['clave']}: el precio (" . money($precio) . ") está por debajo del mínimo permitido (" . money($minimo) . ")";
        }
        $importe = round($cantidad * $precio, 2);
        $subtotal += $importe;
        $lineas[] = [
            'id_estilo' => $item['id_estilo'], 'id_modelo_legacy' => $item['id_modelo_legacy'],
            'clave_estilo' => $item['clave'], 'nombre_estilo' => $item['nombre'], 'color' => $color,
            'cantidad' => $cantidad, 'precio_lista' => $base, 'precio_minimo' => $minimo,
            'precio_final' => $precio, 'importe' => $importe,
            'entrega_dias' => $item['entrega_dias'] ?? null,  // solo informativo, no se guarda en cotizacion_detalle
            'atributo'     => $item['atributo'] ?? null,      // idem (PP, PP+D…)
        ];
    }
    if (!$lineas && !$errores) $errores[] = 'Agrega al menos un modelo con cantidad';

    return [
        'lineas' => $lineas, 'errores' => $errores,
        'total_pares' => $totalPares, 'pares_dickies' => $paresDickies,
        'aplica_mayoreo' => $mayoreo, 'subtotal' => round($subtotal, 2),
        'entrega' => entregaRequeridaDeLineasErp($lineas),
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// Tiempo de entrega por modelo -- COPIA de diasDeTiempoEntrega(),
// entregaRequeridaDeLineas() y avisoTiempoEntrega() de erp/cotizacion/_helpers.php.
// Algunos modelos (hoy los Dickies) traen su propio plazo en días hábiles
// (legacy_cotizador_fb_modelos.entrega_dias: 30 o 75). Solo se muestra y se
// AVISA (no bloquea) si el tiempo de entrega capturado es menor.
// ─────────────────────────────────────────────────────────────────────────────

/** Días que expresa un texto de tiempo de entrega ("15 días hábiles" → 15); null si no trae días. */
function diasDeTiempoEntregaErp(?string $texto): ?int {
    if ($texto === null) return null;
    if (preg_match('/(\d+)\s*d[ií]as?/iu', $texto, $m)) return (int)$m[1];
    return null;
}

/** El plazo más largo entre los renglones y qué modelos lo piden: ['dias' => int|null, 'modelos' => string[]]. */
function entregaRequeridaDeLineasErp(array $lineas): array {
    $max = null; $modelos = [];
    foreach ($lineas as $l) {
        $d = isset($l['entrega_dias']) && $l['entrega_dias'] !== null ? (int)$l['entrega_dias'] : null;
        if ($d === null || $d <= 0) continue;
        if ($max === null || $d > $max) { $max = $d; $modelos = []; }
        if ($d === $max && !in_array($l['clave_estilo'], $modelos, true)) $modelos[] = $l['clave_estilo'];
    }
    return ['dias' => $max, 'modelos' => $modelos];
}

/** Texto de aviso si el tiempo de entrega es menor al del modelo más tardado; null si no hay que avisar. */
function avisoTiempoEntregaErp(?string $tiempoEntrega, array $entrega): ?string {
    if (!$entrega['dias']) return null;
    $dias = diasDeTiempoEntregaErp($tiempoEntrega);
    if ($dias === null || $dias >= $entrega['dias']) return null;
    return implode(', ', $entrega['modelos']) . ' se entrega' . (count($entrega['modelos']) > 1 ? 'n' : '')
         . ' en ' . $entrega['dias'] . ' días hábiles, y el tiempo de entrega de la cotización dice "' . $tiempoEntrega . '".';
}

/**
 * Agrega a cada renglón guardado (cotizacion_detalle) la foto, el plazo de
 * entrega y el atributo de seguridad del modelo del catálogo anterior. Los
 * Estilos del ERP no traen ninguno. Si algo falla, los deja en null.
 */
function agregarFotoYEntregaDetalleErp(PDO $dbErp, array $detalle): array {
    $info = [];
    try {
        $ids = array_values(array_unique(array_filter(array_map(fn($d) => (int)($d['id_modelo_legacy'] ?? 0), $detalle))));
        if ($ids) {
            $marcas = implode(',', array_fill(0, count($ids), '?'));
            $st = $dbErp->prepare("SELECT id, foto, entrega_dias, atributo FROM legacy_cotizador_fb_modelos WHERE id IN ($marcas)");
            $st->execute($ids);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $info[(int)$r['id']] = $r;
        }
    } catch (Throwable $e) {
        error_log('[VISITAS] agregarFotoYEntregaDetalleErp: ' . $e->getMessage());
    }
    foreach ($detalle as &$d) {
        $m = $info[(int)($d['id_modelo_legacy'] ?? 0)] ?? null;
        $d['foto'] = $m['foto'] ?? null;
        $d['entrega_dias'] = isset($m['entrega_dias']) && $m['entrega_dias'] !== null ? (int)$m['entrega_dias'] : null;
        $d['atributo'] = ($m['atributo'] ?? '') !== '' ? $m['atributo'] : null;
    }
    unset($d);
    return $detalle;
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
 * Link al PDF de la cotización (cotizacion_pdf.php, en VISITAS) -- es lo que
 * el vendedor le manda al cliente por WhatsApp. Usa el mismo token_publico
 * que el link del ERP; dentro del PDF va el botón para responder en línea.
 */
function urlPdfCotizacionVisitas(string $token): string {
    $esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $esquema . '://' . $_SERVER['HTTP_HOST'] . '/visitas/cotizacion_pdf.php?t=' . urlencode($token);
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

// ─────────────────────────────────────────────────────────────────────────────
// Atributo de seguridad -- COPIA de atributosSeguridad(), significadoAtributo()
// y leyendaAtributosDeLineas() de erp/cotizacion/_helpers.php.
// ─────────────────────────────────────────────────────────────────────────────

/** Significado de cada atributo de seguridad (legacy_cotizador_fb_modelos.atributo). */
function atributosSeguridadErp(): array {
    return [
        'PP'      => 'Puntera de protección',
        'PP+D'    => 'Puntera + Dieléctrico',
        'PP+A'    => 'Puntera + Antiestático',
        'O'       => 'Ocupacional (sin puntera)',
        'D+PP+PM' => 'Dieléctrico + Puntera + Protector metatarsal',
    ];
}

/** Leyenda (código => significado) de los atributos presentes en los renglones, en orden fijo. */
function leyendaAtributosDeLineasErp(array $lineas): array {
    $presentes = [];
    foreach ($lineas as $l) {
        $a = $l['atributo'] ?? null;
        if ($a !== null && $a !== '') $presentes[$a] = true;
    }
    $leyenda = [];
    foreach (atributosSeguridadErp() as $cod => $sig) if (isset($presentes[$cod])) $leyenda[$cod] = $sig;
    foreach (array_keys($presentes) as $cod) if (!isset($leyenda[$cod])) $leyenda[$cod] = $cod;
    return $leyenda;
}
