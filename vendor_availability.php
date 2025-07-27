<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../classes/Vendor.class.php';

$vendor = new Vendor($pdo); // Pass PDO

// Determine if this is an AJAX request early
$is_ajax_request = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_GET['ajax']) && $_GET['ajax'] == 1);

// Ensure vendor is authenticated for all actions (both page load and API calls)
$vendor->verifyVendorAccess(); // Your existing auth check, ensures $_SESSION['vendor_id'] is set

// Check if vendor ID is set in session after verification, or handle direct access attempts to API
$session_vendor_id = $_SESSION['vendor_id'] ?? null;

// --- START: Handle AJAX POST and GET requests (API endpoints) early and exit ---

// Add a top-level try-catch block for all AJAX responses to ensure valid JSON output
try {
    // Handle calendar updates (POST request)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json'); // Set JSON header for POST responses
        error_log("vendor_availability.php: AJAX POST request received."); // Log start of AJAX POST

        // Validate vendor ID from session for API access
        if (!is_numeric($session_vendor_id)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Authentication error: Invalid vendor ID in session.']);
            error_log("vendor_availability.php: Invalid session_vendor_id for POST: {$session_vendor_id}");
            exit();
        }
        error_log("vendor_availability.php: Valid session_vendor_id for POST: {$session_vendor_id}");

        $data = json_decode(file_get_contents('php://input'), true);
        error_log("vendor_availability.php: POST data received: " . print_r($data, true));

        if (!isset($data['date']) || !isset($data['start_time']) || !isset($data['end_time']) || !isset($data['status'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing availability data.']);
            error_log("vendor_availability.php: Missing data in POST request.");
            exit();
        }

        $vendor->updateAvailability(
            $session_vendor_id, // Use the validated session vendor ID
            $data['date'],
            $data['start_time'],
            $data['end_time'],
            $data['status']
        );
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Availability updated successfully!']);
        error_log("vendor_availability.php: POST update successful. Exiting.");
        exit(); // Important to exit after AJAX response
    }

    // Handle AJAX GET requests for calendar events (FullCalendar's 'events' source)
    if ($is_ajax_request && $_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Content-Type: application/json'); // Set JSON header for GET responses
        error_log("vendor_availability.php: AJAX GET request received."); // Log start of AJAX GET

        // Validate vendor ID from session for API access
        if (!is_numeric($session_vendor_id)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Authentication error: Invalid vendor ID in session.']);
            error_log("vendor_availability.php: Invalid session_vendor_id for GET: {$session_vendor_id}");
            exit();
        }
        error_log("vendor_availability.php: Valid session_vendor_id for GET: {$session_vendor_id}");

        $start_date_fetch = $_GET['start'] ?? date('Y-m-01');
        $end_date_fetch = $_GET['end'] ?? date('Y-m-t');

        error_log("vendor_availability.php: Fetched raw start: {$_GET['start']}, raw end: {$_GET['end']}");
        error_log("vendor_availability.php: Processed start: {$start_date_fetch}, end: {$end_date_fetch}");

        // Ensure start_date_fetch is not in the past
        $today = date('Y-m-d');
        if ($start_date_fetch < $today) {
            $start_date_fetch = $today;
            error_log("vendor_availability.php: start_date_fetch adjusted to today: {$start_date_fetch}");
        }

        $availability = $vendor->getAvailability(
            $session_vendor_id, // Use the validated session vendor ID
            $start_date_fetch,
            $end_date_fetch
        );
        error_log("vendor_availability.php: getAvailability returned data type: " . gettype($availability));
        // Note: For debugging, you could log a snippet of $availability here, but be careful with large data.
        // error_log("vendor_availability.php: Availability snippet: " . substr(print_r($availability, true), 0, 500));


        $formattedEvents = [];
        // FIX: Check if $availability is an array before iterating to prevent fatal errors
        if (is_array($availability)) {
            foreach ($availability as $event) {
                $formattedEvents[] = [
                    'id' => $event['id'],
                    'title' => ucfirst($event['status']), // e.g., "Available", "Booked"
                    'start' => $event['date'] . 'T' . $event['start_time'], // Full datetime string
                    'end' => $event['date'] . 'T' . $event['end_time'],     // Full datetime string
                    'allDay' => false,
                    'extendedProps' => [
                        'status' => $event['status'] // Custom property for styling
                    ]
                ];
            }
            error_log("vendor_availability.php: Successfully formatted " . count($formattedEvents) . " events.");
        } else {
            // If getAvailability returns false or not an array, send an empty array
            // and log an error for debugging on the server-side.
            error_log("vendor_availability.php: getAvailability returned non-array data for vendor ID: {$session_vendor_id}. This may indicate a database issue or a problem in Vendor.class.php. Check its internal logs too.");
        }

        echo json_encode($formattedEvents);
        error_log("vendor_availability.php: JSON response sent for GET. Exiting.");
        exit(); // Crucial: Exit after outputting JSON for AJAX GET requests
    }

} catch (Exception $e) {
    // Catch any unexpected exceptions during AJAX processing
    header('Content-Type: application/json'); // Ensure JSON header is set even on error
    http_response_code(500); // Internal Server Error
    // Log the full error for server-side debugging
    error_log("FATAL ERROR in vendor_availability.php (AJAX): " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile() . ". Trace: " . $e->getTraceAsString());
    // Provide a generic error message to the client for security
    echo json_encode(['success' => false, 'error' => 'An internal server error occurred. Please try again.']);
    exit();
}

// --- END: Handle AJAX POST and GET requests. The script will exit if an AJAX call was made. ---


// --- START: HTML Page Rendering for non-AJAX GET requests (default page load) ---
// If the script reaches this point, it means it's a regular page load, not an AJAX request.
include 'header.php'; // Includes the unified header
?>

<!DOCTYPE html>
<html>
<head>
    <title>Availability Manager</title>
    <link rel="stylesheet" href="<?= ASSETS_PATH ?>css/vendor.css">
    <link rel="stylesheet" href="<?= ASSETS_PATH ?>css/style.css">
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
    <style>
        /* Specific page styles for vendor_availability.php calendar */
        #calendar {
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 12px; /* Increased border radius for eye-catchy look */
            box-shadow: 0 6px 20px rgba(0,0,0,0.15); /* More pronounced shadow */
        }
        .fc-event {
            cursor: pointer;
            padding: 3px;
            border-radius: 4px;
            font-size: 0.9em;
        }

        /* Modal specific styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000; /* Ensure it's on top */
        }
        .modal-content {
            background: var(--white);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            width: 90%;
            max-width: 400px;
            text-align: center;
            position: relative;
        }
        .modal-content h3 {
            margin-top: 0;
            color: var(--primary-color);
            font-size: 1.5em;
        }
        .modal-buttons {
            margin-top: 20px;
            display: flex;
            justify-content: space-around;
            gap: 10px;
            flex-wrap: wrap; /* Allow buttons to wrap */
        }
        .modal-buttons .btn {
            flex-grow: 1;
            font-size: 0.9em;
            padding: 10px 15px;
            border-radius: 8px;
        }
        .date-range-display {
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--text-dark);
        }
        .status-options {
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            text-align: left;
            padding: 10px 0;
            border-top: 1px dashed var(--border-color);
            border-bottom: 1px dashed var(--border-color);
        }
        .status-options label {
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            color: var(--text-dark);
        }
        .status-options input[type="radio"] {
            transform: scale(1.2); /* Make radio buttons slightly larger */
            margin-right: 5px; /* Adjust spacing */
        }
        .modal-close-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            border: none;
            font-size: 1.5em;
            cursor: pointer;
            color: var(--text-subtle);
        }
        .modal-close-btn:hover {
            color: var(--error-color);
        }
    </style>
</head>
<body>
    <?php // include 'header.php'; // Included above for the full page load ?>

    <div id="calendar"></div>

    <div id="availability-modal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <button class="modal-close-btn" id="modal-close-btn">&times;</button>
            <h3>Set Availability Status</h3>
            <p class="date-range-display">
                <span id="modal-date-range"></span>
                <span id="modal-time-range"></span>
            </p>
            <div class="status-options">
                <label>
                    <input type="radio" name="availability_status" value="available" checked> Available
                </label>
                <label>
                    <input type="radio" name="availability_status" value="booked"> Booked
                </label>
                <label>
                    <input type="radio" name="availability_status" value="blocked"> Blocked
                </label>
                <label>
                    <input type="radio" name="availability_status" value="holiday"> Holiday
                </label>
            </div>
            <div class="modal-buttons">
                <button class="btn btn-primary" id="modal-save-btn">Save</button>
                <button class="btn btn-secondary" id="modal-cancel-btn">Cancel</button>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const calendarEl = document.getElementById('calendar');
        const modal = document.getElementById('availability-modal');
        const modalCloseBtn = document.getElementById('modal-close-btn');
        const modalSaveBtn = document.getElementById('modal-save-btn');
        const modalCancelBtn = document.getElementById('modal-cancel-btn');
        const modalDateRange = document.getElementById('modal-date-range');
        const modalTimeRange = document.getElementById('modal-time-range');

        let selectedStart = null;
        let selectedEnd = null;
        let currentEventId = null; // To store event ID if editing an existing event

        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            selectable: true, // Allow selecting dates
            editable: true, // Allow dragging/resizing events
            selectMirror: true, // Shows a placeholder as you drag
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            // ADDED: Restrict calendar navigation to current and future dates
            validRange: {
                start: '<?= date('Y-m-d') ?>' // Sets the start of the valid range to today
            },
            // Handle date range selection
            select: function(info) {
                selectedStart = info.startStr; // This is already an ISO string
                selectedEnd = info.endStr;     // This is already an ISO string
                currentEventId = null; // No event ID for new selection

                // Populate modal with date range
                modalDateRange.textContent = formatDateForModal(info.start, info.end); // Pass Date objects to format
                modalTimeRange.textContent = formatTimeForModal(info.start, info.end); // Pass Date objects to format

                // Reset radio buttons to 'available' for new selections
                document.querySelector('input[name="availability_status"][value="available"]').checked = true;

                modal.style.display = 'flex'; // Show the modal
            },
            // Handle clicking an existing event
            eventClick: function(info) {
                selectedStart = info.event.startStr; // This is already an ISO string
                selectedEnd = info.event.endStr;     // This is already an ISO string
                currentEventId = info.event.id; // Get the ID of the clicked event

                // Populate modal with event details
                modalDateRange.textContent = formatDateForModal(info.event.start, info.event.end); // Pass Date objects to format
                modalTimeRange.textContent = formatTimeForModal(info.event.start, info.event.end); // Pass Date objects to format

                // Set radio button based on existing status
                const existingStatus = info.event.extendedProps.status;
                if (existingStatus) {
                    document.querySelector(`input[name="availability_status"][value="${existingStatus}"]`).checked = true;
                } else {
                    document.querySelector('input[name="availability_status"][value="available"]').checked = true;
                }

                modal.style.display = 'flex'; // Show the modal
            },
            // Handle event dragging (change date/time of existing event)
            eventDrop: function(info) {
                const newDate = info.event.start.toISOString().slice(0, 10);
                const newStartTime = info.event.start.toTimeString().slice(0, 8);
                const newEndTime = info.event.end ? info.event.end.toTimeString().slice(0, 8) : '23:59:59'; // Default end of day if no specific end time

                updateAvailabilityOnServer(
                    newDate,
                    newStartTime,
                    newEndTime,
                    info.event.extendedProps.status || 'available' // Use existing status or default
                );
            },
            // Handle event resizing (change duration of existing event)
            eventResize: function(info) {
                const newDate = info.event.start.toISOString().slice(0, 10);
                const newStartTime = info.event.start.toTimeString().slice(0, 8);
                const newEndTime = info.event.end ? info.event.end.toTimeString().slice(0, 8) : '23:59:59'; // Default end of day if no specific end time

                updateAvailabilityOnServer(
                    newDate,
                    newStartTime,
                    newEndTime,
                    info.event.extendedProps.status || 'available' // Use existing status or default
                );
            },
            // Customize how events are rendered in the calendar
            eventContent: function(arg) {
                let statusClass = '';
                if (arg.event.extendedProps.status === 'available') {
                    statusClass = 'fc-event-available';
                } else if (arg.event.extendedProps.status === 'booked') {
                    statusClass = 'fc-event-booked';
                } else if (arg.event.extendedProps.status === 'blocked') {
                    statusClass = 'fc-event-blocked';
                } else if (arg.event.extendedProps.status === 'holiday') {
                    statusClass = 'fc-event-holiday'; // New class for Holiday
                }
                return { html: `<div class="${statusClass}">${arg.event.title}</div>` };
            },
            // Events are fetched from the dedicated public/availability.php API endpoint
            events: function(fetchInfo, successCallback, failureCallback) {
                // Construct the URL with start and end dates from FullCalendar's fetchInfo
                const url = `<?= BASE_URL ?>public/vendor_availability.php?vendor_id=<?= $session_vendor_id ?>&start=${fetchInfo.startStr}&end=${fetchInfo.endStr}&ajax=1`; // Added &ajax=1

                fetch(url)
                    .then(response => {
                        // Check if response is OK and then if it's JSON
                        const contentType = response.headers.get("content-type");
                        if (!response.ok) {
                             if (contentType && contentType.indexOf("application/json") !== -1) {
                                return response.json().then(errorData => {
                                    throw new Error(errorData.error || 'Network response was not ok, but error message is JSON.');
                                });
                            } else {
                                return response.text().then(text => {
                                    throw new Error('Network response was not ok: ' + response.statusText + ' - ' + text.substring(0, 100) + '...'); // Show first 100 chars of non-JSON
                                });
                            }
                        }
                        if (contentType && contentType.indexOf("application/json") !== -1) {
                            return response.json();
                        } else {
                            throw new TypeError("Oops, we haven't got JSON!");
                        }
                    })
                    .then(data => {
                        // FullCalendar expects an array of event objects.
                        // Our PHP endpoint already formats it correctly.
                        successCallback(data);
                    })
                    .catch(error => {
                        console.error('Error fetching availability:', error);
                        alert('Failed to load calendar events: ' + error.message);
                        failureCallback(error); // Notify FullCalendar of the error
                    });
            }
        });
        calendar.render();

        // Modal button event listeners
        modalCloseBtn.addEventListener('click', hideModal);
        modalCancelBtn.addEventListener('click', hideModal);

        modalSaveBtn.addEventListener('click', function() {
            const selectedStatus = document.querySelector('input[name="availability_status"]:checked').value;
            const startDate = selectedStart.slice(0, 10); // Extract date part
            const startTime = selectedStart.slice(11, 19) || '00:00:00'; // Extract time or default
            const endDate = selectedEnd.slice(0, 10); // Extract date part
            const endTime = selectedEnd.slice(11, 19) || '23:59:59'; // Extract time or default

            // FullCalendar treats a selected *day* as starting at 00:00:00 and ending at 00:00:00 of the *next* day.
            // For `dayGridMonth` selection of a single day, info.endStr is the next day's 00:00:00.
            // Adjust `endDate` to be the same as `startDate` if it's just a single day selection.
            const adjustedEndDate = (startDate === endDate && startTime === '00:00:00' && endTime === '00:00:00') ? startDate : endDate;

            // Send data to server
            updateAvailabilityOnServer(startDate, startTime, adjustedEndDate, selectedStatus); // Pass adjustedEndDate
            hideModal();
        });

        function hideModal() {
            modal.style.display = 'none';
            calendar.unselect(); // Deselect any dates on the calendar
        }

        function updateAvailabilityOnServer(date, startTime, endTime, status) {
            fetch('vendor_availability.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json', // Send as JSON
                },
                body: JSON.stringify({ // Stringify the object to JSON
                    date: date,
                    start_time: startTime,
                    end_time: endTime,
                    status: status
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Availability updated!');
                    calendar.refetchEvents(); // Refresh calendar events
                } else {
                    alert('Error updating availability: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred during update.');
            });
        }

        // Functions to format dates and times for modal display
        function formatDateForModal(start, end) {
            // FullCalendar passes Date objects directly to these functions
            const startDate = new Date(start);
            const endDate = new Date(end); // FullCalendar's end date for day-selection is exclusive, so subtract one day for display

            const displayEndDate = new Date(endDate);
            // Only adjust end date for full-day selections (where time is 00:00:00)
            if (start.getHours() === 0 && start.getMinutes() === 0 && end.getHours() === 0 && end.getMinutes() === 0 && startDate.getTime() !== endDate.getTime()) {
                displayEndDate.setDate(displayEndDate.getDate() - 1);
            }

            if (startDate.getTime() === displayEndDate.getTime()) {
                return startDate.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
            } else {
                return `${startDate.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })} - ${displayEndDate.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}`;
            }
        }

        function formatTimeForModal(start, end) {
            const startDate = new Date(start);
            const endDate = new Date(end);

            // Check if it's an all-day event (start and end times are midnight and it's a date range)
            if (startDate.getHours() === 0 && startDate.getMinutes() === 0 && endDate.getHours() === 0 && endDate.getMinutes() === 0 && startDate.getTime() !== endDate.getTime()) {
                return ''; // All-day event, no time range to display
            } else if (startDate.getTime() === endDate.getTime()) {
                // If it's a single day click with a time range, or an all-day slot represented by same start/end date
                 return `${startDate.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })} - ${endDate.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })}`;
            } else {
                // Multi-day timed event or other specific scenarios
                 return `${startDate.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })} - ${endDate.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })}`;
            }
        }
    });
    </script>
</body>
</html>
