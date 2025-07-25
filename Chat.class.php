<?php
// classes/Chat.class.php
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
            $existing_conv = $this->getConversationByParticipants($user_id, $vendor_id, $event_id);
            if ($existing_conv) {
                // --- MODIFIED DEBUG LOG ---
                $this->debugToFile("startConversation: Existing conversation found: ID " . $existing_conv['id'] . ". Returning existing ID.");
                // --- END MODIFIED DEBUG LOG ---
                return $existing_conv['id'];
            }

            // Start a transaction specifically for the new conversation creation
            $this->conn->beginTransaction();

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

            $result = $stmt->execute([$event_id, $user_id, $vendor_id]);

            if ($result) {
                $conversation_id = $this->conn->lastInsertId();
                $this->conn->commit(); // Commit the transaction for the new conversation
                // --- MODIFIED DEBUG LOG ---
                $this->debugToFile("startConversation: Successfully created new conversation with ID: " . $conversation_id . " and committed.");
                // --- END MODIFIED DEBUG LOG ---
                return $conversation_id;
            } else {
                $this->conn->rollBack(); // Rollback if insert failed
                // Log specific PDO error if execution failed
                $errorInfo = $stmt->errorInfo();
                // --- MODIFIED ERROR LOG ---
                $this->debugToFile("Chat.class.php startConversation PDO execute failed. ErrorInfo: " . implode(" ", $errorInfo) . " for params: event_id=" . ($event_id ?? 'NULL') . ", user_id={$user_id}, vendor_id={$vendor_id} (rolled back).");
                // --- END MODIFIED ERROR LOG ---
                return false;
            }

        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) { // Check if a transaction is active before rolling back
                $this->conn->rollBack();
            }
            // Log the full PDOException message
            // --- MODIFIED ERROR LOG ---
            $this->debugToFile("Chat.class.php startConversation PDOException caught: " . $e->getMessage() . " (Code: " . $e->getCode() . ") SQLSTATE: " . ($e->errorInfo[0] ?? 'N/A') . " - Trace: " . $e->getTraceAsString() . " (rolled back).");
            // --- END MODIFIED ERROR LOG ---
            return false;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) { // Check if a transaction is active before rolling back
                $this->conn->rollBack();
            }
            // Catch other general exceptions
            // --- MODIFIED ERROR LOG ---
            $this->debugToFile("Chat.class.php startConversation General Exception caught: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString() . " (rolled back).");
            // --- END MODIFIED ERROR LOG ---
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
            $sql = "SELECT id FROM chat_conversations WHERE user_id = ? AND vendor_id = ?";
            $params = [$user_id, $vendor_id];

            if ($event_id !== null) {
                $sql .= " AND event_id = ?";
                $params[] = $event_id;
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
                $this->debugToFile("getConversationByParticipants: Found existing conversation ID: " . $result['id']);
            } else {
                $this->debugToFile("getConversationByParticipants: No existing conversation found.");
            }
            // --- END MODIFIED DEBUG LOG ---
            
            return $result;
        } catch (PDOException $e) {
            // --- MODIFIED ERROR LOG ---
            $this->debugToFile("Chat.class.php getConversationByParticipants PDO error: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
            // --- END MODIFIED DEBUG LOG ---
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
            $this->conn->beginTransaction(); // Transaction starts here

            // 1. Insert the new message
            $stmt_insert = $this->conn->prepare("INSERT INTO chat_messages
                (conversation_id, sender_id, message_type, message_content, attachment_url)
                VALUES (?, ?, ?, ?, ?)");

            $insert_success = $stmt_insert->execute([
                $conversation_id,
                $sender_id,
                $type,
                $message,
                $attachment
            ]);

            if (!$insert_success) {
                $errorInfo = $stmt_insert->errorInfo();
                throw new PDOException("Failed to insert message into chat_messages table. ErrorInfo: " . implode(" ", $errorInfo));
            }

            $message_id = $this->conn->lastInsertId();

            // 2. Update conversation's last message time with exponential backoff retry logic
            // This retry is here as a fallback for general transient issues even if conversation creation is atomic.
            $max_retries = 5; // Increased retries for robustness
            $base_delay_microseconds = 50000; // 50 milliseconds initial delay

            $update_success = false;

            for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
                $stmt_update_conv = $this->conn->prepare("UPDATE chat_conversations
                    SET last_message_at = NOW()
                    WHERE id = ?");
                $execute_success = $stmt_update_conv->execute([$conversation_id]);

                if ($execute_success && $stmt_update_conv->rowCount() > 0) {
                    $update_success = true;
                    break; // Success, exit retry loop
                } elseif ($attempt < $max_retries) {
                    // Log retry attempt and wait longer for the next attempt
                    $current_delay = $base_delay_microseconds * pow(2, $attempt - 1); // Exponential backoff
                    $this->debugToFile("Chat.class.php sendMessage: Retrying update for conversation_id {$conversation_id} (Attempt {$attempt}). Rows affected: " . $stmt_update_conv->rowCount() . ". Waiting " . ($current_delay / 1000) . "ms.");
                    usleep($current_delay); // Wait before retrying
                }
            }

            // Check final result of the update attempts
            if (!$update_success) {
                // If after all retries, the update still failed (affected 0 rows or query error), throw an exception
                throw new Exception("Failed to update last_message_at for conversation_id: " . $conversation_id . " after {$max_retries} attempts.");
            }

            // If both operations succeeded, commit the transaction
            $this->conn->commit();
            return $message_id; // Return the new message ID on success

        } catch (PDOException $e) {
            // Catch PDO exceptions specifically for database errors
            $this->conn->rollBack(); // Rollback the transaction on database error
            $this->debugToFile("Chat.class.php sendMessage PDO error (transaction rolled back): " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
            return false;
        } catch (Exception $e) {
            // Catch other general exceptions (like the one from the inlined update failure)
            $this->conn->rollBack(); // Rollback the transaction on other errors
            $this->debugToFile("Chat.class.php sendMessage general error (transaction rolled back): " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
            return false;
        }
    }

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
            $params = [$conversation_id];

            if ($last_message_id !== null) {
                $sql .= " AND id > ?";
                $params[] = $last_message_id;
            }

            $sql .= " ORDER BY created_at ASC, id ASC LIMIT ? OFFSET ?"; // Order oldest first for appending

            $stmt = $this->conn->prepare($sql);
            // Bind parameters ensuring types are correct for LIMIT/OFFSET
            $stmt->bindParam(1, $conversation_id, PDO::PARAM_INT);
            $paramIndex = 2;
            if ($last_message_id !== null) {
                $stmt->bindParam($paramIndex++, $last_message_id, PDO::PARAM_INT);
            }
            $stmt->bindParam($paramIndex++, $limit, PDO::PARAM_INT);
            $stmt->bindParam($paramIndex++, $offset, PDO::PARAM_INT);
            
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
    public function getUserConversations($user_id, $limit = 10) {
        try {
            $stmt = $this->conn->prepare("
                SELECT cc.*,
                       e.title as event_title, -- event_title might be NULL if event_id is NULL
                       CASE
                         WHEN cc.user_id = ? THEN vp.business_name -- Logged-in user is customer, show vendor business name
                         ELSE CONCAT(u2.first_name, ' ', u2.last_name) -- Logged-in user is vendor, show customer's full name (FIXED)
                       END as other_party_name,
                       CASE
                         WHEN cc.user_id = ? THEN up_vendor.profile_image -- Logged-in user is customer, show vendor profile image
                         ELSE up_user.profile_image -- Logged-in user is vendor, show customer profile image
                       END as other_party_image,
                       cm.message_content as last_message,
                       cm.created_at as last_message_time,
                       (SELECT COUNT(*) FROM chat_messages
                        WHERE conversation_id = cc.id AND sender_id != ? AND is_read = FALSE) as unread_count
                FROM chat_conversations cc
                LEFT JOIN events e ON cc.event_id = e.id -- Use LEFT JOIN because event_id can now be NULL
                LEFT JOIN users u ON cc.vendor_id = u.id -- Alias 'u' for the vendor's user record
                LEFT JOIN vendor_profiles vp ON u.id = vp.user_id
                LEFT JOIN user_profiles up_vendor ON u.id = up_vendor.user_id -- Vendor's user_profile
                LEFT JOIN users u2 ON cc.user_id = u2.id -- Alias 'u2' for the customer's user record
                LEFT JOIN user_profiles up_user ON u2.id = up_user.user_id -- Customer's user_profile
                LEFT JOIN chat_messages cm ON cc.id = cm.conversation_id AND cm.id = (
                    SELECT MAX(id) FROM chat_messages WHERE conversation_id = cc.id
                )
                WHERE (cc.user_id = ? OR cc.vendor_id = ?)
                AND cc.status != 'archived' -- ADDED: Exclude archived chats
                GROUP BY cc.id
                ORDER BY cc.last_message_at DESC
                LIMIT ?
            ");
            // Parameters: first two for CASE statements, third for unread_count subquery, next two for WHERE clause, last for LIMIT
            $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException | Exception $e) { // Catch both PDOException and general Exception
            $this->debugToFile("Chat.class.php getUserConversations error: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Mark a conversation as archived (soft delete).
     * @param int $conversationId The ID of the conversation to archive.
     * @param int $userId The ID of the user performing the action (for authorization/ownership).
     * @return bool True on success, false on failure.
     */
    public function markConversationAsArchived($conversationId, $userId) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE chat_conversations
                SET status = 'archived', updated_at = NOW()
                WHERE id = ? AND (user_id = ? OR vendor_id = ?)
            ");
            $result = $stmt->execute([$conversationId, $userId, $userId]);
            $this->debugToFile("markConversationAsArchived: Conversation ID {$conversationId} archived by user {$userId}. Rows affected: " . $stmt->rowCount());
            return $result;
        } catch (PDOException | Exception $e) {
            $this->debugToFile("markConversationAsArchived error: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
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
            return $stmt->execute([$conversation_id, $user_id]);
        } catch (PDOException | Exception $e) {
            $this->debugToFile("Chat.class.php markMessagesAsRead error: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
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
            $stmt->execute([$user_id, $user_id, $user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['unread_count'] ?? 0;
        } catch (PDOException | Exception $e) {
            $this->debugToFile("Chat.class.php getUnreadCount error: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
            return 0;
        }
    }
}
