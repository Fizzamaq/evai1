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
                SUM(CASE WHEN status = 'completed' THEN final_amount ELSE 0 END) AS collected_amount, -- Corrected status
                SUM(CASE WHEN status = 'pending_payment' THEN final_amount ELSE 0 END) AS pending_amount -- Corrected status
            FROM bookings
            WHERE created_at BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function generateVendorPerformanceReport($vendorId, $startDate, $endDate) { // Added missing parameters
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    MONTH(service_date) AS month,
                    COUNT(*) AS total_bookings,
                    AVG(r.rating) AS average_rating, -- Use reviews table for rating
                    SUM(b.final_amount) AS total_earnings,
                    AVG(DATEDIFF(b.service_date, b.created_at)) AS avg_lead_time,
                    COUNT(r.id) AS total_reviews
                FROM bookings b
                LEFT JOIN reviews r ON b.id = r.booking_id -- Join to reviews table
                WHERE b.vendor_id = ? 
                    AND b.service_date BETWEEN ? AND ?
                GROUP BY MONTH(b.service_date)
                ORDER BY MONTH(b.service_date) ASC
            ");
            $stmt->execute([$vendorId, $startDate, $endDate]); // Pass parameters correctly
            return $stmt->fetchAll(PDO::FETCH_ASSOC); // Return fetched data
        } catch (PDOException $e) {
            error_log("Vendor performance report error: " . $e->getMessage());
            return [];
        }
    }

    public function generateUserActivityReport($userId, $startDate, $endDate) { // Added missing parameters (though not used in query for dates)
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    et.type_name as event_type, -- Use type_name from event_types
                    COUNT(e.id) AS total_events,
                    AVG( (e.budget_min + e.budget_max) / 2 ) AS avg_budget, -- Use avg of min/max budget
                    SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) AS completed_events,
                    SUM(CASE WHEN e.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_events,
                    (SELECT COUNT(*) FROM chat_messages cm JOIN chat_conversations cc ON cm.conversation_id = cc.id WHERE (cc.user_id = ? OR cc.vendor_id = ?) AND cm.sender_id = ?) AS total_messages
                FROM events e
                JOIN event_types et ON e.event_type_id = et.id -- Join to get event type name
                WHERE e.user_id = ?
                GROUP BY et.type_name -- Group by event type name
            ");
            $stmt->execute([$userId, $userId, $userId, $userId]); // Pass parameters correctly
            return $stmt->fetchAll(PDO::FETCH_ASSOC); // Return fetched data
        } catch (PDOException $e) {
            error_log("User activity report error: " . $e->getMessage());
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