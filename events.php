<?php
// public/events.php
require_once '../includes/config.php';
require_once '../classes/User.class.php';
require_once '../classes/Event.class.php';
require_once '../classes/Booking.class.php'; // Needed to check for bookings

// TEMPORARY: Enable full error reporting for debugging. REMOVE IN PRODUCTION!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

// Pass PDO to class constructors
$user = new User($pdo);
$event = new Event($pdo);
$booking_obj = new Booking($pdo); // Instantiate Booking class

$user_data = $user->getUserById($_SESSION['user_id']);

// Handle event deletion (still applicable for AI-created events)
if (isset($_POST['delete_event'])) {
    $event_id = $_POST['event_id'];
    // The deleteEvent method will be responsible for checking ownership
    if ($event->deleteEvent($event_id, $_SESSION['user_id'])) {
        $_SESSION['success_message'] = "Event deleted successfully!"; // Use session for messages
    } else {
        $_SESSION['error_message'] = "Failed to delete event. Please check logs for details."; // More informative message
    }
    // Redirect to clear POST data and show message
    header('Location: ' . BASE_URL . 'public/events.php');
    exit();
}

// --- Modified logic to fetch ALL user events (not just AI-created and booked) ---
try {
    // Fetch all events created by the logged-in user, regardless of AI preferences or booking status
    // We can filter/categorize them for display later if needed.
    $stmt = $pdo->prepare("
        SELECT e.*, et.type_name
        FROM events e
        JOIN event_types et ON e.event_type_id = et.id
        WHERE e.user_id = :user_id
          AND e.status != 'deleted' -- Still exclude events explicitly marked as deleted
        ORDER BY e.event_date ASC, e.created_at DESC
    ");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $user_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Now, let's also fetch booking information for these events
    // This allows us to determine if an event is "booked" for display purposes
    foreach ($user_events as &$event_item) { // Use & to modify array elements directly
        // Ensure getBookingsByEventId exists and returns an array
        $bookings_for_event = $booking_obj->getBookingsByEventId($event_item['id']);
        $event_item['is_booked'] = !empty($bookings_for_event); // True if any bookings exist
        $event_item['bookings'] = $bookings_for_event; // Attach booking details if needed

        // --- DEBUGGING: Log event and booking status for each item ---
        error_log("Events Page Debug: Event ID: {$event_item['id']}, Event Title: '{$event_item['title']}', Original Status: '{$event_item['status']}', Is Booked: " . ($event_item['is_booked'] ? 'True' : 'False'));
        if ($event_item['is_booked']) {
            error_log("Events Page Debug: Bookings for Event ID {$event_item['id']}: " . json_encode($event_item['bookings']));
        }
        // --- END DEBUGGING ---
    }
    unset($event_item); // Break the reference with the last element

} catch (PDOException $e) {
    error_log("Error fetching user events: " . $e->getMessage());
    $_SESSION['error_message'] = "Failed to load events. Please try again.";
    $user_events = []; // Ensure $user_events is an empty array on error
}
// --- End of modified logic ---


// Retrieve messages from session if any
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']); // Clear messages after display
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Events - EventCraftAI</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/events.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Add specific styles for the customer dashboard layout and elements */
        .customer-dashboard-container {
            width: 100%; /* Make it take full width initially */
            max-width: 1200px; /* Max width for larger screens */
            margin: 0 auto; /* Center the container */
            padding: 20px; /* Padding on all sides */
            box-sizing: border-box; /* Include padding in width calculation */
        }
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e1e5e9;
            flex-wrap: wrap; /* Allow items to wrap on smaller screens */
        }
        .dashboard-header > div {
            margin-bottom: 10px; /* Add some space when items wrap */
        }
        .customer-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .metric-value {
            font-size: 2em;
            font-weight: 700;
            color: #2d3436;
        }
        .metric-label {
            color: #636e72;
            font-size: 0.9em;
            margin-top: 5px;
        }
        .dashboard-sections {
            display: grid;
            grid-template-columns: 1fr 1fr; /* Two columns for content sections */
            gap: 30px;
        }
        .section-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .section-card h2 {
            margin-top: 0;
            color: #2d3436;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .list-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap; /* Allow list items to wrap */
        }
        .list-item:last-child {
            border-bottom: none; /* Remove border from the last item */
        }
        .list-item-title {
            font-weight: 600;
            color: #2d3436;
            flex-basis: 100%; /* Take full width on wrap */
        }
        .list-item-meta {
            font-size: 0.9em;
            color: #636e72;
            flex-basis: 100%; /* Take full width on wrap */
            margin-top: 5px;
            display: flex; /* Make meta info a flex container */
            align-items: center;
            gap: 8px; /* Gap between icon and text, and status badge */
        }
        .list-item-meta i {
            color: var(--primary-color);
        }
        .list-item .btn-link {
            margin-top: 10px; /* Space out button when wrapped */
        }
        .empty-state {
            text-align: center;
            padding: 20px;
            color: #636e72;
        }
        .btn-link {
            text-decoration: none;
            color: #667eea; /* A nice primary color for links */
            font-weight: 600;
            transition: color 0.2s;
        }
        .btn-link:hover {
            color: #764ba2; /* Darker shade on hover */
        }

        /* Specific styles for Activity list (reusing admin_dashboard.php styles) */
        .activity-log {
            /* Styles for the overall activity log container */
        }
        .activity-item {
            display: flex;
            align-items: flex-start; /* Align icon to the top of message */
            gap: 15px;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .activity-item:last-child {
            border-bottom: none;
        }
        .activity-icon {
            flex-shrink: 0; /* Prevent icon from shrinking */
            font-size: 1.4em; /* Slightly larger icon */
            color: var(--primary-color);
            width: 30px; /* Fixed width for consistent alignment */
            text-align: center;
        }
        .activity-content {
            flex-grow: 1; /* Allow content to take remaining space */
        }
        .activity-message {
            font-size: 0.95em;
            color: var(--text-dark);
            line-height: 1.4;
        }
        .activity-time {
            font-size: 0.8em;
            color: var(--text-subtle);
            margin-top: 5px;
            display: flex; /* For aligning time and view link */
            justify-content: space-between;
            align-items: center;
        }

        /* NEW: Personalized Vendor Recommendations Section */
        /* REMOVED FROM THIS PAGE */


        /* Responsive adjustments for smaller screens */
        @media (max-width: 768px) {
            .dashboard-sections {
                grid-template-columns: 1fr; /* Stack sections vertically on small screens */
            }
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .dashboard-header > div:last-child {
                display: flex;
                flex-direction: column;
                width: 100%;
            }
            .dashboard-header .btn {
                width: 100%;
                margin-bottom: 10px;
            }
            .personalized-vendors-grid { /* Keep these styles, but the section itself is removed */
                grid-template-columns: 1fr; /* Stack vendor cards */
            }
            .vendor-card-image { /* Keep these styles, but the section itself is removed */
                height: 200px; /* Adjust image height for single column */
            }
        }

        @media (max-width: 480px) {
            .customer-dashboard-container {
                padding: 15px; /* Slightly less padding on very small screens */
            }
        }
        /* Further styling for event-actions buttons for better, decent, well-organized look */
        .event-actions {
            display: flex;
            justify-content: flex-start; /* Aligned to the left */
            align-items: center;
            gap: 10px; /* Consistent space between buttons */
            margin-top: 20px; /* More vertical space from description */
            flex-wrap: wrap; /* Allow buttons to wrap on smaller screens */
        }
        .event-actions .btn,
        .event-actions button { /* Target both <a> and <button> elements for consistent styling */
            display: flex; /* Make buttons flex containers to align icon and text */
            align-items: center;
            justify-content: center; /* Center content within button if space allows */
            gap: 8px; /* Slightly more space between icon and text */
            padding: 10px 15px; /* Consistent padding for all buttons */
            font-size: 0.95em; /* Slightly larger, more readable font */
            min-width: 130px; /* Increased min-width for better consistency */
            height: 40px; /* Consistent height for all buttons */
            border-radius: var(--border-radius); /* Inherit from style.css for consistent corners */
            text-decoration: none; /* Ensure links don't have underlines */
            transition: all 0.2s ease-in-out; /* Smooth transitions for hover effects */
            box-sizing: border-box; /* Include padding and border in the element's total width and height */
        }

        /* Specific styles for the Delete button to ensure it looks 'decent' */
        .event-actions .btn-danger {
            background-color: var(--error-color); /* Use primary error color */
            color: var(--white); /* White text for contrast */
            border: 1px solid var(--error-color); /* Solid border matching background */
            box-shadow: var(--shadow-sm); /* Subtle shadow for depth */
        }
        .event-actions .btn-danger:hover {
            background-color: #CC0000; /* Slightly darker red on hover */
            border-color: #CC0000;
            box-shadow: var(--shadow-md); /* More pronounced shadow on hover */
            transform: translateY(-1px); /* Slight lift effect */
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="events-container">
        <div class="events-header">
            <div>
                <h1>My Events</h1>
                <p>Track your event plans and bookings.</p>
            </div>
            <div>
                <a href="ai_chat.php" class="btn btn-primary">Plan New Event with AI</a>
            </div>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (empty($user_events)): ?>
            <div class="empty-state">
                <h3>No Events Yet</h3>
                <p>Your event plans will appear here. Start planning with AI!</p>
                <a href="ai_chat.php" class="btn btn-primary create-event-btn" style="display: inline-block; margin-top: 20px;">Start Planning with AI</a>
            </div>
        <?php else: ?>
            <div class="events-grid">
                <?php foreach ($user_events as $event_item): ?>
                    <div class="event-card">
                        <div class="event-title"><?php echo htmlspecialchars($event_item['title']); ?></div>

                        <div class="event-meta">
                            <span><i class="fas fa-calendar-alt"></i> <?php echo date('M j, Y', strtotime($event_item['event_date'])); ?></span>
                            <span><i class="fas fa-users"></i> <?php echo $event_item['guest_count'] ?: 'TBD'; ?> guests</span>
                            <span><i class="fas fa-dollar-sign"></i> $<?php echo number_format($event_item['budget_min'] ?? 0, 0); ?> - $<?php echo number_format($event_item['budget_max'] ?? 0, 0); ?></span>
                            
                            <?php 
                            // Determine the display status for the card based on bookings or event status
                            $cardDisplayStatus = ucfirst(htmlspecialchars($event_item['status']));
                            $cardStatusClass = strtolower(htmlspecialchars($event_item['status']));

                            if ($event_item['is_booked']) {
                                // Prioritize booking status if any booking exists
                                $hasConfirmedBooking = false;
                                $hasPendingBooking = false;
                                $hasDeclinedBooking = false;

                                foreach ($event_item['bookings'] as $booking) {
                                    if ($booking['status'] === 'confirmed') { $hasConfirmedBooking = true; }
                                    if ($booking['status'] === 'pending_review' || $booking['status'] === 'pending') { $hasPendingBooking = true; }
                                    if ($booking['status'] === 'cancelled') { $hasDeclinedBooking = true; } // Assuming cancelled means declined by vendor
                                }

                                if ($hasConfirmedBooking) {
                                    $cardDisplayStatus = 'Booked';
                                    $cardStatusClass = 'booked';
                                } elseif ($hasPendingBooking) {
                                    $cardDisplayStatus = 'Pending';
                                    $cardStatusClass = 'pending';
                                } elseif ($hasDeclinedBooking) {
                                    $cardDisplayStatus = 'Declined by Vendor';
                                    $cardStatusClass = 'declined';
                                }
                            }
                            ?>
                            <span class="status-badge status-<?= $cardStatusClass ?>"><?= $cardDisplayStatus ?></span>
                        </div>

                        <div class="event-description">
                            <?php echo htmlspecialchars(substr($event_item['description'] ?: 'No description available.', 0, 120)) . (strlen($event_item['description'] ?: '') > 120 ? '...' : ''); ?>
                        </div>

                        <div class="event-actions">
                            <a href="event.php?id=<?php echo $event_item['id']; ?>" class="btn btn-primary btn-sm"><i class="fas fa-eye"></i> View Details</a>
                            <?php if (!$event_item['is_booked']): // Only show edit if no bookings exist for this event ?>
                                <a href="edit_event.php?id=<?php echo $event_item['id']; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i> Edit</a>
                            <?php endif; ?>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this event? This action cannot be undone.');">
                                <input type="hidden" name="event_id" value="<?php echo $event_item['id']; ?>">
                                <button type="submit" name="delete_event" class="btn btn-danger btn-sm"><i class="fas fa-trash-alt"></i> Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        // Add smooth animations (if desired, this is a basic example)
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.event-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'opacity 0.3s ease-out, transform 0.3s ease-out';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 50); // Stagger animation
            });
        });
    </script>
</body>
</html>
