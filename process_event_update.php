<?php
require_once '../includes/config.php';
require_once '../classes/Event.class.php'; // Include Event class
require_once '../classes/User.class.php'; // For user validation

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "public/login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$eventId = (int)$_POST['event_id'];

try {
    $event = new Event($pdo); // Pass PDO
    $userEvent = $event->getEventById($eventId, $userId); // Use getEventById to verify ownership

    if (empty($userEvent)) {
        throw new Exception("Event not found or access denied.");
    }

    // Collect and validate data from form
    $eventData = [
        'title' => trim($_POST['title']),
        'event_type' => (int)$_POST['event_type_id'], // Now using ID
        'description' => trim($_POST['description'] ?? ''),
        'event_date' => $_POST['event_date'],
        'end_date' => $_POST['end_date'] ?? null,
        'event_time' => $_POST['event_time'] ?? null,
        'end_time' => $_POST['end_time'] ?? null,
        'location_string_from_form' => trim($_POST['location_string'] ?? ''), // New raw string input
        'venue_name' => trim($_POST['venue_name'] ?? ''),
        'venue_address' => trim($_POST['venue_address'] ?? ''),
        'venue_city' => trim($_POST['venue_city'] ?? ''),
        'venue_state' => trim($_POST['venue_state'] ?? ''),
        'venue_country' => trim($_POST['venue_country'] ?? ''),
        'venue_postal_code' => trim($_POST['venue_postal_code'] ?? ''),
        'guest_count' => !empty($_POST['guest_count']) ? (int)$_POST['guest_count'] : null,
        'budget_min' => !empty($_POST['budget_min']) ? (float)$_POST['budget_min'] : null,
        'budget_max' => !empty($_POST['budget_max']) ? (float)$_POST['budget_max'] : null,
        'status' => $_POST['status'] ?? $userEvent['status'], // Retain existing status if not provided
        'special_requirements' => trim($_POST['special_requirements'] ?? ''),
        'services_needed_array' => $_POST['services'] ?? [] // Expects an array of service IDs
    ];

    // Perform the update
    if ($event->updateEvent($eventId, $eventData, $userId)) {
        $_SESSION['event_success'] = "Event updated successfully!";
        header("Location: " . BASE_URL . "public/event.php?id=" . $eventId);
        exit();
    } else {
        throw new Exception("Failed to update event. Database error or no changes made.");
    }

} catch (Exception $e) {
    $_SESSION['event_error'] = $e->getMessage();
    header("Location: " . BASE_URL . "public/edit_event.php?id=" . $eventId);
    exit();
}