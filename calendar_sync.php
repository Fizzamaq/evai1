<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../classes/CalendarManager.class.php';
include 'header.php'; // Assuming header includes common setup

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

$calendarManager = new CalendarManager($pdo);
$hasToken = $calendarManager->hasToken($_SESSION['user_id']); // Check if user has a token
$calendarAuthUrl = $calendarManager->getAuthUrl(); // Get the Google Auth URL

// Handle OAuth2 callback for Google Calendar
if (isset($_GET['code']) && isset($_GET['scope']) && str_contains($_GET['scope'], 'calendar')) {
    if ($calendarManager->handleCallback($_GET['code'])) {
        $_SESSION['success_message'] = "Google Calendar connected successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to connect Google Calendar. Please try again.";
    }
    // Redirect to clean URL to prevent re-processing callback code
    header('Location: ' . BASE_URL . 'public/calendar_sync.php');
    exit();
}
?>

<div class="calendar-sync-container">
    <h1>Calendar Integration</h1>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-error"><?= htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
    <?php endif; ?>

    <?php if ($hasToken): ?>
        <div class="sync-status connected">
            ✅ Connected to Google Calendar
            <button id="disconnect-calendar" class="btn btn-danger">Disconnect</button>
        </div>
        <div id="calendar-events" style="margin-top: 20px;">
            <p>Loading upcoming events...</p>
        </div>
    <?php else: ?>
        <a href="<?= htmlspecialchars($calendarAuthUrl) ?>" class="google-connect-btn btn btn-primary">
            <img src="<?= ASSETS_PATH ?>images/google-icon.png" alt="Google" style="height: 20px; vertical-align: middle; margin-right: 10px;">
            Connect Google Calendar
        </a>
        <p style="margin-top: 15px; color: #636e72;">
            Connect your Google Calendar to sync your event bookings and manage your availability.
        </p>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>

<script>
document.getElementById('disconnect-calendar')?.addEventListener('click', async () => {
    if (confirm('Are you sure you want to disconnect Google Calendar?')) {
        try {
            // This would be an AJAX call to a new endpoint to clear the token from DB
            const response = await fetch('<?= BASE_URL ?>api/calendar/disconnect.php', { method: 'POST' }); // Use POST for state-changing actions
            const data = await response.json();
            if (response.ok && data.success) {
                alert('Google Calendar disconnected.');
                location.reload();
            } else {
                alert('Failed to disconnect calendar: ' + (data.error || 'Unknown error.'));
            }
        } catch (error) {
            console.error('Error disconnecting calendar:', error);
            alert('An error occurred while disconnecting the calendar.');
        }
    }
});

// Load calendar events
if (<?= $hasToken ? 'true' : 'false' ?>) {
    loadCalendarEvents();

    async function loadCalendarEvents() {
        try {
            // This would be an AJAX call to a new endpoint to fetch events from Google Calendar
            const response = await fetch('<?= BASE_URL ?>api/calendar/events.php');
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const events = await response.json();
            const calendarEventsDiv = document.getElementById('calendar-events');
            if (calendarEventsDiv) {
                if (events.length > 0) {
                    calendarEventsDiv.innerHTML = '<h3>Upcoming Calendar Events:</h3><ul>' +
                        events.map(event => `<li><strong>${event.summary}</strong>: ${new Date(event.start.dateTime).toLocaleString()} - ${new Date(event.end.dateTime).toLocaleString()}</li>`).join('') +
                        '</ul>';
                } else {
                    calendarEventsDiv.innerHTML = '<p>No upcoming events found on Google Calendar.</p>';
                }
            }
        } catch (error) {
            console.error('Error loading calendar events:', error);
            const calendarEventsDiv = document.getElementById('calendar-events');
            if (calendarEventsDiv) {
                calendarEventsDiv.innerHTML = '<p class="error-message">Failed to load calendar events. Please ensure permissions are granted.</p>';
            }
        }
    }
}
</script>
