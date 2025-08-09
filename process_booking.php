<?php
session_start();
require_once '../includes/config.php';
require_once '../classes/Vendor.class.php';
require_once '../classes/Booking.class.php';
require_once '../classes/Chat.class.php';
require_once '../classes/Notification.class.php';
require_once '../classes/UploadHandler.class.php';
require_once '../classes/Event.class.php';

// TEMPORARY DEBUGGING: Enable full error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start transaction to ensure atomicity
$pdo->beginTransaction();

$newBookingId = null; 
$notification = null; 

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("User not logged in.");
    }

    $bookingSystem = new Booking($pdo);
    $chat = new Chat($pdo);
    $notification = new Notification($pdo);
    $uploadHandler = new UploadHandler($pdo);
    $event_obj = new Event($pdo);
    $vendor_obj = new Vendor($pdo);

    $vendor_id = $_POST['vendor_id'] ?? null;
    $selected_service_offerings_ids = $_POST['selected_services'] ?? [];
    $service_date = $_POST['service_date'] ?? null;
    $service_time = $_POST['service_time'] ?? null; 
    $special_instructions = $_POST['instructions'] ?? '';

    $new_event_title = $_POST['new_event_title'] ?? null;
    $new_event_type_id = $_POST['new_event_type_id'] ?? null;
    $new_event_guest_count = $_POST['new_event_guest_count'] ?? null;
    $final_amount = filter_var($_POST['final_booking_amount'] ?? 0, FILTER_VALIDATE_FLOAT);
    $deposit_amount = filter_var($_POST['deposit_booking_amount'] ?? 0, FILTER_VALIDATE_FLOAT);

    error_log("PROCESS_BOOKING: Received POST data: " . print_r($_POST, true));
    error_log("PROCESS_BOOKING: Received FILES data: " . print_r($_FILES, true));

    if (empty($vendor_id) || !is_numeric($vendor_id)) {
        throw new Exception("Missing or invalid Vendor ID.");
    }
    if (!is_array($selected_service_offerings_ids) || count($selected_service_offerings_ids) === 0) {
        throw new Exception("No services selected for booking.");
    }
    if (empty($service_date)) {
        throw new Exception("Missing desired service date.");
    }
    if (empty($new_event_title)) {
        throw new Exception("Missing new event title.");
    }
    if (empty($new_event_type_id) || !is_numeric($new_event_type_id)) {
        throw new Exception("Missing or invalid new event type.");
    }
    if ($final_amount === false || $final_amount < 0) { 
        throw new Exception("Invalid final booking amount. Must be a number greater than or equal to 0.");
    }
    if ($deposit_amount === false || $deposit_amount < 0) {
        throw new Exception("Invalid deposit amount. Must be a number greater than or equal to 0.");
    }
    if ($deposit_amount > $final_amount) {
        throw new Exception("Deposit amount cannot be greater than final amount.");
    }
    if (empty($service_time)) {
        throw new Exception("Missing service time.");
    }

    $actual_service_id = $vendor_obj->getServiceIdByOfferingId((int)$selected_service_offerings_ids[0]);
    if (!$actual_service_id) {
        throw new Exception("Invalid selected service offering. Could not find corresponding service ID.");
    }
    error_log("PROCESS_BOOKING: Converted service_offering_id " . $selected_service_offerings_ids[0] . " to actual service_id: " . $actual_service_id);

    $event_data_for_creation = [
        'user_id' => $_SESSION['user_id'],
        'title' => $new_event_title,
        'description' => 'Event created via direct booking form for vendor services.',
        'event_date' => $service_date,
        'event_time' => $service_time,
        'event_type_id' => (int)$new_event_type_id,
        'guest_count' => !empty($new_event_guest_count) ? (int)$new_event_guest_count : null,
        'budget_min' => (float)$final_amount,
        'budget_max' => (float)$final_amount,
        'status' => 'active',
        'ai_preferences' => null,
        'location_string' => null,
        'venue_name' => null
    ];

    $event_id = $event_obj->createEvent($event_data_for_creation);

    if (!$event_id) {
        throw new Exception("Failed to create new event record for booking. Check Event.class.php logs for details.");
    }
    error_log("PROCESS_BOOKING: New event created with ID: " . $event_id);

    $screenshot_filename = null;
    if (isset($_FILES['picture_upload']) && $_FILES['picture_upload']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../assets/uploads/bookings/';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0775, true)) {
                throw new Exception("Upload directory not found or not writable: " . $upload_dir);
            }
        }
        $uploaded_filename = (new UploadHandler($pdo))->uploadFile($_FILES['picture_upload'], $upload_dir);
        if (!$uploaded_filename) {
            throw new Exception("Failed to upload screenshot: " . ($_SESSION['upload_error'] ?? 'Unknown upload error.'));
        }
        error_log("PROCESS_BOOKING: Screenshot uploaded: " . $uploaded_filename);
        $screenshot_filename = $uploaded_filename;
    } else if (isset($_FILES['picture_upload']) && $_FILES['picture_upload']['error'] !== UPLOAD_ERR_NO_FILE) {
        throw new Exception("Screenshot upload error (Code: " . $_FILES['picture_upload']['error'] . ").");
    } else {
        throw new Exception("Payment screenshot is required. Please upload one.");
    }

    $selected_service_names = [];
    foreach ($selected_service_offerings_ids as $offering_id) {
        $offering_details = $vendor_obj->getServiceOfferingById((int)$offering_id, (int)$vendor_id); 
        if ($offering_details) {
            $selected_service_names[] = $offering_details['service_name'];
        }
    }
    $special_instructions_for_db = $special_instructions;
    if (!empty($selected_service_names)) {
        $special_instructions_for_db .= "\n\nSelected Services: " . implode(', ', $selected_service_names);
    }
    if ($screenshot_filename) {
        $special_instructions_for_db .= "\n\nUploaded Picture URL: assets/uploads/bookings/" . $screenshot_filename;
    }

    $booking_data = [
        'user_id' => $_SESSION['user_id'],
        'event_id' => (int)$event_id,
        'vendor_id' => (int)$vendor_id,
        'service_id' => (int)$actual_service_id,
        'service_date' => $service_date,
        'service_time' => $service_time,
        'final_amount' => (float)$final_amount,
        'deposit_amount' => (float)$deposit_amount,
        'special_instructions' => $special_instructions_for_db,
        'status' => 'pending',
        'screenshot_filename' => $screenshot_filename
    ];

    error_log("PROCESS_BOOKING: Data sent to createBooking: " . print_r($booking_data, true));

    $newBookingId = $bookingSystem->createBooking($booking_data, $_SESSION['user_id']);

    if (!$newBookingId) {
        throw new Exception("Failed to create booking in database. BookingSystem->createBooking returned false. Check PHP error log for PDO details.");
    }
    error_log("PROCESS_BOOKING: Booking created with ID: " . $newBookingId);

    $vendor_profile_details = $vendor_obj->getVendorProfileById((int)$vendor_id);
    $vendor_user_id_for_chat = $vendor_profile_details['user_id'] ?? null;

    if (!$vendor_user_id_for_chat) {
        error_log("PROCESS_BOOKING: Vendor user ID not found for vendor_profile.id {$vendor_id}. Cannot start chat.");
        $_SESSION['warning_message'] = "Booking submitted, but chat could not be initiated automatically. Please contact the vendor directly.";
    } else {
        $conversation_id = $chat->startConversation((int)$event_id, $_SESSION['user_id'], (int)$vendor_user_id_for_chat);
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
    }

    $pdo->commit();
    error_log("PROCESS_BOOKING: Database transaction committed.");

    $_SESSION['success_message'] = "Booking submitted for review! Your booking (ID: {$newBookingId}) is awaiting vendor confirmation.";
    if ($notification) {
        $notification->createNotification(
            $_SESSION['user_id'],
            "Your booking (ID: {$newBookingId}) has been submitted for review. Please await vendor confirmation.",
            'booking',
            $newBookingId
        );
        if ($vendor_user_id_for_chat) {
            $notification->createNotification(
                (int)$vendor_user_id_for_chat,
                "New booking (ID: {$newBookingId}) received! Please review and confirm.",
                'booking',
                $newBookingId
            );
        }
    }
    
    header('Location: ' . BASE_URL . 'public/booking_confirmation.php?booking_id=' . $newBookingId);
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        error_log("PROCESS_BOOKING: Transaction rolled back. Error: " . $e->getMessage());
    }
    $_SESSION['error_message'] = $e->getMessage();
    error_log("PROCESS_BOOKING: Booking processing error for user " . ($_SESSION['user_id'] ?? 'N/A') . ": " . $e->getMessage());
    if ($notification) {
        if (isset($_SESSION['user_id'])) {
            $notification->createNotification(
                $_SESSION['user_id'],
                "Booking Failed: " . $e->getMessage(),
                'booking',
                $newBookingId ?? null
            );
        }
    }
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? BASE_URL . 'public/'));
    exit();
}
