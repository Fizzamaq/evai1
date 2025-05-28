<?php
session_start();
require_once '../includes/config.php';
require_once '../classes/User.class.php';
require_once '../classes/Notification.class.php';

$user = new User($pdo); // Pass PDO
$notification = new Notification($pdo); // Pass PDO

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

// Mark notification as read
if (isset($_GET['mark_read'])) {
    $notification->markAsRead($_GET['mark_read'], $_SESSION['user_id']);
    header('Location: ' . BASE_URL . 'public/notifications.php'); // Redirect to clean URL
    exit();
}

// Delete notification
if (isset($_GET['delete'])) {
    $notification->deleteNotification($_GET['delete'], $_SESSION['user_id']);
    header('Location: ' . BASE_URL . 'public/notifications.php'); // Redirect to clean URL
    exit();
}

// Get all user notifications
$notifications = $notification->getUserNotifications($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - EventCraftAI</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .notifications-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .notification-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s;
        }

        .notification-card.unread {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
        }

        .notification-content {
            flex: 1;
            margin-right: 20px;
        }

        .notification-time {
            color: #636e72;
            font-size: 0.9em;
        }

        .notification-actions {
            display: flex;
            gap: 10px;
        }

        .mark-read-btn, .delete-btn {
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none; /* Ensure buttons look like buttons, not links */
            display: inline-block; /* Ensure they respect padding */
        }

        .mark-read-btn {
            background: #e1e5e9;
            border: none;
            color: #2d3436;
        }

        .delete-btn {
            background: #ffeef0;
            border: none;
            color: #e74c3c;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="notifications-container">
        <h1>Notifications</h1>

        <?php if (empty($notifications)): ?>
            <div class="empty-state">No notifications found</div>
        <?php else: ?>
            <?php foreach ($notifications as $note): ?>
                <div class="notification-card <?php echo !$note['is_read'] ? 'unread' : ''; ?>">
                    <div class="notification-content">
                        <div class="notification-message"><?php echo htmlspecialchars($note['message']); ?></div>
                        <div class="notification-time">
                            <?php echo date('M j, Y g:i a', strtotime($note['created_at'])); ?>
                            <?php if ($note['related_type']): ?>
                                • <?php echo ucfirst($note['related_type']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="notification-actions">
                        <?php if (!$note['is_read']): ?>
                            <a href="?mark_read=<?php echo $note['id']; ?>" class="mark-read-btn">Mark Read</a>
                        <?php endif; ?>
                        <a href="?delete=<?php echo $note['id']; ?>" class="delete-btn" onclick="return confirm('Are you sure you want to delete this notification?');">Delete</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        // Real-time notifications with WebSocket
        // Note: This WebSocket connection setup requires a separate WebSocket server.
        // It's commented out as it needs a backend WebSocket implementation.
        /*
        const ws = new WebSocket('wss://<?php echo $_SERVER['HTTP_HOST'] ?>/notifications/ws');

        ws.onmessage = function(event) {
            const notification = JSON.parse(event.data);
            if (notification.user_id === <?php echo json_encode($_SESSION['user_id'] ?? null); ?>) {
                // Add new notification to top
                const container = document.querySelector('.notifications-container');
                const newNotification = document.createElement('div');
                newNotification.className = 'notification-card unread';
                newNotification.innerHTML = `
                    <div class="notification-content">
                        <div class="notification-message">${notification.message}</div>
                        <div class="notification-time">
                            ${new Date().toLocaleString()} • <span class="math-inline">\{notification\.type\}
                        </div>
                    </div>
<div class="notification-actions">
<a href="?mark_read={notification.id}" class="mark-read-btn">Mark Read</a>
<a href="?delete=${notification.id}" class="delete-btn">Delete</a>
</div>
`;
container.insertBefore(newNotification, container.firstChild);
}
};
*/
// If WebSocket is not implemented, you might consider polling for new notifications
// periodically, similar to the chat.php example, but with appropriate rate limits.
</script>
</body>
</html>