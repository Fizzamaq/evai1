<?php
// includes/admin_header.php
// This file would contain the header specific to admin dashboards.
// Ensure session_start() is already called in config.php.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="<?= ASSETS_PATH ?>css/style.css">
    <link rel="stylesheet" href="<?= ASSETS_PATH ?>css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <header class="main-header">
        <div class="container">
            <a href="<?= BASE_URL ?>admin/dashboard.php" class="logo">EventCraftAI Admin</a>
            <nav class="main-nav">
                <a href="<?= BASE_URL ?>admin/dashboard.php">Dashboard</a>
                <a href="<?= BASE_URL ?>admin/users.php">Users</a>
                <a href="<?= BASE_URL ?>admin/events.php">Events</a>
                <a href="<?= BASE_URL ?>admin/vendors.php">Vendors</a>
                <a href="<?= BASE_URL ?>admin/reports.php">Reports</a>
                <a href="<?= BASE_URL ?>admin/settings.php">Settings</a>
                <a href="<?= BASE_URL ?>public/logout.php">Logout</a>
            </nav>
        </div>
    </header>
    <main class="container">