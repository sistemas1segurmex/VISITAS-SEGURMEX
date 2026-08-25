<?php
// Jornada de prospección: el vendedor la marca explícitamente cuando no
// tiene citas programadas pero sale a buscar clientes nuevos por su cuenta.
// GET  -> estado de hoy para el vendedor logueado (si ya inició/terminó).
// POST -> action=iniciar (marca el inicio, no duplica si ya existía) o
//         action=finalizar (marca la hora de fin de la jornada de hoy).

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$u  = requireRole('vendedor');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare(
        "SELECT id, hora_inicio, hora_fin
         FROM prospecciones
         WHERE vendedor_id = ? AND fecha = CURRENT_DATE"
    );
    $stmt->execute([$u['id']]);
    $fila = $stmt->fetch();
    jsonResponse(['ok' => true, 'prospeccion' => $fila ?: null]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['action'] ?? '';

    if ($accion === 'iniciar') {
        $stmt = $db->prepare(
            "INSERT INTO prospecciones (vendedor_id, fecha)
             VALUES (?, CURRENT_DATE)
             ON CONFLICT (vendedor_id, fecha) DO NOTHING
             RETURNING id, hora_inicio, hora_fin"
        );
        $stmt->execute([$u['id']]);
        $fila = $stmt->fetch();
        if (!$fila) {
            // Ya existía (el vendedor ya la había iniciado hoy); la regresamos igual.
            $stmt = $db->prepare(
                "SELECT id, hora_inicio, hora_fin FROM prospecciones
                 WHERE vendedor_id = ? AND fecha = CURRENT_DATE"
            );
            $stmt->execute([$u['id']]);
            $fila = $stmt->fetch();
        }
        jsonResponse(['ok' => true, 'prospeccion' => $fila]);
    }

    if ($accion === 'finalizar') {
        $db->prepare(
            "UPDATE prospecciones SET hora_fin = CURRENT_TIMESTAMP
             WHERE vendedor_id = ? AND fecha = CURRENT_DATE AND hora_fin IS NULL"
        )->execute([$u['id']]);
        jsonResponse(['ok' => true]);
    }

    jsonResponse(['ok' => false, 'error' => 'Acción no reconocida'], 400);
}

jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
