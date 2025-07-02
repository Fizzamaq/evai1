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
// Removed $prefill_date as it's no longer used in the form input
// $prefill_date = $_GET['prefill_date'] ?? null;

$user_obj = new User($pdo);
$vendor_obj = new Vendor($pdo);
$event_obj = new Event($pdo);

// Initialize all variables that will be used in the HTML to avoid 'Undefined variable' warnings
$vendor_profile = null;
$vendor_service_offerings_grouped = []; // To hold all services offered by the vendor, grouped by category
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;

// Clear session messages immediately after reading them
unset($_SESSION['success_message'], $_SESSION['error_message']);

try {
    // 1. Validate incoming Vendor ID and fetch data
    if (empty($vendor_id)) {
        throw new Exception("Booking request incomplete. Missing vendor ID.");
    }

    // Fetch vendor profile
    $vendor_profile = $vendor_obj->getVendorProfileById($vendor_id);

    if (!$vendor_profile) {
        throw new Exception("Invalid vendor ID provided. Vendor not found.");
    }

    // Fetch all service offerings for this vendor, grouped by category
    $all_vendor_offerings_raw = $vendor_obj->getVendorServices($vendor_profile['id']); // This gets basic offerings
    
    // Now, retrieve full details (including packages) for each offering
    foreach ($all_vendor_offerings_raw as $offering) {
        $full_offering_details = $vendor_obj->getServiceOfferingById($offering['id'], $vendor_profile['id']);
        if ($full_offering_details) {
            $vendor_service_offerings_grouped[$full_offering_details['category_name']][] = $full_offering_details;
        }
    }

    // User events are no longer fetched for the form, but will be needed in process_booking_new.php
    // $user_events = $event_obj->getUserEvents($_SESSION['user_id']);
    // if (empty($user_events)) {
    //     // This check is now moved to process_booking_new.php
    //     // throw new Exception("You must have at least one event planned to make a booking. Please create an event first.");
    // }

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
        /* New Styles for Services Checkboxes */
        .services-selection-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .service-item-checkbox {
            background: var(--background-light);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            gap: 5px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative; /* Needed for absolute positioning of checkbox checkmark */
        }
        .service-item-checkbox:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        /* Style for checked state */
        .service-item-checkbox input[type="checkbox"]:checked ~ label {
            color: var(--primary-color);
        }
        .service-item-checkbox input[type="checkbox"]:checked + label + .service-details-content {
            /* Adjust content style when checked if needed */
        }
        .service-item-checkbox input[type="checkbox"]:checked {
            /* This is the hidden checkbox, styling applies to its siblings */
        }
        
        .service-item-checkbox input[type="checkbox"] {
            /* Hide the default checkbox */
            position: absolute;
            opacity: 0;
            cursor: pointer;
            height: 0;
            width: 0;
        }

        .service-item-checkbox label {
            font-weight: 600;
            color: var(--text-dark);
            cursor: pointer;
            display: flex;
            align-items: center;
            /* Flex to align custom checkbox and text */
            position: relative;
            padding-left: 30px; /* Space for custom checkbox */
            min-height: 20px; /* Ensure label height for clickable area */
        }
        
        /* Custom checkbox indicator */
        .service-item-checkbox label::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            border: 2px solid var(--border-color);
            border-radius: 4px;
            background-color: var(--white);
            transition: all 0.2s ease;
        }

        /* Custom checkmark */
        .service-item-checkbox label::after {
            content: '';
            position: absolute;
            left: 7px;
            top: 50%;
            transform: translateY(-50%) rotate(45deg);
            width: 6px;
            height: 12px;
            border: solid var(--white);
            border-width: 0 2px 2px 0;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        /* Checked state for custom checkbox */
        .service-item-checkbox input[type="checkbox"]:checked + label::before {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .service-item-checkbox input[type="checkbox"]:checked + label::after {
            opacity: 1;
        }

        /* Optional: Highlight the entire item when checked */
        .service-item-checkbox input[type="checkbox"]:checked ~ .service-details-content {
            background-color: var(--primary-color-rgb, 138, 43, 226, 0.05); /* Lighter shade of primary color */
            border-color: var(--primary-color); /* Highlight border */
            box-shadow: 0 4px 10px rgba(var(--primary-color-rgb, 138, 43, 226), 0.1); /* Subtle shadow */
        }
        .service-item-checkbox input[type="checkbox"]:checked {
            background-color: var(--primary-color-rgb, 138, 43, 226, 0.05); /* Lighter shade of primary color */
            border-color: var(--primary-color); /* Highlight border */
        }
        .service-item-checkbox.is-checked { /* Class added by JS for fallback/consistency */
            background-color: rgba(var(--primary-color-rgb, 138, 43, 226), 0.05); /* Lighter shade of primary color */
            border-color: var(--primary-color); /* Highlight border */
            box-shadow: 0 4px 10px rgba(var(--primary-color-rgb, 138, 43, 226), 0.1); /* Subtle shadow */
        }

        .service-details-content {
            display: flex;
            flex-direction: column;
            width: 100%; /* Take full width within label */
            padding-left: 30px; /* Offset for custom checkbox */
            box-sizing: border-box;
            line-height: 1.2; /* Tighter line-height for details */
        }
        
        .service-details-content .service-name-text {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 2px;
        }

        .service-details-content .price-range {
            font-size: 0.9em;
            color: var(--text-subtle);
            margin-left: 0; /* No extra margin here, let padding handle it */
            font-weight: normal;
        }
        .service-details-content .text-muted {
            font-size: 0.75em;
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
            .services-selection-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="booking-form-container">
        <div class="booking-form-header">
            <h1>Book Services from <?= htmlspecialchars($vendor_profile['business_name']) ?></h1>
        </div>

        <?php if ($success_message): ?>
            <div class="alert success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>public/process_booking_new.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="vendor_id" value="<?= htmlspecialchars($vendor_profile['user_id']) ?>">

            <?php /* Removed "Select Your Event" field */ ?>
            <?php /* Removed "Desired Service Date" field */ ?>

            <div class="form-group">
                <h3>Select Services <span class="required">*</span></h3>
                <?php if (empty($vendor_service_offerings_grouped)): ?>
                    <p>This vendor has no services listed yet.</p>
                <?php else: ?>
                    <?php foreach ($vendor_service_offerings_grouped as $category_name => $service_offerings_in_category): ?>
                        <h4 style="margin-top: var(--spacing-md); color: var(--text-dark);"><?= htmlspecialchars($category_name) ?></h4>
                        <div class="services-selection-grid">
                            <?php foreach ($service_offerings_in_category as $offering): ?>
                                <div class="service-item-checkbox">
                                    <input type="checkbox" id="service_<?= htmlspecialchars($offering['service_id']) ?>" 
                                           name="selected_services[]" value="<?= htmlspecialchars($offering['service_id']) ?>" 
                                           data-offering-id="<?= htmlspecialchars($offering['id']) ?>" 
                                           data-service-name="<?= htmlspecialchars($offering['service_name']) ?>">
                                    <label for="service_<?= htmlspecialchars($offering['service_id']) ?>">
                                        <span class="service-name-text"><?= htmlspecialchars($offering['service_name']) ?></span>
                                        <span class="price-range">
                                            <?php if ($offering['price_range_min'] !== null || $offering['price_range_max'] !== null): ?>
                                                PKR <?= number_format($offering['price_range_min'] ?? 0, 0) ?> - 
                                                PKR <?= number_format($offering['price_range_max'] ?? 0, 0) ?>
                                            <?php else: ?>
                                                Price upon request
                                            <?php endif; ?>
                                        </span>
                                        <?php if (!empty($offering['packages'])): ?>
                                            <span class="text-muted" style="font-size:0.8em;">(View packages on profile)</span>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="instructions">Details</label>
                <textarea id="instructions" name="instructions" rows="5" 
                          placeholder="Provide any additional information, specific requests, or details for the vendor (e.g., 'Theme: Vintage', 'Dietary needs for 5 guests', etc.)."></textarea>
            </div>

            <div class="form-group">
                <label for="picture_upload">Upload a screenshot <span class="required">*</span></h3></label>
                <input type="file" id="picture_upload" name="picture_upload" accept="image/*">
                <small class="text-muted">Minimum 2,000 of advance is mandatory</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Book</button>
                <a href="<?= BASE_URL ?>public/vendor_profile.php?id=<?= htmlspecialchars($vendor_profile['id']) ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Basic client-side validation
            document.querySelector('form').addEventListener('submit', function(event) {
                const selectedServices = document.querySelectorAll('input[name="selected_services[]"]:checked');

                if (selectedServices.length === 0) {
                    alert('Please select at least one service to book.');
                    event.preventDefault();
                    return;
                }
            });

            // Add visual feedback for checked services
            document.querySelectorAll('.service-item-checkbox input[type="checkbox"]').forEach(checkbox => {
                const parentDiv = checkbox.closest('.service-item-checkbox');
                if (checkbox.checked) {
                    parentDiv.classList.add('is-checked');
                }

                checkbox.addEventListener('change', function() {
                    if (this.checked) {
                        parentDiv.classList.add('is-checked');
                    } else {
                        parentDiv.classList.remove('is-checked');
                    }
                });
            });
        });
    </script>
</body>
</html>
