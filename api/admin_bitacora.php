<?php
// Bitácora del admin: dos historiales separados --
//  - "cambios": altas/ediciones/bajas que hacen los vendedores sobre
//    clientes, citas, cotizaciones y muestras (ver registrarCambio() en
//    includes/helpers.php, llamada desde cada endpoint que escribe).
//  - "accesos": cada intento de login, correcto o no (ver registrarAcceso(),
//    llamada desde login.php). No confundir con usuarios_sesiones: esa
//    tabla es "quién sigue conectado ahorita" y se borra sola; esta es
//    historial permanente.
// Ambas tablas son insert-only -- ver supabase/migrations/
// 20260914120000_bitacora_cambios_accesos.sql.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$admin = requireRole('admin');
$db    = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

$accion = $_GET['accion'] ?? 'resumen';

// "7" / "30" / "0" (0 = sin límite de fecha) -- mismo criterio simple en
// ambas pestañas, ver los <select> de assets/js/admin_bitacora.js.
function filtroDias(): string {
    $dias = (int)($_GET['dias'] ?? 7);
    return $dias > 0 ? "INTERVAL '{$dias} days'" : '';
}

if ($accion === 'resumen') {
    $hoy = "creado_en >= CURRENT_DATE";

    $stmt = $db->query("SELECT COUNT(*) FROM bitacora_cambios WHERE $hoy");
    $cambiosHoy = (int)$stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(*) FROM bitacora_cambios WHERE accion='alta' AND creado_en >= NOW() - INTERVAL '7 days'");
    $altas7d = (int)$stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(*) FROM bitacora_cambios WHERE accion='edicion' AND creado_en >= NOW() - INTERVAL '7 days'");
    $ediciones7d = (int)$stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(*) FROM bitacora_cambios WHERE accion='baja' AND creado_en >= NOW() - INTERVAL '30 days'");
    $bajasMes = (int)$stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(*) FROM usuarios_accesos_historial WHERE resultado='correcto' AND $hoy");
    $accesosHoy = (int)$stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(*) FROM usuarios_accesos_historial WHERE resultado IN ('fallido','bloqueado_limite') AND creado_en >= NOW() - INTERVAL '7 days'");
    $fallidos7d = (int)$stmt->fetchColumn();

    // Mismo criterio de "sigue conectado" que contarSesionesActivas() en
    // helpers.php, pero contando vendedores distintos, no sesiones.
    $stmt = $db->query(
        "SELECT COUNT(DISTINCT usuario_id) FROM usuarios_sesiones
         WHERE ultima_actividad >= NOW() - INTERVAL '" . SESION_VENTANA_INACTIVIDAD_MIN . " minutes'"
    );
    $conectadosAhora = (int)$stmt->fetchColumn();

    jsonResponse([
        'ok' => true,
        'cambios_hoy' => $cambiosHoy,
        'altas_7d' => $altas7d,
        'ediciones_7d' => $ediciones7d,
        'bajas_mes' => $bajasMes,
        'accesos_hoy' => $accesosHoy,
        'fallidos_7d' => $fallidos7d,
        'conectados_ahora' => $conectadosAhora,
    ]);
}

if ($accion === 'cambios') {
    $vendedorId = (int)($_GET['vendedor_id'] ?? 0);
    $tipoAccion = $_GET['tipo_accion'] ?? '';
    $entidad    = $_GET['entidad'] ?? '';
    $q          = trim($_GET['q'] ?? '');
    $limit      = min(200, max(1, (int)($_GET['limit'] ?? 80)));
    $offset     = max(0, (int)($_GET['offset'] ?? 0));

    $where  = ['1=1'];
    $params = [];

    if ($vendedorId) { $where[] = 'bc.vendedor_id = ?'; $params[] = $vendedorId; }
    if (in_array($tipoAccion, ['alta', 'edicion', 'baja'], true)) { $where[] = 'bc.accion = ?'; $params[] = $tipoAccion; }
    if (in_array($entidad, ['cliente', 'cita', 'cotizacion', 'muestra', 'prospeccion'], true)) { $where[] = 'bc.entidad = ?'; $params[] = $entidad; }
    if ($q !== '') { $where[] = '(bc.resumen ILIKE ? OR u.nombre ILIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
    if ($dias = filtroDias()) { $where[] = "bc.creado_en >= NOW() - $dias"; }

    $sql = 'SELECT bc.id, bc.entidad, bc.entidad_id, bc.accion, bc.resumen, bc.cambios, bc.creado_en,
                   u.id AS vendedor_id, u.nombre AS vendedor_nombre
            FROM bitacora_cambios bc
            JOIN usuarios u ON u.id = bc.vendedor_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY bc.creado_en DESC
            LIMIT ? OFFSET ?';
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $filas = $stmt->fetchAll();
    foreach ($filas as &$f) {
        $f['cambios'] = $f['cambios'] ? json_decode($f['cambios'], true) : null;
    }
    jsonResponse(['ok' => true, 'cambios' => $filas]);
}

if ($accion === 'accesos') {
    $vendedorId = (int)($_GET['vendedor_id'] ?? 0);
    $resultado  = $_GET['resultado'] ?? ''; // '', 'correcto', 'fallido' (agrupa fallido+bloqueado_limite)
    $q          = trim($_GET['q'] ?? '');
    $limit      = min(200, max(1, (int)($_GET['limit'] ?? 80)));
    $offset     = max(0, (int)($_GET['offset'] ?? 0));

    $where  = ['1=1'];
    $params = [];

    if ($vendedorId) { $where[] = 'ah.usuario_id = ?'; $params[] = $vendedorId; }
    if ($resultado === 'correcto') { $where[] = "ah.resultado = 'correcto'"; }
    if ($resultado === 'fallido')  { $where[] = "ah.resultado IN ('fallido','bloqueado_limite')"; }
    if ($q !== '') { $where[] = '(ah.email_intentado ILIKE ? OR u.nombre ILIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
    if ($dias = filtroDias()) { $where[] = "ah.creado_en >= NOW() - $dias"; }

    $sql = 'SELECT ah.id, ah.usuario_id, ah.email_intentado, ah.resultado, ah.motivo, ah.ip, ah.user_agent, ah.creado_en,
                   u.nombre AS vendedor_nombre
            FROM usuarios_accesos_historial ah
            LEFT JOIN usuarios u ON u.id = ah.usuario_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY ah.creado_en DESC
            LIMIT ? OFFSET ?';
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(['ok' => true, 'accesos' => $stmt->fetchAll()]);
}

jsonResponse(['ok' => false, 'error' => 'Acción no reconocida'], 400);
