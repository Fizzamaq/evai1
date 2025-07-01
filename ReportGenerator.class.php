<?php
class ReportGenerator {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function generateFinancialReport($startDate, $endDate) {
        $stmt = $this->pdo->prepare("
            SELECT
                DATE(created_at) AS date,
                COUNT(*) AS total_bookings,
                SUM(final_amount) AS total_revenue,
                AVG(final_amount) AS average_booking_value,
                SUM(CASE WHEN status = 'completed' THEN final_amount ELSE 0 END) AS collected_amount,
                SUM(CASE WHEN status = 'pending_payment' THEN final_amount ELSE 0 END) AS pending_amount
            FROM bookings
            WHERE created_at BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function generateVendorPerformanceReport($vendorId, $startDate, $endDate) {
        try {
            $sql = "
                SELECT
                    MONTH(service_date) AS month,
                    COUNT(*) AS total_bookings,
                    AVG(r.rating) AS average_rating,
                    SUM(b.final_amount) AS total_earnings,
                    AVG(DATEDIFF(b.service_date, b.created_at)) AS avg_lead_time,
                    COUNT(r.id) AS total_reviews
                FROM bookings b
                LEFT JOIN reviews r ON b.id = r.booking_id
                WHERE b.service_date BETWEEN ? AND ?
            ";
            $params = [$startDate, $endDate];

            if ($vendorId !== null) {
                $sql .= " AND b.vendor_id = ?";
                $params[] = $vendorId;
            }

            $sql .= " GROUP BY MONTH(b.service_date) ORDER BY MONTH(b.service_date) ASC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Vendor performance report error: " . $e->getMessage());
            return [];
        }
    }

    public function generateUserActivityReport($userId, $startDate, $endDate) {
        try {
            $sql = "
                SELECT
                    et.type_name as event_type,
                    COUNT(e.id) AS total_events,
                    AVG( (e.budget_min + e.budget_max) / 2 ) AS avg_budget,
                    SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) AS completed_events,
                    SUM(CASE WHEN e.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_events,
                    -- Subquery for total messages for a specific user
                    (SELECT COUNT(*) FROM chat_messages cm JOIN chat_conversations cc ON cm.conversation_id = cc.id WHERE (cc.user_id = u.id OR cc.vendor_id = u.id)) AS total_messages
                FROM events e
                JOIN event_types et ON e.event_type_id = et.id
                JOIN users u ON e.user_id = u.id
                WHERE e.created_at BETWEEN ? AND ?
            ";
            $params = [$startDate, $endDate];

            if ($userId !== null) {
                $sql .= " AND e.user_id = ?";
                $params[] = $userId;
            }

            $sql .= " GROUP BY et.type_name ORDER BY et.type_name ASC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("User activity report error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Retrieves recent system activities for the admin dashboard.
     * This method fetches recent data from various tables to simulate system activity.
     * @param int $limit The maximum number of activities to retrieve.
     * @return array An array of associative arrays, each representing an activity.
     */
    public function getSystemActivity($limit = 10) {
        try {
            // Use positional parameters and pass the limit value multiple times
            $stmt = $this->pdo->prepare("
                (SELECT 'New User Registered: ' AS type_prefix, CONCAT(first_name, ' ', last_name) AS message_detail, created_at, 'fas fa-user-plus' AS icon_class FROM users ORDER BY created_at DESC LIMIT ?)
                UNION ALL
                (SELECT 'New Event Created: ' AS type_prefix, title AS message_detail, created_at, 'fas fa-calendar-plus' AS icon_class FROM events ORDER BY created_at DESC LIMIT ?)
                UNION ALL
                (SELECT 'New Vendor Registered: ' AS type_prefix, business_name AS message_detail, created_at, 'fas fa-store' AS icon_class FROM vendor_profiles ORDER BY created_at DESC LIMIT ?)
                UNION ALL
                (SELECT 'New Booking: ' AS type_prefix, CONCAT('Booking for event ID ', id) AS message_detail, created_at, 'fas fa-calendar-check' AS icon_class FROM bookings ORDER BY created_at DESC LIMIT ?)
                ORDER BY created_at DESC
                LIMIT ?
            ");
            // Pass the limit value for each placeholder
            $stmt->execute([$limit, $limit, $limit, $limit, $limit]);

            $activities = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $activities[] = [
                    'message' => $row['type_prefix'] . $row['message_detail'],
                    'created_at' => $row['created_at'],
                    'icon_class' => $row['icon_class']
                ];
            }
            return $activities;
        } catch (PDOException $e) {
            error_log("Error getting system activity: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Retrieves recent activities for a specific customer user.
     * @param int $userId The ID of the customer user.
     * @param int $limit The maximum number of activities to retrieve.
     * @return array An array of associative arrays, each representing an activity.
     */
    public function getUserRecentActivity($userId, $limit = 10) {
        try {
            $stmt = $this->pdo->prepare("
                -- New Event Created
                (SELECT 'New Event Created: ' AS type_prefix, e.title AS message_detail, e.created_at, 'fas fa-calendar-plus' AS icon_class, NULL AS related_url
                FROM events e WHERE e.user_id = :user_id1 ORDER BY e.created_at DESC LIMIT :limit1)
                
                UNION ALL
                
                -- New Booking Made
                (SELECT 'Booking Made: ' AS type_prefix, CONCAT('for event ', e.title, ' with ', vp.business_name) AS message_detail, b.created_at, 'fas fa-calendar-check' AS icon_class, CONCAT('public/booking.php?id=', b.id) AS related_url
                FROM bookings b JOIN events e ON b.event_id = e.id JOIN vendor_profiles vp ON b.vendor_id = vp.id
                WHERE b.user_id = :user_id2 ORDER BY b.created_at DESC LIMIT :limit2)
                
                UNION ALL
                
                -- Chat Message Sent by user (only if it's the latest message in conversation)
                (SELECT 'Chat Message: ' AS type_prefix, CONCAT('You sent a message to ', CASE WHEN cc.user_id = :user_id3 THEN vp.business_name ELSE CONCAT(u_other.first_name, ' ', u_other.last_name) END) AS message_detail, cm.created_at, 'fas fa-comment' AS icon_class, CONCAT('public/chat.php?conversation_id=', cc.id) AS related_url
                FROM chat_messages cm
                JOIN chat_conversations cc ON cm.conversation_id = cc.id
                LEFT JOIN users u_other ON (CASE WHEN cc.user_id = :user_id4 THEN cc.vendor_id ELSE cc.user_id END) = u_other.id
                LEFT JOIN vendor_profiles vp ON u_other.id = vp.user_id
                WHERE cm.sender_id = :user_id5 AND cm.id = (SELECT MAX(id) FROM chat_messages WHERE conversation_id = cm.conversation_id)
                ORDER BY cm.created_at DESC LIMIT :limit3)

                UNION ALL

                -- AI Recommendation Viewed (or event saved from AI)
                (SELECT 'AI Plan Saved: ' AS type_prefix, e.title AS message_detail, e.created_at, 'fas fa-robot' AS icon_class, CONCAT('public/event.php?id=', e.id) AS related_url
                FROM events e
                WHERE e.user_id = :user_id6 AND e.ai_preferences IS NOT NULL AND e.status != 'deleted'
                ORDER BY e.created_at DESC LIMIT :limit4)

                ORDER BY created_at DESC
                LIMIT :final_limit
            ");

            $stmt->bindParam(':user_id1', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':limit1', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':user_id2', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':limit2', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':user_id3', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':user_id4', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':user_id5', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':limit3', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':user_id6', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':limit4', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':final_limit', $limit, PDO::PARAM_INT);
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting user recent activity for user {$userId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Retrieves recent activities for a specific vendor.
     * @param int $vendorProfileId The ID of the vendor profile.
     * @param int $limit The maximum number of activities to retrieve.
     * @return array An array of associative arrays, each representing an activity.
     */
    public function getVendorRecentActivity($vendorProfileId, $limit = 10) {
        try {
            // First, get the user_id associated with this vendor_profile_id
            $stmt_user_id = $this->pdo->prepare("SELECT user_id FROM vendor_profiles WHERE id = ?");
            $stmt_user_id->execute([$vendorProfileId]);
            $vendorUserId = $stmt_user_id->fetchColumn();

            if (!$vendorUserId) {
                return []; // Vendor profile not found or no associated user.
            }

            $stmt = $this->pdo->prepare("
                -- New Booking Received
                (SELECT 'New Booking: ' AS type_prefix, CONCAT('for event ', e.title, ' from ', u.first_name, ' ', u.last_name) AS message_detail, b.created_at, 'fas fa-calendar-check' AS icon_class, CONCAT('public/booking.php?id=', b.id) AS related_url
                FROM bookings b JOIN events e ON b.event_id = e.id JOIN users u ON b.user_id = u.id
                WHERE b.vendor_id = :vendor_user_id1 ORDER BY b.created_at DESC LIMIT :limit1)

                UNION ALL
                
                -- Chat Message Received by vendor (only if it's the latest message in conversation)
                (SELECT 'Chat Message: ' AS type_prefix, CONCAT('You received a message from ', CONCAT(u_other.first_name, ' ', u_other.last_name)) AS message_detail, cm.created_at, 'fas fa-comment' AS icon_class, CONCAT('public/vendor_chat.php?conversation_id=', cc.id) AS related_url
                FROM chat_messages cm
                JOIN chat_conversations cc ON cm.conversation_id = cc.id
                JOIN users u_other ON (CASE WHEN cc.user_id = :vendor_user_id2 THEN cc.vendor_id ELSE cc.user_id END) = u_other.id
                WHERE cm.sender_id != :vendor_user_id3 AND (cc.user_id = :vendor_user_id4 OR cc.vendor_id = :vendor_user_id5)
                AND cm.id = (SELECT MAX(id) FROM chat_messages WHERE conversation_id = cm.conversation_id)
                ORDER BY cm.created_at DESC LIMIT :limit2)

                UNION ALL
                
                -- New Review Received
                (SELECT 'New Review: ' AS type_prefix, CONCAT('for your service from ', u.first_name, ' ', u.last_name) AS message_detail, r.created_at, 'fas fa-star' AS icon_class, NULL AS related_url
                FROM reviews r JOIN users u ON r.reviewer_id = u.id
                WHERE r.reviewed_id = :vendor_user_id6 ORDER BY r.created_at DESC LIMIT :limit3)

                ORDER BY created_at DESC
                LIMIT :final_limit
            ");

            $stmt->bindParam(':vendor_user_id1', $vendorUserId, PDO::PARAM_INT);
            $stmt->bindParam(':limit1', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':vendor_user_id2', $vendorUserId, PDO::PARAM_INT);
            $stmt->bindParam(':vendor_user_id3', $vendorUserId, PDO::PARAM_INT);
            $stmt->bindParam(':vendor_user_id4', $vendorUserId, PDO::PARAM_INT);
            $stmt->bindParam(':vendor_user_id5', $vendorUserId, PDO::PARAM_INT);
            $stmt->bindParam(':limit2', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':vendor_user_id6', $vendorUserId, PDO::PARAM_INT);
            $stmt->bindParam(':limit3', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':final_limit', $limit, PDO::PARAM_INT);

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting vendor recent activity for vendor profile {$vendorProfileId} (user {$vendorUserId}): " . $e->getMessage());
            return [];
        }
    }


    public function exportToCSV($data, $filename) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        if (!empty($data)) {
            fputcsv($output, array_keys($data[0])); // CSV header
        }

        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }
}
