<?php
// public/admin/events.php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../classes/User.class.php';
require_once __DIR__ . '/../../classes/Event.class.php';
require_once __DIR__ . '/../../classes/Booking.class.php'; // Required for booking status logic

$user_obj = new User($pdo);
$event_obj = new Event($pdo);
$booking_obj = new Booking($pdo); // Instantiate Booking class

// Admin authentication
if (!$user_obj->isAdmin($_SESSION['user_id'] ?? null)) {
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

// Handle actions (delete/change status) - Status change logic is kept in backend but removed from UI dropdown
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $event_id_target = $_POST['event_id'] ?? null;
    $new_status = $_POST['new_status'] ?? null; // This variable will no longer come from UI dropdown

    if ($event_id_target && is_numeric($event_id_target)) {
        try {
            // Keeping updateEventStatus as a backend capability, even if UI dropdown is removed.
            // This might be used by other admin features or internal logic later.
            if ($action === 'update_status' && $new_status) {
                $event_obj->updateEventStatus($event_id_target, $new_status);
                $_SESSION['success_message'] = "Event status updated successfully.";
            } elseif ($action === 'delete') {
                $event_obj->deleteEventSoft($event_id_target);
                $_SESSION['success_message'] = "Event deleted successfully.";
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Action failed: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = "Invalid event ID.";
    }
    header('Location: ' . BASE_URL . 'public/admin/events.php');
    exit();
}

// Fetch all events (including AI-generated ones)
$all_events = $event_obj->getAllEvents();

// Process events to determine display status based on bookings
foreach ($all_events as &$event_item) {
    $bookings_for_event = $booking_obj->getBookingsByEventId($event_item['id']);
    
    // Default to event's own status
    $event_item['display_status'] = ucfirst(htmlspecialchars($event_item['status']));
    $event_item['status_class'] = strtolower(htmlspecialchars($event_item['status']));

    if (!empty($bookings_for_event)) {
        $hasConfirmed = false;
        $hasPendingReview = false;
        $hasDeclined = false;

        foreach ($bookings_for_event as $booking) {
            if ($booking['status'] === 'confirmed') {
                $hasConfirmed = true;
                break; 
            }
            if ($booking['status'] === 'pending_review' || $booking['status'] === 'pending') {
                $hasPendingReview = true;
            }
            if ($booking['status'] === 'cancelled') { 
                $hasDeclined = true;
            }
        }

        if ($hasConfirmed) {
            $event_item['display_status'] = 'Booked';
            $event_item['status_class'] = 'booked';
        } elseif ($hasPendingReview) {
            $event_item['display_status'] = 'Pending';
            $event_item['status_class'] = 'pending';
        } elseif ($hasDeclined) {
            $event_item['display_status'] = 'Declined by Vendor';
            $event_item['status_class'] = 'declined';
        }
    }
}
unset($event_item); // Break the reference

include '../../includes/admin_header.php';
?>

<div class="admin-container">
    <h1>Manage Events</h1>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert success"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert error"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Event Date</th>
                    <th>Date of Booking</th> <?php /* New Column */ ?>
                    <th>Guests</th>
                    <th>Budget (PKR)</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($all_events)): ?>
                    <?php foreach ($all_events as $event): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($event['id']); ?></td>
                            <td><?php echo htmlspecialchars($event['title']); ?></td>
                            <td><?php echo htmlspecialchars($event['type_name']); ?></td>
                            <td><?php echo htmlspecialchars($event['event_date']); ?></td>
                            <td><?php echo !empty($event['date_of_booking']) ? date('M j, Y', strtotime($event['date_of_booking'])) : 'N/A'; ?></td> <?php /* Display Date of Booking */ ?>
                            <td><?php echo htmlspecialchars($event['guest_count'] ?: 'N/A'); ?></td>
                            <td><?php echo number_format($event['budget_min'] ?? 0, 0); ?> - <?php echo number_format($event['budget_max'] ?? 0, 0); ?></td>
                            <td><span class="status-badge status-<?= $event['status_class'] ?>"><?= $event['display_status'] ?></span></td>
                            <td style="white-space: nowrap;"> <?php /* Added inline style to prevent button wrap */ ?>
                                <a href="<?= BASE_URL ?>public/event.php?id=<?php echo htmlspecialchars($event['id']); ?>" class="btn btn-sm btn-info">View</a>
                                <form method="POST" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete this event? This will mark it as deleted.');">
                                    <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($event['id']); ?>">
                                    <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9">No events found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/admin_footer.php'; ?>
