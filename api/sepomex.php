<?php
// Catálogo de estados/municipios/colonias (SEPOMEX) para los selects en
// cascada del formulario de nuevo cliente.
//
// GET ?tipo=estados
// GET ?tipo=municipios&estado=...
// GET ?tipo=colonias&estado=...&municipio=...
// GET ?tipo=cp&cp=20367

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

jsonResponse(['ok' => false, 'error' => 'Tipo de consulta no válido'], 400);
