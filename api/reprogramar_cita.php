<?php
// Cambia la fecha/hora de una cita propia que sigue "pendiente" (nadie ha
// hecho check-in). Reglas:
//   - motivo obligatorio (me equivoqué / el cliente la movió / otro + texto)
//   - nunca a una fecha u hora que ya pasó (hora de CDMX)
//   - no se guarda si choca con otra cita suya a menos de CHOQUE_CITAS_MIN
//   - máximo MAX_REPROGRAMACIONES veces por cita
// El conteo sale de la bitácora (ver sqlReprogramaciones() en helpers.php),
// por eso aquí el registro en bitacora_cambios va dentro de la misma
// transacción que el UPDATE -- si no se guarda la bitácora, no se mueve la
// cita, o el límite de 3 dejaría de cuadrar.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$u  = requireRole('vendedor');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

$MOTIVOS = [
    'error'   => 'Me equivoqué al asignarla',
    'cliente' => 'El cliente la reprogramó',
    'otro'    => 'Otro',
];

$citaId      = (int)($_POST['cita_id'] ?? 0);
$fechaHora   = trim(str_replace('T', ' ', $_POST['fecha_hora'] ?? ''));
$motivoTipo  = $_POST['motivo_tipo'] ?? '';
$motivoTexto = trim($_POST['motivo_texto'] ?? '');

if (!$citaId) {
    jsonResponse(['ok' => false, 'error' => 'Cita no válida'], 400);
}
if (!isset($MOTIVOS[$motivoTipo])) {
    jsonResponse(['ok' => false, 'error' => 'Elige el motivo de la reprogramación.'], 400);
}
if ($motivoTipo === 'otro' && $motivoTexto === '') {
    jsonResponse(['ok' => false, 'error' => 'Escribe el motivo en "Otro".'], 400);
}
$motivo = $motivoTipo === 'otro' ? 'Otro: ' . mb_substr($motivoTexto, 0, 200) : $MOTIVOS[$motivoTipo];

// citas.fecha_hora es hora local de CDMX tal cual (ver api/citas.php).
$tzMx  = new DateTimeZone('America/Mexico_City');
$nueva = DateTime::createFromFormat('!Y-m-d H:i', substr($fechaHora, 0, 16), $tzMx);
if (!$nueva) {
    jsonResponse(['ok' => false, 'error' => 'Fecha u hora no válida.'], 400);
}
if ($nueva <= new DateTime('now', $tzMx)) {
    jsonResponse(['ok' => false, 'error' => 'Esa hora ya pasó. Elige una fecha y hora a partir de ahora.'], 400);
}
$nuevaStr = $nueva->format('Y-m-d H:i:s');

$stmt = $db->prepare(
    'SELECT c.id, c.estado, c.fecha_hora, cl.nombre AS cliente_nombre,
            (SELECT COUNT(*) FROM checkins ch WHERE ch.cita_id = c.id) AS checkins,
            ' . sqlReprogramaciones() . '
     FROM citas c
     JOIN clientes cl ON cl.id = c.cliente_id
     WHERE c.id = ? AND c.vendedor_id = ?'
);
$stmt->execute([$citaId, $u['id']]);
$cita = $stmt->fetch();

if (!$cita) {
    jsonResponse(['ok' => false, 'error' => 'Cita no encontrada'], 404);
}
if ($cita['estado'] !== 'pendiente' || (int)$cita['checkins'] > 0) {
    jsonResponse(['ok' => false, 'error' => 'Esta cita ya no se puede reprogramar (ya tiene actividad registrada).'], 400);
}
if ((int)$cita['reprogramaciones'] >= MAX_REPROGRAMACIONES) {
    jsonResponse(['ok' => false, 'error' => 'Esta cita ya se reprogramó ' . MAX_REPROGRAMACIONES . ' veces; solo se puede cancelar.'], 400);
}
$anteriorStr = substr($cita['fecha_hora'], 0, 19);
if (substr($anteriorStr, 0, 16) === substr($nuevaStr, 0, 16)) {
    jsonResponse(['ok' => false, 'error' => 'Es la misma fecha y hora que ya tiene la cita.'], 400);
}

$chk = $db->prepare(
    "SELECT c.fecha_hora, cl.nombre AS cliente_nombre
     FROM citas c JOIN clientes cl ON cl.id = c.cliente_id
     WHERE c.vendedor_id = ? AND c.id <> ?
       AND c.estado NOT IN ('cancelada', 'no_realizada')
       AND ABS(EXTRACT(EPOCH FROM (c.fecha_hora - CAST(? AS timestamp)))) < ?
     ORDER BY c.fecha_hora LIMIT 1"
);
$chk->execute([$u['id'], $citaId, $nuevaStr, CHOQUE_CITAS_MIN * 60]);
if ($choque = $chk->fetch()) {
    $hora = (new DateTime($choque['fecha_hora'], $tzMx))->format('g:i a');
    jsonResponse(['ok' => false, 'error' => "Choca con {$choque['cliente_nombre']} a las {$hora}. Elige otra hora."], 400);
}

try {
    $db->beginTransaction();
    // Se vuelve a exigir "pendiente" en el UPDATE por si alguien hizo
    // check-in entre la validación y aquí. Ojo: NO tocar
    // recordatorio_pendiente_enviado_en -- esa columna no existe en la base
    // de producción (su migración nunca se corrió ahí) y el UPDATE truena.
    // Tampoco hace falta: ese aviso es de check-out pendiente, solo aplica
    // a citas con entrada, y una cita pendiente no tiene.
    $upd = $db->prepare(
        "UPDATE citas SET fecha_hora = ?
         WHERE id = ? AND vendedor_id = ? AND estado = 'pendiente'"
    );
    $upd->execute([$nuevaStr, $citaId, $u['id']]);
    if ($upd->rowCount() !== 1) {
        $db->rollBack();
        jsonResponse(['ok' => false, 'error' => 'Esta cita ya no se puede reprogramar.'], 409);
    }
    $db->prepare(
        'INSERT INTO bitacora_cambios (vendedor_id, entidad, entidad_id, accion, resumen, cambios)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([
        $u['id'], 'cita', $citaId, 'edicion',
        mb_substr("Reprogramó la cita con {$cita['cliente_nombre']}", 0, 255),
        json_encode([
            'Fecha y hora' => [substr($anteriorStr, 0, 16), substr($nuevaStr, 0, 16)],
            LLAVE_MOTIVO_REPROGRAMACION => [null, $motivo],
        ], JSON_UNESCAPED_UNICODE),
    ]);
    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[VISITAS] reprogramar_cita: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'error' => 'No se pudo reprogramar la cita. Intenta de nuevo.'], 500);
}

// Alertas para el admin (fuera de la transacción: si fallan, la cita ya se
// movió y eso no se revierte). Una sola alerta por reprogramación: la del
// límite gana y, si además fue con poco aviso, lo menciona.
$usadas    = (int)$cita['reprogramaciones'] + 1;
$horasAviso = ((new DateTime($anteriorStr, $tzMx))->getTimestamp() - time()) / 3600;
$pocoAviso = $horasAviso < HORAS_AVISO_REPROGRAMACION;
$cambio    = fechaCitaCorta($anteriorStr) . ' → ' . fechaCitaCorta($nuevaStr);
if ($usadas >= MAX_REPROGRAMACIONES) {
    crearAlerta($db, (int)$u['id'], $citaId, ALERTA_REPROG_LIMITE,
        "Reprogramó por {$usadas}.ª vez la cita con {$cita['cliente_nombre']}"
        . ($pocoAviso ? ', con menos de ' . HORAS_AVISO_REPROGRAMACION . ' h de aviso' : '')
        . " ({$cambio}). Motivo: {$motivo}");
} elseif ($pocoAviso) {
    crearAlerta($db, (int)$u['id'], $citaId, ALERTA_REPROG_POCO_AVISO,
        "Reprogramó la cita con {$cita['cliente_nombre']} con menos de " . HORAS_AVISO_REPROGRAMACION
        . " h de aviso ({$cambio}). Motivo: {$motivo}");
}

jsonResponse([
    'ok' => true,
    'fecha_hora' => $nuevaStr,
    'reprogramaciones' => $usadas,
]);
