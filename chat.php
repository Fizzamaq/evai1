<?php
// public/chat.php
session_start();
require_once '../includes/config.php';
require_once '../classes/User.class.php';
require_once '../classes/Chat.class.php';
require_once '../classes/Event.class.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

$user = new User($pdo); // Pass PDO
$chat = new Chat($pdo); // Pass PDO
$event = new Event($pdo); // Pass PDO

$user_data = $user->getUserById($_SESSION['user_id']);
$conversation_id = $_GET['conversation_id'] ?? null;
$event_id = $_GET['event_id'] ?? null;
$vendor_id = $_GET['vendor_id'] ?? null;

// Handle new message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $message = trim($_POST['message']);

    // Validate CSRF token (if implemented)
    // if (!verifyCSRFToken($_POST['csrf_token'])) {
    //     error_log("CSRF token mismatch on chat message send.");
    //     // Handle error, e.g., redirect or show message
    // }

    if (!empty($message)) {
        // If this is a new conversation (no conversation_id but has event_id and vendor_id)
        if (!$conversation_id && $event_id && $vendor_id) {
            $conversation_id = $chat->startConversation($event_id, $_SESSION['user_id'], $vendor_id);
        }

        if ($conversation_id) {
            $chat->sendMessage($conversation_id, $_SESSION['user_id'], $message);
            // For AJAX-based message sending, avoid full page redirect.
            // If it's a non-AJAX POST, redirect to prevent form re-submission on refresh
            // header("Location: " . BASE_URL . "public/chat.php?conversation_id=" . urlencode($conversation_id));
            // exit();
            // For AJAX:
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Message sent.']);
            exit();
        } else {
            error_log("Failed to send message: No conversation ID or invalid parameters for starting new conversation.");
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Failed to start/send message to conversation.']);
            exit();
        }
    } else {
         header('Content-Type: application/json');
         echo json_encode(['success' => false, 'error' => 'Message cannot be empty.']);
         exit();
    }
}

// Get current conversation details
$current_conversation = null;
$messages = [];
$other_party = null;

if ($conversation_id) {
    // Mark messages as read when opening conversation
    $chat->markMessagesAsRead($conversation_id, $_SESSION['user_id']);

    // Get conversation details
    try {
        // Updated joins to correctly fetch profile_image from user_profiles table
        $stmt = $pdo->prepare("
            SELECT cc.*, 
                   e.title as event_title,
                   CASE 
                     WHEN cc.user_id = :current_user_id THEN vp.business_name
                     ELSE CONCAT(u.first_name, ' ', u.last_name)
                   END as other_party_name,
                   CASE 
                     WHEN cc.user_id = :current_user_id THEN up_vendor.profile_image
                     ELSE up_user.profile_image
                   END as other_party_image
            FROM chat_conversations cc
            JOIN events e ON cc.event_id = e.id
            LEFT JOIN users u ON cc.vendor_id = u.id
            LEFT JOIN user_profiles up_vendor ON u.id = up_vendor.user_id -- Join for vendor's profile image
            LEFT JOIN users u2 ON cc.user_id = u2.id
            LEFT JOIN user_profiles up_user ON u2.id = up_user.user_id -- Join for user's profile image
            WHERE cc.id = :conversation_id
        ");
        $stmt->execute([
            ':current_user_id' => $_SESSION['user_id'], 
            ':conversation_id' => $conversation_id
        ]);
        $current_conversation = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($current_conversation) {
            $other_party = [
                'id' => ($current_conversation['user_id'] == $_SESSION['user_id']) 
                    ? $current_conversation['vendor_id'] 
                    : $current_conversation['user_id'],
                'name' => $current_conversation['other_party_name'],
                'image' => $current_conversation['other_party_image']
            ];

            $messages = $chat->getMessages($conversation_id, 100);
            $messages = array_reverse($messages); // Show oldest first
        }
    } catch (PDOException $e) {
        error_log("Get conversation error: " . $e->getMessage());
    }
}

// Get user's conversations
$conversations = $chat->getUserConversations($_SESSION['user_id']);
$unread_count = $chat->getUnreadCount($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - EventCraftAI</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        /* ... (existing chat.php inline styles) ... */
        .chat-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 20px;
            height: calc(100vh - 120px);
        }

        .conversations-sidebar {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .conversations-header {
            padding: 20px;
            border-bottom: 2px solid #e1e5e9;
        }

        .conversations-list {
            flex: 1;
            overflow-y: auto;
        }

        .conversation-item {
            padding: 15px 20px;
            border-bottom: 1px solid #e1e5e9;
            cursor: pointer;
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .conversation-item:hover, .conversation-item.active {
            background-color: #f8f9fa;
        }

        .conversation-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-size: cover;
            background-position: center;
            background-color: #e1e5e9;
            flex-shrink: 0;
        }

        .conversation-details {
            flex: 1;
            min-width: 0;
        }

        .conversation-title {
            font-weight: 600;
            color: #2d3436;
            margin-bottom: 5px;
            display: flex;
            justify-content: space-between;
        }

        .conversation-preview {
            color: #636e72;
            font-size: 0.9em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conversation-time {
            font-size: 0.8em;
            color: #b2bec3;
        }

        .unread-badge {
            background: #e17055;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7em;
            font-weight: bold;
        }

        .chat-area {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
        }

        .chat-header {
            padding: 20px;
            border-bottom: 2px solid #e1e5e9;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .chat-header-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-size: cover;
            background-position: center;
            background-color: #e1e5e9;
        }

        .chat-header-info {
            flex: 1;
        }

        .chat-header-title {
            font-weight: 600;
        }
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .message {
            max-width: 80%;
            padding: 15px;
            border-radius: 15px;
            position: relative;
        }

        .message-outgoing {
            background: #667eea;
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 5px;
        }

        .message-incoming {
            background: #f8f9fa;
            color: #2d3436;
            align-self: flex-start;
            border-bottom-left-radius: 5px;
        }

        .message-time {
            font-size: 0.8em;
            color: rgba(255,255,255,0.7);
            margin-top: 5px;
            display: block;
            text-align: right;
        }

        .message-incoming .message-time {
            color: #636e72;
        }

        .chat-input {
            padding: 20px;
            border-top: 2px solid #e1e5e9;
            background: #f8f9fa;
        }

        .message-form {
            display: flex;
            gap: 10px;
        }

        .message-input {
            flex: 1;
            padding: 12px 20px;
            border: 2px solid #e1e5e9;
            border-radius: 25px;
            font-size: 16px;
            resize: none;
            height: 50px;
        }

        .send-button {
            background: #667eea;
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .send-button:hover {
            background: #764ba2;
        }

        .no-conversation {
            text-align: center;
            padding: 40px;
            color: #636e72;
        }

        @media (max-width: 768px) {
            .chat-container {
                grid-template-columns: 1fr;
                height: auto;
            }

            .conversations-sidebar {
                height: 400px;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="chat-container">
        <div class="conversations-sidebar">
            <div class="conversations-header">
                <h2>Messages <?php if ($unread_count > 0): ?><span class="unread-badge"><?php echo $unread_count; ?></span><?php endif; ?></h2>
            </div>
            <div class="conversations-list">
                <?php if (empty($conversations)): ?>
                    <div class="no-conversation">No conversations yet</div>
                <?php else: ?>
                    <?php foreach ($conversations as $conv): ?>
                        <div class="conversation-item <?php echo ($conv['id'] == $conversation_id) ? 'active' : ''; ?>" 
                             onclick="window.location.href='<?= BASE_URL ?>public/chat.php?conversation_id=<?php echo $conv['id']; ?>'">
                            <div class="conversation-avatar" style="background-image: url('<?php echo htmlspecialchars($conv['other_party_image'] ? BASE_URL . 'assets/uploads/users/' . $conv['other_party_image'] : BASE_URL . 'assets/images/default-avatar.jpg'); ?>')"></div>
                            <div class="conversation-details">
                                <div class="conversation-title">
                                    <span><?php echo htmlspecialchars($conv['other_party_name']); ?></span>
                                    <?php if ($conv['unread_count'] > 0): ?>
                                        <span class="unread-badge"><?php echo $conv['unread_count']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="conversation-preview">
                                    <?php echo htmlspecialchars(substr($conv['last_message'] ?? 'No messages yet', 0, 30)); ?>
                                </div>
                                <div class="conversation-time">
                                    <?php echo date('M j, g:i a', strtotime($conv['last_message_time'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="chat-area">
            <?php if ($current_conversation): ?>
                <div class="chat-header">
                    <div class="chat-header-avatar" style="background-image: url('<?php echo htmlspecialchars($other_party['image'] ? BASE_URL . 'assets/uploads/users/' . $other_party['image'] : BASE_URL . 'assets/images/default-avatar.jpg'); ?>')"></div>
                    <div class="chat-header-info">
                        <div class="chat-header-title"><?php echo htmlspecialchars($other_party['name']); ?></div>
                        <div class="chat-header-subtitle"><?php echo htmlspecialchars($current_conversation['event_title']); ?></div>
                    </div>
                </div>
                <div class="chat-messages" id="messages-container">
                    <?php foreach ($messages as $message): ?>
                        <div class="message <?php echo ($message['sender_id'] == $_SESSION['user_id']) ? 'message-outgoing' : 'message-incoming'; ?>" data-id="<?= $message['id'] ?>">
                            <div class="message-content"><?php echo htmlspecialchars($message['message_content']); ?></div>
                            <span class="message-time">
                                <?php echo date('g:i a', strtotime($message['created_at'])); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="chat-input">
                    <form class="message-form" method="POST">
                        <textarea 
                            class="message-input" 
                            name="message" 
                            placeholder="Type your message..." 
                            id="message-input"
                            required
                        ></textarea>
                        <button type="submit" name="send_message" class="send-button">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/>
                            </svg>
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="no-conversation">
                    <h3>Select a conversation or start a new one</h3>
                    <p>Choose from your existing conversations or initiate a new chat from an event page</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        // Auto-scroll to bottom of messages
        function scrollToBottom() {
            const container = document.getElementById('messages-container');
            if (container) container.scrollTop = container.scrollHeight;
        }

        // Send message with AJAX
        document.querySelector('.message-form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const form = this;
            const messageInput = document.getElementById('message-input');
            const message = messageInput.value.trim();

            if (message) {
                fetch('<?= BASE_URL ?>public/chat.php?<?php echo $_SERVER['QUERY_STRING']; ?>', { // Use BASE_URL
                    method: 'POST',
                    body: new URLSearchParams(new FormData(form)),
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    }
                }).then(response => response.json()) // Expect JSON response
                .then(data => {
                    if (data.success) {
                        messageInput.value = '';
                        const messagesContainer = document.getElementById('messages-container');
                        if (messagesContainer) {
                            const tempMsg = document.createElement('div');
                            tempMsg.className = 'message message-outgoing';
                            tempMsg.innerHTML = `
                                <div class="message-content">${message}</div>
                                <span class="message-time">Just now</span>
                            `;
                            messagesContainer.appendChild(tempMsg);
                            scrollToBottom();
                        }
                    } else {
                        alert('Failed to send message: ' + (data.error || 'Unknown error.'));
                        console.error('Message send failed:', data.error);
                    }
                }).catch(error => {
                    console.error('Error sending message:', error);
                    alert('An error occurred while sending your message.');
                });
            }
        });

        // Poll for new messages every 5 seconds (consider WebSockets for real-time)
        setInterval(() => {
            if (<?php echo $conversation_id ? 'true' : 'false'; ?>) {
                fetch(`<?= BASE_URL ?>public/chat.php?conversation_id=<?php echo $conversation_id; ?>&ajax=1`) // Use BASE_URL
                    .then(response => response.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        // Select only incoming messages that are not already present
                        const newIncomingMessages = doc.querySelectorAll('.message-incoming');
                        const messagesContainer = document.getElementById('messages-container');
                        if (messagesContainer) {
                            let addedNew = false;
                            newIncomingMessages.forEach(newMsg => {
                                const msgId = newMsg.dataset.id;
                                if (!document.querySelector(`#messages-container .message[data-id="${msgId}"]`)) {
                                    messagesContainer.appendChild(newMsg.cloneNode(true));
                                    addedNew = true;
                                }
                            });
                            if (addedNew) {
                                scrollToBottom();
                            }
                        }
                    }).catch(error => console.error('Error polling for messages:', error));
            }
        }, 5000);

        // Initial scroll to bottom
        scrollToBottom();

        // Enter key handling (Shift+Enter for new line)
        document.getElementById('message-input')?.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                document.querySelector('.send-button')?.click();
            }
        });
    </script>
</body>
</html>