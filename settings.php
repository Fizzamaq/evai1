<?php
// public/admin/settings.php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../classes/User.class.php';
require_once __DIR__ . '/../../classes/SystemSettings.class.php';

$user = new User($pdo);
$settings = new SystemSettings($pdo);

// ADDED: Ensure default settings are present in the database.
// This will populate the system_settings table if it's empty.
$settings->ensureDefaultSettings(); 

if (!$user->isAdmin($_SESSION['user_id'] ?? null)) {
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['settings'] as $key => $value) {
        // htmlspecialchars is generally good for output, but for values to be saved/parsed
        // as JSON or other types, direct htmlspecialchars might double-encode or break JSON.
        // It's safer to sanitize based on data_type if strict parsing happens later.
        // For simple text values, htmlspecialchars is fine before saving to DB.
        $sanitizedValue = $value; // Assuming SystemSettings class handles final DB escaping
        $settings->updateSetting($key, $sanitizedValue);
    }
    $_SESSION['success_message'] = "Settings updated successfully!"; // Use 'success_message' for consistency
    header('Location: ' . BASE_URL . 'public/admin/settings.php'); // Redirect back to this page
    exit();
}

// Get all system settings
$system_settings = $settings->getAllSettings(); // This line fetches the settings

// Include admin header
include '../../includes/admin_header.php'; // Correct path
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin System Settings - EventCraftAI</title>
    <link rel="stylesheet" href="<?= ASSETS_PATH ?>css/style.css">
    <link rel="stylesheet" href="<?= ASSETS_PATH ?>css/admin.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Styles specific to settings page layout */
        .setting-item {
            background-color: var(--background-light);
            padding: var(--spacing-md);
            border-radius: 8px;
            margin-bottom: var(--spacing-md);
            border: 1px solid var(--light-grey-border);
            display: flex;
            flex-direction: column;
            gap: var(--spacing-xs);
        }
        .setting-key {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 1.1em;
        }
        .setting-description {
            font-size: 0.9em;
            color: var(--text-subtle);
            margin-bottom: var(--spacing-sm);
        }
        .setting-input {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-size: 1em;
            box-sizing: border-box; /* Include padding in width */
        }
        .setting-item label { /* For checkbox labels */
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: normal;
            color: var(--text-dark);
        }
        .setting-item input[type="checkbox"] {
            transform: scale(1.1); /* Slightly larger checkbox */
        }
        .admin-section-card form .btn { /* Style submit button within the form */
            margin-top: var(--spacing-md);
            width: auto; /* Auto width for buttons */
            padding: 10px 20px;
        }
    </style>
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

        <div class="admin-section-card"> <form method="POST">
                <?php foreach ($system_settings as $setting): ?>
                    <div class="setting-item">
                        <div class="setting-key"><?php echo htmlspecialchars($setting['setting_key']); ?></div>
                        <div class="setting-description"><?php echo htmlspecialchars($setting['description']); ?></div>

                        <?php if ($setting['data_type'] === 'boolean'): ?>
                            <label>
                                <input type="checkbox" name="settings[<?php echo htmlspecialchars($setting['setting_key']); ?>]"
                                    value="1" <?php echo ($setting['setting_value'] == '1') ? 'checked' : ''; ?>> Enable
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
        </div> </div>

    <?php include '../../includes/admin_footer.php'; ?>
</body>
</html>
