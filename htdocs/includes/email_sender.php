<?php
require_once __DIR__ . '/../config/email_config.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendVerificationEmail($email, $token, $username) {
    $verificationLink = BASE_URL . 'verify.php?token=' . $token;
    
    $subject = "Weryfikacja konta - Wypozyczalnia Samochodow";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
            .header { background: #2c3e50; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { padding: 20px; background: #f9f9f9; }
            .button { display: inline-block; padding: 12px 24px; background: #3498db; 
                     color: white; text-decoration: none; border-radius: 4px; margin: 15px 0; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; border-top: 1px solid #ddd; }
            .code { background: #f4f4f4; padding: 10px; border-radius: 4px; font-family: monospace; word-break: break-all; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Wypozyczalnia Samochodow</h1>
            </div>
            <div class='content'>
                <h2>Witaj, $username!</h2>
                <p>Dziękujemy za rejestrację w naszej wypożyczalni samochodów.</p>
                <p>Aby aktywować swoje konto, kliknij w poniższy link:</p>
                <p style='text-align: center;'>
                    <a href='$verificationLink' class='button'>Aktywuj konto</a>
                </p>
                <p>Jeśli przycisk nie działa, skopiuj poniższy link do przeglądarki:</p>
                <div class='code'>$verificationLink</div>
                <p><strong>Jeśli to nie Ty zakładałeś konto, zignoruj tego maila.</strong></p>
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " Wypożyczalnia Samochodów. Wszelkie prawa zastrzeżone.</p>
                <p>Wiadomość wygenerowana automatycznie, prosimy na nią nie odpowiadać.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmailPHPMailer($email, $subject, $message);
}

function sendPasswordResetEmail($email, $token, $username) {
    $resetLink = BASE_URL . 'reset_password.php?token=' . $token;
    
    $subject = "Resetowanie hasla - Wypozyczalnia Samochodow";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
            .header { background: #e74c3c; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { padding: 20px; background: #f9f9f9; }
            .button { display: inline-block; padding: 12px 24px; background: #e74c3c; 
                     color: white; text-decoration: none; border-radius: 4px; margin: 15px 0; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; border-top: 1px solid #ddd; }
            .code { background: #f4f4f4; padding: 10px; border-radius: 4px; font-family: monospace; word-break: break-all; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Resetowanie hasla</h1>
            </div>
            <div class='content'>
                <h2>Witaj, $username!</h2>
                <p>Otrzymaliśmy prośbę o resetowanie hasła do Twojego konta.</p>
                <p>Aby zresetować hasło, kliknij w poniższy link:</p>
                <p style='text-align: center;'>
                    <a href='$resetLink' class='button'>Resetuj hasło</a>
                </p>
                <p>Jeśli przycisk nie działa, skopiuj poniższy link do przeglądarki:</p>
                <div class='code'>$resetLink</div>
                <p><strong>Jeśli to nie Ty wysłałeś prośbę o resetowanie hasła, zignoruj tego maila.</strong></p>
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " Wypożyczalnia Samochodów. Wszelkie prawa zastrzeżone.</p>
                <p>Wiadomość wygenerowana automatycznie, prosimy na nią nie odpowiadać.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmailPHPMailer($email, $subject, $message);
}

function sendEmailPHPMailer($to, $subject, $message) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings - alternatywna wersja z SSL
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
        $mail->Port       = 465; // Port dla SSL
        
        $mail->SMTPDebug  = 2;
        $mail->Debugoutput = function($str, $level) {
            file_put_contents(__DIR__ . '/smtp_debug.log', date('Y-m-d H:i:s') . " Level $level: $str\n", FILE_APPEND | LOCK_EX);
        };
        
        // Recipients
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->AltBody = strip_tags($message);
        
        $result = $mail->send();
        error_log("Email wysłany pomyślnie do: $to (SSL)");
        return true;
    } catch (Exception $e) {
        $error_msg = "Błąd wysyłania emaila do: $to - " . $mail->ErrorInfo;
        error_log($error_msg);
        file_put_contents(__DIR__ . '/smtp_errors.log', date('Y-m-d H:i:s') . " - $error_msg\n", FILE_APPEND | LOCK_EX);
        return false;
    }
}
// Zachowaj starą funkcję dla kompatybilności
function sendEmail($to, $subject, $message) {
    return sendEmailPHPMailer($to, $subject, $message);
}

function logEmailAttempt($email, $success, $error = '') {
    $logMessage = date('Y-m-d H:i:s') . " - Email: $email - " . 
                  ($success ? 'SUKCES' : 'BŁĄD: ' . $error) . "\n";
    file_put_contents(__DIR__ . '/email_log.txt', $logMessage, FILE_APPEND | LOCK_EX);
}
?>