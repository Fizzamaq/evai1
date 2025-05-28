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
            // Check if a vendor profile already exists for this user_id
            $existingVendor = $this->getVendorByUserId($user_id);

            if ($existingVendor) {
                // If profile exists, update it
                return $this->updateVendor($existingVendor['id'], $data);
            } else {
                // If no profile exists, create a new one
                $stmt = $this->conn->prepare("INSERT INTO vendor_profiles 
                    (user_id, business_name, business_license, tax_id, website, 
                     business_address, business_city, business_state, business_country, 
                     business_postal_code, service_radius, min_budget, max_budget, 
                     experience_years) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
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
                ]);
                
                return $this->conn->lastInsertId();
            }
        } catch (PDOException $e) {
            error_log("Vendor registration/update error: " . $e->getMessage());
            return false;
        }
    }

    // Get vendor by user ID
    public function getVendorByUserId($user_id) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM vendor_profiles WHERE user_id = ?");
            $stmt->execute([$user_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get vendor error: " . $e->getMessage());
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
            
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([
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
                $vendor_profile_id // Use vendor_profile_id here, not user_id
            ]);
        } catch (PDOException $e) {
            error_log("Update vendor error: " . $e->getMessage());
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

    // Add portfolio item
    public function addPortfolioItem($vendor_id, $data) {
        try {
            $stmt = $this->conn->prepare("INSERT INTO vendor_portfolios 
                (vendor_id, title, description, event_type_id, image_url, 
                 video_url, project_date, client_testimonial, is_featured)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            return $stmt->execute([
                $vendor_id,
                $data['title'],
                $data['description'] ?? null,
                $data['event_type_id'] ?? null,
                $data['image_url'] ?? null,
                $data['video_url'] ?? null,
                $data['project_date'] ?? null,
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
                date, // Select 'date' column
                start_time,
                end_time,
                status
            FROM vendor_availability
            WHERE 
                vendor_id = ? AND
                date BETWEEN ? AND ? // Query by date
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
            header('Location: /login.php'); // Absolute path fallback
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
            header('Location: ' . BASE_URL . 'public/edit_profile.php'); // Redirect to edit_profile or a dedicated vendor onboarding page
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
}
