<?php
// classes/MailSender.class.php

// You need to include PHPMailer. There are two main ways:

// OPTION 1 (Recommended for most modern PHP projects - using Composer):
// If you've installed PHPMailer via Composer (running 'composer require phpmailer/phpmailer' in your project root),
// then you should have a 'vendor' folder in your project root.
// Uncomment the following line and ensure the path is correct relative to this file.
require_once __DIR__ . '/../vendor/autoload.php'; // UNCOMMENT THIS LINE

// OPTION 2 (Manual PHPMailer installation - if you don't use Composer):
// If you manually downloaded PHPMailer and placed its 'src' folder (or renamed it to 'PHPMailer')
// inside your 'classes' directory (so you have classes/PHPMailer/PHPMailer.php etc.),
// then uncomment and adjust the paths below.
// require_once __DIR__ . '/PHPMailer/PHPMailer.php';
// require_once __DIR__ . '/PHPMailer/SMTP.php';
// require_once __DIR__ . '/PHPMailer/Exception.php';


// Make sure you have one of the above options uncommented and correctly configured.

// Use the PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class MailSender {
    private $mailer;

    public function __construct() {
        $this->mailer = new PHPMailer(true); // Passing `true` enables exceptions

        // Server settings (configure these in your main config.php or directly here)
        // You MUST define these constants in your includes/config.php file.
        // Example additions to config.php:
        // define('SMTP_HOST', 'smtp.mailtrap.io'); // or 'smtp.gmail.com' for Gmail, etc.
        // define('SMTP_AUTH', true);
        // define('SMTP_USERNAME', 'your_smtp_username');
        // define('SMTP_PASSWORD', 'your_smtp_password');
        // define('SMTP_SECURE', PHPMailer::ENCRYPTION_STARTTLS); // Use PHPMailer::ENCRYPTION_SMTPS for port 465
        // define('SMTP_PORT', 587); // Use 465 for SMTPS

        // For `MAIL_FROM_EMAIL` and `MAIL_FROM_NAME`, these define the sender's email and name.
        // define('MAIL_FROM_EMAIL', 'no-reply@eventcraftai.com');
        // define('MAIL_FROM_NAME', 'EventCraftAI');

        try {
            $this->mailer->isSMTP();
            $this->mailer->Host       = defined('SMTP_HOST') ? SMTP_HOST : 'localhost'; // Fallback to localhost if not defined
            $this->mailer->SMTPAuth   = defined('SMTP_AUTH') ? SMTP_AUTH : false;       // Fallback to false if not defined
            $this->mailer->Username   = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
            $this->mailer->Password   = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
            $this->mailer->SMTPSecure = defined('SMTP_SECURE') ? SMTP_SECURE : '';
            $this->mailer->Port       = defined('SMTP_PORT') ? SMTP_PORT : 25; // Default SMTP port

            // Set the 'From' address and name
            $this->mailer->setFrom(
                defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : 'no-reply@example.com',
                defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'EventCraftAI'
            );
            $this->mailer->isHTML(true); // Set email format to HTML
            $this->mailer->CharSet = 'UTF-8'; // Ensure proper character encoding

        } catch (Exception $e) {
            // Log constructor errors, but don't re-throw here to allow app to continue
            // Error will be caught during sendEmail call if setup is bad
            error_log("MailSender constructor error: " . $e->getMessage());
        }
    }

    /**
     * Sends an email.
     * @param string $toEmail Recipient's email address.
     * @param string $toName Recipient's name.
     * @param string $subject Email subject.
     * @param string $bodyHtml HTML content of the email.
     * @param string $bodyText Plain text content of the email (fallback).
     * @return bool True on success, false on failure.
     */
    public function sendEmail($toEmail, $toName, $subject, $bodyHtml, $bodyText) {
        try {
            $this->mailer->clearAllRecipients(); // Clear any previous recipients
            $this->mailer->addAddress($toEmail, $toName); // Add a recipient

            $this->mailer->Subject = $subject;
            $this->mailer->Body    = $bodyHtml;
            $this->mailer->AltBody = $bodyText; // Plain text alternative for non-HTML email clients

            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed to {$toEmail}. Mailer Error: {$this->mailer->ErrorInfo}");
            return false;
        }
    }
}