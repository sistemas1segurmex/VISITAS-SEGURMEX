<?php
// Revisa si el vendedor ya tiene un cliente muy parecido al que está a punto
// de registrar (mismo nombre, mismo teléfono, ubicación muy cercana, o misma
// colonia con una calle parecida). No bloquea nada: solo devuelve candidatos
// para que el frontend le pida confirmación al vendedor antes de guardar.
//
// GET ?nombre=...&telefono=...&lat=...&lng=...&colonia=...&calle_numero=...

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$u  = requireRole('vendedor');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

// Qué tan cerca (en metros) consideramos que dos puntos son "el mismo lugar".
define('RADIO_DUPLICADO_METROS', 120);
// Qué tan parecidos deben ser dos textos (0-100) para considerarlos "el mismo".
define('SIMILITUD_MINIMA', 75);

$nombre      = trim($_GET['nombre'] ?? '');
$telefono    = trim($_GET['telefono'] ?? '');
$colonia     = trim($_GET['colonia'] ?? '');
$calleNumero = trim($_GET['calle_numero'] ?? '');
$lat = (isset($_GET['lat']) && $_GET['lat'] !== '') ? (float)$_GET['lat'] : null;
$lng = (isset($_GET['lng']) && $_GET['lng'] !== '') ? (float)$_GET['lng'] : null;

if ($nombre === '') {
    jsonResponse(['ok' => true, 'candidatos' => []]);
}

function normalizarTexto(string $s): string {
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = str_replace(
        ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'],
        ['a', 'e', 'i', 'o', 'u', 'u', 'n'],
        $s
    );
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
    return trim($s);
}

$stmt = $db->prepare(
    'SELECT id, nombre, direccion, telefono, lat, lng, colonia, calle_numero
     FROM clientes WHERE vendedor_id = ?'
);
$stmt->execute([$u['id']]);
$clientes = $stmt->fetchAll();

$nombreObjetivo   = normalizarTexto($nombre);
$telefonoObjetivo = preg_replace('/\D+/', '', $telefono);
$coloniaObjetivo  = normalizarTexto($colonia);
$calleObjetivo    = normalizarTexto($calleNumero);

$candidatos = [];

foreach ($clientes as $c) {
    $razones = [];

    $nombreExistente = normalizarTexto($c['nombre']);
    if ($nombreExistente !== '' && $nombreObjetivo !== '') {
        if ($nombreExistente === $nombreObjetivo) {
            $razones[] = 'Mismo nombre';
        } else {
            similar_text($nombreExistente, $nombreObjetivo, $porcentaje);
            if ($porcentaje >= SIMILITUD_MINIMA) {
                $razones[] = 'Nombre muy parecido';
            }
        }
    }

    $telefonoExistente = preg_replace('/\D+/', '', (string)($c['telefono'] ?? ''));
    if ($telefonoObjetivo !== '' && $telefonoExistente !== '' && $telefonoExistente === $telefonoObjetivo) {
        $razones[] = 'Mismo teléfono';
    }

    if ($lat !== null && $lng !== null && $c['lat'] !== null && $c['lng'] !== null) {
        $dist = haversineDistance($lat, $lng, (float)$c['lat'], (float)$c['lng']);
        if ($dist <= RADIO_DUPLICADO_METROS) {
            $razones[] = 'A solo ' . round($dist) . ' m de distancia';
        }
    }

    if (empty($razones) && $coloniaObjetivo !== '' && $calleObjetivo !== '') {
        $coloniaExistente = normalizarTexto((string)($c['colonia'] ?? ''));
        $calleExistente   = normalizarTexto((string)($c['calle_numero'] ?? ''));
        if ($coloniaExistente !== '' && $coloniaExistente === $coloniaObjetivo && $calleExistente !== '') {
            similar_text($calleExistente, $calleObjetivo, $porcentajeCalle);
            if ($porcentajeCalle >= 70) {
                $razones[] = 'Misma colonia y calle parecida';
            }
        }
    }

    if (!empty($razones)) {
        $candidatos[] = [
            'id'        => $c['id'],
            'nombre'    => $c['nombre'],
            'direccion' => $c['direccion'],
            'razon'     => implode(' · ', $razones),
        ];
    }
}

jsonResponse(['ok' => true, 'candidatos' => $candidatos]);
