<?php
// Coincidencias de clientes entre vendedores, para la responsable de
// muestras y Sistemas (muestras/coincidencias.php). Ver includes/coincidencias.php.
//   GET  ?estado=pendientes|resueltas  -> {grupos: [{nuevo, coincidencias: [...]}]}
//   POST cliente_nuevo_id, decision (distintos|se_queda_existente|pasa_a_nuevo),
//        existente_id (si no es "distintos"), nota?
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/coincidencias.php';

// La responsable de muestras y, desde el 12-nov-2026, Sistemas/Dirección (admin).
$u  = requireAnyRole(['muestras', 'admin']);
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $resueltas = ($_GET['estado'] ?? '') === 'resueltas';
    $sql = "SELECT cc.id, cc.cliente_nuevo_id, cc.cliente_existente_id, cc.razon, cc.origen, cc.estado,
                   cc.nota, cc.creado_en, cc.resuelto_en, ur.nombre AS resuelto_por_nombre,
                   cn.nombre AS n_nombre, cn.direccion AS n_direccion, cn.telefono AS n_telefono,
                   cn.nombre_contacto AS n_contacto, cn.municipio AS n_municipio, cn.etapa AS n_etapa,
                   cn.created_at AS n_creado, cn.estado_revision AS n_estado_revision,
                   un.id AS n_vendedor_id, un.nombre AS n_vendedor,
                   ce.nombre AS e_nombre, ce.direccion AS e_direccion, ce.telefono AS e_telefono,
                   ce.nombre_contacto AS e_contacto, ce.municipio AS e_municipio, ce.etapa AS e_etapa,
                   ce.created_at AS e_creado,
                   ue.id AS e_vendedor_id, ue.nombre AS e_vendedor,
                   (SELECT COUNT(*) FROM citas ci WHERE ci.cliente_id = ce.id AND ci.estado = 'completada') AS e_visitas,
                   (SELECT COUNT(*) FROM citas ci WHERE ci.cliente_id = ce.id AND ci.estado = 'pendiente') AS e_citas_pendientes
            FROM coincidencias_clientes cc
            JOIN clientes cn ON cn.id = cc.cliente_nuevo_id
            JOIN clientes ce ON ce.id = cc.cliente_existente_id
            JOIN usuarios un ON un.id = cc.vendedor_nuevo_id
            JOIN usuarios ue ON ue.id = cc.vendedor_existente_id
            LEFT JOIN usuarios ur ON ur.id = cc.resuelto_por
            WHERE " . ($resueltas ? "cc.estado NOT IN ('pendiente', 'anulada')" : "cc.estado = 'pendiente'") . "
            ORDER BY " . ($resueltas ? 'cc.resuelto_en DESC' : 'cc.creado_en ASC') . ", cc.id
            LIMIT 300";
    $grupos = [];
    foreach ($db->query($sql)->fetchAll() as $r) {
        // Pendientes: se agrupan por cliente nuevo (una decisión por grupo).
        $k = $resueltas ? 'r' . $r['id'] : 'n' . $r['cliente_nuevo_id'];
        if (!isset($grupos[$k])) {
            $grupos[$k] = [
                'nuevo' => [
                    'id' => (int)$r['cliente_nuevo_id'], 'nombre' => $r['n_nombre'], 'direccion' => $r['n_direccion'],
                    'telefono' => $r['n_telefono'], 'contacto' => $r['n_contacto'], 'municipio' => $r['n_municipio'],
                    'etapa' => $r['n_etapa'], 'creado' => $r['n_creado'], 'estado_revision' => $r['n_estado_revision'],
                    'vendedor_id' => (int)$r['n_vendedor_id'], 'vendedor' => $r['n_vendedor'],
                ],
                'origen' => $r['origen'], 'creado_en' => $r['creado_en'],
                'coincidencias' => [],
            ];
        }
        $grupos[$k]['coincidencias'][] = [
            'id' => (int)$r['cliente_existente_id'], 'nombre' => $r['e_nombre'], 'direccion' => $r['e_direccion'],
            'telefono' => $r['e_telefono'], 'contacto' => $r['e_contacto'], 'municipio' => $r['e_municipio'],
            'etapa' => $r['e_etapa'], 'creado' => $r['e_creado'],
            'vendedor_id' => (int)$r['e_vendedor_id'], 'vendedor' => $r['e_vendedor'],
            'visitas' => (int)$r['e_visitas'], 'citas_pendientes' => (int)$r['e_citas_pendientes'],
            'razon' => $r['razon'], 'estado' => $r['estado'], 'nota' => $r['nota'],
            'resuelto_en' => $r['resuelto_en'], 'resuelto_por' => $r['resuelto_por_nombre'],
            'decision' => COINC_DECISIONES[$r['estado']] ?? null,
        ];
    }
    $pend = (int)$db->query("SELECT COUNT(DISTINCT cliente_nuevo_id) FROM coincidencias_clientes WHERE estado = 'pendiente'")->fetchColumn();
    jsonResponse(['ok' => true, 'pendientes' => $pend, 'grupos' => array_values($grupos)]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

$res = resolverCoincidencia(
    $db, $u,
    (int)($_POST['cliente_nuevo_id'] ?? 0),
    (string)($_POST['decision'] ?? ''),
    (int)($_POST['existente_id'] ?? 0),
    (string)($_POST['nota'] ?? '')
);
jsonResponse($res, $res['ok'] ? 200 : 400);
