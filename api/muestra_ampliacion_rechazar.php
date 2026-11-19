<?php
// La responsable de muestras NO AUTORIZA la ampliación de pares de una solicitud
// «por_autorizar» (20-nov-2026): queda «cancelada» con el motivo («No autorizada: …»)
// y al vendedor le llega aviso + correo. El vendedor puede pedir una nueva de hasta
// MUESTRAS_TOPE_PARES_CLIENTE_MES pares.
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

$res = rechazarAmpliacionMuestra(getDB(), $id, (int)$u['id'], (string)($_POST['motivo'] ?? ''));
if (!$res['ok']) jsonResponse($res, 400);
jsonResponse(['ok' => true, 'solicitud' => solicitudMuestraParaJson($res['solicitud'])]);
