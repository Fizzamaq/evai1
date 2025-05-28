<?php
// Removed session_start() as it's handled by config.php
// if (session_status() === PHP_SESSION_NONE) {
//     session_start([
//         'cookie_lifetime' => 86400, // 1 day
//         'read_and_close'  => false,
//     ]);
// }
?>
<!--<!DOCTYPE html>-->
<!--<html lang="en">-->
<!--<head>-->
<!--    <meta charset="UTF-8">-->
<!--    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>EventCraftAI</title>-->
<!--    <link rel="stylesheet" href="<?= ASSETS_PATH ?>css/style.css"> -->
<!--    <?php if (file_exists("../assets/css/" . basename($_SERVER['PHP_SELF'], '.php') . '.css')): ?>-->
<!--        <link rel="stylesheet" href="../assets/css/<?= basename($_SERVER['PHP_SELF'], '.php') ?>.css">-->
<!--    <?php endif; ?>-->
<!--    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">-->
<!--</head>-->
<!--<body>-->
<!--    <header class="main-header">-->
<!--        <div class="container"> <a href="<?= BASE_URL ?>public/index.php" class="logo">EventCraftAI</a>-->
<!--            <nav class="main-nav">-->
<!--                <?php if (isset($_SESSION['user_id'])): ?>-->
<!--                    <a href="<?= BASE_URL ?>public/dashboard.php">Dashboard</a>-->
<!--                    <a href="<?= BASE_URL ?>public/events.php">My Events</a>-->
<!--                    <a href="<?= BASE_URL ?>public/ai_chat.php">AI Assistant</a>-->
<!--                    <a href="<?= BASE_URL ?>public/profile.php">Profile</a>-->
<!--                    <a href="<?= BASE_URL ?>public/logout.php">Logout</a>-->
<!--                <?php else: ?>-->
<!--                    <a href="<?= BASE_URL ?>public/index.php">Home</a>-->
<!--                    <a href="<?= BASE_URL ?>public/login.php">Login</a>-->
<!--                    <a href="<?= BASE_URL ?>public/register.php">Register</a>-->
<!--                <?php endif; ?>-->
<!--            </nav>-->
<!--        </div>-->
<!--    </header>-->
<!--    <main class="container main-content-area"> -->
    
<?php
// This file assumes session_start() is handled by config.php
// [cite: fizzamaq/evai/evai-270c475187253adadaf42cfe122a431191cf1f80/config.php]
// [cite: fizzamaq/evai/evai-270c475187253adadaf42cfe122a431191cf1f80/header.php]
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventCraftAI</title>
    <link rel="stylesheet" href="<?= ASSETS_PATH ?>css/style.css"> 
    <?php 
    // Dynamically load page-specific CSS if it exists
    $page_css_file = basename($_SERVER['PHP_SELF'], '.php') . '.css';
    if (file_exists("../assets/css/" . $page_css_file)): ?>
        <link rel="stylesheet" href="../assets/css/<?= htmlspecialchars($page_css_file) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="main-header">
        <div class="container"> 
            <a href="<?= BASE_URL ?>public/index.php" class="logo">EventCraftAI</a>
            <nav class="main-nav">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php 
                    // Determine which dashboard link to show based on user type
                    $dashboard_link = BASE_URL . 'public/dashboard.php'; // Default for customer
                    if (isset($_SESSION['user_type'])) {
                        if ($_SESSION['user_type'] == 2) { // Vendor
                            $dashboard_link = BASE_URL . 'public/vendor_dashboard.php';
                        } elseif ($_SESSION['user_type'] == 3) { // Admin
                            $dashboard_link = BASE_URL . 'admin/dashboard.php';
                        }
                    }
                    ?>
                    <a href="<?= $dashboard_link ?>">Dashboard</a>
                    <a href="<?= BASE_URL ?>public/events.php">My Events</a>
                    <a href="<?= BASE_URL ?>public/ai_chat.php">AI Assistant</a>
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
