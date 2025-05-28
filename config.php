<?php
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

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'u950050130_ecai');
define('DB_PASS', '!@#Acc3ss931!@#');
define('DB_NAME', 'u950050130_ecai');
define('BASE_URL', 'https://eventcraft.gatvia.com/');
define('ASSETS_PATH', BASE_URL . 'assets/');
// --- ADD THIS LINE FOR OPENAI API KEY ---
define('OPENAI_API_KEY', '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'); // Replace with your actual key!
// --- END OF ADDITION ---

// Initialize PDO Connection
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
