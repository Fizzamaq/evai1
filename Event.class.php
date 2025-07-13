<?php
// classes/Event.class.php
class Event {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // MODIFIED: createEvent now takes a data array for flexibility
    public function createEvent($data) {
        try {
            // Default values for optional fields if not provided in $data
            $title = $data['title'] ?? 'New Event Booking';
            $description = $data['description'] ?? 'Event created via booking form.';
            $event_date = $data['event_date'] ?? date('Y-m-d'); // Default to today if not provided
            $event_type_id = $data['event_type_id'] ?? 1; // Default to a generic event type if not provided (e.g., 'Other')
            $guest_count = $data['guest_count'] ?? 0;
            $budget_min = $data['budget_min'] ?? 0.00;
            $budget_max = $data['budget_max'] ?? 0.00;
            $status = $data['status'] ?? 'planning'; // Default status
            $ai_preferences = $data['ai_preferences'] ?? null;
            $location_string = $data['location_string'] ?? null;
            $venue_name = $data['venue_name'] ?? null;
            $venue_address = $data['venue_address'] ?? null;
            $venue_city = $data['venue_city'] ?? null;
            $venue_state = $data['venue_state'] ?? null;
            $venue_country = $data['venue_country'] ?? null;
            $venue_postal_code = $data['venue_postal_code'] ?? null;
            $event_time = $data['event_time'] ?? null;
            $end_date = $data['end_date'] ?? null;
            $end_time = $data['end_time'] ?? null;
            $special_requirements = $data['special_requirements'] ?? null;
            $venue_location_point_str = $data['venue_location_point_str'] ?? null;


            $stmt = $this->pdo->prepare("
                INSERT INTO events (
                    user_id, event_type_id, title, description, event_date, event_time,
                    end_date, end_time, venue_name, venue_address, venue_city, venue_state,
                    venue_country, venue_postal_code, guest_count, budget_min, budget_max,
                    status, special_requirements, ai_preferences, location_string, venue_location,
                    created_at, updated_at
                ) VALUES (
                    :user_id, :event_type_id, :title, :description, :event_date, :event_time,
                    :end_date, :end_time, :venue_name, :venue_address, :venue_city, :venue_state,
                    :venue_country, :venue_postal_code, :guest_count, :budget_min, :budget_max,
                    :status, :special_requirements, :ai_preferences, :location_string, ST_GeomFromText(:venue_location_point_str),
                    NOW(), NOW()
                )
            ");
            $success = $stmt->execute([
                ':user_id' => $data['user_id'], // This must be provided in $data
                ':event_type_id' => $event_type_id,
                ':title' => $title,
                ':description' => $description,
                ':event_date' => $event_date,
                ':event_time' => $event_time,
                ':end_date' => $end_date,
                ':end_time' => $end_time,
                ':venue_name' => $venue_name,
                ':venue_address' => $venue_address,
                ':venue_city' => $venue_city,
                ':venue_state' => $venue_state,
                ':venue_country' => $venue_country,
                ':venue_postal_code' => $venue_postal_code,
                ':guest_count' => $guest_count,
                ':budget_min' => $budget_min,
                ':budget_max' => $budget_max,
                ':status' => $status,
                ':special_requirements' => $special_requirements,
                ':ai_preferences' => $ai_preferences,
                ':location_string' => $location_string,
                ':venue_location_point_str' => $venue_location_point_str
            ]);

            if ($success) {
                return $this->pdo->lastInsertId();
            } else {
                $errorInfo = $stmt->errorInfo();
                error_log("Event creation error: PDO Execute Failed. ErrorInfo: " . implode(" | ", $errorInfo) . " | Data: " . print_r($data, true));
                return false;
            }
        } catch (PDOException $e) {
            error_log("Event creation PDO Exception: " . $e->getMessage() . " | SQLSTATE: " . $e->getCode());
            return false;
        } catch (Exception $e) {
            error_log("Event creation General Exception: " . $e->getMessage());
            return false;
        }
    }

    public function updateEvent($eventId, $userId, $title, $description, $event_date, $event_type_id, $guest_count, $budget_min, $budget_max, $ai_preferences = null, $status = 'planning') {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE events
                SET title = ?, description = ?, event_date = ?, event_type_id = ?, guest_count = ?, budget_min = ?, budget_max = ?, ai_preferences = ?, status = ?, updated_at = NOW()
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$title, $description, $event_date, $event_type_id, $guest_count, $budget_min, $budget_max, $ai_preferences, $status, $eventId, $userId]);
            return $stmt->rowCount(); // Returns number of affected rows
        } catch (PDOException $e) {
            error_log("Event update error: " . $e->getMessage());
            return false;
        }
    }

    public function getEventById($eventId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT e.*, et.type_name
                FROM events e
                JOIN event_types et ON e.event_type_id = et.id
                WHERE e.id = ?
            ");
            $stmt->execute([$eventId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get event by ID error: " . $e->getMessage());
            return false;
        }
    }

    public function getUserEvents($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT e.*, et.type_name
                FROM events e
                JOIN event_types et ON e.event_type_id = et.id
                WHERE e.user_id = ?
                ORDER BY e.event_date DESC
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get user events error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Deletes an event.
     * This method now attempts to delete the event from the database.
     * It also sets the event status to 'deleted' in the events table
     * and deletes associated bookings due to ON DELETE CASCADE.
     * @param int $eventId The ID of the event to delete.
     * @param int $userId The ID of the user attempting to delete the event (for ownership check).
     * @return bool True on successful deletion, false otherwise.
     */
    public function deleteEvent($eventId, $userId) {
        try {
            // First, verify ownership to prevent unauthorized deletion
            $checkStmt = $this->pdo->prepare("SELECT id FROM events WHERE id = ? AND user_id = ?");
            $checkStmt->execute([$eventId, $userId]);
            if (!$checkStmt->fetch()) {
                error_log("Delete Event Error: User (ID: $userId) attempted to delete event (ID: $eventId) they do not own.");
                return false; // User does not own this event
            }

            // Attempt to delete the event.
            // Due to ON DELETE CASCADE on bookings.event_id, associated bookings should be deleted automatically.
            $deleteStmt = $this->pdo->prepare("DELETE FROM events WHERE id = ?");
            $success = $deleteStmt->execute([$eventId]);

            if ($success) {
                error_log("Event (ID: $eventId) successfully deleted by User (ID: $userId).");
                return true;
            } else {
                // Log PDO error information if the execute fails
                $errorInfo = $deleteStmt->errorInfo();
                error_log("Delete Event Error: Failed to execute DELETE statement for Event ID: $eventId. PDO ErrorInfo: " . implode(" | ", $errorInfo));
                return false;
            }
        } catch (PDOException $e) {
            // Catch and log any PDO exceptions during the process
            error_log("Delete Event PDO Exception: " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            // Catch and log any other general exceptions
            error_log("Delete Event General Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Search events by criteria
     */
    public function searchEvents($user_id, $criteria = []) {
        try {
            $sql = "SELECT e.*, et.type_name
                    FROM events e
                    JOIN event_types et ON e.event_type_id = et.id
                    WHERE e.user_id = :user_id";
            $params = [':user_id' => $user_id];

            if (!empty($criteria['title'])) {
                $sql .= " AND e.title LIKE :title";
                $params[':title'] = '%' . $criteria['title'] . '%';
            }

            if (!empty($criteria['event_type_id'])) {
                $sql .= " AND e.event_type_id = :event_type_id";
                $params[':event_type_id'] = $criteria['event_type_id'];
            }

            if (!empty($criteria['status'])) {
                if (is_array($criteria['status'])) {
                    $named_placeholders = [];
                    $status_in_params = [];
                    foreach ($criteria['status'] as $idx => $status_val) {
                        $placeholder = ':status_' . $idx;
                        $named_placeholders[] = $placeholder;
                        $status_in_params[$placeholder] = $status_val;
                    }
                    $sql .= " AND e.status IN (" . implode(',', $named_placeholders) . ")";
                    $params = array_merge($params, $status_in_params);
                } else {
                    $sql .= " AND e.status = :status_single";
                    $params[':status_single'] = $criteria['status'];
                }
            }

            if (!empty($criteria['date_from'])) {
                $sql .= " AND e.event_date >= :date_from";
                $params[':date_from'] = $criteria['date_from'];
            }

            if (!empty($criteria['date_to'])) {
                $sql .= " AND e.event_date <= :date_to";
                $params[':date_to'] = $criteria['date_to'];
            }

            $sql .= " ORDER BY e.event_date ASC";

            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Event search error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get upcoming events for a user
     * MODIFIED: Only shows events that have a booked vendor.
     */
    public function getUpcomingEvents($user_id, $limit = 5) {
        try {
            $sql = "SELECT e.*, et.type_name
                    FROM events e
                    JOIN event_types et ON e.event_type_id = et.id
                    WHERE e.user_id = :user_id
                    AND e.event_date >= CURDATE()
                    AND e.status != 'deleted'
                    AND EXISTS (SELECT 1 FROM bookings b WHERE b.event_id = e.id)
                    ORDER BY e.event_date ASC
                    LIMIT :limit";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Get upcoming events error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get event statistics for a user
     */
    public function getUserEventStats($user_id) {
        try {
            $sql = "SELECT
                        COUNT(*) as total_events,
                        SUM(CASE WHEN status = 'planning' THEN 1 ELSE 0 END) as planning_events,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_events,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_events,
                        SUM(CASE WHEN event_date >= CURDATE() THEN 1 ELSE 0 END) as upcoming_events,
                        AVG( (budget_min + budget_max) / 2 ) as avg_budget,
                        SUM( (budget_min + budget_max) / 2 ) as total_budget
                    FROM events
                    WHERE user_id = :user_id AND status != 'deleted'";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Get user event stats error: " . $e->getMessage());
            return [
                'total_events' => 0,
                'planning_events' => 0,
                'active_events' => 0,
                'completed_events' => 0,
                'upcoming_events' => 0,
                'avg_budget' => 0,
                'total_budget' => 0
            ];
        }
    }

    /**
     * Get events that need specific services (for vendor matching)
     * This method will need to query the event_service_requirements table.
     */
    public function getEventsByService($service_id, $location = null) {
        try {
            $sql = "SELECT e.*, et.type_name
                    FROM events e
                    JOIN event_service_requirements esr ON e.id = esr.event_id
                    JOIN event_types et ON e.event_type_id = et.id
                    WHERE esr.service_id = :service_id
                    AND e.status IN ('planning', 'active')
                    AND e.event_date >= CURDATE()";

            $params = [':service_id' => $service_id];

            if ($location) {
                $sql .= " AND e.location_string LIKE :location";
                $params[':location'] = '%' . $location . '%';
            }

            $sql .= " ORDER BY e.event_date ASC";

            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Get events by service error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Duplicate an event (for recurring events)
     */
    public function duplicateEvent($event_id, $user_id, $new_date = null) {
        try {
            // Get original event
            $original_event = $this->getEventById($event_id, $user_id);
            if (!$original_event) {
                return false;
            }

            // Prepare new event data
            $new_event_data = $original_event;
            unset($new_event_data['id']);
            $new_event_data['title'] .= ' (Copy)';
            $new_event_data['status'] = 'planning';
            // created_at and updated_at will be handled by NOW() in createEvent

            if ($new_date) {
                $new_event_data['event_date'] = $new_date;
            }

            // Services needed for duplication (fetch from event_service_requirements)
            $services_to_duplicate_stmt = $this->pdo->prepare("SELECT service_id, priority, budget_allocated, specific_requirements, status FROM event_service_requirements WHERE event_id = ?");
            $services_to_duplicate_stmt->execute([$event_id]);
            $new_event_data['services_needed_array'] = $services_to_duplicate_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Need to map old 'event_type_name' back to 'event_type_id' for insertion
            // This is crucial for createEvent if it expects event_type_id
            $event_type_stmt = $this->pdo->prepare("SELECT id FROM event_types WHERE type_name = ?");
            $event_type_stmt->execute([$original_event['type_name']]);
            $new_event_data['event_type_id'] = $event_type_stmt->fetchColumn(); // Corrected key to event_type_id


            return $this->createEvent($new_event_data);

        } catch (Exception $e) {
            error_log("Event duplication error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * NEW: Method to get all events for the admin panel.
     * @return array
     */
    public function getAllEvents() {
        try {
            $sql = "SELECT e.*, et.type_name
                    FROM events e
                    JOIN event_types et ON e.event_type_id = et.id
                    ORDER BY e.event_date ASC, e.created_at DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting all events for admin: " . $e->getMessage());
            return [];
        }
    }

    /**
     * NEW: Method to update event status for admin panel.
     * @param int $eventId
     * @param string $newStatus
     * @return bool
     */
    public function updateEventStatus($eventId, $newStatus) {
        try {
            $stmt = $this->pdo->prepare("UPDATE events SET status = ?, updated_at = NOW() WHERE id = ?");
            return $stmt->execute([$newStatus, $eventId]);
        } catch (PDOException $e) {
            error_log("Failed to update event status by admin: " . $e->getMessage());
            throw new Exception("Failed to update event status.");
        }
    }

    /**
     * NEW: Method to soft delete an event for admin panel (changes status to 'deleted').
     * @param int $eventId
     * @return bool
     */
    public function deleteEventSoft($eventId) {
        try {
            $stmt = $this->pdo->prepare("UPDATE events SET status = 'deleted', updated_at = NOW() WHERE id = ?");
            return $stmt->execute([$eventId]);
        } catch (PDOException $e) {
            error_log("Failed to soft delete event by admin: " . $e->getMessage());
            throw new Exception("Failed to soft delete event.");
        }
    }
}
