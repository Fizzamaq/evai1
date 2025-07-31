<?php
session_start();
require_once '../includes/config.php';
// You might want to include more classes here if you intend to fetch/display more details
// e.g., require_once '../classes/Booking.class.php';

// Get booking ID from URL if passed, for display purposes
$bookingId = $_GET['booking_id'] ?? 'N/A';

// You can add logic here to prevent direct access if desired,
// or fetch more booking details if needed for a richer confirmation message.

include 'header.php'; // Include the main site header
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmed!</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
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
        <h1><i class="fas fa-check-circle"></i> Booking Confirmed!</h1>
        <p>Booking #<strong><?= htmlspecialchars($bookingId) ?></strong> is confirmed.</p>
        <p>You can now proceed with further steps for this booking.</p>
        <a href="<?= BASE_URL ?>public/vendor_dashboard.php" class="btn btn-primary">Go to Dashboard</a>
        <a href="<?= BASE_URL ?>public/vendor_chat.php" class="btn btn-secondary">Go to Messages</a>
    </div>

<?php include 'footer.php'; ?>

</body>
</html>