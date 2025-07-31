<?php
// api/update_booking_status.php
session_start();
require_once '../includes/config.php';
require_once '../classes/Booking.class.php';
require_once '../classes/Notification.class.php';
require_once '../classes/Vendor.class.php'; // To verify vendor ownership
require_once '../classes/User.class.php';     // To fetch customer's email and name
require_once '../classes/MailSender.class.php'; // To send email notifications

header('Content-Type: application/json');

$response = ['success' => false, 'error' => 'An unknown error occurred.'];

try {
    // 1. Basic Authentication & Authorization: Ensure a vendor is logged in
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 2) { // Assuming 2 is vendor user_type_id
        throw new Exception("Unauthorized access. Vendor login required.");
    }

    $data = json_decode(file_get_contents('php://input'), true);

    $bookingId = $data['booking_id'] ?? null;
    $newStatus = $data['status'] ?? null; // 'confirmed' or 'cancelled'

    if (empty($bookingId) || !is_numeric($bookingId) || empty($newStatus)) {
        throw new Exception("Invalid booking ID or status provided.");
    }

    // Ensure target status is one of the valid states for this action
    if (!in_array($newStatus, ['confirmed', 'cancelled'])) { 
        throw new Exception("Invalid status value provided. Must be 'confirmed' or 'cancelled'.");
    }

    // Instantiate necessary classes
    $bookingSystem = new Booking($pdo);
    $notification_obj = new Notification($pdo);
    $vendor_obj = new Vendor($pdo); 
    $user_obj = new User($pdo);
    $mailSender = new MailSender(); 

    // Start a transaction for atomicity of status update, notification, and email
    $pdo->beginTransaction();

    // 2. Authorization Check: Ensure this booking belongs to the logged-in vendor
    $booking_details = $bookingSystem->getBooking((int)$bookingId);
    if (!$booking_details) {
        throw new Exception("Booking not found.");
    }
    $vendor_profile = $vendor_obj->getVendorByUserId($_SESSION['user_id']); // Get vendor_profile for logged-in user
    if (!$vendor_profile || $booking_details['vendor_id'] != $vendor_profile['id']) {
        throw new Exception("Access denied. This booking does not belong to your account.");
    }

    // 3. Update Booking Status in Database
    $success = $bookingSystem->updateBookingStatus((int)$bookingId, $newStatus);

    if (!$success) {
        throw new Exception("Failed to update booking status in database.");
    }

    // 4. Fetch Client Details for Notification and Email
    $client_user_id = $booking_details['user_id'];
    $client_user_data = $user_obj->getUserById($client_user_id);

    if (!$client_user_data) {
        error_log("API Error (update_booking_status.php): Client user data not found for user_id {$client_user_id} of booking {$bookingId}. Notification/Email will not be sent.");
        // We will continue to commit the booking status update, but log this failure
    } else {
        $client_name = htmlspecialchars($client_user_data['first_name'] . ' ' . $client_user_data['last_name']);
        $client_email = htmlspecialchars($client_user_data['email']);
        $vendor_business_name = htmlspecialchars($vendor_profile['business_name'] ?? 'Your Vendor');
        $event_title = htmlspecialchars($booking_details['event_title'] ?? 'your event'); // Get event title if available

        // 5. Send Dashboard Notification to User
        $notification_message = "Your booking (ID: {$bookingId}) for '{$event_title}' with '{$vendor_business_name}' has been {$newStatus}.";
        $notification_obj->createNotification(
            $client_user_id,
            $notification_message,
            'booking_status_update',
            (int)$bookingId
        );

        // 6. Send Email Notification to User (Modified for better debugging)
        $email_subject = "EventCraftAI Booking Update: Your Booking for {$event_title} is {$newStatus}";
        $email_html_body = "
            <p>Dear {$client_name},</p>
            <p>This is to inform you that your booking (ID: <strong>{$bookingId}</strong>) for <strong>'{$event_title}'</strong> with <strong>'{$vendor_business_name}'</strong> has been updated.</p>
            <p><strong>New Status:</strong> " . ucfirst($newStatus) . "</p>
            <p>For full details, please log in to your dashboard and view your booking: <a href='" . BASE_URL . "public/booking.php?id={$bookingId}'>View Booking</a></p>
            <p>Thank you for using EventCraftAI!</p>
            <p>The EventCraftAI Team</p>
        ";
        $email_text_body = "Dear {$client_name},\nYour booking (ID: {$bookingId}) for '{$event_title}' with '{$vendor_business_name}' has been updated.\nNew Status: " . ucfirst($newStatus) . "\nView details: " . BASE_URL . "public/booking.php?id={$bookingId}\nThank you,\nEventCraftAI Team";

        $email_sent = $mailSender->sendEmail($client_email, $client_name, $email_subject, $email_html_body, $email_text_body);
        
        if (!$email_sent) {
            error_log("API Error (update_booking_status.php): Failed to send email to client {$client_email} for booking ID {$bookingId}.");
            // This is a warning, not a critical failure for the booking update itself.
        } else {
            error_log("API Debug (update_booking_status.php): Successfully sent email to {$client_email} for booking ID {$bookingId}.");
        }
    }

    // Commit the transaction if everything was successful
    $pdo->commit();
    $response['success'] = true;
    $response['message'] = "Booking status updated successfully.";

} catch (Exception $e) {
    // Rollback transaction if any error occurred
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("API Error (update_booking_status.php): " . $e->getMessage() . " on line " . $e->getLine() . " in file " . $e->getFile());
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
exit();
