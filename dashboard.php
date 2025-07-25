<?php
// TEMPORARY: Enable full error reporting for debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session and include necessary files for database connection and classes
session_start();
require_once '../includes/config.php';
require_once '../classes/User.class.php';
require_once '../classes/Event.class.php';
require_once '../classes/Booking.class.php';
require_once '../classes/Notification.class.php'; // Include Notification class
require_once '../classes/ReportGenerator.class.php'; // Include ReportGenerator for recent activities
require_once '../classes/AI_Assistant.php'; // Include AI_Assistant class

// Redirect to login page if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

// Instantiate necessary classes with the PDO connection
$user = new User($pdo);
$event = new Event($pdo);
$booking = new Booking($pdo);
$notification_obj = new Notification($pdo); // Instantiate Notification class
$report_generator = new ReportGenerator($pdo); // Instantiate ReportGenerator
$ai_assistant = new AI_Assistant($pdo); // Instantiate AI_Assistant

// Fetch current user's data
$user_data = $user->getUserById($_SESSION['user_id']);

// Ensure that only customer type users remain on this dashboard.
// Redirect vendors and admins to their respective dashboards if they somehow land here directly.
if (isset($_SESSION['user_type'])) {
    switch ($_SESSION['user_type']) {
        case 2: // Vendor
            header('Location: ' . BASE_URL . 'public/vendor_dashboard.php');
            exit();
        case 3: // Admin
            header('Location: ' . BASE_URL . 'public/admin/admin_dashboard.php');
            exit();
        // User type 1 (Customer) will fall through and continue
    }
}

// Fetch customer-specific dashboard data
// Get overall event statistics for the user
$event_stats = $event->getUserEventStats($_SESSION['user_id']);
// Get a limited number of upcoming events for display
$upcoming_events = $event->getUpcomingEvents($_SESSION['user_id'], 5);
// Get a limited number of recent bookings for display (assuming getCustomerBookings exists and can be limited)
$recent_bookings = $booking->getUserBookings($_SESSION['user_id']);
// Manually limit if the method doesn't support it directly
$recent_bookings = array_slice($recent_bookings, 0, 5);

// Fetch recent activities for the user
$recent_activities = $report_generator->getUserRecentActivity($_SESSION['user_id'], 5);

// REMOVED: Fetching personalized vendor recommendations for dashboard.php
// This logic is now handled exclusively by index.php for customers.
$personalized_vendors = []; // Ensure it's empty on this page

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - EventCraftAI</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
    /* Specific styles for the customer dashboard layout and elements */
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
    color: #667eea;
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
</style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; // Include the main site header ?>

    <div class="customer-dashboard-container">
        <div class="dashboard-header">
            <div>
                <h1>Welcome, <?= htmlspecialchars($user_data['first_name']) ?>!</h1>
                <p>Your event planning journey starts here.</p>
            </div>
            <div>
                <a href="ai_chat.php" class="btn btn-primary">AI Assistant</a>
            </div>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <div class="customer-stats-grid">
            <div class="stat-card">
                <div class="metric-value"><?= $event_stats['total_events'] ?? 0 ?></div>
                <div class="metric-label">Total Events</div>
            </div>
            <div class="stat-card">
                <div class="metric-value"><?= $event_stats['upcoming_events'] ?? 0 ?></div>
                <div class="metric-label">Upcoming Events</div>
            </div>
            <div class="stat-card">
                <div class="metric-value"><?= $event_stats['planning_events'] ?? 0 ?></div>
                <div class="metric-label">Events in Planning</div>
            </div>
            <div class="stat-card">
                <div class="metric-value">PKR<?= number_format($event_stats['avg_budget'] ?? 0, 2) ?></div>
                <div class="metric-label">Avg. Event Budget</div>
            </div>
        </div>

        <?php /*
        // The "Recommended Vendors for You" section has been moved to index.php
        // and is no longer displayed on the customer dashboard.
        // The previous conditional logic here ensured it was only for customers,
        // but now the section is entirely removed from this file.
        if (($_SESSION['user_type'] ?? null) == 1 && !empty($personalized_vendors)): ?>
            <div class="section-card">
                <h2>Recommended Vendors for You</h2>
                <p class="text-subtle">Based on your recent activity and preferences.</p>
                <div class="personalized-vendors-grid">
                    <?php foreach ($personalized_vendors as $vendor_item): ?>
                        <div class="vendor-card-item">
                            <a href="<?= BASE_URL ?>public/vendor_profile.php?id=<?= htmlspecialchars($vendor_item['id']) ?>" class="vendor-card-link">
                                <div class="vendor-card-image" style="background-image: url('<?= ASSETS_PATH ?>uploads/users/<?= htmlspecialchars($vendor_item['profile_image'] ?: 'default-avatar.jpg') ?>')"></div>
                                <div class="vendor-card-content">
                                    <h3><?= htmlspecialchars($vendor_item['business_name']) ?></h3>
                                    <p class="vendor-city"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($vendor_item['business_city']) ?></p>
                                    <div class="vendor-card-rating">
                                        <?php
                                        $rating = round($vendor_item['rating'] * 2) / 2;
                                        for ($i = 1; $i <= 5; $i++):
                                            if ($rating >= $i) { echo '<i class="fas fa-star"></i>'; }
                                            elseif ($rating > ($i - 1) && $rating < $i) { echo '<i class="fas fa-star-half-alt"></i>'; }
                                            else { echo '<i class="far fa-star"></i>'; }
                                        endfor;
                                        ?>
                                        <span><?= number_format($vendor_item['rating'], 1) ?> (<?= $vendor_item['total_reviews'] ?> Reviews)</span>
                                    </div>
                                    <?php if (!empty($vendor_item['offered_services_names'])): ?>
                                        <p class="vendor-services">Services: <?= htmlspecialchars($vendor_item['offered_services_names']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (empty($personalized_vendors)): ?>
                    <div class="empty-state">No personalized vendor recommendations at this time. View more vendors or use the AI Assistant to get suggestions!</div>
                <?php endif; ?>
            </div>
        <?php endif; */ ?>


        <div class="dashboard-sections">
            <div class="section-card">
                <h2>Recent Activities</h2>
                <?php if (!empty($recent_activities)): ?>
                    <div class="activity-log">
                        <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon"><i class="<?= htmlspecialchars($activity['icon_class']) ?>"></i></div>
                            <div class="activity-content">
                                <div class="activity-message"><?= htmlspecialchars($activity['type_prefix']) . htmlspecialchars($activity['message_detail']) ?></div>
                                <div class="activity-time">
                                    <?= date('M j, Y g:i a', strtotime($activity['created_at'])) ?>
                                    <?php if (!empty($activity['related_url'])): ?>
                                        <a href="<?= BASE_URL . htmlspecialchars($activity['related_url']) ?>" class="btn-link btn-sm" style="margin-left: 10px;">View</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="text-align: right; margin-top: 15px;">
                        <a href="<?= BASE_URL ?>public/notifications.php" class="btn-link">View All Notifications</a>
                    </div>
                <?php else: ?>
                    <div class="empty-state">No recent activities.</div>
                <?php endif; ?>
            </div>

            <div class="section-card">
                <h2>Upcoming Events</h2>
                <?php if (!empty($upcoming_events)): ?>
                    <?php foreach ($upcoming_events as $event_item): ?>
                        <div class="list-item">
                            <div>
                                <div class="list-item-title"><?= htmlspecialchars($event_item['title']) ?></div>
                                <div class="list-item-meta"><?= date('M j, Y', strtotime($event_item['event_date'])) ?> | <?= htmlspecialchars($event_item['type_name']) ?></div>
                            </div>
                            <a href="<?= BASE_URL ?>public/event.php?id=<?= $event_item['id'] ?>" class="btn-link">View</a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">No upcoming events. <a href="ai_chat.php" class="btn-link">Plan one with AI!</a></div>
                <?php endif; ?>
            </div>

            <div class="section-card">
                <h2>Recent Bookings</h2>
                <?php if (!empty($recent_bookings)): ?>
                    <?php foreach ($recent_bookings as $booking_item): ?>
                        <div class="list-item">
                            <div>
                                <div class="list-item-title">Booking for <?= htmlspecialchars($booking_item['event_title'] ?? 'N/A') ?></div>
                                <div class="list-item-meta"><?= htmlspecialchars($booking_item['business_name'] ?? 'N/A') ?> | Date: <?= date('F j, Y', strtotime($booking_item['service_date'])) ?> | Status: <?= ucfirst(htmlspecialchars($booking_item['status'])) ?></div>
                            </div>
                            <a href="<?= BASE_URL ?>public/booking.php?id=<?= $booking_item['id'] ?>" class="btn-link">View</a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">No recent bookings. <a href="events.php" class="btn-link">Find vendors for your events!</a></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; // Include the main site footer ?>
</body>
</html>
