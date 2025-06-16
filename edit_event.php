<?php
require_once '../includes/config.php';
require_once '../classes/Event.class.php'; // Include Event class
include 'header.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: " . BASE_URL . "public/login.php");
    exit();
}

$eventId = (int)$_GET['id'];
$userId = $_SESSION['user_id'];

$event = new Event($pdo); // Pass PDO
$eventDetails = $event->getEventById($eventId, $userId); // Use getEventById

if (empty($eventDetails)) {
    $_SESSION['event_error'] = "Event not found or you don't have permission to edit it.";
    header("Location: " . BASE_URL . "public/events.php");
    exit();
}

$eventTypes = dbFetchAll("SELECT * FROM event_types"); // Use dbFetchAll
$eventServices = dbFetchAll("SELECT service_id FROM event_service_requirements WHERE event_id = ?", [$eventId]);
$selectedServices = array_column($eventServices, 'service_id');
$allServices = dbFetchAll("SELECT * FROM vendor_services"); // Use dbFetchAll

$error = $_SESSION['event_error'] ?? null;
unset($_SESSION['event_error']);
?>
<div class="event-form-container">
    <h1>Edit Event: <?= htmlspecialchars($eventDetails['title']) ?></h1>

    <?php if ($error): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form action="process_event_update.php" method="post">
        <input type="hidden" name="event_id" value="<?= $eventId ?>">

        <div class="form-group">
            <label>Event Title</label>
            <input type="text" name="title" value="<?= htmlspecialchars($eventDetails['title']) ?>" required>
        </div>

        <div class="form-group">
            <label>Event Type</label>
            <select id="event_type_id" name="event_type_id" required>
                <option value="">Select event type</option>
                <?php foreach ($eventTypes as $type): ?>
                    <option value="<?= htmlspecialchars($type['id']) ?>"
                        <?= ($eventDetails['event_type_id'] == $type['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($type['type_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Start Date</label>
                <input type="date" name="event_date" value="<?= htmlspecialchars($eventDetails['event_date']) ?>" required>
            </div>
            <div class="form-group">
                <label>End Date (optional)</label>
                <input type="date" name="end_date" value="<?= htmlspecialchars($eventDetails['end_date']) ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Start Time (optional)</label>
                <input type="time" name="event_time" value="<?= htmlspecialchars($eventDetails['event_time']) ?>">
            </div>
            <div class="form-group">
                <label>End Time (optional)</label>
                <input type="time" name="end_time" value="<?= htmlspecialchars($eventDetails['end_time']) ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="4"><?= htmlspecialchars($eventDetails['description']) ?></textarea>
        </div>

        <div class="form-group">
            <label for="location_string">Full Event Location String</label>
            <input type="text" id="location_string" name="location_string"
                value="<?= htmlspecialchars($eventDetails['location_string'] ?? '') ?>"
                placeholder="e.g., 123 Main St, City, State or Venue Name">
        </div>

        <div class="form-group">
            <label for="venue_name">Venue Name</label>
            <input type="text" id="venue_name" name="venue_name"
                value="<?= htmlspecialchars($eventDetails['venue_name'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="venue_address">Venue Address</label>
            <input type="text" id="venue_address" name="venue_address"
                value="<?= htmlspecialchars($eventDetails['venue_address'] ?? '') ?>">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="venue_city">City</label>
                <input type="text" id="venue_city" name="venue_city"
                    value="<?= htmlspecialchars($eventDetails['venue_city'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="venue_state">State</label>
                <input type="text" id="venue_state" name="venue_state"
                    value="<?= htmlspecialchars($eventDetails['venue_state'] ?? '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="venue_country">Country</label>
                <input type="text" id="venue_country" name="venue_country"
                    value="<?= htmlspecialchars($eventDetails['venue_country'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="venue_postal_code">Postal Code</label>
                <input type="text" id="venue_postal_code" name="venue_postal_code"
                    value="<?= htmlspecialchars($eventDetails['venue_postal_code'] ?? '') ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="guest_count">Expected Guest Count</label>
                <input type="number" id="guest_count" name="guest_count" min="1"
                    value="<?= htmlspecialchars($eventDetails['guest_count'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="budget_min">Minimum Budget ($)</label>
                <input type="number" id="budget_min" name="budget_min" min="0" step="0.01"
                    value="<?= htmlspecialchars($eventDetails['budget_min'] ?? '') ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="budget_max">Maximum Budget ($)</label>
            <input type="number" id="budget_max" name="budget_max" min="0" step="0.01"
                value="<?= htmlspecialchars($eventDetails['budget_max'] ?? '') ?>">
        </div>

        <h3>Required Services</h3>
        <div class="services-list">
            <?php foreach ($allServices as $service): ?>
            <div class="service-checkbox">
                <input type="checkbox" name="services[]" value="<?= $service['id'] ?>" 
                    id="service_<?= $service['id'] ?>" <?= in_array($service['id'], $selectedServices) ? 'checked' : '' ?>>
                <label for="service_<?= $service['id'] ?>"><?= htmlspecialchars($service['service_name']) ?></label>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="form-group">
            <label for="special_requirements">Special Requirements or Notes</label>
            <textarea id="special_requirements" name="special_requirements" rows="4"><?= htmlspecialchars($eventDetails['special_requirements']) ?></textarea>
        </div>

        <button type="submit" class="btn">Update Event</button>
    </form>
</div>
<?php include 'footer.php'; ?>
