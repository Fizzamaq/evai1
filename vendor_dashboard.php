<?php
session_start();
require_once '../../includes/config.php';
require_once '../../classes/Vendor.class.php';
require_once '../../classes/User.class.php'; // Include User class

$vendor = new Vendor($pdo); // Pass PDO
$user = new User($pdo); // Pass PDO

// Verify vendor access (this also sets $_SESSION['vendor_id'])
$vendor->verifyVendorAccess();

$vendor_data = $vendor->getVendorByUserId($_SESSION['user_id']); // Re-fetch to ensure all data is current
if (!$vendor_data) {
    // This case should ideally be caught by verifyVendorAccess, but as a fallback:
    header('Location: /login.php');
    exit();
}

// Get vendor statistics
$stats = [
    'total_bookings' => $vendor->getBookingCount($vendor_data['id']),
    'upcoming_events' => $vendor->getUpcomingEvents($vendor_data['id']),
    'average_rating' => $vendor_data['rating'] ?? 0, // Handle null rating
    'response_rate' => $vendor->getResponseRate($vendor_data['id'])
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Dashboard - EventCraftAI</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css"> <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
    <style>
        .vendor-dashboard {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .vendor-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .metric-value {
            font-size: 2em;
            font-weight: 700;
            color: #2d3436;
        }
        
        .metric-label {
            color: #636e72;
            font-size: 0.9em;
        }
        
        .dashboard-sections {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        .upcoming-bookings {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .booking-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .calendar-widget {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <?php include '../includes/vendor_header.php'; ?>

    <div class="vendor-dashboard">
        <div class="vendor-header">
            <h1>Welcome, <?= htmlspecialchars($vendor_data['business_name']) ?></h1>
            <div class="rating">
                ★ <?= number_format($stats['average_rating'], 1) ?> (<?= $vendor_data['total_reviews'] ?? 0 ?> reviews)
            </div>
        </div>

        <div class="vendor-stats">
            <div class="stat-card">
                <div class="metric-value"><?= $stats['total_bookings'] ?></div>
                <div class="metric-label">Total Bookings</div>
            </div>
            <div class="stat-card">
                <div class="metric-value"><?= $stats['upcoming_events'] ?></div>
                <div class="metric-label">Upcoming Events</div>
            </div>
            <div class="stat-card">
                <div class="metric-value"><?= number_format($stats['response_rate'] * 100, 0) ?>%</div>
                <div class="metric-label">Response Rate</div>
            </div>
        </div>

        <div class="dashboard-sections">
            <div class="upcoming-bookings">
                <h2>Upcoming Bookings</h2>
                <?php $upcomingBookings = $vendor->getUpcomingBookings($vendor_data['id']); ?>
                <?php if (empty($upcomingBookings)): ?>
                    <p>No upcoming bookings.</p>
                <?php else: ?>
                    <?php foreach ($upcomingBookings as $booking): ?>
                        <div class="booking-item">
                            <div>
                                <h3><?= htmlspecialchars($booking['event_title']) ?></h3>
                                <div class="booking-date">
                                    <?= date('M j, Y', strtotime($booking['service_date'])) ?>
                                </div>
                            </div>
                            <a href="booking.php?id=<?= $booking['id'] ?>" class="btn">View Details</a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="calendar-widget">
                <h2>Availability Calendar</h2>
                <div id="availability-calendar"></div>
            </div>
        </div>
    </div>

    <script>
        // Initialize calendar
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('availability-calendar');
            if (calendarEl) {
                const calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    events: '/api/vendor/availability?vendor_id=<?= $vendor_data['id'] ?>', // API endpoint for events
                    eventContent: function(arg) {
                        // Customize event display
                        let statusClass = '';
                        if (arg.event.extendedProps.status === 'available') {
                            statusClass = 'fc-event-available';
                        } else if (arg.event.extendedProps.status === 'booked') {
                            statusClass = 'fc-event-booked';
                        } else if (arg.event.extendedProps.status === 'blocked') {
                            statusClass = 'fc-event-blocked';
                        }
                        return { html: `<div class="<span class="math-inline">\{statusClass\}"\></span>{arg.event.title}</div>` };
                    },
                    eventClick: function(info) {
                        // Handle date click for availability management
                        // Example: alert('Event: ' + info.event.title + ' on ' + info.event.startStr);
                        // You might want to redirect to a detailed availability management page
                        // or open a modal here.
                    }
                });
                calendar.render();
            }
        });
    </script>
</body>
</html>