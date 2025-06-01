<?php
// public/vendor_dashboard.php
// This file is the main dashboard for vendor users.

// Removed session_start(): handled by config.php
require_once __DIR__ . '/../includes/config.php'; // Use __DIR__ for robust pathing
require_once __DIR__ . '/../classes/Vendor.class.php';

// require_once __DIR__ . '/../classes/User.class.php'; // Removed: User class not directly used here after Vendor verification

$vendor = new Vendor($pdo); // Pass PDO
// $user = new User($pdo);     // Removed: User object not directly used here

// Verify vendor access: This method ensures the user is logged in, is a vendor,
// and sets $_SESSION['vendor_id'] if successful. It redirects otherwise.
$vendor->verifyVendorAccess();

// Re-fetch vendor_data to ensure all details are current after verification
$vendor_data = $vendor->getVendorByUserId($_SESSION['user_id']);
if (!$vendor_data) {
    // This case should ideally be caught by verifyVendorAccess, but as a fallback
    $_SESSION['error_message'] = "Vendor profile not found. Please complete your vendor registration.";
    header('Location: ' . BASE_URL . 'public/login.php'); // Redirect to login or a vendor registration page
    exit();
}

// Get vendor-specific statistics for the dashboard display
$stats = [
    'total_bookings' => $vendor->getBookingCount($vendor_data['id']),
    'upcoming_events' => $vendor->getUpcomingEvents($vendor_data['id']), // Refers to upcoming bookings for the vendor
    'average_rating' => $vendor_data['rating'] ?? 0, // Display vendor's average rating
    'response_rate' => $vendor->getResponseRate($vendor_data['id']) // Display vendor's response rate
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Dashboard - EventCraftAI</title>
    <link rel="stylesheet" href="<?= ASSETS_PATH ?>css/style.css">
    <link rel="stylesheet" href="<?= ASSETS_PATH ?>css/dashboard.css">
    <link rel="stylesheet" href="<?= ASSETS_PATH ?>css/vendor.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'header.php'; // Includes the unified header ?>

    <div class="vendor-dashboard">
        <div class="dashboard-header">
            <div>
                <h1>Welcome, <?= htmlspecialchars($vendor_data['business_name']) ?>!</h1>
                <p>Your vendor management hub.</p>
            </div>
            <div class="rating">
                <i class="fas fa-star"></i> <?= number_format($stats['average_rating'], 1) ?> (<?= $vendor_data['total_reviews'] ?? 0 ?> reviews)
            </div>
        </div>

        <div class="vendor-stats">
            <div class="stat-card">
                <div class="metric-value"><?= $stats['total_bookings'] ?></div>
                <div class="metric-label">Total Bookings</div>
            </div>
            <div class="stat-card">
                <div class="metric-value"><?= $stats['upcoming_events'] ?></div>
                <div class="metric-label">Upcoming Bookings</div>
            </div>
            <div class="stat-card">
                <div class="metric-value"><?= number_format($stats['response_rate'] * 100, 0) ?>%</div>
                <div class="metric-label">Response Rate</div>
            </div>
        </div>

        <!--{{-- Removed: Quick action card for portfolio --}}-->
        <!-- <div class="section-card quick-action-card">-->
        <!--    <h2>Manage Your Public Portfolio</h2>-->
        <!--    <p>Showcase your best work and attract more clients by updating your portfolio.</p>-->
        <!--    <a href="<?= BASE_URL ?>public/vendor_portfolio.php" class="btn btn-primary">-->
        <!--        <i class="fas fa-images"></i> Go to My Portfolio-->
        <!--    </a>-->
        <!--</div> --}}-->

        <div class="dashboard-sections">
            <div class="section-card upcoming-bookings">
                <h2>Upcoming Bookings</h2>
                <?php $upcomingBookings = $vendor->getUpcomingBookings($vendor_data['id']); ?>
                <?php if (empty($upcomingBookings)): ?>
                    <div class="empty-state">No upcoming bookings.</div>
                <?php else: ?>
                    <?php foreach ($upcomingBookings as $booking): ?>
                        <div class="list-item booking-item">
                            <div>
                                <div class="list-item-title"><?= htmlspecialchars($booking['event_title']) ?></div>
                                <div class="list-item-meta">
                                    <i class="fas fa-calendar-alt"></i> <?= date('M j, Y', strtotime($booking['service_date'])) ?>
                                    <span class="status-badge status-<?= strtolower($booking['status']) ?>"><?= ucfirst(htmlspecialchars($booking['status'])) ?></span>
                                </div>
                            </div>
                            <a href="<?= BASE_URL ?>public/booking.php?id=<?= $booking['id'] ?>" class="btn btn-sm btn-primary">View Details</a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="section-card calendar-widget">
                <h2>Availability Calendar</h2>
                <div id="availability-calendar"></div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        // Initialize FullCalendar for availability display on the vendor dashboard
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('availability-calendar');
            if (calendarEl) {
                const calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    // Events are fetched from the dedicated public/availability.php API endpoint
                    events: function(fetchInfo, successCallback, failureCallback) {
                        fetch(`<?= BASE_URL ?>public/availability.php?vendor_id=<?= $_SESSION['vendor_id'] ?>&start=${fetchInfo.startStr}&end=${fetchInfo.endStr}`)
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error('Network response was not ok');
                                }
                                return response.json();
                            })
                            .then(data => {
                                // Format events for FullCalendar display
                                const formattedEvents = data.map(event => ({
                                    id: event.id,
                                    title: event.status.charAt(0).toUpperCase() + event.status.slice(1), // Capitalize first letter
                                    start: event.date + 'T' + event.start_time, // Combine date and time for ISO format
                                    end: event.date + 'T' + event.end_time,     // Combine date and time
                                    allDay: false, // Assuming specific times are always provided
                                    extendedProps: { // Custom property to store original status
                                        status: event.status
                                    }
                                }));
                                successCallback(formattedEvents);
                            })
                            .catch(error => {
                                console.error('Error fetching availability:', error);
                                // Optionally display an error message on the dashboard
                                const calendarContainer = document.getElementById('availability-calendar');
                                if (calendarContainer) {
                                    calendarContainer.innerHTML = '<p class="text-subtle">Failed to load calendar. Please try again.</p>';
                                }
                                failureCallback(error); // Notify FullCalendar of the error
                            });
                    },
                    // Customize how events are rendered in the calendar
                    eventContent: function(arg) {
                        let statusClass = '';
                        if (arg.event.extendedProps.status === 'available') {
                            statusClass = 'fc-event-available';
                        } else if (arg.event.extendedProps.status === 'booked') {
                            statusClass = 'fc-event-booked';
                        } else if (arg.event.extendedProps.status === 'blocked') {
                            statusClass = 'fc-event-blocked';
                        }
                        return { html: `<div class="${statusClass}">${arg.event.title}</div>` };
                    },
                    // Handle clicks on existing events (e.g., to view/edit availability)
                    eventClick: function(info) {
                        // When an event is clicked, redirect to the full availability management page
                        window.location.href = `<?= BASE_URL ?>public/vendor_availability.php`;
                    }
                });
                calendar.render(); // Render the calendar on the page
            }
        });
    </script>
</body>
</html>
