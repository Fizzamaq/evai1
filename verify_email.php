<?php
session_start();
require_once '../includes/config.php';
require_once '../classes/User.class.php';

$user = new User($pdo);

$token = $_GET['token'] ?? '';
$message = '';
$message_type = '';

if (empty($token)) {
    $message = "No verification token provided.";
    $message_type = 'error';
} else {
    try {
        if ($user->verifyEmail($token)) {
            $message = "Your email has been successfully verified! You can now log in.";
            $message_type = 'success';
        } else {
            // Specific error messages would be caught by the try-catch in verifyEmail method and re-thrown
            $message = "Failed to verify email. The link might be invalid or expired.";
            $message_type = 'error';
        }
    } catch (Exception $e) {
        $message = "Verification error: " . htmlspecialchars($e->getMessage());
        $message_type = 'error';
    }
}

// Redirect to login page with message
$_SESSION['login_message_type'] = $message_type;
$_SESSION['login_message'] = $message;
header('Location: ' . BASE_URL . 'public/login.php');
exit();
?>