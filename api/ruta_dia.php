<?php
// Historial de puntos GPS de cada vendedor en un día, para dibujar su
// recorrido en el mapa del admin (líneas de ruta, no solo el último punto
// como ya hacía api/tracking.php). Mismo criterio de permisos que el resto
// del panel: solo admin.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$u  = requireRole('admin');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

$fecha = $_GET['fecha'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    jsonResponse(['ok' => false, 'error' => 'Fecha inválida'], 400);
}

// Límite por vendedor para no mandar un payload gigante si alguien dejó el
// tracking encendido todo el día (reporta cada pocos segundos/minutos).
const MAX_PUNTOS_POR_VENDEDOR = 300;

$stmt = $db->prepare(
    "SELECT vendedor_id, lat, lng, fecha_hora
     FROM tracking_ubicaciones
     WHERE DATE(fecha_hora) = ?
     ORDER BY vendedor_id, fecha_hora ASC"
);
$stmt->execute([$fecha]);

// Agrupamos por vendedor en PHP (más simple que armar el muestreo en SQL) y
// recortamos parejo si algún vendedor trae de más — nos quedamos con puntos
// espaciados a lo largo del día, no solo los primeros N.
$porVendedor = [];
foreach ($stmt->fetchAll() as $r) {
    $porVendedor[$r['vendedor_id']][] = ['lat' => (float)$r['lat'], 'lng' => (float)$r['lng'], 'fecha_hora' => $r['fecha_hora']];
}

$rutas = [];
foreach ($porVendedor as $vendedorId => $puntos) {
    $total = count($puntos);
    if ($total > MAX_PUNTOS_POR_VENDEDOR) {
        $paso = $total / MAX_PUNTOS_POR_VENDEDOR;
        $muestreados = [];
        for ($i = 0.0; $i < $total; $i += $paso) {
            $muestreados[] = $puntos[(int)$i];
        }
        $puntos = $muestreados;
    }
    $rutas[] = ['vendedor_id' => (int)$vendedorId, 'puntos' => $puntos];
}

jsonResponse(['ok' => true, 'fecha' => $fecha, 'rutas' => $rutas]);
