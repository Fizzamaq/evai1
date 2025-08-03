<?php
require_once '../includes/config.php';
require_once '../classes/Event.class.php'; // Include Event class
require_once '../classes/Booking.class.php'; // Include Booking class to check bookings
require_once '../classes/User.class.php'; // For getting user details if event created by another user
require_once '../classes/Vendor.class.php'; // For vendor profile link

include 'header.php'; // This header automatically handles user type and loads appropriate CSS

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: " . BASE_URL . "public/login.php");
    exit();
}

$eventId = (int)$_GET['id'];
$userId = $_SESSION['user_id'];

$event_obj = new Event($pdo); // Pass PDO
$booking_obj = new Booking($pdo); // Instantiate Booking class
$user_obj = new User($pdo); // Instantiate User class
$vendor_obj = new Vendor($pdo); // Instantiate Vendor class

$eventDetails = $event_obj->getEventById($eventId); // Use getEventById without user_id for general view

// Important: Add an authorization check here to ensure only the owner or an admin can view.
// If the user is not an admin, verify ownership.
if (!isset($_SESSION['user_type']) || ($_SESSION['user_type'] != 3)) { // Assuming 3 is admin type
    if (!isset($eventDetails['user_id']) || $eventDetails['user_id'] != $userId) {
        $_SESSION['error_message'] = "You do not have permission to view this event.";
        header("Location: " . BASE_URL . "public/events.php");
        exit();
    }
}


if (empty($eventDetails)) {
    $_SESSION['error_message'] = "Event not found.";
    header("Location: " . BASE_URL . "public/events.php");
    exit();
}

// --- Determine displayed status based on bookings ---
$displayStatus = ucfirst(htmlspecialchars($eventDetails['status'])); // Default to event status
$statusClass = strtolower(htmlspecialchars($eventDetails['status'])); // Default to event status class
$needsReview = false; // Flag for review button visibility
$reviewBookingId = null; // Variable to hold the booking ID for the review link


$bookingsForEvent = $booking_obj->getBookingsByEventId($eventId); // Fetch all bookings for this event
$hasAnyBooking = !empty($bookingsForEvent); // Flag to check if any booking exists for the event

if ($hasAnyBooking) {
    $hasConfirmed = false;
    $hasPendingReview = false;
    $hasCancelledOrFailedBooking = false;
    $hasBeenReviewed = false;

    foreach ($bookingsForEvent as $booking) {
        if ($booking['status'] === 'confirmed') {
            $hasConfirmed = true;
        }
        if ($booking['status'] === 'pending_review' || $booking['status'] === 'pending' || $booking['status'] === "") { 
            $hasPendingReview = true; 
        }
        if ($booking['status'] === 'cancelled' || $booking['status'] === 'payment_failed' || $booking['status'] === 'refunded') { 
            $hasCancelledOrFailedBooking = true;
        }
        
        // Check if a completed booking needs a review
        if (($booking['status'] === 'completed') && ($booking['is_reviewed'] == 0)) {
            $needsReview = true;
            $reviewBookingId = $booking['id'];
        }

        // Check if a completed booking has already been reviewed
        if ($booking['is_reviewed'] == 1) {
            $hasBeenReviewed = true;
        }
    }

    if ($needsReview) {
        $displayStatus = 'Pending Review';
        $statusClass = 'pending-review-action';
    } elseif ($hasBeenReviewed) {
        $displayStatus = 'Reviewed';
        $statusClass = 'reviewed';
    } elseif ($hasConfirmed) {
        $displayStatus = 'Booked';
        $statusClass = 'booked';
    } elseif ($hasCancelledOrFailedBooking) {
        $displayStatus = 'Cancelled/Failed';
        $statusClass = 'cancelled';
    } elseif ($hasPendingReview) {
        $displayStatus = 'Pending';
        $statusClass = 'pending';
    }
}
// --- END OF STATUS LOGIC ---


// Get the name and details of the user who created the event
$eventCreator = $user_obj->getUserById($eventDetails['user_id']);
$creatorName = htmlspecialchars($eventCreator['first_name'] ?? 'N/A') . ' ' . htmlspecialchars($eventCreator['last_name'] ?? '');

?>
<style>
    /* Add any specific styles for event.php if not covered by general styles */
    .event-details-container {
        max-width: 900px;
        margin: var(--spacing-lg) auto;
        padding: var(--spacing-md);
        background: var(--white);
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .event-details-container h1 {
        text-align: center;
        margin-bottom: var(--spacing-lg);
        color: var(--primary-color);
        font-size: 2.5em;
        border-bottom: 2px solid var(--border-color);
        padding-bottom: var(--spacing-md);
    }
    .event-section-card {
        background: var(--background-light);
        padding: var(--spacing-md);
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        margin-bottom: var(--spacing-lg);
        border: 1px solid var(--light-grey-border);
    }
    .event-section-card h3 {
        font-size: 1.6em;
        color: var(--text-dark);
        margin-top: 0;
        margin-bottom: var(--spacing-md);
        border-bottom: 1px dashed var(--border-color);
        padding-bottom: var(--spacing-sm);
    }
    .event-section-card p {
        margin-bottom: var(--spacing-sm);
        color: var(--text-subtle);
    }
    .event-section-card p strong {
        color: var(--text-dark);
    }
    .event-meta-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: var(--spacing-sm);
    }
    .event-meta-grid p {
        margin-bottom: 0;
    }
    .event-services-list ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .event-services-list li {
        background-color: var(--white);
        padding: var(--spacing-sm) var(--spacing-md);
        border-radius: 6px;
        margin-bottom: var(--spacing-xs);
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        font-size: 0.95em;
        color: var(--text-dark);
    }
    .event-services-list li::before {
        content: "•";
        color: var(--primary-color);
        font-weight: bold;
        display: inline-block;
        width: 1em;
        margin-left: -1em;
    }
    .event-actions {
        display: flex;
        justify-content: center;
        gap: var(--spacing-md);
        margin-top: var(--spacing-xl);
        flex-wrap: wrap;
    }
    .event-actions .btn {
        width: auto;
        padding: 12px 25px;
    }
    /* New Status Badges (added for this page's context) */
    .status-badge {
        padding: 4px 10px;
        border-radius: 15px; /* More rounded pill shape */
        font-size: 0.75em;
        font-weight: 600;
        text-transform: uppercase;
        white-space: nowrap;
        vertical-align: middle; /* Align with text */
    }
    .status-booked { /* Represents 'confirmed' booking */
        background: #d4edda; /* Light green */
        color: #155724; /* Dark green */
    }
    .status-pending { /* Represents 'pending_review' or 'pending' booking */
        background: #fff3cd; /* Light yellow */
        color: #856404; /* Dark yellow */
    }
    .status-declined { /* Represents 'cancelled' booking, or a true 'declined' if added */
        background: #f8d7da; /* Light red */
        color: #721c24; /* Dark red */
    }
    /* Existing event statuses for fallback */
    .status-planning { background: #ffeaa7; color: #fdcb6e; }
    .status-active { background: #55efc4; color: #00b894; }
    .status-completed { background: #a29bfe; color: #6c5ce7; }
    .status-cancelled { background: #ff7675; color: #d63031; } /* Event cancelled status */
    .status-reviewed { /* NEW: Style for a 'Reviewed' status */
        background: #2C3E50;
        color: white;
    }
    .status-pending-review-action { /* NEW: Style for the action button flag */
        background: #8e44ad;
        color: #ecf0f1;
    }


    /* New styles for AI Preferences and booking lists */
    .ai-preferences-toggle {
        background: none;
        border: none;
        color: var(--primary-color);
        text-decoration: underline;
        cursor: pointer;
        font-size: 0.9em;
        margin-top: var(--spacing-sm);
        display: block;
        text-align: left;
    }
    .ai-preferences-content {
        display: none;
        background-color: var(--white);
        padding: var(--spacing-sm);
        border-radius: 5px;
        margin-top: var(--spacing-sm);
        font-family: monospace;
        white-space: pre-wrap; /* Preserve whitespace and newlines */
        font-size: 0.85em;
        color: var(--text-dark);
        border: 1px solid var(--border-color);
        box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);
    }
    .ai-preferences-content.active {
        display: block;
    }
    .bookings-list-item {
        background-color: var(--white);
        padding: var(--spacing-sm) var(--spacing-md);
        border-radius: 6px;
        margin-bottom: var(--spacing-xs);
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        display: flex;
        flex-direction: column;
        gap: var(--spacing-xs);
    }
    .bookings-list-item p {
        margin: 0;
        color: var(--text-dark);
        font-size: 0.95em;
    }
    .bookings-list-item p strong {
        color: var(--primary-color);
    }
    .bookings-list-item .booking-actions {
        display: flex;
        justify-content: flex-end;
        gap: var(--spacing-sm);
        margin-top: var(--spacing-sm);
    }
    .bookings-list-item .booking-actions .btn {
        padding: 5px 10px;
        font-size: 0.8em;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .event-meta-grid {
            grid-template-columns: 1fr;
        }
        .event-actions {
            flex-direction: column;
        }
        .event-actions .btn {
            width: 100%;
        }
    }
</style>

<div class="event-details-container">
    <h1><?= htmlspecialchars($eventDetails['title']) ?></h1>

    <div class="event-section-card">
        <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
        <div class="event-meta-grid">
            <p><strong>Type:</strong> <?= htmlspecialchars($eventDetails['type_name'] ?? 'N/A') ?></p>
            <p><strong>Status:</strong> <span class="status-badge status-<?= $statusClass ?>"><?= $displayStatus ?></span></p>
            <p><strong>Created By:</strong> <?= $creatorName ?></p>
            <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 3): // Admin-specific details ?>
                <p><strong>Creator Email:</strong> <?= htmlspecialchars($eventCreator['email'] ?? 'N/A') ?></p>
                <p><strong>Creator Phone:</strong> <?= htmlspecialchars($eventCreator['phone'] ?? 'N/A') ?></p>
            <?php endif; ?>
            <p><strong>Created On:</strong> <?= date('F j, Y', strtotime($eventDetails['created_at'])) ?></p>
        </div>
        <?php if ($eventDetails['description']): ?>
            <p style="margin-top: var(--spacing-md);"><strong>Description:</strong></p>
            <p><?= nl2br(htmlspecialchars($eventDetails['description'])) ?></p>
        <?php endif; ?>

        <?php if (!empty($eventDetails['ai_preferences']) && (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 3)): // Show AI preferences only to admin ?>
            <h4 style="margin-top: var(--spacing-lg); color: var(--text-dark);">AI Planning Details:</h4>
            <p>This event was planned with AI assistance. </p>
            <button type="button" class="ai-preferences-toggle" data-target="#ai-preferences-content">View AI Preferences (JSON)</button>
            <div id="ai-preferences-content" class="ai-preferences-content">
                <?= htmlspecialchars(json_encode(json_decode($eventDetails['ai_preferences'], true), JSON_PRETTY_PRINT)) ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="event-section-card">
        <h3><i class="fas fa-calendar-alt"></i> Dates & Times</h3>
        <div class="event-meta-grid">
            <p><strong>Start Date:</strong> <?= date('F j, Y', strtotime($eventDetails['event_date'])) ?></p>
            <?php if ($eventDetails['event_time']): ?>
                <p><strong>Start Time:</strong> <?= date('g:i A', strtotime($eventDetails['event_time'])) ?></p>
            <?php endif; ?>
            <?php if ($eventDetails['end_date']): ?>
                <p><strong>End Date:</strong> <?= date('F j, Y', strtotime($eventDetails['end_date'])) ?></p>
            <?php endif; ?>
            <?php if ($eventDetails['end_time']): ?>
                <p><strong>End Time:</strong> <?= date('g:i A', strtotime($eventDetails['end_time'])) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="event-section-card">
        <h3><i class="fas fa-map-marker-alt"></i> Location Details</h3>
        <?php if ($eventDetails['location_string']): ?>
            <p><strong>Location String:</strong> <?= htmlspecialchars($eventDetails['location_string']) ?></p>
        <?php endif; ?>
        <?php if ($eventDetails['venue_name']): ?>
            <p><strong>Venue Name:</strong> <?= htmlspecialchars($eventDetails['venue_name']) ?></p>
        <?php endif; ?>
        <?php if ($eventDetails['venue_address'] || $eventDetails['venue_city']): ?>
            <p><strong>Full Address:</strong> 
                <?php 
                    $address_parts = [];
                    if($eventDetails['venue_address']) $address_parts[] = htmlspecialchars($eventDetails['venue_address']);
                    if($eventDetails['venue_city']) $address_parts[] = htmlspecialchars($eventDetails['venue_city']);
                    if($eventDetails['venue_state']) $address_parts[] = htmlspecialchars($eventDetails['venue_state']);
                    if($eventDetails['venue_postal_code']) $address_parts[] = htmlspecialchars($eventDetails['venue_postal_code']);
                    if($eventDetails['venue_country']) $address_parts[] = htmlspecialchars($eventDetails['venue_country']);
                    echo implode(', ', $address_parts);
                ?>
            </p>
        <?php endif; ?>
    </div>

    <div class="event-section-card">
        <h3><i class="fas fa-users"></i> Guests & Budget</h3>
        <div class="event-meta-grid">
            <p><strong>Expected Guests:</strong> <?= htmlspecialchars($eventDetails['guest_count'] ?? 'TBD') ?></p>
            <p><strong>Budget Range:</strong> PKR <?= number_format($eventDetails['budget_min'] ?? 0, 2) ?> - PKR <?= number_format($eventDetails['budget_max'] ?? 0, 2) ?></p>
        </div>
    </div>

    <?php
    // Fetch required services for this event
    $requiredServices = dbFetchAll("
        SELECT vs.service_name, esr.priority, esr.budget_allocated, esr.specific_requirements
        FROM event_service_requirements esr
        JOIN vendor_services vs ON esr.service_id = vs.id
        WHERE esr.event_id = ?
    ", [$eventId]);
    if (!empty($requiredServices)):
    ?>
    <div class="event-section-card event-services-list">
        <h3><i class="fas fa-concierge-bell"></i> Required Services</h3>
        <ul>
            <?php foreach ($requiredServices as $service): ?>
                <li>
                    <strong><?= htmlspecialchars($service['service_name']) ?></strong> (Priority: <?= ucfirst(htmlspecialchars($service['priority'])) ?>)
                    <?php if ($service['budget_allocated']): ?> - Allocated: PKR <?= number_format($service['budget_allocated'], 2) ?><?php endif; ?>
                    <?php if ($service['specific_requirements']): ?> <br> <em>"<?= htmlspecialchars($service['specific_requirements']) ?>"</em><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if ($eventDetails['special_requirements']): ?>
    <div class="event-section-card">
        <h3><i class="fas fa-clipboard-list"></i> Special Requirements</h3>
        <p><?= nl2br(htmlspecialchars($eventDetails['special_requirements'])) ?></p>
    </div>
    <?php endif; ?>

    <?php if ($hasAnyBooking): ?>
    <div class="event-section-card event-bookings-list">
        <h3><i class="fas fa-book-open"></i> Associated Bookings</h3>
        <?php foreach ($bookingsForEvent as $booking):
            $vendorProfile = $vendor_obj->getVendorProfileById($booking['vendor_id']);
            $vendorName = htmlspecialchars($vendorProfile['business_name'] ?? 'N/A');
            $bookingStatusClass = strtolower(htmlspecialchars($booking['status']));
            $bookingDisplayStatus = ucfirst(htmlspecialchars($booking['status']));
            ?>
            <div class="bookings-list-item">
                <p><strong>Booking ID:</strong> <?= htmlspecialchars($booking['id']) ?></p>
                <p><strong>Vendor:</strong> <a href="<?= BASE_URL ?>public/vendor_profile.php?id=<?= htmlspecialchars($booking['vendor_id']) ?>"><?= $vendorName ?></a></p>
                <p><strong>Service Date:</strong> <?= date('F j, Y', strtotime($booking['service_date'])) ?></p>
                <p><strong>Amount:</strong> PKR <?= number_format($booking['final_amount'], 2) ?> (Deposit: PKR <?= number_format($booking['deposit_amount'], 2) ?>)</p>
                <p><strong>Status:</strong> <span class="status-badge status-<?= $bookingStatusClass ?>"><?= $bookingDisplayStatus ?></span></p>
                <p><strong>Booked On:</strong> <?= date('F j, Y g:i A', strtotime($booking['created_at'])) ?></p>
                <div class="booking-actions">
                    <a href="<?= BASE_URL ?>public/booking.php?id=<?= htmlspecialchars($booking['id']) ?>" class="btn btn-sm btn-info">View Booking Details</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="event-actions">
        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 3): // Admin actions ?>
            <a href="<?= BASE_URL ?>public/admin/events.php" class="btn btn-secondary">Back to Events Management</a>
        <?php else: // Customer actions ?>
            <?php if ($needsReview): ?>
                <a href="review.php?booking_id=<?= htmlspecialchars($reviewBookingId) ?>" class="btn btn-warning btn-sm">
                    <i class="fas fa-star"></i> Leave Review
                </a>
            <?php endif; ?>
            <?php if (!$hasAnyBooking): // Only show edit if no bookings exist for this event ?>
                <a href="edit_event.php?id=<?= $eventId ?>" class="btn btn-primary">Edit Event</a>
            <?php endif; ?>
            <a href="ai_chat.php?event_id=<?= $eventId ?>" class="btn btn-secondary">Get AI Recommendations</a>
            <a href="<?= BASE_URL ?>public/chat.php?event_id=<?= $eventId ?>&vendor_id=" class="btn">Start Chat</a>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle AI Preferences JSON display
    const aiPreferencesToggle = document.querySelector('.ai-preferences-toggle');
    if (aiPreferencesToggle) {
        aiPreferencesToggle.addEventListener('click', function() {
            const targetId = this.dataset.target;
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                targetElement.classList.toggle('active');
                if (targetElement.classList.contains('active')) {
                    this.textContent = 'Hide AI Preferences (JSON)';
                } else {
                    this.textContent = 'View AI Preferences (JSON)';
                }
            }
        });
    }
});
</script>

<?php include 'footer.php'; ?>
