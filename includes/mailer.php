<?php
// Envío de correo vía SMTP (Gmail) usando PHPMailer, vendorizado directo en
// includes/phpmailer/ (sin Composer -- mismo patrón simple que el resto de
// este proyecto, que no usa gestor de dependencias). Credenciales desde
// .env (MAIL_HOST/MAIL_PORT/MAIL_USER/MAIL_PASS/MAIL_FROM/MAIL_FROM_NAME),
// mismas variables que ya existían sin usarse.
require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Manda un correo HTML simple. Regresa true/false; nunca truena la petición
 * que lo llama (un correo fallido no debe tumbar, por ejemplo, la creación
 * de una invitación -- el link se puede seguir copiando a mano).
 */
function enviarCorreo(string $destinatario, string $asunto, string $cuerpoHtml): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = envConfig('MAIL_HOST', 'smtp.gmail.com');
        $mail->SMTPAuth   = true;
        $mail->Username   = envConfig('MAIL_USER');
        $mail->Password   = envConfig('MAIL_PASS');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)envConfig('MAIL_PORT', '587');
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(envConfig('MAIL_FROM', envConfig('MAIL_USER')), envConfig('MAIL_FROM_NAME', 'Segurmex'));
        $mail->addAddress($destinatario);

        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $cuerpoHtml;

        $mail->send();
        return true;
    } catch (PHPMailerException | Throwable $e) {
        error_log('[VISITAS] enviarCorreo: ' . $e->getMessage());
        return false;
    }
}
