<?php
// Guarda (o refresca) el token de notificaciones push de este vendedor --
// lo llama la app nativa (ver assets/js/vendedor.js -> registrarPushNativo())
// justo después de que Android le entrega el token de Firebase Cloud
// Messaging. Un vendedor puede tener varios tokens (celular nuevo,
// reinstaló la app) -- todos se usan al mandar un aviso.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$u  = requireRole('vendedor');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

$token = trim($_POST['token'] ?? '');
if ($token === '') {
    jsonResponse(['ok' => false, 'error' => 'Falta el token'], 400);
}

$stmt = $db->prepare(
    'INSERT INTO push_tokens (usuario_id, token, plataforma)
     VALUES (?, ?, \'android\')
     ON CONFLICT (token) DO UPDATE SET usuario_id = EXCLUDED.usuario_id, actualizado_en = NOW()'
);
$stmt->execute([$u['id'], $token]);

jsonResponse(['ok' => true]);
