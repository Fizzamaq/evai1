<?php
// This file assumes session_start() is handled by config.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventCraftAI</title>
    <link rel="stylesheet" href="<?= ASSETS_PATH ?>css/style.css">
    <?php
    // MODIFIED: Dynamically load page-specific CSS, specifically handling auth pages
    $current_page = basename($_SERVER['PHP_SELF'], '.php');
    $page_css_to_load = null;

    // Check if the current page is one of the authentication pages
    if (in_array($current_page, ['login', 'register', 'forgot_password', 'reset_password'])) {
        $page_css_to_load = 'auth.css'; // Always load auth.css for these pages
    } else {
        // For other pages, use the existing dynamic loading logic
        $dynamic_css_file_name = $current_page . '.css';
        if (file_exists("../assets/css/" . $dynamic_css_file_name)) {
            $page_css_to_load = $dynamic_css_file_name;
        }
    }

    if ($page_css_to_load): ?>
        <link rel="stylesheet" href="../assets/css/<?= htmlspecialchars($page_css_to_load) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="main-header">
        <div class="container">
            <a href="<?= BASE_URL ?>public/index.php" class="logo">EventCraftAI</a>

            <div id="mobile-menu-toggle" class="mobile-menu-icon">
                <i class="fas fa-bars"></i>
            </div>

            <nav class="main-nav" id="main-nav-links">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php
                    // Determine which dashboard link to show based on user type
                    $dashboard_link = BASE_URL . 'public/dashboard.php'; // Default for customer
                    $user_type = $_SESSION['user_type'] ?? null;

                    if ($user_type == 2) { // Vendor
                        $dashboard_link = BASE_URL . 'public/vendor_dashboard.php';
                    } elseif ($user_type == 3) { // Admin
                        $dashboard_link = BASE_URL . 'admin/dashboard.php';
                    }
                    ?>
                    <a href="<?= $dashboard_link ?>">Dashboard</a>
                    <a href="<?= BASE_URL ?>public/index.php">Home</a>
                    <?php if ($user_type == 1): // Customer specific links ?>
                        <a href="<?= BASE_URL ?>public/events.php">My Events</a>
                        <a href="<?= BASE_URL ?>public/ai_chat.php">AI Assistant</a>
                        <a href="<?= BASE_URL ?>public/chat.php">Messages</a>
                    <?php elseif ($user_type == 2): // Vendor specific links ?>
                        <a href="<?= BASE_URL ?>public/vendor_portfolio.php">Portfolio</a>
                        <a href="<?= BASE_URL ?>public/vendor_chat.php">Messages</a>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>public/profile.php">Profile</a>
                    <a href="<?= BASE_URL ?>public/logout.php">Logout</a>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>public/index.php">Home</a>
                    <a href="<?= BASE_URL ?>public/login.php">Login</a>
                    <a href="<?= BASE_URL ?>public/register.php">Register</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="container main-content-area">
