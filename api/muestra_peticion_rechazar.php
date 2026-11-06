<?php
// La responsable de muestras contesta «No se puede cambiar» a la petición
// (corrección o cancelación) de un vendedor (06-nov-2026). La muestra sigue
// igual y al vendedor le llega aviso + correo con el motivo.
//   POST id, motivo
// Solo el rol 'muestras' -- el admin únicamente consulta.
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/muestras.php';

$u = requireRole('muestras');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}
$id = (int)($_POST['id'] ?? 0);
if (!$id) jsonResponse(['ok' => false, 'error' => 'Solicitud no válida'], 400);

$res = rechazarPeticionMuestra(getDB(), $id, (int)$u['id'], (string)($_POST['motivo'] ?? ''));
if (!$res['ok']) jsonResponse($res, 400);
jsonResponse(['ok' => true]);
