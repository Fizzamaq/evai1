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
                    status, screenshot_proof, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
                )
            ");
            $success = $stmt->execute([
                $userId,
                $data['event_id'],
                $data['vendor_id'],
                $data['service_id'],
                $data['service_date'],
                $data['final_amount'],
                $data['deposit_amount'],
                $data['special_instructions'],
                $data['status'],
                $data['screenshot_filename'] // This is the 10th parameter
            ]);

            if ($success) {
                return $this->pdo->lastInsertId();
            } else {
                // Log PDO error information if the execute fails
                $errorInfo = $stmt->errorInfo();
                error_log("Booking creation error: PDO Execute Failed. ErrorInfo: " . implode(" | ", $errorInfo) . " | Data: " . print_r($data, true));
                return false;
            }
        } catch (PDOException $e) {
            // Catch and log any PDO exceptions during the process
            error_log("Booking creation PDO Exception: " . $e->getMessage() . " | SQLSTATE: " . $e->getCode());
            return false;
        } catch (Exception $e) {
            // Catch and log any other general exceptions
            error_log("Booking creation General Exception: " . $e->getMessage());
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

    // MODIFIED: updateBookingStatus to also update screenshot_proof if provided
    public function updateBookingStatus($bookingId, $status, $stripePaymentId = null, $screenshotProof = null) {
        try {
            $sql = "UPDATE bookings SET status = ?, updated_at = NOW()";
            $params = [$status];
            if ($stripePaymentId) {
                $sql .= ", stripe_payment_id = ?";
                $params[] = $stripePaymentId;
            }
            if ($screenshotProof) { // New: Update screenshot proof
                $sql .= ", screenshot_proof = ?";
                $params[] = $screenshotProof;
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

    /**
     * Updates the chat_conversation_id for a given booking.
     * @param int $bookingId The ID of the booking to update.
     * @param int $conversationId The ID of the chat conversation to link.
     * @return bool True on success, false on failure.
     */
    public function updateBookingChatConversationId($bookingId, $conversationId) {
        try {
            $stmt = $this->pdo->prepare("UPDATE bookings SET chat_conversation_id = ? WHERE id = ?");
            return $stmt->execute([$conversationId, $bookingId]);
        } catch (PDOException $e) {
            error_log("Update booking chat conversation ID error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Updates the stripe_payment_id for a given booking.
     * This method might become obsolete if Stripe is not used.
     * @param int $bookingId The ID of the booking to update.
     * @param string $stripePaymentId The Stripe Payment Intent ID.
     * @return bool True on success, false on failure.
     */
    public function updateBookingStripePaymentId($bookingId, $stripePaymentId) {
        try {
            $stmt = $this->pdo->prepare("UPDATE bookings SET stripe_payment_id = ? WHERE id = ?");
            return $stmt->execute([$stripePaymentId, $bookingId]);
        } catch (PDOException $e) {
            error_log("Update booking Stripe payment ID error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all bookings for a user.
     * @param int $userId The ID of the user.
     * @return array An array of booking data.
     */
    public function getUserBookings($userId) {
        try {
            // Added screenshot_proof to the SELECT statement
            $stmt = $this->pdo->prepare("
                SELECT b.*, e.title as event_title, vp.business_name
                FROM bookings b
                LEFT JOIN events e ON b.event_id = e.id
                JOIN vendor_profiles vp ON b.vendor_id = vp.id
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

    /**
     * Get all bookings associated with a specific event ID.
     * @param int $eventId The ID of the event.
     * @return array An array of booking data, or empty array if none found.
     */
    public function getBookingsByEventId($eventId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT b.*
                FROM bookings b
                WHERE b.event_id = ?
            ");
            $stmt->execute([$eventId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get bookings by event ID error: " . $e->getMessage());
            return [];
        }
    }
}
