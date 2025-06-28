// api/calendar/events.php
<?php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../classes/CalendarManager.class.php';

header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not authenticated.']);
    exit();
}

// Only allow GET requests for fetching data
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit();
}

try {
    $calendarManager = new CalendarManager($pdo);
    
    // Check if user has a token
    if (!$calendarManager->hasToken($_SESSION['user_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Google Calendar not connected for this user.']);
        exit();
    }

    // Fetch events from Google Calendar
    // You might want to add parameters here for date ranges, max results, etc.
    // For simplicity, let's fetch events for the next few months
    $timeMin = date('c'); // Current time in RFC3339 format
    $timeMax = date('c', strtotime('+6 months')); // Events for the next 6 months

    $events = $calendarManager->listCalendarEvents($_SESSION['user_id'], $timeMin, $timeMax);

    if ($events !== false) {
        http_response_code(200);
        echo json_encode($events);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to retrieve events from Google Calendar.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An error occurred: ' . $e->getMessage()]);
}
?>