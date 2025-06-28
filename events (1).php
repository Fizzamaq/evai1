<?php
// public/admin/events.php
session_start();
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../classes/User.class.php';
require_once __DIR__ . '/../../classes/Event.class.php';

$user_obj = new User($pdo);
$event_obj = new Event($pdo);

// Admin authentication
if (!$user_obj->isAdmin($_SESSION['user_id'] ?? null)) {
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

// Handle actions (delete/change status)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $event_id_target = $_POST['event_id'] ?? null;
    $new_status = $_POST['new_status'] ?? null;

    if ($event_id_target && is_numeric($event_id_target)) {
        try {
            if ($action === 'update_status' && $new_status) {
                $event_obj->updateEventStatus($event_id_target, $new_status); // This method is in Event.class.php
                $_SESSION['success_message'] = "Event status updated successfully.";
            } elseif ($action === 'delete') {
                $event_obj->deleteEventSoft($event_id_target); // This method is in Event.class.php
                $_SESSION['success_message'] = "Event deleted successfully.";
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Action failed: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = "Invalid event ID.";
    }
    header('Location: ' . BASE_URL . 'public/admin/events.php');
    exit();
}

// Fetch all events (including AI-generated ones)
$all_events = $event_obj->getAllEvents(); // This method is in Event.class.php

include '../../includes/admin_header.php';
?>

<div class="admin-container">
    <h1>Manage Events</h1>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert success"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert error"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Date</th>
                    <th>Guests</th>
                    <th>Budget</th>
                    <th>Status</th>
                    <th>AI-Planned</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($all_events)): ?>
                    <?php foreach ($all_events as $event): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($event['id']); ?></td>
                            <td><?php echo htmlspecialchars($event['title']); ?></td>
                            <td><?php echo htmlspecialchars($event['type_name']); ?></td>
                            <td><?php echo htmlspecialchars($event['event_date']); ?></td>
                            <td><?php echo htmlspecialchars($event['guest_count'] ?: 'N/A'); ?></td>
                            <td>$<?php echo number_format($event['budget_min'] ?? 0, 0); ?> - $<?php echo number_format($event['budget_max'] ?? 0, 0); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($event['status'])); ?></td>
                            <td><?php echo $event['ai_preferences'] ? 'Yes' : 'No'; ?></td>
                            <td>
                                <a href="<?= BASE_URL ?>public/event.php?id=<?php echo htmlspecialchars($event['id']); ?>" class="btn btn-sm btn-info">View</a>
                                <form method="POST" style="display: inline-block;">
                                    <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($event['id']); ?>">
                                    <select name="new_status" onchange="this.form.submit()" class="btn btn-sm">
                                        <option value="">Change Status</option>
                                        <option value="planning" <?php echo ($event['status'] === 'planning') ? 'selected' : ''; ?>>Planning</option>
                                        <option value="active" <?php echo ($event['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                        <option value="completed" <?php echo ($event['status'] === 'completed') ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo ($event['status'] === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                    <input type="hidden" name="action" value="update_status">
                                </form>
                                <form method="POST" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete this event? This will mark it as deleted.');">
                                    <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($event['id']); ?>">
                                    <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9">No events found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'admin_footer.php'; ?>