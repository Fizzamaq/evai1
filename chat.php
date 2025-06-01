<?php
// TEMPORARY: Enable full error reporting for debugging.
// This will display any PHP errors directly on the page.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// public/chat.php
session_start();
require_once '../includes/config.php';
require_once '../classes/User.class.php';
require_once '../classes/Chat.class.php';
require_once '../classes/Event.class.php'; // To fetch user's events for selection
require_once '../includes/auth.php'; // Required for generateCSRFToken and verifyCSRFToken

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

$user = new User($pdo); // Pass PDO
$chat = new Chat($pdo); // Pass PDO
$event = new Event($pdo); // Pass PDO

$user_data = $user->getUserById($_SESSION['user_id']);
$conversation_id = $_GET['conversation_id'] ?? null;
$event_id_from_url = $_GET['event_id'] ?? null; // Capture event_id if present in URL
$vendor_id_from_url = $_GET['vendor_id'] ?? null; // Capture vendor_id if present in URL

// Generate CSRF token for the form
$csrf_token = generateCSRFToken();

// --- Logic to find/create conversation if vendor_id is passed without conversation_id ---
// This runs only if no specific conversation_id is requested (i.e., new chat initiation)
if (!$conversation_id && $vendor_id_from_url) {
    // Try to find an existing conversation for this user, vendor, and (optionally) event
    // If event_id_from_url is NULL, getConversationByParticipants will look for event_id IS NULL
    $existing_conv = $chat->getConversationByParticipants($_SESSION['user_id'], $vendor_id_from_url, $event_id_from_url);

    if ($existing_conv) {
        $conversation_id = $existing_conv['id'];
        // Redirect to ensure URL reflects the conversation_id for proper polling and display
        header('Location: ' . BASE_URL . 'public/chat.php?conversation_id=' . $conversation_id);
        exit();
    }
    // If no existing conversation is found, conversation_id remains null.
    // The chat interface will be shown (because vendor_id_from_url is present),
    // and the first message sent will create the conversation.
}
// --- End of new conversation finding logic ---


// Handle new message (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    // Validate CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        error_log("CSRF token mismatch on chat message send for user " . $_SESSION['user_id']);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid request. Please refresh the page and try again.']);
        exit();
    }

    $message = trim($_POST['message']);

    if (!empty($message)) {
        // If conversation_id is still null, it means we need to create a new conversation.
        if (!$conversation_id) {
            // Get event_id and vendor_id from hidden inputs (if set by previous form state)
            // or directly from URL parameters captured on page load.
            $event_id_for_creation = $_POST['event_id_for_chat'] ?? $event_id_from_url;
            $vendor_id_for_creation = $_POST['vendor_id_for_chat'] ?? $vendor_id_from_url;

            // --- FINAL FIX for event_id_for_creation to be strict NULL or positive integer ---
            // Validate that event_id_for_creation is a positive integer.
            // If not (e.g., empty, 0, false, or non-numeric string), treat it as NULL.
            $validated_event_id = filter_var($event_id_for_creation, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

            if ($validated_event_id === false) { // If validation fails (e.g., is 0, empty string, non-numeric)
                $event_id_for_creation = NULL;
            } else {
                $event_id_for_creation = $validated_event_id; // Use the strictly validated integer
            }
            // --- END FINAL FIX ---

            // --- DEBUG LOG START (retained for final confirmation) ---
            error_log("DEBUG: chat.php - Attempting to start new conversation.");
            error_log("DEBUG: chat.php - event_id_for_creation: " . var_export($event_id_for_creation, true) . " (Type: " . gettype($event_id_for_creation) . ")");
            error_log("DEBUG: chat.php - user_id: " . var_export($_SESSION['user_id'], true));
            error_log("DEBUG: chat.php - vendor_id_for_chat: " . var_export($vendor_id_for_creation, true));
            // --- DEBUG LOG END ---


            // Crucially, vendor_id_for_creation MUST be present
            if ($vendor_id_for_creation) {
                // event_id_for_creation can now be NULL for general chats
                $conversation_id = $chat->startConversation($event_id_for_creation, $_SESSION['user_id'], $vendor_id_for_creation);
                if ($conversation_id) {
                    // Success! Now send the message. And tell frontend to redirect.
                    $chat->sendMessage($conversation_id, $_SESSION['user_id'], $message);
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'redirect_to_conversation' => BASE_URL . 'public/chat.php?conversation_id=' . $conversation_id]);
                    exit();
                } else {
                    error_log("Failed to start new conversation for user " . $_SESSION['user_id'] . " and vendor " . $vendor_id_for_creation);
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'Failed to create new conversation.']);
                    exit();
                }
            } else {
                error_log("Missing vendor_id for new conversation creation for user " . $_SESSION['user_id']);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'A vendor must be specified to start a new chat.']);
                exit();
            }
        }

        // If conversation_id is available (either pre-existing or just created above), send message
        if ($conversation_id) {
            $chat->sendMessage($conversation_id, $_SESSION['user_id'], $message);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Message sent.']);
            exit();
        }
    } else {
         header('Content-Type: application/json');
         echo json_encode(['success' => false, 'error' => 'Message cannot be empty.']);
         exit();
    }
}

// Get current conversation details (for initial page load or after redirection)
$current_conversation = null;
$messages = [];
$other_party = null;

// Only fetch user's events if we might need them (e.g., if event_id is missing for a new chat)
// Not directly used in the main chat display if a conversation is loaded.
$user_events = []; // Still useful if we want to add an option to link existing general chats to events later.


if ($conversation_id) {
    // Mark messages as read when opening conversation
    $chat->markMessagesAsRead($conversation_id, $_SESSION['user_id']);

    // Get conversation details
    try {
        // Corrected: Changed named parameters to positional for HY093 fix
        $stmt = $pdo->prepare("
            SELECT cc.*,
                   e.title as event_title, -- event_title might be NULL now
                   CASE
                     WHEN cc.user_id = ? THEN vp.business_name
                     ELSE CONCAT(u.first_name, ' ', u.last_name)
                   END as other_party_name,
                   CASE
                     WHEN cc.user_id = ? THEN up_vendor.profile_image
                     ELSE up_user.profile_image
                   END as other_party_image
            FROM chat_conversations cc
            LEFT JOIN events e ON cc.event_id = e.id -- Use LEFT JOIN because event_id can now be NULL
            LEFT JOIN users u ON cc.vendor_id = u.id
            LEFT JOIN vendor_profiles vp ON u.id = vp.user_id
            LEFT JOIN user_profiles up_vendor ON u.id = up_vendor.user_id
            LEFT JOIN users u2 ON cc.user_id = u2.id
            LEFT JOIN user_profiles up_user ON u2.id = up_user.user_id
            WHERE cc.id = ?
            AND (cc.user_id = ? OR cc.vendor_id = ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'], // Corresponds to first ? (current_user_id in original named query)
            $_SESSION['user_id'], // Corresponds to second ? (current_user_id in original named query)
            $conversation_id,     // Corresponds to third ? (conversation_id in original named query)
            $_SESSION['user_id'], // Corresponds to fourth ? (user_id_check1 in original named query)
            $_SESSION['user_id']  // Corresponds to fifth ? (user_id_check2 in original named query)
        ]);
        $current_conversation = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($current_conversation) {
            $other_party = [
                'id' => ($current_conversation['user_id'] == $_SESSION['user_id'])
                    ? $current_conversation['vendor_id']
                    : $current_conversation['user_id'],
                'name' => htmlspecialchars($current_conversation['other_party_name'] ?? 'Unknown User'), // Fixed deprecated warning
                'image' => htmlspecialchars($current_conversation['other_party_image'] ?? '') // Fixed deprecated warning
            ];

            $messages = $chat->getMessages($conversation_id, 100);
            $messages = array_reverse($messages); // Show oldest first
        }
    } catch (PDOException $e) {
        error_log("Get conversation error: " . $e->getMessage());
    }
} else if ($vendor_id_from_url) {
    // This block runs if we arrived with just vendor_id (for a new chat initiation)
    // We now directly show the chat interface.
    // Fetch vendor info to display in chat header.
    try {
        $stmt_vendor = $pdo->prepare("SELECT vp.business_name, up.profile_image FROM vendor_profiles vp JOIN users u ON vp.user_id = u.id LEFT JOIN user_profiles up ON u.id = up.user_id WHERE u.id = ?");
        $stmt_vendor->execute([$vendor_id_from_url]);
        $vendor_info = $stmt_vendor->fetch(PDO::FETCH_ASSOC);
        if (is_array($vendor_info) && !empty($vendor_info)) { // Check if vendor_info is not empty/false
            $other_party = [
                'id' => $vendor_id_from_url,
                'name' => htmlspecialchars($vendor_info['business_name'] ?? 'Unknown Vendor'), // Fixed deprecated warning
                'image' => htmlspecialchars($vendor_info['profile_image'] ?? '') // Fixed deprecated warning
            ];
            // Since we're ready to chat, we can set current_conversation to a non-null value
            // so the chat interface (messages and input) renders immediately.
            // This is a dummy object just for rendering purposes before the real conversation ID is set.
            $current_conversation = ['vendor_id' => $vendor_id_from_url, 'event_id' => $event_id_from_url];
            $messages = []; // No messages yet for a new chat
        } else {
            // Handle case where vendor_id_from_url does not correspond to a valid vendor
            error_log("Error fetching vendor info: Vendor ID '{$vendor_id_from_url}' not found or no profile.");
            // Set current_conversation to null so it shows "Select a conversation..."
            $current_conversation = null;
            $_SESSION['error_message'] = "Invalid vendor selected to start chat.";
            // You might redirect here, but for now, letting it fall through to no-conversation message
        }
    } catch (PDOException $e) {
        error_log("Error fetching vendor info for new chat initiation: " . $e->getMessage());
        $current_conversation = null;
    }
}


// Get user's conversations for the sidebar
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
    <link rel="stylesheet" href="<?= ASSETS_PATH ?>css/chat.css">
</head>
<body>
    <?php include 'header.php'; // Using general header, assuming it adapts for vendor ?>

    <div class="chat-container">
        <div class="conversations-sidebar">
            <div class="conversations-header">
                <h2>Messages <?php if ($unread_count > 0): ?><span class="unread-badge"><?php echo htmlspecialchars($unread_count); ?></span><?php endif; ?></h2>
            </div>
            <div class="conversations-list">
                <?php if (empty($conversations)): ?>
                    <div class="no-conversation">No conversations yet</div>
                <?php else: ?>
                    <?php foreach ($conversations as $conv):
                        // Safely get other_party_image URL for display
                        $display_image = !empty($conv['other_party_image']) ? BASE_URL . 'assets/uploads/users/' . htmlspecialchars($conv['other_party_image'] ?? '') : BASE_URL . 'assets/images/default-avatar.jpg'; // Fixed deprecated warning
                        // Safely get last message preview, ensuring it's not null before substr
                        $last_message_preview = htmlspecialchars(substr($conv['last_message'] ?? 'No messages yet', 0, 30));
                        // Safely format last message time, handling null/invalid dates
                        $last_message_time_formatted = !empty($conv['last_message_time']) ? date('M j, g:i a', strtotime($conv['last_message_time'])) : 'N/A';
                    ?>
                        <div class="conversation-item <?php echo ($conv['id'] == $conversation_id) ? 'active' : ''; ?>"
                             onclick="window.location.href='<?= BASE_URL ?>public/chat.php?conversation_id=<?php echo htmlspecialchars($conv['id']); ?>'">
                            <div class="conversation-avatar" style="background-image: url('<?= $display_image ?>')"></div>
                            <div class="conversation-details">
                                <div class="conversation-title">
                                    <span><?php echo htmlspecialchars($conv['other_party_name'] ?? 'Unknown User'); ?></span>
                                    <?php if ($conv['unread_count'] > 0): ?>
                                        <span class="unread-badge"><?php echo htmlspecialchars($conv['unread_count']); ?></span>
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
            <?php if ($current_conversation || $vendor_id_from_url): /* Show chat interface if a conversation is loaded OR a vendor is targeted for a new chat */ ?>
                <div class="chat-header">
                    <div class="chat-header-avatar" style="background-image: url('<?php echo htmlspecialchars($other_party['image'] ? BASE_URL . 'assets/uploads/users/' . $other_party['image'] : BASE_URL . 'assets/images/default-avatar.jpg'); ?>')"></div>
                    <div class="chat-header-info">
                        <div class="chat-header-title"><?php echo htmlspecialchars($other_party['name'] ?? 'New Chat'); ?></div>
                        <div class="chat-header-subtitle">
                            <?php
                            // Display event title if available, otherwise "General Chat"
                            if (isset($current_conversation['event_title']) && $current_conversation['event_title'] !== null) {
                                echo htmlspecialchars($current_conversation['event_title']);
                            } elseif ($vendor_id_from_url && !$conversation_id) { // For new chat where event_id_from_url might be set
                                // Attempt to get event title if event_id_from_url is present for a new chat
                                $new_chat_event_title = '';
                                if (!empty($event_id_from_url)) {
                                    $new_chat_event = $event->getEventById((int)$event_id_from_url, $_SESSION['user_id']); // Assuming user_id is needed for getEventById
                                    $new_chat_event_title = $new_chat_event['title'] ?? '';
                                }
                                echo htmlspecialchars($new_chat_event_title ?: 'General Chat');
                            } else {
                                echo 'General Chat';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <div class="chat-messages" id="messages-container">
                    <?php if (empty($messages)): ?>
                        <div class="empty-state">Start your conversation!</div>
                    <?php else: ?>
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
                    <?php endif; ?>
                </div>
                <div class="chat-input">
                    <form class="message-form" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="vendor_id_for_chat" value="<?= htmlspecialchars($vendor_id_from_url) ?>">
                        <input type="hidden" name="event_id_for_chat" value="<?= htmlspecialchars($event_id_from_url) ?>">
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
            <?php else: /* No current conversation loaded AND no vendor_id_from_url to initiate a chat */ ?>
                <div class="no-conversation">
                    <h3>Select a conversation or start a new one</h3>
                    <p>Choose from your existing conversations on the left, or initiate a new chat by messaging a vendor from their profile or a booking.</p>
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
                // Determine the URL for the POST request
                let postUrl = '<?= BASE_URL ?>public/chat.php';
                const currentConversationId = '<?= htmlspecialchars($conversation_id) ?>';
                if (currentConversationId) {
                    postUrl += `?conversation_id=${currentConversationId}`;
                } else {
                    // For a new conversation being created, parameters are in form via hidden inputs
                }

                fetch(postUrl, {
                    method: 'POST',
                    body: new URLSearchParams(new FormData(form)), // Use URLSearchParams with FormData for x-www-form-urlencoded
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    }
                }).then(response => response.json()) // Expect JSON response
                .then(data => {
                    if (data.success) {
                        messageInput.value = '';
                        // Redirect to the new conversation if it was just created (first message sent)
                        if (data.redirect_to_conversation) {
                            window.location.href = data.redirect_to_conversation;
                            return; // Stop further execution
                        }
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

        // Poll for new messages every 5 seconds (NOTE: For real-time chat, consider replacing this with WebSockets for better performance and efficiency.)
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
