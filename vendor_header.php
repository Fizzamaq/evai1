<?php
// includes/vendor_header.php
// This file would contain the header specific to vendor dashboards.
// It should include common navigation links for vendors.
// Ensure session_start() is already called in config.php.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Dashboard</title>
    <link rel="stylesheet" href="<?= ASSETS_PATH ?>css/style.css">
    <link rel="stylesheet" href="<?= ASSETS_PATH ?>css/dashboard.css">
    <?php if (file_exists(ASSETS_PATH . 'css/vendor.css')): ?>
        <link rel="stylesheet" href="<?= ASSETS_PATH ?>css/vendor.css">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <header class="main-header">
        <div class="container">
            <a href="<?= BASE_URL ?>public/dashboard.php" class="logo">EventCraftAI Vendor</a>
            <nav class="main-nav">
                <a href="<?= BASE_URL ?>public/dashboard.php">Dashboard</a>
                <a href="<?= BASE_URL ?>public/vendor_portfolio.php">Portfolio</a>
                <a href="<?= BASE_URL ?>public/vendor_availability.php">Availability</a>
                <a href="<?= BASE_URL ?>public/chat.php">Messages</a>
                <a href="<?= BASE_URL ?>public/reports.php">Reports</a>
                <a href="<?= BASE_URL ?>public/profile.php">Profile</a>
                <a href="<?= BASE_URL ?>public/logout.php">Logout</a>
            </nav>
        </div>
    </header>
    <main class="container">