<?php
// La responsable de muestras AUTORIZA la ampliación de pares de una solicitud
// «por_autorizar» (20-nov-2026): pasa a «enviada» y sigue el flujo normal. Al
// vendedor le llega aviso + correo.
//   POST id, nota (opcional)
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

$res = autorizarAmpliacionMuestra(getDB(), $id, (int)$u['id'], (string)($_POST['nota'] ?? ''));
if (!$res['ok']) jsonResponse($res, 400);
jsonResponse(['ok' => true, 'solicitud' => solicitudMuestraParaJson($res['solicitud'])]);
