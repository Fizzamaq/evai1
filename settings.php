<?php
session_start();
require_once '../../includes/config.php';
require_once '../../classes/User.class.php';
require_once '../../classes/SystemSettings.class.php';

$user = new User($pdo); // Pass PDO
$settings = new SystemSettings($pdo); // Pass PDO

if (!$user->isAdmin($_SESSION['user_id'] ?? null)) { // Check if user is admin
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['settings'] as $key => $value) {
        // Sanitize input values
        $sanitizedValue = htmlspecialchars($value);

        // Fetch data_type from DB for proper boolean/json handling if needed,
        // or assume basic string conversion based on form input.
        // For boolean checkboxes, a '1' value is submitted if checked, absence if unchecked.
        // Adjust value for boolean based on whether key exists in POST (if it's a checkbox)
        if (isset($_POST['settings_checkboxes']) && in_array($key, $_POST['settings_checkboxes'])) {
             $finalValue = isset($_POST['settings'][$key]) ? '1' : '0';
        } else {
             $finalValue = $sanitizedValue;
        }

        $settings->updateSetting($key, $finalValue);
    }
    $_SESSION['success'] = "Settings updated successfully!";
    header('Location: ' . BASE_URL . 'admin/settings.php');
    exit();
}

// Get all system settings
$system_settings = $settings->getAllSettings();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - EventCraftAI</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .settings-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .setting-item {
            margin-bottom: 25px;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .setting-key {
            font-weight: 600;
            margin-bottom: 8px;
            color: #2d3436;
        }

        .setting-description {
            color: #636e72;
            font-size: 0.9em;
            margin-bottom: 15px;
        }

        .setting-input {
            width: 100%;
            padding: 10px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>

    <div class="settings-container">
        <h1>System Settings</h1>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <form method="POST">
            <?php foreach ($system_settings as $setting): ?>
                <div class="setting-item">
                    <div class="setting-key"><?php echo htmlspecialchars($setting['setting_key']); ?></div>
                    <div class="setting-description"><?php echo htmlspecialchars($setting['description']); ?></div>

                    <?php if ($setting['data_type'] === 'boolean'): ?>
                        <label>
                            <input type="hidden" name="settings_checkboxes[]" value="<?= htmlspecialchars($setting['setting_key']); ?>">
                            <input type="checkbox" name="settings[<?php echo htmlspecialchars($setting['setting_key']); ?>]" 
                                value="1" <?php echo $setting['setting_value'] ? 'checked' : ''; ?>>
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