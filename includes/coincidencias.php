<?php
/**
 * Coincidencias de clientes ENTRE vendedores (03-nov-2026). Ver
 * supabase/migrations/20261103120000_coincidencias_clientes.sql.
 *
 * Al dar de alta o editar un cliente se compara contra los clientes de los
 * DEMÁS vendedores activos del mismo municipio (misma empresa en otro
 * municipio = sucursal, se permite). Si coincide, el cliente se guarda
 * "en_revision": el vendedor no lo puede trabajar hasta que la responsable
 * de muestras (rol 'muestras') decida en muestras/coincidencias.php.
 * Solo se avisa al vendedor que lo registró y a la responsable (no al
 * vendedor que ya lo tenía).
 *
 * La revisión DENTRO de la cartera del propio vendedor sigue en
 * api/verificar_duplicado.php (solo avisa, no bloquea).
 *
 * 18-nov-2026: también se compara contra lo de las vendedoras INTERNAS en el
 * ERP (public.clientes y public.cotizador_prospectos que no vienen de Visitas;
 * migración 20261118120000_coincidencias_con_erp.sql). Mismo teléfono, mismo
 * cliente del ERP, o nombre parecido con el mismo C.P./municipio (o sin
 * dirección en el ERP para comparar). Se decide aquí igual; con «Pasa al
 * vendedor que lo registró» el registro del ERP pasa a este vendedor y su
 * cliente de Visitas queda ligado (no se descarta). Lo que registran las
 * internas en el ERP se decide en el ERP (Ventas › Coincidencias).
 * Se lee con la MISMA conexión (esquema public): si algo del ERP falla, el
 * alta en Visitas sigue.
 */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/muestras.php'; // responsablesMuestras, crearAviso, correo
require_once __DIR__ . '/fcm.php';

const COINC_RADIO_METROS       = 150;  // GPS "mismo lugar"
const COINC_RADIO_SIN_MUNICIPIO = 2000; // si a alguno le falta municipio, se usa cercanía como "misma zona"
const COINC_SIMILITUD_NOMBRE   = 80;   // 0-100, similar_text()

const COINC_TIPOS_ERP = ['erp_cliente' => 'Cliente del ERP', 'erp_prospecto' => 'Prospecto del ERP'];

const COINC_DECISIONES = [
    'distintos'          => 'Son clientes distintos',
    'se_queda_existente' => 'Se queda con el vendedor que ya lo tenía',
    'pasa_a_nuevo'       => 'Pasa al vendedor que lo registró',
];

function coincNormalizarTexto(?string $s): string {
    $s = mb_strtolower(trim((string)$s), 'UTF-8');
    $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n','à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u']);
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
    return trim(preg_replace('/\s+/', ' ', $s));
}

/**
 * Nombre de empresa sin razón social ni artículos: "Ferretería López, S.A.
 * de C.V." -> "ferreteria lopez". No se quitan giros ("ferretería",
 * "abarrotes") a propósito: "Ferretería López" y "Abarrotes López" son
 * negocios distintos.
 */
function coincNormalizarNombre(?string $s): string {
    $s = ' ' . coincNormalizarTexto($s) . ' ';
    $s = preg_replace('/ s ?a ?p ?i( de)?( c ?v)? | s ?a ?b( de)?( c ?v)? | s ?a( de)?( c ?v)? | s( de)? r ?l( de)?( c ?v)? | s ?c | a ?c | c ?v /', ' ', $s);
    $s = preg_replace('/ (de|del|la|el|los|las|y|e) /', ' ', $s);
    $s = preg_replace('/ (de|del|la|el|los|las|y|e) /', ' ', $s); // 2a pasada: artículos seguidos
    return trim(preg_replace('/\s+/', ' ', $s));
}

function coincNombresParecidos(string $a, string $b): bool {
    if ($a === '' || $b === '') return false;
    if ($a === $b) return true;
    if (min(strlen($a), strlen($b)) >= 5 && (str_contains($a, $b) || str_contains($b, $a))) return true;
    similar_text($a, $b, $pct);
    return $pct >= COINC_SIMILITUD_NOMBRE;
}

/**
 * Clientes de OTROS vendedores activos que parecen ser el mismo.
 * $d: nombre, telefono (10 dígitos o ''), municipio, colonia, codigo_postal,
 *     lat, lng, id_cliente_erp (opcional).
 * Devuelve [['id','nombre','direccion','municipio','vendedor_id','vendedor_nombre','razon'], ...]
 */
function buscarCoincidenciasOtrosVendedores(PDO $db, int $vendedorId, array $d, int $excluirClienteId = 0): array {
    $nombre = coincNormalizarNombre($d['nombre'] ?? '');
    if ($nombre === '' && empty($d['telefono'])) return [];

    $tel     = normalizarTelefonoMx($d['telefono'] ?? '') ?: '';
    $mun     = coincNormalizarTexto($d['municipio'] ?? '');
    $col     = coincNormalizarTexto($d['colonia'] ?? '');
    $cp      = preg_replace('/\D+/', '', (string)($d['codigo_postal'] ?? ''));
    $lat     = (isset($d['lat']) && $d['lat'] !== '' && $d['lat'] !== null) ? (float)$d['lat'] : null;
    $lng     = (isset($d['lng']) && $d['lng'] !== '' && $d['lng'] !== null) ? (float)$d['lng'] : null;
    $idErp   = !empty($d['id_cliente_erp']) ? (int)$d['id_cliente_erp'] : 0;

    $stmt = $db->prepare(
        "SELECT c.id, c.nombre, c.direccion, c.telefono, c.municipio, c.colonia, c.codigo_postal,
                c.lat, c.lng, c.id_cliente_erp, c.vendedor_id, u.nombre AS vendedor_nombre
         FROM clientes c JOIN usuarios u ON u.id = c.vendedor_id
         WHERE c.vendedor_id <> ? AND c.id <> ? AND u.rol = 'vendedor' AND u.activo = 1
           AND c.estado_revision IS NULL"
    );
    $stmt->execute([$vendedorId, $excluirClienteId]);

    // Parejas que la responsable ya marcó como "distintos": no se vuelven a marcar.
    $distintos = [];
    if ($excluirClienteId) {
        $st = $db->prepare(
            "SELECT cliente_nuevo_id, cliente_existente_id FROM coincidencias_clientes
             WHERE estado = 'distintos' AND (cliente_nuevo_id = ? OR cliente_existente_id = ?)"
        );
        $st->execute([$excluirClienteId, $excluirClienteId]);
        foreach ($st->fetchAll() as $r) {
            $distintos[(int)$r['cliente_nuevo_id']] = true;
            $distintos[(int)$r['cliente_existente_id']] = true;
        }
    }

    $out = [];
    foreach ($stmt->fetchAll() as $c) {
        if (isset($distintos[(int)$c['id']])) continue;

        $dist = null;
        if ($lat !== null && $lng !== null && $c['lat'] !== null && $c['lng'] !== null) {
            $dist = haversineDistance($lat, $lng, (float)$c['lat'], (float)$c['lng']);
        }

        // Mismo municipio; si a alguno le falta (clientes viejos), cercanía.
        $munC = coincNormalizarTexto($c['municipio'] ?? '');
        if ($mun !== '' && $munC !== '') {
            if ($mun !== $munC) continue;
        } elseif ($dist === null || $dist > COINC_RADIO_SIN_MUNICIPIO) {
            continue;
        }

        $razones = [];
        $telC = normalizarTelefonoMx($c['telefono'] ?? '') ?: '';
        if ($tel !== '' && $tel === $telC) $razones[] = 'Mismo teléfono';
        if ($idErp && (int)$c['id_cliente_erp'] === $idErp) $razones[] = 'Mismo cliente del ERP';

        if (coincNombresParecidos($nombre, coincNormalizarNombre($c['nombre']))) {
            $lugar = [];
            $colC = coincNormalizarTexto($c['colonia'] ?? '');
            $cpC  = preg_replace('/\D+/', '', (string)($c['codigo_postal'] ?? ''));
            if ($col !== '' && $col === $colC) $lugar[] = 'misma colonia';
            elseif ($cp !== '' && $cp === $cpC) $lugar[] = 'mismo C.P.';
            if ($dist !== null && $dist <= COINC_RADIO_METROS) $lugar[] = 'a ' . round($dist) . ' m';
            if ($lugar) $razones[] = 'Nombre parecido y ' . implode(', ', $lugar);
        }

        if ($razones) {
            $out[] = [
                'tipo'            => 'visitas',
                'id'              => (int)$c['id'],
                'nombre'          => $c['nombre'],
                'direccion'       => $c['direccion'],
                'municipio'       => $c['municipio'],
                'vendedor_id'     => (int)$c['vendedor_id'],
                'vendedor_nombre' => $c['vendedor_nombre'],
                'razon'           => implode(' · ', $razones),
            ];
        }
    }
    // Contra lo de las vendedoras internas en el ERP (18-nov-2026).
    foreach (coincBuscarEnErp($db, $vendedorId, $nombre, $tel, $mun, $cp, $idErp, $excluirClienteId) as $e) $out[] = $e;
    return $out;
}

/** usuarios.id del ERP (public) de un vendedor de Visitas, por correo. null si no tiene. */
function coincUsuarioErp(PDO $db, int $vendedorId): ?array {
    $st = $db->prepare("SELECT u.id, u.usuario, u.email, TRIM(CONCAT(u.nombre, ' ', COALESCE(u.apellidos, ''))) AS nombre
                          FROM public.usuarios u JOIN usuarios v ON LOWER(TRIM(u.email)) = LOWER(TRIM(v.email))
                         WHERE v.id = ? ORDER BY u.id LIMIT 1");
    $st->execute([$vendedorId]);
    return $st->fetch() ?: null;
}

/**
 * Clientes y prospectos del ERP (no de Visitas) de OTRO vendedor que parecen
 * el mismo. $nombre ya normalizado (coincNormalizarNombre), $mun normalizado
 * (coincNormalizarTexto), $cp solo dígitos. Nunca truena el alta: cualquier
 * falla del lado del ERP se anota y devuelve [].
 */
function coincBuscarEnErp(PDO $db, int $vendedorId, string $nombre, string $tel, string $mun, string $cp, int $idErp, int $excluirClienteId): array {
    $out = [];
    $enTx = $db->inTransaction();
    if ($enTx) $db->exec('SAVEPOINT sp_coinc_erp');
    try {
        $yo = coincUsuarioErp($db, $vendedorId);
        $yoId = $yo ? (int)$yo['id'] : 0;

        // Parejas ya marcadas «Son distintos» (aquí o, al revés, en el ERP).
        $distintos = [];
        if ($excluirClienteId) {
            $st = $db->prepare("SELECT existente_tipo, existente_erp_id FROM coincidencias_clientes
                                 WHERE estado = 'distintos' AND cliente_nuevo_id = ? AND existente_erp_id IS NOT NULL");
            $st->execute([$excluirClienteId]);
            foreach ($st->fetchAll() as $r) $distintos[$r['existente_tipo'] . ':' . $r['existente_erp_id']] = true;
            if ($enTx) $db->exec('SAVEPOINT sp_coinc_erp2');
            try {
                $st = $db->prepare("SELECT nuevo_tipo, nuevo_id FROM public.coincidencias_vendedores
                                     WHERE estado = 'distintos' AND existente_tipo = 'visitas' AND existente_id = ?");
                $st->execute([$excluirClienteId]);
                foreach ($st->fetchAll() as $r) {
                    if ($r['nuevo_tipo'] === 'prospecto') $distintos['erp_prospecto:' . $r['nuevo_id']] = true;
                }
                if ($enTx) $db->exec('RELEASE SAVEPOINT sp_coinc_erp2');
            } catch (Throwable $e) {
                if ($enTx) $db->exec('ROLLBACK TO SAVEPOINT sp_coinc_erp2');   // el ERP aún sin su migración 20261118120000
            }
        }

        $cands = [];
        $st = $db->prepare("
            SELECT 'erp_cliente' AS tipo, c.id, c.razon_social AS nombre, c.nombre_comercial AS nombre2,
                   (to_jsonb(c) ->> 'telefono') AS tel1, (to_jsonb(c) ->> 'whatsapp') AS tel2,
                   c.cp, m.nombre AS municipio, c.colonia, c.id_agente AS dueno_id
              FROM public.clientes c
              LEFT JOIN public.municipios m ON m.id = c.id_municipio
             WHERE c.estatus = 1 AND c.id_agente IS DISTINCT FROM ?
               AND NOT EXISTS (SELECT 1 FROM clientes vc WHERE vc.id_cliente_erp = c.id
                                  AND vc.estado_revision IS DISTINCT FROM 'descartado')");
        $st->execute([$yoId ?: null]);
        $cands = $st->fetchAll();
        $st = $db->query("
            SELECT 'erp_prospecto' AS tipo, p.id, COALESCE(NULLIF(p.empresa, ''), p.nombre) AS nombre, p.nombre AS nombre2,
                   p.telefono AS tel1, NULL AS tel2, p.cp, (to_jsonb(p) ->> 'municipio') AS municipio, p.colonia,
                   COALESCE(
                     (SELECT u.id FROM public.usuarios u
                       WHERE u.usuario = p.creado_por OR LOWER(TRIM(CONCAT(u.nombre, ' ', u.apellidos))) = LOWER(TRIM(p.creado_por))
                       ORDER BY (u.usuario = p.creado_por) DESC, u.id LIMIT 1),
                     (SELECT ct.id_vendedor FROM public.cotizaciones ct WHERE ct.id_prospecto = p.id ORDER BY ct.id DESC LIMIT 1)) AS dueno_id
              FROM public.cotizador_prospectos p
             WHERE p.convertido_a_cliente_id IS NULL AND p.visitas_cliente_id IS NULL
               AND (to_jsonb(p) ->> 'estado_revision') IS DISTINCT FROM 'descartado'");
        foreach ($st->fetchAll() as $r) {
            if ($yoId && (int)$r['dueno_id'] === $yoId) continue;
            $cands[] = $r;
        }

        $nombresVend = [];
        foreach ($db->query("SELECT id, TRIM(CONCAT(nombre, ' ', COALESCE(apellidos, ''))) AS n FROM public.usuarios")->fetchAll() as $u) {
            $nombresVend[(int)$u['id']] = $u['n'];
        }

        foreach ($cands as $c) {
            if (isset($distintos[$c['tipo'] . ':' . $c['id']])) continue;
            $razones = [];
            $telsC = array_filter([normalizarTelefonoMx($c['tel1'] ?? '') ?: '', normalizarTelefonoMx($c['tel2'] ?? '') ?: '']);
            if ($tel !== '' && in_array($tel, $telsC, true)) $razones[] = 'Mismo teléfono';
            if ($c['tipo'] === 'erp_cliente' && $idErp && (int)$c['id'] === $idErp) $razones[] = 'Mismo cliente del ERP';

            $parecido = false;
            foreach ([$c['nombre'], $c['nombre2']] as $n2) {
                if ($n2 !== null && coincNombresParecidos($nombre, coincNormalizarNombre($n2))) { $parecido = true; break; }
            }
            if ($parecido) {
                $cpC  = preg_replace('/\D+/', '', (string)($c['cp'] ?? ''));
                $munC = coincNormalizarTexto($c['municipio'] ?? '');
                if ($cp !== '' && $cp === $cpC)         $razones[] = 'Nombre parecido y mismo C.P.';
                elseif ($mun !== '' && $mun === $munC)  $razones[] = 'Nombre parecido y mismo municipio';
                elseif ($cpC === '' && $munC === '')    $razones[] = 'Nombre parecido (en el ERP no tiene dirección para comparar)';
            }
            if (!$razones) continue;
            $duenoId = $c['dueno_id'] !== null ? (int)$c['dueno_id'] : 0;
            $out[] = [
                'tipo'            => $c['tipo'],
                'id'              => (int)$c['id'],
                'nombre'          => (string)$c['nombre'],
                'direccion'       => trim(implode(', ', array_filter([(string)($c['colonia'] ?? ''), (string)($c['municipio'] ?? ''), (string)($c['cp'] ?? '')]))) ?: 'Sin dirección en el ERP',
                'municipio'       => (string)($c['municipio'] ?? ''),
                'vendedor_id'     => null,
                'vendedor_nombre' => $nombresVend[$duenoId] ?? 'Sin vendedor',
                'razon'           => implode(' · ', $razones) . ' (' . COINC_TIPOS_ERP[$c['tipo']] . ')',
            ];
        }
        if ($enTx) $db->exec('RELEASE SAVEPOINT sp_coinc_erp');
    } catch (Throwable $e) {
        if ($enTx) $db->exec('ROLLBACK TO SAVEPOINT sp_coinc_erp');
        error_log('[VISITAS] coincBuscarEnErp: ' . $e->getMessage());
        return [];
    }
    return array_slice($out, 0, 5);
}

/**
 * Deja el cliente en revisión, guarda las coincidencias y avisa al vendedor
 * que lo registró y a la(s) responsable(s) de muestras (si no hay ninguna
 * activa, a los admins como respaldo).
 */
function registrarCoincidencias(PDO $db, array $vendedor, int $clienteId, string $nombreCliente, array $coincidencias, string $origen = 'alta'): void {
    if (!$coincidencias) return;
    $db->prepare("UPDATE clientes SET estado_revision = 'en_revision' WHERE id = ?")->execute([$clienteId]);
    // Si ya había pendientes de este cliente (p. ej. editó otra vez), se reemplazan.
    $db->prepare("UPDATE coincidencias_clientes SET estado = 'anulada', resuelto_en = NOW() WHERE cliente_nuevo_id = ? AND estado = 'pendiente'")
       ->execute([$clienteId]);
    $ins = $db->prepare(
        'INSERT INTO coincidencias_clientes (cliente_nuevo_id, cliente_existente_id, vendedor_nuevo_id, vendedor_existente_id, razon, origen,
                                             existente_tipo, existente_erp_id, vendedor_existente_nombre)
         VALUES (?,?,?,?,?,?,?,?,?)'
    );
    foreach ($coincidencias as $c) {
        $esErp = ($c['tipo'] ?? 'visitas') !== 'visitas';
        $ins->execute([$clienteId, $esErp ? null : $c['id'], $vendedor['id'], $esErp ? null : $c['vendedor_id'],
                       mb_substr($c['razon'], 0, 255), $origen,
                       $c['tipo'] ?? 'visitas', $esErp ? $c['id'] : null, mb_substr((string)$c['vendedor_nombre'], 0, 150)]);
    }
    registrarCambio($db, (int)$vendedor['id'], 'cliente', $clienteId, 'revision',
        "El cliente {$nombreCliente} quedó en revisión: coincide con cliente(s) de otro vendedor",
        ['Coincide con' => [null, implode(' | ', array_map(fn($c) => $c['nombre'] . ' (' . $c['vendedor_nombre'] . ') — ' . $c['razon'], $coincidencias))]]);

    // Vendedor que lo registró: sin decirle de quién es.
    $titulo = 'Cliente en revisión: ' . $nombreCliente;
    $msg = 'Coincide con un cliente que ya atiende otro vendedor. No podrás trabajarlo hasta que la responsable de muestras lo valide.';
    crearAviso($db, (int)$vendedor['id'], 'cliente_revision', $titulo, $msg, 'vendedor/clientes.php');
    try { enviarPushFCM($db, (int)$vendedor['id'], $titulo, $msg, ['tipo' => 'cliente_revision']); } catch (Throwable $e) { error_log('[VISITAS] push coincidencia: ' . $e->getMessage()); }

    // Responsable(s) de muestras, o admins de respaldo.
    $destinos = responsablesMuestras($db);
    $enlace = 'muestras/coincidencias.php';
    if (!$destinos) {
        error_log('[VISITAS] Coincidencia de cliente ' . $clienteId . ' sin responsable de muestras activa; se avisa a los admins.');
        $destinos = $db->query("SELECT id, nombre, email FROM usuarios WHERE rol = 'admin' AND activo = 1")->fetchAll();
        $enlace = 'admin/index.php';
    }
    $tituloR = 'Coincidencia de cliente por validar';
    $msgR = $vendedor['nombre'] . ' registró a ' . $nombreCliente . ', que coincide con un cliente de '
          . implode(', ', array_unique(array_map(fn($c) => $c['vendedor_nombre'], $coincidencias))) . '.';
    $renglones = [
        'Registró'   => $vendedor['nombre'],
        'Cliente'    => $nombreCliente,
        'Origen'     => $origen === 'edicion' ? 'Edición de un cliente' : 'Alta de cliente',
    ];
    foreach ($coincidencias as $i => $c) {
        $renglones['Coincide con' . ($i ? ' (' . ($i + 1) . ')' : '')] = $c['nombre'] . ' — ' . $c['vendedor_nombre'] . "\n" . $c['direccion'] . "\n" . $c['razon'];
    }
    $html = correoMuestraHtml($tituloR, 'Revisa si es el mismo cliente y decide a qué vendedor se asigna. Mientras tanto el vendedor no puede trabajarlo.',
        $renglones, urlBaseVisitas() . 'muestras/coincidencias.php', 'Revisar coincidencia');
    foreach ($destinos as $r) {
        crearAviso($db, (int)$r['id'], 'cliente_coincidencia', $tituloR, $msgR, $enlace);
        if (!empty($r['email'])) enviarCorreoMuestra($r['email'], $tituloR, $html);
    }
}

/** Corta la petición con 409 si el cliente está en revisión o descartado. */
function exigirClienteTrabajable(PDO $db, int $clienteId): void {
    $st = $db->prepare('SELECT estado_revision FROM clientes WHERE id = ?');
    $st->execute([$clienteId]);
    $estado = $st->fetchColumn();
    if ($estado === 'en_revision') {
        jsonResponse(['ok' => false, 'error' => 'Este cliente está en revisión por coincidir con el de otro vendedor. No se puede trabajar hasta que la responsable de muestras lo valide.'], 409);
    }
    if ($estado === 'descartado') {
        jsonResponse(['ok' => false, 'error' => 'Este cliente fue descartado por duplicado.'], 409);
    }
}

/**
 * Resuelve TODAS las coincidencias pendientes de un cliente nuevo.
 * $decision: distintos | se_queda_existente | pasa_a_nuevo
 * $existenteId: obligatorio para se_queda_existente / pasa_a_nuevo.
 * $existenteTipo (18-nov-2026): 'visitas' (id de visitas.clientes) |
 *   'erp_cliente' | 'erp_prospecto' (id del ERP).
 */
function resolverCoincidencia(PDO $db, array $responsable, int $clienteNuevoId, string $decision, int $existenteId = 0, string $nota = '', string $existenteTipo = 'visitas'): array {
    if (!isset(COINC_DECISIONES[$decision])) return ['ok' => false, 'error' => 'Decisión no válida'];

    $st = $db->prepare(
        "SELECT cc.*, cn.nombre AS nuevo_nombre,
                COALESCE(ce.nombre,
                         CASE cc.existente_tipo
                           WHEN 'erp_cliente'   THEN (SELECT c.razon_social FROM public.clientes c WHERE c.id = cc.existente_erp_id)
                           WHEN 'erp_prospecto' THEN (SELECT COALESCE(NULLIF(p.empresa, ''), p.nombre) FROM public.cotizador_prospectos p WHERE p.id = cc.existente_erp_id)
                         END) AS existente_nombre,
                un.nombre AS vendedor_nuevo_nombre,
                COALESCE(ue.nombre, cc.vendedor_existente_nombre) AS vendedor_existente_nombre
         FROM coincidencias_clientes cc
         JOIN clientes cn ON cn.id = cc.cliente_nuevo_id
         LEFT JOIN clientes ce ON ce.id = cc.cliente_existente_id
         JOIN usuarios un ON un.id = cc.vendedor_nuevo_id
         LEFT JOIN usuarios ue ON ue.id = cc.vendedor_existente_id
         WHERE cc.cliente_nuevo_id = ? AND cc.estado = 'pendiente'"
    );
    $st->execute([$clienteNuevoId]);
    $filas = $st->fetchAll();
    if (!$filas) return ['ok' => false, 'error' => 'Esta coincidencia ya fue resuelta'];

    $elegida = null;
    if ($decision !== 'distintos') {
        foreach ($filas as $f) {
            $tipoF = $f['existente_tipo'] ?? 'visitas';
            $idF   = $tipoF === 'visitas' ? (int)$f['cliente_existente_id'] : (int)$f['existente_erp_id'];
            if ($tipoF === $existenteTipo && $idF === $existenteId) $elegida = $f;
        }
        if (!$elegida) return ['ok' => false, 'error' => 'Elige con cuál cliente existente coincide'];
    }
    $f0 = $elegida ?: $filas[0];
    $nota = mb_substr(trim($nota), 0, 500) ?: null;
    $rid = (int)$responsable['id'];
    $esErp = $elegida && ($elegida['existente_tipo'] ?? 'visitas') !== 'visitas';
    $quienNombre = (string)($responsable['nombre'] ?? 'Sistemas');
    $enErpDirecto = [];   // lo que se hizo en el ERP cuando lo existente era del ERP

    $db->beginTransaction();
    try {
        if ($decision === 'distintos') {
            $db->prepare("UPDATE coincidencias_clientes SET estado = 'distintos', nota = ?, resuelto_por = ?, resuelto_en = NOW() WHERE cliente_nuevo_id = ? AND estado = 'pendiente'")
               ->execute([$nota, $rid, $clienteNuevoId]);
            $db->prepare("UPDATE clientes SET estado_revision = NULL WHERE id = ?")->execute([$clienteNuevoId]);
        } else {
            $db->prepare("UPDATE coincidencias_clientes SET estado = ?, nota = ?, resuelto_por = ?, resuelto_en = NOW() WHERE id = ?")
               ->execute([$decision, $nota, $rid, $elegida['id']]);
            $db->prepare("UPDATE coincidencias_clientes SET estado = 'anulada', resuelto_por = ?, resuelto_en = NOW() WHERE cliente_nuevo_id = ? AND estado = 'pendiente'")
               ->execute([$rid, $clienteNuevoId]);
            if ($decision === 'pasa_a_nuevo' && $esErp) {
                // Lo existente es del ERP (de una vendedora interna): pasa a este
                // vendedor y su cliente de Visitas queda ligado (no se descarta).
                $enErpDirecto = coincPasarRegistroErp($db, $elegida, $clienteNuevoId, (int)$elegida['vendedor_nuevo_id'], $quienNombre);
                $db->prepare("UPDATE clientes SET estado_revision = NULL WHERE id = ?")->execute([$clienteNuevoId]);
            } else {
                // El registro nuevo no se borra: queda como evidencia.
                $db->prepare("UPDATE clientes SET estado_revision = 'descartado' WHERE id = ?")->execute([$clienteNuevoId]);
            }
            if ($decision === 'pasa_a_nuevo' && !$esErp) {
                // El cliente (con su historial) cambia de vendedor. Las citas,
                // cotizaciones y muestras pasadas o ya agendadas se quedan a
                // nombre del vendedor anterior (tienen su propio vendedor_id).
                $db->prepare('UPDATE clientes SET vendedor_id = ? WHERE id = ?')
                   ->execute([$elegida['vendedor_nuevo_id'], $elegida['cliente_existente_id']]);
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        error_log('[VISITAS] resolverCoincidencia: ' . $e->getMessage());
        return ['ok' => false, 'error' => $e instanceof DomainException ? $e->getMessage() : 'No se pudo guardar la decisión'];
    }

    // ERP (12-nov-2026). «Son distintos» en un alta: ahora sí aparece en
    // ERP › Prospectos. «Pasa al nuevo vendedor»: el ERP queda igual que VISITAS.
    require_once __DIR__ . '/erp_prospectos_sync.php';
    $enErp = null;
    if ($decision === 'distintos' && ($f0['origen'] ?? '') === 'alta') {
        crearProspectoErpDeAlta($db, $clienteNuevoId);
    } elseif ($decision === 'pasa_a_nuevo' && !$esErp) {
        $enErp = reasignarEnErp($db, (int)$elegida['cliente_existente_id'], (int)$elegida['vendedor_nuevo_id'], $quienNombre);
    } elseif ($decision === 'pasa_a_nuevo' && $esErp) {
        $enErp = $enErpDirecto;
    }

    $etiqueta = COINC_DECISIONES[$decision];
    registrarCambio($db, $rid, 'cliente', $clienteNuevoId, 'revision',
        "Coincidencia de {$f0['nuevo_nombre']} ({$f0['vendedor_nuevo_nombre']}): {$etiqueta}",
        array_filter([
            'Decisión'     => [null, $etiqueta],
            'Cliente existente' => $decision !== 'distintos' ? [null, $f0['existente_nombre'] . ' (' . $f0['vendedor_existente_nombre'] . ')'
                                   . ($esErp ? ' — ' . (COINC_TIPOS_ERP[$f0['existente_tipo']] ?? 'ERP') : '')] : null,
            'Nota'         => $nota ? [null, $nota] : null,
        ]));
    if ($decision === 'pasa_a_nuevo') {
        registrarCambio($db, $rid, 'cliente', $esErp ? $clienteNuevoId : (int)$f0['cliente_existente_id'], 'reasignacion',
            "Reasignó a {$f0['existente_nombre']} de {$f0['vendedor_existente_nombre']} a {$f0['vendedor_nuevo_nombre']}",
            array_filter([
                'Vendedor' => [$f0['vendedor_existente_nombre'], $f0['vendedor_nuevo_nombre']],
                'Enlace de registro' => !empty($enErp['enlaces_cancelados']) ? [null, 'Cancelado (sin llenar)'] : null,
                'Registro recibido' => !empty($enErp['enlaces_movidos']) ? [null, 'Pasa a ' . $f0['vendedor_nuevo_nombre']] : null,
                'Cliente formal' => !empty($enErp['cliente_erp']) ? [null, 'Cambió de agente a ' . $f0['vendedor_nuevo_nombre']] : null,
            ]));
    }

    // Aviso al vendedor que lo registró.
    $vid = (int)$f0['vendedor_nuevo_id'];
    $msgs = [
        'distintos'          => ['Cliente liberado: ' . $f0['nuevo_nombre'], 'Se validó que es un cliente distinto. Ya puedes trabajarlo.'],
        'se_queda_existente' => ['Cliente descartado: ' . $f0['nuevo_nombre'], 'Se determinó que este cliente ya lo atiende otro vendedor. Tu registro quedó descartado.'],
        'pasa_a_nuevo'       => $esErp
            ? ['Cliente asignado: ' . $f0['nuevo_nombre'], 'Se determinó que este cliente lo atiendes tú. Ya puedes trabajarlo.'
               . (!empty($enErp['enlaces_cancelados']) ? ' El enlace de registro que tenía se canceló: si lo quieres hacer cliente, envíale uno nuevo desde el ERP › Ventas › Prospectos.' : '')]
            : ['Cliente asignado: ' . $f0['existente_nombre'], 'Se te asignó este cliente. Ya aparece en tu cartera; tu registro duplicado quedó descartado.'
               . (!empty($enErp['enlaces_cancelados']) ? ' El enlace de registro que tenía se canceló: si lo quieres hacer cliente, envíale uno nuevo desde el ERP › Ventas › Prospectos.' : '')],
    ];
    [$t, $m] = $msgs[$decision];
    if ($nota) $m .= ' Nota: ' . $nota;
    crearAviso($db, $vid, 'cliente_revision_resuelta', $t, $m, 'vendedor/clientes.php');
    try { enviarPushFCM($db, $vid, $t, $m, ['tipo' => 'cliente_revision_resuelta']); } catch (Throwable $e) { error_log('[VISITAS] push coincidencia: ' . $e->getMessage()); }

    // La vendedora interna que lo tenía en el ERP (correo; ella no usa Visitas).
    if ($decision === 'pasa_a_nuevo' && $esErp && !empty($enErpDirecto['correo_anterior'])) {
        $tA = 'Cliente reasignado: ' . $f0['existente_nombre'];
        enviarCorreoMuestra($enErpDirecto['correo_anterior'], $tA, correoMuestraHtml($tA,
            'La responsable de muestras determinó que este cliente lo atiende ' . $f0['vendedor_nuevo_nombre'] . ' (vendedor externo). '
            . 'Lo que ya hiciste (cotizaciones) se queda a tu nombre.',
            array_filter(['Cliente' => $f0['existente_nombre'], 'Ahora lo atiende' => $f0['vendedor_nuevo_nombre'], 'Nota' => $nota]),
            preg_replace('#visitas/$#', 'erp/', urlBaseVisitas()) . 'cotizacion/prospectos.php', 'Abrir el ERP'));
    }

    return ['ok' => true];
}

/**
 * «Pasa al vendedor que lo registró» cuando lo existente es del ERP
 * (18-nov-2026). Dentro de la transacción de resolverCoincidencia(), con la
 * misma conexión (esquema public):
 *   · cliente del ERP  → cambia de agente; el cliente de Visitas queda ligado
 *     (id_cliente_erp) y en etapa «Convertido».
 *   · prospecto del ERP → queda a nombre del vendedor y ligado a su cliente de
 *     Visitas; el enlace de registro sin llenar se cancela y el ya llenado
 *     (sin aprobar) pasa a él. Lo de antes (cotizaciones) se queda con la
 *     vendedora anterior.
 * Devuelve lo hecho + 'correo_anterior' (para avisarle a la vendedora interna).
 */
function coincPasarRegistroErp(PDO $db, array $fila, int $clienteNuevoId, int $vendedorId, string $quien): array {
    $hecho = ['enlaces_cancelados' => 0, 'enlaces_movidos' => 0, 'cliente_erp' => 0, 'correo_anterior' => null];
    $ue = coincUsuarioErp($db, $vendedorId);
    if (!$ue) throw new DomainException('Este vendedor no tiene usuario en el ERP (se busca por su correo). Dalo de alta en el ERP y vuelve a intentar.');
    $erpId = (int)$fila['existente_erp_id'];
    $correoDe = function (?int $idUsuario) use ($db): ?string {
        if (!$idUsuario) return null;
        $st = $db->prepare('SELECT email FROM public.usuarios WHERE id = ?');
        $st->execute([$idUsuario]);
        return ($e = $st->fetchColumn()) ? (string)$e : null;
    };

    if ($fila['existente_tipo'] === 'erp_cliente') {
        $st = $db->prepare('SELECT id_agente FROM public.clientes WHERE id = ?');
        $st->execute([$erpId]);
        $hecho['correo_anterior'] = $correoDe(($a = $st->fetchColumn()) ? (int)$a : null);
        $st = $db->prepare('UPDATE public.clientes SET id_agente = ? WHERE id = ? AND id_agente IS DISTINCT FROM ?');
        $st->execute([$ue['id'], $erpId, $ue['id']]);
        $hecho['cliente_erp'] = $st->rowCount();
        $db->prepare("UPDATE clientes SET id_cliente_erp = ?, etapa = 'convertido', etapa_actualizada_en = CURRENT_TIMESTAMP WHERE id = ?")
           ->execute([$erpId, $clienteNuevoId]);
        return $hecho;
    }

    // erp_prospecto
    $st = $db->prepare('SELECT creado_por FROM public.cotizador_prospectos WHERE id = ?');
    $st->execute([$erpId]);
    $creadoPor = (string)$st->fetchColumn();
    $st = $db->prepare("SELECT id FROM public.usuarios WHERE usuario = ? OR LOWER(TRIM(CONCAT(nombre, ' ', apellidos))) = LOWER(TRIM(?)) ORDER BY (usuario = ?) DESC, id LIMIT 1");
    $st->execute([$creadoPor, $creadoPor, $creadoPor]);
    $hecho['correo_anterior'] = $correoDe(($a = $st->fetchColumn()) ? (int)$a : null);

    $db->prepare('UPDATE public.cotizador_prospectos SET creado_por = ?, visitas_cliente_id = ? WHERE id = ?')
       ->execute([$ue['usuario'], $clienteNuevoId, $erpId]);
    $notaCancel = '[Cancelado: el prospecto pasó a ' . $ue['nombre'] . '; decidió ' . $quien . ' el ' . date('d/m/Y H:i') . ']';
    $st = $db->prepare("UPDATE public.invitaciones_cliente SET estado = 'expirada', notas = CONCAT_WS(E'\\n', NULLIF(notas, ''), ?::text)
                         WHERE id_prospecto_origen = ? AND estado = 'pendiente'");
    $st->execute([$notaCancel, $erpId]);
    $hecho['enlaces_cancelados'] = $st->rowCount();
    $st = $db->prepare("UPDATE public.invitaciones_cliente i SET id_agente = ?
                         WHERE i.id_prospecto_origen = ? AND i.estado = 'completada'
                           AND EXISTS (SELECT 1 FROM public.clientes_solicitudes s WHERE s.token = i.token
                                          AND s.estado IN ('pendiente', 'aprobada_ventas'))");
    $st->execute([$ue['id'], $erpId]);
    $hecho['enlaces_movidos'] = $st->rowCount();
    $db->prepare("UPDATE public.clientes_solicitudes s SET id_agente = ?
                    FROM public.invitaciones_cliente i
                   WHERE s.token = i.token AND i.id_prospecto_origen = ?
                     AND s.estado IN ('pendiente', 'aprobada_ventas')")->execute([$ue['id'], $erpId]);
    return $hecho;
}

/**
 * Cita de un cliente que ya no es del vendedor de la cita (reasignado con
 * "pasa_a_nuevo"): se puede atender o cancelar, pero no reprogramar.
 */
function citaDeClienteReasignado(PDO $db, int $citaId): bool {
    $st = $db->prepare('SELECT 1 FROM citas c JOIN clientes cl ON cl.id = c.cliente_id WHERE c.id = ? AND cl.vendedor_id <> c.vendedor_id');
    $st->execute([$citaId]);
    return (bool)$st->fetchColumn();
}
