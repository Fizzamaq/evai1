<?php
// classes/Booking.class.php
class Booking {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function createBooking($data, $userId) {
        try {
            // Log the data array received for debugging
            error_log("createBooking: Data received: " . print_r($data, true));

            $stmt = $this->pdo->prepare("
                INSERT INTO bookings (
                    user_id, event_id, vendor_id, service_id, booking_date, service_date, /* ADDED booking_date */
                    final_amount, deposit_amount, special_instructions,
                    status, screenshot_proof, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, NOW(), ?, /* ADDED NOW() for booking_date */ ?, ?, ?, ?, ?, NOW(), NOW()
                )
            ");
            $success = $stmt->execute([
                $userId,
                $data['event_id'],
                $data['vendor_id'],
                $data['service_id'],
                /* NOW() replaces a placeholder here */
                $data['service_date'],
                $data['final_amount'],
                $data['deposit_amount'],
                $data['special_instructions'],
                $data['status'],
                $data['screenshot_filename']
            ]);

            if ($success) {
                $lastId = $this->pdo->lastInsertId();
                error_log("createBooking: Successfully inserted booking with ID: " . $lastId);
                return $lastId;
            } else {
                // Log PDO error information if the execute fails
                $errorInfo = $stmt->errorInfo();
                error_log("createBooking Error: PDO Execute Failed. ErrorInfo: " . implode(" | ", $errorInfo) . " | Data: " . print_r($data, true));
                return false;
            }
        } catch (PDOException $e) {
            // Catch and log any PDO exceptions during the process
            error_log("createBooking PDO Exception: " . $e->getMessage() . " | SQLSTATE: " . $e->getCode() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
            return false;
        } catch (Exception $e) {
            // Catch and log any other general exceptions
            error_log("createBooking General Exception: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
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
                JOIN vendor_profiles v ON b.vendor_id = v.id  /* Corrected JOIN: Use vp.id instead of vp.user_id if vendor_id in bookings refers to vendor_profiles.id */
                WHERE b.id = ? AND b.user_id = ?
            ");
            $stmt->execute([$bookingId, $userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get user booking error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Updates a booking's status.
     * @param int $bookingId The ID of the booking to update.
     * @param string $status The new status for the booking.
     * @param string|null $stripePaymentId An optional Stripe payment ID.
     * @param string|null $screenshotProof An optional screenshot filename.
     * @return bool True on success, false on failure.
     */
    public function updateBookingStatus(int $bookingId, string $status, string $stripePaymentId = null, string $screenshotProof = null) {
        try {
            $sql = "UPDATE bookings SET status = :status, updated_at = NOW()";
            $params = [':status' => $status];
            if ($stripePaymentId) {
                $sql .= ", stripe_payment_id = :stripePaymentId";
                $params[':stripePaymentId'] = $stripePaymentId;
            }
            if ($screenshotProof) {
                $sql .= ", screenshot_proof = :screenshotProof";
                $params[':screenshotProof'] = $screenshotProof;
            }
            $sql .= " WHERE id = :bookingId";
            $params[':bookingId'] = $bookingId;

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
                JOIN vendor_profiles vp ON b.vendor_id = vp.id /* Corrected JOIN: Use vp.id instead of vp.user_id */
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

    public function getVendorUpcomingBookings($vendorId, $limit = 5) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT b.*, e.title as event_title, u.first_name, u.last_name
                FROM bookings b
                LEFT JOIN events e ON b.event_id = e.id
                LEFT JOIN users u ON b.user_id = u.id
                WHERE b.vendor_id = ? AND b.service_date >= CURDATE() AND b.status IN ('pending_review', 'confirmed')
                ORDER BY b.service_date ASC
                LIMIT ?
            ");
            $stmt->bindParam(1, $vendorId, PDO::PARAM_INT);
            $stmt->bindParam(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Add 'client_name' for display convenience
            foreach ($bookings as &$booking) {
                $booking['client_name'] = trim($booking['first_name'] . ' ' . $booking['last_name']);
            }
            return $bookings;
        } catch (PDOException | Exception $e) { // Catch both PDOException and general Exception
            error_log("Error getting vendor upcoming bookings for vendor {$vendorId}: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
            return [];
        }
    }

    public function getVendorRecentBookings($vendorId, $limit = 5) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT b.*, e.title as event_title, u.first_name, u.last_name
                FROM bookings b
                LEFT JOIN events e ON b.event_id = e.id
                LEFT JOIN users u ON b.user_id = u.id
                WHERE b.vendor_id = ?
                ORDER BY b.created_at DESC
                LIMIT ?
            ");
            $stmt->bindParam(1, $vendorId, PDO::PARAM_INT);
            $stmt->bindParam(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Add 'client_name' for display convenience
            foreach ($bookings as &$booking) {
                $booking['client_name'] = trim($booking['first_name'] . ' ' . $booking['last_name']);
            }
            return $bookings;
        } catch (PDOException | Exception $e) { // Catch both PDOException and general Exception
            error_log("Error getting vendor recent bookings for vendor {$vendorId}: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
            return [];
        }
    }

    public function getVendorBookingStats($vendorId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) AS total_bookings,
                    SUM(CASE WHEN status = 'pending_review' THEN 1 ELSE 0 END) AS pending_bookings,
                    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed_bookings,
                    SUM(CASE WHEN status = 'completed' THEN final_amount ELSE 0 END) AS total_revenue
                FROM bookings
                WHERE vendor_id = ?
            ");
            $stmt->execute([$vendorId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException | Exception $e) { // Catch both PDOException and general Exception
            error_log("Error getting vendor booking stats for vendor {$vendorId}: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
            return [
                'total_bookings' => 0,
                'pending_bookings' => 0,
                'confirmed_bookings' => 0,
                'total_revenue' => 0
            ];
        }
    }

    /**
     * Automatically updates confirmed bookings to 'completed' status
     * if the service date has passed.
     * This method is designed to be run by a cron job or scheduled task.
     * @return int The number of bookings that were updated.
     */
    public function updateCompletedBookings() {
        try {
            // We need to update bookings that are 'confirmed' and whose service_date is in the past
            $sql = "UPDATE bookings SET status = 'completed', updated_at = NOW() WHERE status = 'confirmed' AND service_date < CURDATE()";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();

            $updatedRows = $stmt->rowCount();
            error_log("Successfully updated {$updatedRows} confirmed bookings to completed status.");
            return $updatedRows;
        } catch (PDOException $e) {
            error_log("Failed to run updateCompletedBookings cron job: " . $e->getMessage());
            return 0;
        }
    }
}
