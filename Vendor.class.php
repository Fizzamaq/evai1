<?php
// classes/Vendor.class.php
class Vendor {
    private $conn;

    public function __construct($pdo) {
        $this->conn = $pdo;
    }

    // Register a new vendor (or complete profile if user_id exists)
    public function registerVendor($user_id, $data) {
        try {
            $existingVendor = $this->getVendorByUserId($user_id);
            error_log("Vendor.registerVendor: Checking for existing vendor for user_id: " . $user_id . ", Found: " . ($existingVendor ? "Yes (ID: " . $existingVendor['id'] . ")" : "No"));

            if ($existingVendor) {
                // If profile exists, update it
                error_log("Vendor.registerVendor: Updating existing vendor profile ID: " . $existingVendor['id']);
                $update_successful = $this->updateVendor($existingVendor['id'], $data);
                if ($update_successful) {
                    return $existingVendor['id'];
                } else {
                    return false;
                }
            } else {
                // If no profile exists, create a new one
                error_log("Vendor.registerVendor: Creating new vendor profile for user_id: " . $user_id);
                $query = "INSERT INTO vendor_profiles
                    (user_id, business_name, business_license, tax_id, website,
                     business_address, business_city, business_state, business_country,
                     business_postal_code, service_radius, min_budget, max_budget,
                     experience_years)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $params = [
                    $user_id,
                    $data['business_name'],
                    $data['business_license'] ?? null,
                    $data['tax_id'] ?? null,
                    $data['website'] ?? null,
                    $data['business_address'],
                    $data['business_city'],
                    $data['business_state'],
                    $data['business_country'],
                    $data['business_postal_code'],
                    $data['service_radius'] ?? 50,
                    $data['min_budget'] ?? null,
                    $data['max_budget'] ?? null,
                    $data['experience_years'] ?? null
                ];

                error_log("Vendor.registerVendor (INSERT): Query: " . $query);
                error_log("Vendor.registerVendor (INSERT): Params: " . print_r($params, true));

                $stmt = $this->conn->prepare($query);
                $result = $stmt->execute($params);

                if ($result) {
                    $lastId = $this->conn->lastInsertId();
                    error_log("Vendor.registerVendor (INSERT): Success, new ID: " . $lastId);
                    return $lastId;
                } else {
                    error_log("Vendor.registerVendor (INSERT): Failed to execute, PDO ErrorInfo: " . print_r($stmt->errorInfo(), true));
                    return false;
                }
            }
        } catch (PDOException $e) {
            error_log("Vendor.registerVendor error: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");
            return false;
        } catch (Exception $e) {
            error_log("Vendor.registerVendor general error: " . $e->getMessage());
            return false;
        }
    }

    // Get vendor by user ID
    public function getVendorByUserId($user_id) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM vendor_profiles WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $vendorData = $stmt->fetch(PDO::FETCH_ASSOC);
            return $vendorData;
        } catch (PDOException $e) {
            error_log("Vendor.getVendorByUserId error: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");
            return false;
        }
    }

    // NEW METHOD: Get vendor profile by vendor_profiles.id
    public function getVendorProfileById($vendor_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT
                    vp.*,
                    u.email,
                    u.first_name,
                    u.last_name,
                    up.profile_image,
                    GROUP_CONCAT(DISTINCT vs.service_name ORDER BY vs.service_name ASC SEPARATOR ', ') AS offered_services_names
                FROM vendor_profiles vp
                JOIN users u ON vp.user_id = u.id
                LEFT JOIN user_profiles up ON u.id = up.user_id
                LEFT JOIN vendor_service_offerings vso ON vp.id = vso.vendor_id
                LEFT JOIN vendor_services vs ON vso.service_id = vs.id
                WHERE vp.id = ?
                GROUP BY vp.id /* Group by vendor profile to get single row */
            ");
            $stmt->execute([$vendor_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Vendor.getVendorProfileById error: " . $e->getMessage());
            return false;
        }
    }

    // Update vendor profile (assuming vendor_id is the primary key of vendor_profiles)
    public function updateVendor($vendor_profile_id, $data) {
        try {
            $query = "UPDATE vendor_profiles SET
                business_name = ?,
                business_license = ?,
                tax_id = ?,
                website = ?,
                business_address = ?,
                business_city = ?,
                business_state = ?,
                business_country = ?,
                business_postal_code = ?,
                service_radius = ?,
                min_budget = ?,
                max_budget = ?,
                experience_years = ?,
                updated_at = NOW()
                WHERE id = ?";

            $params = [
                $data['business_name'],
                $data['business_license'] ?? null,
                $data['tax_id'] ?? null,
                $data['website'] ?? null,
                $data['business_address'],
                $data['business_city'],
                $data['business_state'],
                $data['business_country'],
                $data['business_postal_code'],
                $data['service_radius'] ?? 50,
                $data['min_budget'] ?? null,
                $data['max_budget'] ?? null,
                $data['experience_years'] ?? null,
                $vendor_profile_id
            ];

            error_log("Vendor.updateVendor (UPDATE): Query: " . $query);
            error_log("Vendor.updateVendor (UPDATE): Params: " . print_r($params, true));

            $stmt = $this->conn->prepare($query);
            $result = $stmt->execute($params);

            if ($result) {
                error_log("Vendor.updateVendor (UPDATE): Success for vendor ID: " . $vendor_profile_id);
                return true;
            } else {
                error_log("Vendor.updateVendor (UPDATE): Failed to execute, PDO ErrorInfo: " . print_r($stmt->errorInfo(), true));
                return false;
            }
        } catch (PDOException $e) {
            error_log("Vendor.updateVendor error: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");
            return false;
        } catch (Exception $e) {
            error_log("Vendor.updateVendor general error: " . $e->getMessage());
            return false;
        }
    }

    // Add vendor service offering
    public function addServiceOffering($vendor_id, $service_id, $data) {
        try {
            $stmt = $this->conn->prepare("INSERT INTO vendor_service_offerings
                (vendor_id, service_id, price_range_min, price_range_max, description)
                VALUES (?, ?, ?, ?, ?)");

            return $stmt->execute([
                $vendor_id,
                $service_id,
                $data['price_min'] ?? null,
                $data['price_max'] ?? null,
                $data['description'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("Add service offering error: " . $e->getMessage());
            return false;
        }
    }

    // Get vendor services
    public function getVendorServices($vendor_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT vso.*, vs.service_name, vc.category_name
                FROM vendor_service_offerings vso
                JOIN vendor_services vs ON vso.service_id = vs.id
                JOIN vendor_categories vc ON vs.category_id = vc.id
                WHERE vso.vendor_id = ? AND vso.is_active = TRUE
            ");
            $stmt->execute([$vendor_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get vendor services error: " . $e->getMessage());
            return false;
        }
    }

    // NEW METHOD: Update vendor's service offerings (delete existing and insert new ones)
    public function updateVendorServiceOfferings($vendor_profile_id, $service_ids_array) {
        try {
            error_log("DEBUG: updateVendorServiceOfferings - Starting transaction for Vendor ID: " . $vendor_profile_id);
            $this->conn->beginTransaction();

            error_log("DEBUG: updateVendorServiceOfferings - Service IDs to insert: " . print_r($service_ids_array, true));

            // 1. Delete all existing offerings for this vendor
            $stmt_delete = $this->conn->prepare("DELETE FROM vendor_service_offerings WHERE vendor_id = ?");
            $stmt_delete->execute([$vendor_profile_id]);
            $deleted_rows = $stmt_delete->rowCount();
            error_log("DEBUG: updateVendorServiceOfferings - Deleted " . $deleted_rows . " existing offerings.");

            // 2. Insert new offerings
            $inserted_count = 0;
            if (!empty($service_ids_array)) {
                $insert_sql = "INSERT INTO vendor_service_offerings (vendor_id, service_id) VALUES (?, ?)";
                $insert_stmt = $this->conn->prepare($insert_sql);
                foreach ($service_ids_array as $service_id) {
                    $service_id = (int)$service_id;
                    if ($service_id > 0) {
                        $insert_stmt->execute([$vendor_profile_id, $service_id]);
                        $inserted_count++;
                    }
                }
            }
            error_log("DEBUG: updateVendorServiceOfferings - Attempted to insert " . $inserted_count . " new offerings.");

            $this->conn->commit();
            error_log("DEBUG: updateVendorServiceOfferings - Transaction committed successfully.");
            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("ERROR: Vendor.updateVendorServiceOfferings PDO Exception: " . $e->getMessage() . " (Code: " . $e->getCode() . ") SQLSTATE: " . $e->errorInfo[0]);
            throw new Exception("Database error updating services: " . $e->getMessage());
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("ERROR: Vendor.updateVendorServiceOfferings General Exception: " . $e->getMessage());
            throw $e;
        }
    }

    // Add portfolio item
    public function addPortfolioItem($vendor_id, $data) {
        try {
            $stmt = $this->conn->prepare("INSERT INTO vendor_portfolios
                (vendor_id, title, description, event_type_id, image_url,
                 video_url, project_date, project_charges, client_testimonial, is_featured)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            return $stmt->execute([
                $vendor_id,
                $data['title'],
                $data['description'] ?? null,
                $data['event_type_id'] ?? null,
                $data['image_url'] ?? null,
                $data['video_url'] ?? null,
                $data['project_date'] ?? null,
                $data['project_charges'] ?? null,
                $data['client_testimonial'] ?? null,
                $data['is_featured'] ?? false
            ]);
        } catch (PDOException $e) {
            error_log("Add portfolio item error: " . $e->getMessage());
            return false;
        }
    }

    // Get vendor portfolio
    public function getVendorPortfolio($vendor_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT vp.*, et.type_name as event_type_name
                FROM vendor_portfolios vp
                LEFT JOIN event_types et ON vp.event_type_id = et.id
                WHERE vp.vendor_id = ?
                ORDER BY vp.display_order, vp.created_at DESC
            ");
            $stmt->execute([$vendor_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get portfolio error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves a single portfolio item by its ID.
     * @param int $portfolioItemId The ID of the portfolio item.
     * @return array|false The portfolio item data if found, false otherwise.
     */
    public function getPortfolioItemById($portfolioItemId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT vp.*, et.type_name as event_type_name
                FROM vendor_portfolios vp
                LEFT JOIN event_types et ON vp.event_type_id = et.id
                WHERE vp.id = ?
            ");
            $stmt->execute([$portfolioItemId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get portfolio item by ID error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Updates an existing portfolio item.
     * @param int $portfolioItemId The ID of the portfolio item to update.
     * @param int $vendorId The ID of the vendor owning the item (for security).
     * @param array $data New data for the portfolio item.
     * @return bool True on success, false on failure.
     */
    public function updatePortfolioItem($portfolioItemId, $vendorId, $data) {
        try {
            $query = "UPDATE vendor_portfolios SET
                title = ?,
                description = ?,
                event_type_id = ?,";

            $params = [
                $data['title'],
                $data['description'] ?? null,
                $data['event_type_id'] ?? null,
            ];

            // Only update image_url if a new one is provided (not null)
            if (isset($data['image_url']) && $data['image_url'] !== null) {
                 $query .= "image_url = ?,";
                 $params[] = $data['image_url'];
            } else if (isset($data['image_url']) && $data['image_url'] === null) {
                // If explicitly set to null, it means remove the image
                 $query .= "image_url = NULL,";
            }

            $query .= "video_url = ?,
                project_date = ?,
                project_charges = ?,
                client_testimonial = ?,
                is_featured = ?,
                updated_at = NOW()
                WHERE id = ? AND vendor_id = ?";

            $params = array_merge($params, [
                $data['video_url'] ?? null,
                $data['project_date'] ?? null,
                $data['project_charges'] ?? null,
                $data['client_testimonial'] ?? null,
                $data['is_featured'] ?? false,
                $portfolioItemId,
                $vendorId
            ]);

            $stmt = $this->conn->prepare($query);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Update portfolio item error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletes a portfolio item.
     * @param int $portfolioItemId The ID of the portfolio item to delete.
     * @param int $vendorId The ID of the vendor owning the item (for security).
     * @return bool True on success, false on failure.
     */
    public function deletePortfolioItem($portfolioItemId, $vendorId) {
        try {
            $stmt = $this->conn->prepare("DELETE FROM vendor_portfolios WHERE id = ? AND vendor_id = ?"); // Ensure vendor ownership
            return $stmt->execute([$portfolioItemId, $vendorId]);
        } catch (PDOException $e) {
            error_log("Delete portfolio item error: " . $e->getMessage());
            return false;
        }
    }

    // Set vendor availability (Unified method)
    public function updateAvailability($vendorId, $date, $start_time, $end_time, $status) {
        $stmt = $this->conn->prepare("
            INSERT INTO vendor_availability
            (vendor_id, date, start_time, end_time, status)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                start_time = VALUES(start_time),
                end_time = VALUES(end_time),
                status = VALUES(status),
                updated_at = NOW()
        ");

        return $stmt->execute([
            $vendorId,
            $date, // Use 'date' column for the specific day
            $start_time,
            $end_time,
            $status
        ]);
    }

    // Get vendor availability (Unified method)
    public function getAvailability($vendorId, $startDate, $endDate) {
        $stmt = $this->conn->prepare("
            SELECT
                id,
                date, -- Select 'date' column
                start_time,
                end_time,
                status
            FROM vendor_availability
            WHERE
                vendor_id = ? AND
                date BETWEEN ? AND ?
            ORDER BY date, start_time
        ");

        $stmt->execute([$vendorId, $startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get recommended vendors for an event
    public function getRecommendedVendors($event_id, $service_id, $limit = 5) {
        try {
            $stmt = $this->conn->prepare("
                SELECT vp.*, u.first_name, u.last_name, u.profile_image,
                       ar.confidence_score, ar.total_score
                FROM ai_recommendations ar
                JOIN vendor_profiles vp ON ar.vendor_id = vp.id
                JOIN users u ON vp.user_id = u.id
                WHERE ar.event_id = ? AND ar.service_id = ?
                ORDER BY ar.total_score DESC
                LIMIT ?
            ");
            $stmt->execute([$event_id, $service_id, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get recommended vendors error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifies if the current session user is a vendor and has a complete vendor profile.
     * Redirects if conditions are not met.
     */
    public function verifyVendorAccess() {
        // Ensure BASE_URL is defined (from config.php)
        if (!defined('BASE_URL')) {
            error_log("BASE_URL not defined in config.php. Cannot perform vendor access verification.");
            // Fallback to a generic error or login page if BASE_URL is not set
            header('Location: /login.php');
            exit();
        }

        // 1. Check if user is logged in at all
        if (!isset($_SESSION['user_id'])) {
            // Not logged in, redirect to login page
            header('Location: ' . BASE_URL . 'public/login.php');
            exit();
        }

        // 2. Check if the logged-in user's type is 'vendor' (user_type_id = 2)
        // This relies on $_SESSION['user_type'] being set correctly during login.
        if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 2) {
            // User is logged in but not a vendor, redirect to their general dashboard
            header('Location: ' . BASE_URL . 'public/dashboard.php');
            exit();
        }

        // 3. If user is a vendor type, check if they have a completed vendor profile
        $vendorData = $this->getVendorByUserId($_SESSION['user_id']);
        if ($vendorData) {
            // Vendor profile found, set vendor_id in session for convenience
            $_SESSION['vendor_id'] = $vendorData['id'];
        } else {
            // User is of type 'vendor' but no vendor_profiles entry exists.
            // Redirect them to a page where they can complete their vendor profile.
            $_SESSION['error_message'] = "Your vendor profile is incomplete. Please register your business details to access vendor features.";
            header('Location: ' . BASE_URL . 'public/edit_profile.php');
            exit();
        }
    }

    // Checks if a user is of type 'vendor'
    public function isVendor($userId) {
        $stmt = $this->conn->prepare("SELECT user_type_id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && $result['user_type_id'] == 2; // Assuming 2 is vendor type
    }

    // Get total booking count for a vendor
    public function getBookingCount($vendorId) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM bookings WHERE vendor_id = ?");
        $stmt->execute([$vendorId]);
        return $stmt->fetchColumn();
    }

    // Get count of upcoming bookings for a vendor
    public function getUpcomingEvents($vendorId) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM bookings WHERE vendor_id = ? AND service_date >= CURDATE() AND status != 'cancelled'");
        $stmt->execute([$vendorId]);
        return $stmt->fetchColumn();
    }

    // Placeholder for vendor response rate (requires chat message tracking)
    public function getResponseRate($vendorId) {
        // This would involve more complex logic tracking messages sent to vendor vs. vendor replies.
        // For now, return a static value or implement a basic calculation.
        return 0.85; // Example static value
    }

    // Get a list of upcoming bookings for a vendor
    public function getUpcomingBookings($vendorId) {
        $stmt = $this->conn->prepare("SELECT b.*, e.title as event_title FROM bookings b JOIN events e ON b.event_id = e.id WHERE b.vendor_id = ? AND b.service_date >= CURDATE() ORDER BY b.service_date ASC LIMIT 5");
        $stmt->execute([$vendorId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a list of featured vendors for the homepage display.
     * Includes their profile image and a concatenated list of their services.
     * @param int $limit The maximum number of vendors to retrieve.
     * @return array An array of vendor data.
     */
    public function getFeaturedVendorsForHomepage($limit = 8) {
        try {
            $stmt = $this->conn->prepare("
                SELECT
                    vp.id,
                    vp.business_name,
                    vp.business_city,
                    vp.rating,
                    vp.total_reviews,
                    up.profile_image,
                    GROUP_CONCAT(DISTINCT vs.service_name ORDER BY vs.service_name ASC SEPARATOR ', ') AS offered_services
                FROM vendor_profiles vp
                JOIN users u ON vp.user_id = u.id
                LEFT JOIN user_profiles up ON u.id = up.user_id
                LEFT JOIN vendor_service_offerings vso ON vp.id = vso.vendor_id
                LEFT JOIN vendor_services vs ON vso.service_id = vs.id
                WHERE vp.featured = TRUE /* Condition for active/verified temporarily removed for testing */
                GROUP BY vp.id
                ORDER BY vp.rating DESC, vp.total_reviews DESC
                LIMIT ?
            ");
            $stmt->bindParam(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching featured vendors for homepage: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all active vendor categories.
     * @return array An array of vendor categories.
     */
    public function getAllVendorCategories() {
        try {
            $stmt = $this->conn->prepare("SELECT id, category_name, icon FROM vendor_categories WHERE is_active = TRUE ORDER BY category_name ASC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching vendor categories: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get a list of vendors offering services within a specific category.
     * Includes their profile image and a concatenated list of their services.
     * @param int $categoryId The ID of the vendor category.
     * @param int $limit The maximum number of vendors to retrieve.
     * @return array An array of vendor data.
     */
    public function getVendorsByCategoryId($categoryId, $limit = 10) {
        try {
            $stmt = $this->conn->prepare("
                SELECT
                    vp.id,
                    vp.business_name,
                    vp.business_city,
                    vp.rating,
                    vp.total_reviews,
                    up.profile_image,
                    GROUP_CONCAT(DISTINCT vs.service_name ORDER BY vs.service_name ASC SEPARATOR ', ') AS offered_services
                FROM vendor_profiles vp
                JOIN users u ON vp.user_id = u.id
                LEFT JOIN user_profiles up ON u.id = up.user_id
                JOIN vendor_service_offerings vso ON vp.id = vso.vendor_id
                JOIN vendor_services vs ON vso.service_id = vs.id
                WHERE vs.category_id = ? /* Conditions for active/verified temporarily removed for testing */
                GROUP BY vp.id
                ORDER BY vp.rating DESC, vp.total_reviews DESC
                LIMIT ?
            ");
            $stmt->bindParam(1, $categoryId, PDO::PARAM_INT);
            $stmt->bindParam(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching vendors by category ID: " . $e->getMessage());
            return [];
        }
    }
}
