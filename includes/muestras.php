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

// Tope de solicitudes por vendedor por mes calendario (hora de CDMX). Las
// canceladas no cuentan.
const MUESTRAS_TOPE_MES = 5;

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
        ];
    }
    if ($item !== null) return $lista[$item] ?? null;
    return array_values($lista);
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

/** Solicitudes de este mes que cuentan para el tope (todas menos canceladas). */
function muestrasDelMesVendedor(PDO $db, int $vendedorId): int {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM muestras_solicitudes
         WHERE vendedor_id = ? AND estado <> 'cancelada' AND created_at >= " . sqlInicioMesMx()
    );
    $stmt->execute([$vendedorId]);
    return (int)$stmt->fetchColumn();
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

/** Texto corto de cómo se envió ("DHL, guía 123" / "Entregada en persona"). */
function textoEnvioMuestra(array $s): string {
    if (($s['envio_modo'] ?? '') === 'en_persona') return 'Entregada en persona';
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
    $mensaje = $s['vendedor_nombre'] . ' pide ' . $s['estilo_nombre'] . ' para ' . $s['cliente_nombre'] . '.';
    $enlaceRel = 'muestras/ver.php?id=' . (int)$s['id'];
    $cuerpo = correoMuestraHtml(
        $titulo,
        $s['vendedor_nombre'] . ' acaba de pedir una muestra. Revísala y marca el avance en el sistema.',
        [
            'Vendedor'        => $s['vendedor_nombre'] . ($s['vendedor_telefono'] ? ' · ' . $s['vendedor_telefono'] : ''),
            'Cliente'         => $s['cliente_nombre'],
            'Contacto'        => trim(($s['cliente_contacto'] ?? '') . (!empty($s['cliente_telefono']) ? ' · ' . $s['cliente_telefono'] : '')),
            'Estilo'          => $s['estilo_nombre'],
            'Color'           => $s['color'] ?? null,
            'Talla'           => $s['talla'],
            'Cantidad'        => textoParesMuestra($s['cantidad'] ?? 1),
            'Tipo'            => $s['tipo'] === 'variante' ? 'Variante (con cambios)' : 'Idéntico al estilo',
            'Cambios'         => textoCambiosMuestra($s['cambios']),
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
            $mensaje = "Tu muestra de $que ya salió: " . textoEnvioMuestra($s) . '.';
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
        $renglones = ['Cliente' => $s['cliente_nombre'], 'Estilo' => $s['estilo_nombre'], 'Color' => $s['color'] ?? null, 'Talla' => $s['talla'], 'Cantidad' => textoParesMuestra($s['cantidad'] ?? 1)];
        if ($s['estado'] === 'en_preparacion') $renglones['Preparación'] = textoPreparacionMuestra($s);
        if ($s['estado'] === 'embarcada') {
            $renglones['Envío'] = textoEnvioMuestra($s);
            $renglones['Entregar a'] = textoEntregarAMuestra($s);
            $renglones['Dirección']  = $s['destino_direccion'];
        }
        if ($s['estado'] === 'cancelada') $renglones['Motivo'] = $s['motivo_cancelacion'];
        enviarCorreoMuestra($s['vendedor_email'], $titulo, correoMuestraHtml(
            $titulo, $mensaje, $renglones, urlBaseVisitas() . $enlaceRel, 'Ver mis muestras'
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
 * solo por programar) para 'en_preparacion'; envio_modo/paqueteria/guia
 * para 'embarcada'; motivo para 'cancelada'. Una que ya está "en
 * preparación · por programar" puede volver a marcarse en preparación con
 * preparacion = 'pt' (ya se fabricó). Valida la transición, guarda
 * historial y avisa al vendedor.
 * Devuelve ['ok'=>bool, 'error'?=>string, 'solicitud'?=>array].
 */
function cambiarEstadoSolicitudMuestra(PDO $db, int $id, int $usuarioId, string $nuevo, array $datos): array {
    if (!isset(MUESTRA_ESTADOS[$nuevo])) return ['ok' => false, 'error' => 'Estado no válido'];

    $envioModo = null; $paqueteria = null; $guia = null; $motivo = null; $nota = null;
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
            $paqueteria = trim((string)($datos['paqueteria'] ?? ''));
            $guia       = trim((string)($datos['guia'] ?? ''));
            if ($paqueteria === '') return ['ok' => false, 'error' => 'Escribe la paquetería'];
            if ($guia === '')       return ['ok' => false, 'error' => 'Escribe el número de guía'];
            $paqueteria = mb_substr($paqueteria, 0, 80);
            $guia       = mb_substr($guia, 0, 80);
        }
        $nota = $envioModo === 'en_persona' ? 'Entregada en persona' : "$paqueteria, guía $guia";
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
                    envio_modo = COALESCE(?, envio_modo), paqueteria = COALESCE(?, paqueteria),
                    guia = COALESCE(?, guia), motivo_cancelacion = COALESCE(?, motivo_cancelacion)
              WHERE id = ?'
        )->execute([$nuevo, $envioModo, $paqueteria, $guia, $motivo, $id]);
        if ($nuevo === 'en_preparacion') {
            $db->prepare('UPDATE muestras_solicitudes SET preparacion = ?, fecha_estimada_pt = ? WHERE id = ?')
               ->execute([$preparacion, $fechaEstimada, $id]);
        }
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
        'entregar_a'         => $s['entregar_a'] ?? 'cliente',
        'destino_direccion'  => $s['destino_direccion'],
        'estado'             => $s['estado'],
        'estado_etiqueta'    => etiquetaEstadoMuestra($s['estado']),
        'preparacion'        => $s['preparacion'] ?? null,
        'fecha_estimada_pt'  => $s['fecha_estimada_pt'] ?? null,
        'envio_modo'         => $s['envio_modo'],
        'paqueteria'         => $s['paqueteria'],
        'guia'               => $s['guia'],
        'motivo_cancelacion' => $s['motivo_cancelacion'],
        'created_at'         => $s['created_at'],
        'actualizada_en'     => $s['actualizada_en'],
    ];
}
