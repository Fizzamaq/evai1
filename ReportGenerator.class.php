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
