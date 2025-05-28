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
    header("Location: " . BASE_URL . "public/events.php");
    exit();
}
?>
<div class="event-details-container">
    <h1><?= htmlspecialchars($eventDetails['title']) ?></h1>

    <div class="event-meta">
        <p><strong>Type:</strong> <?= htmlspecialchars($eventDetails['type_name'] ?? 'N/A') ?></p>
        <p><strong>Start Date:</strong> <?= date('F j, Y', strtotime($eventDetails['event_date'])) ?></p>
        <?php if ($eventDetails['event_time']): ?>
        <p><strong>Start Time:</strong> <?= date('g:i A', strtotime($eventDetails['event_time'])) ?></p>
        <?php endif; ?>
        <?php if ($eventDetails['end_date']): ?>
        <p><strong>End Date:</strong> <?= date('F j, Y', strtotime($eventDetails['end_date'])) ?></p>
        <?php endif; ?>
        <?php if ($eventDetails['end_time']): ?>
        <p><strong>End Time:</strong> <?= date('g:i A', strtotime($eventDetails['end_time'])) ?></p>
        <?php endif; ?>
        <p><strong>Status:</strong> <?= ucfirst(htmlspecialchars($eventDetails['status'])) ?></p>
    </div>

    <?php if ($eventDetails['description']): ?>
    <div class="event-description">
        <h3>Description</h3>
        <p><?= nl2br(htmlspecialchars($eventDetails['description'])) ?></p>
    </div>
    <?php endif; ?>

    <div class="event-location-details">
        <h3>Location Details</h3>
        <?php if ($eventDetails['location_string']): ?>
            <p><strong>Location:</strong> <?= htmlspecialchars($eventDetails['location_string']) ?></p>
        <?php endif; ?>
        <?php if ($eventDetails['venue_name']): ?>
            <p><strong>Venue Name:</strong> <?= htmlspecialchars($eventDetails['venue_name']) ?></p>
        <?php endif; ?>
        <?php if ($eventDetails['venue_address']): ?>
            <p><strong>Address:</strong> <?= htmlspecialchars($eventDetails['venue_address']) ?>, <?= htmlspecialchars($eventDetails['venue_city']) ?>, <?= htmlspecialchars($eventDetails['venue_state']) ?>, <?= htmlspecialchars($eventDetails['venue_postal_code']) ?>, <?= htmlspecialchars($eventDetails['venue_country']) ?></p>
        <?php endif; ?>
    </div>

    <div class="event-guest-budget">
        <h3>Guests & Budget</h3>
        <p><strong>Expected Guests:</strong> <?= htmlspecialchars($eventDetails['guest_count'] ?? 'TBD') ?></p>
        <p><strong>Budget Range:</strong> $<?= number_format($eventDetails['budget_min'] ?? 0, 2) ?> - $<?= number_format($eventDetails['budget_max'] ?? 0, 2) ?></p>
    </div>

    <?php
    // Fetch required services for this event
    $requiredServices = dbFetchAll("
        SELECT vs.service_name, esr.priority, esr.budget_allocated, esr.specific_requirements
        FROM event_service_requirements esr
        JOIN vendor_services vs ON esr.service_id = vs.id
        WHERE esr.event_id = ?
    ", [$eventId]);
    if (!empty($requiredServices)):
    ?>
    <div class="event-services">
        <h3>Required Services</h3>
        <ul>
            <?php foreach ($requiredServices as $service): ?>
                <li>
                    <strong><?= htmlspecialchars($service['service_name']) ?></strong> (Priority: <?= ucfirst(htmlspecialchars($service['priority'])) ?>)
                    <?php if ($service['budget_allocated']): ?> - Allocated: $<?= number_format($service['budget_allocated'], 2) ?><?php endif; ?>
                    <?php if ($service['specific_requirements']): ?> <br> <em>"<?= htmlspecialchars($service['specific_requirements']) ?>"</em><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if ($eventDetails['special_requirements']): ?>
    <div class="event-special-requirements">
        <h3>Special Requirements</h3>
        <p><?= nl2br(htmlspecialchars($eventDetails['special_requirements'])) ?></p>
    </div>
    <?php endif; ?>

    <div class="event-actions">
        <a href="edit_event.php?id=<?= $eventId ?>" class="btn btn-primary">Edit Event</a>
        <a href="ai_chat.php?event_id=<?= $eventId ?>" class="btn btn-secondary">Get AI Recommendations</a>
        <a href="<?= BASE_URL ?>public/chat.php?event_id=<?= $eventId ?>&user_id=<?= $_SESSION['user_id'] ?>&vendor_id=" class="btn">Start Chat</a>
    </div>
</div>
<?php include 'footer.php'; ?>