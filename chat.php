<?php
// TEMPORARY: Enable full error reporting for debugging.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// public/chat.php
session_start();
require_once '../includes/config.php';
require_once '../classes/User.class.php';
require_once '../classes/Chat.class.php';
require_once '../classes/Event.class.php';
require_once '../includes/auth.php';

// Determine if this is an AJAX request (includes X-Requested-With header or custom 'ajax=1' param)
$is_ajax_request = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_GET['ajax']) && $_GET['ajax'] == 1);

// Initialize main variables needed across different request types
$conversation_id = $_GET['conversation_id'] ?? null;
$event_id_from_url = $_GET['event_id'] ?? null;
$vendor_id_from_url = $_GET['vendor_id'] ?? null;
$csrf_token = generateCSRFToken();

// Instantiate classes
$user = new User($pdo);
$chat = new Chat($pdo);
$event = new Event($pdo);


// --- START: Main Request Type Handling ---

// This section handles ALL POST requests for the file
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // All POST responses will be JSON and exit immediately.

    // Check user authentication for POST requests
    if (!isset($_SESSION['user_id'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'User not authenticated. Please log in.', 'redirect' => BASE_URL . 'public/login.php']);
        exit();
    }

    // Handle specific POST actions
    if (isset($_POST['send_message'])) { // This condition will now always be true due to hidden input
        // Validate CSRF token
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            error_log("CSRF token mismatch on chat message send for user " . ($_SESSION['user_id'] ?? 'N/A'));
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

        // Logic to find/create conversation if vendor_id is passed without conversation_id
        if (!$conversation_id) {
            $event_id_for_creation = filter_var(($_POST['event_id_for_chat'] ?? $event_id_from_url), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $vendor_id_for_creation = $_POST['vendor_id_for_chat'] ?? $vendor_id_from_url;

            if ($vendor_id_for_creation) {
                $conversation_id = $chat->startConversation($event_id_for_creation, $_SESSION['user_id'], $vendor_id_for_creation);
                if (!$conversation_id) {
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

        // If conversation_id is now available (either pre-existing or just created), send message
        if ($conversation_id) {
            $message_sent = $chat->sendMessage($conversation_id, $_SESSION['user_id'], $message);
            if ($message_sent) {
                header('Content-Type: application/json');
                if (!empty($_POST['vendor_id_for_chat']) && empty($_GET['conversation_id'])) { // New conversation created via POST
                     echo json_encode(['success' => true, 'redirect_to_conversation' => BASE_URL . 'public/chat.php?conversation_id=' . $conversation_id]);
                } else { // Message sent to existing conversation
                     echo json_encode(['success' => true, 'message' => 'Message sent.']);
                }
                exit();
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Failed to save message to database.']);
                exit();
            }
        }
    } else {
        // This 'else' block should ideally not be hit for message sending anymore
        // since the hidden input will always set $_POST['send_message'].
        // It remains as a safeguard for other POST actions if they exist.
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid POST action.']);
        exit();
    }
} // Correctly closing the main 'if ($_SERVER['REQUEST_METHOD'] === 'POST')' block

// --- Handle ALL GET Requests (AJAX or Full Page Load) ---
else { // This 'else' pairs correctly with the main 'if' above
    // Check user authentication for GET requests
    if (!isset($_SESSION['user_id'])) {
        if ($is_ajax_request) { // For polling, output minimal content
            echo '<div class="chat-messages" id="messages-container"></div>'; // Minimal output
            exit();
        } else { // Redirect for full page load
            header('Location: ' . BASE_URL . 'public/login.php');
            exit();
        }
    }
}
    // Fetch user data needed for chat interface
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
                        echo '<div class="empty-state">Start your conversation!</div>';
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
                    exit(); // Crucial: Exit after AJAX response
                }
            } catch (PDOException $e) {
                error_log("Get conversation details for AJAX error: " . $e->getMessage());
                echo '<div class="chat-messages" id="messages-container"></div>'; // Send empty container on error
                exit();
            }
        }
        echo '<div class="chat-messages" id="messages-container"></div>'; // For AJAX requests with no conversation_id
        exit(); // Crucial: Exit for all AJAX GET requests
    }

    // --- START: Full HTML Page Rendering for non-AJAX GET requests ---
    else { // This branch handles non-AJAX, full page GET requests
        include 'header.php'; // Include header only for full page loads

        // Logic to find/create conversation if vendor_id is passed without conversation_id
        if (!$conversation_id && $vendor_id_from_url) {
            $existing_conv = $chat->getConversationByParticipants($_SESSION['user_id'], $vendor_id_from_url, $event_id_from_url);
            if ($existing_conv) {
                $conversation_id = $existing_conv['id'];
                header('Location: ' . BASE_URL . 'public/chat.php?conversation_id=' . $conversation_id);
                exit();
            }
        }

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
                $current_conversation = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($current_conversation) {
                    $other_party = [
                        'id' => (int)(($current_conversation['user_id'] == $_SESSION['user_id']) ? $current_conversation['vendor_id'] : $current_conversation['user_id']),
                        'name' => htmlspecialchars((string)($current_conversation['other_party_name'] ?? 'Unknown User')),
                        'image' => htmlspecialchars((string)($current_conversation['other_party_image'] ?? ''))
                    ];
                    $messages = $chat->getMessages($conversation_id, 100);
                    $messages = array_reverse($messages);
                }
            } catch (PDOException $e) {
                error_log("Get conversation error: " . $e->getMessage());
            }
        } else if ($vendor_id_from_url) {
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
                    $current_conversation = ['vendor_id' => $vendor_id_from_url, 'event_id' => $event_id_from_url];
                    $messages = [];
                } else {
                    error_log("Error fetching vendor info: Vendor ID '{$vendor_id_from_url}' not found or no profile.");
                    $current_conversation = null;
                    $_SESSION['error_message'] = "Invalid vendor selected to start chat.";
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
            <?php if ($current_conversation || $vendor_id_from_url): ?>
                <div class="chat-header">
                    <div class="chat-header-avatar" style="background-image: url('<?php echo htmlspecialchars((string)($other_party['image'] ? BASE_URL . 'assets/uploads/users/' . $other_party['image'] : BASE_URL . 'assets/images/default-avatar.jpg')); ?>')"></div>
                    <div class="chat-header-info">
                        <div class="chat-header-title"><?php echo htmlspecialchars((string)($other_party['name'] ?? 'New Chat')); ?></div>
                        <div class="chat-header-subtitle">
                            <?php
                            if (isset($current_conversation['event_title']) && $current_conversation['event_title'] !== null) {
                                echo htmlspecialchars((string)$current_conversation['event_title']);
                            } elseif ($vendor_id_from_url && !$conversation_id) {
                                $new_chat_event_title = '';
                                if (!empty($event_id_from_url)) {
                                    $new_chat_event = $event->getEventById((int)$event_id_from_url, $_SESSION['user_id']);
                                    $new_chat_event_title = $new_chat_event['title'] ?? '';
                                }
                                echo htmlspecialchars((string)$new_chat_event_title ?: 'General Chat');
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
                        <input type="hidden" name="vendor_id_for_chat" value="<?= htmlspecialchars((string)($vendor_id_from_url ?? '')) ?>">
                        <input type="hidden" name="event_id_for_chat" value="<?= htmlspecialchars((string)($event_id_from_url ?? '')) ?>">
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
                    <p>Choose from your existing conversations on the left, or initiate a new chat by messaging a vendor from their profile or a booking.</p>
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
            e.preventDefault();
            const form = this;
            const messageInput = document.getElementById('message-input');
            const message = messageInput.value.trim();

            if (message) {
                let postUrl = '<?= BASE_URL ?>public/chat.php';
                const currentConversationId = '<?php echo htmlspecialchars((string)($conversation_id ?? '')); ?>';
                if (currentConversationId) {
                    postUrl += `?conversation_id=${currentConversationId}`;
                }

                fetch(postUrl, {
                    method: 'POST',
                    body: new URLSearchParams(new FormData(form)),
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).then(response => {
                    if (!response.ok) {
                        return response.text().then(text => {
                            console.error('HTTP Error:', response.status, response.statusText, text);
                            throw new Error('Network response was not ok: ' + response.statusText + ' - ' + text);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        messageInput.value = '';
                        if (data.redirect_to_conversation) {
                            window.location.href = data.redirect_to_conversation;
                            return;
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

                fetch(`<?= BASE_URL ?>public/chat.php?conversation_id=${conversationId}&ajax=1`)
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
