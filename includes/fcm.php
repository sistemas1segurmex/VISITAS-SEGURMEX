<?php
// Envío de notificaciones push vía Firebase Cloud Messaging (API HTTP v1),
// escrito a mano con openssl -- sin el SDK de Firebase ni Composer, mismo
// criterio que PHPMailer en este proyecto (ver includes/phpmailer/).
//
// Requiere el archivo de credenciales de una cuenta de servicio de Firebase
// (Consola de Firebase -> Configuración del proyecto -> Cuentas de
// servicio -> "Generar nueva clave privada"), guardado en
// includes/firebase-service-account.json -- NUNCA en git (ver .gitignore).
// Sin ese archivo, enviarPushFCM() no hace nada (falla en silencio con un
// log), para que el resto del sistema siga funcionando aunque todavía no
// se haya configurado Firebase.

define('FCM_CREDENCIALES_PATH', __DIR__ . '/firebase-service-account.json');

function fcmCredencialesDisponibles(): bool {
    return is_readable(FCM_CREDENCIALES_PATH);
}

function base64UrlEncode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/** Intercambia la cuenta de servicio por un access_token OAuth2 de Google
 *  (válido 1 hora), firmando un JWT con la llave privada -- flujo estándar
 *  "Server to Server" de Google, sin ninguna librería externa. */
function fcmAccessToken(): ?string {
    if (!fcmCredencialesDisponibles()) return null;
    $cred = json_decode(file_get_contents(FCM_CREDENCIALES_PATH), true);
    if (!$cred || empty($cred['private_key']) || empty($cred['client_email'])) {
        error_log('[VISITAS] fcmAccessToken: firebase-service-account.json inválido');
        return null;
    }

    $ahora = time();
    $header = base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claims = base64UrlEncode(json_encode([
        'iss'   => $cred['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $ahora,
        'exp'   => $ahora + 3600,
    ]));
    $firmaOk = openssl_sign("$header.$claims", $firma, $cred['private_key'], 'sha256WithRSAEncryption');
    if (!$firmaOk) {
        error_log('[VISITAS] fcmAccessToken: no se pudo firmar el JWT');
        return null;
    }
    $jwt = "$header.$claims." . base64UrlEncode($firma);

    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]),
        'ignore_errors' => true,
        'timeout' => 8,
    ]]);
    $resp = @file_get_contents('https://oauth2.googleapis.com/token', false, $ctx);
    $data = $resp ? json_decode($resp, true) : null;
    if (empty($data['access_token'])) {
        error_log('[VISITAS] fcmAccessToken: Google no regresó access_token (' . ($resp ?: 'sin respuesta') . ')');
        return null;
    }
    return $data['access_token'];
}

/** Manda una notificación a TODOS los tokens registrados de un usuario.
 *  Si un token ya no es válido (celular formateado, app desinstalada), se
 *  borra solo de push_tokens para no seguir intentando en el futuro. */
function enviarPushFCM(PDO $db, int $usuarioId, string $titulo, string $cuerpo, array $datos = []): void {
    if (!fcmCredencialesDisponibles()) return; // Firebase todavía no configurado -- no hace nada

    $cred = json_decode(file_get_contents(FCM_CREDENCIALES_PATH), true);
    $projectId = $cred['project_id'] ?? null;
    if (!$projectId) return;

    $accessToken = fcmAccessToken();
    if (!$accessToken) return;

    $stmt = $db->prepare('SELECT id, token FROM push_tokens WHERE usuario_id = ?');
    $stmt->execute([$usuarioId]);
    $tokens = $stmt->fetchAll();
    if (!$tokens) return;

    $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
    foreach ($tokens as $t) {
        $payload = json_encode([
            'message' => [
                'token' => $t['token'],
                'notification' => ['title' => $titulo, 'body' => $cuerpo],
                'data' => array_map('strval', $datos),
                'android' => ['priority' => 'high'],
            ],
        ]);
        $ctx = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\nAuthorization: Bearer {$accessToken}\r\n",
            'content' => $payload,
            'ignore_errors' => true,
            'timeout' => 8,
        ]]);
        $resp = @file_get_contents($url, false, $ctx);
        $data = $resp ? json_decode($resp, true) : null;

        // Token ya no sirve (app desinstalada, celular restaurado de
        // fábrica, etc.) -- se limpia para no volver a intentarlo.
        $status = $data['error']['status'] ?? null;
        if (in_array($status, ['UNREGISTERED', 'NOT_FOUND', 'INVALID_ARGUMENT'], true)) {
            $db->prepare('DELETE FROM push_tokens WHERE id = ?')->execute([$t['id']]);
        } elseif (!$resp || $status) {
            error_log('[VISITAS] enviarPushFCM: fallo al enviar a token ' . $t['id'] . ' (' . ($resp ?: 'sin respuesta') . ')');
        }
    }
}
