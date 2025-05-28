<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/classes/User.class.php';

$user = new User();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    if ($user->initiatePasswordReset($email)) {
        $_SESSION['success'] = "Reset link sent to your email";
    } else {
        $_SESSION['error'] = "Error processing request";
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
    <link rel="stylesheet" href="/assets/css/auth.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <h1>Forgot Password?</h1>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert success"><?= $_SESSION['success'] ?></div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert error"><?= $_SESSION['error'] ?></div>
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