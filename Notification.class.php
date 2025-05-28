<?php
// classes/Notification.class.php
class Notification {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function createNotification($userId, $message, $relatedType = null, $relatedId = null) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO notifications (user_id, message, related_type, related_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $message, $relatedType, $relatedId]);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Notification creation error: " . $e->getMessage());
            return false;
        }
    }

    public function getUserNotifications($userId, $limit = 20) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get user notifications error: " . $e->getMessage());
            return [];
        }
    }

    public function markAsRead($notificationId, $userId) {
        try {
            $stmt = $this->pdo->prepare("UPDATE notifications SET is_read = TRUE, read_at = NOW() WHERE id = ? AND user_id = ?");
            return $stmt->execute([$notificationId, $userId]);
        } catch (PDOException $e) {
            error_log("Mark notification as read error: " . $e->getMessage());
            return false;
        }
    }

    public function deleteNotification($notificationId, $userId) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
            return $stmt->execute([$notificationId, $userId]);
        } catch (PDOException $e) {
            error_log("Delete notification error: " . $e->getMessage());
            return false;
        }
    }

    public function getUnreadCount($userId) {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) AS unread_count FROM notifications WHERE user_id = ? AND is_read = FALSE");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['unread_count'] ?? 0;
        } catch (PDOException $e) {
            error_log("Get unread notifications count error: " . $e->getMessage());
            return 0;
        }
    }
}
?>