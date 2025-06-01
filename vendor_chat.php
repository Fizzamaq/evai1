<?php
// TEMPORARY: Enable full error reporting for debugging.
// This helps identify any hidden PHP errors that might stop page rendering.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// public/vendor_chat.php
session_start();
require_once '../includes/config.php';
require_once '../classes/User.class.php';
require_once '../classes/Chat.class.php';
require_once '../classes/Event.class.php'; // Required for event_title in conversations
require_once '../classes/Vendor.class.php'; // For vendor-specific access checks
require_once '../includes/auth.php'; // Required for generateCSRFToken and verifyCSRFToken

// --- Vendor Access Check ---
// This method ensures the user is logged in, is a vendor,
// and sets $_SESSION['vendor_id'] if successful. It redirects otherwise.
$vendor = new Vendor($pdo);
$vendor->verifyVendorAccess();

// Instantiate the User class to fetch user data
$user = new User($pdo);

// Instantiate Chat and Event classes
$chat = new Chat($pdo);
$event = new Event($pdo);

// Get the conversation ID from the URL parameter, if available
$conversation_id = $_GET['conversation_id'] ?? null;

// Get logged-in user's data (the vendor's user data)
$user_data = $user->getUserById($_SESSION['user_id']);

// Generate CSRF token for the message submission form
$csrf_token = generateCSRFToken();

// --- Handle New Message (POST request) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    // Validate CSRF token to prevent cross-site request forgery attacks
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        error_log("CSRF token mismatch on vendor chat message send for user " . ($_SESSION['user_id'] ?? 'N/A'));
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid request. Please refresh the page and try again.']);
        exit();
    }

    $message = trim($_POST['message']);

    // Ensure the message content is not empty
    if (!empty($message)) {
        // Vendors should always be responding within an existing conversation.
        // If no conversation ID is provided, it indicates an issue.
        if ($conversation_id) {
            $chat->sendMessage($conversation_id, $_SESSION['user_id'], $message); // Sender is the vendor's user_id
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Message sent.']);
            exit();
        } else {
            error_log("Failed to send message: No conversation ID provided for vendor response.");
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Failed to send message. Conversation not identified.']);
            exit();
        }
    } else {
         // Return error if message is empty
         header('Content-Type: application/json');
         echo json_encode(['success' => false, 'error' => 'Message cannot be empty.']);
         exit();
    }
}

// --- Fetch Current Conversation Details and Messages ---
$current_conversation = null;
$messages = [];
$other_party = null; // Represents the client/customer in this vendor view

if ($conversation_id) {
    // Mark messages as read for the current vendor when they open the conversation
    $chat->markMessagesAsRead($conversation_id, $_SESSION['user_id']);

    try {
        // Fetch conversation details including the other party's name and image, and event title
        $stmt = $pdo->prepare("
            SELECT cc.*,
                   e.title as event_title,
                   CASE
                     WHEN cc.vendor_id = :current_vendor_user_id THEN CONCAT(u.first_name, ' ', u.last_name)
                     ELSE vp.business_name
                   END as other_party_name,
                   CASE
                     WHEN cc.vendor_id = :current_vendor_user_id THEN up_user.profile_image
                     ELSE up_vendor.profile_image
                   END as other_party_image
            FROM chat_conversations cc
            LEFT JOIN events e ON cc.event_id = e.id
            LEFT JOIN users u ON cc.user_id = u.id -- Join to get customer's user data
            LEFT JOIN user_profiles up_user ON u.id = up_user.user_id -- Join for customer's profile image
            LEFT JOIN users u2 ON cc.vendor_id = u2.id -- Join to get vendor's user data (self)
            LEFT JOIN vendor_profiles vp ON u2.id = vp.user_id -- Join to get vendor's business name (self)
            LEFT JOIN user_profiles up_vendor ON u2.id = up_vendor.user_id -- Join for vendor's profile image (self)
            WHERE cc.id = :conversation_id
            AND (cc.vendor_id = :vendor_user_id_check1 OR cc.user_id = :vendor_user_id_check2)
        ");
        $stmt->execute([
            ':current_vendor_user_id' => $_SESSION['user_id'],
            ':conversation_id' => $conversation_id,
            ':vendor_user_id_check1' => $_SESSION['user_id'],
            ':vendor_user_id_check2' => $_SESSION['user_id']
        ]);
        $current_conversation = $stmt->fetch(PDO::FETCH_ASSOC);

        // If a conversation is found, populate other_party details and fetch messages
        if ($current_conversation) {
            $other_party = [
                'id' => ($current_conversation['vendor_id'] == $_SESSION['user_id'])
                    ? $current_conversation['user_id'] // If current user is the vendor, the other party is the customer
                    : $current_conversation['vendor_id'], // This case should theoretically not happen on vendor_chat.php
                'name' => htmlspecialchars($current_conversation['other_party_name'] ?? 'Unknown User'),
                'image' => htmlspecialchars($current_conversation['other_party_image'] ?? '')
            ];

            $messages = $chat->getMessages($conversation_id, 100);
            $messages = array_reverse($messages); // Display oldest messages first
        }
    } catch (PDOException $e) {
        error_log("Get conversation details error (vendor_chat.php): " . $e->getMessage());
        // Handle error gracefully, e.g., display a user-friendly message
        $_SESSION['error_message'] = "Could not load conversation details. Please try again.";
        $current_conversation = null; // Ensure no partial display
    }
}

// --- Fetch Vendor's Conversations for the Sidebar ---
// This list is used to populate the left-hand sidebar with all the vendor's chats.
$conversations = $chat->getUserConversations($_SESSION['user_id']);
$unread_count = $chat->getUnreadCount($_SESSION['user_id']);
?>

<?php include 'header.php'; ?>

    <link rel="stylesheet" href="<?= ASSETS_PATH ?>css/chat.css">

    <div class="chat-container">
        <div class="conversations-sidebar">
            <div class="conversations-header">
                <h2>Messages <?php if ($unread_count > 0): ?><span class="unread-badge"><?php echo $unread_count; ?></span><?php endif; ?></h2>
            </div>
            <div class="conversations-list">
                <?php if (empty($conversations)): ?>
                    <div class="no-conversation">No conversations yet</div>
                <?php else: ?>
                    <?php foreach ($conversations as $conv):
                        // Ensure other_party_image path is handled safely
                        $display_image = !empty($conv['other_party_image']) ? BASE_URL . 'assets/uploads/users/' . htmlspecialchars($conv['other_party_image']) : BASE_URL . 'assets/images/default-avatar.jpg';
                        // Safely get last message preview, ensuring it's not null before substr
                        $last_message_preview = htmlspecialchars(substr($conv['last_message'] ?? 'No messages yet', 0, 30));
                        // Safely format last message time, handle null/invalid dates
                        $last_message_time_formatted = !empty($conv['last_message_time']) ? date('M j, g:i a', strtotime($conv['last_message_time'])) : 'N/A';
                    ?>
                        <div class="conversation-item <?php echo ($conv['id'] == $conversation_id) ? 'active' : ''; ?>"
                             onclick="window.location.href='<?= BASE_URL ?>public/vendor_chat.php?conversation_id=<?php echo $conv['id']; ?>'">
                            <div class="conversation-avatar" style="background-image: url('<?= $display_image ?>')"></div>
                            <div class="conversation-details">
                                <div class="conversation-title">
                                    <span><?php echo htmlspecialchars($conv['other_party_name'] ?? 'Unknown User'); ?></span>
                                    <?php if ($conv['unread_count'] > 0): ?>
                                        <span class="unread-badge"><?php echo $conv['unread_count']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="conversation-preview">
                                    <?php echo $last_message_preview; ?>
                                </div>
                                <div class="conversation-time">
                                    <?php echo $last_message_time_formatted; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="chat-area">
            <?php if ($current_conversation): /* A conversation is loaded and ready for display */ ?>
                <div class="chat-header">
                    <div class="chat-header-avatar" style="background-image: url('<?php echo htmlspecialchars($other_party['image'] ? BASE_URL . 'assets/uploads/users/' . $other_party['image'] : BASE_URL . 'assets/images/default-avatar.jpg'); ?>')"></div>
                    <div class="chat-header-info">
                        <div class="chat-header-title"><?php echo htmlspecialchars($other_party['name']); ?></div>
                        <div class="chat-header-subtitle">
                            <?php
                            // Display event title if available, otherwise "General Chat"
                            if (isset($current_conversation['event_title']) && $current_conversation['event_title'] !== null) {
                                echo htmlspecialchars($current_conversation['event_title']);
                            } else {
                                echo 'General Chat';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <div class="chat-messages" id="messages-container">
                    <?php foreach ($messages as $message):
                        // Ensure message content is safely displayed
                        $message_content_display = nl2br(htmlspecialchars($message['message_content'] ?? ''));
                        // Safely format message creation time
                        $message_time_display = !empty($message['created_at']) ? date('g:i a', strtotime($message['created_at'])) : 'N/A';
                    ?>
                        <div class="message <?php echo ($message['sender_id'] == $_SESSION['user_id']) ? 'message-outgoing' : 'message-incoming'; ?>" data-id="<?= htmlspecialchars($message['id']) ?>">
                            <div class="message-content"><?php echo $message_content_display; ?></div>
                            <span class="message-time">
                                <?php echo $message_time_display; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="chat-input">
                    <form class="message-form" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
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
            <?php else: /* No conversation selected or loaded */ ?>
                <div class="no-conversation">
                    <h3>Select a conversation to view messages</h3>
                    <p>Your conversations will appear on the left sidebar. If you have no conversations, start one from a booking or a vendor's profile.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php include 'footer.php'; ?>

    <script>
        // Auto-scroll to bottom of messages container
        function scrollToBottom() {
            const container = document.getElementById('messages-container');
            if (container) container.scrollTop = container.scrollHeight;
        }

        // Send message with AJAX
        document.querySelector('.message-form')?.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent default form submission
            const form = this;
            const messageInput = document.getElementById('message-input');
            const message = messageInput.value.trim();

            if (message) {
                let postUrl = '<?= BASE_URL ?>public/vendor_chat.php';
                const currentConversationId = '<?= htmlspecialchars($conversation_id) ?>';
                if (currentConversationId) {
                    postUrl += `?conversation_id=${currentConversationId}`; // Append conversation ID for existing chats
                }

                // Send message via Fetch API
                fetch(postUrl, {
                    method: 'POST',
                    body: new URLSearchParams(new FormData(form)), // Format data as URL-encoded
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    }
                })
                .then(response => {
                    // Check if response is OK (200 status)
                    if (!response.ok) {
                        return response.text().then(text => { // Get text for detailed error logging
                            console.error('HTTP Error:', response.status, response.statusText, text);
                            throw new Error('Network response was not ok: ' + response.statusText + ' - ' + text);
                        });
                    }
                    return response.json(); // Parse JSON response
                })
                .then(data => {
                    if (data.success) {
                        messageInput.value = ''; // Clear input field
                        // Add the sent message to the display immediately for responsiveness
                        const messagesContainer = document.getElementById('messages-container');
                        if (messagesContainer) {
                            const tempMsg = document.createElement('div');
                            tempMsg.className = 'message message-outgoing';
                            tempMsg.innerHTML = `
                                <div class="message-content">${message}</div>
                                <span class="message-time">Just now</span>
                            `;
                            messagesContainer.appendChild(tempMsg);
                            scrollToBottom(); // Scroll to the new message
                        }
                    } else {
                        // Display error message from server
                        alert('Failed to send message: ' + (data.error || 'Unknown error.'));
                        console.error('Message send failed:', data.error);
                    }
                })
                .catch(error => {
                    console.error('Error sending message:', error);
                    alert('An error occurred while sending your message. Please check the console for details.');
                });
            }
        });

        // Poll for new messages every 5 seconds (Note: For real-time chat, consider WebSockets for better performance)
        setInterval(() => {
            // Only poll if a conversation is currently loaded
            if (<?php echo $conversation_id ? 'true' : 'false'; ?>) {
                fetch(`<?= BASE_URL ?>public/vendor_chat.php?conversation_id=<?php echo htmlspecialchars($conversation_id); ?>&ajax=1`)
                    .then(response => response.text()) // Fetch the full HTML content to parse for new messages
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        // Select only incoming messages (not sent by the current user) that are not already on the page
                        const newIncomingMessages = doc.querySelectorAll('.message-incoming');
                        const messagesContainer = document.getElementById('messages-container');
                        if (messagesContainer) {
                            let addedNew = false;
                            newIncomingMessages.forEach(newMsg => {
                                const msgId = newMsg.dataset.id; // Get the message ID to check for duplicates
                                if (!document.querySelector(`#messages-container .message[data-id="${msgId}"]`)) {
                                    messagesContainer.appendChild(newMsg.cloneNode(true)); // Append new messages
                                    addedNew = true;
                                }
                            });
                            if (addedNew) {
                                scrollToBottom(); // Scroll to bottom if new messages were added
                            }
                        }
                    })
                    .catch(error => console.error('Error polling for messages:', error));
            }
        }, 5000); // Poll every 5 seconds

        // Initial scroll to bottom when page loads
        document.addEventListener('DOMContentLoaded', scrollToBottom);

        // Handle Enter key for sending messages (Shift+Enter for new line)
        document.getElementById('message-input')?.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) { // If Enter is pressed without Shift
                e.preventDefault(); // Prevent default new line behavior
                document.querySelector('.send-button')?.click(); // Trigger send button click
            }
        });
    </script>
