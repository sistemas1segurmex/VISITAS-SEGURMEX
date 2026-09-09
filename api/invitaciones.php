<?php
// Invitaciones de registro para vendedores nuevos: el admin genera un link
// de un solo uso con vencimiento; el vendedor lo abre y se registra solo
// (ver registro_vendedor.php); queda pendiente de aprobación (activo=0)
// hasta que el admin lo revisa desde admin/usuarios.php.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$admin = requireRole('admin');
$db    = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'error' => 'Método no soportado'], 405);
}

$accion = $_POST['accion'] ?? 'crear';

// Días de vigencia permitidos -- una lista corta y fija, no un número libre,
// para no dejar generar links que duren meses por error de dedo.
const DIAS_VIGENCIA_PERMITIDOS = [1, 2, 7];

if ($accion === 'crear') {
    $email = trim($_POST['email'] ?? '');
    $dias  = (int)($_POST['dias'] ?? 2);
    if (!in_array($dias, DIAS_VIGENCIA_PERMITIDOS, true)) $dias = 2;

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['ok' => false, 'error' => 'Escribe un correo válido'], 400);
    }

    $existe = $db->prepare('SELECT id FROM usuarios WHERE email = ?');
    $existe->execute([$email]);
    if ($existe->fetch()) {
        jsonResponse(['ok' => false, 'error' => 'Ya existe un usuario con ese correo'], 409);
    }

    $token = bin2hex(random_bytes(32));
    $stmt = $db->prepare(
        "INSERT INTO invitaciones_vendedor (token, email, creado_por, expira_en)
         VALUES (?, ?, ?, NOW() + (? || ' days')::interval)
         RETURNING id, expira_en"
    );
    $stmt->execute([$token, $email, $admin['id'], $dias]);
    $fila = $stmt->fetch();

    jsonResponse(['ok' => true, 'id' => (int)$fila['id'], 'token' => $token, 'expira_en' => $fila['expira_en']]);
}

if ($accion === 'enviar') {
    require_once __DIR__ . '/../includes/mailer.php';

    $id   = (int)($_POST['id'] ?? 0);
    $link = trim($_POST['link'] ?? '');
    if (!$id || $link === '') {
        jsonResponse(['ok' => false, 'error' => 'Faltan datos'], 400);
    }

    $stmt = $db->prepare('SELECT email FROM invitaciones_vendedor WHERE id = ? AND usado_en IS NULL');
    $stmt->execute([$id]);
    $invitacion = $stmt->fetch();
    if (!$invitacion) {
        jsonResponse(['ok' => false, 'error' => 'Invitación no encontrada o ya usada'], 404);
    }

    $cuerpo = '
      <p>Hola,</p>
      <p>Te invitamos a registrarte como vendedor en el sistema de Control de Visitas de Segurmex.</p>
      <p><a href="' . htmlspecialchars($link) . '">Completa tu registro aquí</a></p>
      <p>Este link es de un solo uso y vence pronto -- si ya venció, pide uno nuevo.</p>
    ';
    $enviado = enviarCorreo($invitacion['email'], 'Invitación — Control de Visitas Segurmex', $cuerpo);

    jsonResponse(['ok' => $enviado, 'error' => $enviado ? null : 'No se pudo enviar el correo (revisa la configuración SMTP en .env)']);
}

jsonResponse(['ok' => false, 'error' => 'Acción no reconocida'], 400);
