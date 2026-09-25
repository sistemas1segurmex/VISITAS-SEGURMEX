<?php
// Correcciones de la ubicación de clientes hechas en la primera visita (ver
// api/checkin.php y correcciones_ubicacion en
// supabase/migrations/20260926120000_confirmar_ubicacion_cliente.sql), para
// admin/ubicaciones.php.
//
// GET  ?estado=por_revisar|todas -> lista, más recientes primero.
// POST action=aprobar  id=..     -> queda 'aprobada' (el pin nuevo se queda).
// POST action=revertir id=..     -> pin anterior de vuelta, el cliente queda
//                                  por confirmar y esa entrada pasa a
//                                  "Fuera de zona" (verificado = 0).

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$u  = requireRole('admin');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $soloPorRevisar = ($_GET['estado'] ?? 'por_revisar') !== 'todas';
    $stmt = $db->query(
        "SELECT cu.id, cu.estado, cu.nota, cu.creado_en, cu.revisado_en,
                cu.lat_anterior, cu.lng_anterior, cu.lat_nueva, cu.lng_nueva,
                cu.distancia_metros, cu.accuracy, cu.checkin_id,
                cl.id AS cliente_id, cl.nombre AS cliente_nombre, cl.direccion,
                v.nombre AS vendedor_nombre, r.nombre AS revisado_por_nombre
         FROM correcciones_ubicacion cu
         JOIN clientes cl ON cl.id = cu.cliente_id
         JOIN usuarios v ON v.id = cu.vendedor_id
         LEFT JOIN usuarios r ON r.id = cu.revisado_por
         " . ($soloPorRevisar ? "WHERE cu.estado = 'por_revisar'" : '') . "
         ORDER BY cu.creado_en DESC LIMIT 200"
    );
    $porRevisar = (int)$db->query("SELECT COUNT(*) FROM correcciones_ubicacion WHERE estado = 'por_revisar'")->fetchColumn();
    jsonResponse(['ok' => true, 'correcciones' => $stmt->fetchAll(), 'por_revisar' => $porRevisar]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    $stmt = $db->prepare(
        'SELECT cu.*, cl.nombre AS cliente_nombre, cl.lat AS cliente_lat, cl.lng AS cliente_lng
         FROM correcciones_ubicacion cu JOIN clientes cl ON cl.id = cu.cliente_id WHERE cu.id = ?'
    );
    $stmt->execute([$id]);
    $corr = $stmt->fetch();
    if (!$corr) {
        jsonResponse(['ok' => false, 'error' => 'Corrección no encontrada'], 404);
    }
    if (!in_array($corr['estado'], ['aplicada', 'por_revisar'], true)) {
        jsonResponse(['ok' => false, 'error' => 'Esta corrección ya se revisó'], 400);
    }

    if ($accion === 'aprobar') {
        $db->prepare("UPDATE correcciones_ubicacion SET estado = 'aprobada', revisado_por = ?, revisado_en = CURRENT_TIMESTAMP WHERE id = ?")
           ->execute([$u['id'], $id]);
        jsonResponse(['ok' => true]);
    }

    if ($accion === 'revertir') {
        // Si el pin se volvió a mover después (edición, otra corrección),
        // no se pisa ese cambio más reciente.
        $pinSigueIgual = abs((float)$corr['cliente_lat'] - (float)$corr['lat_nueva']) < 0.000001
                      && abs((float)$corr['cliente_lng'] - (float)$corr['lng_nueva']) < 0.000001;
        $db->beginTransaction();
        if ($pinSigueIgual) {
            $db->prepare(
                "UPDATE clientes SET lat = ?, lng = ?, ubicacion_confirmada = FALSE, ubicacion_fuente = 'registro', ubicacion_confirmada_en = NULL
                 WHERE id = ?"
            )->execute([$corr['lat_anterior'], $corr['lng_anterior'], $corr['cliente_id']]);
        }
        $db->prepare('UPDATE checkins SET verificado = 0, ubicacion_corregida = FALSE WHERE id = ?')->execute([$corr['checkin_id']]);
        $db->prepare("UPDATE correcciones_ubicacion SET estado = 'revertida', revisado_por = ?, revisado_en = CURRENT_TIMESTAMP WHERE id = ?")
           ->execute([$u['id'], $id]);
        $db->commit();
        registrarCambio($db, (int)$corr['vendedor_id'], 'cliente', (int)$corr['cliente_id'], 'edicion',
            "{$u['nombre']} revirtió la corrección de ubicación de {$corr['cliente_nombre']}", [
            'Ubicación' => [$corr['lat_nueva'] . ', ' . $corr['lng_nueva'], $pinSigueIgual ? $corr['lat_anterior'] . ', ' . $corr['lng_anterior'] : '(sin cambio: el pin ya se había movido)'],
        ]);
        jsonResponse(['ok' => true, 'pin_restaurado' => $pinSigueIgual]);
    }

    jsonResponse(['ok' => false, 'error' => 'Acción no reconocida'], 400);
}

jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
