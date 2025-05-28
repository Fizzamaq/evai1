<?php
require_once '../includes/config.php';
include 'header.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = $_SESSION['register_error'] ?? null;
unset($_SESSION['register_error']);
?>
<div class="auth-container">
    <h2>Create Your Account</h2>
    
    <?php if ($error): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form action="process_register.php" method="post" id="registerForm">
        <div class="form-group">
            <label>First Name</label>
            <input type="text" name="first_name" required>
        </div>
        <div class="form-group">
            <label>Last Name</label>
            <input type="text" name="last_name" required>
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required>
        </div>
        <div class="form-group">
            <label>Password (min 8 characters)</label>
            <input type="password" name="password" minlength="8" required>
        </div>
        <div class="form-group">
            <label>User Type</label>
            <select name="user_type" required>
                <option value="1">Event Planner</option>
                <option value="2">Vendor</option>
            </select>
        </div>
        <button type="submit" class="btn primary">Register</button>
    </form>
    <p>Already have an account? <a href="login.php">Login here</a></p>
</div>
<?php include 'footer.php'; ?>