<?php
// config.php
// Start session first
if (session_status() === PHP_SESSION_NONE) {
    // Session Configuration
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// Load PHPMailer's autoloader very early for its constants
// This path is relative to config.php (which is in 'includes/')
// So, '../vendor/autoload.php' points to [project_root]/vendor/autoload.php
require_once __DIR__ . '/../vendor/autoload.php';

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'u950050130_ecai');
define('DB_PASS', '!@#Acc3ss931!@#');
define('DB_NAME', 'u950050130_ecai');

// Base URL and Asset Path Configuration
// This BASE_URL assumes your domain points to the project's root folder,
// where 'public/', 'classes/', 'includes/', and 'vendor/' are located.
define('BASE_URL', 'https://eventcraft.gatvia.com/');
define('ASSETS_PATH', BASE_URL . 'assets/');

// OpenAI API Key (for AI Assistant functionality)
define('OPENAI_API_KEY', 'Fizza'); // !! IMPORTANT: Replace with your actual OpenAI API Key !!

// SMTP Mailer Configuration for MailSender.class.php
// !! IMPORTANT: Replace these with your actual SMTP server details !!
define('SMTP_HOST', 'smtp.gmail.com'); // e.g., 'smtp.gmail.com', 'smtp.mailtrap.io'
define('SMTP_AUTH', true); // Set to true if your SMTP server requires authentication
define('SMTP_USERNAME', 'fleura.pk@gmail.com'); // Your email address for SMTP authentication
define('SMTP_PASSWORD', 'ypqmlfyvyqxxzeuo'); // Your email password for SMTP authentication
define('SMTP_SECURE', PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS); // Use PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS for port 465 (SSL); PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS for port 587 (TLS)
define('SMTP_PORT', 465); // Common ports: 465 for SMTPS, 587 for STARTTLS

// Sender Email Details (for emails sent by the application)
define('MAIL_FROM_EMAIL', 'fleura.pk@gmail.com'); // This should be an email address on your domain or authorized by your SMTP host
define('MAIL_FROM_NAME', 'EventCraftAI');


// Stripe Payment Gateway Configuration (if applicable for future use)
define('STRIPE_PUBLISHABLE_KEY', 'Fizza'); // Frontend key
define('STRIPE_SECRET_KEY', 'Fizza'); // Backend key
define('STRIPE_WEBHOOK_SECRET', 'whsec_YOUR_WEBHOOK_SECRET'); // For webhook signature verification


// Initialize PDO Database Connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection error. Please try again later.");
}

// Load helper functions
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';


// <?php
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
// // ... rest of your config.php content ...

// // Start session first
// if (session_status() === PHP_SESSION_NONE) {
//     // Session Configuration
//     session_set_cookie_params([
//         'lifetime' => 86400,
//         'path' => '/',
//         'domain' => $_SERVER['HTTP_HOST'],
//         'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
//         'httponly' => true,
//         'samesite' => 'Strict'
//     ]);
//     session_start();
// }

// // Database Configuration
// define('DB_HOST', 'localhost');
// define('DB_USER', 'u950050130_ecai');
// define('DB_PASS', '!@#Acc3ss931!@#');
// define('DB_NAME', 'u950050130_ecai');
// define('BASE_URL', 'https://eventcraft.gatvia.com/');
// define('ASSETS_PATH', BASE_URL . 'assets/');
// // --- ADD THIS LINE FOR OPENAI API KEY ---
// define('OPENAI_API_KEY', 'Fizza'); // Replace with your actual key!
// // --- END OF ADDITION ---

// // Initialize PDO Connection
// try {
//     $pdo = new PDO(
//         "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
//         DB_USER,
//         DB_PASS,
//         [
//             PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
//             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
//             PDO::ATTR_EMULATE_PREPARES => false
//         ]
//     );
// } catch (PDOException $e) {
//     error_log("Database connection failed: " . $e->getMessage());
//     die("Database connection error. Please try again later.");
// }

// // Load helper functions
// require_once __DIR__ . '/auth.php';
// require_once __DIR__ . '/db.php';



