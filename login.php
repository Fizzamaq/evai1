<?php
require_once '../includes/config.php';
include 'header.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = $_SESSION['login_error'] ?? null;
$success = $_SESSION['registration_success'] ?? null;
unset($_SESSION['login_error'], $_SESSION['registration_success']);
?>
<div class="auth-container">
    <h2>Login to Your Account</h2>
    
    <?php if ($success): ?>
        <div class="alert success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form action="process_login.php" method="post">
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required>
        </div>
        <button type="submit" class="btn primary">Login</button>
    </form>
    <p>Don't have an account? <a href="register.php">Register here</a></p>
</div>
<?php include 'footer.php'; ?>
