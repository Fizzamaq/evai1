<?php
session_start();
require_once '../includes/config.php';
require_once '../classes/Vendor.class.php'; // Might not be directly needed here, but kept for safety
require_once '../classes/Booking.class.php';
// REMOVED: require_once '../classes/PaymentProcessor.class.php'; // Not needed for screenshot payment
require_once '../classes/Chat.class.php'; // Needed to potentially start a chat
require_once '../classes/Notification.class.php'; // Include Notification class
require_once '../classes/UploadHandler.class.php'; // Include UploadHandler for screenshot

// TEMPORARY DEBUGGING: Enable full error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start transaction to ensure atomicity
$pdo->beginTransaction();

$newBookingId = null; // Initialize to null
$notification = null; // Initialize notification object to null outside try block

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("User not logged in.");
    }

    // Instantiate classes here, after basic session check
    $bookingSystem = new Booking($pdo);
    // REMOVED: $paymentProcessor = new PaymentProcessor($pdo); // Not needed for screenshot payment
    $chat = new Chat($pdo);
    $notification = new Notification($pdo);
    $uploadHandler = new UploadHandler($pdo); // Instantiate UploadHandler

    $event_id = $_POST['event_id'] ?? null;
    $vendor_id = $_POST['vendor_id'] ?? null;
    $service_ids = $_POST['selected_services'] ?? []; // Array of selected service IDs
    $service_date = $_POST['service_date'] ?? null;
    // REMOVED: $final_amount = $_POST['final_amount'] ?? null; // Not sent from form, and not used for payment processing
    // REMOVED: $deposit_amount = $_POST['deposit_amount'] ?? null; // Not sent from form, and not used for payment processing
    $special_instructions = $_POST['instructions'] ?? '';

    // --- DEBUGGING INPUTS ---
    error_log("PROCESS_BOOKING: Received POST data: " . print_r($_POST, true));
    error_log("PROCESS_BOOKING: Received FILES data: " . print_r($_FILES, true));
    // --- END DEBUGGING INPUTS ---

    // For screenshot payment, final_amount and deposit_amount are not processed here.
    // They might be stored in the booking data, but not used for payment gateway interaction.
    // Ensure your booking form captures these if they are needed for the DB.
    // For now, removing dummy values as they are not used for payment processing.

    // Basic validation
    if (empty($event_id) || empty($vendor_id) || empty($service_ids) || empty($service_date)) {
        throw new Exception("Missing required booking information (Event ID, Vendor ID, Services, Date).");
    }
    if (!is_array($service_ids) || count($service_ids) === 0) {
        throw new Exception("No services selected for booking.");
    }

    // --- Handle Screenshot Upload ---
    $screenshot_filename = null;
    if (isset($_FILES['picture_upload']) && $_FILES['picture_upload']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../assets/uploads/bookings/'; // Define your upload directory
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true); // Create directory if it doesn't exist
        }
        $screenshot_filename = $uploadHandler->uploadFile($_FILES['picture_upload'], $upload_dir);
        if (!$screenshot_filename) {
            throw new Exception("Failed to upload screenshot: " . ($_SESSION['upload_error'] ?? 'Unknown error'));
        }
        error_log("PROCESS_BOOKING: Screenshot uploaded: " . $screenshot_filename);
    } else {
        // Screenshot is mandatory, so if not uploaded or error, throw exception
        throw new Exception("Payment screenshot is required. Please upload one.");
    }

    // Create booking with initial status 'pending_review' or 'awaiting_payment_confirmation'
    // Note: service_id in bookings table seems to be singular, but selected_services is an array.
    // You might need to create multiple booking entries or store service_ids as JSON/comma-separated.
    // For now, I'll use the first service_id, but this needs review based on your DB design.
    $primary_service_id = $service_ids[0]; // Assuming one booking per primary service, or you need to loop
    
    // You need to ensure final_amount and deposit_amount are passed if your DB requires them.
    // Assuming they are either calculated later or not strictly required at this initial booking stage for screenshot payment.
    // For now, setting them to 0 or null if they are not coming from the form.
    $final_amount = $_POST['final_amount'] ?? 0; // Ensure this is captured from the form if needed
    $deposit_amount = $_POST['deposit_amount'] ?? 0; // Ensure this is captured from the form if needed

    $booking_data = [
        'event_id' => $event_id,
        'vendor_id' => $vendor_id,
        'service_id' => $primary_service_id, // Using first selected service
        'service_date' => $service_date,
        'final_amount' => $final_amount, // Will be 0 if not sent from form
        'deposit_amount' => $deposit_amount, // Will be 0 if not sent from form
        'special_instructions' => $special_instructions,
        'status' => 'pending_review', // Set initial status for screenshot payment
        'screenshot_filename' => $screenshot_filename // Pass screenshot filename
    ];

    $newBookingId = $bookingSystem->createBooking($booking_data, $_SESSION['user_id']);

    if (!$newBookingId) {
        throw new Exception("Failed to create booking in database. BookingSystem->createBooking returned false.");
    }
    error_log("PROCESS_BOOKING: Booking created with ID: " . $newBookingId);

    // Create chat conversation for this booking
    $conversation_id = $chat->startConversation($event_id, $_SESSION['user_id'], $vendor_id);
    if ($conversation_id) {
        $bookingSystem->updateBookingChatConversationId($newBookingId, $conversation_id);
        error_log("PROCESS_BOOKING: Chat conversation started with ID: " . $conversation_id);
    } else {
        error_log("PROCESS_BOOKING: Failed to create chat conversation for booking ID: $newBookingId");
        if ($notification) { // Check if notification object is available before using
            $notification->createNotification(
                $_SESSION['user_id'],
                "Warning: Could not start chat for your booking (ID: {$newBookingId}). Please contact support if you need to chat with the vendor.",
                'booking',
                $newBookingId
            );
        }
    }

    // REMOVED ALL STRIPE PAYMENT INTENT CREATION/UPDATE LOGIC
    // Since payment is via screenshot, no payment intent is created here.

    $pdo->commit(); // Commit transaction if all successful so far

    $_SESSION['success_message'] = "Booking submitted for review! Your booking (ID: {$newBookingId}) is awaiting vendor confirmation.";
    if ($notification) { // Check if notification object is available before using
        $notification->createNotification(
            $_SESSION['user_id'],
            "Your booking (ID: {$newBookingId}) has been submitted for review. Please await vendor confirmation.",
            'booking',
            $newBookingId
        );
        // Also notify the vendor
        $notification->createNotification(
            $vendor_id, // Vendor's user_id
            "New booking (ID: {$newBookingId}) received! Please review and confirm.",
            'booking',
            $newBookingId
        );
    }
    
    // Redirect to a booking confirmation/status page, NOT Stripe confirmation
    header('Location: ' . BASE_URL . 'public/booking_confirmation.php?booking_id=' . $newBookingId);
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack(); // Rollback if an error occurred after transaction started
        error_log("PROCESS_BOOKING: Transaction rolled back. Error: " . $e->getMessage());
    }
    $_SESSION['error_message'] = $e->getMessage();
    error_log("PROCESS_BOOKING: Booking processing error for user " . ($_SESSION['user_id'] ?? 'N/A') . ": " . $e->getMessage());
    if ($notification) { // Check if notification object is available before using
        $notification->createNotification(
            $_SESSION['user_id'],
            "Booking Failed: " . $e->getMessage(),
            'booking',
            $newBookingId ?? null // Pass booking ID if available, else null
        );
    }
    // Redirect back to the previous page or a specific error page
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? BASE_URL . 'public/')); // Fallback to homepage
    exit();
}
