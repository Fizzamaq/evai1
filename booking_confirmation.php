<?php
session_start();
require_once '../includes/config.php';
include 'header.php';

// This page would typically handle displaying confirmation after a successful payment intent capture
// Or it might be a redirect target from a payment gateway.

$bookingId = $_GET['booking_id'] ?? null;
$clientSecret = $_GET['client_secret'] ?? null; // For Stripe client-side confirmation

?>
<div class="confirmation-container">
    <h1>Booking Confirmation</h1>
    <p>Your booking has been successfully initiated!</p>
    <p>Booking ID: <strong><?= htmlspecialchars($bookingId) ?></strong></p>

    <?php if ($clientSecret): ?>
        <p>Please complete the payment using the provided payment method.</p>
        <div id="payment-element">
            </div>
        <button id="submit-payment-btn" class="btn btn-primary">Pay Now</button>
        <script src="https://js.stripe.com/v3/"></script>
        <script>
            const stripe = Stripe('YOUR_STRIPE_PUBLISHABLE_KEY'); // Replace with your actual key
            const clientSecret = '<?= htmlspecialchars($clientSecret) ?>';

            const appearance = { /* appearance styling */ };
            const elements = stripe.elements({ clientSecret, appearance });
            const paymentElement = elements.create('payment');
            paymentElement.mount('#payment-element');

            document.getElementById('submit-payment-btn').addEventListener('click', async () => {
                const { error } = await stripe.confirmPayment({
                    elements,
                    confirmParams: {
                        return_url: '<?= BASE_URL ?>public/booking_confirmed.php?booking_id=<?= $bookingId ?>', // Redirect after payment
                    },
                });

                if (error) {
                    alert(error.message);
                }
            });
        </script>
    <?php else: ?>
        <p>Your booking is awaiting payment confirmation. You will receive an email shortly with details.</p>
    <?php endif; ?>

    <a href="<?= BASE_URL ?>public/dashboard.php" class="btn btn-secondary">Go to Dashboard</a>
</div>
<?php include 'footer.php'; ?>