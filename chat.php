<?php
// TEMPORARY: Enable full error reporting for debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ensure session is started ONLY ONCE, ideally in config.php
// If not in config.php, it must be the very first line of this script.
// For now, assuming config.php handles it. If not, uncomment the line below:
// session_start(); 

require_once '../includes/config.php'; // This should contain session_start() or be the first include
require_once '../classes/User.class.php';
require_once '../classes/Chat.class.php';
require_once '../classes/Event.class.php';
require_once '../includes/auth.php'; // This might also contain session_start() or redirect logic

// Instantiate classes (needed for both POST and GET logic)
$user = new User($pdo);
$chat = new Chat($pdo);
$event = new Event($pdo);

// Determine if this is an AJAX request (used for both POST and GET context)
$is_ajax_request = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_GET['ajax']) && $_GET['ajax'] == 1);

// --- START: Main Request Type Handling ---

// All POST requests are handled here and exit immediately
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json'); // Set JSON header early for all POST responses

    // Check user authentication for POST requests
    if (!isset($_SESSION['user_id'])) {
        error_log("Chat POST: User not authenticated.");
        echo json_encode(['success' => false, 'error' => 'User not authenticated. Please log in.', 'redirect' => BASE_URL . 'public/login.php']);
        exit();
    }

    // Handle specific POST actions
    if (isset($_POST['send_message'])) {
        // Validate CSRF token
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            error_log("CSRF token mismatch on chat message send for user " . ($_SESSION['user_id'] ?? 'N/A'));
            echo json_encode(['success' => false, 'error' => 'Invalid request. Please refresh the page and try again.']);
            exit();
        }

        $message = trim($_POST['message']);

        if (empty($message)) {
            echo json_encode(['success' => false, 'error' => 'Message cannot be empty.']);
            exit();
        }

        // Initialize variables for conversation creation
        $conversation_id = $_GET['conversation_id'] ?? null; // Get from URL for existing conversations

        // Ensure event_id_for_creation is genuinely NULL if not provided or invalid
        $event_id_for_creation = null; // Default to NULL
        if (isset($_POST['event_id_for_chat']) && $_POST['event_id_for_chat'] !== '') {
            $filtered_event_id = filter_var($_POST['event_id_for_chat'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($filtered_event_id !== false) {
                $event_id_for_creation = $filtered_event_id;
            }
        }

        $vendor_id_for_creation = $_POST['vendor_id_for_chat'] ?? null;
        $is_new_conversation = false;

        // Logic to find/create conversation if vendor_id is passed without conversation_id
        if (!$conversation_id && $vendor_id_for_creation) {
            $created_conversation_id = $chat->startConversation($event_id_for_creation, $_SESSION['user_id'], $vendor_id_for_creation);
            if (!$created_conversation_id) {
                error_log("Chat POST: Failed to start new conversation for user " . ($_SESSION['user_id'] ?? 'N/A') . " and vendor " . ($vendor_id_for_creation ?? 'N/A'));
                echo json_encode(['success' => false, 'error' => 'Failed to create new conversation.']);
                exit();
            }
            $conversation_id = $created_conversation_id;
            $is_new_conversation = true;
        } elseif (!$conversation_id) { // If no conversation_id and no vendor_id for new creation
             error_log("Chat POST: Missing conversation ID or vendor_id for new conversation creation for user " . $_SESSION['user_id']);
             echo json_encode(['success' => false, 'error' => 'A conversation or vendor must be identified to send a message.']);
             exit();
        }


        // If conversation_id is now available (either pre-existing or just created), send message
        if ($conversation_id) {
            $message_sent = $chat->sendMessage($conversation_id, $_SESSION['user_id'], $message);
            if ($message_sent) {
                if ($is_new_conversation) { // New conversation created via POST
                     echo json_encode(['success' => true, 'redirect_to_conversation' => BASE_URL . 'public/chat.php?conversation_id=' . $conversation_id]);
                } else { // Message sent to existing conversation
                     echo json_encode(['success' => true, 'message' => 'Message sent.', 'message_id' => $message_sent]);
                }
                exit();
            } else {
                error_log("Chat POST: Failed to save message to database for conversation " . $conversation_id);
                echo json_encode(['success' => false, 'error' => 'Failed to save message to database.']);
                exit();
            }
        } else {
            error_log("Chat POST: Conversation not identified after processing for user " . $_SESSION['user_id']);
            echo json_encode(['success' => false, 'error' => 'Conversation not identified after processing.']);
            exit();
        }
    } elseif (isset($_POST['delete_chat'])) { // Handle chat deletion request
        $conversation_id_to_delete = $_POST['conversation_id'];
        // Basic validation: ensure conversation_id is numeric and user is part of it.
        if (!empty($conversation_id_to_delete) && is_numeric($conversation_id_to_delete)) {
            if ($chat->markConversationAsArchived((int)$conversation_id_to_delete, $_SESSION['user_id'])) {
                echo json_encode(['success' => true, 'message' => 'Chat deleted successfully.', 'redirect' => BASE_URL . 'public/chat.php']);
            } else {
                error_log("Chat POST: Failed to archive chat {$conversation_id_to_delete} for user " . $_SESSION['user_id']);
                echo json_encode(['success' => false, 'error' => 'Failed to archive chat.']);
            }
        } else {
            error_log("Chat POST: Invalid conversation ID for deletion: " . ($conversation_id_to_delete ?? 'NULL'));
            echo json_encode(['success' => false, 'error' => 'Invalid conversation ID for deletion.']);
        }
        exit();
    }
    else {
        // If other POST actions were sent to this file, handle or error out
        error_log("Chat POST: Invalid POST action received.");
        echo json_encode(['success' => false, 'error' => 'Invalid POST action.']);
        exit();
    }
}

// --- END OF POST REQUEST HANDLING ---


// --- START: All GET Request Handling (only runs if not exited by POST block) ---

// Check user authentication for GET requests
if (!isset($_SESSION['user_id'])) {
    if ($is_ajax_request) { // For AJAX polling GET, output minimal content
        echo json_encode(['success' => false, 'error' => 'User not authenticated for AJAX.']); // Return JSON for AJAX
        exit();
    } else { // For full page load GET, redirect to login
        header('Location: ' . BASE_URL . 'public/login.php');
        exit();
    }
}

// Re-fetch conversation details for rendering
$conversation_id = $_GET['conversation_id'] ?? null; // Get conversation_id from URL for GET requests
$event_id_from_url = $_GET['event_id'] ?? null;
$vendor_id_from_url = $_GET['vendor_id'] ?? null;
$csrf_token = generateCSRFToken(); // Generate for GET form


    // Fetch user data needed for chat interface for GET requests
    $user_data = $user->getUserById($_SESSION['user_id']);

    $current_conversation_details = null; 
    $messages = []; 
    $other_party = null;

    if ($conversation_id) {
        $chat->markMessagesAsRead($conversation_id, $_SESSION['user_id']); // Mark as read on full page load or AJAX refresh

        try {
            $stmt = $pdo->prepare("
                SELECT cc.*,
                       e.title as event_title,
                       CASE
                         WHEN cc.user_id = ? THEN vp.business_name
                         ELSE CONCAT(u.first_name, ' ', u.last_name)
                       END as other_party_name,
                       CASE
                         WHEN cc.user_id = ? THEN up_vendor.profile_image
                         ELSE up_user.profile_image
                       END as other_party_image
                    FROM chat_conversations cc
                    LEFT JOIN events e ON cc.event_id = e.id
                    LEFT JOIN users u ON cc.vendor_id = u.id
                    LEFT JOIN vendor_profiles vp ON u.id = vp.user_id
                    LEFT JOIN user_profiles up_vendor ON u.id = up_vendor.user_id
                    LEFT JOIN users u2 ON cc.user_id = u2.id
                    LEFT JOIN user_profiles up_user ON u2.id = up_user.user_id
                    WHERE cc.id = ?
                    AND (cc.user_id = ? OR cc.vendor_id = ?)
                ");
                $params = [ 
                    $_SESSION['user_id'], 
                    $_SESSION['user_id'], 
                    $conversation_id,
                    $_SESSION['user_id'], 
                    $_SESSION['user_id']  
                ];
                $stmt->execute($params);
                $current_conversation_details = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($current_conversation_details) {
                    // Determine the 'other party' (the vendor in this customer chat)
                    $other_party_user_id = ($current_conversation_details['user_id'] == $_SESSION['user_id']) ? $current_conversation_details['vendor_id'] : $current_conversation_details['user_id'];

                    $other_party = [
                        'id' => (int)$other_party_user_id,
                        'name' => htmlspecialchars((string)($current_conversation_details['other_party_name'] ?? 'Unknown User')),
                        'image' => htmlspecialchars((string)($current_conversation_details['other_party_image'] ?? ''))
                    ];
                    
                    // If it's an AJAX request (polling), return JSON
                    if ($is_ajax_request) { 
                        $last_message_id_for_polling = $_GET['last_message_id'] ?? null; 
                        $messages = $chat->getMessages($conversation_id, 100, 0, $last_message_id_for_polling);
                        echo json_encode([
                            'success' => true,
                            'messages' => array_values($messages) 
                        ]);
                        exit(); 
                    } else { // Initial page load
                        $messages = $chat->getMessages($conversation_id, 100); 
                    }

                } else {
                    error_log("Chat GET: Conversation ID {$conversation_id} not found or not accessible to user {$_SESSION['user_id']}.");
                    $conversation_id = null; 
                }
            } catch (PDOException $e) {
                error_log("Chat GET: Get conversation details error: " . $e->getMessage());
                if ($is_ajax_request) {
                    echo json_encode(['success' => false, 'error' => 'Failed to load conversation details for polling.']);
                } else {
                    $_SESSION['error_message'] = "Could not load conversation details. Please try again.";
                }
                $current_conversation_details = null;
                $conversation_id = null; 
            }
        } elseif ($vendor_id_from_url) {
            // This case handles a *new* chat initiation directly from a vendor profile
            try {
                $stmt_vendor = $pdo->prepare("SELECT vp.business_name, up.profile_image FROM vendor_profiles vp JOIN users u ON vp.user_id = u.id LEFT JOIN user_profiles up ON u.id = up.user_id WHERE u.id = ?");
                $stmt_vendor->execute([(int)$vendor_id_from_url]);
                $vendor_info = $stmt_vendor->fetch(PDO::FETCH_ASSOC);
                if (is_array($vendor_info) && !empty($vendor_info)) {
                    $other_party = [
                        'id' => (int)$vendor_id_from_url,
                        'name' => htmlspecialchars((string)($vendor_info['business_name'] ?? 'Unknown Vendor')),
                        'image' => htmlspecialchars((string)($vendor_info['profile_image'] ?? ''))
                    ];
                    // Also get event title if available
                    if (!empty($event_id_from_url)) {
                        $event_details_for_new_chat = $event->getEventById((int)$event_id_from_url); 
                        if ($event_details_for_new_chat) {
                            $current_conversation_details = ['event_title' => $event_details_for_new_chat['title']];
                        }
                    }
                } else {
                    error_log("Chat GET: Error fetching vendor info: Vendor ID '{$vendor_id_from_url}' not found or no profile.");
                    $other_party = null; 
                    $_SESSION['error_message'] = "Invalid vendor selected to start chat.";
                }
            } catch (PDOException $e) {
                error_log("Chat GET: Error fetching vendor info for new chat initiation (GET): " . $e->getMessage());
                $other_party = null; 
                $_SESSION['error_message'] = "Could not prepare new chat. Please try again.";
            }
        }
    
    // If other_party is not set by now (i.e., not an existing convo or new convo with vendor_id), set a default
    if (!isset($other_party)) {
        $other_party = [
            'id' => null,
            'name' => 'Select Chat',
            'image' => ''
        ];
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
    <title>Chat - EventCraftAI</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/chat.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="chat-container">
        <div class="conversations-sidebar">
            <div class="conversations-header">
                <h2>Messages <?php if ($unread_count > 0): ?><span class="unread-badge"><?php echo htmlspecialchars((string)$unread_count); ?></span><?php endif; ?></h2>
            </div>
            <div class="conversations-list">
                <?php if (empty($conversations)): ?>
                    <div class="no-conversation">No conversations yet</div>
                <?php else: ?>
                    <?php foreach ($conversations as $conv):
                        $display_image = !empty($conv['other_party_image']) ? BASE_URL . 'assets/uploads/users/' . htmlspecialchars((string)$conv['other_party_image']) : BASE_URL . 'assets/images/default-avatar.jpg';
                        $last_message_preview = htmlspecialchars(substr((string)($conv['last_message'] ?? 'No messages yet'), 0, 30));
                        $last_message_time_formatted = !empty($conv['last_message_time']) ? date('M j, g:i a', strtotime((string)$conv['last_message_time'])) : 'N/A';
                    ?>
                        <div class="conversation-item <?php echo ((string)$conv['id'] === (string)$conversation_id) ? 'active' : ''; ?>"
                             onclick="window.location.href='<?= BASE_URL ?>public/chat.php?conversation_id=<?php echo htmlspecialchars((string)$conv['id']); ?>'">
                            <div class="conversation-avatar" style="background-image: url('<?= $display_image ?>')"></div>
                            <div class="conversation-details">
                                <div class="conversation-title">
                                    <span><?php echo htmlspecialchars((string)($conv['other_party_name'] ?? 'Unknown User')); ?></span>
                                    <?php if ($conv['unread_count'] > 0): ?>
                                        <span class="unread-badge"><?php echo htmlspecialchars((string)$conv['unread_count']); ?></span>
                                    <?php endif; ?>
                                    <span class="delete-chat-icon" data-conversation-id="<?= htmlspecialchars((string)$conv['id']) ?>" title="Delete Chat" style="margin-left: auto;">
                                        <i class="fas fa-trash-alt"></i>
                                    </span>
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
            <?php if ($conversation_id || isset($_GET['vendor_id'])): ?>
                <div class="chat-header">
                    <div class="chat-header-avatar" style="background-image: url('<?php echo htmlspecialchars((string)($other_party['image'] ? BASE_URL . 'assets/uploads/users/' . $other_party['image'] : BASE_URL . 'assets/images/default-avatar.jpg')); ?>')"></div>
                    <div class="chat-header-info">
                        <div class="chat-header-title"><?php echo htmlspecialchars((string)($other_party['name'] ?? 'New Chat')); ?></div>
                        <div class="chat-header-subtitle">
                            <?php
                            if (isset($current_conversation_details['event_title']) && $current_conversation_details['event_title'] !== null) {
                                echo htmlspecialchars((string)$current_conversation_details['event_title']);
                            } elseif (isset($event_details_for_new_chat['title'])) {
                                echo htmlspecialchars((string)$event_details_for_new_chat['title']);
                            } else {
                                echo 'General Chat';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <div class="chat-messages" id="messages-container">
                    <?php 
                    if (empty($messages)): ?>
                        <div class="empty-state">No messages yet. Say hello!</div>
                    <?php else: 
                        foreach ($messages as $message):
                            $message_content_display = nl2br(htmlspecialchars((string)($message['message_content'] ?? '')));
                            $message_time_display = !empty($message['created_at']) ? date('g:i a', strtotime((string)$message['created_at'])) : 'N/A';
                            ?>
                            <div class="message <?php echo ($message['sender_id'] == $_SESSION['user_id']) ? 'message-outgoing' : 'message-incoming'; ?>" data-id="<?= htmlspecialchars((string)($message['id'])) ?>">
                                <div class="message-content"><?php echo $message_content_display; ?></div>
                                <span class="message-time">
                                    <?php echo $message_time_display; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="chat-error-container" id="chat-error-container" style="display: none;">
                    <div class="chat-error-message" id="chat-error-message"></div>
                    <div class="chat-error-actions">
                        <button type="button" id="chat-retry-send" class="btn btn-sm btn-primary">Retry</button>
                        <button type="button" id="chat-dismiss-error" class="btn btn-sm btn-secondary">Dismiss</button>
                    </div>
                </div>
                <div class="chat-input">
                    <form class="message-form" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$csrf_token) ?>">
                        <input type="hidden" name="vendor_id_for_chat" value="<?= htmlspecialchars((string)($_GET['vendor_id'] ?? '')) ?>">
                        <input type="hidden" name="event_id_for_chat" value="<?= htmlspecialchars((string)($_GET['event_id'] ?? '')) ?>">
                        <input type="hidden" name="send_message" value="1">
                        <textarea
                            class="message-input"
                            name="message"
                            placeholder="Type your message..."
                            id="message-input"
                            required
                        ></textarea>
                        <button type="submit" name="send_button_submit" class="send-button">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/>
                            </svg>
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="no-conversation">
                    <h3>Select a conversation to view messages</h3>
                    <p>Your conversations will appear on the left sidebar. If you have no conversations, start one from a booking or a vendor's profile.</p>
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

        // Helper function to create a message element
        function createMessageElement(messageData, isOutgoing) {
            const tempMsg = document.createElement('div');
            tempMsg.className = 'message ' + (isOutgoing ? 'message-outgoing' : 'message-incoming');
            tempMsg.dataset.id = messageData.id; // Set data-id for polling

            const contentDiv = document.createElement('div');
            contentDiv.className = 'message-content';
            contentDiv.innerHTML = messageData.message_content.replace(/\n/g, '<br>'); // Preserve newlines
            
            const timeSpan = document.createElement('span');
            timeSpan.className = 'message-time';
            // Format time: get 12-hour format with AM/PM
            const messageDate = new Date(messageData.created_at);
            timeSpan.textContent = messageDate.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });

            tempMsg.appendChild(contentDiv);
            tempMsg.appendChild(timeSpan);
            return tempMsg;
        }

        // Elements for error display
        const chatErrorContainer = document.getElementById('chat-error-container');
        const chatErrorMessage = document.getElementById('chat-error-message');
        const chatRetryButton = document.getElementById('chat-retry-send');
        const chatDismissButton = document.getElementById('chat-dismiss-error');
        const messageInput = document.getElementById('message-input');
        const messageForm = document.querySelector('.message-form');
        const deleteChatButtons = document.querySelectorAll('.delete-chat-icon'); 

        let lastFailedMessage = ''; // Store the last message that failed to send

        function showErrorMessage(message) {
            if (chatErrorContainer && chatErrorMessage) {
                chatErrorMessage.textContent = message;
                chatErrorContainer.style.display = 'flex'; // Use flex to center content
                scrollToBottom(); // Keep the error in view
            }
        }

        function hideErrorMessage() {
            if (chatErrorContainer) {
                chatErrorContainer.style.display = 'none';
                chatErrorMessage.textContent = '';
            }
        }

        // Event listener for retry button
        chatRetryButton?.addEventListener('click', function() {
            if (messageInput && lastFailedMessage) {
                messageInput.value = lastFailedMessage; // Restore message to input
                hideErrorMessage(); // Hide the error message
                messageForm?.dispatchEvent(new Event('submit')); // Re-submit the form
            }
        });

        // Event listener for dismiss button
        chatDismissButton?.addEventListener('click', function() {
            hideErrorMessage();
            lastFailedMessage = ''; // Clear stored message
        });

        // Attach event listener to all delete buttons in the sidebar
        deleteChatButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.stopPropagation(); // Prevent the conversation item's click event from firing
                const conversationIdToDelete = this.dataset.conversationId;
                if (confirm('Are you sure you want to delete (archive) this chat? This action cannot be undone.')) {
                    fetch('<?= BASE_URL ?>public/chat.php', {
                        method: 'POST',
                        body: new URLSearchParams({
                            delete_chat: '1',
                            conversation_id: conversationIdToDelete,
                            csrf_token: '<?= htmlspecialchars((string)$csrf_token) ?>' // Include CSRF token
                        }),
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.message);
                            window.location.href = data.redirect || '<?= BASE_URL ?>public/chat.php'; // Redirect to clear page or chat list
                        } else {
                            alert(data.error || 'Failed to delete chat.');
                        }
                    })
                    .catch(error => {
                        console.error('Error deleting chat:', error);
                        alert('An error occurred while trying to delete the chat.');
                    });
                }
            });
        });


        // Send message with AJAX
        messageForm?.addEventListener('submit', function(e) {
            e.preventDefault();
            hideErrorMessage(); // Hide any previous error when attempting to send
            
            const form = this;
            const message = messageInput.value.trim();

            if (message) {
                lastFailedMessage = message; // Store message in case of failure

                let postUrl = '<?= BASE_URL ?>public/chat.php';
                const currentConversationId = '<?php echo htmlspecialchars((string)($conversation_id ?? '')); ?>';
                if (currentConversationId) {
                    postUrl += `?conversation_id=${currentConversationId}`;
                }

                fetch(postUrl, {
                    method: 'POST',
                    body: new URLSearchParams(new FormData(form)),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).then(response => {
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        return response.json();
                    } else {
                        return response.text().then(text => {
                            console.error('Server response was not JSON for chat send (Status:', response.status, '):', text);
                            throw new Error('Server returned unexpected response format.');
                        });
                    }
                })
                .then(data => {
                    if (data.success) {
                        messageInput.value = '';
                        lastFailedMessage = ''; // Clear stored message on success

                        // If a redirect is explicitly sent, follow it. (e.g., for initial chat creation)
                        if (data.redirect_to_conversation) {
                            window.location.href = data.redirect_to_conversation;
                            return;
                        }
                        // Add message to display instantly (optimistic update)
                        const messagesContainer = document.getElementById('messages-container');
                        if (messagesContainer) {
                            // Create a temporary message object for optimistic display
                            const optimisticMessage = {
                                id: data.message_id || Date.now(), // Use real ID if returned, else timestamp
                                message_content: message,
                                created_at: new Date().toISOString(), // Use current time for optimistic display
                                sender_id: <?php echo json_encode($_SESSION['user_id']); ?>
                            };
                            messagesContainer.appendChild(createMessageElement(optimisticMessage, true));
                            scrollToBottom();
                        }
                    } else {
                        // Display the error message from the server's JSON response
                        showErrorMessage(data.error || 'Unknown error occurred.');
                        console.error('Message send failed:', data.error);
                    }
                }).catch(error => {
                    showErrorMessage('Network error: ' + error.message + '. Please check your connection.');
                    console.error('Error sending message:', error);
                });
            }
        });

        // Polling for new messages
        setInterval(() => {
            const conversationId = '<?php echo htmlspecialchars((string)($conversation_id ?? '')); ?>';
            if (conversationId) {
                const messagesContainer = document.getElementById('messages-container');
                // Capture the current scroll position before refetching to maintain it
                const shouldScrollToBottom = messagesContainer.scrollHeight - messagesContainer.scrollTop <= messagesContainer.clientHeight + 1; // Tolerance for "at bottom"
                const currentLastMessage = messagesContainer.lastElementChild;
                const lastMessageId = currentLastMessage ? parseInt(currentLastMessage.dataset.id) : 0; // Get ID of last message

                fetch(`<?= BASE_URL ?>public/chat.php?conversation_id=${conversationId}&ajax=1&last_message_id=${lastMessageId}`) // Pass last_message_id
                    .then(response => {
                        if (!response.ok) {
                            return response.json().then(json => { // Expect JSON on error now
                                console.error('Polling HTTP Error:', response.status, response.statusText, json.error || 'Unknown error.');
                                throw new Error('Polling network response was not ok: ' + (json.error || 'Unknown error.'));
                            });
                        }
                        return response.json(); // Expect JSON data
                    })
                    .then(data => {
                        if (data.success && data.messages) {
                            let addedNew = false;
                            data.messages.forEach(fetchedMsg => {
                                // Only append if the message is actually new (ID > lastMessageId)
                                // This check is crucial if backend returns all messages or a range that includes old ones.
                                // getMessages in Chat.class.php should already handle this by filtering `id > ?`.
                                if (parseInt(fetchedMsg.id) > lastMessageId) {
                                    messagesContainer.appendChild(createMessageElement(fetchedMsg, fetchedMsg.sender_id == <?php echo json_encode($_SESSION['user_id']); ?>));
                                    addedNew = true;
                                }
                            });

                            if (addedNew && shouldScrollToBottom) {
                                scrollToBottom();
                            }
                        }
                    })
                    .catch(error => console.error('Error polling for messages:', error));
            }
        }, 5000); // Poll every 5 seconds

        // Initial scroll to bottom and event listener setup
        document.addEventListener('DOMContentLoaded', function() {
            scrollToBottom();

            // Allow message input to grow with content but not excessively
            const messageInput = document.getElementById('message-input');
            if (messageInput) {
                messageInput.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = (this.scrollHeight) + 'px';
                    scrollToBottom(); // Scroll to bottom when input grows
                });
            }
        });

        // Enter key handling (Shift+Enter for new line)
        document.getElementById('message-input')?.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                document.querySelector('.send-button')?.click();
            }
        });
    </script>
