<?php
// Consulta entre sistemas: el ERP (la copia que corre junto a Visitas en
// 192.168.4.200) pregunta aquí si un correo pertenece a un vendedor activo
// de Visitas -- así se define "vendedor externo" para el candado de 5
// muestras al mes en el ERP (ver cotizacion/_helpers.php::esVendedorExterno
// del lado del ERP). Sin esta llamada el ERP no tiene forma de saberlo: son
// dos bases de datos separadas, no comparten esquema.
//
// Protegido con un secreto compartido (INTEGRACION_ERP_SECRET en el .env de
// AMBOS sistemas) en vez de requireLogin() -- quien llama es un servidor,
// no una persona con sesión.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

$secretoEsperado = envConfig('INTEGRACION_ERP_SECRET');
$secretoRecibido = $_GET['secreto'] ?? '';
if (!$secretoEsperado || !hash_equals($secretoEsperado, $secretoRecibido)) {
    jsonResponse(['ok' => false, 'error' => 'No autorizado'], 401);
}

$email = trim($_GET['email'] ?? '');
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['ok' => false, 'error' => 'Correo inválido'], 400);
}

$db = getDB();
$stmt = $db->prepare("SELECT 1 FROM usuarios WHERE email = ? AND rol = 'vendedor' AND activo = 1");
$stmt->execute([$email]);

jsonResponse(['ok' => true, 'es_vendedor' => (bool)$stmt->fetch()]);
