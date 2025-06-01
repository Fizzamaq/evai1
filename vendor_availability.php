<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../classes/Vendor.class.php';

$vendor = new Vendor($pdo); // Pass PDO
$vendor->verifyVendorAccess(); // Your existing auth check, ensures $_SESSION['vendor_id'] is set

if (!isset($_SESSION['vendor_id'])) {
    // This should ideally be caught by verifyVendorAccess, but as a fallback
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

// Handle calendar updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Assuming $_POST['dates'] contains a JSON string or similar structure
    // For FullCalendar, availability updates usually come as individual event objects.
    // This needs to be clarified based on how FullCalendar sends data for updates.
    // Let's assume a simplified single update:
    $date = $_POST['date'] ?? null;
    $start_time = $_POST['start_time'] ?? null;
    $end_time = $_POST['end_time'] ?? null;
    $status = $_POST['status'] ?? 'available';

    if ($date && $start_time && $end_time) {
        try {
            $vendor->updateAvailability($_SESSION['vendor_id'], $date, $start_time, $end_time, $status);
            // For a real API endpoint, you'd usually return JSON success/error.
            // For a direct page, redirect or show message.
            $_SESSION['success_message'] = "Availability updated successfully!";
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error updating availability: " . $e->getMessage();
        }
    } else {
         $_SESSION['error_message'] = "Invalid availability data provided.";
    }
    header('Location: ' . $_SERVER['REQUEST_URI']); // Redirect to avoid re-submission
    exit();
}

// Fetch current month's availability for display
$start_date_display = date('Y-m-01');
$end_date_display = date('Y-m-t');
$events = $vendor->getAvailability($_SESSION['vendor_id'], $start_date_display, $end_date_display);

// Format events for FullCalendar if needed
$fullCalendarEvents = array_map(function($event) {
    return [
        'id' => $event['id'],
        'title' => ucfirst($event['status']),
        'start' => $event['date'] . 'T' . $event['start_time'], // Combine date and time
        'end' => $event['date'] . 'T' . $event['end_time'],     // Combine date and time
        'allDay' => false,
        'status' => $event['status'], // Custom property for styling
        'classNames' => ['fc-event-' . $event['status']]
    ];
}, $events);

// Convert PHP array to JSON for JavaScript
$jsonEvents = json_encode($fullCalendarEvents);

?>

<!DOCTYPE html>
<html>
<head>
    <title>Availability Manager</title>
    <link rel="stylesheet" href="<?= ASSETS_PATH ?>css/vendor.css">
    <link rel="stylesheet" href="<?= ASSETS_PATH ?>css/style.css">
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script> <style>
        #calendar {
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .fc-event {
            cursor: pointer;
            padding: 3px;
            border-radius: 4px;
            font-size: 0.9em;
        }

        .fc-event-available {
            background: #c8e6c9;
            border-color: #a5d6a7;
            color: #1b5e20;
        }

        .fc-event-booked {
            background: #ffcdd2;
            border-color: #ef9a9a;
            color: #b71c1c;
        }

        .fc-event-blocked {
            background: #e0e0e0;
            border-color: #bdbdbd;
            color: #424242;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; // Includes the unified header ?>

    <div id="calendar"></div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const calendarEl = document.getElementById('calendar');
        if (calendarEl) {
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                events: <?= $jsonEvents ?>, // Pass events as JSON
                selectable: true, // Allow selecting dates
                select: function(info) {
                    // Prompt user for availability status
                    const status = prompt('Set availability for ' + info.startStr + ' to ' + info.endStr + '\n(available, booked, blocked):');
                    if (status && ['available', 'booked', 'blocked'].includes(status.toLowerCase())) {
                        // Send data to server for update
                        fetch('vendor_availability.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: new URLSearchParams({
                                date: info.startStr.split('T')[0], // Extract date
                                start_time: info.startStr.split('T')[1] || '00:00:00', // Extract time or default
                                end_time: info.endStr.split('T')[1] || '23:59:59',   // Extract time or default
                                status: status.toLowerCase()
                            })
                        }).then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Availability updated!');
                                calendar.refetchEvents(); // Refresh calendar events
                            } else {
                                alert('Error updating availability: ' + data.error);
                            }
                        }).catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred during update.');
                        });
                    }
                    calendar.unselect(); // Deselect the dates
                },
                eventClick: function(info) {
                    // Handle click on an existing event (e.g., edit or delete)
                    // Example: alert('Event: ' + info.event.title + ' on ' + info.event.startStr);
                    if (confirm('Do you want to change the status of this ' + info.event.title + ' slot?')) {
                        const newStatus = prompt('New status (available, booked, blocked):', info.event.extendedProps.status);
                        if (newStatus && ['available', 'booked', 'blocked'].includes(newStatus.toLowerCase())) {
                            fetch('vendor_availability.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: new URLSearchParams({
                                    date: info.event.startStr.split('T')[0],
                                    start_time: info.event.startStr.split('T')[1] || '00:00:00',
                                    end_time: info.event.endStr.split('T')[1] || '23:59:59',
                                    status: newStatus.toLowerCase()
                                })
                            }).then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    alert('Status updated!');
                                    calendar.refetchEvents();
                                } else {
                                    alert('Error updating status: ' + data.error);
                                }
                            }).catch(error => {
                                console.error('Error:', error);
                                alert('An error occurred during status update.');
                            });
                        }
                    }
                },
                eventContent: function(arg) {
                    // Customize event display
                    let statusClass = '';
                    if (arg.event.extendedProps.status === 'available') {
                        statusClass = 'fc-event-available';
                    } else if (arg.event.extendedProps.status === 'booked') {
                        statusClass = 'fc-event-booked';
                    } else if (arg.event.extendedProps.status === 'blocked') {
                        statusClass = 'fc-event-blocked';
                    }
                    return { html: `<div class="${statusClass}">${arg.event.title}</div>` };
                }
            });
            calendar.render();
        }
    });
    </script>
</body>
</html>
