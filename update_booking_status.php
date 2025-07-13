<?php
// api/update_booking_status.php
session_start();
require_once '../includes/config.php';
require_once '../classes/Booking.class.php';
require_once '../classes/Notification.class.php';
require_once '../classes/Vendor.class.php'; // To verify vendor ownership

header('Content-Type: application/json');

$response = ['success' => false, 'error' => 'An unknown error occurred.'];

try {
    // 1. Basic Authentication & Authorization: Ensure a vendor is logged in
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 2) {
        throw new Exception("Unauthorized access. Vendor login required.");
    }

    $data = json_decode(file_get_contents('php://input'), true);

    $bookingId = $data['booking_id'] ?? null;
    $newStatus = $data['status'] ?? null; // 'confirmed' or 'declined'

    if (empty($bookingId) || !is_numeric($bookingId) || empty($newStatus)) {
        throw new Exception("Invalid booking ID or status provided.");
    }

    if (!in_array($newStatus, ['confirmed', 'declined', 'cancelled'])) { // Add other valid statuses if applicable
        throw new Exception("Invalid status value provided.");
    }

    $bookingSystem = new Booking($pdo);
    $notification = new Notification($pdo);
    $vendor_obj = new Vendor($pdo);

    // 2. Authorization Check: Ensure this booking belongs to the logged-in vendor
    $booking_details = $bookingSystem->getBooking((int)$bookingId);
    if (!$booking_details) {
        throw new Exception("Booking not found.");
    }
    $vendor_profile = $vendor_obj->getVendorProfileByUserId($_SESSION['user_id']);
    if (!$vendor_profile || $booking_details['vendor_id'] != $vendor_profile['id']) {
        throw new Exception("Access denied. This booking does not belong to your account.");
    }

    // 3. Update Booking Status
    $success = $bookingSystem->updateBookingStatus((int)$bookingId, $newStatus);

    if ($success) {
        $response['success'] = true;
        $response['message'] = "Booking status updated to " . $newStatus;

        // Notify the client (user who made the booking)
        $notification->createNotification(
            $booking_details['user_id'], // Client's user_id
            "Your booking (ID: {$bookingId}) has been {$newStatus} by the vendor.",
            'booking_status_update',
            (int)$bookingId
        );
    } else {
        throw new Exception("Failed to update booking status in database.");
    }

} catch (Exception $e) {
    error_log("API Error (update_booking_status.php): " . $e->getMessage());
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
exit();