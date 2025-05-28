<?php
session_start();
require_once '../includes/config.php';
require_once '../classes/Vendor.class.php'; // Might not be directly needed here
require_once '../classes/Booking.class.php';
require_once '../classes/PaymentProcessor.class.php';
require_once '../classes/Chat.class.php'; // Needed to potentially start a chat

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

$bookingSystem = new Booking($pdo);
$paymentProcessor = new PaymentProcessor($pdo);
$chat = new Chat($pdo);

try {
    $event_id = $_POST['event_id'] ?? null;
    $vendor_id = $_POST['vendor_id'] ?? null;
    $service_id = $_POST['service_id'] ?? null;
    $service_date = $_POST['service_date'] ?? null;
    $final_amount = $_POST['final_amount'] ?? null;
    $deposit_amount = $_POST['deposit_amount'] ?? null;
    $special_instructions = $_POST['instructions'] ?? '';

    // Basic validation
    if (empty($event_id) || empty($vendor_id) || empty($service_id) || empty($service_date) || empty($final_amount)) {
        throw new Exception("Missing required booking information.");
    }
    if (!is_numeric($final_amount) || $final_amount <= 0) {
        throw new Exception("Invalid final amount.");
    }

    // Create booking with initial status 'pending_payment'
    $booking_data = [
        'event_id' => $event_id,
        'vendor_id' => $vendor_id,
        'service_id' => $service_id,
        'service_date' => $service_date,
        'final_amount' => $final_amount,
        'deposit_amount' => $deposit_amount,
        'special_instructions' => $special_instructions,
        'status' => 'pending_payment' // Set initial status
    ];

    $newBookingId = $bookingSystem->createBooking($booking_data, $_SESSION['user_id']);

    if (!$newBookingId) {
        throw new Exception("Failed to create booking in database.");
    }

    // Create chat conversation for this booking
    // The startConversation method expects user_id and vendor_id to start a new chat
    $conversation_id = $chat->startConversation($event_id, $_SESSION['user_id'], $vendor_id);
    if ($conversation_id) {
        // Update the booking with the chat_conversation_id
        $bookingSystem->updateBookingChatConversationId($newBookingId, $conversation_id);
    } else {
        error_log("Failed to create chat conversation for booking ID: $newBookingId");
        // Decide how to handle this: still proceed with payment or fail booking
        // For now, allow to proceed, but log it.
    }

    // Create payment intent using PaymentProcessor
    $paymentIntent = $paymentProcessor->createPaymentIntent(
        $final_amount,
        ['booking_id' => $newBookingId, 'user_id' => $_SESSION['user_id']]
    );

    if ($paymentIntent) {
        // Update booking with the Stripe Payment Intent ID
        $bookingSystem->updateBookingStripePaymentId($newBookingId, $paymentIntent->id);

        $_SESSION['success_message'] = "Booking initiated. Redirecting to payment!";
        // Redirect to a page where payment can be completed (e.g., Stripe Checkout/Elements)
        header('Location: ' . BASE_URL . 'public/booking_confirmation.php?booking_id=' . $newBookingId . '&client_secret=' . $paymentIntent->client_secret);
        exit();
    } else {
        // If payment intent creation fails, cancel the booking or mark it appropriately
        $bookingSystem->updateBookingStatus($newBookingId, 'cancelled');
        throw new Exception("Payment intent creation failed. Booking cancelled.");
    }

} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
    // Redirect back to the previous page or a specific error page
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? BASE_URL . 'public/')); // Fallback to homepage
    exit();
}