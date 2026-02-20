<?php
require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class Mailer {

    /**
     * Returns true if smtp_host is configured in the settings table.
     */
    public static function isConfigured(PDO $pdo): bool {
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = 'smtp_host'");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return !empty($row['value']);
    }

    /**
     * Send an email using SMTP settings stored in the DB.
     * Throws Exception on failure.
     */
    public static function send(PDO $pdo, string $to, string $subject, string $body): void {
        $get = function (string $key, string $default = '') use ($pdo): string {
            $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = ?");
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (string)$row['value'] : $default;
        };

        $host       = $get('smtp_host');
        $port       = (int)($get('smtp_port', '587') ?: 587);
        $encryption = $get('smtp_encryption', 'tls');
        $username   = $get('smtp_username');
        $password   = $get('smtp_password');
        $fromEmail  = $get('smtp_from_email');
        $fromName   = $get('smtp_from_name', 'Braga Agenda');

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->Port       = $port;
        $mail->CharSet    = 'UTF-8';

        if ($encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        if ($username) {
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
        }

        $mail->setFrom($fromEmail ?: $username, $fromName);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->isHTML(false);

        $mail->send();
    }
}
