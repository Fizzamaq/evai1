<?php
session_start();
require_once '../includes/config.php';
require_once '../classes/User.class.php';
require_once '../classes/Vendor.class.php';
require_once '../classes/Event.class.php';

// Enable error reporting temporarily for debugging. REMOVE IN PRODUCTION!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

$vendor_id = $_GET['vendor_id'] ?? null;
$service_offering_id = $_GET['service_offering_id'] ?? null;
$package_id = $_GET['package_id'] ?? null;
$prefill_date = $_GET['prefill_date'] ?? null; // For pre-filling from availability calendar

$user_obj = new User($pdo);
$vendor_obj = new Vendor($pdo);
$event_obj = new Event($pdo);

// Initialize all variables that will be used in the HTML to avoid 'Undefined variable' warnings
$vendor_profile = null;
$service_offering = null;
$package = null;
$final_amount = 0;
$deposit_amount = 0;
$user_events = [];
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;

// Clear session messages immediately after reading them
unset($_SESSION['success_message'], $_SESSION['error_message']);

try {
    // 1. Validate incoming IDs and fetch data
    // This block catches if any critical IDs are genuinely missing from the URL
    if (empty($vendor_id) || empty($service_offering_id) || empty($package_id)) {
        throw new Exception("Booking request incomplete. Missing vendor, service, or package ID.");
    }

    // Fetch vendor profile first
    $vendor_profile = $vendor_obj->getVendorProfileById($vendor_id);

    // If vendor_profile is not found, redirect immediately
    if (!$vendor_profile) {
        throw new Exception("Invalid vendor ID provided. Vendor not found.");
    }

    // Now that $vendor_profile is confirmed, fetch service offering
    // Ensure the service offering belongs to this vendor
    $service_offering = $vendor_obj->getServiceOfferingById($service_offering_id, $vendor_profile['id']);

    // If service_offering is not found or doesn't belong to the vendor, redirect
    if (!$service_offering) {
        throw new Exception("Invalid service ID provided or service does not belong to this vendor.");
    }

    // Find the specific package within the service offering
    if (!empty($service_offering['packages'])) {
        foreach ($service_offering['packages'] as $pkg) {
            if ($pkg['id'] == $package_id) { // Use == for comparison, not === to allow for type coercion if needed
                $package = $pkg;
                break;
            }
        }
    }

    // If package is not found within the service offering, redirect
    if (!$package) {
        throw new Exception("Invalid package ID provided or package not found for this service.");
    }

    // 2. Fetch user's events (only if we've successfully validated vendor, service, and package)
    $user_events = $event_obj->getUserEvents($_SESSION['user_id']);
    if (empty($user_events)) {
        throw new Exception("You must have at least one event planned to make a booking. Please create an event first.");
    }

    // Determine final amount for the form
    // Use package's min_price, if not set, use max_price. Default to 0.
    $final_amount = $package['price_min'] ?? $package['price_max'] ?? 0;
    if ($final_amount == 0) { // If final_amount is still 0 after trying min/max
        // Only log warning if original min/max were not null
        if ($package['price_min'] !== null || $package['price_max'] !== null) {
            error_log("Warning: Package ID {$package_id} has zero price range. Defaulting final_amount to 1.");
        }
        $final_amount = 1; // Set a minimum amount to avoid 0 bookings
    }


    // You might implement a deposit calculation here
    $deposit_amount = round($final_amount * 0.20, 2); // Example: 20% deposit

} catch (Exception $e) {
    // If any exception occurs during data fetching/validation, redirect with error
    $_SESSION['error_message'] = $e->getMessage();
    // Decide appropriate redirect URL based on what failed
    if (isset($vendor_profile) && $vendor_profile) {
        // If vendor profile was fetched, redirect back to vendor's profile page
        header('Location: ' . BASE_URL . 'public/vendor_profile.php?id=' . $vendor_profile['id']);
    } else {
        // If vendor profile wasn't even fetched (e.g., vendor_id was bad), go to general vendors list
        header('Location: ' . BASE_URL . 'public/vendors.php');
    }
    exit();
}

// If we reach this point, all data is valid and variables are set.
include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book <?= htmlspecialchars($vendor_profile['business_name']) ?> - EventCraftAI</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .booking-form-container {
            max-width: 700px;
            margin: var(--spacing-lg) auto;
            padding: var(--spacing-md);
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .booking-form-header {
            text-align: center;
            margin-bottom: var(--spacing-lg);
            padding-bottom: var(--spacing-md);
            border-bottom: 2px solid var(--border-color);
        }
        .booking-form-header h1 {
            font-size: 2.2em;
            color: var(--primary-color);
            margin-bottom: var(--spacing-sm);
        }
        .booking-summary {
            background-color: var(--background-light);
            padding: var(--spacing-md);
            border-radius: 8px;
            margin-bottom: var(--spacing-lg);
            border: 1px solid var(--light-grey-border);
        }
        .booking-summary p {
            margin-bottom: var(--spacing-xs);
            color: var(--text-dark);
        }
        .booking-summary strong {
            color: var(--primary-color);
        }
        .booking-summary .price-display {
            font-size: 1.2em;
            font-weight: 700;
            color: var(--secondary-color);
            margin-top: var(--spacing-sm);
        }
        .form-group { margin-bottom: var(--spacing-md); }
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: var(--spacing-md);
            margin-top: var(--spacing-lg);
            padding-top: var(--spacing-md);
            border-top: 1px solid var(--border-color);
        }
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .booking-form-container {
                padding: var(--spacing-sm);
            }
            .form-actions {
                flex-direction: column;
            }
            .form-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="booking-form-container">
        <div class="booking-form-header">
            <h1>Book Service: <?= htmlspecialchars($package['package_name']) ?></h1>
            <p>From: **<?= htmlspecialchars($vendor_profile['business_name']) ?>**</p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <div class="booking-summary">
            <p><strong>Vendor:</strong> <?= htmlspecialchars($vendor_profile['business_name']) ?></p>
            <p><strong>Service:</strong> <?= htmlspecialchars($service_offering['service_name']) ?></p>
            <p><strong>Package:</strong> <?= htmlspecialchars($package['package_name']) ?></p>
            <?php if (!empty($package['package_description'])): ?>
                <p><strong>Package Details:</strong> <?= nl2br(htmlspecialchars($package['package_description'])) ?></p>
            <?php endif; ?>
            <p class="price-display">
                <strong>Price Range:</strong> PKR <?= number_format($package['price_min'] ?? 0, 0) ?> - 
                PKR <?= number_format($package['price_max'] ?? 0, 0) ?>
            </p>
            <p style="font-size: 0.9em; color: var(--text-subtle);">
                *Actual final amount may vary based on specific requirements.
            </p>
        </div>

        <form action="<?= BASE_URL ?>public/process_booking.php" method="POST">
            <input type="hidden" name="vendor_id" value="<?= htmlspecialchars($vendor_profile['user_id']) ?>">
            <input type="hidden" name="service_id" value="<?= htmlspecialchars($service_offering['service_id']) ?>">
            <input type="hidden" name="final_amount" value="<?= htmlspecialchars($final_amount) ?>">
            <input type="hidden" name="deposit_amount" value="<?= htmlspecialchars($deposit_amount) ?>">
            <input type="hidden" name="package_id" value="<?= htmlspecialchars($package['id']) ?>">

            <div class="form-group">
                <label for="event_id">Select Your Event <span class="required">*</span></label>
                <select id="event_id" name="event_id" required>
                    <option value="">Choose an existing event</option>
                    <?php foreach ($user_events as $event): ?>
                        <option value="<?= htmlspecialchars($event['id']) ?>">
                            <?= htmlspecialchars($event['title']) ?> (<?= date('M j, Y', strtotime($event['event_date'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="service_date">Desired Service Date <span class="required">*</span></label>
                <input type="date" id="service_date" name="service_date" required 
                       value="<?= htmlspecialchars($prefill_date ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="instructions">Special Instructions (Optional)</label>
                <textarea id="instructions" name="instructions" rows="4" 
                          placeholder="Any specific requests, preferences, or details for the vendor..."></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Proceed to Payment</button>
                <a href="<?= BASE_URL ?>public/vendor_profile.php?id=<?= htmlspecialchars($vendor_profile['id']) ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set minimum date for service_date to today
            const serviceDateInput = document.getElementById('service_date');
            const today = new Date().toISOString().split('T')[0];
            serviceDateInput.min = today;

            // Optional: If a prefill_date is provided, ensure it's valid and set
            <?php if ($prefill_date): ?>
                if (serviceDateInput.value < serviceDateInput.min) {
                    serviceDateInput.value = serviceDateInput.min; // Adjust if prefill is in the past
                }
            <?php endif; ?>

            // Basic client-side validation
            document.querySelector('form').addEventListener('submit', function(event) {
                const eventId = document.getElementById('event_id');
                const serviceDate = document.getElementById('service_date');

                if (!eventId.value) {
                    alert('Please select an event for this booking.');
                    eventId.focus();
                    event.preventDefault();
                    return;
                }

                if (!serviceDate.value) {
                    alert('Please select a desired service date.');
                    serviceDate.focus();
                    event.preventDefault();
                    return;
                }
            });
        });
    </script>
</body>
</html>