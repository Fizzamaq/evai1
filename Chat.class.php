<?php
// classes/Chat.class.php
class Chat {
    private $conn;

    public function __construct($pdo) { // Pass PDO to constructor
        $this->conn = $pdo;
    }

    /**
     * Start a new conversation.
     * @param int|null $event_id The ID of the event, or NULL for general inquiries.
     * @param int $user_id
     * @param int $vendor_id (user ID of the vendor)
     * @return int|false Conversation ID on success, false on failure.
     */
    public function startConversation($event_id, $user_id, $vendor_id) {
        try {
            // Check if conversation already exists to prevent duplicates
            $existing_conv = $this->getConversationByParticipants($user_id, $vendor_id, $event_id);
            if ($existing_conv) {
                return $existing_conv['id'];
            }

            // Create new conversation
            $stmt = $this->conn->prepare("INSERT INTO chat_conversations
                (event_id, user_id, vendor_id)
                VALUES (?, ?, ?)");

            // Bind event_id. PDO automatically handles NULL correctly for INT type.
            $result = $stmt->execute([$event_id, $user_id, $vendor_id]);

            if ($result) {
                $conversation_id = $this->conn->lastInsertId();
                return $conversation_id;
            } else {
                // Log specific PDO error if execution failed
                error_log("Chat.class.php startConversation PDO error: " . implode(" ", $stmt->errorInfo()));
                return false;
            }

        } catch (PDOException $e) {
            // Log the full PDOException message
            error_log("Chat.class.php startConversation Exception: " . $e->getMessage());
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

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Chat.class.php getConversationByParticipants error: " . $e->getMessage());
            return false;
        }
    }


    // Send a message
    public function sendMessage($conversation_id, $sender_id, $message, $type = 'text', $attachment = null) {
        try {
            $stmt = $this->conn->prepare("INSERT INTO chat_messages
                (conversation_id, sender_id, message_type, message_content, attachment_url)
                VALUES (?, ?, ?, ?, ?)");

            $stmt->execute([
                $conversation_id,
                $sender_id,
                $type,
                $message,
                $attachment
            ]);

            // Update conversation last message time
            // IMPORTANT: Check if updateConversationTime also succeeded.
            // If it fails, sendMessage should also indicate failure.
            if (!$this->updateConversationTime($conversation_id)) {
                // If this update fails, log it and return false, as the full "send" operation isn't complete.
                error_log("Chat.class.php sendMessage: Failed to update last_message_at for conversation_id: " . $conversation_id);
                return false;
            }

            return $this->conn->lastInsertId();
        } catch (PDOException $e) {
            error_log("Chat.class.php sendMessage error: " . $e->getMessage());
            return false;
        }
    }

    // Update conversation last message time
    private function updateConversationTime($conversation_id) {
        try {
            $stmt = $this->conn->prepare("UPDATE chat_conversations
                SET last_message_at = NOW()
                WHERE id = ?");
            $result = $stmt->execute([$conversation_id]);
            if (!$result) {
                error_log("Chat.class.php updateConversationTime PDO error: " . implode(" ", $stmt->errorInfo()));
            }
            return $result; // Return true on success, false on failure
        } catch (PDOException $e) {
            error_log("Chat.class.php updateConversationTime Exception: " . $e->getMessage());
            return false;
        }
    }

    // Get conversation messages
    public function getMessages($conversation_id, $limit = 50, $offset = 0) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM chat_messages
                WHERE conversation_id = ?
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?");
            $stmt->execute([$conversation_id, $limit, $offset]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Chat.class.php getMessages error: " . $e->getMessage());
            return false;
        }
    }

    // Get user conversations
    public function getUserConversations($user_id, $limit = 10) {
        try {
            $stmt = $this->conn->prepare("
                SELECT cc.*,
                       e.title as event_title, -- event_title might be NULL if event_id is NULL
                       CASE
                         WHEN cc.user_id = ? THEN vp.business_name
                         ELSE CONCAT(u.first_name, ' ', u.last_name)
                       END as other_party_name,
                       CASE
                         WHEN cc.user_id = ? THEN up_vendor.profile_image
                         ELSE up_user.profile_image
                       END as other_party_image,
                       cm.message_content as last_message,
                       cm.created_at as last_message_time,
                       (SELECT COUNT(*) FROM chat_messages
                        WHERE conversation_id = cc.id AND sender_id != ? AND is_read = FALSE) as unread_count
                FROM chat_conversations cc
                LEFT JOIN events e ON cc.event_id = e.id -- Use LEFT JOIN because event_id can now be NULL
                LEFT JOIN users u ON cc.vendor_id = u.id
                LEFT JOIN vendor_profiles vp ON u.id = vp.user_id
                LEFT JOIN user_profiles up_vendor ON u.id = up_vendor.user_id
                LEFT JOIN users u2 ON cc.user_id = u2.id
                LEFT JOIN user_profiles up_user ON u2.id = up_user.user_id
                LEFT JOIN chat_messages cm ON cc.id = cm.conversation_id AND cm.id = (
                    SELECT MAX(id) FROM chat_messages WHERE conversation_id = cc.id
                )
                WHERE (cc.user_id = ? OR cc.vendor_id = ?)
                GROUP BY cc.id
                ORDER BY cc.last_message_at DESC
                LIMIT ?
            ");
            $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Chat.class.php getUserConversations error: " . $e->getMessage());
            return false;
        }
    }

    // Mark messages as read
    public function markMessagesAsRead($conversation_id, $user_id) {
        try {
            $stmt = $this->conn->prepare("UPDATE chat_messages
                SET is_read = TRUE, read_at = NOW()
                WHERE conversation_id = ? AND sender_id != ? AND is_read = FALSE");
            return $stmt->execute([$conversation_id, $user_id]);
        } catch (PDOException $e) {
            error_log("Chat.class.php markMessagesAsRead error: " . $e->getMessage());
            return false;
        }
    }

    // Get unread message count
    public function getUnreadCount($user_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as unread_count
                FROM chat_messages cm
                JOIN chat_conversations cc ON cm.conversation_id = cc.id
                WHERE (cc.user_id = ? OR cc.vendor_id = ?)
                AND cm.sender_id != ?
                AND cm.is_read = FALSE
            ");
            $stmt->execute([$user_id, $user_id, $user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['unread_count'] ?? 0;
        } catch (PDOException $e) {
            error_log("Chat.class.php getUnreadCount error: " . $e->getMessage());
            return 0;
        }
    }
}
