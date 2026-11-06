<?php
// La responsable de muestras CORRIGE una solicitud abierta (06-nov-2026),
// con el mismo folio. Mismos campos que el alta (api/muestra_solicitar.php)
// menos cliente, contacto y entrega, que no se cambian.
//   POST id, estilos (JSON), motivo, notas_planta, fecha_promesa,
//        tiempo_prueba_dias, destino_direccion
// Al vendedor le llega aviso + correo con lo que cambió. Solo el rol 'muestras'.
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

$res = corregirSolicitudMuestra(getDB(), (int)$u['id'], $id, $_POST);
if (!$res['ok']) jsonResponse(['ok' => false, 'error' => $res['error']], $res['codigo'] ?? 400);
jsonResponse($res);
