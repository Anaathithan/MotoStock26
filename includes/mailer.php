<?php
// includes/mailer.php — PHPMailer + Gmail SMTP

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';

// ── Your Gmail credentials ────────────────────────────────────────────────────
define('SMTP_USER', OWNER_EMAIL);
define('SMTP_PASS', OWNER_SMTP_PASS);
define('SMTP_FROM_NAME', 'Bimsara Motors');

function ms_send_email(string $to, string $subject, string $htmlBody): bool {
    if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mailer error: ' . $mail->ErrorInfo);
        return false;
    }
}

function ms_email_template(string $title, string $body): string {
    return "
    <div style='font-family:Arial,sans-serif;max-width:560px;margin:0 auto;background:#f9f9f9;padding:24px;border-radius:10px;'>
      <div style='background:#1b2738;padding:16px 24px;border-radius:8px 8px 0 0;'>
        <span style='font-size:1.2rem;font-weight:800;color:#fff;'>Bimsara Motors</span>
      </div>
      <div style='background:#fff;padding:24px;border-radius:0 0 8px 8px;border:1px solid #e2e8f0;border-top:none;'>
        <h2 style='color:#1b2738;margin-top:0;font-size:1.1rem;'>{$title}</h2>
        {$body}
        <hr style='border:none;border-top:1px solid #e2e8f0;margin:20px 0;'>
        <p style='color:#94a3b8;font-size:.78rem;margin:0;'>This is an automated message from MotoStock26. Do not reply.</p>
      </div>
    </div>";
}