<?php
require_once __DIR__ . '/vendor/autoload.php'; // For Stripe SDK

class PaymentProcessor {
    private $stripe;
    private $pdo;

    public function __construct($pdo) {
        // Ensure Stripe API key is defined
        if (!defined('STRIPE_SECRET_KEY') || empty(STRIPE_SECRET_KEY)) {
            throw new Exception('Stripe Secret Key is not configured.');
        }
        $this->stripe = new \Stripe\StripeClient(STRIPE_SECRET_KEY);
        $this->pdo = $pdo;
    }

    public function createPaymentIntent($amount, $metadata = []) {
        try {
            return $this->stripe->paymentIntents->create([
                'amount' => $amount * 100, // Convert to cents
                'currency' => 'usd',
                'metadata' => $metadata,
                'payment_method_types' => ['card'],
                'capture_method' => 'manual' // For holding payments
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->logError($e, $metadata['booking_id'] ?? null, $metadata['user_id'] ?? null);
            return false;
        }
    }

    public function confirmPayment($paymentIntentId) {
        try {
            return $this->stripe->paymentIntents->capture($paymentIntentId);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            // Fetch payment intent to get metadata for logging
            $paymentIntent = $this->stripe->paymentIntents->retrieve($paymentIntentId);
            $this->logError($e, $paymentIntent->metadata->booking_id ?? null, $paymentIntent->metadata->user_id ?? null);
            return false;
        }
    }

    public function createCustomer($userData) {
        try {
            return $this->stripe->customers->create([
                'email' => $userData['email'],
                'name' => $userData['name'],
                'metadata' => ['user_id' => $userData['id']]
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->logError($e, null, $userData['id'] ?? null);
            return false;
        }
    }

    public function handleWebhook() {
        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

        // Ensure STRIPE_WEBHOOK_SECRET is defined
        if (!defined('STRIPE_WEBHOOK_SECRET') || empty(STRIPE_WEBHOOK_SECRET)) {
            error_log('Stripe Webhook Secret is not configured.');
            http_response_code(500);
            exit;
        }

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sig_header, STRIPE_WEBHOOK_SECRET
            );
        } catch(\UnexpectedValueException $e) {
            // Invalid payload
            error_log('Webhook Error: Invalid payload. ' . $e->getMessage());
            http_response_code(400);
            exit;
        } catch(\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            error_log('Webhook Error: Invalid signature. ' . $e->getMessage());
            http_response_code(400);
            exit;
        }

        // Get booking instance to update status
        require_once __DIR__ . '/Booking.class.php';
        $bookingSystem = new Booking($this->pdo);

        switch ($event->type) {
            case 'payment_intent.succeeded':
                $paymentIntent = $event->data->object;
                $bookingId = $paymentIntent->metadata->booking_id ?? null;
                if ($bookingId) {
                    $bookingSystem->updateBookingStatus($bookingId, 'completed', $paymentIntent->id);
                    error_log("Booking $bookingId payment succeeded. PaymentIntent: " . $paymentIntent->id);
                }
                break;
            case 'payment_intent.payment_failed':
                $paymentIntent = $event->data->object;
                $bookingId = $paymentIntent->metadata->booking_id ?? null;
                if ($bookingId) {
                    $bookingSystem->updateBookingStatus($bookingId, 'payment_failed', $paymentIntent->id);
                    error_log("Booking $bookingId payment failed. PaymentIntent: " . $paymentIntent->id);
                }
                break;
            case 'charge.refunded':
                $charge = $event->data->object;
                // Find booking associated with this charge/payment_intent if possible
                $paymentIntentId = $charge->payment_intent ?? null;
                if ($paymentIntentId) {
                    // You might need to query your bookings table to find the booking
                    // by stripe_payment_id if you store payment_intent_id there.
                    $stmt = $this->pdo->prepare("SELECT id FROM bookings WHERE stripe_payment_id = ?");
                    $stmt->execute([$paymentIntentId]);
                    $bookingId = $stmt->fetchColumn();
                    if ($bookingId) {
                        $bookingSystem->updateBookingStatus($bookingId, 'refunded', $paymentIntentId);
                        error_log("Booking $bookingId refunded. Charge ID: " . $charge->id);
                    }
                }
                break;
            default:
                error_log('Received unknown event type ' . $event->type);
        }

        http_response_code(200);
    }

    private function logError($e, $bookingId = null, $userId = null) {
        error_log("Payment Error: " . $e->getMessage());
        // Insert into payment_errors table
        $stmt = $this->pdo->prepare("
            INSERT INTO payment_errors 
            (booking_id, user_id, error_code, message, metadata)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $bookingId,
            $userId,
            $e->getError()->code ?? 'unknown',
            $e->getMessage(),
            json_encode($e->getError() ? $e->getError()->jsonSerialize() : [])
        ]);
    }
}