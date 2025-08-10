<?php
// public/booking.php
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
$vendor_obj = new Vendor($pdo);
$user_obj = new User($pdo);

// Fetch booking details first, without restricting by user_id yet
$bookingDetails = $booking->getBooking($bookingId);

if (!$bookingDetails) {
    $_SESSION['error'] = "Booking not found.";
    header('Location: ' . BASE_URL . 'public/dashboard.php');
    exit();
}

// --- NEW ACCESS CONTROL LOGIC ---
$is_admin = (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 3);
$is_customer = ($bookingDetails['user_id'] == $_SESSION['user_id']);
$is_vendor_of_booking = false;

// Check if logged-in user is the vendor for this booking
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 2) {
    $vendor_profile = $vendor_obj->getVendorByUserId($_SESSION['user_id']);
    if ($vendor_profile && $bookingDetails['vendor_id'] == $vendor_profile['id']) {
        $is_vendor_of_booking = true;
    }
}

// If user is not the customer, not the vendor, and not an admin, deny access
if (!$is_customer && !$is_vendor_of_booking && !$is_admin) {
    $_SESSION['error'] = "Access denied. You do not have permission to view this booking.";
    header('Location: ' . BASE_URL . 'public/dashboard.php');
    exit();
}

// Re-fetch booking with additional details for display purposes now that access is granted
$stmt = $pdo->prepare("
    SELECT b.*, e.title as event_title, vs.service_name, vp.business_name, u.first_name, u.last_name
    FROM bookings b
    LEFT JOIN events e ON b.event_id = e.id
    LEFT JOIN vendor_services vs ON b.service_id = vs.id
    LEFT JOIN vendor_profiles vp ON b.vendor_id = vp.id
    LEFT JOIN users u ON b.user_id = u.id
    WHERE b.id = ?
");
$stmt->execute([$bookingId]);
$bookingDetails = $stmt->fetch(PDO::FETCH_ASSOC);

// If the booking details couldn't be re-fetched for some reason, redirect.
if (!$bookingDetails) {
    $_SESSION['error'] = "Booking not found or an error occurred.";
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
<head>
    <link rel="stylesheet" href="../assets/css/booking_details.css"> </head>
<div class="booking-details-wrapper">
    <div class="booking-header">
        <h1>Booking Details</h1>
        <?php if (isset($_SESSION['success'])) { ?>
            <div class="alert success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php } ?>
        <?php if (isset($_SESSION['error'])) { ?>
            <div class="alert error"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php } ?>
    </div>

    <div class="booking-summary-card">
        <h3>Summary</h3>
        <p><strong>Booking ID:</strong> <span><?= htmlspecialchars($bookingDetails['id']) ?></span></p>
        <p><strong>Event:</strong> <span><?= htmlspecialchars($bookingDetails['event_title'] ?? 'N/A') ?></span></p>
        <p><strong>Vendor:</strong> <a href="<?= BASE_URL ?>public/vendor_profile.php?id=<?= htmlspecialchars($bookingDetails['vendor_id']) ?>"><?= htmlspecialchars($bookingDetails['business_name']) ?></a></p>
        <p><strong>Service Date:</strong> <span><?= date('M j, Y', strtotime($bookingDetails['service_date'])) ?></span></p>
        <?php if (!empty($bookingDetails['service_time'])): ?>
        <p><strong>Service Time:</strong> <span><?= date('g:i A', strtotime($bookingDetails['service_time'])) ?></span></p>
        <?php endif; ?>
        <p><strong>Status:</strong> <span class="status-badge status-<?= strtolower($bookingDetails['status']) ?>"><?= ucfirst(htmlspecialchars($bookingDetails['status'])) ?></span></p>
        <p class="final-amount"><strong>Total Amount:</strong> <span>PKR <?= number_format($bookingDetails['final_amount'], 2) ?></span></p>
        <?php if (!empty($bookingDetails['deposit_amount'])): ?>
        <p><strong>Deposit:</strong> <span>PKR <?= number_format($bookingDetails['deposit_amount'], 2) ?></span></p>
        <?php endif; ?>
    </div>
    
    <div class="booking-section-card">
        <h3>Selected Services</h3>
        <p><?= $selected_services_display ?></p>
    </div>

    <?php if (!empty($clean_instructions)): ?>
    <div class="booking-section-card">
        <h3>Special Instructions</h3>
        <p><?= nl2br($clean_instructions) ?></p>
    </div>
    <?php endif; ?>

    <?php if (!empty($uploaded_picture_url)): ?>
    <div class="booking-section-card">
        <h3>Uploaded Picture</h3>
        <img src="<?= BASE_URL . $uploaded_picture_url ?>" alt="Uploaded Booking Reference">
    </div>
    <?php endif; ?>

    <div class="booking-actions">
        <?php if ($is_customer && $bookingDetails['status'] === 'completed' && !$bookingDetails['is_reviewed']): ?>
            <a href="<?= BASE_URL ?>public/review.php?booking_id=<?= $bookingId ?>" class="btn btn-primary">Leave Review</a>
        <?php elseif ($is_customer && $bookingDetails['status'] === 'pending_payment'): ?>
             <a href="<?= BASE_URL ?>public/payment.php?booking_id=<?= $bookingId ?>" class="btn btn-primary">Complete Payment</a>
        <?php endif; ?>
        <?php if ($is_customer || $is_vendor_of_booking): ?>
            <?php if (!empty($bookingDetails['chat_conversation_id'])): ?>
                <a href="<?= BASE_URL ?>public/chat.php?conversation_id=<?= htmlspecialchars($bookingDetails['chat_conversation_id']) ?>" class="btn btn-secondary">Go to Chat</a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>public/chat.php?vendor_id=<?= htmlspecialchars($bookingDetails['vendor_id']) ?>&event_id=<?= htmlspecialchars($bookingDetails['event_id']) ?>" class="btn btn-secondary">Start Chat</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php include 'footer.php'; ?>
