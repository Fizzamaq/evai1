<?php
session_start();
require_once '../includes/config.php';
require_once '../classes/Vendor.class.php'; // Might not be directly needed here, but kept for safety
require_once '../classes/Booking.class.php';
require_once '../classes/Chat.class.php'; // Needed to potentially start a chat
require_once '../classes/Notification.class.php'; // Include Notification class
require_once '../classes/UploadHandler.class.php'; // Include UploadHandler for screenshot
require_once '../classes/Event.class.php'; // Include Event class for creating new event

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
    $chat = new Chat($pdo);
    $notification = new Notification($pdo);
    $uploadHandler = new UploadHandler($pdo);
    $event_obj = new Event($pdo); // Instantiate Event object

    $vendor_id = $_POST['vendor_id'] ?? null;
    $service_ids = $_POST['selected_services'] ?? []; // Array of selected service IDs
    $service_date = $_POST['service_date'] ?? null;
    $special_instructions = $_POST['instructions'] ?? '';

    // NEW EVENT DETAILS from book_vendor.php
    $new_event_title = $_POST['new_event_title'] ?? null;
    $new_event_type_id = $_POST['new_event_type_id'] ?? null;
    $new_event_guest_count = $_POST['new_event_guest_count'] ?? null;
    $new_event_budget_min = $_POST['new_event_budget_min'] ?? null;
    $new_event_budget_max = $_POST['new_event_budget_max'] ?? null;

    // --- DEBUGGING INPUTS ---
    error_log("PROCESS_BOOKING: Received POST data: " . print_r($_POST, true));
    error_log("PROCESS_BOOKING: Received FILES data: " . print_r($_FILES, true));
    // --- END DEBUGGING INPUTS ---

    // Basic validation for mandatory fields
    if (empty($vendor_id) || !is_numeric($vendor_id)) {
        throw new Exception("Missing or invalid Vendor ID.");
    }
    if (!is_array($service_ids) || count($service_ids) === 0) {
        throw new Exception("No services selected for booking.");
    }
    if (empty($service_date)) { // Date is mandatory
        throw new Exception("Missing desired service date.");
    }
    if (empty($new_event_title)) {
        throw new Exception("Missing new event title.");
    }
    if (empty($new_event_type_id) || !is_numeric($new_event_type_id)) {
        throw new Exception("Missing or invalid new event type.");
    }

    // --- Create New Event Record FIRST ---
    $event_data_for_creation = [
        'user_id' => $_SESSION['user_id'],
        'title' => $new_event_title,
        'description' => 'Event created via direct booking form.', // Default description
        'event_date' => $service_date, // Use selected service date as event date
        'event_type_id' => (int)$new_event_type_id,
        'guest_count' => (int)$new_event_guest_count,
        'budget_min' => (float)$new_event_budget_min,
        'budget_max' => (float)$new_event_budget_max,
        'status' => 'active', // Set initial status for newly created event
        'ai_preferences' => null, // Not AI-generated if created this way
        'location_string' => null, // Not capturing location here
        'venue_name' => null // Not capturing venue here
    ];

    $event_id = $event_obj->createEvent($event_data_for_creation); // Use the createEvent method from Event.class.php

    if (!$event_id) {
        throw new Exception("Failed to create new event record for booking. Check Event.class.php logs.");
    }
    error_log("PROCESS_BOOKING: New event created with ID: " . $event_id);


    // --- Handle Screenshot Upload ---
    $screenshot_filename = null;
    if (isset($_FILES['picture_upload']) && $_FILES['picture_upload']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../assets/uploads/bookings/'; // Define your upload directory
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0775, true); // Create directory if it doesn't exist, 0775 permissions
        }
        $screenshot_filename = $uploadHandler->uploadFile($_FILES['picture_upload'], $upload_dir);
        if (!$screenshot_filename) {
            // uploadFile sets $_SESSION['upload_error'] on failure
            throw new Exception("Failed to upload screenshot: " . ($_SESSION['upload_error'] ?? 'Unknown upload error.'));
        }
        error_log("PROCESS_BOOKING: Screenshot uploaded: " . $screenshot_filename);
    } else if (isset($_FILES['picture_upload']) && $_FILES['picture_upload']['error'] !== UPLOAD_ERR_NO_FILE) {
        // If a file was attempted but failed for reasons other than "no file"
        throw new Exception("Screenshot upload error (Code: " . $_FILES['picture_upload']['error'] . ").");
    } else {
        // If no file was uploaded AND it's required
        throw new Exception("Payment screenshot is required. Please upload one.");
    }

    // DUMMY VALUES FOR AMOUNTS (as they are not coming from the form)
    // You MUST implement logic to calculate these based on selected services/packages.
    // Setting to 0.00 for now, assuming DB allows 0 or is nullable.
    $final_amount = 0.00; 
    $deposit_amount = 0.00;

    // Create booking data array
    $booking_data = [
        'user_id' => $_SESSION['user_id'], // Ensure user_id is passed
        'event_id' => (int)$event_id, // Use the NEWLY CREATED event_id
        'vendor_id' => (int)$vendor_id, // Cast to int
        'service_id' => (int)$service_ids[0], // Using first selected service, cast to int
        'service_date' => $service_date, // Should be 'YYYY-MM-DD' format
        'final_amount' => (float)$final_amount, // Cast to float
        'deposit_amount' => (float)$deposit_amount, // Cast to float
        'special_instructions' => $special_instructions,
        'status' => 'pending_review', // Initial status for screenshot payment
        'screenshot_filename' => $screenshot_filename // Filename from upload
    ];

    // --- DEBUGGING booking_data before createBooking ---
    error_log("PROCESS_BOOKING: Data sent to createBooking: " . print_r($booking_data, true));
    // --- END DEBUGGING ---

    $newBookingId = $bookingSystem->createBooking($booking_data, $_SESSION['user_id']);

    if (!$newBookingId) {
        throw new Exception("Failed to create booking in database. BookingSystem->createBooking returned false. Check PHP error log for PDO details.");
    }
    error_log("PROCESS_BOOKING: Booking created with ID: " . $newBookingId);

    // Create chat conversation for this booking
    $conversation_id = $chat->startConversation((int)$event_id, $_SESSION['user_id'], (int)$vendor_id);
    if ($conversation_id) {
        $bookingSystem->updateBookingChatConversationId($newBookingId, $conversation_id);
        error_log("PROCESS_BOOKING: Chat conversation started with ID: " . $conversation_id);
    } else {
        error_log("PROCESS_BOOKING: Failed to create chat conversation for booking ID: $newBookingId");
        if ($notification) {
            $notification->createNotification(
                $_SESSION['user_id'],
                "Warning: Could not start chat for your booking (ID: {$newBookingId}). Please contact support if you need to chat with the vendor.",
                'booking',
                $newBookingId
            );
        }
    }

    $pdo->commit(); // Commit transaction if all successful so far
    error_log("PROCESS_BOOKING: Database transaction committed.");

    $_SESSION['success_message'] = "Booking submitted for review! Your booking (ID: {$newBookingId}) is awaiting vendor confirmation.";
    if ($notification) {
        $notification->createNotification(
            $_SESSION['user_id'],
            "Your booking (ID: {$newBookingId}) has been submitted for review. Please await vendor confirmation.",
            'booking',
            $newBookingId
        );
        // Also notify the vendor (assuming vendor_id is their user_id)
        $notification->createNotification(
            (int)$vendor_id, // Vendor's user_id
            "New booking (ID: {$newBookingId}) received! Please review and confirm.",
            'booking',
            $newBookingId
        );
    }
    
    // Redirect to a booking confirmation/status page
    header('Location: ' . BASE_URL . 'public/booking_confirmation.php?booking_id=' . $newBookingId);
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack(); // Rollback if an error occurred after transaction started
        error_log("PROCESS_BOOKING: Transaction rolled back. Error: " . $e->getMessage());
    }
    $_SESSION['error_message'] = $e->getMessage();
    error_log("PROCESS_BOOKING: Booking processing error for user " . ($_SESSION['user_id'] ?? 'N/A') . ": " . $e->getMessage());
    if ($notification) {
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
