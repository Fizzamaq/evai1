<?php
// api/availability.php
// TEMPORARY: Enable full error reporting for debugging. REMOVE IN PRODUCTION!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start(); // Ensure session is started for BASE_URL and other configs
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../classes/Vendor.class.php';

header('Content-Type: application/json'); // Always send JSON header

$vendor = new Vendor($pdo); // Pass PDO

// Handle availability updates (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // It's better to read input from php://input for AJAX POST requests
    $data = json_decode(file_get_contents('php://input'), true);

    // Ensure required data is present
    if (!isset($data['date']) || !isset($data['start_time']) || !isset($data['end_time']) || !isset($data['status'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing availability data.']);
        exit();
    }

    // IMPORTANT: For POST requests (managing availability), the user MUST be a logged-in vendor.
    // This check should remain for security.
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] != 2) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authentication required to update availability.']);
        exit();
    }
    // Also ensure the vendor_id from session matches the one being updated (if passed, though not currently)
    // Or, if using $_SESSION['vendor_id'], ensure it's set.
    $vendor_id_from_session = $_SESSION['vendor_id'] ?? null;
    if (!$vendor_id_from_session) {
        // This means the vendor_profile might not be complete, or session is stale.
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Vendor profile not identified. Please complete your profile.']);
        exit();
    }


    try {
        $vendor->updateAvailability(
            $vendor_id_from_session, // Use session's vendor ID for security
            $data['date'],
            $data['start_time'],
            $data['end_time'],
            $data['status']
        );
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Availability updated successfully!']);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Error updating availability: " . $e->getMessage()]);
    }
    exit(); // Important to exit after AJAX response
}

// Handle fetching existing availability (GET request)
// This part is for *viewing* availability and should be accessible if a valid vendor_id is provided.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $vendor_id_from_url = $_GET['vendor_id'] ?? null;
    $start_date_fetch = $_GET['start'] ?? date('Y-m-01');
    $end_date_fetch = $_GET['end'] ?? date('Y-m-t');

    // The issue is likely here: filter_var might be returning false or 0 for a valid ID string.
    // Let's explicitly check and cast.
    if (empty($vendor_id_from_url) || !is_numeric($vendor_id_from_url)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid vendor ID provided for fetching availability.']);
        exit();
    }

    try {
        $availability = $vendor->getAvailability(
            (int)$vendor_id_from_url, // Explicitly cast to int here
            $start_date_fetch,
            $end_date_fetch
        );

        if ($availability === false) { // Check for false explicitly as getAvailability might return empty array
            throw new Exception("Failed to retrieve availability data from the database.");
        }

        // Format for FullCalendar. `id`, `title`, `start`, `end`, `allDay`, `extendedProps`
        $formattedEvents = [];
        foreach ($availability as $event) {
            $formattedEvents[] = [
                'id' => $event['id'],
                'title' => ucfirst($event['status']), // e.g., "Available", "Booked"
                'start' => $event['date'] . 'T' . $event['start_time'], // Full datetime string
                'end' => $event['date'] . 'T' . $event['end_time'],     // Full datetime string
                'allDay' => false,
                'extendedProps' => [
                    'status' => $event['status'] // Custom property for styling
                ]
            ];
        }

        echo json_encode($formattedEvents);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'An error occurred while fetching availability: ' . $e->getMessage()]);
    }
    exit(); // Important to exit after AJAX response
}

// If neither POST nor GET with valid parameters, return method not allowed
http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
exit();
?>
