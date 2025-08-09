<?php
// public/run_booking_updates.php
// This is a manual script to trigger the booking status update.
// In a production environment, this would be a scheduled task (cron job).
// To run this script, navigate to its URL in your browser.

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session to access BASE_URL from config.php
session_start();

// Include necessary files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../classes/Booking.class.php';

// Instantiate the Booking class
$booking = new Booking($pdo);

// Call the function to update booking statuses
$updated_count = $booking->updateCompletedBookings();

// Display a success message
$message = "Booking status update complete. {$updated_count} bookings were updated from 'confirmed' to 'completed'.";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Update Script</title>
    <link rel="stylesheet" href="<?= ASSETS_PATH ?>css/style.css">
</head>
<body>
    <div class="container" style="text-align: center; margin-top: 50px;">
        <h1>Booking Update Status</h1>
        <p><?= htmlspecialchars($message) ?></p>
        <a href="<?= BASE_URL ?>public/dashboard.php" class="btn btn-primary" style="margin-top: 20px;">Return to Dashboard</a>
    </div>
</body>
</html>