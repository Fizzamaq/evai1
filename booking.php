<?php
session_start();
require_once '../includes/config.php';
require_once '../classes/Booking.class.php';
include 'header.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

$bookingId = (int)$_GET['id'];
$booking = new Booking($pdo);
$bookingDetails = $booking->getUserBooking($_SESSION['user_id'], $bookingId);

if (!$bookingDetails) {
    $_SESSION['error'] = "Booking not found or access denied.";
    header('Location: ' . BASE_URL . 'public/dashboard.php');
    exit();
}
?>
<div class="booking-details-container">
    <h1>Booking Details</h1>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <p><strong>Booking ID:</strong> <?= htmlspecialchars($bookingDetails['id']) ?></p>
    <p><strong>Event Title:</strong> <?= htmlspecialchars($bookingDetails['event_title'] ?? 'N/A') ?></p>
    <p><strong>Vendor:</strong> <?= htmlspecialchars($bookingDetails['business_name']) ?></p>
    <p><strong>Service Date:</strong> <?= date('M j, Y', strtotime($bookingDetails['service_date'])) ?></p>
    <p><strong>Total Amount:</strong> $<?= number_format($bookingDetails['final_amount'], 2) ?></p>
    <p><strong>Deposit:</strong> $<?= number_format($bookingDetails['deposit_amount'], 2) ?></p>
    <p><strong>Status:</strong> <?= htmlspecialchars(ucfirst($bookingDetails['status'])) ?></p>
    <p><strong>Special Instructions:</strong> <?= nl2br(htmlspecialchars($bookingDetails['special_instructions'])) ?></p>

    <div class="booking-actions">
        <?php if ($bookingDetails['status'] === 'completed' && !$bookingDetails['is_reviewed']): ?>
            <a href="<?= BASE_URL ?>public/review.php?booking_id=<?= $bookingId ?>" class="btn btn-primary">Leave Review</a>
        <?php elseif ($bookingDetails['status'] === 'pending_payment'): ?>
             <a href="<?= BASE_URL ?>public/payment.php?booking_id=<?= $bookingId ?>" class="btn btn-primary">Complete Payment</a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>public/chat.php?conversation_id=<?= htmlspecialchars($bookingDetails['chat_conversation_id'] ?? '') ?>" class="btn btn-secondary">Go to Chat</a>
    </div>
</div>
<?php include 'footer.php'; ?>