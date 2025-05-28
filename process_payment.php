<?php
// public/process_payment.php
session_start();
require_once '../includes/config.php';
require_once '../classes/PaymentProcessor.class.php';
require_once '../classes/Booking.class.php';

$processor = new PaymentProcessor($pdo); // Pass PDO
$bookingSystem = new Booking($pdo); // Pass PDO

try {
    $bookingId = $_POST['booking_id'];
    $booking = $bookingSystem->getBooking($bookingId); // Get booking by ID

    if (!$booking) {
        throw new Exception("Booking not found.");
    }
    // Validate that the user owns this booking or has permission
    if ($booking['user_id'] != $_SESSION['user_id']) {
         throw new Exception("Access denied to process payment for this booking.");
    }

    // Create payment intent
    // The amount should come from the booking details, not directly from POST if possible
    $paymentIntent = $processor->createPaymentIntent(
        $booking['final_amount'], // Use amount from booking
        ['booking_id' => $bookingId, 'user_id' => $_SESSION['user_id']]
    );

    if ($paymentIntent) {
        echo json_encode([
            'clientSecret' => $paymentIntent->client_secret,
            'booking_id' => $booking['id'], // Return booking ID for client-side confirmation
            'final_amount' => $booking['final_amount']
        ]);
    } else {
        throw new Exception("Failed to create payment intent.");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}