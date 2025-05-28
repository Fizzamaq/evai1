<?php
// classes/Chat.class.php
class Chat {
    private $conn;

    public function __construct($pdo) { // Pass PDO to constructor
        $this->conn = $pdo;
    }

    // Start a new conversation
    public function startConversation($event_id, $user_id, $vendor_id) {
        try {
            // Check if conversation already exists
            $stmt = $this->conn->prepare("SELECT id FROM chat_conversations 
                WHERE event_id = ? AND user_id = ? AND vendor_id = ?");
            $stmt->execute([$event_id, $user_id, $vendor_id]);

            if ($stmt->rowCount() > 0) {
                return $stmt->fetch(PDO::FETCH_ASSOC)['id'];
            }

            // Create new conversation
            $stmt = $this->conn->prepare("INSERT INTO chat_conversations 
                (event_id, user_id, vendor_id) 
                VALUES (?, ?, ?)");
            $stmt->execute([$event_id, $user_id, $vendor_id]);

            $conversation_id = $this->conn->lastInsertId();

            // Update booking with chat_conversation_id (if a booking exists for this event/user/vendor)
            // This logic might need to be refined based on when a chat starts relative to a booking.
            // Assuming you'd link an existing booking or create one later.
            $updateBookingStmt = $this->conn->prepare("UPDATE bookings SET chat_conversation_id = ? WHERE event_id = ? AND user_id = ? AND vendor_id = ?");
            $updateBookingStmt->execute([$conversation_id, $event_id, $user_id, $vendor_id]);

            return $conversation_id;

        } catch (PDOException $e) {
            error_log("Start conversation error: " . $e->getMessage());
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
            $this->updateConversationTime($conversation_id);

            return $this->conn->lastInsertId();
        } catch (PDOException $e) {
            error_log("Send message error: " . $e->getMessage());
            return false;
        }
    }

    // Update conversation last message time
    private function updateConversationTime($conversation_id) {
        try {
            $stmt = $this->conn->prepare("UPDATE chat_conversations 
                SET last_message_at = NOW() 
                WHERE id = ?");
            return $stmt->execute([$conversation_id]);
        } catch (PDOException $e) {
            error_log("Update conversation time error: " . $e->getMessage());
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
            error_log("Get messages error: " . $e->getMessage());
            return false;
        }
    }

    // Get user conversations
    public function getUserConversations($user_id, $limit = 10) {
        try {
            $stmt = $this->conn->prepare("
                SELECT cc.*, 
                       e.title as event_title,
                       CASE 
                         WHEN cc.user_id = ? THEN vp.business_name
                         ELSE CONCAT(u.first_name, ' ', u.last_name)
                       END as other_party_name,
                       CASE 
                         WHEN cc.user_id = ? THEN up_vendor.profile_image -- profile_image from vendor user_profile
                         ELSE up_user.profile_image -- profile_image from user's user_profile
                       END as other_party_image,
                       cm.message_content as last_message,
                       cm.created_at as last_message_time,
                       (SELECT COUNT(*) FROM chat_messages 
                        WHERE conversation_id = cc.id AND sender_id != ? AND is_read = FALSE) as unread_count
                FROM chat_conversations cc
                JOIN events e ON cc.event_id = e.id
                LEFT JOIN users u ON cc.vendor_id = u.id -- Join to get vendor's user data
                LEFT JOIN user_profiles up_vendor ON u.id = up_vendor.user_id -- Join to get vendor's profile image
                LEFT JOIN users u2 ON cc.user_id = u2.id -- Join to get user's user data
                LEFT JOIN user_profiles up_user ON u2.id = up_user.user_id -- Join to get user's profile image
                LEFT JOIN chat_messages cm ON cc.id = cm.conversation_id AND cm.id = (
                    SELECT MAX(id) FROM chat_messages WHERE conversation_id = cc.id
                ) -- Get only the very last message for preview
                WHERE (cc.user_id = ? OR cc.vendor_id = ?)
                GROUP BY cc.id -- Group by conversation to avoid duplicate rows for last message
                ORDER BY cc.last_message_at DESC
                LIMIT ?
            ");
            $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get conversations error: " . $e->getMessage());
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
            error_log("Mark messages as read error: " . $e->getMessage());
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
            error_log("Get unread count error: " . $e->getMessage());
            return 0;
        }
    }
}