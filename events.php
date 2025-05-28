<?php
// public/events.php
// session_start(); // Removed: handled by config.php
require_once '../includes/config.php';
require_once '../classes/User.class.php';
require_once '../classes/Event.class.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

// Pass PDO to class constructors
$user = new User($pdo); 
$event = new Event($pdo); 

$user_data = $user->getUserById($_SESSION['user_id']);
$user_events = $event->getUserEvents($_SESSION['user_id']);

// Handle event deletion
if (isset($_POST['delete_event'])) {
    $event_id = $_POST['event_id'];
    if ($event->deleteEvent($event_id, $_SESSION['user_id'])) {
        $_SESSION['success_message'] = "Event deleted successfully!"; // Use session for messages
    } else {
        $_SESSION['error_message'] = "Failed to delete event."; // Use session for messages
    }
    // Redirect to clear POST data and show message
    header('Location: ' . BASE_URL . 'public/events.php');
    exit();
}

// Retrieve messages from session if any
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']); // Clear messages after display
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Events - EventCraftAI</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/events.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="events-container">
        <div class="events-header">
            <div>
                <h1>My Events</h1>
                <p>Manage and track all your events</p>
            </div>
            <div>
                <a href="create_event.php" class="btn btn-primary create-event-btn">+ Create New Event</a>
                <a href="ai_chat.php" class="btn btn-secondary create-event-btn">AI Assistant</a>
            </div>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="alert success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <?php if (empty($user_events)): ?>
            <div class="empty-state">
                <h3>No Events Yet</h3>
                <p>Start planning your first event!</p>
                <a href="create_event.php" class="btn btn-primary create-event-btn" style="display: inline-block; margin-top: 20px;">Create Your First Event</a>
            </div>
        <?php else: ?>
            <div class="events-grid">
                <?php foreach ($user_events as $event_item): ?>
                    <div class="event-card">
                        <div class="event-title"><?php echo htmlspecialchars($event_item['title']); ?></div>
                        
                        <div class="event-meta">
                            <span><i class="fas fa-calendar-alt"></i> <?php echo date('M j, Y', strtotime($event_item['event_date'])); ?></span>
                            <span><i class="fas fa-users"></i> <?php echo $event_item['guest_count'] ?: 'TBD'; ?> guests</span>
                            <span><i class="fas fa-dollar-sign"></i> $<?php echo number_format($event_item['budget_min'] ?? 0, 0); ?> - $<?php echo number_format($event_item['budget_max'] ?? 0, 0); ?></span>
                            <span class="status-badge status-<?php echo strtolower($event_item['status'] ?: 'planning'); ?>">
                                <?php echo $event_item['status'] ?: 'Planning'; ?>
                            </span>
                        </div>
                        
                        <div class="event-description">
                            <?php echo htmlspecialchars(substr($event_item['description'] ?: 'No description available.', 0, 120)) . (strlen($event_item['description'] ?: '') > 120 ? '...' : ''); ?>
                        </div>
                        
                        <div class="event-actions">
                            <a href="event.php?id=<?php echo $event_item['id']; ?>" class="btn btn-primary btn-sm">View Details</a>
                            <a href="edit_event.php?id=<?php echo $event_item['id']; ?>" class="btn btn-secondary btn-sm">Edit</a>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this event?');">
                                <input type="hidden" name="event_id" value="<?php echo $event_item['id']; ?>">
                                <button type="submit" name="delete_event" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script>
        // Add smooth animations (if desired, this is a basic example)
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.event-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'opacity 0.3s ease-out, transform 0.3s ease-out';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 50); // Stagger animation
            });
        });
    </script>
</body>
</html>
