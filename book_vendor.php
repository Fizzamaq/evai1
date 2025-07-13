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

$vendor_id_from_url = $_GET['vendor_id'] ?? null;
$prefill_date = $_GET['prefill_date'] ?? null;

$user_obj = new User($pdo);
$vendor_obj = new Vendor($pdo);
$event_obj = new Event($pdo); // Instantiate Event object

// Initialize all variables that will be used in the HTML to avoid 'Undefined variable' warnings
$vendor_profile = null;
$vendor_service_offerings_grouped = [];
$event_types = []; // To fetch event types for the new event dropdown
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;

// Clear session messages immediately after reading them
unset($_SESSION['success_message'], $_SESSION['error_message']);

try {
    // 1. Validate incoming Vendor ID from URL and fetch data
    if (empty($vendor_id_from_url) || !is_numeric($vendor_id_from_url)) {
        throw new Exception("Booking request incomplete. Missing or invalid vendor ID in URL.");
    }

    // Fetch vendor profile using the validated and cast ID
    $vendor_profile = $vendor_obj->getVendorProfileById((int)$vendor_id_from_url);

    if (!$vendor_profile) {
        throw new Exception("Invalid vendor ID provided. Vendor not found.");
    }

    // Fetch all service offerings for this vendor, grouped by category
    $all_vendor_offerings_raw = $vendor_obj->getVendorServices($vendor_profile['id']); 
    
    // Now, retrieve full details (including packages) for each offering
    foreach ($all_vendor_offerings_raw as $offering) {
        $full_offering_details = $vendor_obj->getServiceOfferingById($offering['id'], $vendor_profile['id']);
        if ($full_offering_details) {
            $vendor_service_offerings_grouped[$full_offering_details['category_name']][] = $full_offering_details;
        }
    }

    // Fetch all event types for the "new event" dropdown
    $stmt = $pdo->query("SELECT id, type_name FROM event_types ORDER BY type_name ASC");
    $event_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($event_types)) {
        throw new Exception("No event types configured. Please contact support.");
    }


} catch (Exception $e) {
    // If any exception occurs during data fetching/validation, redirect with error
    $_SESSION['error_message'] = $e->getMessage();
    error_log("Booking page load error: " . $e->getMessage()); // Log the error
    
    // Redirect to a relevant page based on the error
    if (strpos($e->getMessage(), "event types configured") !== false) {
        header('Location: ' . BASE_URL . 'public/dashboard.php'); // Go to dashboard if no event types
    } else if (isset($vendor_profile) && $vendor_profile) {
        header('Location: ' . BASE_URL . 'public/vendor_profile.php?id=' . $vendor_profile['id']);
    } else {
        header('Location: ' . BASE_URL . 'public/vendors.php');
    }
    exit();
}

// This variable is now guaranteed to be a valid integer ID because of the checks above.
$vendor_profile_id_for_js = (int)$vendor_profile['id'];

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
    <link rel="stylesheet" href="../assets/css/dashboard.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script> <!-- Include FullCalendar -->
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

        /* Calendar specific styles for book_vendor.php */
        .booking-calendar-section {
            margin-top: var(--spacing-lg);
            padding: var(--spacing-md);
            background: var(--background-light);
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .booking-calendar-section h3 {
            font-size: 1.5em;
            color: var(--text-dark);
            margin-bottom: var(--spacing-md);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: var(--spacing-sm);
        }
        #booking-fullcalendar {
            max-width: 100%; /* Ensure calendar is responsive */
            margin: 0 auto;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
        }
        /* Reusing FullCalendar event styles from vendor_profile.css */
        .fc-event-available { background-color: #4CAF50 !important; color: #fff !important; }
        .fc-event-booked { background-color: #F44336 !important; color: #fff !important; }
        .fc-event-blocked { background-color: #B0BEC5 !important; color: #fff !important; }
        .fc-event-holiday { background-color: #FF9800 !important; color: #fff !important; }
        .fc-event-status-text { /* Ensure text is visible in events */
            white-space: normal;
            overflow: hidden;
            text-overflow: ellipsis;
            padding: 1px;
            line-height: 1.2;
        }
        .fc .fc-button-primary:not(:disabled).fc-button-active {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .fc .fc-button-primary:not(:disabled).fc-button-active:hover {
            background-color: var(--primary-hover-color);
            border-color: var(--primary-hover-color);
        }
        .fc .fc-button {
            background: none;
            border: 1px solid var(--border-color);
            color: var(--text-dark);
        }
        .fc .fc-button:hover {
            background-color: #e9e9e9;
        }
        /* Style for selected date in calendar */
        .fc-daygrid-day.selected-date {
            background-color: var(--secondary-color-light) !important; /* Light highlight */
            border: 2px solid var(--secondary-color) !important; /* Stronger border */
            box-shadow: 0 0 5px rgba(var(--secondary-color-rgb), 0.5);
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

        <form action="<?= BASE_URL ?>public/process_booking.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="vendor_id" value="<?= htmlspecialchars($vendor_profile['user_id']) ?>">
            
            <!-- Hidden input for service_date, will be populated by JS from calendar selection -->
            <input type="hidden" name="service_date" id="service_date_input" value="<?= htmlspecialchars($prefill_date ?? '') ?>">

            <div class="form-group">
                <label for="new_event_title">New Event Name <span class="required">*</span></label>
                <input type="text" id="new_event_title" name="new_event_title" class="form-control" required placeholder="e.g., John's Birthday Party">
            </div>

            <div class="form-group">
                <label for="new_event_type_id">Event Type <span class="required">*</span></label>
                <select id="new_event_type_id" name="new_event_type_id" class="form-control" required>
                    <option value="">-- Select Event Type --</option>
                    <?php foreach ($event_types as $type): ?>
                        <option value="<?= htmlspecialchars($type['id']) ?>"><?= htmlspecialchars($type['type_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Optional: Add guest count and budget for new event if needed -->
            <div class="form-group">
                <label for="new_event_guest_count">Approx. Guest Count</label>
                <input type="number" id="new_event_guest_count" name="new_event_guest_count" class="form-control" min="1" placeholder="e.g., 50">
            </div>
            <div class="form-group">
                <label for="new_event_budget_min">Min. Budget (PKR)</label>
                <input type="number" id="new_event_budget_min" name="new_event_budget_min" class="form-control" min="0" placeholder="e.g., 10000">
            </div>
            <div class="form-group">
                <label for="new_event_budget_max">Max. Budget (PKR)</label>
                <input type="number" id="new_event_budget_max" name="new_event_budget_max" class="form-control" min="0" placeholder="e.g., 50000">
            </div>


            <div class="form-group">
                <label for="display_selected_date">Selected Service Date <span class="required">*</span></label>
                <!-- Display the selected date to the user, updated by JS -->
                <input type="text" id="display_selected_date" class="form-control" 
                       value="<?= htmlspecialchars($prefill_date ? date('F j, Y', strtotime($prefill_date)) : 'No date selected') ?>" readonly>
                <small class="text-muted">Click on an available date in the calendar below to select it.</small>
            </div>

            <div class="booking-calendar-section">
                <h3>Select a Date from Availability</h3>
                <div id="booking-fullcalendar"></div>
                <div class="calendar-legend" style="margin-top: var(--spacing-md);">
                    <span class="legend-item"><span class="legend-color available-color"></span> Available</span>
                    <span class="legend-item"><span class="legend-color booked-color"></span> Booked</span>
                    <span class="legend-item"><span class="legend-color blocked-color"></span> Blocked</span>
                    <span class="legend-item"><span class="legend-color holiday-color"></span> Holiday</span>
                </div>
            </div>


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
                <label for="picture_upload">Upload a screenshot <span class="required">*</span></label>
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
            const serviceDateInput = document.getElementById('service_date_input');
            const displaySelectedDate = document.getElementById('display_selected_date');
            const bookingCalendarEl = document.getElementById('booking-fullcalendar');
            const vendorId = <?= json_encode($vendor_profile_id_for_js) ?>; // Use the validated ID

            let selectedDateCell = null; // To keep track of the visually selected date cell

            // Initialize FullCalendar on the booking page
            if (bookingCalendarEl && typeof vendorId === 'number' && vendorId > 0) {
                const bookingCalendar = new FullCalendar.Calendar(bookingCalendarEl, {
                    initialView: 'dayGridMonth',
                    initialDate: serviceDateInput.value || new Date(), // Use prefill date or current date
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: ''
                    },
                    validRange: {
                        start: '<?= date('Y-m-d') ?>' // Only allow selecting today and future dates
                    },
                    events: function(fetchInfo, successCallback, failureCallback) {
                        fetch(`<?= BASE_URL ?>public/availability.php?vendor_id=${vendorId}&start=${fetchInfo.startStr}&end=${fetchInfo.endStr}`)
                            .then(response => {
                                if (!response.ok) {
                                    return response.text().then(text => {
                                        console.error('Error fetching availability for booking calendar:', response.status, text);
                                        throw new Error('Server error or invalid JSON response for calendar data.');
                                    });
                                }
                                return response.json();
                            })
                            .then(data => {
                                successCallback(data);
                            })
                            .catch(error => {
                                console.error('Error fetching availability for booking calendar:', error);
                                bookingCalendarEl.innerHTML = '<p class="text-subtle">Failed to load calendar. Please check console for details.</p>';
                                failureCallback(error);
                            });
                    },
                    eventContent: function(arg) {
                        // Display event title/status in the calendar cells
                        return { html: `<div class="fc-event-status-text">${arg.event.title}</div>` };
                    },
                    dateClick: function(info) {
                        // Clear previous selection highlight
                        if (selectedDateCell) {
                            selectedDateCell.classList.remove('selected-date');
                        }

                        // Highlight the newly clicked date cell
                        info.dayEl.classList.add('selected-date');
                        selectedDateCell = info.dayEl;

                        // Update the hidden input field and visible display
                        serviceDateInput.value = info.dateStr;
                        displaySelectedDate.value = info.date.toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        });

                        // Optional: Alert user if date is not available for booking
                        // You might want to check info.dayEl.querySelector('.fc-event-available')
                        // or fetch availability for this specific date if not already done.
                        const eventsOnDay = bookingCalendar.getEvents().filter(event => event.startStr.startsWith(info.dateStr)); // Corrected: Use getEvents() directly
                        const isAvailable = eventsOnDay.some(event => event.extendedProps.status === 'available');

                        if (!isAvailable) {
                            alert('This date is not marked as available by the vendor. Please choose an available date.');
                            // Optionally clear the selection if not available
                            serviceDateInput.value = '';
                            displaySelectedDate.value = 'No date selected';
                            info.dayEl.classList.remove('selected-date');
                            selectedDateCell = null;
                        }
                    },
                    // Ensure events are clickable for details, but dateClick handles selection
                    eventClick: function(info) {
                        info.jsEvent.preventDefault(); // Prevent default behavior
                        // If you want clicking an event to also select the date, call dateClick logic here
                        // For now, it will just trigger the dateClick if the event covers the whole day.
                        // Or you can add specific logic for event details here.
                    }
                });
                bookingCalendar.render();

                // If a prefill_date exists, try to highlight it on calendar load
                if (serviceDateInput.value) {
                    const prefillDate = serviceDateInput.value;
                    // FullCalendar's getDateElement is not for day cells directly.
                    // We need to find the day cell element based on its data-date attribute.
                    const prefillDateEl = bookingCalendarEl.querySelector(`.fc-daygrid-day[data-date="${prefillDate}"]`);
                    
                    if (prefillDateEl) {
                        prefillDateEl.classList.add('selected-date');
                        selectedDateCell = prefillDateEl;
                    }
                }

            } else {
                bookingCalendarEl.innerHTML = '<p class="text-subtle">Vendor ID is invalid, cannot display availability calendar for booking.</p>';
            }


            // Basic client-side validation for form submission
            document.querySelector('form').addEventListener('submit', function(event) {
                const selectedServices = document.querySelectorAll('input[name="selected_services[]"]:checked');
                const pictureUploadInput = document.getElementById('picture_upload');
                const newEventTitleInput = document.getElementById('new_event_title'); // NEW
                const newEventTypeSelect = document.getElementById('new_event_type_id'); // NEW

                if (selectedServices.length === 0) {
                    alert('Please select at least one service to book.');
                    event.preventDefault();
                    return;
                }

                if (!serviceDateInput.value) { // Check if the hidden date input has a value
                    alert('Please select a desired service date from the calendar.');
                    event.preventDefault();
                    return;
                }

                // NEW: Validate new event details
                if (!newEventTitleInput.value.trim()) {
                    alert('Please enter a name for your new event.');
                    event.preventDefault();
                    return;
                }
                if (!newEventTypeSelect.value) {
                    alert('Please select an event type for your new event.');
                    event.preventDefault();
                    return;
                }


                if (pictureUploadInput.files.length === 0) {
                    alert('Please upload a screenshot for the booking.');
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
