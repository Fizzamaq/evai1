<?php
class Event {
    private $conn;

    public function __construct($pdo) { // Pass PDO to constructor
        $this->conn = $pdo;
    }

    /**
     * Create a new event
     */
    public function createEvent($data) {
        try {
            $this->conn->beginTransaction();

            // Insert into events table
            $sql = "INSERT INTO events (
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
                NOW(), NOW() -- Use NOW() for created_at and updated_at
            )";

            $stmt = $this->conn->prepare($sql);

            $venue_location_point_str_val = null;
            // Placeholder for geocoding logic if you implement it
            // if (!empty($data['location_string'])) {
            //      $coords = $this->geocodeLocation($data['location_string']);
            //      if ($coords) {
            //          $venue_location_point_str_val = "POINT(" . $coords['lat'] . " " . $coords['lng'] . ")";
            //      }
            // }

            $execute_params = [
                ':user_id' => $data['user_id'],
                ':event_type_id' => $data['event_type_id'], // Corrected key from 'event_type' to 'event_type_id'
                ':title' => $data['title'],
                ':description' => $data['description'],
                ':event_date' => $data['event_date'],
                ':event_time' => $data['event_time'] ?? null,
                ':end_date' => $data['end_date'] ?? null,
                ':end_time' => $data['end_time'] ?? null,
                ':venue_name' => $data['venue_name'] ?? null,
                ':venue_address' => $data['venue_address'] ?? null,
                ':venue_city' => $data['venue_city'] ?? null,
                ':venue_state' => $data['venue_state'] ?? null,
                ':venue_country' => $data['venue_country'] ?? null,
                ':venue_postal_code' => $data['postal_code'] ?? null, // Changed from data['business_postal_code'] to data['postal_code']
                ':guest_count' => $data['guest_count'],
                ':budget_min' => $data['budget_min'],
                ':budget_max' => $data['budget_max'],
                ':status' => $data['status'],
                ':special_requirements' => $data['special_requirements'],
                ':ai_preferences' => $data['ai_preferences'] ?? null,
                ':location_string' => $data['location_string'] ?? null,
                ':venue_location_point_str' => $venue_location_point_str_val
            ];

            $event_insert_success = $stmt->execute($execute_params);

            if (!$event_insert_success) {
                throw new PDOException("Events table insert failed.");
            }

            $event_id = $this->conn->lastInsertId();

            // Handle services (insert into event_service_requirements)
            if (!empty($data['services_needed_array'])) {
                $service_req_sql = "INSERT INTO event_service_requirements
                                        (event_id, service_id, priority, budget_allocated, specific_requirements, status)
                                        VALUES (?, ?, ?, ?, ?, ?)";
                $service_stmt = $this->conn->prepare($service_req_sql);

                foreach ($data['services_needed_array'] as $service) {
                    $service_execute_params = [
                        $event_id,
                        $service['service_id'],
                        $service['priority'] ?? 'medium',
                        $service['budget_allocated'] ?? null,
                        $service['specific_requirements'] ?? null,
                        $service['status'] ?? 'needed'
                    ];

                    $service_insert_success = $service_stmt->execute($service_execute_params);

                    if (!$service_insert_success) {
                        throw new PDOException("Service insert failed for service_id: " . $service['service_id']);
                    }
                }
            }

            $this->conn->commit();
            return $event_id;

        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Event creation PDO error: " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Event creation general error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all events for a specific user
     */
    public function getUserEvents($user_id) {
        try {
            // Join with event_types to get type_name for display
            $sql = "SELECT e.*, et.type_name
                    FROM events e
                    JOIN event_types et ON e.event_type_id = et.id
                    WHERE e.user_id = :user_id
                    ORDER BY e.event_date ASC, e.created_at DESC";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Get user events error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get a specific event by ID
     */
    public function getEventById($event_id, $user_id = null) {
        try {
            // Join with event_types to get type_name
            $sql = "SELECT e.*, et.type_name
                    FROM events e
                    JOIN event_types et ON e.event_type_id = et.id
                    WHERE e.id = :event_id";
            if ($user_id) {
                $sql .= " AND e.user_id = :user_id";
            }

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':event_id', $event_id);
            if ($user_id) {
                $stmt->bindParam(':user_id', $user_id);
            }
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Get event by ID error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update an existing event
     */
    public function updateEvent($event_id, $data, $user_id) {
        try {
            $this->conn->beginTransaction();

            $sql = "UPDATE events SET
                title = :title,
                description = :description,
                event_type_id = :event_type_id,
                event_date = :event_date,
                event_time = :event_time,
                end_date = :end_date,
                end_time = :end_time,
                venue_name = :venue_name,
                venue_address = :venue_address,
                venue_city = :venue_city,
                venue_state = :venue_state,
                venue_country = :venue_country,
                venue_postal_code = :venue_postal_code,
                guest_count = :guest_count,
                budget_min = :budget_min,
                budget_max = :budget_max,
                status = :status,
                special_requirements = :special_requirements,
                location_string = :location_string,
                venue_location = ST_GeomFromText(:venue_location_point_str) -- Update point as well
                WHERE id = :event_id AND user_id = :user_id";

            $stmt = $this->conn->prepare($sql);

            $venue_location_point_str = null;
            // Placeholder for geocoding logic if you implement it
            // if (!empty($data['location_string_from_form'])) {
            //      $coords = $this->geocodeLocation($data['location_string_from_form']);
            //      if ($coords) {
            //          $venue_location_point_str = "POINT(" . $coords['lat'] . " " . $coords['lng'] . ")";
            //      }
            // }

            $stmt->execute([
                ':title' => $data['title'],
                ':description' => $data['description'],
                ':event_type_id' => $data['event_type'], // Corrected key to 'event_type'
                ':event_date' => $data['event_date'],
                ':event_time' => $data['event_time'] ?? null,
                ':end_date' => $data['end_date'] ?? null,
                ':end_time' => $data['end_time'] ?? null,
                ':venue_name' => $data['venue_name'] ?? null,
                ':venue_address' => $data['venue_address'] ?? null,
                ':venue_city' => $data['venue_city'] ?? null,
                ':venue_state' => $data['venue_state'] ?? null,
                ':venue_country' => $data['venue_country'] ?? null,
                ':venue_postal_code' => $data['venue_postal_code'] ?? null,
                ':guest_count' => $data['guest_count'] ?? null,
                ':budget_min' => $data['budget_min'] ?? null,
                ':budget_max' => $data['budget_max'] ?? null,
                ':status' => $data['status'] ?? 'planning',
                ':special_requirements' => $data['special_requirements'] ?? null,
                ':location_string' => $data['location_string_from_form'] ?? null,
                ':venue_location_point_str' => $venue_location_point_str, // Pass as named parameter
                ':event_id' => $event_id,
                ':user_id' => $user_id
            ]);

            // Update services (delete existing and insert new ones)
            $this->conn->prepare("DELETE FROM event_service_requirements WHERE event_id = ?")
                        ->execute([$event_id]);

            if (!empty($data['services_needed_array'])) {
                $service_req_sql = "INSERT INTO event_service_requirements
                                        (event_id, service_id, priority)
                                        VALUES (?, ?, ?)";
                $service_stmt = $this->conn->prepare($service_req_sql);
                foreach ($data['services_needed_array'] as $service_id) {
                    $service_stmt->execute([$event_id, $service_id, 'medium']);
                }
            }

            $this->conn->commit();
            return true;

        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Event update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete an event (soft delete by changing status)
     */
    public function deleteEvent($event_id, $user_id) {
        try {
            // Verify ownership first
            $checkStmt = $this->conn->prepare("SELECT id FROM events WHERE id = ? AND user_id = ?");
            $checkStmt->execute([$event_id, $user_id]);
            if (!$checkStmt->fetch()) {
                error_log("Delete Event Error: User (ID: $user_id) attempted to delete event (ID: $event_id) they do not own.");
                return false; // User does not own this event
            }

            // Perform soft delete
            $sql = "UPDATE events SET status = 'deleted', updated_at = NOW()
                    WHERE id = :event_id"; // Removed user_id from WHERE clause here as ownership is already checked
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':event_id', $event_id);
            // $stmt->bindParam(':user_id', $user_id); // No longer needed if removed from SQL

            $success = $stmt->execute();

            if ($success) {
                error_log("Event (ID: $event_id) soft-deleted successfully by User (ID: $user_id).");
                return true;
            } else {
                $errorInfo = $stmt->errorInfo();
                error_log("Soft Delete Event Error: Failed to execute UPDATE statement for Event ID: $event_id. PDO ErrorInfo: " . implode(" | ", $errorInfo));
                return false;
            }

        } catch (PDOException $e) {
            error_log("Soft Delete Event PDO Exception: " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            error_log("Soft Delete Event General Exception: " . $e->getMessage());
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

            $stmt = $this->conn->prepare($sql);
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

            $stmt = $this->conn->prepare($sql);
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

            $stmt = $this->conn->prepare($sql);
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

            $stmt = $this->conn->prepare($sql);
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
            $services_to_duplicate_stmt = $this->conn->prepare("SELECT service_id, priority, budget_allocated, specific_requirements, status FROM event_service_requirements WHERE event_id = ?");
            $services_to_duplicate_stmt->execute([$event_id]);
            $new_event_data['services_needed_array'] = $services_to_duplicate_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Need to map old 'event_type_name' back to 'event_type_id' for insertion
            // This is crucial for createEvent if it expects event_type_id
            $event_type_stmt = $this->conn->prepare("SELECT id FROM event_types WHERE type_name = ?");
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
            $stmt = $this->conn->prepare($sql);
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
            $stmt = $this->conn->prepare("UPDATE events SET status = ?, updated_at = NOW() WHERE id = ?");
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
            $stmt = $this->conn->prepare("UPDATE events SET status = 'deleted', updated_at = NOW() WHERE id = ?");
            return $stmt->execute([$eventId]);
        } catch (PDOException $e) {
            error_log("Failed to soft delete event by admin: " . $e->getMessage());
            throw new Exception("Failed to soft delete event.");
        }
    }
}
