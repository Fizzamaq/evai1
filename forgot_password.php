<?php
// Corrected path to config.php
require_once __DIR__ . '/../includes/config.php'; 
// Corrected path to User.class.php
require_once __DIR__ . '/../classes/User.class.php';

// session_start(); // Should be handled by config.php

// Instantiate User class with $pdo
$user = new User($pdo); 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    // Pass $pdo to the User constructor
    if ($user->initiatePasswordReset($email)) {
        $_SESSION['success'] = "Password reset link sent to your email address.";
    } else {
        // Provide a generic message for security, regardless of whether email exists or not
        $_SESSION['error'] = "If an account with that email exists, a password reset link has been sent.";
    }
    header('Location: forgot_password.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Password Recovery - EventCraftAI</title>
    <link rel="stylesheet" href="<?= ASSETS_PATH ?>css/auth.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <h1>Forgot Password?</h1>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert success"><?= htmlspecialchars($_SESSION['success']) ?></div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert error"><?= htmlspecialchars($_SESSION['error']) ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required>
                </div>
                
                <button type="submit" class="btn primary">Send Reset Link</button>
            </form>
            
            <div class="auth-links">
                <a href="login.php">Remember your password? Login</a>
            </div>
        </div>
    </div>
</body>
</html>
