h<?php
// public/admin/settings.php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../classes/User.class.php';
require_once __DIR__ . '/../../classes/SystemSettings.class.php';

$user = new User($pdo);
$settings = new SystemSettings($pdo);

if (!$user->isAdmin($_SESSION['user_id'] ?? null)) {
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['settings'] as $key => $value) {
        $sanitizedValue = htmlspecialchars($value);
        $settings->updateSetting($key, $sanitizedValue);
    }
    $_SESSION['success_message'] = "Settings updated successfully!"; // Use 'success_message' for consistency
    header('Location: ' . BASE_URL . 'public/admin/settings.php'); // Redirect back to this page
    exit();
}

// Get all system settings
$system_settings = $settings->getAllSettings();

include '../../includes/admin_header.php'; // Corrected path
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin System Settings - EventCraftAI</title>
    <link rel="stylesheet" href="<?= ASSETS_PATH ?>css/style.css">
    <link rel="stylesheet" href="<?= ASSETS_PATH ?>css/admin.css"> <!-- Admin specific CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

    <div class="admin-container">
        <h1>System Settings</h1>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert success"><?= htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): // Add error message display if needed ?>
            <div class="alert error"><?= htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
        <?php endif; ?>

        <form method="POST">
            <?php foreach ($system_settings as $setting): ?>
                <div class="setting-item">
                    <div class="setting-key"><?php echo htmlspecialchars($setting['setting_key']); ?></div>
                    <div class="setting-description"><?php echo htmlspecialchars($setting['description']); ?></div>

                    <?php if ($setting['data_type'] === 'boolean'): ?>
                        <label>
                            <input type="checkbox" name="settings[<?php echo htmlspecialchars($setting['setting_key']); ?>]"
                                value="1" <?php echo ($setting['setting_value'] == '1') ? 'checked' : ''; ?>> <!-- Ensure comparison for boolean -->
                            Enable
                        </label>
                    <?php elseif ($setting['data_type'] === 'json'): ?>
                        <textarea class="setting-input" name="settings[<?php echo htmlspecialchars($setting['setting_key']); ?>]"
                            rows="4"><?php echo htmlspecialchars($setting['setting_value']); ?></textarea>
                    <?php else: ?>
                        <input type="text" class="setting-input"
                            name="settings[<?php echo htmlspecialchars($setting['setting_key']); ?>]"
                            value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <button type="submit" class="btn btn-primary">Save All Changes</button>
        </form>
    </div>

    <?php include 'admin_footer.php'; ?>
</body>
</html>
