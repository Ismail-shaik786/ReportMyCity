<?php
/**
 * ReportMyCity — Centralized Mailer Utility
 */
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    /**
     * Send a standardized email
     */
    public static function send($toEmail, $toName, $subject, $bodyHtml, $fallbackText = '', $attachments = []) {
        // Load .env if not already loaded
        if (!isset($_ENV['SMTP_HOST'])) {
            try {
                $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
                $dotenv->load();
            } catch (Exception $e) {
                error_log("Mailer: Failed to load .env");
            }
        }

        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = $_ENV['SMTP_HOST']     ?? 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['SMTP_USERNAME'] ?? '';
            $mail->Password   = $_ENV['SMTP_PASSWORD'] ?? '';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int)($_ENV['SMTP_PORT'] ?? 587);

            // Recipients
            $mail->setFrom(
                $_ENV['SMTP_FROM_EMAIL'] ?? $_ENV['SMTP_USERNAME'] ?? '',
                $_ENV['SMTP_FROM_NAME']  ?? 'ReportMyCity India'
            );
            $mail->addAddress($toEmail, $toName);

            // Attachments
            foreach ($attachments as $path => $name) {
                if (file_exists($path)) {
                    $mail->addAttachment($path, $name);
                }
            }

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = self::getEmailTemplate($toName, $subject, $bodyHtml);
            $mail->AltBody = $fallbackText ?: strip_tags($bodyHtml);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mailer Error: {$mail->ErrorInfo}");
            return false;
        }
    }

    /**
     * Standard visual wrapper for all emails
     */
    private static function getEmailTemplate($name, $title, $content) {
        return "
        <div style=\"font-family: 'Inter', Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; color: #1e293b;\">
            <div style=\"background: #0a2558; padding: 30px; text-align: center;\">
                <h1 style=\"color: #ffffff; margin: 0; font-size: 24px; letter-spacing: -0.02em;\">ReportMyCity</h1>
                <p style=\"color: #94a3b8; margin: 5px 0 0 0; font-size: 14px;\">National Citizen Engagement Portal</p>
            </div>
            <div style=\"padding: 40px;\">
                <h2 style=\"margin-top: 0; color: #0a2558; font-size: 20px;\">$title</h2>
                <p>Hello <strong>$name</strong>,</p>
                <div style=\"line-height: 1.6; color: #475569;\">
                    $content
                </div>
                <div style=\"margin-top: 30px; padding-top: 20px; border-top: 1px solid #f1f5f9; font-size: 13px; color: #94a3b8;\">
                    This is an automated notification from the ReportMyCity System. Please do not reply to this email.
                </div>
            </div>
            <div style=\"background: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #e2e8f0;\">
                <p style=\"margin: 0; font-size: 12px; color: #64748b;\">&copy; " . date('Y') . " ReportMyCity India. Empowering Citizens through Digital Oversight.</p>
            </div>
        </div>";
    }
}
