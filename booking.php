<?php
session_start();
require_once '../includes/config.php';
require_once '../classes/Booking.class.php';
require_once '../classes/Event.class.php'; // Required for event_title
require_once '../classes/Vendor.class.php'; // Required for business_name
require_once '../classes/User.class.php'; // Required for getUserBooking JOIN

include 'header.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

$bookingId = (int)$_GET['id'];
$booking = new Booking($pdo);

// Re-fetch booking details with relevant joins for display
// FIX: Corrected the JOIN condition for vendor_profiles from vp.user_id to vp.id
$stmt = $pdo->prepare("
    SELECT b.*, e.title as event_title, vp.business_name
    FROM bookings b
    LEFT JOIN events e ON b.event_id = e.id /* Use LEFT JOIN as event_id can be null now */
    JOIN vendor_profiles vp ON b.vendor_id = vp.id
    WHERE b.id = ? AND b.user_id = ?
");
$stmt->execute([$bookingId, $_SESSION['user_id']]);
$bookingDetails = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$bookingDetails) {
    $_SESSION['error'] = "Booking not found or access denied.";
    header('Location: ' . BASE_URL . 'public/dashboard.php');
    exit();
}

// --- Parse Special Instructions for Selected Services and Uploaded Picture URL ---
$original_instructions = htmlspecialchars($bookingDetails['special_instructions']);
$selected_services_display = 'N/A';
$uploaded_picture_url = null;
$clean_instructions = $original_instructions; // This will hold instructions without the parsed parts

// Pattern to find "Selected Services: ..."
if (preg_match('/Selected Services:\s*(.*?)(?:\n\n|$)/s', $original_instructions, $matches_services)) {
    $selected_services_display = htmlspecialchars($matches_services[1]);
    $clean_instructions = str_replace($matches_services[0], '', $clean_instructions);
}

// Pattern to find "Uploaded Picture URL: ..."
if (preg_match('/Uploaded Picture URL:\s*(.*?)(?:\n\n|$)/s', $original_instructions, $matches_picture)) {
    $uploaded_picture_url = htmlspecialchars(trim($matches_picture[1]));
    $clean_instructions = str_replace($matches_picture[0], '', $clean_instructions);
}

// Clean up any double newlines or leading/trailing whitespace left from parsing
$clean_instructions = trim(preg_replace('/\n\s*\n/', "\n\n", $clean_instructions));

?>
<div class="booking-details-container">
    <h1>Booking Details</h1>
    <?php if (isset($_SESSION['success'])) { ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php } ?>
    <?php if (isset($_SESSION['error'])) { ?>
        <div class="alert alert-error"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php } ?>

    <p><strong>Booking ID:</strong> <?= htmlspecialchars($bookingDetails['id']) ?></p>
    <p><strong>Vendor:</strong> <?= htmlspecialchars($bookingDetails['business_name']) ?></p>
    <p><strong>Date:</strong> <?= date('M j, Y', strtotime($bookingDetails['created_at'])) ?> (Booking Created)</p>
    
    <div class="booking-section">
        <h3>Selected Services</h3>
        <p><?= $selected_services_display ?></p>
    </div>

    <?php if (!empty($clean_instructions)): ?>
    <div class="booking-section">
        <h3>Special Instructions</h3>
        <p><?= nl2br($clean_instructions) ?></p>
    </div>
    <?php endif; ?>

    <?php if (!empty($uploaded_picture_url)): ?>
    <div class="booking-section">
        <h3>Uploaded Picture</h3>
        <img src="<?= BASE_URL . $uploaded_picture_url ?>" alt="Uploaded Booking Reference" style="max-width: 300px; height: auto; border: 1px solid #ddd; border-radius: 8px; display: block; margin-top: 10px;">
    </div>
    <?php endif; ?>

    <div class="booking-actions" style="margin-top: 30px;">
        <?php if ($bookingDetails['status'] === 'completed' && !$bookingDetails['is_reviewed']): ?>
            <a href="<?= BASE_URL ?>public/review.php?booking_id=<?= $bookingId ?>" class="btn btn-primary">Leave Review</a>
        <?php elseif ($bookingDetails['status'] === 'pending_payment'): ?>
             <a href="<?= BASE_URL ?>public/payment.php?booking_id=<?= $bookingId ?>" class="btn btn-primary">Complete Payment</a>
        <?php endif; ?>
        <?php if (!empty($bookingDetails['chat_conversation_id'])): ?>
            <a href="<?= BASE_URL ?>public/chat.php?conversation_id=<?= htmlspecialchars($bookingDetails['chat_conversation_id']) ?>" class="btn btn-secondary">Go to Chat</a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>public/chat.php?vendor_id=<?= htmlspecialchars($bookingDetails['vendor_id']) ?>&event_id=<?= htmlspecialchars($bookingDetails['event_id']) ?>" class="btn btn-secondary">Start Chat</a>
        <?php endif; ?>
    </div>
</div>
<?php include 'footer.php'; ?>
