<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../classes/Vendor.class.php';

$vendor = new Vendor($pdo); // Pass PDO
$vendor->verifyVendorAccess(); // Your existing auth check, ensures $_SESSION['vendor_id'] is set

if (!isset($_SESSION['vendor_id'])) {
    // This should ideally be caught by verifyVendorAccess, but as a fallback
    http_response_code(401);
    echo json_encode(['error' => 'Vendor not authenticated or ID not found.']);
    exit();
}

// Handle availability updates (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['date']) || !isset($data['start_time']) || !isset($data['end_time']) || !isset($data['status'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing availability data.']);
        exit();
    }

    try {
        $vendor->updateAvailability(
            $_SESSION['vendor_id'],
            $data['date'],
            $data['start_time'],
            $data['end_time'],
            $data['status']
        );
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Availability updated successfully!']);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

// Load existing availability (GET request)
// Ensure vendor_id is set in session for this GET request as well
if (!isset($_SESSION['vendor_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Vendor not authenticated or ID not found for fetching availability.']);
    exit();
}

$start_date_fetch = $_GET['start'] ?? date('Y-m-01');
$end_date_fetch = $_GET['end'] ?? date('Y-m-t');

$availability = $vendor->getAvailability(
    $_SESSION['vendor_id'],
    $start_date_fetch,
    $end_date_fetch
);

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

header('Content-Type: application/json');
echo json_encode($formattedEvents);