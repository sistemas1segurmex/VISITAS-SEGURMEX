<?php
// La responsable de muestras cambia el estado de una solicitud:
//   POST id, estado=en_preparacion|embarcada|cancelada
//        + en_preparacion: preparacion=pt|por_programar, fecha_estimada (opcional)
//        + embarcada: envio_modo=paqueteria|en_persona, guia_url (link de rastreo)
//        + cancelada: motivo
// Solo el rol 'muestras' -- el admin únicamente consulta. Cada cambio le
// avisa al vendedor (campanita + correo), ver includes/muestras.php.
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/muestras.php';

$u = requireRole('muestras');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

$id     = (int)($_POST['id'] ?? 0);
$estado = (string)($_POST['estado'] ?? '');
if (!$id) jsonResponse(['ok' => false, 'error' => 'Solicitud no válida'], 400);

$db = getDB();
$res = cambiarEstadoSolicitudMuestra($db, $id, (int)$u['id'], $estado, [
    'preparacion'    => $_POST['preparacion'] ?? '',
    'fecha_estimada' => $_POST['fecha_estimada'] ?? '',
    'envio_modo' => $_POST['envio_modo'] ?? '',
    'guia_url'   => $_POST['guia_url'] ?? '',
    'motivo'     => $_POST['motivo'] ?? '',
]);
if (!$res['ok']) jsonResponse($res, 400);
jsonResponse(['ok' => true, 'solicitud' => solicitudMuestraParaJson($res['solicitud'])]);
