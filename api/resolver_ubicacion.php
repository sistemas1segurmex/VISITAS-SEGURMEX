<?php
// Resuelve un link corto de ubicación (maps.app.goo.gl, goo.gl/maps, etc.)
// al link largo de Google Maps y saca las coordenadas, para cuando el
// cliente comparte su ubicación por WhatsApp o Maps y el vendedor la pega
// en el formulario. El navegador no puede seguir esas redirecciones por
// CORS, por eso se hace aquí.
//
// GET ?url=https://maps.app.goo.gl/xxxx
// -> { ok, lat, lng, url_final, nombre }  (nombre = lugar buscado cuando el
//    link no trae coordenadas, para que el vendedor lo busque)
//
// Solo se siguen redirecciones hacia dominios de Google/Apple Maps: nunca
// se hace una petición a un host arbitrario que mande el usuario.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin();

const HOSTS_PERMITIDOS = [
    'maps.app.goo.gl', 'goo.gl', 'g.co',
    'maps.google.com', 'www.google.com', 'google.com', 'www.google.com.mx', 'google.com.mx', 'maps.google.com.mx',
    'maps.apple.com',
];

function hostPermitido(string $url): bool {
    $partes = parse_url($url);
    if (!$partes || ($partes['scheme'] ?? '') !== 'https') return false;
    return in_array(strtolower($partes['host'] ?? ''), HOSTS_PERMITIDOS, true);
}

// Mismos patrones que extraerCoordenadas() en assets/js/direccion-cliente.js.
function coordenadasDe(string $texto): ?array {
    $texto = urldecode($texto);
    $patrones = [
        '/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/',
        '/[?&](?:q|query|ll|sll|destination|daddr|center|markers)=(?:loc:)?(-?\d+\.\d+)\s*,\s*(-?\d+\.\d+)/',
        '/@(-?\d+\.\d+),(-?\d+\.\d+)/',
        '/\/search\/(-?\d+\.\d+),\+?(-?\d+\.\d+)/',
    ];
    foreach ($patrones as $p) {
        if (preg_match($p, $texto, $m)) {
            $lat = (float)$m[1];
            $lng = (float)$m[2];
            if (abs($lat) <= 90 && abs($lng) <= 180) return [$lat, $lng];
        }
    }
    return null;
}

function nombreDe(string $url): ?string {
    $url = urldecode($url);
    if (preg_match('#/maps/(?:place|search)/([^/@?]+)#', $url, $m)) return trim(str_replace('+', ' ', $m[1]));
    if (preg_match('/[?&](?:q|query)=([^&]+)/', $url, $m)) return trim(str_replace('+', ' ', $m[1]));
    return null;
}

$url = preg_replace('#^http://#i', 'https://', trim($_GET['url'] ?? ''));
if (!hostPermitido($url)) {
    jsonResponse(['ok' => false, 'error' => 'Ese link no es de Google Maps ni de Apple Maps.'], 400);
}

$cuerpo = '';
for ($saltos = 0; $saltos < 6; $saltos++) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER         => false,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Linux; Android 13) VisitasSegurmex',
        CURLOPT_HTTPHEADER     => ['Accept-Language: es-MX,es;q=0.9'],
    ]);
    $respuesta = curl_exec($ch);
    $codigo = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $siguiente = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);

    if ($respuesta === false) {
        jsonResponse(['ok' => false, 'error' => 'No se pudo abrir el link. Revisa que esté completo.'], 502);
    }
    if ($codigo >= 300 && $codigo < 400 && $siguiente) {
        // Ya trae coordenadas: no hace falta seguir redireccionando.
        if (coordenadasDe($siguiente)) { $url = $siguiente; break; }
        if (!hostPermitido($siguiente)) break;
        $url = $siguiente;
        continue;
    }
    $cuerpo = (string)$respuesta;
    break;
}

$coords = coordenadasDe($url) ?? ($cuerpo !== '' ? coordenadasDe(substr($cuerpo, 0, 500000)) : null);
jsonResponse([
    'ok'        => true,
    'lat'       => $coords[0] ?? null,
    'lng'       => $coords[1] ?? null,
    'url_final' => $url,
    'nombre'    => $coords ? null : nombreDe($url),
]);
