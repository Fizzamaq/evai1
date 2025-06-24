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
require_once '../includes/auth.php';

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
        $event_id_for_creation = filter_var(($_POST['event_id_for_chat'] ?? null), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $vendor_id_for_creation = $_POST['vendor_id_for_chat'] ?? null;

        // --- REMOVED: Vendor-to-vendor chat blocking check ---
        /*
        $sender_user_type = $_SESSION['user_type'] ?? null;
        $recipient_user_type = null;

        if ($vendor_id_for_creation) {
            $recipient_user_data = $user->getUserById($vendor_id_for_creation);
            if ($recipient_user_data) {
                $recipient_user_type = $recipient_user_data['user_type_id'];
            }
        }
        
        if ($sender_user_type == 2 && $recipient_user_type == 2) {
            echo json_encode(['success' => false, 'error' => 'Vendor-to-vendor chat is not supported by the current system design.']);
            exit();
        }
        */
        // --- END REMOVED CHECK ---


        // Logic to find/create conversation if vendor_id is passed without conversation_id
        if (!$conversation_id && $vendor_id_for_creation) {
            $conversation_id = $chat->startConversation($event_id_for_creation, $_SESSION['user_id'], $vendor_id_for_creation);
            if (!$conversation_id) {
                error_log("Failed to start new conversation for user " . $_SESSION['user_id'] . " and vendor " . $vendor_id_for_creation);
                echo json_encode(['success' => false, 'error' => 'Failed to create new conversation.']);
                exit();
            }
        } elseif (!$conversation_id) { // If no conversation_id and no vendor_id for new creation
             error_log("Missing conversation ID or vendor_id for new conversation creation for user " . $_SESSION['user_id']);
             echo json_encode(['success' => false, 'error' => 'A conversation or vendor must be identified to send a message.']);
             exit();
        }


        // If conversation_id is now available (either pre-existing or just created), send message
        if ($conversation_id) {
            $message_sent = $chat->sendMessage($conversation_id, $_SESSION['user_id'], $message);
            if ($message_sent) {
                if (!empty($_POST['vendor_id_for_chat']) && empty($_GET['conversation_id'])) { // New conversation created via POST
                     echo json_encode(['success' => true, 'redirect_to_conversation' => BASE_URL . 'public/chat.php?conversation_id=' . $conversation_id]);
                } else { // Message sent to existing conversation
                     echo json_encode(['success' => true, 'message' => 'Message sent.']);
                }
                exit();
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to save message to database.']);
                exit();
            }
        } else {
            // This condition should ideally not be reached if the above logic is sound
            echo json_encode(['success' => false, 'error' => 'Conversation not identified after processing.']);
            exit();
        }
    } else {
        // If other POST actions were sent to this file, handle or error out
        echo json_encode(['success' => false, 'error' => 'Invalid POST action.']);
        exit();
    }
}

// --- END OF POST REQUEST HANDLING ---


// --- START: All GET Request Handling (only runs if not exited by POST block) ---

// Check user authentication for GET requests
if (!isset($_SESSION['user_id'])) {
    if ($is_ajax_request) { // For AJAX polling GET, output minimal content
        echo '<div class="chat-messages" id="messages-container"></div>'; // Minimal output
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

    // Handle AJAX GET Requests (for polling messages or partial content loads)
    if ($is_ajax_request) {
        $current_conversation = null;
        $messages = [];

        if ($conversation_id) {
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
                $params = [ // 5 parameters for 5 placeholders
                    $_SESSION['user_id'], // for first CASE
                    $_SESSION['user_id'], // for second CASE
                    $conversation_id,
                    $_SESSION['user_id'], // for first OR
                    $_SESSION['user_id']  // for second OR
                ];
                $stmt->execute($params);
                $current_conversation = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($current_conversation) {
                    $other_party = [
                        'id' => (int)(($current_conversation['user_id'] == $_SESSION['user_id']) ? $current_conversation['vendor_id'] : $current_conversation['user_id']),
                        'name' => htmlspecialchars((string)($current_conversation['other_party_name'] ?? 'Unknown User')),
                        'image' => htmlspecialchars((string)($current_conversation['other_party_image'] ?? ''))
                    ];

                    $messages = $chat->getMessages($conversation_id, 100);
                    $messages = array_reverse($messages); // Show oldest first

                    // Output only the messages container (partial HTML)
                    echo '<div class="chat-messages" id="messages-container">';
                    if (empty($messages)):
                        echo '<div class="empty-state">No messages yet</div>';
                    else:
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
                            <?php
                        endforeach;
                    endif;
                    echo '</div>'; // Close messages-container
                    exit(); // Crucial: Exit after AJAX GET response
                }
            } catch (PDOException $e) {
                error_log("Get conversation details for AJAX error: " . $e->getMessage());
                echo '<div class="chat-messages" id="messages-container"></div>'; // Send empty container on error
                exit();
            }
        }
        echo '<div class="chat-messages" id="messages-container"></div>'; // For AJAX GET requests with no conversation_id
        exit(); // Crucial: Exit for all AJAX GET requests
    }

    // --- START: Full HTML Page Rendering for non-AJAX GET requests (default page load) ---
    include 'header.php'; // Include header only for full page loads

    // Logic to find/create conversation if vendor_id is passed without conversation_id for initial page load
    if (!$conversation_id && $vendor_id_from_url) {
        $existing_conv = $chat->getConversationByParticipants($_SESSION['user_id'], (int)$vendor_id_from_url, (int)$event_id_from_url);
        if ($existing_conv) {
            $conversation_id = $existing_conv['id'];
            // If conversation found, redirect to URL with conversation_id to maintain clean state
            header('Location: ' . BASE_URL . 'public/chat.php?conversation_id=' . $conversation_id);
            exit();
        } else {
            // If no existing conversation found for vendor_id and event_id,
            // we will create a new one on first message send (handled by POST logic).
            // For now, prepare `other_party` info for display.
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
                        $event_details = $event->getEventById((int)$event_id_from_url, $_SESSION['user_id']);
                        if ($event_details) {
                            $current_conversation = ['event_title' => $event_details['title']];
                        }
                    }
                } else {
                    error_log("Error fetching vendor info: Vendor ID '{$vendor_id_from_url}' not found or no profile.");
                    $other_party = null; // Clear other_party if vendor not found
                    $_SESSION['error_message'] = "Invalid vendor selected to start chat.";
                }
            } catch (PDOException $e) {
                error_log("Error fetching vendor info for new chat initiation (GET): " . $e->getMessage());
                $other_party = null; // Clear other_party on error
            }
        }
    }


    $current_conversation_details = null; // Renamed to avoid conflict with $current_conversation used above for event_title
    $messages = []; // Clear messages for initial page load, they'll be fetched by JS if conversation_id exists
    
    if ($conversation_id) { // Only fetch messages if a conversation ID exists
        $chat->markMessagesAsRead($conversation_id, $_SESSION['user_id']);

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
                    $other_party = [
                        'id' => (int)(($current_conversation_details['user_id'] == $_SESSION['user_id']) ? $current_conversation_details['vendor_id'] : $current_conversation_details['user_id']),
                        'name' => htmlspecialchars((string)($current_conversation_details['other_party_name'] ?? 'Unknown User')),
                        'image' => htmlspecialchars((string)($current_conversation_details['other_party_image'] ?? ''))
                    ];
                    $messages = $chat->getMessages($conversation_id, 100);
                    $messages = array_reverse($messages);
                }
            } catch (PDOException $e) {
                error_log("Get conversation error: " . $e->getMessage());
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
            <?php if ($conversation_id || isset($vendor_id_from_url)): /* Only show chat area if conversation is active or initiating new with vendor_id */ ?>
                <div class="chat-header">
                    <div class="chat-header-avatar" style="background-image: url('<?php echo htmlspecialchars((string)($other_party['image'] ? BASE_URL . 'assets/uploads/users/' . $other_party['image'] : BASE_URL . 'assets/images/default-avatar.jpg')); ?>')"></div>
                    <div class="chat-header-info">
                        <div class="chat-header-title"><?php echo htmlspecialchars((string)($other_party['name'] ?? 'New Chat')); ?></div>
                        <div class="chat-header-subtitle">
                            <?php
                            if (isset($current_conversation_details['event_title']) && $current_conversation_details['event_title'] !== null) {
                                echo htmlspecialchars((string)$current_conversation_details['event_title']);
                            } elseif (isset($current_conversation['event_title'])) { // Fallback for new chat init path
                                echo htmlspecialchars((string)$current_conversation['event_title']);
                            } else {
                                echo 'General Chat';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <div class="chat-messages" id="messages-container">
                    <?php if (empty($messages) && empty($conversation_id)): /* For new chats before first message */ ?>
                        <div class="empty-state">Start your conversation!</div>
                    <?php elseif (empty($messages) && $conversation_id): /* For existing chats with no messages */?>
                        <div class="empty-state">No messages yet. Say hello!</div>
                    <?php else: ?>
                        <?php foreach ($messages as $message):
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
                        <input type="hidden" name="vendor_id_for_chat" value="<?= htmlspecialchars((string)($vendor_id_from_url ?? '')) ?>">
                        <input type="hidden" name="event_id_for_chat" value="<?= htmlspecialchars((string)($event_id_from_url ?? '')) ?>">
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
                    <h3>Select a conversation or start a new one</h3>
                    <p>Choose from your existing conversations on the left, or initiate a new chat by messaging a vendor from their profile or a booking.</p>
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

        // Elements for error display
        const chatErrorContainer = document.getElementById('chat-error-container');
        const chatErrorMessage = document.getElementById('chat-error-message');
        const chatRetryButton = document.getElementById('chat-retry-send');
        const chatDismissButton = document.getElementById('chat-dismiss-error');
        const messageInput = document.getElementById('message-input');
        const messageForm = document.querySelector('.message-form');

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

                        if (data.redirect_to_conversation) {
                            window.location.href = data.redirect_to_conversation;
                            return;
                        }
                        // Add message to display
                        const messagesContainer = document.getElementById('messages-container');
                        if (messagesContainer) {
                            const tempMsg = document.createElement('div');
                            tempMsg.className = 'message message-outgoing';
                            const contentDiv = document.createElement('div');
                            contentDiv.className = 'message-content';
                            contentDiv.textContent = message; // Use textContent for safety
                            
                            const timeSpan = document.createElement('span');
                            timeSpan.className = 'message-time';
                            timeSpan.textContent = 'Just now'; // Temporary client-side timestamp

                            tempMsg.appendChild(contentDiv);
                            tempMsg.appendChild(timeSpan);
                            messagesContainer.appendChild(tempMsg);
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
                const currentLastMessage = messagesContainer ? messagesContainer.lastElementChild : null;
                const lastMessageId = currentLastMessage ? parseInt(currentLastMessage.dataset.id) : 0;

                fetch(`<?= BASE_URL ?>public/chat.php?conversation_id=${conversationId}&ajax=1`)
                    .then(response => {
                        if (!response.ok) {
                            return response.text().then(text => {
                                console.error('Polling HTTP Error:', response.status, response.statusText, text);
                                throw new Error('Polling network response was not ok: ' + response.statusText + ' - ' + text);
                            });
                        }
                        return response.text(); // Expect HTML for polling
                    })
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const allFetchedMessages = doc.querySelectorAll('#messages-container .message');

                        let addedNew = false;
                        allFetchedMessages.forEach(fetchedMsg => {
                            const fetchedMsgId = parseInt(fetchedMsg.dataset.id);
                            // Only append new messages
                            if (fetchedMsgId > lastMessageId) {
                                messagesContainer.appendChild(fetchedMsg.cloneNode(true));
                                addedNew = true;
                            }
                        });

                        if (addedNew) {
                            scrollToBottom();
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
