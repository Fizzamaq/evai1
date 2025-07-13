<?php
session_start();
require_once '../includes/config.php';
require_once '../classes/User.class.php';
require_once '../classes/Event.class.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user = new User();
$event = new Event();
$user_data = $user->getUserById($_SESSION['user_id']);
$user_events = $event->getUserEvents($_SESSION['user_id']);

// Handle event deletion
if (isset($_POST['delete_event'])) {
    $event_id = $_POST['event_id'];
    if ($event->deleteEvent($event_id, $_SESSION['user_id'])) {
        $success_message = "Event deleted successfully!";
        $user_events = $event->getUserEvents($_SESSION['user_id']); // Refresh events
    } else {
        $error_message = "Failed to delete event.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Events - EventCraftAI</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .events-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .events-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e1e5e9;
        }
        
        .create-event-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: transform 0.2s;
        }
        
        .create-event-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .event-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .event-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .event-title {
            font-size: 1.4em;
            font-weight: 600;
            color: #2d3436;
            margin-bottom: 10px;
        }
        
        .event-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
            color: #636e72;
            font-size: 0.9em;
        }
        
        .event-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .event-description {
            color: #636e72;
            line-height: 1.5;
            margin-bottom: 20px;
        }
        
        .event-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9em;
            font-weight: 500;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: #74b9ff;
            color: white;
        }
        
        .btn-secondary {
            background: #81ecec;
            color: #2d3436;
        }
        
        .btn-danger {
            background: #e17055;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-1px);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #636e72;
        }
        
        .empty-state h3 {
            color: #2d3436;
            margin-bottom: 15px;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-planning {
            background: #ffeaa7;
            color: #fdcb6e;
        }
        
        .status-active {
            background: #55efc4;
            color: #00b894;
        }
        
        .status-completed {
            background: #fd79a8;
            color: #e84393;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
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
                <a href="create_event.php" class="create-event-btn">+ Create New Event</a>
                <a href="ai_chat.php" class="create-event-btn" style="margin-left: 10px; background: linear-gradient(135deg, #fd79a8 0%, #fdcb6e 100%);">🤖 AI Assistant</a>
            </div>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <?php if (empty($user_events)): ?>
            <div class="empty-state">
                <h3>No Events Yet</h3>
                <p>Start planning your first event!</p>
                <a href="create_event.php" class="create-event-btn" style="display: inline-block; margin-top: 20px;">Create Your First Event</a>
            </div>
        <?php else: ?>
            <div class="events-grid">
                <?php foreach ($user_events as $event_item): ?>
                    <div class="event-card">
                        <div class="event-title"><?php echo htmlspecialchars($event_item['title']); ?></div>
                        
                        <div class="event-meta">
                            <span>📅 <?php echo date('M j, Y', strtotime($event_item['event_date'])); ?></span>
                            <span>👥 <?php echo $event_item['guest_count'] ?: 'TBD'; ?> guests</span>
                            <span>💰 $<?php echo number_format($event_item['budget'] ?: 0); ?></span>
                            <span class="status-badge status-<?php echo strtolower($event_item['status'] ?: 'planning'); ?>">
                                <?php echo $event_item['status'] ?: 'Planning'; ?>
                            </span>
                        </div>
                        
                        <div class="event-description">
                            <?php echo htmlspecialchars(substr($event_item['description'] ?: 'No description available.', 0, 120)) . (strlen($event_item['description'] ?: '') > 120 ? '...' : ''); ?>
                        </div>
                        
                        <div class="event-actions">
                            <a href="event.php?id=<?php echo $event_item['id']; ?>" class="btn btn-primary">View Details</a>
                            <a href="edit_event.php?id=<?php echo $event_item['id']; ?>" class="btn btn-secondary">Edit</a>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this event?');">
                                <input type="hidden" name="event_id" value="<?php echo $event_item['id']; ?>">
                                <button type="submit" name="delete_event" class="btn btn-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script>
        // Add smooth animations
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.event-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'opacity 0.3s, transform 0.3s';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 50);
                }, index * 100);
            });
        });
    </script>
</body>
</html>