<?php
session_start();
require_once '../includes/config.php';
require_once '../classes/User.class.php';
require_once '../classes/Vendor.class.php';
require_once '../classes/Booking.class.php';
require_once '../classes/Notification.class.php';
require_once '../classes/ReportGenerator.class.php';

// Enable error reporting temporarily for debugging. REMOVE IN PRODUCTION!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 2) { // Ensure only vendors access
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

$user = new User($pdo);
$vendor = new Vendor($pdo);
$booking = new Booking($pdo);
$notification = new Notification($pdo);
$reportGenerator = new ReportGenerator($pdo);

$user_data = $user->getUserById($_SESSION['user_id']);
// CORRECTED: Changed getVendorProfileByUserId to getVendorByUserId
$vendor_profile = $vendor->getVendorByUserId($_SESSION['user_id']);

if (!$vendor_profile) {
    $_SESSION['error_message'] = "Please complete your vendor profile to access the dashboard.";
    header('Location: ' . BASE_URL . 'public/edit_profile.php'); // Redirect to edit_profile.php
    exit();
}

// Fetch dashboard data
$upcoming_bookings = $booking->getVendorUpcomingBookings($vendor_profile['id'], 5); // Assuming this method exists
$booking_stats = $booking->getVendorBookingStats($vendor_profile['id']); // Assuming this method exists
$recent_activities = $reportGenerator->getVendorRecentActivity($vendor_profile['id'], 10); // Limit to 10 recent activities

// Retrieve messages from session
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Dashboard - EventCraftAI</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/vendor.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Add specific styles for the vendor dashboard here */
        /* Reusing some styles from customer dashboard for consistency */
        .vendor-dashboard-container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            box-sizing: border-box;
        }
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e1e5e9;
            flex-wrap: wrap;
        }
        .dashboard-header > div {
            margin-bottom: 10px;
        }
        .vendor-stats-grid {
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
            grid-template-columns: 1fr 1fr;
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
            flex-wrap: wrap;
        }
        .list-item:last-child {
            border-bottom: none;
        }
        .list-item-title {
            font-weight: 600;
            color: #2d3436;
            flex-basis: 100%;
        }
        .list-item-meta {
            font-size: 0.9em;
            color: #636e72;
            flex-basis: 100%;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .list-item-meta i {
            color: var(--primary-color);
        }
        .list-item .btn-link {
            margin-top: 10px;
        }
        .empty-state {
            text-align: center;
            padding: 20px;
            color: #636e72;
        }
        .btn-link {
            text-decoration: none;
            color: #667eea;
            font-weight: 600;
            transition: color 0.2s;
        }
        .btn-link:hover {
            color: #764ba2;
        }

        /* Activity list styles */
        .activity-log {
            /* Styles for the overall activity log container */
        }
        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .activity-item:last-child {
            border-bottom: none;
        }
        .activity-icon {
            flex-shrink: 0;
            font-size: 1.4em;
            color: var(--primary-color);
            width: 30px;
            text-align: center;
        }
        .activity-content {
            flex-grow: 1;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Booking Details Lightbox Styles */
        .booking-details-lightbox-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        .booking-details-lightbox-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .booking-details-lightbox-content {
            background: var(--white);
            padding: var(--spacing-md); /* Overall padding for the content */
            border-radius: 12px;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.6);
            width: 90%;
            max-width: 900px; /* Adjust max-width as needed */
            height: 90%;
            max-height: 700px; /* Adjust max-height as needed */
            display: flex;
            overflow: hidden; /* Ensures content stays within bounds */
            position: relative;
            flex-direction: column; /* Stack header, content, actions */
        }
        .booking-details-lightbox-header {
            padding-bottom: var(--spacing-md);
            border-bottom: 1px solid var(--border-color);
            text-align: center;
            flex-shrink: 0; /* Prevent header from shrinking */
        }
        .booking-details-lightbox-header h3 {
            margin: 0;
            color: var(--primary-color);
            font-size: 1.8em;
        }
        .booking-details-lightbox-body {
            display: flex;
            flex-grow: 1; /* Allows body to take remaining space */
            overflow-y: auto; /* Scroll content if it overflows */
        }
        .booking-details-lightbox-left-col,
        .booking-details-lightbox-right-col {
            padding: var(--spacing-md);
            flex: 1; /* Equal width columns */
            overflow-y: auto; /* Allow scrolling within columns if content is long */
        }
        .booking-details-lightbox-left-col {
            border-right: 1px solid var(--border-color); /* Separator */
        }
        .booking-details-lightbox-right-col {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .booking-details-lightbox-right-col img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        /* Make footer always visible and at bottom */
        .booking-details-lightbox-footer {
            padding-top: var(--spacing-md);
            border-top: 1px solid var(--border-color);
            display: flex; /* Ensure flexbox for buttons */
            justify-content: flex-end; /* Align buttons to the right */
            gap: var(--spacing-md); /* Space between buttons */
            flex-shrink: 0; /* Prevent footer from shrinking */
            /* Add background to ensure it stands out if content scrolls behind it */
            background-color: var(--white);
            z-index: 10; /* Ensure it's above scrolling content */
        }
        .booking-details-lightbox-footer .btn { /* Style for buttons within the footer */
            padding: 10px 15px; /* Adjust padding for better size */
            font-size: 0.9em; /* Adjust font size */
            border-radius: 8px; /* Consistent border radius */
        }
        .booking-details-lightbox-close {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 2.5em;
            color: #fff;
            cursor: pointer;
            z-index: 10001;
            background-color: rgba(0, 0, 0, 0.4);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: background-color 0.2s ease;
        }
        .booking-details-lightbox-close:hover {
            background-color: rgba(255, 0, 0, 0.6);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .dashboard-sections {
                grid-template-columns: 1fr;
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

            .booking-details-lightbox-content {
                flex-direction: column;
                height: 95%;
                max-height: 95%;
            }
            .booking-details-lightbox-body {
                flex-direction: column;
            }
            .booking-details-lightbox-left-col {
                border-right: none;
                border-bottom: 1px solid var(--border-color);
            }
            .booking-details-lightbox-right-col {
                height: 50%; /* Image takes 50% height on mobile */
            }
            .booking-details-lightbox-footer {
                flex-direction: column;
            }
            .booking-details-lightbox-footer .btn {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .vendor-dashboard-container {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="vendor-dashboard-container">
        <div class="dashboard-header">
            <div>
                <h1>Welcome, <?= htmlspecialchars($user_data['first_name']) ?>!</h1>
                <p>Your vendor management hub.</p>
            </div>
            <div>
                <a href="vendor_manage_services.php" class="btn btn-primary">Manage Services</a>
                <a href="vendor_portfolio.php" class="btn btn-secondary">Manage Portfolio</a>
            </div>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
            <div class="alert error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <div class="vendor-stats-grid">
            <div class="stat-card">
                <div class="metric-value"><?= $booking_stats['total_bookings'] ?? 0 ?></div>
                <div class="metric-label">Total Bookings</div>
            </div>
            <div class="stat-card">
                <div class="metric-value"><?= $booking_stats['pending_bookings'] ?? 0 ?></div>
                <div class="metric-label">Pending Bookings</div>
            </div>
            <div class="stat-card">
                <div class="metric-value"><?= $booking_stats['confirmed_bookings'] ?? 0 ?></div>
                <div class="metric-label">Confirmed Bookings</div>
            </div>
            <div class="stat-card">
                <div class="metric-value">PKR<?= number_format($booking_stats['total_revenue'] ?? 0, 2) ?></div>
                <div class="metric-label">Total Revenue</div>
            </div>
        </div>

        <div class="dashboard-sections">
            <div class="section-card section-card-half">
                <h2>Upcoming Bookings</h2>
                <?php $upcomingBookings = $booking->getVendorUpcomingBookings($vendor_profile['id'], 5); ?>
                <?php if (empty($upcomingBookings)): ?>
                    <div class="empty-state">No upcoming bookings.</div>
                <?php else: ?>
                    <?php foreach ($upcomingBookings as $booking_item): ?>
                        <div class="list-item booking-item">
                            <div>
                                <div class="list-item-title"><?= htmlspecialchars($booking_item['event_title'] ?? 'N/A') ?></div>
                                <div class="list-item-meta">
                                    Client: <?= htmlspecialchars($booking_item['client_name'] ?? 'N/A') ?> |
                                    Date: <?= date('M j, Y', strtotime($booking_item['service_date'])) ?> |
                                    Status: <span class="status-badge status-<?= strtolower(htmlspecialchars($booking_item['status'])) ?>"><?= htmlspecialchars($booking_item['status']) ?></span>
                                </div>
                            </div>
                            <a href="#" class="btn-link view-booking-details" data-booking-id="<?= htmlspecialchars($booking_item['id']) ?>">View</a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="section-card section-card-half">
                <h2>Recent Bookings</h2>
                <?php $recent_vendor_bookings = $booking->getVendorRecentBookings($vendor_profile['id'], 5); ?>
                <?php if (empty($recent_vendor_bookings)): ?>
                    <div class="empty-state">No recent bookings.</div>
                <?php else: ?>
                    <?php foreach ($recent_vendor_bookings as $booking_item): ?>
                        <div class="list-item booking-item">
                            <div>
                                <div class="list-item-title">For <?= htmlspecialchars($booking_item['event_title'] ?? 'N/A') ?> by <?= htmlspecialchars($booking_item['client_name'] ?? 'N/A') ?></div>
                                <div class="list-item-meta">
                                    <i class="fas fa-calendar-alt"></i> Booked: <?= date('M j, Y', strtotime($booking_item['created_at'])) ?>
                                    <span class="status-badge status-<?= strtolower(htmlspecialchars($booking_item['status'])) ?>"><?= ucfirst(htmlspecialchars($booking_item['status'])) ?></span>
                                </div>
                            </div>
                            <a href="#" class="btn-link view-booking-details" data-booking-id="<?= htmlspecialchars($booking_item['id']) ?>">View</a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="section-card section-card-full">
                <h2>Recent Activities</h2>
                <?php if (!empty($recent_activities)): ?>
                    <div class="activity-log">
                        <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon"><i class="<?= htmlspecialchars($activity['icon_class'] ?? 'fas fa-info-circle') ?>"></i></div> <div class="activity-content">
                                <div class="activity-message"><?= htmlspecialchars($activity['type_prefix'] ?? '') . htmlspecialchars($activity['message_detail'] ?? 'No detail.') ?></div> <div class="activity-time">
                                    <?= date('M j, Y g:i a', strtotime($activity['created_at'] ?? 'now')) ?>
                                    <?php if (isset($activity['related_url']) && !empty($activity['related_url'])): ?>
                                        <?php if (isset($activity['type']) && $activity['type'] === 'booking' && !empty($activity['related_id'])): ?> <a href="#" class="btn-link btn-sm view-booking-details" data-booking-id="<?= htmlspecialchars($activity['related_id']) ?>" style="margin-left: 10px;">View</a>
                                        <?php else: ?>
                                            <a href="<?= BASE_URL . htmlspecialchars($activity['related_url']) ?>" class="btn-link btn-sm" style="margin-left: 10px;">View</a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="text-align: right; margin-top: 15px;">
                        <?php /* You could create a dedicated 'vendor_notifications.php' or 'vendor_activity_log.php' page for full history */ ?>
                        <a href="<?= BASE_URL ?>public/notifications.php" class="btn-link">View All Notifications</a>
                    </div>
                <?php else: ?>
                    <div class="empty-state">No recent activities.</div>
                <?php endif; ?>
            </div>

            <div class="section-card section-card-full calendar-widget">
                <h2>Availability Calendar
                    <a href="<?= BASE_URL ?>public/vendor_availability.php" class="btn btn-sm btn-info" style="float:right; margin-left: 10px;">Manage Availability</a>
                </h2>
                <div id="availability-calendar"></div>
            </div>
        </div>

        <div class="dashboard-actions" style="margin-top: var(--spacing-lg); text-align: center;">
            <a href="vendor_manage_services.php" class="btn btn-primary btn-large">Manage My Services & Packages</a>
        </div>
    </div>

    <div class="booking-details-lightbox-overlay" id="bookingDetailsLightbox">
        <div class="booking-details-lightbox-content">
            <span class="booking-details-lightbox-close" id="bookingDetailsLightboxClose">&times;</span>
            <div class="booking-details-lightbox-header">
                <h3 id="lightboxBookingTitle">Booking Details</h3>
            </div>
            <div class="booking-details-lightbox-body">
                <div class="booking-details-lightbox-left-col">
                    <p><strong>Booking ID:</strong> <span id="lightboxBookingId"></span></p>
                    <p><strong>Event:</strong> <span id="lightboxEventTitle"></span></p>
                    <p><strong>Client Name:</strong> <span id="lightboxClientName"></span></p>
                    <p><strong>Client Email:</strong> <span id="lightboxClientEmail"></span></p>
                    <p><strong>Service Date:</strong> <span id="lightboxServiceDate"></span></p>
                    <p><strong>Service:</strong> <span id="lightboxServiceName"></span></p>
                    <p><strong>Final Amount:</strong> PKR <span id="lightboxFinalAmount"></span></p>
                    <p><strong>Deposit Amount:</strong> PKR <span id="lightboxDepositAmount"></span></p>
                    <p><strong>Instructions:</strong> <span id="lightboxInstructions"></span></p>
                    <p><strong>Status:</strong> <span id="lightboxStatus" class="status-badge"></span></p>
                    <p><strong>Booked On:</strong> <span id="lightboxCreatedAt"></span></p>
                </div>
                <div class="booking-details-lightbox-right-col">
                    <h4>Payment Proof:</h4>
                    <img id="lightboxScreenshotProof" src="" alt="Payment Screenshot" style="max-width: 100%; height: auto; display: block; margin-top: 10px;">
                    <p id="noScreenshotMessage" style="display: none; color: var(--text-subtle);">No screenshot provided.</p>
                </div>
            </div>
            <div class="booking-details-lightbox-footer">
                <button id="proceedBookingBtn" class="btn btn-primary"><i class="fas fa-handshake"></i> Proceed</button>
                <a href="#" id="messageClientBtn" class="btn btn-secondary"><i class="fas fa-comment"></i> Message Client</a>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const bookingDetailsLightbox = document.getElementById('bookingDetailsLightbox');
            const bookingDetailsLightboxClose = document.getElementById('bookingDetailsLightboxClose');
            const lightboxBookingTitle = document.getElementById('lightboxBookingTitle');
            const lightboxBookingId = document.getElementById('lightboxBookingId');
            const lightboxEventTitle = document.getElementById('lightboxEventTitle');
            const lightboxClientName = document.getElementById('lightboxClientName');
            const lightboxClientEmail = document.getElementById('lightboxClientEmail');
            const lightboxServiceDate = document.getElementById('lightboxServiceDate');
            const lightboxServiceName = document.getElementById('lightboxServiceName');
            const lightboxFinalAmount = document.getElementById('lightboxFinalAmount');
            const lightboxDepositAmount = document.getElementById('lightboxDepositAmount');
            const lightboxInstructions = document.getElementById('lightboxInstructions');
            const lightboxStatus = document.getElementById('lightboxStatus');
            const lightboxCreatedAt = document.getElementById('lightboxCreatedAt');
            const lightboxScreenshotProof = document.getElementById('lightboxScreenshotProof');
            const noScreenshotMessage = document.getElementById('noScreenshotMessage');
            const proceedBookingBtn = document.getElementById('proceedBookingBtn');
            const messageClientBtn = document.getElementById('messageClientBtn');

            // --- Function to open and populate lightbox ---
            async function openBookingDetailsLightbox(bookingId) {
                try {
                    const response = await fetch(`<?= BASE_URL ?>api/get_booking_details.php?booking_id=${bookingId}`);
                    if (!response.ok) {
                        const errorText = await response.text();
                        throw new Error(`Failed to fetch booking details: ${response.status} - ${errorText}`);
                    }
                    const data = await response.json();

                    if (data.success && data.booking) {
                        const booking = data.booking;
                        const client = data.client;

                        console.log("Booking Status received by JS:", booking.status);

                        lightboxBookingTitle.textContent = `Booking ID: ${booking.id}`;
                        lightboxBookingId.textContent = booking.id;
                        lightboxEventTitle.textContent = booking.event_title || 'N/A';
                        lightboxClientName.textContent = client.first_name + ' ' + client.last_name + (client.phone ? ` (${client.phone})` : '');
                        lightboxClientEmail.textContent = client.email;
                        lightboxServiceDate.textContent = new Date(booking.service_date + 'T00:00:00').toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
                        lightboxServiceName.textContent = booking.service_name || 'N/A';
                        lightboxFinalAmount.textContent = parseFloat(booking.final_amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        lightboxDepositAmount.textContent = parseFloat(booking.deposit_amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        lightboxInstructions.textContent = booking.special_instructions || 'None provided.';
                        
                        lightboxStatus.textContent = ucfirst(booking.status);
                        lightboxStatus.className = `status-badge status-${booking.status.toLowerCase().replace(/_/g, '-')}`;

                        lightboxCreatedAt.textContent = new Date(booking.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });

                        if (booking.screenshot_proof) {
                            lightboxScreenshotProof.src = `<?= BASE_URL ?>assets/uploads/bookings/${booking.screenshot_proof}`;
                            lightboxScreenshotProof.style.display = 'block';
                            noScreenshotMessage.style.display = 'none';
                        } else {
                            lightboxScreenshotProof.src = '';
                            lightboxScreenshotProof.style.display = 'none';
                            noScreenshotMessage.style.display = 'block';
                        }

                        // Set the message client button's href based on client ID
                        messageClientBtn.href = `<?= BASE_URL ?>public/vendor_chat.php?user_id=${client.id}`;
                        
                        // Show/hide action buttons
                        if (booking.status === 'pending') {
                            proceedBookingBtn.style.display = 'inline-block';
                        } else {
                            proceedBookingBtn.style.display = 'none';
                        }
                        messageClientBtn.style.display = (client.id && client.id !== 0) ? 'inline-block' : 'none';

                        // Set the data attribute for the proceed button
                        proceedBookingBtn.dataset.bookingId = booking.id;

                        bookingDetailsLightbox.classList.add('active');
                        document.body.style.overflow = 'hidden';

                    } else {
                        alert(data.error || 'Failed to load booking details.');
                        console.error('API response error:', data.error);
                    }
                } catch (error) {
                    console.error('Error opening booking details lightbox:', error);
                    alert('Could not load booking details. Please try again.');
                }
            }

            function ucfirst(string) {
                if (typeof string !== 'string' || string.length === 0) {
                    return '';
                }
                return string.charAt(0).toUpperCase() + string.slice(1);
            }

            // --- Event Listeners for "View" buttons ---
            document.querySelectorAll('.view-booking-details').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const bookingId = this.dataset.bookingId;
                    if (bookingId) {
                        openBookingDetailsLightbox(bookingId);
                    } else {
                        alert('Booking ID not found for this item.');
                    }
                });
            });

            // --- Close Lightbox ---
            bookingDetailsLightboxClose.addEventListener('click', () => {
                bookingDetailsLightbox.classList.remove('active');
                document.body.style.overflow = '';
            });

            bookingDetailsLightbox.addEventListener('click', function(e) {
                if (e.target === bookingDetailsLightbox) {
                    bookingDetailsLightbox.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && bookingDetailsLightbox.classList.contains('active')) {
                    bookingDetailsLightbox.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
            
            // --- Proceed Button Click Handler (AJAX) ---
            proceedBookingBtn.addEventListener('click', async function() {
                const bookingId = this.dataset.bookingId;
                if (!bookingId) {
                    alert('Booking ID not found for proceed action.');
                    return;
                }

                if (!confirm(`Are you sure you want to confirm booking ID: ${bookingId}?`)) {
                    return;
                }

                try {
                    console.log('Sending AJAX request to confirm booking...');
                    const response = await fetch('<?= BASE_URL ?>api/update_booking_status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ booking_id: bookingId, status: 'confirmed' })
                    });

                    if (!response.ok) {
                        const errorText = await response.text();
                        throw new Error(`Server responded with status: ${response.status}. Error: ${errorText}`);
                    }

                    const data = await response.json();
                    console.log('Server response:', data);

                    if (data.success) {
                        alert('Booking confirmed successfully!');
                        window.location.href = `<?= BASE_URL ?>public/booking_confirmed_message.php?booking_id=${bookingId}`;
                    } else {
                        alert('Failed to confirm booking: ' + (data.error || 'Unknown error.'));
                        console.error('Booking confirmation failed:', data.error);
                    }
                } catch (error) {
                    console.error('Error confirming booking:', error);
                    alert('An error occurred while trying to confirm the booking: ' + error.message);
                }
            });

            // Initialize FullCalendar for availability display on the vendor dashboard
            const calendarEl = document.getElementById('availability-calendar');
            if (calendarEl) {
                const calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    validRange: {
                        start: '<?= date('Y-m-d') ?>'
                    },
                    events: function(fetchInfo, successCallback, failureCallback) {
                        fetch(`<?= BASE_URL ?>public/vendor_availability.php?vendor_id=<?= $_SESSION['vendor_id'] ?? ($vendor_profile['id'] ?? 0) ?>&start=${fetchInfo.startStr}&end=${fetchInfo.endStr}`)
                            .then(response => {
                                if (!response.ok) {
                                    return response.text().then(text => {
                                        console.error('Error fetching availability: Server response not OK.', response.status, text);
                                        throw new Error('Server returned unexpected HTML/error for availability data. Check server logs.');
                                    });
                                }
                                return response.json();
                            })
                            .then(data => {
                                const formattedEvents = data.map(event => ({
                                    id: event.id,
                                    title: (event.status ? event.status.charAt(0).toUpperCase() + event.status.slice(1) : 'N/A'),
                                    start: event.date + 'T' + event.start_time,
                                    end: event.date + 'T' + event.end_time,
                                    allDay: false,
                                    extendedProps: {
                                        status: event.status
                                    }
                                }));
                                successCallback(formattedEvents);
                            })
                            .catch(error => {
                                console.error('Error fetching availability:', error);
                                const calendarContainer = document.getElementById('availability-calendar');
                                if (calendarContainer) {
                                    calendarContainer.innerHTML = '<p class="text-subtle">Failed to load calendar. Please try again.</p>';
                                }
                                failureCallback(error);
                            });
                    },
                    eventContent: function(arg) {
                        let statusClass = '';
                        if (arg.event.extendedProps.status === 'available') {
                            statusClass = 'fc-event-available';
                        } else if (arg.event.extendedProps.status === 'booked') {
                            statusClass = 'fc-event-booked';
                        } else if (arg.event.extendedProps.status === 'blocked') {
                            statusClass = 'fc-event-blocked';
                        } else if (arg.event.extendedProps.status === 'holiday') {
                            statusClass = 'fc-event-holiday';
                        }
                        return { html: `<div class="${statusClass}">${arg.event.title}</div>` };
                    },
                    eventClick: function(info) {
                        window.location.href = `<?= BASE_URL ?>public/vendor_availability.php`;
                    }
                });
                calendar.render();
            }
        });
    </script>
</body>
</html>
