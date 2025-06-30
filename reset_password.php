<?php
require_once __DIR__ . '/../includes/config.php'; // Correct path to config.php
require_once __DIR__ . '/../classes/User.class.php'; // Correct path to User.class.php
require_once __DIR__ . '/../includes/auth.php'; // Corrected path to auth.php

// session_start(); // Should be handled by config.php
$user = new User($pdo); // Pass PDO

// Verify token validity
$token = $_GET['token'] ?? null;
$validToken = false;
if ($token) {
    $validToken = $user->validateResetToken($token);
}

if (!$validToken) {
    $_SESSION['error'] = "Invalid or expired reset link";
    header('Location: ' . BASE_URL . 'public/forgot_password.php');
    exit();
}

// Generate CSRF token for the form
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = "Invalid request. Please try again.";
        header('Location: ' . BASE_URL . 'public/reset_password.php?token=' . urlencode($token));
        exit();
    }

    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    if (empty($password) || empty($confirmPassword)) {
        $_SESSION['error'] = "Password fields cannot be empty.";
    } elseif ($password !== $confirmPassword) {
        $_SESSION['error'] = "Passwords do not match";
    } elseif (strlen($password) < 8) {
        $_SESSION['error'] = "Password must be at least 8 characters.";
    } elseif ($user->resetPassword($token, $password)) {
        $_SESSION['success'] = "Password updated successfully!";
        header('Location: ' . BASE_URL . 'public/login.php');
        exit();
    } else {
        $_SESSION['error'] = "Failed to reset password. Please try again.";
    }
    // If there's an error, redirect back to the reset page with token
    header('Location: ' . BASE_URL . 'public/reset_password.php?token=' . urlencode($token));
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password - EventCraftAI</title>
    <link rel="stylesheet" href="<?= ASSETS_PATH ?>css/auth.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="auth-container">
        <div class="auth-card">
            <h1>Set New Password</h1>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert error"><?= htmlspecialchars($_SESSION['error']) ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="password" required minlength="8">
                </div>

                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" required>
                </div>

                <button type="submit" class="btn primary">Reset Password</button>
            </form>
        </div>
    </div>
</body>
</html>
