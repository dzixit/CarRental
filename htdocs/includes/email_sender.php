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
        <meta http-equiv='Content-Type' content='text/html; charset=utf-8'>
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
                <h1>Wypożyczalnia Samochodów</h1>
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
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " Wypożyczalnia Samochodów.</p>
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
        <meta http-equiv='Content-Type' content='text/html; charset=utf-8'>
        <style>
            body { font-family: Arial, sans-serif; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
            .header { background: #e74c3c; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { padding: 20px; background: #f9f9f9; }
            .button { display: inline-block; padding: 12px 24px; background: #e74c3c; color: white; text-decoration: none; border-radius: 4px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Resetowanie hasła</h1>
            </div>
            <div class='content'>
                <h2>Witaj, $username!</h2>
                <p>Aby zresetować hasło, kliknij w poniższy link:</p>
                <p style='text-align: center;'>
                    <a href='$resetLink' class='button'>Resetuj hasło</a>
                </p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmailPHPMailer($email, $subject, $message);
}

// ZMIANA: Nowa funkcja powiadomienia o rezerwacji
function sendReservationConfirmationEmail($email, $username, $vehicle_info, $start_date, $end_date) {
    $subject = "Potwierdzenie rezerwacji - CarRental";
    
    $message = "
    <html>
    <head>
        <meta http-equiv='Content-Type' content='text/html; charset=utf-8'>
        <style>
            body { font-family: Arial, sans-serif; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
            .header { background: #27ae60; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { padding: 20px; background: #f9f9f9; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Potwierdzenie Rezerwacji</h1>
            </div>
            <div class='content'>
                <h2>Witaj, $username!</h2>
                <p>Twoja rezerwacja została pomyślnie przyjęta.</p>
                <p><strong>Szczegóły:</strong></p>
                <ul>
                    <li><strong>Pojazd:</strong> $vehicle_info</li>
                    <li><strong>Data odbioru:</strong> $start_date</li>
                    <li><strong>Data zwrotu:</strong> $end_date</li>
                </ul>
                <p>Czekamy na Ciebie w naszym punkcie!</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmailPHPMailer($email, $subject, $message);
}

// ZMIANA: Nowa funkcja powiadomienia o karze
function sendPenaltyNotificationEmail($email, $username, $amount, $reason) {
    $subject = "Nowa opłata / Powiadomienie o karze - CarRental";
    
    $message = "
    <html>
    <head>
        <meta http-equiv='Content-Type' content='text/html; charset=utf-8'>
        <style>
            body { font-family: Arial, sans-serif; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
            .header { background: #c0392b; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { padding: 20px; background: #f9f9f9; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Powiadomienie o opłacie</h1>
            </div>
            <div class='content'>
                <h2>Witaj, $username</h2>
                <p>Informujemy, że do Twojego wypożyczenia została doliczona dodatkowa opłata.</p>
                <p><strong>Kwota:</strong> " . number_format($amount, 2) . " PLN</p>
                <p><strong>Powód:</strong> $reason</p>
                <p>Prosimy o uregulowanie należności zgodnie z regulaminem.</p>
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
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64'; 

        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        
        $mail->SMTPDebug  = 0; // Wyłącz debugowanie na produkcji
        
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to);
        
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->AltBody = strip_tags($message);
        
        $result = $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Błąd wysyłania emaila do: $to - " . $mail->ErrorInfo);
        file_put_contents(__DIR__ . '/smtp_errors.log', date('Y-m-d H:i:s') . " - " . $mail->ErrorInfo . "\n", FILE_APPEND | LOCK_EX);
        return false;
    }
}

function sendEmail($to, $subject, $message) {
    return sendEmailPHPMailer($to, $subject, $message);
}
?>