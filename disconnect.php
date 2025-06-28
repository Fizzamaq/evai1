// api/calendar/disconnect.php
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

// Only allow POST requests for state changes
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit();
}

try {
    $calendarManager = new CalendarManager($pdo);
    
    // Call a new method in CalendarManager to delete the token
    if ($calendarManager->deleteToken($_SESSION['user_id'])) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Google Calendar disconnected successfully.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to disconnect Google Calendar.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An error occurred: ' . $e->getMessage()]);
}
?>