<?php
session_start();
require_once '../includes/config.php';
require_once '../classes/Event.class.php';
require_once '../classes/User.class.php'; // For user data if needed for logs/validation

// --- DEBUG START: Verify process_event.php is reached ---
echo "<h2>Debugging process_event.php</h2>";
echo "<p>Script execution started.</p>";
// --- DEBUG END ---

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<p>Request method is not POST. Redirecting to create_event.php</p>"; // DEBUG
    header('Location: ' . BASE_URL . 'public/create_event.php');
    exit();
}

// --- DEBUG START: Display POST data ---
echo "<h3>POST Data Received:</h3>";
echo "<pre>";
print_r($_POST);
echo "</pre>";
// --- DEBUG END ---

$event = new Event($pdo);
$user_id = $_SESSION['user_id'];

// --- DEBUG START: Display User ID ---
echo "<p>User ID: " . htmlspecialchars($user_id) . "</p>";
// --- DEBUG END ---

// Validate required fields and collect errors
$errors = [];

// Basic validation
if (empty(trim($_POST['title']))) {
    $errors[] = 'Event Title is required.';
}
if (empty($_POST['event_type_id'])) {
    $errors[] = 'Event Type is required.';
}
if (empty($_POST['event_date'])) {
    $errors[] = 'Event Date is required.';
} elseif (strtotime($_POST['event_date']) < strtotime('today')) {
    $errors[] = 'Event date cannot be in the past.';
}

// Validate budget range
$budget_min = $_POST['budget_min'] ?? null;
$budget_max = $_POST['budget_max'] ?? null;
if ($budget_min !== null && !is_numeric($budget_min)) {
    $errors[] = 'Minimum budget must be a valid number.';
}
if ($budget_max !== null && !is_numeric($budget_max)) {
    $errors[] = 'Maximum budget must be a valid number.';
}
if (is_numeric($budget_min) && is_numeric($budget_max) && $budget_min > $budget_max) {
    $errors[] = 'Minimum budget cannot be greater than maximum budget.';
}

// Validate guest count
if (!empty($_POST['guest_count']) && (!is_numeric($_POST['guest_count']) || $_POST['guest_count'] < 1)) {
    $errors[] = 'Guest count must be a positive number.';
}

// If there are validation errors, redirect back with errors
if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data'] = $_POST;
    echo "<h3>Validation Errors:</h3>"; // DEBUG
    echo "<pre>"; // DEBUG
    print_r($errors); // DEBUG
    echo "</pre>"; // DEBUG
    die("Validation failed. Check errors above."); // DEBUG: Stop execution here to see errors
    // header('Location: ' . BASE_URL . 'public/create_event.php'); // ORIGINAL
    // exit(); // ORIGINAL
}

// Prepare event data for Event class
$event_data = [
    'user_id' => $user_id,
    'title' => trim($_POST['title']),
    'description' => trim($_POST['description'] ?? ''),
    'event_type' => (int)$_POST['event_type_id'],
    'event_date' => $_POST['event_date'],
    'event_time' => !empty($_POST['event_time']) ? $_POST['event_time'] : null,
    'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
    'end_time' => !empty($_POST['end_time']) ? $_POST['end_time'] : null,
    'location_string' => trim($_POST['location_string'] ?? ''),
    'venue_name' => trim($_POST['venue_name'] ?? ''),
    'venue_address' => trim($_POST['venue_address'] ?? ''),
    'venue_city' => trim($_POST['venue_city'] ?? ''),
    'venue_state' => trim($_POST['venue_state'] ?? ''),
    'venue_country' => trim($_POST['venue_country'] ?? ''),
    'venue_postal_code' => trim($_POST['venue_postal_code'] ?? ''), // Corrected: Used venue_postal_code, previously postal_code
    'guest_count' => !empty($_POST['guest_count']) ? (int)$_POST['guest_count'] : null,
    'budget_min' => !empty($budget_min) ? (float)$budget_min : null,
    'budget_max' => !empty($budget_max) ? (float)$budget_max : null,
    'status' => $_POST['status'] ?? 'planning',
    'special_requirements' => trim($_POST['special_requirements'] ?? ''),
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s')
];

$event_data['services_needed_array'] = [];
if (isset($_POST['services']) && is_array($_POST['services'])) {
    foreach ($_POST['services'] as $service_id) {
        $event_data['services_needed_array'][] = [
            'service_id' => (int)$service_id,
            'priority' => 'medium',
            'budget_allocated' => null,
            'specific_requirements' => null,
            'status' => 'needed'
        ];
    }
}

// --- DEBUG START: Display prepared $event_data ---
echo "<h3>Prepared Event Data (`\$event_data`):</h3>";
echo "<pre>";
print_r($event_data);
echo "</pre>";
// --- DEBUG END ---

try {
    echo "<p>Attempting to create event via Event.class.php->createEvent()...</p>"; // DEBUG
    $event_id = $event->createEvent($event_data);

    if ($event_id) {
        $_SESSION['success_message'] = 'Event created successfully!';
        echo "<p>Event created successfully! Event ID: " . htmlspecialchars($event_id) . "</p>"; // DEBUG
        die("Event creation successful. Check redirect."); // DEBUG: Stop execution here
        // header('Location: ' . BASE_URL . 'public/event.php?id=' . $event_id); // ORIGINAL
        // exit(); // ORIGINAL
    } else {
        $_SESSION['error_message'] = 'Failed to create event. Please try again.';
        $_SESSION['form_data'] = $_POST;
        echo "<p>Event creation returned false/0. Failed.</p>"; // DEBUG
        die("Event creation failed. Check error log or Event.class.php debug output."); // DEBUG: Stop execution here
        // header('Location: ' . BASE_URL . 'public/create_event.php'); // ORIGINAL
        // exit(); // ORIGINAL
    }

} catch (Exception $e) {
    error_log('Event creation error: ' . $e->getMessage()); // This logs to server's error log
    $_SESSION['error_message'] = 'An error occurred while creating the event. Please try again. Error: ' . $e->getMessage();
    $_SESSION['form_data'] = $_POST;
    echo "<h3>Caught Exception:</h3>"; // DEBUG
    echo "<p>Message: " . htmlspecialchars($e->getMessage()) . "</p>"; // DEBUG
    echo "<p>File: " . htmlspecialchars($e->getFile()) . " Line: " . htmlspecialchars($e->getLine()) . "</p>"; // DEBUG
    echo "<pre>Trace: " . htmlspecialchars($e->getTraceAsString()) . "</pre>"; // DEBUG
    die("Exception caught during event creation. See above for details."); // DEBUG: Stop execution
    // header('Location: ' . BASE_URL . 'public/create_event.php'); // ORIGINAL
    // exit(); // ORIGINAL
}
