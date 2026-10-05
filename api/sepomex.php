<?php
// Catálogo de estados/municipios/colonias (SEPOMEX) para los selects en
// cascada del formulario de nuevo cliente.
//
// GET ?tipo=estados
// GET ?tipo=municipios&estado=...
// GET ?tipo=colonias&estado=...&municipio=...
// GET ?tipo=cp&cp=20367
// GET ?tipo=buscar_colonia&q=tlalchichilpan[&estado=...]

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

requireLogin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

$tipo = $_GET['tipo'] ?? '';

if ($tipo === 'estados') {
    $stmt = $db->query('SELECT DISTINCT estado FROM sepomex_colonias ORDER BY estado');
    jsonResponse(['ok' => true, 'estados' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
}

if ($tipo === 'municipios') {
    $estado = trim($_GET['estado'] ?? '');
    if ($estado === '') jsonResponse(['ok' => false, 'error' => 'Falta el estado'], 400);
    $stmt = $db->prepare('SELECT DISTINCT municipio FROM sepomex_colonias WHERE estado = ? ORDER BY municipio');
    $stmt->execute([$estado]);
    jsonResponse(['ok' => true, 'municipios' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
}

if ($tipo === 'colonias') {
    $estado = trim($_GET['estado'] ?? '');
    $municipio = trim($_GET['municipio'] ?? '');
    if ($estado === '' || $municipio === '') jsonResponse(['ok' => false, 'error' => 'Falta estado o municipio'], 400);
    $stmt = $db->prepare(
        'SELECT DISTINCT asentamiento, cp FROM sepomex_colonias
         WHERE estado = ? AND municipio = ?
         ORDER BY asentamiento'
    );
    $stmt->execute([$estado, $municipio]);
    jsonResponse(['ok' => true, 'colonias' => $stmt->fetchAll()]);
}

// Todas las colonias de un código postal, con su estado y municipio, para
// que el vendedor escriba el CP y el resto se llene solo.
if ($tipo === 'cp') {
    $cp = trim($_GET['cp'] ?? '');
    if (!preg_match('/^\d{5}$/', $cp)) jsonResponse(['ok' => false, 'error' => 'El código postal debe tener 5 dígitos'], 400);
    $stmt = $db->prepare(
        'SELECT DISTINCT estado, municipio, asentamiento, cp FROM sepomex_colonias
         WHERE cp = ?
         ORDER BY estado, municipio, asentamiento'
    );
    $stmt->execute([$cp]);
    jsonResponse(['ok' => true, 'colonias' => $stmt->fetchAll()]);
}

// Colonias por nombre (sin importar acentos ni mayúsculas), para cuando el
// vendedor no sabe el CP o el que tiene no coincide con SEPOMEX.
//
// Se busca palabra por palabra, no la frase exacta: "col centro",
// "fracc las hilamas", "las americas leon" o "tlalchichilpan san mateo"
// encuentran su colonia. Cada palabra puede estar en el nombre de la colonia,
// el municipio o el estado. "2a", "secc", "III"... no son obligatorias (en
// SEPOMEX se escriben de mil formas), solo suben la colonia en la lista.
//
// Orden: primero las que tienen todas las palabras en el nombre, y entre
// ellas las de municipios donde este vendedor (y luego cualquiera) ya tiene
// clientes -- si no, "Centro" o "San Juan" llenaban la lista con
// Aguascalientes y nunca salía León.
if ($tipo === 'buscar_colonia') {
    $q = trim($_GET['q'] ?? '');
    $estado = trim($_GET['estado'] ?? '');
    if (mb_strlen($q) < 3) jsonResponse(['ok' => false, 'error' => 'Escribe al menos 3 letras'], 400);

    $texto = mb_strtolower($q, 'UTF-8');
    $texto = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'u', 'n'], $texto);
    $texto = trim(preg_replace('/[^a-z0-9]+/', ' ', $texto));
    // Quita el tipo de asentamiento al inicio: "col.", "fracc", "colonia"...
    $frase = trim(preg_replace('/^(col|colonia|fracc|frac|fraccionamiento|fraccto)\s+/', '', $texto));

    $vacias = ['de', 'del', 'la', 'las', 'los', 'el', 'y', 'en',
               'col', 'colonia', 'fracc', 'frac', 'fraccionamiento', 'fraccto'];
    $opcionales = '/^(\d+[a-z]{0,3}|i|ii|iii|iv|v|vi|vii|viii|ix|x|secc|sec|seccion|etapa|sector|primera|segunda|tercera|cuarta|quinta)$/';
    $obligatorias = [];
    $extra = [];
    foreach (array_unique(explode(' ', $texto)) as $p) {
        if ($p === '' || in_array($p, $vacias, true)) continue;
        if (preg_match($opcionales, $p)) $extra[] = $p;
        elseif (strlen($p) >= 2) $obligatorias[] = $p;
    }
    if (!$obligatorias) { $obligatorias = $extra; $extra = []; }
    if (!$obligatorias) jsonResponse(['ok' => true, 'colonias' => []]);

    $sinAcentos = "translate(lower(%s), 'áéíóúüñ', 'aeiouun')";
    $nombre = sprintf($sinAcentos, 's.asentamiento');
    $todo = sprintf($sinAcentos, "s.asentamiento || ' ' || s.municipio || ' ' || s.estado");

    // Cuántas palabras (obligatorias y opcionales) trae el nombre de la colonia.
    $puntos = [];
    $paramsPuntos = [];
    foreach (array_merge($obligatorias, $extra) as $p) {
        $puntos[] = "CASE WHEN $nombre LIKE ? THEN 1 ELSE 0 END";
        $paramsPuntos[] = '%' . $p . '%';
    }

    $where = [];
    $paramsWhere = [];
    foreach ($obligatorias as $p) {
        $where[] = "$todo LIKE ?";
        $paramsWhere[] = '%' . $p . '%';
    }
    if ($estado !== '') {
        $where[] = 's.estado = ?';
        $paramsWhere[] = $estado;
    }

    $sql = "WITH zona AS (
                SELECT estado, municipio, MAX(CASE WHEN vendedor_id = ? THEN 1 ELSE 0 END) AS propio
                FROM clientes
                WHERE estado IS NOT NULL AND municipio IS NOT NULL
                GROUP BY estado, municipio
            ),
            encontradas AS (
                SELECT DISTINCT s.estado, s.municipio, s.asentamiento, s.cp
                FROM sepomex_colonias s
                WHERE " . implode(' AND ', $where) . "
            )
            SELECT s.estado, s.municipio, s.asentamiento, s.cp
            FROM encontradas s
            LEFT JOIN zona z ON z.estado = s.estado AND z.municipio = s.municipio
            ORDER BY (" . implode(' + ', $puntos) . ") DESC,
                     COALESCE(z.propio, -1) DESC,
                     CASE WHEN regexp_replace($nombre, '[^a-z0-9]+', ' ', 'g') = ? THEN 0
                          WHEN $nombre LIKE ? THEN 1 ELSE 2 END,
                     s.estado, s.municipio, s.asentamiento
            LIMIT 50";
    $params = array_merge(
        [(int)($_SESSION['usuario_id'] ?? 0)],
        $paramsWhere,
        $paramsPuntos,
        [$frase, $frase . '%']
    );
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(['ok' => true, 'colonias' => $stmt->fetchAll()]);
}

jsonResponse(['ok' => false, 'error' => 'Tipo de consulta no válido'], 400);
