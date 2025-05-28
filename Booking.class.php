<?php
// classes/Booking.class.php
class Booking {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function createBooking($data, $userId) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO bookings (
                    user_id, event_id, vendor_id, service_id, service_date,
                    final_amount, deposit_amount, special_instructions,
                    status, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW()
                )
            ");
            $stmt->execute([
                $userId,
                $data['event_id'],
                $data['vendor_id'],
                $data['service_id'],
                $data['service_date'],
                $data['final_amount'],
                $data['deposit_amount'],
                $data['special_instructions']
            ]);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Booking creation error: " . $e->getMessage());
            return false;
        }
    }

    public function getBooking($bookingId) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM bookings WHERE id = ?");
            $stmt->execute([$bookingId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get booking error: " . $e->getMessage());
            return false;
        }
    }

    public function getUserBooking($userId, $bookingId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT b.*, v.business_name
                FROM bookings b
                JOIN vendor_profiles v ON b.vendor_id = v.user_id
                WHERE b.id = ? AND b.user_id = ?
            ");
            $stmt->execute([$bookingId, $userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get user booking error: " . $e->getMessage());
            return false;
        }
    }

    public function updateBookingStatus($bookingId, $status, $stripePaymentId = null) {
        try {
            $sql = "UPDATE bookings SET status = ?, updated_at = NOW()";
            $params = [$status];
            if ($stripePaymentId) {
                $sql .= ", stripe_payment_id = ?";
                $params[] = $stripePaymentId;
            }
            $sql .= " WHERE id = ?";
            $params[] = $bookingId;

            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Update booking status error: " . $e->getMessage());
            return false;
        }
    }

    // Add method to get all bookings for a user
    public function getUserBookings($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT b.*, e.title as event_title, v.business_name
                FROM bookings b
                JOIN events e ON b.event_id = e.id
                JOIN vendor_profiles v ON b.vendor_id = v.user_id
                WHERE b.user_id = ?
                ORDER BY b.created_at DESC
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get user bookings error: " . $e->getMessage());
            return [];
        }
    }
}
?>