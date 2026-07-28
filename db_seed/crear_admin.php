<?php
// Script de un solo uso: crea (o actualiza) un usuario admin.
// Uso: php db_seed/crear_admin.php
// Bórralo después de usarlo si no lo necesitas más.

require_once __DIR__ . '/../includes/db.php';

$email  = 'sistemas@segurmex.com.mx';
$pass   = 'sistemas2026';
$nombre = 'Sistemas';

$hash = password_hash($pass, PASSWORD_BCRYPT);
$pdo  = getDB();

$stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
$stmt->execute([$email]);
$existe = $stmt->fetch();

if ($existe) {
    $upd = $pdo->prepare('UPDATE usuarios SET password_hash = ?, rol = ?, nombre = ?, activo = 1 WHERE email = ?');
    $upd->execute([$hash, 'admin', $nombre, $email]);
    echo "Usuario existente actualizado a admin: $email\n";
} else {
    $ins = $pdo->prepare('INSERT INTO usuarios (nombre, email, password_hash, rol, activo) VALUES (?, ?, ?, ?, 1)');
    $ins->execute([$nombre, $email, $hash, 'admin']);
    echo "Usuario admin creado: $email\n";
}
