<?php
// api/create_booking.php
session_start();
require_once '../includes/config.php';
require_once '../classes/Booking.class.php';
require_once '../classes/Notification.class.php';
require_once '../classes/Vendor.class.php';
require_once '../classes/MailSender.class.php';
require_once '../classes/User.class.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unexpected error occurred.'];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = "Unauthorized: You must be logged in to create a booking.";
    http_response_code(401);
    echo json_encode($response);
    exit();
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['event_id'], $data['vendor_id'], $data['service_id'])) {
    $response['message'] = "Invalid request: Missing required data.";
    http_response_code(400);
    echo json_encode($response);
    exit();
}

try {
    $booking = new Booking($pdo);
    $notification = new Notification($pdo);
    $vendor_obj = new Vendor($pdo);
    $user_obj = new User($pdo);
    $mailSender = new MailSender();

    $booking_details = [
        'user_id' => $user_id,
        'event_id' => $data['event_id'],
        'vendor_id' => $data['vendor_id'],
        'service_id' => $data['service_id'],
        'special_instructions' => $data['special_instructions'] ?? null,
        'final_amount' => $data['final_amount'] ?? 0.00,
        'deposit_amount' => $data['deposit_amount'] ?? 0.00,
        'screenshot_proof' => $data['screenshot_proof'] ?? null,
        'service_date' => $data['service_date'] ?? date('Y-m-d')
    ];

    $new_booking_id = $booking->createBooking($booking_details);

    if ($new_booking_id) {
        $response['success'] = true;
        $response['message'] = "Booking request submitted successfully!";
        $response['booking_id'] = $new_booking_id;

        // --- NEW: Send notification to vendor ---
        $vendor_user_id = $vendor_obj->getVendorUserIdByVendorId($data['vendor_id']);
        $user_data = $user_obj->getUserById($user_id);
        
        if ($vendor_user_id && $user_data) {
            // Send dashboard notification
            $notification_message = "New booking request from " . htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']) . " for event ID " . $data['event_id'] . ".";
            $notification->createNotification($vendor_user_id, $notification_message, 'new_booking_request', $new_booking_id);

            // Send email notification
            $vendor_data = $user_obj->getUserById($vendor_user_id);
            $client_name = htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']);
            $vendor_name = htmlspecialchars($vendor_data['first_name'] . ' ' . $vendor_data['last_name']);
            $vendor_email = htmlspecialchars($vendor_data['email']);

            $email_subject = "New Booking Request Received on EventCraftAI";
            $email_html_body = "
                <p>Dear {$vendor_name},</p>
                <p>You have received a new booking request from <strong>{$client_name}</strong>.</p>
                <p><strong>Booking ID:</strong> {$new_booking_id}</p>
                <p><strong>Event Date:</strong> " . date('M j, Y', strtotime($booking_details['service_date'])) . "</p>
                <p>Please log in to your dashboard to view the full details and respond to the client: <a href='" . BASE_URL . "public/vendor_dashboard.php'>Go to Dashboard</a></p>
                <p>Thank you,</p>
                <p>The EventCraftAI Team</p>
            ";

            $mailSender->sendEmail($vendor_email, $vendor_name, $email_subject, $email_html_body);
        }
        
    } else {
        $response['message'] = "Failed to create booking.";
        http_response_code(500);
    }

} catch (Exception $e) {
    $response['message'] = "Error: " . $e->getMessage();
    error_log("Booking API Error: " . $e->getMessage());
    http_response_code(500);
}

echo json_encode($response);