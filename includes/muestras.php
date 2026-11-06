<?php
/**
 * Solicitudes de muestra de los vendedores externos -- etapa "solo aviso"
 * (07-oct-2026). Ver supabase/migrations/20261007120000_muestras_solo_aviso.sql.
 *
 * La solicitud se queda en Visitas (ya NO se manda al ERP): el vendedor la
 * pide, se le avisa a la responsable de muestras (rol 'muestras'), ella la
 * surte por fuera y marca el avance. Cada evento genera un aviso dentro del
 * sistema (campanita, tabla avisos) y un correo:
 *   - nueva solicitud            -> responsable(s) de muestras
 *   - en preparación / embarcada / cancelada -> el vendedor que la pidió
 * El admin solo consulta (muestras/index.php y muestras/ver.php en modo
 * lectura) y no recibe avisos.
 */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/mailer.php';

// Tope de PARES de muestra por cliente por mes calendario (hora de CDMX),
// sumando todas sus solicitudes. Las canceladas no cuentan. (22-oct-2026:
// antes eran 5 solicitudes por vendedor al mes; se quitó.)
const MUESTRAS_TOPE_PARES_CLIENTE_MES = 6;

// Máximo de estilos y de tallas por estilo en una solicitud (solo para que
// el formulario no crezca sin fin; el tope real es el de pares).
const MUESTRAS_MAX_ESTILOS = 6;
const MUESTRAS_MAX_TALLAS_POR_ESTILO = 6;

const MUESTRA_ESTADOS = [
    'enviada'        => 'Enviada',
    'en_preparacion' => 'En preparación',
    'embarcada'      => 'Embarcada',
    'cancelada'      => 'Cancelada',
];

// A dónde puede pasar cada estado. embarcada y cancelada cierran.
const MUESTRA_TRANSICIONES = [
    'enviada'        => ['en_preparacion', 'embarcada', 'cancelada'],
    'en_preparacion' => ['embarcada', 'cancelada'],
    'embarcada'      => [],
    'cancelada'      => [],
];

// Detalle de "En preparación" (columna preparacion): de dónde sale la muestra.
// 'por_programar' puede pasar después a 'pt'.
const MUESTRA_PREPARACION = [
    'pt'            => 'En Producto Terminado',
    'por_programar' => 'Por programar',
];

const MUESTRA_CATEGORIAS_CAMBIO = ['casco' => 'Casco', 'suela' => 'Suela', 'piel' => 'Piel', 'forro' => 'Forro'];

// Peticiones del vendedor (06-nov-2026): una vez enviada la solicitud ya no la
// edita; le pide el cambio a la responsable y ella decide. Ver pedirCambioMuestra().
const MUESTRA_PETICIONES = ['correccion' => 'Corrección', 'cancelacion' => 'Cancelación'];

/**
 * Catálogo para el formulario de muestra: el MISMO que ofrece la Nueva
 * cotización (catalogoCotizableErp() en includes/cotizador_helpers.php) --
 * modelos del cotizador anterior ("m:ID") más estilos del ERP con precio
 * ("e:ID"). Así el vendedor pide muestra de lo mismo que cotiza.
 * $item: si se pasa ("m:12" / "e:34"), regresa solo ese, o null si ya no
 * está en el catálogo.
 */
function catalogoParaMuestraErp(?string $item = null): ?array {
    require_once __DIR__ . '/cotizador_helpers.php';
    $lista = [];
    $tallas = tallasCatalogoMuestraErp();
    foreach (catalogoCotizableErp(configCotizadorErp()) as $it) {
        $nombre = trim($it['clave'] . ' — ' . $it['nombre']);
        if ($it['marca'] && $it['marca'] !== 'SEGURMEX') $nombre .= ' (' . $it['marca'] . ')';
        $lista[$it['item']] = [
            'id'               => $it['item'],
            'nombre'           => $nombre,
            'id_estilo'        => $it['id_estilo'],
            'id_modelo_legacy' => $it['id_modelo_legacy'],
            // Para el selector visual (mismo diseño que la Nueva cotización).
            // Sin precios: en una muestra no aplican.
            'clave'            => $it['clave'],
            'descripcion'      => $it['nombre'],
            'marca'            => $it['marca'],
            'grupo'            => $it['grupo'],
            'foto'             => $it['foto'],
            'atributo'         => $it['atributo'],
            'colores'          => $it['colores'],
            // Tallas a elegir: las de la numeración del estilo del ERP (o
            // del estilo al que está ligado el modelo); si no tiene, la
            // lista fija de 20 a 35.
            'tallas'           => $tallas[$it['item']] ?? tallasMuestraFijas(),
            'tallas_del_estilo' => isset($tallas[$it['item']]),
        ];
    }
    if ($item !== null) return $lista[$item] ?? null;
    return array_values($lista);
}

/** Lista fija de tallas (20 a 35 en medios puntos) para los modelos sin numeración. */
function tallasMuestraFijas(): array {
    $t = [];
    for ($x = 20.0; $x <= 35.0; $x += 0.5) $t[] = textoTallaMuestra($x);
    return $t;
}

/** 27.0 -> "27", 27.5 -> "27.5". */
function textoTallaMuestra($talla): string {
    $f = (float)$talla;
    return floor($f) == $f ? (string)(int)$f : rtrim(rtrim(number_format($f, 1, '.', ''), '0'), '.');
}

/**
 * Tallas de la numeración de cada item del catálogo ("e:ID" o "m:ID" ligado
 * a un estilo), leídas del ERP (estilos.id_numeracion -> numeracion_tallas,
 * igual que pedidos/ajax.php?accion=tallas del ERP). Si falla, [] y se usa
 * la lista fija.
 */
function tallasCatalogoMuestraErp(): array {
    $res = [];
    try {
        $db = getDBErp();
        $rows = $db->query("
            SELECT 'e:' || e.id AS item, nt.talla
            FROM estilos e
            JOIN numeracion_tallas nt ON nt.id_numeracion = e.id_numeracion AND nt.estatus = 1
            WHERE e.estatus = 1
            UNION
            SELECT 'm:' || m.id AS item, nt.talla
            FROM legacy_cotizador_fb_modelos m
            JOIN estilos e ON e.id = m.id_estilo
            JOIN numeracion_tallas nt ON nt.id_numeracion = e.id_numeracion AND nt.estatus = 1
            WHERE m.activo
            ORDER BY 1, 2
        ")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $t = textoTallaMuestra($r['talla']);
            if (!in_array($t, $res[$r['item']] ?? [], true)) $res[$r['item']][] = $t;
        }
    } catch (Throwable $e) {
        error_log('[VISITAS] tallasCatalogoMuestraErp: ' . $e->getMessage());
    }
    return $res;
}

/** "Al cliente" / "Al vendedor (Nombre)" -- a quién se entrega la muestra. */
function textoEntregarAMuestra(array $s): string {
    if (($s['entregar_a'] ?? 'cliente') === 'vendedor') {
        return 'Al vendedor' . (!empty($s['vendedor_nombre']) ? ' (' . $s['vendedor_nombre'] . ')' : '');
    }
    return 'Al cliente' . (!empty($s['cliente_nombre']) ? ' (' . $s['cliente_nombre'] . ')' : '');
}

/** "2 pares" / "1 par". */
function textoParesMuestra($n): string {
    $n = max(1, (int)$n);
    return $n . ($n === 1 ? ' par' : ' pares');
}

/** "15 días" o null si no se indicó. */
function textoTiempoPruebaMuestra($dias): ?string {
    if ($dias === null || $dias === '') return null;
    $d = (int)$dias;
    return $d . ($d === 1 ? ' día' : ' días');
}

/** Link al PDF de la solicitud (muestra_pdf.php, pide sesión). $base: '../' desde subcarpetas. */
function urlPdfMuestra(int $id, string $base = ''): string {
    return $base . 'muestra_pdf.php?id=' . $id;
}

/** "En Producto Terminado" / "Por programar · fecha estimada 20/10/2026", o null. */
function textoPreparacionMuestra(array $s): ?string {
    $p = $s['preparacion'] ?? null;
    if (!$p || !isset(MUESTRA_PREPARACION[$p])) return null;
    $txt = MUESTRA_PREPARACION[$p];
    if ($p === 'por_programar' && !empty($s['fecha_estimada_pt'])) {
        $txt .= ' · fecha estimada ' . date('d/m/Y', strtotime($s['fecha_estimada_pt']));
    }
    return $txt;
}

function etiquetaEstadoMuestra(string $estado): string {
    return MUESTRA_ESTADOS[$estado] ?? $estado;
}

/**
 * URL absoluta de Visitas (para los links de los correos). VISITAS_URL en
 * .env manda si existe; si no, se arma con el host de la petición actual.
 */
function urlBaseVisitas(): string {
    $env = envConfig('VISITAS_URL');
    if ($env) return rtrim($env, '/') . '/';
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'sgmx.com.mx';
    return ($https ? 'https' : 'http') . '://' . $host . '/visitas/';
}

/** Expresión SQL (UTC, sin zona) del inicio del mes actual en hora de CDMX. */
function sqlInicioMesMx(): string {
    return "((date_trunc('month', now() AT TIME ZONE 'America/Mexico_City') AT TIME ZONE 'America/Mexico_City') AT TIME ZONE 'UTC')";
}

/**
 * Pares de muestra que ya se pidieron este mes para un cliente (todas sus
 * solicitudes menos las canceladas) -- cuentan para
 * MUESTRAS_TOPE_PARES_CLIENTE_MES. muestras_solicitudes.cantidad guarda el
 * total de pares de la solicitud.
 */
function paresMuestraDelMesCliente(PDO $db, int $clienteId): int {
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(cantidad), 0) FROM muestras_solicitudes
         WHERE cliente_id = ? AND estado <> 'cancelada' AND created_at >= " . sqlInicioMesMx()
    );
    $stmt->execute([$clienteId]);
    return (int)$stmt->fetchColumn();
}

/** [cliente_id => pares usados este mes] de los clientes de un vendedor. */
function paresMuestraDelMesPorCliente(PDO $db, int $vendedorId): array {
    $stmt = $db->prepare(
        "SELECT cliente_id, COALESCE(SUM(cantidad), 0) AS pares FROM muestras_solicitudes
         WHERE cliente_id IN (SELECT id FROM clientes WHERE vendedor_id = ?)
           AND estado <> 'cancelada' AND created_at >= " . sqlInicioMesMx() . "
         GROUP BY cliente_id"
    );
    $stmt->execute([$vendedorId]);
    $res = [];
    foreach ($stmt->fetchAll() as $r) $res[(int)$r['cliente_id']] = (int)$r['pares'];
    return $res;
}

/**
 * Estilos (con sus tallas) de varias solicitudes: [solicitud_id => [estilo, ...]].
 * Cada estilo: estilo_nombre, color, tipo, cambios[], tallas[{talla, pares,
 * nota}], pares (total del estilo).
 */
function estilosDeSolicitudesMuestra(PDO $db, array $ids): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) return [];
    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare(
        "SELECT e.id, e.solicitud_id, e.id_estilo_erp, e.id_modelo_legacy, e.estilo_nombre, e.color, e.tipo, e.cambios,
                t.talla, t.pares, t.nota
         FROM muestras_solicitudes_estilos e
         LEFT JOIN muestras_solicitudes_tallas t ON t.estilo_id = e.id
         WHERE e.solicitud_id IN ($marcas)
         ORDER BY e.solicitud_id, e.orden, e.id, t.orden, t.id"
    );
    $stmt->execute($ids);
    $res = [];
    foreach ($stmt->fetchAll() as $r) {
        $sid = (int)$r['solicitud_id'];
        $eid = (int)$r['id'];
        if (!isset($res[$sid][$eid])) {
            $res[$sid][$eid] = [
                'id_estilo_erp'    => $r['id_estilo_erp'] !== null ? (int)$r['id_estilo_erp'] : null,
                'id_modelo_legacy' => $r['id_modelo_legacy'] !== null ? (int)$r['id_modelo_legacy'] : null,
                'estilo_nombre'    => $r['estilo_nombre'],
                'color'            => $r['color'],
                'tipo'             => $r['tipo'],
                'cambios'          => json_decode($r['cambios'] ?? '[]', true) ?: [],
                'tallas'           => [],
                'pares'            => 0,
            ];
        }
        if ($r['pares'] !== null) {
            $res[$sid][$eid]['tallas'][] = ['talla' => $r['talla'], 'pares' => (int)$r['pares'], 'nota' => $r['nota']];
            $res[$sid][$eid]['pares'] += (int)$r['pares'];
        }
    }
    return array_map('array_values', $res);
}

/**
 * Los estilos de una solicitud ya cargada; si no tiene renglones (no
 * debería: la migración 20261022120000 los creó para las de antes), se arma
 * uno con las columnas de la solicitud.
 */
function estilosSolicitudMuestraConRespaldo(array $s, ?array $estilos): array {
    if ($estilos) return $estilos;
    $cambios = is_array($s['cambios'] ?? null) ? $s['cambios'] : (json_decode($s['cambios'] ?? '[]', true) ?: []);
    $pares = max(1, (int)($s['cantidad'] ?? 1));
    return [[
        'id_estilo_erp' => isset($s['id_estilo_erp']) ? (int)$s['id_estilo_erp'] : null,
        'id_modelo_legacy' => isset($s['id_modelo_legacy']) ? (int)$s['id_modelo_legacy'] : null,
        'estilo_nombre' => $s['estilo_nombre'], 'color' => $s['color'] ?? null, 'tipo' => $s['tipo'] ?? 'identico',
        'cambios' => $cambios, 'tallas' => [['talla' => $s['talla'] ?? null, 'pares' => $pares, 'nota' => null]], 'pares' => $pares,
    ]];
}

/** Agrega 'estilos' a cada fila de muestras_solicitudes (listas y bandeja). */
function adjuntarEstilosSolicitudesMuestra(PDO $db, array $filas): array {
    $est = estilosDeSolicitudesMuestra($db, array_column($filas, 'id'));
    foreach ($filas as &$f) $f['estilos'] = estilosSolicitudMuestraConRespaldo($f, $est[(int)$f['id']] ?? null);
    unset($f);
    return $filas;
}

/** "27 · 1 par, 24 · 2 pares" -- las tallas de un estilo en una línea. */
function textoTallasEstiloMuestra(array $estilo): string {
    $partes = [];
    foreach ($estilo['tallas'] as $t) {
        $partes[] = ($t['talla'] !== null && $t['talla'] !== '' ? 'Talla ' . $t['talla'] . ': ' : '') . textoParesMuestra($t['pares'])
                  . (!empty($t['nota']) ? ' (' . $t['nota'] . ')' : '');
    }
    return implode(', ', $partes);
}

/**
 * Todos los estilos de una solicitud en texto (correos), uno por línea:
 * "5019 — ... · Negro · Variante: Talla 27: 1 par, Talla 24: 1 par".
 */
function textoEstilosMuestra(array $s): string {
    $lineas = [];
    foreach ($s['estilos'] ?? [] as $e) {
        $cab = $e['estilo_nombre'] . (!empty($e['color']) ? ' · ' . $e['color'] : '')
             . ($e['tipo'] === 'variante' ? ' · Variante' : '');
        $lineas[] = $cab . ': ' . textoTallasEstiloMuestra($e);
        $cambios = textoCambiosMuestra($e['cambios'] ?? []);
        if ($cambios) $lineas[] = '   Cambios: ' . str_replace("\n", '; ', $cambios);
    }
    return implode("\n", $lineas);
}

/** Total de pares de una solicitud cargada. */
function totalParesSolicitudMuestra(array $s): int {
    $n = 0;
    foreach ($s['estilos'] ?? [] as $e) $n += (int)$e['pares'];
    return $n ?: max(1, (int)($s['cantidad'] ?? 1));
}

/** Usuarios activos con rol 'muestras' (normalmente una sola persona). */
function responsablesMuestras(PDO $db): array {
    return $db->query("SELECT id, nombre, email FROM usuarios WHERE rol = 'muestras' AND activo = 1 ORDER BY nombre")->fetchAll();
}

/** Inserta un aviso para la campanita. Nunca truena el flujo que lo llama. */
function crearAviso(PDO $db, int $usuarioId, string $tipo, string $titulo, string $mensaje, ?string $enlace = null, ?int $solicitudId = null): void {
    try {
        $db->prepare(
            'INSERT INTO avisos (usuario_id, tipo, titulo, mensaje, enlace, solicitud_muestra_id) VALUES (?,?,?,?,?,?)'
        )->execute([$usuarioId, $tipo, mb_substr($titulo, 0, 150), mb_substr($mensaje, 0, 500), $enlace, $solicitudId]);
    } catch (Throwable $e) {
        error_log('[VISITAS] crearAviso: ' . $e->getMessage());
    }
}

/**
 * Manda el correo solo si el SMTP está configurado (MAIL_USER/MAIL_PASS en
 * .env); si no, lo deja en el log y sigue -- el aviso de la campanita ya
 * quedó guardado de todos modos.
 */
function enviarCorreoMuestra(string $destino, string $asunto, string $html): bool {
    if (!envConfig('MAIL_USER') || !envConfig('MAIL_PASS')) {
        error_log('[VISITAS] Correo de muestras no enviado (falta MAIL_USER/MAIL_PASS en .env): ' . $asunto);
        return false;
    }
    return enviarCorreo($destino, $asunto, $html);
}

/** Una solicitud con los datos del vendedor que la pidió. */
function cargarSolicitudMuestra(PDO $db, int $id): ?array {
    $stmt = $db->prepare(
        "SELECT s.*, u.nombre AS vendedor_nombre, u.email AS vendedor_email, u.telefono AS vendedor_telefono,
                -- Contacto: el que se copió al pedirla; las de antes, el de la ficha.
                COALESCE(s.contacto_nombre, c.nombre_contacto) AS cliente_contacto,
                COALESCE(s.contacto_telefono, c.telefono) AS cliente_telefono
         FROM muestras_solicitudes s
         JOIN usuarios u ON u.id = s.vendedor_id
         LEFT JOIN clientes c ON c.id = s.cliente_id
         WHERE s.id = ?"
    );
    $stmt->execute([$id]);
    $s = $stmt->fetch();
    if (!$s) return null;
    $s['cambios'] = json_decode($s['cambios'] ?? '[]', true) ?: [];
    $s['estilos'] = estilosSolicitudMuestraConRespaldo($s, estilosDeSolicitudesMuestra($db, [$id])[$id] ?? null);
    return $s;
}

function historialSolicitudMuestra(PDO $db, int $id): array {
    $stmt = $db->prepare(
        "SELECT h.estado, h.nota, h.creado_en, u.nombre AS usuario_nombre
         FROM muestras_solicitudes_historial h
         LEFT JOIN usuarios u ON u.id = h.usuario_id
         WHERE h.solicitud_id = ? ORDER BY h.creado_en, h.id"
    );
    $stmt->execute([$id]);
    return $stmt->fetchAll();
}

/**
 * Texto corto de cómo se envió: "Por paquetería" (las nuevas: el link de
 * rastreo va aparte, en guia_url) / "Entregada en persona". Las embarcadas
 * antes del 09-oct-2026 traen paquetería y número de guía: "DHL, guía 123".
 */
function textoEnvioMuestra(array $s): string {
    if (($s['envio_modo'] ?? '') === 'en_persona') return 'Entregada en persona';
    if (!empty($s['guia_url'])) return 'Por paquetería';
    $partes = [];
    if (!empty($s['paqueteria'])) $partes[] = $s['paqueteria'];
    if (!empty($s['guia']))       $partes[] = 'guía ' . $s['guia'];
    return $partes ? implode(', ', $partes) : 'Por paquetería';
}

/**
 * Cuerpo HTML de los correos de muestras -- sencillo a propósito (los
 * leen en el celular): título, renglones clave y un botón al sistema.
 */
function correoMuestraHtml(string $titulo, string $intro, array $renglones, string $enlace, string $textoBoton): string {
    $filas = '';
    foreach ($renglones as $etiqueta => $valor) {
        if ($valor === null || $valor === '') continue;
        $filas .= '<tr><td style="padding:4px 12px 4px 0;color:#6B7280;white-space:nowrap;vertical-align:top">'
                . htmlspecialchars($etiqueta) . '</td><td style="padding:4px 0;color:#14171F;font-weight:600">'
                . nl2br(htmlspecialchars((string)$valor)) . '</td></tr>';
    }
    return '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#14171F;max-width:520px">'
         . '<h2 style="font-size:18px;margin:0 0 8px">' . htmlspecialchars($titulo) . '</h2>'
         . '<p style="margin:0 0 14px;color:#374151">' . htmlspecialchars($intro) . '</p>'
         . '<table style="border-collapse:collapse;margin:0 0 18px">' . $filas . '</table>'
         . '<p style="margin:0 0 18px"><a href="' . htmlspecialchars($enlace) . '" style="display:inline-block;background:#E8A400;color:#fff;text-decoration:none;font-weight:700;padding:10px 18px;border-radius:999px">'
         . htmlspecialchars($textoBoton) . '</a></p>'
         . '<p style="margin:0;color:#9CA3AF;font-size:12px">Control de Visitas — Segurmex. Este correo se generó automáticamente.</p>'
         . '</div>';
}

/** Aviso + correo a la(s) responsable(s) de muestras por una solicitud nueva. */
function notificarNuevaSolicitudMuestra(PDO $db, array $s): void {
    $responsables = responsablesMuestras($db);
    if (!$responsables) {
        error_log('[VISITAS] Solicitud de muestra ' . $s['folio'] . ' sin responsable de muestras activa a quien avisar.');
        return;
    }
    $titulo  = 'Nueva solicitud de muestra ' . $s['folio'];
    $nEst    = count($s['estilos']);
    $mensaje = $s['vendedor_nombre'] . ' pide ' . textoParesMuestra(totalParesSolicitudMuestra($s))
             . ($nEst > 1 ? ' de ' . $nEst . ' estilos' : ' de ' . $s['estilos'][0]['estilo_nombre'])
             . ' para ' . $s['cliente_nombre'] . '.';
    $enlaceRel = 'muestras/ver.php?id=' . (int)$s['id'];
    $cuerpo = correoMuestraHtml(
        $titulo,
        $s['vendedor_nombre'] . ' acaba de pedir una muestra. Revísala y marca el avance en el sistema.',
        [
            'Vendedor'        => $s['vendedor_nombre'] . ($s['vendedor_telefono'] ? ' · ' . $s['vendedor_telefono'] : ''),
            'Cliente'         => $s['cliente_nombre'],
            'Contacto'        => trim(($s['cliente_contacto'] ?? '') . (!empty($s['cliente_telefono']) ? ' · ' . $s['cliente_telefono'] : '')),
            'Muestras'        => textoEstilosMuestra($s),
            'Total'           => textoParesMuestra(totalParesSolicitudMuestra($s)),
            'Motivo'          => $s['motivo'] ?? null,
            'Notas para planta' => $s['notas_planta'] ?? null,
            'Fecha promesa'   => $s['fecha_promesa'] ? date('d/m/Y', strtotime($s['fecha_promesa'])) : null,
            'Tiempo de prueba' => textoTiempoPruebaMuestra($s['tiempo_prueba_dias'] ?? null),
            'Entregar a'      => textoEntregarAMuestra($s),
            'Dirección'       => $s['destino_direccion'],
        ],
        urlBaseVisitas() . $enlaceRel,
        'Abrir solicitud'
    );
    foreach ($responsables as $r) {
        crearAviso($db, (int)$r['id'], 'muestra_nueva', $titulo, $mensaje, $enlaceRel, (int)$s['id']);
        if (!empty($r['email'])) enviarCorreoMuestra($r['email'], $titulo, $cuerpo);
    }
}

/**
 * Aviso + correo al vendedor cuando su solicitud cambia de estado.
 * $yaEnPt: true cuando una "por programar" pasa a Producto Terminado.
 */
function notificarCambioEstadoMuestra(PDO $db, array $s, bool $yaEnPt = false): void {
    $folio = $s['folio'];
    $que   = $s['estilo_nombre'] . ' para ' . $s['cliente_nombre'];
    switch ($s['estado']) {
        case 'en_preparacion':
            if ($yaEnPt) {
                $titulo  = "Tu muestra $folio ya está lista en almacén";
                $mensaje = "Ya se fabricó tu muestra de $que y está en Producto Terminado. Se está preparando el envío.";
            } elseif (($s['preparacion'] ?? '') === 'por_programar') {
                $titulo  = "Tu muestra $folio se va a fabricar";
                $mensaje = "Tu muestra de $que no está en almacén: se va a programar para fabricarla."
                         . (!empty($s['fecha_estimada_pt']) ? ' Fecha estimada para tenerla lista: ' . date('d/m/Y', strtotime($s['fecha_estimada_pt'])) . '.' : '');
            } elseif (($s['preparacion'] ?? '') === 'pt') {
                $titulo  = "Tu muestra $folio está en preparación";
                $mensaje = "Ya hay en almacén (Producto Terminado): se está preparando el envío de tu muestra de $que.";
            } else {
                $titulo  = "Tu muestra $folio está en preparación";
                $mensaje = "Ya se está preparando tu muestra de $que.";
            }
            break;
        case 'embarcada':
            $titulo  = "Tu muestra $folio ya se embarcó";
            $mensaje = !empty($s['guia_url'])
                ? "Tu muestra de $que ya salió por paquetería. Toca para rastrearla."
                : "Tu muestra de $que ya salió: " . textoEnvioMuestra($s) . '.';
            break;
        case 'cancelada':
            $titulo  = "Tu muestra $folio se canceló";
            $mensaje = "Se canceló tu solicitud de $que. Motivo: " . $s['motivo_cancelacion'];
            break;
        default:
            return;
    }
    $enlaceRel = 'vendedor/muestras.php';
    crearAviso($db, (int)$s['vendedor_id'], 'muestra_' . $s['estado'], $titulo, $mensaje, $enlaceRel, (int)$s['id']);

    if (!empty($s['vendedor_email'])) {
        $renglones = ['Cliente' => $s['cliente_nombre'], 'Muestras' => textoEstilosMuestra($s), 'Total' => textoParesMuestra(totalParesSolicitudMuestra($s))];
        if ($s['estado'] === 'en_preparacion') $renglones['Preparación'] = textoPreparacionMuestra($s);
        if ($s['estado'] === 'embarcada') {
            $renglones['Envío'] = textoEnvioMuestra($s);
            $renglones['Entregar a'] = textoEntregarAMuestra($s);
            $renglones['Dirección']  = $s['destino_direccion'];
        }
        if ($s['estado'] === 'cancelada') $renglones['Motivo'] = $s['motivo_cancelacion'];
        // Embarcada con link de rastreo: el botón del correo abre el rastreo.
        $conRastreo = $s['estado'] === 'embarcada' && !empty($s['guia_url']);
        enviarCorreoMuestra($s['vendedor_email'], $titulo, correoMuestraHtml(
            $titulo, $conRastreo ? "Tu muestra de $que ya salió por paquetería." : $mensaje, $renglones,
            $conRastreo ? $s['guia_url'] : urlBaseVisitas() . $enlaceRel,
            $conRastreo ? 'Rastrear envío' : 'Ver mis muestras'
        ));
    }
}

/** "Suela: más gruesa\nOtro (Color): negro" -- para correos. */
function textoCambiosMuestra(array $cambios): ?string {
    if (!$cambios) return null;
    $lineas = [];
    foreach ($cambios as $c) {
        $cat = ($c['categoria'] ?? '') === 'otro'
            ? ($c['categoria_otro'] ?? 'Otro')
            : (MUESTRA_CATEGORIAS_CAMBIO[$c['categoria'] ?? ''] ?? ($c['categoria'] ?? ''));
        $lineas[] = $cat . ': ' . ($c['descripcion'] ?? '');
    }
    return implode("\n", $lineas);
}

/**
 * Cambia el estado de una solicitud (solo la responsable de muestras).
 * $datos: preparacion ('pt'|'por_programar') y fecha_estimada (opcional,
 * solo por programar) para 'en_preparacion'; envio_modo/guia_url
 * para 'embarcada'; motivo para 'cancelada'. Una que ya está "en
 * preparación · por programar" puede volver a marcarse en preparación con
 * preparacion = 'pt' (ya se fabricó). Valida la transición, guarda
 * historial y avisa al vendedor.
 * Devuelve ['ok'=>bool, 'error'?=>string, 'solicitud'?=>array].
 */
function cambiarEstadoSolicitudMuestra(PDO $db, int $id, int $usuarioId, string $nuevo, array $datos): array {
    if (!isset(MUESTRA_ESTADOS[$nuevo])) return ['ok' => false, 'error' => 'Estado no válido'];

    $envioModo = null; $guiaUrl = null; $motivo = null; $nota = null;
    $preparacion = null; $fechaEstimada = null;
    if ($nuevo === 'en_preparacion') {
        $preparacion = (string)($datos['preparacion'] ?? '');
        if (!isset(MUESTRA_PREPARACION[$preparacion])) return ['ok' => false, 'error' => 'Elige si está en Producto Terminado o por programar'];
        if ($preparacion === 'por_programar') {
            $f = trim((string)($datos['fecha_estimada'] ?? ''));
            if ($f !== '') {
                $dt = DateTime::createFromFormat('Y-m-d', $f);
                if (!$dt || $dt->format('Y-m-d') !== $f) return ['ok' => false, 'error' => 'La fecha estimada no es válida'];
                $fechaEstimada = $f;
            }
        }
        $nota = MUESTRA_PREPARACION[$preparacion] . ($fechaEstimada ? ' · fecha estimada ' . date('d/m/Y', strtotime($fechaEstimada)) : '');
    }
    if ($nuevo === 'embarcada') {
        $envioModo = ($datos['envio_modo'] ?? '') === 'en_persona' ? 'en_persona' : 'paqueteria';
        if ($envioModo === 'paqueteria') {
            // Solo el link de rastreo de la guía (ya no paquetería ni número).
            $guiaUrl = trim((string)($datos['guia_url'] ?? ''));
            $esquema = strtolower((string)parse_url($guiaUrl, PHP_URL_SCHEME));
            if ($guiaUrl === '' || strlen($guiaUrl) > 1000 || !filter_var($guiaUrl, FILTER_VALIDATE_URL)
                || !in_array($esquema, ['http', 'https'], true)) {
                return ['ok' => false, 'error' => 'Pega el link de rastreo de la guía (debe empezar con http:// o https://)'];
            }
        }
        $nota = $envioModo === 'en_persona' ? 'Entregada en persona' : 'Por paquetería · con link de rastreo';
    }
    if ($nuevo === 'cancelada') {
        $motivo = trim((string)($datos['motivo'] ?? ''));
        if ($motivo === '') return ['ok' => false, 'error' => 'Escribe el motivo de la cancelación'];
        $motivo = mb_substr($motivo, 0, 500);
        $nota = $motivo;
    }

    try {
        $db->beginTransaction();
        $stmt = $db->prepare('SELECT estado, preparacion FROM muestras_solicitudes WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $fila = $stmt->fetch();
        if (!$fila) { $db->rollBack(); return ['ok' => false, 'error' => 'Solicitud no encontrada']; }
        $actual = $fila['estado'];
        // "Por programar" -> "En Producto Terminado" (sigue en preparación).
        $yaEnPt = $actual === 'en_preparacion' && $nuevo === 'en_preparacion'
               && $fila['preparacion'] === 'por_programar' && $preparacion === 'pt';
        if ($yaEnPt) $nota = 'Ya está en Producto Terminado';
        if (!$yaEnPt && !in_array($nuevo, MUESTRA_TRANSICIONES[$actual] ?? [], true)) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'La solicitud ya está "' . etiquetaEstadoMuestra($actual) . '" y no puede pasar a "' . etiquetaEstadoMuestra($nuevo) . '". Recarga la página.'];
        }
        $db->prepare(
            'UPDATE muestras_solicitudes
                SET estado = ?, actualizada_en = NOW(),
                    envio_modo = COALESCE(?, envio_modo), guia_url = COALESCE(?, guia_url),
                    motivo_cancelacion = COALESCE(?, motivo_cancelacion)
              WHERE id = ?'
        )->execute([$nuevo, $envioModo, $guiaUrl, $motivo, $id]);
        if ($nuevo === 'en_preparacion') {
            $db->prepare('UPDATE muestras_solicitudes SET preparacion = ?, fecha_estimada_pt = ? WHERE id = ?')
               ->execute([$preparacion, $fechaEstimada, $id]);
        }
        // Embarcada o cancelada: la petición del vendedor (si había) ya quedó atendida.
        if (in_array($nuevo, ['embarcada', 'cancelada'], true)) limpiarPeticionMuestra($db, $id);
        $db->prepare('INSERT INTO muestras_solicitudes_historial (solicitud_id, estado, usuario_id, nota) VALUES (?,?,?,?)')
           ->execute([$id, $nuevo, $usuarioId, $nota]);
        // Los avisos de "nueva solicitud" de esta muestra ya no hacen falta.
        $db->prepare("UPDATE avisos SET leido_en = NOW() WHERE solicitud_muestra_id = ? AND tipo = 'muestra_nueva' AND leido_en IS NULL")
           ->execute([$id]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[VISITAS] cambiarEstadoSolicitudMuestra: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'No se pudo guardar. Intenta de nuevo.'];
    }

    $s = cargarSolicitudMuestra($db, $id);
    notificarCambioEstadoMuestra($db, $s, $yaEnPt);
    return ['ok' => true, 'solicitud' => $s];
}

/** Forma pública (JSON) de una solicitud para las pantallas. */
function solicitudMuestraParaJson(array $s): array {
    return [
        'id'                 => (int)$s['id'],
        'folio'              => $s['folio'],
        'vendedor_id'        => (int)$s['vendedor_id'],
        'vendedor_nombre'    => $s['vendedor_nombre'] ?? null,
        'vendedor_email'     => $s['vendedor_email'] ?? null,
        'vendedor_telefono'  => $s['vendedor_telefono'] ?? null,
        'cliente_nombre'     => $s['cliente_nombre'],
        'contacto_nombre'    => $s['cliente_contacto'] ?? $s['contacto_nombre'] ?? null,
        'contacto_telefono'  => $s['cliente_telefono'] ?? $s['contacto_telefono'] ?? null,
        'estilo_nombre'      => $s['estilo_nombre'],
        'color'              => $s['color'] ?? null,
        'talla'              => $s['talla'],
        'cantidad'           => (int)($s['cantidad'] ?? 1),
        'motivo'             => $s['motivo'] ?? null,
        'notas_planta'       => $s['notas_planta'] ?? null,
        'tiempo_prueba_dias' => isset($s['tiempo_prueba_dias']) ? (int)$s['tiempo_prueba_dias'] : null,
        'fecha_promesa'      => $s['fecha_promesa'],
        'tipo'               => $s['tipo'],
        'cambios'            => is_array($s['cambios']) ? $s['cambios'] : (json_decode($s['cambios'] ?? '[]', true) ?: []),
        // Varios estilos con sus tallas (22-oct-2026). Las pantallas usan
        // esto; estilo_nombre/color/talla/cantidad/tipo de arriba son resumen.
        'estilos'            => $s['estilos'] ?? [],
        'entregar_a'         => $s['entregar_a'] ?? 'cliente',
        'destino_direccion'  => $s['destino_direccion'],
        'estado'             => $s['estado'],
        'estado_etiqueta'    => etiquetaEstadoMuestra($s['estado']),
        'preparacion'        => $s['preparacion'] ?? null,
        'fecha_estimada_pt'  => $s['fecha_estimada_pt'] ?? null,
        'envio_modo'         => $s['envio_modo'],
        'paqueteria'         => $s['paqueteria'],
        'guia'               => $s['guia'],
        'guia_url'           => $s['guia_url'] ?? null,
        'motivo_cancelacion' => $s['motivo_cancelacion'],
        'peticion_tipo'      => $s['peticion_tipo'] ?? null,
        'peticion_texto'     => $s['peticion_texto'] ?? null,
        'peticion_en'        => $s['peticion_en'] ?? null,
        'created_at'         => $s['created_at'],
        'actualizada_en'     => $s['actualizada_en'],
    ];
}

// ─── Validar y guardar estilos (alta y corrección) ───────────────────────────

/**
 * Valida los estilos que manda el formulario (JSON de solicitar_muestra.php)
 * contra el catálogo de la Nueva cotización. La usan el alta
 * (api/muestra_solicitar.php) y la corrección de la responsable
 * (api/muestra_corregir.php). Si algo está mal contesta el error (JSON) y sale.
 * Devuelve ['estilos', 'total', 'resumen', 'variante'].
 */
function validarEstilosMuestraOSalir(array $estilosEntrada): array {
    require_once __DIR__ . '/db_erp.php';
    if (!$estilosEntrada) {
        jsonResponse(['ok' => false, 'error' => 'Agrega al menos un estilo'], 400);
    }
    if (count($estilosEntrada) > MUESTRAS_MAX_ESTILOS) {
        jsonResponse(['ok' => false, 'error' => 'Puedes pedir hasta ' . MUESTRAS_MAX_ESTILOS . ' estilos en una solicitud'], 400);
    }
    // Cada estilo tiene que estar en el catálogo de la Nueva cotización (modelo
    // del cotizador anterior o estilo del ERP); el nombre, los colores y las
    // tallas válidas salen de ahí, no de lo que mande el navegador.
    try {
        $catalogo = [];
        foreach (catalogoParaMuestraErp() as $it) $catalogo[$it['id']] = $it;
    } catch (Throwable $e) {
        error_log('[VISITAS] muestra_solicitar (estilos): ' . $e->getMessage());
        jsonResponse(['ok' => false, 'error' => 'No se pudo validar el estilo. Intenta más tarde.'], 503);
    }

    $estilos   = [];   // ya validados
    $totalPares = 0;
    $vistos    = [];   // item|color repetidos
    foreach (array_values($estilosEntrada) as $i => $en) {
        $n = $i + 1;
        $item = trim((string)($en['item'] ?? ''));
        if (!preg_match('/^[me]:\d+$/', $item)) {
            jsonResponse(['ok' => false, 'error' => "Elige el estilo $n"], 400);
        }
        $estilo = $catalogo[$item] ?? null;
        if (!$estilo) {
            jsonResponse(['ok' => false, 'error' => "El estilo $n ya no está disponible. Elige otro."], 400);
        }
        $nombre = $estilo['clave'];

        // Color: igual que en la Nueva cotización -- si el modelo tiene varios
        // hay que elegir uno de ellos; si tiene uno solo, va ese.
        $colores = $estilo['colores'] ?? [];
        $color   = trim((string)($en['color'] ?? ''));
        if (count($colores) > 1) {
            if (!in_array($color, $colores, true)) {
                jsonResponse(['ok' => false, 'error' => "Elige el color del estilo $nombre"], 400);
            }
        } else {
            $color = $colores[0] ?? '';
        }
        $llave = $item . '|' . $color;
        if (isset($vistos[$llave])) {
            jsonResponse(['ok' => false, 'error' => "El estilo $nombre" . ($color !== '' ? " en $color" : '') . ' está repetido. Pon todas sus tallas en una sola tarjeta.'], 400);
        }
        $vistos[$llave] = true;

        // Tipo y cambios (por estilo).
        $tipo = ($en['tipo'] ?? '') === 'variante' ? 'variante' : 'identico';
        $cambios = [];
        if ($tipo === 'variante') {
            foreach (is_array($en['adendum'] ?? null) ? $en['adendum'] : [] as $c) {
                $cat  = (string)($c['categoria'] ?? '');
                $desc = mb_substr(trim((string)($c['descripcion_cliente'] ?? $c['descripcion'] ?? '')), 0, 500);
                if ($desc === '') continue;
                if ($cat === 'otro') {
                    $nombreOtro = mb_substr(trim((string)($c['categoria_otro'] ?? '')), 0, 60);
                    if ($nombreOtro === '') continue;
                    $cambios[] = ['categoria' => 'otro', 'categoria_otro' => $nombreOtro, 'descripcion' => $desc];
                } elseif (isset(MUESTRA_CATEGORIAS_CAMBIO[$cat])) {
                    $cambios[] = ['categoria' => $cat, 'descripcion' => $desc];
                }
            }
            if (!$cambios) {
                jsonResponse(['ok' => false, 'error' => "Describe al menos un cambio de la variante del estilo $nombre"], 400);
            }
        }

        // Tallas: de la lista del estilo (numeración del ERP o la fija de 20 a 35).
        $tallasEntrada = is_array($en['tallas'] ?? null) ? array_values($en['tallas']) : [];
        if (!$tallasEntrada) {
            jsonResponse(['ok' => false, 'error' => "Agrega al menos una talla al estilo $nombre"], 400);
        }
        if (count($tallasEntrada) > MUESTRAS_MAX_TALLAS_POR_ESTILO) {
            jsonResponse(['ok' => false, 'error' => "El estilo $nombre puede llevar hasta " . MUESTRAS_MAX_TALLAS_POR_ESTILO . ' tallas'], 400);
        }
        $tallas = [];
        foreach ($tallasEntrada as $t) {
            $talla = trim((string)($t['talla'] ?? ''));
            if ($talla === '') {
                jsonResponse(['ok' => false, 'error' => "Elige la talla en el estilo $nombre"], 400);
            }
            if (is_numeric($talla)) $talla = textoTallaMuestra($talla);
            if (!in_array($talla, $estilo['tallas'], true)) {
                jsonResponse(['ok' => false, 'error' => "La talla $talla no es válida para el estilo $nombre"], 400);
            }
            if (isset($tallas[$talla])) {
                jsonResponse(['ok' => false, 'error' => "La talla $talla está repetida en el estilo $nombre"], 400);
            }
            $pares = $t['pares'] ?? null;
            if (!is_numeric($pares) || (int)$pares != $pares || (int)$pares < 1) {
                jsonResponse(['ok' => false, 'error' => "Pon cuántos pares de la talla $talla del estilo $nombre (al menos 1)"], 400);
            }
            $tallas[$talla] = [
                'talla' => $talla,
                'pares' => (int)$pares,
                'nota'  => mb_substr(trim((string)($t['nota'] ?? '')), 0, 500),
            ];
            $totalPares += (int)$pares;
        }

        $estilos[] = [
            'nombre'           => mb_substr($estilo['nombre'], 0, 200),
            'id_estilo'        => $estilo['id_estilo'],
            'id_modelo_legacy' => $estilo['id_modelo_legacy'],
            'color'            => $color,
            'tipo'             => $tipo,
            'cambios'          => $cambios,
            'tallas'           => array_values($tallas),
        ];
    }
    if ($totalPares > MUESTRAS_TOPE_PARES_CLIENTE_MES) {
        jsonResponse(['ok' => false, 'error' => 'Son ' . $totalPares . ' pares y el máximo por cliente es de '
            . MUESTRAS_TOPE_PARES_CLIENTE_MES . ' pares al mes. Quita ' . ($totalPares - MUESTRAS_TOPE_PARES_CLIENTE_MES)
            . ($totalPares - MUESTRAS_TOPE_PARES_CLIENTE_MES === 1 ? ' par.' : ' pares.')], 400);
    }

    // Resumen que se guarda en la solicitud (bandeja, búsqueda): ver la
    // migración 20261022120000_muestras_varios_estilos.sql.
    $primero      = $estilos[0];
    $unaTalla     = count($estilos) === 1 && count($primero['tallas']) === 1;
    $resumenNombre = $primero['nombre'] . (count($estilos) > 1
        ? ' y ' . (count($estilos) - 1) . (count($estilos) === 2 ? ' estilo más' : ' estilos más') : '');
    $hayVariante  = false;
    foreach ($estilos as $e) if ($e['tipo'] === 'variante') $hayVariante = true;
    return ['estilos' => $estilos, 'total' => $totalPares, 'resumen' => $resumenNombre, 'variante' => $hayVariante];
}

/** Inserta los estilos (ya validados) y sus tallas de una solicitud. */
function guardarEstilosMuestra(PDO $db, int $id, array $estilos): void {
    $insE = $db->prepare(
        'INSERT INTO muestras_solicitudes_estilos (solicitud_id, orden, id_estilo_erp, id_modelo_legacy, estilo_nombre, color, tipo, cambios)
         VALUES (?,?,?,?,?,?,?,?::jsonb) RETURNING id'
    );
    $insT = $db->prepare('INSERT INTO muestras_solicitudes_tallas (estilo_id, orden, talla, pares, nota) VALUES (?,?,?,?,?)');
    foreach ($estilos as $i => $e) {
        $insE->execute([$id, $i + 1, $e['id_estilo'], $e['id_modelo_legacy'], $e['nombre'],
                        $e['color'] !== '' ? mb_substr($e['color'], 0, 40) : null, $e['tipo'],
                        json_encode($e['cambios'], JSON_UNESCAPED_UNICODE)]);
        $estiloId = (int)$insE->fetchColumn();
        foreach ($e['tallas'] as $j => $t) {
            $insT->execute([$estiloId, $j + 1, $t['talla'], $t['pares'], $t['nota'] !== '' ? $t['nota'] : null]);
        }
    }
}

// ─── Peticiones del vendedor y corrección (06-nov-2026) ──────────────────────
//
// Una vez enviada, el vendedor ya NO edita su solicitud. Mientras esté abierta
// (enviada o en preparación) le PIDE a la responsable una corrección o una
// cancelación (una petición pendiente a la vez; api/muestra_pedir.php). Ella
// decide en muestras/ver.php:
//   - la corrige (vendedor/solicitar_muestra.php?corregir=ID, mismo folio),
//   - contesta «No se puede cambiar» con el motivo, o
//   - la cancela (estado «cancelada» de siempre).
// Cada paso queda en el historial y le llega aviso + correo al otro.
// Mismo flujo que el ERP para los vendedores internos.

function limpiarPeticionMuestra(PDO $db, int $id): void {
    $db->prepare('UPDATE muestras_solicitudes SET peticion_tipo = NULL, peticion_texto = NULL, peticion_en = NULL, peticion_usuario_id = NULL WHERE id = ?')
       ->execute([$id]);
}

function sePuedeCorregirMuestra(array $s): bool {
    return in_array($s['estado'], ['enviada', 'en_preparacion'], true);
}

/** El vendedor pide una corrección o una cancelación de SU solicitud. */
function pedirCambioMuestra(PDO $db, int $vendedorId, int $id, string $tipo, string $texto): array {
    if (!isset(MUESTRA_PETICIONES[$tipo])) return ['ok' => false, 'error' => 'Petición no válida'];
    $texto = mb_substr(trim($texto), 0, 1000);
    if ($texto === '') {
        return ['ok' => false, 'error' => $tipo === 'correccion' ? 'Escribe qué hay que corregir.' : 'Escribe por qué hay que cancelarla.'];
    }
    try {
        $db->beginTransaction();
        $stmt = $db->prepare('SELECT vendedor_id, estado, peticion_tipo FROM muestras_solicitudes WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $f = $stmt->fetch();
        if (!$f || (int)$f['vendedor_id'] !== $vendedorId) { $db->rollBack(); return ['ok' => false, 'error' => 'No se encontró la muestra.']; }
        if (!in_array($f['estado'], ['enviada', 'en_preparacion'], true)) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'La muestra ya está "' . etiquetaEstadoMuestra($f['estado']) . '": ya no se puede cambiar. Recarga la página.'];
        }
        if (!empty($f['peticion_tipo'])) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'Ya pediste un cambio para esta muestra. Espera la respuesta (te llega un aviso).'];
        }
        $db->prepare('UPDATE muestras_solicitudes SET peticion_tipo = ?, peticion_texto = ?, peticion_en = NOW(), peticion_usuario_id = ?, actualizada_en = NOW() WHERE id = ?')
           ->execute([$tipo, $texto, $vendedorId, $id]);
        $db->prepare('INSERT INTO muestras_solicitudes_historial (solicitud_id, estado, usuario_id, nota) VALUES (?,?,?,?)')
           ->execute([$id, $tipo . '_pedida', $vendedorId, mb_substr($texto, 0, 500)]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[VISITAS] pedirCambioMuestra: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'No se pudo enviar. Intenta de nuevo.'];
    }
    $s = cargarSolicitudMuestra($db, $id);
    if ($s) notificarPeticionMuestra($db, $s);
    return ['ok' => true, 'solicitud' => $s];
}

/** La responsable contesta «No se puede cambiar» (la muestra sigue igual). */
function rechazarPeticionMuestra(PDO $db, int $id, int $usuarioId, string $motivo): array {
    $motivo = mb_substr(trim($motivo), 0, 500);
    if ($motivo === '') return ['ok' => false, 'error' => 'Escribe por qué no se puede (le llega al vendedor).'];
    try {
        $db->beginTransaction();
        $stmt = $db->prepare('SELECT peticion_tipo FROM muestras_solicitudes WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $f = $stmt->fetch();
        if (!$f) { $db->rollBack(); return ['ok' => false, 'error' => 'Solicitud no encontrada']; }
        if (empty($f['peticion_tipo'])) { $db->rollBack(); return ['ok' => false, 'error' => 'Esta solicitud ya no tiene una petición pendiente. Recarga la página.']; }
        limpiarPeticionMuestra($db, $id);
        $db->prepare('UPDATE muestras_solicitudes SET actualizada_en = NOW() WHERE id = ?')->execute([$id]);
        $db->prepare("INSERT INTO muestras_solicitudes_historial (solicitud_id, estado, usuario_id, nota) VALUES (?, 'peticion_rechazada', ?, ?)")
           ->execute([$id, $usuarioId, $motivo]);
        $db->commit();
        $tipo = $f['peticion_tipo'];
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[VISITAS] rechazarPeticionMuestra: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'No se pudo guardar. Intenta de nuevo.'];
    }
    $s = cargarSolicitudMuestra($db, $id);
    if ($s) notificarRespuestaMuestra($db, $s, 'rechazada', $motivo, $tipo);
    return ['ok' => true, 'solicitud' => $s];
}

/** Pares de las OTRAS solicitudes del mismo cliente en el mes (CDMX) en que se pidió ésta. */
function paresMuestraDelMesSin(PDO $db, array $s): int {
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(cantidad), 0) FROM muestras_solicitudes
         WHERE cliente_id = ? AND id <> ? AND estado <> 'cancelada'
           AND date_trunc('month', (created_at AT TIME ZONE 'UTC') AT TIME ZONE 'America/Mexico_City')
             = date_trunc('month', (?::timestamp AT TIME ZONE 'UTC') AT TIME ZONE 'America/Mexico_City')"
    );
    $stmt->execute([(int)$s['cliente_id'], (int)$s['id'], $s['created_at']]);
    return (int)$stmt->fetchColumn();
}

/** "Antes → ahora" de lo que cambió (para el historial, el aviso y el correo). */
function textoCorreccionMuestra(array $antes, array $ahora): string {
    $fecha = fn($v) => $v ? date('d/m/Y', strtotime($v)) : 'sin fecha';
    $campos = [
        'Muestras'          => [textoEstilosMuestra($antes), textoEstilosMuestra($ahora)],
        'Total'             => [textoParesMuestra(totalParesSolicitudMuestra($antes)), textoParesMuestra(totalParesSolicitudMuestra($ahora))],
        'Motivo'            => [(string)($antes['motivo'] ?? ''), (string)($ahora['motivo'] ?? '')],
        'Notas para planta' => [(string)($antes['notas_planta'] ?? ''), (string)($ahora['notas_planta'] ?? '')],
        'Fecha promesa'     => [$fecha($antes['fecha_promesa'] ?? null), $fecha($ahora['fecha_promesa'] ?? null)],
        'Tiempo de prueba'  => [(string)textoTiempoPruebaMuestra($antes['tiempo_prueba_dias'] ?? null), (string)textoTiempoPruebaMuestra($ahora['tiempo_prueba_dias'] ?? null)],
        'Dirección'         => [(string)$antes['destino_direccion'], (string)$ahora['destino_direccion']],
    ];
    $lineas = [];
    foreach ($campos as $nombre => [$a, $b]) {
        if (trim($a) === trim($b)) continue;
        $lineas[] = $nombre === 'Muestras'
            ? "Muestras antes:\n" . $a . "\nMuestras ahora:\n" . $b
            : $nombre . ': ' . ($a !== '' ? $a : '(vacío)') . ' → ' . ($b !== '' ? $b : '(vacío)');
    }
    return $lineas ? implode("\n", $lineas) : 'Sin cambios en los datos';
}

/**
 * La responsable corrige la solicitud (mismo folio). Cliente, contacto y
 * entrega no se cambian. Se valida igual que el alta y con el tope del mes en
 * que se pidió (sin contarse a sí misma). Atiende la petición pendiente.
 * Contesta JSON y sale si algo está mal (validarEstilosMuestraOSalir).
 */
function corregirSolicitudMuestra(PDO $db, int $usuarioId, int $id, array $post): array {
    $err = fn(string $m, int $c = 400) => ['ok' => false, 'error' => $m, 'codigo' => $c];
    $antes = cargarSolicitudMuestra($db, $id);
    if (!$antes) return $err('Solicitud no encontrada', 404);
    if (!sePuedeCorregirMuestra($antes)) return $err('La muestra ya está "' . etiquetaEstadoMuestra($antes['estado']) . '": ya no se puede corregir.', 409);

    $direccion    = trim((string)($post['destino_direccion'] ?? ''));
    $fechaPromesa = trim((string)($post['fecha_promesa'] ?? ''));
    $motivo       = mb_substr(trim((string)($post['motivo'] ?? '')), 0, 500);
    $notasPlanta  = mb_substr(trim((string)($post['notas_planta'] ?? '')), 0, 2000);
    $tiempoPrueba = trim((string)($post['tiempo_prueba_dias'] ?? ''));
    $estilosEntrada = json_decode((string)($post['estilos'] ?? '[]'), true);
    if (!is_array($estilosEntrada)) $estilosEntrada = [];
    if ($motivo === '') return $err('Escribe el motivo de la muestra');
    if ($tiempoPrueba !== '' && (!ctype_digit($tiempoPrueba) || (int)$tiempoPrueba < 1 || (int)$tiempoPrueba > 365)) {
        return $err('El tiempo de prueba debe ser de 1 a 365 días');
    }
    $tiempoPrueba = $tiempoPrueba === '' ? null : (int)$tiempoPrueba;
    if ($direccion === '') return $err('Indica la dirección de entrega');
    if ($fechaPromesa !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaPromesa)) return $err('La fecha promesa no es válida');

    $v = validarEstilosMuestraOSalir($estilosEntrada);
    $estilos = $v['estilos'];
    $primero = $estilos[0];
    $unaTalla = count($estilos) === 1 && count($primero['tallas']) === 1;

    try {
        $db->beginTransaction();
        $db->prepare('SELECT pg_advisory_xact_lock(hashtext(?))')->execute(['muestras_cliente_' . (int)$antes['cliente_id']]);
        $stmt = $db->prepare('SELECT estado FROM muestras_solicitudes WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $estado = (string)$stmt->fetchColumn();
        if (!in_array($estado, ['enviada', 'en_preparacion'], true)) {
            $db->rollBack();
            return $err('La muestra ya está "' . etiquetaEstadoMuestra($estado) . '": ya no se puede corregir. Recarga la página.', 409);
        }
        $otros = paresMuestraDelMesSin($db, $antes);
        if ($otros + $v['total'] > MUESTRAS_TOPE_PARES_CLIENTE_MES) {
            $db->rollBack();
            $quedan = max(0, MUESTRAS_TOPE_PARES_CLIENTE_MES - $otros);
            return $err('Este cliente tiene otras solicitudes por ' . textoParesMuestra($otros) . ' en ese mes; con esta corrección serían '
                . ($otros + $v['total']) . ' de ' . MUESTRAS_TOPE_PARES_CLIENTE_MES . '. Esta solicitud puede llevar hasta ' . textoParesMuestra($quedan) . '.', 409);
        }
        // Resumen igual que en el alta (ver la migración 20261022120000_muestras_varios_estilos.sql).
        $db->prepare(
            'UPDATE muestras_solicitudes
                SET id_estilo_erp = ?, id_modelo_legacy = ?, estilo_nombre = ?, color = ?, talla = ?, tipo = ?, cambios = ?::jsonb,
                    cantidad = ?, motivo = ?, notas_planta = ?, fecha_promesa = ?, tiempo_prueba_dias = ?, destino_direccion = ?,
                    actualizada_en = NOW()
              WHERE id = ?'
        )->execute([
            $primero['id_estilo'], $primero['id_modelo_legacy'], mb_substr($v['resumen'], 0, 200),
            count($estilos) === 1 && $primero['color'] !== '' ? mb_substr($primero['color'], 0, 40) : null,
            $unaTalla ? $primero['tallas'][0]['talla'] : null, $v['variante'] ? 'variante' : 'identico',
            json_encode(count($estilos) === 1 ? $primero['cambios'] : [], JSON_UNESCAPED_UNICODE),
            $v['total'], $motivo, $notasPlanta !== '' ? $notasPlanta : null, $fechaPromesa ?: null, $tiempoPrueba, $direccion, $id,
        ]);
        $db->prepare('DELETE FROM muestras_solicitudes_estilos WHERE solicitud_id = ?')->execute([$id]); // tallas: ON DELETE CASCADE
        guardarEstilosMuestra($db, $id, $estilos);
        limpiarPeticionMuestra($db, $id);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[VISITAS] corregirSolicitudMuestra: ' . $e->getMessage());
        return $err('No se pudo guardar la corrección. Intenta de nuevo.', 500);
    }

    $ahora = cargarSolicitudMuestra($db, $id);
    $cambios = textoCorreccionMuestra($antes, $ahora);
    try {
        $db->prepare("INSERT INTO muestras_solicitudes_historial (solicitud_id, estado, usuario_id, nota) VALUES (?, 'corregida', ?, ?)")
           ->execute([$id, $usuarioId, mb_substr($cambios, 0, 500)]);
    } catch (Throwable $e) {
        error_log('[VISITAS] corregirSolicitudMuestra (historial): ' . $e->getMessage());
    }
    notificarRespuestaMuestra($db, $ahora, 'corregida', $cambios, $antes['peticion_tipo'] ?? null);
    return ['ok' => true, 'id' => $id, 'folio' => $ahora['folio'], 'cambios' => $cambios];
}

/** Aviso + correo a la(s) responsable(s): el vendedor pide corrección o cancelación. */
function notificarPeticionMuestra(PDO $db, array $s): void {
    $cancelar = ($s['peticion_tipo'] ?? '') === 'cancelacion';
    $titulo  = ($cancelar ? 'Piden cancelar la muestra ' : 'Piden corregir la muestra ') . $s['folio'];
    $mensaje = $s['vendedor_nombre'] . ': «' . mb_substr((string)$s['peticion_texto'], 0, 300) . '»';
    $enlaceRel = 'muestras/ver.php?id=' . (int)$s['id'];
    $cuerpo = correoMuestraHtml(
        $titulo,
        $s['vendedor_nombre'] . ($cancelar ? ' pide que se cancele' : ' pide una corrección en') . ' su solicitud de muestra. Tú decides: ábrela para '
            . ($cancelar ? 'cancelarla' : 'corregirla') . ' o contestar que no se puede.',
        [
            'Lo que pide' => $s['peticion_texto'],
            'Estado'      => etiquetaEstadoMuestra($s['estado']) . (textoPreparacionMuestra($s) ? ' · ' . textoPreparacionMuestra($s) : ''),
            'Cliente'     => $s['cliente_nombre'],
            'Muestras'    => textoEstilosMuestra($s),
            'Total'       => textoParesMuestra(totalParesSolicitudMuestra($s)),
        ],
        urlBaseVisitas() . $enlaceRel,
        'Abrir solicitud'
    );
    foreach (responsablesMuestras($db) as $r) {
        crearAviso($db, (int)$r['id'], 'muestra_peticion', $titulo, $mensaje, $enlaceRel, (int)$s['id']);
        if (!empty($r['email'])) enviarCorreoMuestra($r['email'], $titulo, $cuerpo);
    }
}

/**
 * Aviso + correo al vendedor: se corrigió su solicitud ($que = 'corregida',
 * $detalle = lo que cambió) o no se pudo hacer lo que pidió ($que = 'rechazada',
 * $detalle = por qué). Al externo no se le dice quién atiende: «el equipo de Segurmex».
 */
function notificarRespuestaMuestra(PDO $db, array $s, string $que, string $detalle, ?string $tipoPeticion): void {
    $folio = $s['folio'];
    if ($que === 'corregida') {
        $titulo  = "Se corrigió tu muestra $folio";
        $mensaje = 'El equipo de Segurmex corrigió tu solicitud para ' . $s['cliente_nombre'] . '. Sigue con el mismo folio.';
        $renglones = ['Cliente' => $s['cliente_nombre'], 'Qué cambió' => $detalle, 'Total ahora' => textoParesMuestra(totalParesSolicitudMuestra($s))];
    } else {
        $titulo  = $tipoPeticion === 'cancelacion' ? "No se canceló tu muestra $folio" : "No se pudo corregir tu muestra $folio";
        $mensaje = 'El equipo de Segurmex revisó lo que pediste y la muestra sigue igual. Motivo: ' . $detalle;
        $renglones = ['Cliente' => $s['cliente_nombre'], 'Por qué' => $detalle, 'Muestras' => textoEstilosMuestra($s)];
    }
    $enlaceRel = 'vendedor/muestras.php';
    crearAviso($db, (int)$s['vendedor_id'], $que === 'corregida' ? 'muestra_corregida' : 'muestra_peticion_rechazada', $titulo, $mensaje, $enlaceRel, (int)$s['id']);
    if (!empty($s['vendedor_email'])) {
        enviarCorreoMuestra($s['vendedor_email'], $titulo, correoMuestraHtml($titulo, $mensaje, $renglones, urlBaseVisitas() . $enlaceRel, 'Ver mis muestras'));
    }
}
