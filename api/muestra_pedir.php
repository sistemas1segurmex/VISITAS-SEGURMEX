<?php
// El vendedor le PIDE a la responsable de muestras una corrección o una
// cancelación de SU solicitud (06-nov-2026). Ya enviada, el vendedor no la
// edita: ella decide. Ver pedirCambioMuestra() en includes/muestras.php.
//   POST id, tipo=correccion|cancelacion, texto
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/muestras.php';

$u = requireRole('vendedor');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}
$id = (int)($_POST['id'] ?? 0);
if (!$id) jsonResponse(['ok' => false, 'error' => 'Muestra no válida'], 400);

$db = getDB();
$res = pedirCambioMuestra($db, (int)$u['id'], $id, (string)($_POST['tipo'] ?? ''), (string)($_POST['texto'] ?? ''));
if (!$res['ok']) jsonResponse($res, 400);
registrarCambio($db, (int)$u['id'], 'muestra', $id, 'edicion',
    'Pidió ' . (($_POST['tipo'] ?? '') === 'cancelacion' ? 'cancelar' : 'corregir') . ' la muestra ' . $res['solicitud']['folio']);
jsonResponse(['ok' => true]);
