<?php
// TEMPORARY: Enable full error reporting for debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../includes/config.php';
require_once '../classes/User.class.php';
require_once '../classes/Chat.class.php';
require_once '../classes/Event.class.php';
require_once '../classes/Vendor.class.php';
require_once '../includes/auth.php';

// Instantiate classes (needed for both POST and GET logic)
$vendor = new Vendor($pdo);
$user = new User($pdo);
$chat = new Chat($pdo);
$event = new Event($pdo);

// Determine if this is an AJAX request
$is_ajax_request = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_GET['ajax']) && $_GET['ajax'] == 1);


// --- START: Main Request Type Handling ---

// All POST requests are handled here and exit immediately
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json'); // Set JSON header early for all POST responses

    // Check user authentication and vendor access for POST requests
    if (!isset($_SESSION['user_id'])) {
        error_log("Vendor Chat POST: User not authenticated.");
        echo json_encode(['success' => false, 'error' => 'User not authenticated. Please log in.', 'redirect' => BASE_URL . 'public/login.php']);
        exit();
    }
    // Ensure user is a verified vendor for POST actions
    $vendor->verifyVendorAccess(); 

    // Handle specific POST actions
    if (isset($_POST['send_message'])) {
        // Validate CSRF token
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            error_log("CSRF token mismatch on vendor chat message send for user " . ($_SESSION['user_id'] ?? 'N/A'));
            echo json_encode(['success' => false, 'error' => 'Invalid request. Please refresh the page and try again.']);
            exit();
        }

        $message = trim($_POST['message']);

        if (empty($message)) {
             echo json_encode(['success' => false, 'error' => 'Message cannot be empty.']);
             exit();
        }

        // Get conversation_id. For vendors, it should usually be present from URL.
        $conversation_id = $_GET['conversation_id'] ?? null; 

        // --- NEW: Check for vendor-to-vendor chat attempt (not supported) ---
        $sender_user_type = $_SESSION['user_type'] ?? null;
        $recipient_user_type = null;

        if (!$conversation_id && ($client_user_id_for_chat = $_POST['client_user_id_for_chat'] ?? null)) {
            $recipient_user_data = $user->getUserById($client_user_id_for_chat);
            if ($recipient_user_data) {
                $recipient_user_type = $recipient_user_data['user_type_id'];
            }

            // Attempt to start conversation
            $created_conversation_id = $chat->startConversation(null, (int)$client_user_id_for_chat, $_SESSION['user_id']); // vendor_id is current user
            if (!$created_conversation_id) {
                error_log("Failed to start new conversation for vendor " . ($_SESSION['user_id'] ?? 'N/A') . " and client " . ($client_user_id_for_chat ?? 'N/A'));
                echo json_encode(['success' => false, 'error' => 'Failed to create new conversation.']);
                exit();
            }
            $conversation_id = $created_conversation_id;
        } elseif (!$conversation_id) {
             error_log("Missing conversation ID or client_user_id for new conversation creation for vendor " . $_SESSION['user_id']);
             echo json_encode(['success' => false, 'error' => 'A conversation or client must be identified to send a message.']);
             exit();
        }
        // --- END NEW CHECK ---

        // If conversation_id is now available, send message
        if ($conversation_id) {
            $message_sent = $chat->sendMessage($conversation_id, $_SESSION['user_id'], $message); // Sender is the vendor's user_id
            if ($message_sent) {
                echo json_encode(['success' => true, 'message' => 'Message sent.', 'message_id' => $message_sent]); // Return message_id
                exit();
            } else {
                error_log("Vendor Chat POST: Failed to save message to database for conversation " . $conversation_id);
                echo json_encode(['success' => false, 'error' => 'Failed to save message to database.']);
                exit();
            }
        } else {
            error_log("Failed to send message: No conversation ID provided for vendor response.");
            echo json_encode(['success' => false, 'error' => 'Failed to send message. Conversation not identified.']);
            exit();
    }
    } elseif (isset($_POST['delete_chat'])) { // Handle chat deletion request
        $conversation_id_to_delete = $_POST['conversation_id'];
        // Basic validation: ensure conversation_id is numeric and user is part of it.
        if (!empty($conversation_id_to_delete) && is_numeric($conversation_id_to_delete)) {
            if ($chat->markConversationAsArchived((int)$conversation_id_to_delete, $_SESSION['user_id'])) {
                echo json_encode(['success' => true, 'message' => 'Chat archived successfully.', 'redirect' => BASE_URL . 'public/vendor_chat.php']);
            } else {
                error_log("Vendor Chat POST: Failed to archive chat {$conversation_id_to_delete} for user " . $_SESSION['user_id']);
                echo json_encode(['success' => false, 'error' => 'Failed to archive chat.']);
            }
        } else {
            error_log("Vendor Chat POST: Invalid conversation ID for deletion: " . ($conversation_id_to_delete ?? 'NULL'));
            echo json_encode(['success' => false, 'error' => 'Invalid conversation ID for deletion.']);
        }
        exit();
    }
    else {
        // If other POST actions were sent to this file, handle or error out
        error_log("Vendor Chat POST: Invalid POST action received.");
        echo json_encode(['success' => false, 'error' => 'Invalid POST action.']);
        exit();
    }
}

// --- END OF POST REQUEST HANDLING ---


// --- START: All GET Request Handling (only runs if not exited by POST block) ---

// Check user authentication and vendor access for GET requests
if (!isset($_SESSION['user_id'])) {
    if ($is_ajax_request) { // For AJAX polling GET, output minimal content
        echo json_encode(['success' => false, 'error' => 'User not authenticated for AJAX.']); // Return JSON for AJAX
        exit();
    } else { // For full page load GET, redirect to login
        header('Location: ' . BASE_URL . 'public/login.php');
        exit();
    }
}
// Ensure user is a verified vendor for GET actions (only if not already exited above)
$vendor->verifyVendorAccess();

// Re-fetch conversation details for rendering
$conversation_id = $_GET['conversation_id'] ?? null; // Get conversation_id from URL for GET requests
$csrf_token = generateCSRFToken(); // Generate for GET form


    // Fetch vendor user data needed for chat interface for GET requests
    $user_data = $user->getUserById($_SESSION['user_id']);

    $current_conversation = null;
    $messages = []; // Initialize empty. Will be populated below if conversation_id exists.
    $other_party = null;

    if ($conversation_id) {
        $chat->markMessagesAsRead($conversation_id, $_SESSION['user_id']); // Mark as read on full page load or AJAX refresh

        try {
            $stmt = $pdo->prepare("
                SELECT cc.*,
                           e.title as event_title,
                           CASE
                             WHEN cc.vendor_id = ? THEN CONCAT(u.first_name, ' ', u.last_name)
                             ELSE vp.business_name
                           END as other_party_name,
                           CASE
                             WHEN cc.vendor_id = ? THEN up_user.profile_image
                             ELSE up_vendor.profile_image
                           END as other_party_image
                    FROM chat_conversations cc
                    LEFT JOIN events e ON cc.event_id = e.id
                    LEFT JOIN users u ON cc.user_id = u.id -- This is the client user (cc.user_id)
                    LEFT JOIN user_profiles up_user ON u.id = up_user.user_id
                    -- For the vendor details (cc.vendor_id)
                    LEFT JOIN users u2 ON cc.vendor_id = u2.id
                    LEFT JOIN vendor_profiles vp ON u2.id = vp.user_id
                    LEFT JOIN user_profiles up_vendor ON u2.id = up_vendor.user_id
                    WHERE cc.id = ?
                    AND (cc.vendor_id = ? OR cc.user_id = ?)
                ");
                $params = [ // 5 parameters for 5 placeholders
                    $_SESSION['user_id'], // for first CASE (is current user the vendor?)
                    $_SESSION['user_id'], // for second CASE (is current user the vendor?)
                    $conversation_id,
                    $_SESSION['user_id'], // for first OR (is current user the vendor?)
                    $_SESSION['user_id']  // for second OR (is current user the client?)
                ];
                $stmt->execute($params);
                $current_conversation = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($current_conversation) {
                // Determine the 'other party' (the client in this vendor chat)
                $other_party_user_id = ($current_conversation['vendor_id'] == $_SESSION['user_id']) ? $current_conversation['user_id'] : $current_conversation['vendor_id'];

                // Ensure the 'other_party_name' and 'other_party_image' logic is correct
                $other_party = [
                    'id' => (int)$other_party_user_id,
                    'name' => htmlspecialchars((string)($current_conversation['other_party_name'] ?? 'Unknown User')),
                    'image' => htmlspecialchars((string)($current_conversation['other_party_image'] ?? ''))
                ];
                
                // If it's an AJAX request (polling), return JSON
                if ($is_ajax_request) { 
                    $last_message_id_for_polling = $_GET['last_message_id'] ?? null; 
                    $messages = $chat->getMessages($conversation_id, 100, 0, $last_message_id_for_polling);
                    echo json_encode([
                        'success' => true,
                        'messages' => array_values($messages) // Ensure it's a simple array
                    ]);
                    exit(); // Crucial: Exit after AJAX GET JSON response
                } else { // Initial page load
                    // Fetch all messages for initial rendering (oldest first by default from getMessages)
                    $messages = $chat->getMessages($conversation_id, 100); 
                }

            } else {
                // If conversation not found or not accessible to this user
                error_log("Vendor Chat GET: Conversation ID {$conversation_id} not found or not accessible to user {$_SESSION['user_id']}.");
                $conversation_id = null; // Clear conversation_id to show "select chat" view
            }
        } catch (PDOException $e) {
            error_log("Get conversation details error (vendor_chat.php): " . $e->getMessage());
            if ($is_ajax_request) {
                echo json_encode(['success' => false, 'error' => 'Failed to load conversation details for polling.']);
            } else {
                $_SESSION['error_message'] = "Could not load conversation details. Please try again.";
            }
            $current_conversation = null;
            $conversation_id = null; // Clear conversation_id on error
        }
    }
    
    // If other_party is not set by now (i.e., not an existing convo), set a default
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

    <link rel="stylesheet" href="<?= ASSETS_PATH ?>css/chat.css">

    <?php include 'header.php'; // Includes the main site header, which itself pulls in style.css and potentially page-specific CSS ?>

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
                             onclick="window.location.href='<?= BASE_URL ?>public/vendor_chat.php?conversation_id=<?php echo htmlspecialchars((string)$conv['id']); ?>'">
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
            <?php if ($conversation_id): /* Only show chat area if conversation is active */ ?>
                <div class="chat-header">
                    <div class="chat-header-avatar" style="background-image: url('<?php echo htmlspecialchars((string)($other_party['image'] ? BASE_URL . 'assets/uploads/users/' . $other_party['image'] : BASE_URL . 'assets/images/default-avatar.jpg')); ?>')"></div>
                    <div class="chat-header-info">
                        <div class="chat-header-title"><?php echo htmlspecialchars((string)($other_party['name'])); ?></div>
                        <div class="chat-header-subtitle">
                            <?php
                            if (isset($current_conversation['event_title']) && $current_conversation['event_title'] !== null) {
                                echo htmlspecialchars((string)$current_conversation['event_title']);
                            } else {
                                echo 'General Chat';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <div class="chat-messages" id="messages-container">
                    <?php 
                    // Initial message loading for the main page view
                    // $messages is already populated with all messages for the selected conversation (oldest first).
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

<?php
    include 'footer.php'; // Include footer for full page loads
?>

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
                    fetch('<?= BASE_URL ?>public/vendor_chat.php', {
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
                            window.location.href = data.redirect || '<?= BASE_URL ?>public/vendor_chat.php'; // Redirect to clear page or chat list
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

                let postUrl = '<?= BASE_URL ?>public/vendor_chat.php';
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

                fetch(`<?= BASE_URL ?>public/vendor_chat.php?conversation_id=${conversationId}&ajax=1&last_message_id=${lastMessageId}`) // Pass last_message_id
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
