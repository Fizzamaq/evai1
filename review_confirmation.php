<?php
session_start();
require_once '../includes/config.php';
include 'header.php';

// Retrieve the success message from the session
$success_message = $_SESSION['success'] ?? null;
unset($_SESSION['success']); // Clear the message to prevent it from showing again on refresh

if (empty($success_message)) {
    // If there's no message, redirect to the dashboard or events page
    header('Location: ' . BASE_URL . 'public/dashboard.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Submitted</title>
    <link rel="stylesheet" href="<?= ASSETS_PATH ?>css/style.css">
    <style>
        .confirmation-container {
            max-width: 600px;
            margin: var(--spacing-xxl) auto;
            padding: var(--spacing-lg);
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
            text-align: center;
            border: 1px solid var(--success-color);
        }
        .confirmation-container h1 {
            color: var(--success-color);
            font-size: 2.5em;
            margin-bottom: var(--spacing-md);
        }
        .confirmation-container p {
            font-size: 1.2em;
            color: var(--text-dark);
            margin-bottom: var(--spacing-lg);
        }
        .confirmation-container .btn {
            margin-top: var(--spacing-md);
        }
    </style>
</head>
<body>
    <div class="confirmation-container">
        <h1><i class="fas fa-check-circle"></i> Review Submitted!</h1>
        <p><?= htmlspecialchars($success_message) ?></p>
        <a href="<?= BASE_URL ?>public/events.php" class="btn btn-primary">Go to My Events</a>
        <a href="<?= BASE_URL ?>public/dashboard.php" class="btn btn-secondary">Go to Dashboard</a>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>