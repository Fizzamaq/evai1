<?php
// classes/Chat.class.php

// You need to include PHPMailer. There are two main ways:

// OPTION 1 (Recommended for most modern PHP projects - using Composer):
// If you've installed PHPMailer via Composer (running 'composer require phpmailer/phpmailer' in your project root),
// then you should have a 'vendor' folder in your project root.
// Uncomment the following line and ensure the path is correct relative to this file.
require_once __DIR__ . '/../vendor/autoload.php'; // UNCOMMENT THIS LINE

// OPTION 2 (Manual PHPMailer installation - if you don't use Composer):
// If you manually downloaded PHPMailer and placed its 'src' folder (or renamed it to 'PHPMailer')
// inside your 'classes' directory (so you have classes/PHPMailer/PHPMailer.php etc.),
// then uncomment and adjust the paths below.
// require_once __DIR__ . '/PHPMailer/PHPMailer.php';
// require_once __DIR__ . '/PHPMailer/SMTP.php';
// require_once __DIR__ . '/PHPMailer/Exception.php';


// Make sure you have one of the above options uncommented and correctly configured.

// Use the PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Chat {
    private $conn;

    public function __construct($pdo) { // Pass PDO to constructor
        $this->conn = $pdo;
    }

    // --- NEW DEBUGGING FUNCTION (TEMPORARY) ---
    private function debugToFile($message) {
        $logFile = __DIR__ . '/chat_debug.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
    }
    // --- END NEW DEBUGGING FUNCTION ---


    /**
     * Start a new conversation.
     * @param int|null $event_id The ID of the event, or NULL for general inquiries.
     * @param int $user_id
     * @param int $vendor_id (user ID of the vendor)
     * @return int|false Conversation ID on success, false on failure.
     */
    public function startConversation($event_id, $user_id, $vendor_id) {
        // --- MODIFIED DEBUG LOGS to use debugToFile ---
        $this->debugToFile("startConversation: Attempting to create conversation with event_id={$event_id}, user_id={$user_id}, vendor_id={$vendor_id}");
        // --- END MODIFIED DEBUG LOGS ---

        try {
            // Check if conversation already exists to prevent duplicates
            // Now fetches status too
            $existing_conv = $this->getConversationByParticipants($user_id, $vendor_id, $event_id);
            if ($existing_conv) {
                // --- NEW FIX: If conversation is archived, reactivate it and ONLY update updated_at ---
                if ($existing_conv['status'] === 'archived') {
                    $reactivate_stmt = $this->conn->prepare("UPDATE chat_conversations SET status = 'active', updated_at = NOW() WHERE id = ?"); // Removed last_message_at update here
                    $reactivate_stmt->execute([(int)$existing_conv['id']]);
                    $this->debugToFile("startConversation: Reactivated archived conversation ID: " . $existing_conv['id']);
                } else {
                    // For active existing conversations, ONLY update updated_at (not last_message_at here)
                    $update_stmt = $this->conn->prepare("UPDATE chat_conversations SET updated_at = NOW() WHERE id = ?"); // Removed last_message_at update here
                    $update_stmt->execute([(int)$existing_conv['id']]);
                    $this->debugToFile("startConversation: Updated updated_at for existing conversation ID: " . $existing_conv['id']);
                }
                // --- END NEW FIX ---
                $this->debugToFile("startConversation: Existing conversation found: ID " . $existing_conv['id'] . ". Returning existing ID.");
                return $existing_conv['id'];
            }

            // Create new conversation
            $sql = "INSERT INTO chat_conversations
                (event_id, user_id, vendor_id, created_at, updated_at)
                VALUES (?, ?, ?, NOW(), NOW())"; // Added created_at and updated_at for completeness
            
            // --- MODIFIED DEBUG LOG ---
            $this->debugToFile("startConversation: Preparing SQL statement: " . $sql);
            // --- END MODIFIED DEBUG LOG ---
            
            $stmt = $this->conn->prepare($sql);

            // --- MODIFIED DEBUG LOG ---
            $this->debugToFile("startConversation: Executing statement with parameters: event_id=" . ($event_id ?? 'NULL') . ", user_id={$user_id}, vendor_id={$vendor_id}");
            // --- END MODIFIED DEBUG LOG ---

            $result = $stmt->execute([$event_id, (int)$user_id, (int)$vendor_id]); // Cast to int for safety

            if ($result) {
                $conversation_id = $this->conn->lastInsertId();
                // --- MODIFIED DEBUG LOG ---
                $this->debugToFile("startConversation: Successfully created new conversation with ID: " . $conversation_id . ".");
                // --- END MODIFIED DEBUG LOG ---
                return $conversation_id;
            } else {
                // Log specific PDO error if execution failed
                $errorInfo = $stmt->errorInfo();
                // --- MODIFIED ERROR LOG ---
                $this->debugToFile("Chat.class.php startConversation PDO execute failed. ErrorInfo: " . implode(" ", $errorInfo) . " for params: event_id=" . ($event_id ?? 'NULL') . ", user_id={$user_id}, vendor_id={$vendor_id} (caller should roll back).");
                // --- END MODIFIED ERROR LOG ---
                return false;
            }

        } catch (PDOException | Exception $e) {
            $this->debugToFile("Chat.class.php startConversation PDOException caught: " . $e->getMessage() . " (Code: " . $e->getCode() . ") SQLSTATE: " . ($e->errorInfo[0] ?? 'N/A') . " - Trace: " . $e->getTraceAsString() . " (caller should roll back).");
            return false;
        } catch (Exception $e) {
            $this->debugToFile("Chat.class.php startConversation General Exception caught: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Finds an existing conversation between a user and a vendor for a specific event.
     * If $event_id is null, it tries to find a general conversation between user and vendor.
     * @param int $user_id
     * @param int $vendor_id (user ID of the vendor)
     * @param int|null $event_id The event ID, or NULL for general conversations.
     * @return array|false Conversation details if found, false otherwise.
     */
    public function getConversationByParticipants($user_id, $vendor_id, $event_id = null) {
        try {
            // ADDED status to SELECT
            $sql = "SELECT id, status FROM chat_conversations WHERE user_id = ? AND vendor_id = ?"; 
            $params = [(int)$user_id, (int)$vendor_id]; // Cast to int for safety

            if ($event_id !== null) {
                $sql .= " AND event_id = ?";
                $params[] = (int)$event_id; // Cast to int for safety
            } else {
                $sql .= " AND event_id IS NULL"; // For general conversations
            }
            
            // --- MODIFIED DEBUG LOG ---
            $this->debugToFile("getConversationByParticipants: Checking for existing conversation with SQL: " . $sql . " and params: " . json_encode($params));
            // --- END MODIFIED DEBUG LOG ---

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            // --- MODIFIED DEBUG LOG ---
            if ($result) {
                $this->debugToFile("getConversationByParticipants: Found existing conversation ID: " . $result['id'] . ", Status: " . $result['status']);
            } else {
                $this->debugToFile("getConversationByParticipants: No existing conversation found.");
            }
            // --- END MODIFIED DEBUG LOG ---
            
            return $result;
        } catch (PDOException | Exception $e) {
            // --- MODIFIED ERROR LOG ---
            $this->debugToFile("Chat.class.php getConversationByParticipants PDO error: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
            // --- END MODIFIED DEBUG LOG ---
            return false;
        }
    }

    /**
     * Get details for a specific conversation by ID, with user ownership check.
     * This method is useful for verifying a conversation after creation or for displaying its header.
     * @param int $conversationId
     * @param int $userId The user ID attempting to access it (for ownership check).
     * @return array|false Conversation details if found and owned by user, false otherwise.
     */
    public function getConversationById(int $conversationId, int $userId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT cc.*,
                       e.title as event_title,
                       CASE
                         WHEN cc.user_id = ? THEN IFNULL(vp.business_name, CONCAT(u.first_name, ' ', u.last_name)) -- If customer (logged in user is cc.user_id), show business name or vendor's user name
                         ELSE CONCAT(u2.first_name, ' ', u2.last_name) -- If vendor (logged in user is cc.vendor_id), show customer's full name
                       END as other_party_name,
                       CASE
                         WHEN cc.user_id = ? THEN up_vendor.profile_image
                         ELSE up_user.profile_image
                       END as other_party_image
                FROM chat_conversations cc
                LEFT JOIN events e ON cc.event_id = e.id
                LEFT JOIN users u ON cc.vendor_id = u.id -- Alias for the vendor's user record
                LEFT JOIN vendor_profiles vp ON u.id = vp.user_id
                LEFT JOIN user_profiles up_vendor ON u.id = up_vendor.user_id
                LEFT JOIN users u2 ON cc.user_id = u2.id -- Alias for the customer's user record
                LEFT JOIN user_profiles up_user ON u2.id = up_user.user_id
                WHERE cc.id = ? AND (cc.user_id = ? OR cc.vendor_id = ?)
            ");
            $params = [(int)$userId, (int)$userId, (int)$conversationId, (int)$userId, (int)$userId];
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException | Exception $e) {
            $this->debugToFile("Chat.class.php getConversationById error: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
            return false;
        }
    }


    /**
     * Send a message and update conversation's last message time atomically.
     * @param int $conversation_id
     * @param int $sender_id
     * @param string $message
     * @param string $type
     * @param string|null $attachment
     * @return int|false Last inserted message ID on success, false on failure.
     */
    public function sendMessage($conversation_id, $sender_id, $message, $type = 'text', $attachment = null) {
        try {
            // 1. Insert the new message
            $stmt_insert = $this->conn->prepare("INSERT INTO chat_messages
                (conversation_id, sender_id, message_type, message_content, attachment_url)
                VALUES (?, ?, ?, ?, ?)");

            $insert_success = $stmt_insert->execute([
                (int)$conversation_id, // Cast to int for safety
                (int)$sender_id,      // Cast to int for safety
                $type,
                $message,
                $attachment
            ]);

            if (!$insert_success) {
                throw new PDOException("Failed to insert message into chat_messages table. ErrorInfo: " . implode(" ", $stmt_insert->errorInfo()));
            }

            $message_id = $this->conn->lastInsertId();

            // 2. Update conversation's last message time directly here
            $stmt_update_conv_time = $this->conn->prepare("UPDATE chat_conversations
                SET last_message_at = NOW(), updated_at = NOW() -- Added updated_at to ensure rowCount is non-zero
                WHERE id = ?");
            $update_conv_success = $stmt_update_conv_time->execute([(int)$conversation_id]);

            // Allow execution to continue even if rowCount is 0 for this update, as the message was sent.
            // This handles cases where MySQL returns rowCount=0 if timestamp value is identical.
            if (!$update_conv_success || $stmt_update_conv_time->rowCount() === 0) {
                $errorInfo = $stmt_update_conv_time->errorInfo();
                // Log a warning, but don't stop the message flow.
                $this->debugToFile("Chat.class.php sendMessage Warning: last_message_at update for conv_id: {$conversation_id} affected 0 rows. ErrorInfo: " . implode(" ", $errorInfo) . " Likely due to identical timestamp.");
            }

            return $message_id; // Return the new message ID on success (message itself was inserted)

        } catch (PDOException | Exception $e) {
            $this->debugToFile("Chat.class.php sendMessage PDO error: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
            return false;
        } catch (Exception $e) {
            $this->debugToFile("Chat.class.php sendMessage general error: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Removed: This private method is no longer used as its logic is inlined in sendMessage for robustness.
     * private function updateConversationTime($conversation_id) { ... }
     */

    /**
     * Get conversation messages.
     * @param int $conversation_id
     * @param int $limit
     * @param int $offset
     * @param int|null $last_message_id If provided, fetch messages with ID > this value.
     * @return array|false Messages ordered by oldest first, or false on error.
     */
    public function getMessages($conversation_id, $limit = 50, $offset = 0, $last_message_id = null) {
        try {
            $sql = "SELECT * FROM chat_messages WHERE conversation_id = ?";
            // No need for $params array if binding values by position directly
            // Removed: $params = [(int)$conversation_id];

            if ($last_message_id !== null) {
                $sql .= " AND id > ?";
                // Removed: $params[] = (int)$last_message_id;
            }

            $sql .= " ORDER BY created_at ASC, id ASC LIMIT ? OFFSET ?"; // Order oldest first for appending

            $stmt = $this->conn->prepare($sql);

            // Use bindValue for all parameters, ensuring types are correct and avoiding pass-by-reference issues
            $paramIndex = 1;
            $stmt->bindValue($paramIndex++, (int)$conversation_id, PDO::PARAM_INT);
            
            if ($last_message_id !== null) {
                $stmt->bindValue($paramIndex++, (int)$last_message_id, PDO::PARAM_INT);
            }
            $stmt->bindValue($paramIndex++, (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue($paramIndex++, (int)$offset, PDO::PARAM_INT);
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException | Exception $e) { // Catch both PDOException and general Exception
            $this->debugToFile("Chat.class.php getMessages error: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString()); 
            return false;
        }
    }

    /**
     * Get user conversations, excluding 'archived' ones.
     * @param int $user_id
     * @param int $limit
     * @return array|false
     */
    public function getUserConversations($user_id, $limit = 50) { // Changed default limit from 10 to 50
        try {
            $stmt = $this->conn->prepare("
                SELECT cc.*,
                       e.title as event_title, -- event_title might be NULL if event_id is NULL
                       CASE
                         WHEN cc.user_id = ? THEN IFNULL(vp.business_name, CONCAT(u.first_name, ' ', u.last_name)) -- Logged-in user is customer, show business name or vendor's user name
                         ELSE CONCAT(u2.first_name, ' ', u2.last_name) -- Logged-in user is vendor, show customer's full name
                       END as other_party_name,
                       CASE
                         WHEN cc.user_id = ? THEN up_vendor.profile_image
                         ELSE up_user.profile_image
                       END as other_party_image,
                       -- Use correlated subqueries to get the last message and its time
                       (SELECT message_content FROM chat_messages WHERE conversation_id = cc.id ORDER BY created_at DESC, id DESC LIMIT 1) as last_message,
                       (SELECT created_at FROM chat_messages WHERE conversation_id = cc.id ORDER BY created_at DESC, id DESC LIMIT 1) as last_message_time,
                       (SELECT COUNT(*) FROM chat_messages
                        WHERE conversation_id = cc.id AND sender_id != ? AND is_read = FALSE) as unread_count
                FROM chat_conversations cc
                LEFT JOIN events e ON cc.event_id = e.id -- Use LEFT JOIN because event_id can now be NULL
                LEFT JOIN users u ON cc.vendor_id = u.id -- Alias 'u' for the vendor's user record
                LEFT JOIN vendor_profiles vp ON u.id = vp.user_id
                LEFT JOIN user_profiles up_vendor ON u.id = up_vendor.user_id
                LEFT JOIN users u2 ON cc.user_id = u2.id -- Alias 'u2' for the customer's user record
                LEFT JOIN user_profiles up_user ON u2.id = up_user.user_id -- Customer's user_profile
                WHERE (cc.user_id = ? OR cc.vendor_id = ?)
                AND cc.status != 'archived' -- ADDED: Exclude archived chats
                GROUP BY cc.id -- Group by cc.id to ensure one row per conversation
                ORDER BY cc.last_message_at DESC, cc.id DESC -- Order by last_message_at primarily, then by ID for stability
                LIMIT ?
            ");
            // Parameters: first two for CASE statements, third for unread_count subquery, next two for WHERE clause, last for LIMIT
            $stmt->execute([(int)$user_id, (int)$user_id, (int)$user_id, (int)$user_id, (int)$user_id, (int)$limit]); // Cast all params to int
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC); // Fetch results

            // --- DEBUG LOGGING ADDED HERE ---
            $this->debugToFile("getUserConversations: Query returned " . count($results) . " conversations. Results: " . json_encode($results)); // Corrected here
            // --- END DEBUG LOGGING ---

            return $results;
        } catch (PDOException | Exception $e) { // Catch both PDOException and general Exception
            $this->debugToFile("Chat.class.php getUserConversations error: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString()); // Corrected here
            return false;
        }
    }

    /**
     * Mark a conversation as archived (soft delete).
     * This method will now rely on the caller for transaction management.
     * @param int $conversationId The ID of the conversation to archive.
     * @param int $userId The ID of the user performing the action (for authorization/ownership).
     * @return bool True on success, false on failure.
     */
    public function markConversationAsArchived($conversationId, $userId) {
        try {
            // Removed: $this->conn->beginTransaction(); // This method now relies on caller's transaction
            $stmt = $this->conn->prepare("
                UPDATE chat_conversations
                SET status = 'archived', updated_at = NOW()
                WHERE id = ? AND (user_id = ? OR vendor_id = ?)
            ");
            $result = $stmt->execute([(int)$conversationId, (int)$userId, (int)$userId]); // Cast to int for safety
            $this->debugToFile("markConversationAsArchived: Conversation ID {$conversationId} archived by user {$userId}. Rows affected: " . $stmt->rowCount());
            // Removed: $this->conn->commit(); // This method now relies on caller's transaction
            return $result;
        } catch (PDOException | Exception $e) {
            // Removed: $this->conn->rollBack(); // This method now relies on caller's transaction
            $this->debugToFile("markConversationAsArchived error: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString()); // Corrected here
            return false;
        }
    }

    /**
     * Mark messages as read
     * @param int $conversation_id
     * @param int $user_id
     * @return bool
     */
    public function markMessagesAsRead($conversation_id, $user_id) {
        try {
            $stmt = $this->conn->prepare("UPDATE chat_messages
                SET is_read = TRUE, read_at = NOW()
                WHERE conversation_id = ? AND sender_id != ? AND is_read = FALSE");
            return $stmt->execute([(int)$conversation_id, (int)$user_id]); // Cast to int for safety
        } catch (PDOException | Exception $e) {
            $this->debugToFile("Chat.class.php markMessagesAsRead error: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString()); // Corrected here
            return false;
        }
    }

    /**
     * Get unread message count
     * @param int $user_id
     * @return int
     */
    public function getUnreadCount($user_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as unread_count
                FROM chat_messages cm
                JOIN chat_conversations cc ON cm.conversation_id = cc.id
                WHERE (cc.user_id = ? OR cc.vendor_id = ?)
                AND cm.sender_id != ?
                AND cm.is_read = FALSE
                AND cc.status != 'archived' -- ADDED: Exclude archived chats from unread count
            ");
            $stmt->execute([(int)$user_id, (int)$user_id, (int)$user_id]); // Cast to int for safety
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['unread_count'] ?? 0;
        } catch (PDOException | Exception $e) {
            $this->debugToFile("Chat.class.php getUnreadCount error: " . $e->getTraceAsString()); // Corrected here
            return 0;
        }
    }
}
