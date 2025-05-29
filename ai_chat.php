<?php
// TEMPORARY: Enable full error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../includes/config.php';
require_once '../includes/ai_functions.php'; // For AI_Assistant class
require_once '../classes/Event.class.php';   // For Event class (if saving event later)
require_once '../classes/User.class.php';    // For User data

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // For AJAX requests, send a JSON error response instead of redirecting
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'User not authenticated. Please log in.', 'redirect' => BASE_URL . 'public/login.php']);
        exit();
    } else {
        header("Location: " . BASE_URL . "public/login.php");
        exit();
    }
}

// Ensure $pdo is accessible here if it wasn't by includes
// global $pdo; 

// --- AI Chat Session Management ---
$session_id = $_GET['session_id'] ?? null;
$current_session = null;
$messages = []; // Array to store chat history

// Function to fetch chat session from DB or create a new one
function getOrCreateChatSession($pdo, $user_id, $session_id = null) {
    if ($session_id) {
        $stmt = $pdo->prepare("SELECT * FROM ai_chat_sessions WHERE id = ? AND user_id = ?");
        $stmt->execute([$session_id, $user_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($session) {
            return $session;
        }
    }

    // If no session found or invalid, create a new one
    $session_token = bin2hex(random_bytes(16)); // Generate a unique token
    $stmt = $pdo->prepare("INSERT INTO ai_chat_sessions (user_id, session_token, current_step, status) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $session_token, 'start', 'active']); // 'start' is the initial step
    $new_session_id = $pdo->lastInsertId();

    return [
        'id' => $new_session_id,
        'user_id' => $user_id,
        'session_token' => $session_token,
        'event_data' => null,
        'current_step' => 'start',
        'completed_steps' => null,
        'context_data' => null,
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
}

// Function to save a message to DB
function saveMessage($pdo, $session_id, $message_type, $message_content, $intent = null, $entities = null) {
    $stmt = $pdo->prepare("INSERT INTO ai_chat_messages (session_id, message_type, message_content, intent, entities) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$session_id, $message_type, $message_content, $intent, $entities ? json_encode($entities) : null]);
}

// Function to get messages for a session
function getMessages($pdo, $session_id) {
    $stmt = $pdo->prepare("SELECT * FROM ai_chat_messages WHERE session_id = ? ORDER BY created_at ASC");
    $stmt->execute([$session_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to update session state (e.g., current_step, event_data)
function updateSession($pdo, $session_id, $data) {
    $set_clauses = [];
    $params = [];
    foreach ($data as $key => $value) {
        $set_clauses[] = "$key = ?";
        $params[] = is_array($value) ? json_encode($value) : $value;
    }
    $params[] = $session_id;

    $sql = "UPDATE ai_chat_sessions SET " . implode(', ', $set_clauses) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

// --- IMPORTANT: Handle AJAX POST requests first, before any HTML output ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message_content'])) {
    header('Content-Type: application/json'); // Respond with JSON for AJAX

    $current_session = getOrCreateChatSession($pdo, $user_id, $session_id); // Ensure session is loaded for POST
    $user_message_content = trim($_POST['message_content']);
    saveMessage($pdo, $current_session['id'], 'user', $user_message_content);

    $ai_response_content = '';
    $next_step = $current_session['current_step'];
    $event_data = json_decode($current_session['event_data'] ?? '{}', true);

    switch ($current_session['current_step']) {
        case 'start':
            $ai_response_content = "Hello! I'm your Event Planning AI. What type of event are you planning?";
            $next_step = 'ask_event_type';
            break;

        case 'ask_event_type':
            $event_data['event_type_name'] = $user_message_content;
            
            $stmt = $pdo->prepare("SELECT id FROM event_types WHERE LOWER(type_name) LIKE ?");
            $stmt->execute(['%' . strtolower($user_message_content) . '%']);
            $matched_type = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($matched_type) {
                $event_data['event_type_id'] = $matched_type['id'];
                $ai_response_content = "Got it, a " . htmlspecialchars($user_message_content) . ". And for how many guests do you expect?";
                $next_step = 'ask_guest_count';
            } else {
                $ai_response_content = "Hmm, I'm not familiar with that event type. Could you describe it briefly, or choose from common types like 'Wedding', 'Birthday Party', 'Corporate Event'?";
                $next_step = 'ask_event_type';
            }
            break;

        case 'ask_guest_count':
            if (is_numeric($user_message_content) && (int)$user_message_content > 0) {
                $event_data['guest_count'] = (int)$user_message_content;
                $ai_response_content = "Perfect! What's your approximate budget for this event (e.g., $5000 - $10000)?";
                $next_step = 'ask_budget';
            } else {
                $ai_response_content = "Please provide a valid number for the guest count (e.g., '50', '200').";
                $next_step = 'ask_guest_count';
            }
            break;

        case 'ask_budget':
            if (preg_match('/\$?(\d+)\s*(?:-\s*\$?(\d+))?/', $user_message_content, $matches)) {
                $budget_min = (float)$matches[1];
                $budget_max = isset($matches[2]) ? (float)$matches[2] : $budget_min;
                
                $event_data['budget_min'] = $budget_min;
                $event_data['budget_max'] = $budget_max;
                
                $ai_response_content = "Okay, understood your budget. When is the event date? (e.g.,YYYY-MM-DD)";
                $next_step = 'ask_event_date';
            } else {
                $ai_response_content = "Please provide a valid budget, for example, '$5000' or '$5000 - $10000'.";
                $next_step = 'ask_budget';
            }
            break;

        case 'ask_event_date':
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $user_message_content)) {
                $event_data['event_date'] = $user_message_content;
                $ai_response_content = "Great! Do you have a specific location or venue in mind? (e.g., 'The Grand Ballroom, New York' or 'My backyard')";
                $next_step = 'ask_location';
            } else {
                $ai_response_content = "Please provide the date in YYYY-MM-DD format (e.g., 2025-06-15).";
                $next_step = 'ask_event_date';
            }
            break;
        
        case 'ask_location':
            $event_data['location_string'] = $user_message_content;
            $ai_response_content = "Got it. Now, could you tell me about any specific services you think you'll need? (e.g., 'Catering', 'Photography', 'Music', 'Decorations')";
            $next_step = 'ask_services';
            break;

        case 'ask_services':
            $event_data['special_requirements'] = ($event_data['special_requirements'] ?? '') . "\nServices requested: " . $user_message_content;
            
            $ai_response_content = "Understood. I have enough information to create a draft event plan. Would you like to save this event plan now? (Yes/No)";
            $next_step = 'confirm_save';
            break;

        case 'confirm_save':
            if (strtolower($user_message_content) === 'yes') {
                $event_to_save = [
                    'user_id' => $user_id,
                    'title' => ($event_data['event_type_name'] ?? 'AI Planned') . ' Event' . (isset($event_data['event_date']) ? ' on ' . $event_data['event_date'] : ''),
                    'description' => 'Planned with AI Assistant. ' . ($event_data['special_requirements'] ?? ''),
                    'event_type_id' => $event_data['event_type_id'] ?? 1,
                    'event_date' => $event_data['event_date'] ?? date('Y-m-d', strtotime('+1 month')),
                    'event_time' => null,
                    'end_date' => null,
                    'end_time' => null,
                    'location_string' => $event_data['location_string'] ?? '',
                    'venue_name' => null,
                    'venue_address' => null,
                    'venue_city' => null,
                    'venue_state' => null,
                    'venue_country' => null,
                    'venue_postal_code' => null,
                    'guest_count' => $event_data['guest_count'] ?? null,
                    'budget_min' => $event_data['budget_min'] ?? null,
                    'budget_max' => $event_data['budget_max'] ?? null,
                    'status' => 'planning',
                    'special_requirements' => $event_data['special_requirements'] ?? null,
                    'ai_preferences' => json_encode(['conversation_plan' => $event_data]),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'services_needed_array' => [] 
                ];

                try {
                    $new_event_id = $event_class->createEvent($event_to_save);
                    
                    if ($new_event_id) {
                        $ai_response_content = "Great! Your event has been saved! You can view it here: <a href='" . BASE_URL . "public/event.php?id=" . $new_event_id . "'>View Event</a>. Would you like me to suggest some vendors for this event?";
                        $next_step = 'ask_vendor_suggestions';
                        updateSession($pdo, $current_session['id'], ['event_data' => json_encode($event_data), 'current_step' => $next_step, 'context_data' => json_encode(['event_id' => $new_event_id])]);
                    } else {
                        $ai_response_content = "I encountered an error saving your event. Please try again later. Or would you like to continue planning?";
                        $next_step = 'confirm_save';
                    }
                } catch (Exception $e) {
                    error_log("AI Chat - Save Event Error: " . $e->getMessage());
                    $ai_response_content = "Oops! Something went wrong while trying to save your event. Error: " . htmlspecialchars($e->getMessage()) . ". Would you like to try again?";
                    $next_step = 'confirm_save';
                }
            } else {
                $ai_response_content = "Okay, I won't save it as an event for now. What else can I help you with? Or would you like to restart planning?";
                $next_step = 'restart_or_continue';
            }
            break;
        
        case 'ask_vendor_suggestions':
            if (strtolower($user_message_content) === 'yes') {
                $context_data = json_decode($current_session['context_data'] ?? '{}', true);
                $event_id_for_vendors = $context_data['event_id'] ?? null;
                if ($event_id_for_vendors) {
                    $ai_response_content = "Finding the best vendors for your event... This feature is under development, but you can explore vendors manually from your dashboard.";
                    $next_step = 'end_chat';
                } else {
                    $ai_response_content = "I couldn't find the event details to suggest vendors. Would you like to start a new plan?";
                    $next_step = 'restart_or_continue';
                }
            } else {
                $ai_response_content = "Okay, no vendor suggestions for now. What else can I help you with?";
                $next_step = 'restart_or_continue';
            }
            break;

        case 'restart_or_continue':
            if (strtolower($user_message_content) === 'restart') {
                updateSession($pdo, $current_session['id'], ['current_step' => 'start', 'event_data' => null, 'context_data' => null]);
                $ai_response_content = "Okay, let's start fresh! What type of event are you planning?";
                $next_step = 'ask_event_type';
            } else {
                $ai_response_content = "Alright, let me know if you need anything else.";
                $next_step = 'end_chat';
            }
            break;

        case 'end_chat':
            $ai_response_content = "It was great chatting with you! Feel free to come back anytime. Goodbye!";
            $next_step = 'end_chat';
            break;

        default:
            $ai_response_content = "I'm not sure how to respond to that. Can you please rephrase?";
            break;
    }

    saveMessage($pdo, $current_session['id'], 'ai', $ai_response_content);
    updateSession($pdo, $current_session['id'], ['current_step' => $next_step, 'event_data' => json_encode($event_data), 'context_data' => json_encode($context_data ?? [])]);

    $updated_messages = getMessages($pdo, $current_session['id']);
    echo json_encode(['success' => true, 'messages' => $updated_messages, 'current_step' => $next_step]);
    exit();
}

// --- Main page load logic (initial setup) ---
// Initialize session if not set via POST, and retrieve messages
$current_session = getOrCreateChatSession($pdo, $user_id, $session_id);
$messages = getMessages($pdo, $current_session['id']);

// Initial AI greeting for new sessions (if no POST or initial load)
if (empty($messages) && $current_session['current_step'] === 'start') {
    $initial_ai_message = "Hello! I'm your Event Planning AI. What type of event are you planning?";
    saveMessage($pdo, $current_session['id'], 'ai', $initial_ai_message);
    updateSession($pdo, $current_session['id'], ['current_step' => 'ask_event_type']);
    $messages = getMessages($pdo, $current_session['id']); // Refresh messages to include initial greeting
}

// INCLUDE HEADER AND FOOTER ONLY FOR NON-AJAX REQUESTS (Normal page load)
include 'header.php';
?>
<div class="ai-chat-wrapper">
    <div class="ai-chat-header">
        <h2>EventCraftAI Assistant</h2>
        <button id="downloadChatBtn" class="btn btn-secondary btn-sm">Download Chat</button>
    </div>
    <div class="ai-chat-messages" id="ai-chat-messages">
        <?php foreach ($messages as $message): ?>
            <div class="message-bubble <?= $message['message_type'] === 'user' ? 'user-message' : 'ai-message' ?>">
                <div class="message-content"><?= nl2br(htmlspecialchars($message['message_content'])) ?></div>
                <div class="message-time"><?= date('H:i', strtotime($message['created_at'])) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="ai-chat-input">
        <textarea id="messageInput" placeholder="Type your message..." rows="1"></textarea>
        <button id="sendMessageBtn" class="btn btn-primary">Send</button>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chatMessagesContainer = document.getElementById('ai-chat-messages');
    const messageInput = document.getElementById('messageInput');
    const sendMessageBtn = document.getElementById('sendMessageBtn');
    const downloadChatBtn = document.getElementById('downloadChatBtn');
    const currentSessionId = <?= json_encode($current_session['id']) ?>;

    function scrollToBottom() {
        chatMessagesContainer.scrollTop = chatMessagesContainer.scrollHeight;
    }

    function addMessageToDisplay(message) {
        const messageBubble = document.createElement('div');
        messageBubble.classList.add('message-bubble');
        messageBubble.classList.add(message.message_type === 'user' ? 'user-message' : 'ai-message');
        
        const content = document.createElement('div');
        content.classList.add('message-content');
        content.innerHTML = message.message_content.replace(/\n/g, '<br>');
        
        const time = document.createElement('div');
        time.classList.add('message-time');
        time.textContent = new Date(message.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        messageBubble.appendChild(content);
        messageBubble.appendChild(time);
        chatMessagesContainer.appendChild(messageBubble);
        scrollToBottom();
    }

    // Initial scroll on page load
    scrollToBottom();

    // Send message logic
    sendMessageBtn.addEventListener('click', sendMessage);
    messageInput.addEventListener('keydown', function(event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage();
        }
    });

    function sendMessage() {
        const messageContent = messageInput.value.trim();
        if (messageContent === '') return;

        addMessageToDisplay({
            message_type: 'user',
            message_content: messageContent,
            created_at: new Date().toISOString()
        });

        messageInput.value = '';
        messageInput.style.height = 'auto';

        const formData = new URLSearchParams();
        formData.append('message_content', messageContent);

        fetch('<?= BASE_URL ?>public/ai_chat.php?session_id=' + currentSessionId, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData
        })
        .then(response => {
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
                chatMessagesContainer.innerHTML = '';
                data.messages.forEach(msg => addMessageToDisplay(msg));
            } else {
                console.error('Error from server (data.success is false):', data.error);
                addMessageToDisplay({
                    message_type: 'ai',
                    message_content: 'Sorry, I encountered an error: ' + (data.error || 'Unknown server error.'),
                    created_at: new Date().toISOString()
                });
                if (data.redirect) {
                    alert(data.error || 'You have been logged out.');
                    window.location.href = data.redirect;
                }
            }
        })
        .catch(error => {
            console.error('Fetch error (caught by JS):', error);
            addMessageToDisplay({
                message_type: 'ai',
                message_content: 'Network error or unable to get response. Please try again. Check console for details.',
                created_at: new Date().toISOString()
            });
        });
    }

    messageInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });

    downloadChatBtn.addEventListener('click', function() {
        const chatHistory = [];
        document.querySelectorAll('.message-bubble').forEach(bubble => {
            const sender = bubble.classList.contains('user-message') ? 'You' : 'AI';
            const time = bubble.querySelector('.message-time').textContent;
            const content = bubble.querySelector('.message-content').innerText;
            chatHistory.push(`${time} - ${sender}: ${content}`);
        });

        const chatText = chatHistory.join('\n');
        const blob = new Blob([chatText], { type: 'text/plain;charset=utf-8' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'eventcraftai_chat_log.txt';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(a.href);
    });

});
</script>
