<?php
// TEMPORARY: Enable full error reporting for debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// public/vendor_chat.php
session_start();
require_once '../includes/config.php';
require_once '../classes/User.class.php';
require_once '../classes/Chat.class.php';
require_once '../classes/Event.class.php';
require_once '../classes/Vendor.class.php';
require_once '../includes/auth.php';

// Determine if this is an AJAX request
$is_ajax_request = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_GET['ajax']) && $_GET['ajax'] == 1);

// Initialize main variables needed across different request types
$conversation_id = $_GET['conversation_id'] ?? null;
$csrf_token = generateCSRFToken();

// Instantiate classes
$vendor = new Vendor($pdo);
$user = new User($pdo);
$chat = new Chat($pdo);
$event = new Event($pdo);


// --- START: Main Request Type Handling ---

// This section handles ALL POST requests for the file
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // All POST responses will be JSON and exit immediately.

    // Check user authentication and vendor access for POST requests
    if (!isset($_SESSION['user_id'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'User not authenticated. Please log in.', 'redirect' => BASE_URL . 'public/login.php']);
        exit();
    }
    $vendor->verifyVendorAccess(); // Ensure user is a verified vendor for POST actions

    // Handle specific POST actions
    if (isset($_POST['send_message'])) {
        // Validate CSRF token
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            error_log("CSRF token mismatch on vendor chat message send for user " . ($_SESSION['user_id'] ?? 'N/A'));
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid request. Please refresh the page and try again.']);
            exit();
        }

        $message = trim($_POST['message']);

        if (empty($message)) {
             header('Content-Type: application/json');
             echo json_encode(['success' => false, 'error' => 'Message cannot be empty.']);
             exit();
        }

        // Vendors should always be responding within an existing conversation.
        if ($conversation_id) {
            $message_sent = $chat->sendMessage($conversation_id, $_SESSION['user_id'], $message); // Sender is the vendor's user_id
            if ($message_sent) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Message sent.']);
                exit();
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Failed to save message to database.']);
                exit();
            }
        } else {
            error_log("Failed to send message: No conversation ID provided for vendor response.");
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Failed to send message. Conversation not identified.']);
            exit();
        }
    } else {
        // If other POST actions were sent to this file, handle or error out
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid POST action.']);
        exit();
    }
} // Correctly closing the main 'if ($_SERVER['REQUEST_METHOD'] === 'POST')' block

// --- Handle ALL GET Requests (AJAX or Full Page Load) ---
else  // This 'else' pairs correctly with the main 'if' above
    // Check user authentication and vendor access for GET requests
    if (!isset($_SESSION['user_id'])) {
        if ($is_ajax_request) { // For polling, output minimal content
            echo '<div class="chat-messages" id="messages-container"></div>'; // Minimal output
            exit();
        } else { // Redirect for full page load
            header('Location: ' . BASE_URL . 'public/login.php');
            exit();
        }
    }
    $vendor->verifyVendorAccess(); // Ensure user is a verified vendor for GET actions

    // Fetch vendor user data needed for chat interface
    $user_data = $user->getUserById($_SESSION['user_id']);

    // --- Handle AJAX GET Requests (for polling messages or partial content loads) ---
    if ($is_ajax_request) {
        $current_conversation = null;
        $messages = [];

        if ($conversation_id) {
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
                    LEFT JOIN users u ON cc.user_id = u.id
                    LEFT JOIN user_profiles up_user ON u.id = up_user.user_id
                    LEFT JOIN users u2 ON cc.vendor_id = u2.id
                    LEFT JOIN vendor_profiles vp ON u2.id = vp.user_id
                    LEFT JOIN user_profiles up_vendor ON u2.id = up_vendor.user_id
                    WHERE cc.id = ?
                    AND (cc.vendor_id = ? OR cc.user_id = ?)
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
                    exit();
                }
            } catch (PDOException $e) {
                error_log("Get conversation details for AJAX error (vendor_chat.php): " . $e->getMessage());
                echo '<div class="chat-messages" id="messages-container"></div>'; // Send empty container on error
                exit();
            }
        }
        echo '<div class="chat-messages" id="messages-container"></div>'; // For AJAX requests with no conversation_id
        exit();
    }

    // --- START: Full HTML Page Rendering for non-AJAX GET requests ---
    else {
        include 'header.php'; // Include header only for full page loads

        $current_conversation = null;
        $messages = [];
        $other_party = null;

        if ($conversation_id) {
            $chat->markMessagesAsRead($conversation_id, $_SESSION['user_id']); // Mark as read on full page load

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
                    LEFT JOIN users u ON cc.user_id = u.id
                    LEFT JOIN user_profiles up_user ON u.id = up_user.user_id
                    LEFT JOIN users u2 ON cc.vendor_id = u2.id
                    LEFT JOIN vendor_profiles vp ON u2.id = vp.user_id
                    LEFT JOIN user_profiles up_vendor ON u2.id = up_vendor.user_id
                    WHERE cc.id = ?
                    AND (cc.vendor_id = ? OR cc.user_id = ?)
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
                        'id' => (int)(($current_conversation['vendor_id'] == $_SESSION['user_id']) ? $current_conversation['user_id'] : $current_conversation['vendor_id']),
                        'name' => htmlspecialchars((string)($current_conversation['other_party_name'] ?? 'Unknown User')),
                        'image' => htmlspecialchars((string)($current_conversation['other_party_image'] ?? ''))
                    ];
                    $messages = $chat->getMessages($conversation_id, 100);
                    $messages = array_reverse($messages); // Display oldest messages first
                }
            } catch (PDOException $e) {
                error_log("Get conversation details error (vendor_chat.php): " . $e->getMessage());
                $_SESSION['error_message'] = "Could not load conversation details. Please try again.";
                $current_conversation = null;
            }
        }

        // Get user's conversations for the sidebar
        $conversations = $chat->getUserConversations($_SESSION['user_id']);
        $unread_count = $chat->getUnreadCount($_SESSION['user_id']);
?>

    <link rel="stylesheet" href="<?= ASSETS_PATH ?>css/chat.css">

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
                    <?php if (empty($messages)): ?>
                        <div class="empty-state">Start your conversation!</div>
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
                <div class="chat-input">
                    <form class="message-form" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)$csrf_token) ?>">
                        <input type="hidden" name="send_message" value="1"> <textarea
                            class="message-input"
                            name="message"
                            placeholder="Type your message..."
                            id="message-input"
                            required
                        ></textarea>
                        <button type="submit" name="send_button_submit" class="send-button"> <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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

<?php
    include 'footer.php'; // Include footer for full page loads
} // Correctly closing the main 'else' (full page GET requests) block
?>

    <script>
        // Auto-scroll to bottom of messages
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
                let postUrl = '<?= BASE_URL ?>public/vendor_chat.php'; // Corrected for vendor_chat.php
                const currentConversationId = '<?php echo htmlspecialchars((string)($conversation_id ?? '')); ?>';
                if (currentConversationId) {
                    postUrl += `?conversation_id=${currentConversationId}`; // Append conversation ID for existing chats
                }

                // Send message via Fetch API
                fetch(postUrl, {
                    method: 'POST',
                    body: new URLSearchParams(new FormData(form)), // Format data as URL-encoded
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest' // ADDED THIS HEADER
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => {
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

        // Polling for new messages
        setInterval(() => {
            const conversationId = '<?php echo htmlspecialchars((string)($conversation_id ?? '')); ?>';
            if (conversationId) {
                const messagesContainer = document.getElementById('messages-container');
                const currentLastMessage = messagesContainer ? messagesContainer.lastElementChild : null;
                const lastMessageId = currentLastMessage ? parseInt(currentLastMessage.dataset.id) : 0;

                fetch(`<?= BASE_URL ?>public/vendor_chat.php?conversation_id=${conversationId}&ajax=1`) // Corrected for vendor_chat.php
                    .then(response => {
                        if (!response.ok) {
                            return response.text().then(text => {
                                console.error('Polling HTTP Error:', response.status, response.statusText, text);
                                throw new Error('Polling network response was not ok: ' + response.statusText + ' - ' + text);
                            });
                        }
                        return response.text();
                    })
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const allFetchedMessages = doc.querySelectorAll('#messages-container .message');

                        let addedNew = false;
                        allFetchedMessages.forEach(fetchedMsg => {
                            const fetchedMsgId = parseInt(fetchedMsg.dataset.id);
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
