<?php
// api/get_booking_details.php
session_start();
require_once '../includes/config.php';
require_once '../classes/Booking.class.php';
require_once '../classes/User.class.php'; // To fetch client details
require_once '../classes/Vendor.class.php'; // To verify vendor ownership of booking

header('Content-Type: application/json');

$response = ['success' => false, 'error' => 'An unknown error occurred.'];

// TEMPORARY DEBUGGING: Enable full error reporting for this API endpoint
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    // 1. Basic Authentication & Authorization: Ensure a vendor is logged in
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 2) {
        error_log("API Error (get_booking_details.php): Unauthorized access. User ID: " . ($_SESSION['user_id'] ?? 'N/A') . ", Type: " . ($_SESSION['user_type'] ?? 'N/A'));
        throw new Exception("Unauthorized access. Vendor login required.");
    }

    $bookingId = $_GET['booking_id'] ?? null;
    error_log("API Debug (get_booking_details.php): Received booking_id: " . $bookingId);

    if (empty($bookingId) || !is_numeric($bookingId)) {
        throw new Exception("Invalid booking ID provided.");
    }

    $bookingSystem = new Booking($pdo);
    $user_obj = new User($pdo);
    $vendor_obj = new Vendor($pdo);

    // 2. Fetch Booking Details
    $booking_details = $bookingSystem->getBooking((int)$bookingId);
    error_log("API Debug (get_booking_details.php): Fetched booking_details: " . print_r($booking_details, true));

    if (!$booking_details) {
        throw new Exception("Booking not found.");
    }

    // 3. Authorization Check: Ensure this booking belongs to the logged-in vendor
    $vendor_profile = $vendor_obj->getVendorProfileByUserId($_SESSION['user_id']); // This method now exists
    error_log("API Debug (get_booking_details.php): Fetched vendor_profile: " . print_r($vendor_profile, true));

    if (!$vendor_profile || $booking_details['vendor_id'] != $vendor_profile['id']) {
        error_log("API Error (get_booking_details.php): Access denied. Booking vendor ID: " . ($booking_details['vendor_id'] ?? 'N/A') . ", Logged-in vendor profile ID: " . ($vendor_profile['id'] ?? 'N/A'));
        throw new Exception("Access denied. This booking does not belong to your account.");
    }

    // 4. Fetch related data for display
    $client_details = $user_obj->getUserById($booking_details['user_id']);
    error_log("API Debug (get_booking_details.php): Fetched client_details: " . print_r($client_details, true));

    if (!$client_details) {
        throw new Exception("Client details not found for booking user ID: " . $booking_details['user_id']);
    }

    // Fetch service name (assuming service_id in bookings maps to vendor_services.id)
    $stmt_service = $pdo->prepare("SELECT service_name FROM vendor_services WHERE id = ?");
    $stmt_service->execute([$booking_details['service_id']]);
    $service_name = $stmt_service->fetchColumn();
    error_log("API Debug (get_booking_details.php): Fetched service_name: " . $service_name);

    // Fetch event title (assuming event_id in bookings maps to events.id)
    $stmt_event = $pdo->prepare("SELECT title FROM events WHERE id = ?");
    $stmt_event->execute([$booking_details['event_id']]);
    $event_title = $stmt_event->fetchColumn();
    error_log("API Debug (get_booking_details.php): Fetched event_title: " . $event_title);


    $response = [
        'success' => true,
        'booking' => [
            'id' => $booking_details['id'],
            'event_id' => $booking_details['event_id'],
            'event_title' => $event_title ?? 'N/A', // Null coalesce for safety
            'user_id' => $booking_details['user_id'],
            'vendor_id' => $booking_details['vendor_id'],
            'service_id' => $booking_details['service_id'],
            'service_name' => $service_name ?? 'N/A', // Null coalesce for safety
            'service_date' => $booking_details['service_date'],
            'final_amount' => $booking_details['final_amount'],
            'deposit_amount' => $booking_details['deposit_amount'],
            'special_instructions' => $booking_details['special_instructions'],
            'status' => $booking_details['status'],
            'screenshot_proof' => $booking_details['screenshot_proof'],
            'created_at' => $booking_details['created_at'],
            'updated_at' => $booking_details['updated_at']
        ],
        'client' => [
            'id' => $client_details['id'],
            'first_name' => $client_details['first_name'],
            'last_name' => $client_details['last_name'],
            'email' => $client_details['email'] ?? 'N/A' // Ensure email is handled safely
        ]
    ];

} catch (Exception $e) {
    error_log("API Error (get_booking_details.php) - Caught Exception: " . $e->getMessage());
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
exit();