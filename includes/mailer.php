<?php
// Envío de correo vía SMTP (Gmail) usando PHPMailer, vendorizado directo en
// includes/phpmailer/ (sin Composer -- mismo patrón simple que el resto de
// este proyecto, que no usa gestor de dependencias). Credenciales desde
// .env (MAIL_HOST/MAIL_PORT/MAIL_USER/MAIL_PASS/MAIL_FROM/MAIL_FROM_NAME y,
// opcionales, MAIL_ENCRYPTION/MAIL_AUTH_TYPE/MAIL_DEBUG -- mismas que el ERP).
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
        // Mismas variables que el ERP (erp/includes/mailer.php), para que la
        // cuenta noreply funcione igual en los dos: MAIL_ENCRYPTION=ssl usa
        // SSL directo (puerto 465); cualquier otro valor, STARTTLS (587).
        $ssl = strtolower((string)envConfig('MAIL_ENCRYPTION', 'tls')) === 'ssl';
        $mail->SMTPSecure = $ssl ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)envConfig('MAIL_PORT', $ssl ? '465' : '587');
        $mail->CharSet    = 'UTF-8';
        // Sin esto PHPMailer espera hasta 5 min si el servidor no contesta, y
        // la pantalla que manda el correo se queda colgada.
        $mail->Timeout    = 15;
        $authType = strtoupper(trim((string)envConfig('MAIL_AUTH_TYPE', 'LOGIN')));
        if ($authType !== '') $mail->AuthType = $authType;
        if (envConfig('MAIL_DEBUG', '') === '1') {
            $mail->SMTPDebug   = \PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
            $mail->Debugoutput = function ($str) { error_log('[VISITAS mailer SMTP] ' . trim($str)); };
        }

        $mail->setFrom(envConfig('MAIL_FROM', envConfig('MAIL_USER')), envConfig('MAIL_FROM_NAME', 'Segurmex'));
        // Modo prueba: con MAIL_PRUEBA_A en .env TODOS los correos se mandan a
        // esa dirección (el asunto lleva a quién iba de verdad). Para probar
        // en local sin que le llegue nada a vendedores ni a la responsable.
        // En producción se deja vacío o sin poner.
        $redirigir = trim((string)envConfig('MAIL_PRUEBA_A', ''));
        if ($redirigir !== '') {
            $asunto = '[PRUEBA para ' . $destinatario . '] ' . $asunto;
            $destinatario = $redirigir;
        }
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
