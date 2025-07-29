<?php
// classes/Vendor.class.php
class Vendor {
    private $conn;

    public function __construct($pdo) {
        $this->conn = $pdo;
    }

    // Register a new vendor (or complete profile if user_id exists)
    // This method does not explicitly start its own transaction but relies on internal calls
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

    // Add vendor service offering (original, modified to include description)
    // This is for adding a *type* of service to the vendor's offerings (from edit_profile.php)
    public function addServiceOffering($vendor_id, $service_id, $data) {
        try {
            $stmt = $this->conn->prepare("INSERT INTO vendor_service_offerings
                (vendor_id, service_id, price_range_min, price_range_max, description)
                VALUES (?, ?, ?, ?, ?)");

            $result = $stmt->execute([
                $vendor_id,
                $service_id,
                $data['price_min'] ?? null,
                $data['price_max'] ?? null,
                $data['description'] ?? null
            ]);
            return $result ? $this->conn->lastInsertId() : false;
        } catch (PDOException $e) {
            // Check if it's a duplicate entry error
            if ($e->getCode() == '23000' && strpos($e->getMessage(), 'Duplicate entry') !== false) {
                 throw new Exception("This service is already offered. Please edit the existing entry or select a different service.");
            }
            error_log("Add service offering error: " . $e->getMessage());
            return false;
        }
    }

    // NEW METHOD: Update a specific service offering
    public function updateServiceOffering($service_offering_id, $vendor_id, $data) {
        try {
            $query = "UPDATE vendor_service_offerings SET
                price_range_min = ?,
                price_range_max = ?,
                description = ?,
                updated_at = NOW()
                WHERE id = ? AND vendor_id = ?"; // Ensure vendor ownership

            $params = [
                $data['price_min'] ?? null,
                $data['price_max'] ?? null,
                $data['description'] ?? null,
                $service_offering_id,
                $vendor_id
            ];

            $stmt = $this->conn->prepare($query);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Update service offering error: " . $e->getMessage());
            throw new Exception("Failed to update service offering: " . $e->getMessage());
        }
    }

    // NEW METHOD: Delete a specific service offering (and all its packages/images)
    public function deleteServiceOffering($service_offering_id, $vendor_id) {
        try {
            // REMOVED: $this->conn->beginTransaction(); // This method now relies on caller's transaction

            // All associated packages and their images will be cascade-deleted by database foreign key constraints
            // We need to fetch package images manually to delete physical files
            $packages_with_images = $this->getPackagesByServiceOfferingId($service_offering_id);
            $all_package_images_to_delete = [];
            foreach ($packages_with_images as $package) {
                if (!empty($package['images'])) {
                    $all_package_images_to_delete = array_merge($all_package_images_to_delete, $package['images']);
                }
            }
            
            // Delete the main service offering record
            $stmt = $this->conn->prepare("DELETE FROM vendor_service_offerings WHERE id = ? AND vendor_id = ?");
            $success = $stmt->execute([$service_offering_id, $vendor_id]);

            if ($success) {
                require_once __DIR__ . '/UploadHandler.class.php';
                $uploader = new UploadHandler();

                foreach ($all_package_images_to_delete as $image) {
                    $relative_path_from_web_root = str_replace(BASE_URL, '', $image['image_url']);
                    $physical_path = realpath(__DIR__ . '/../../') . '/' . $relative_path_from_web_root;

                    if (file_exists($physical_path)) {
                        $uploader->deleteFile(basename($physical_path), dirname($relative_path_from_web_root) . '/');
                    } else {
                        error_log("File not found for deletion during service offering delete: " . $physical_path);
                    }
                }
                // REMOVED: $this->conn->commit(); // This method now relies on caller's transaction
                return true;
            }
            // REMOVED: $this->conn->rollBack(); // This method now relies on caller's transaction
            return false;
        } catch (PDOException $e) {
            // REMOVED: $this->conn->rollBack(); // This method now relies on caller's transaction
            error_log("Delete service offering error: " . $e->getMessage());
            throw new Exception("Failed to delete service offering: " . $e->getMessage());
        } catch (Exception $e) {
            // REMOVED: $this->conn->rollBack(); // This method now relies on caller's transaction
            error_log("Delete service offering general error: " . $e->getMessage());
            throw $e;
        }
    }

    // Get vendor services (original method, fetches what's in vendor_service_offerings)
    // Now also includes a count of packages for display in the management page
    public function getVendorServices($vendor_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT vso.*, vs.service_name, vc.category_name,
                       (SELECT COUNT(sp.id) FROM service_packages sp WHERE sp.service_offering_id = vso.id) as package_count
                FROM vendor_service_offerings vso
                JOIN vendor_services vs ON vso.service_id = vs.id
                JOIN vendor_categories vc ON vs.category_id = vc.id
                WHERE vso.vendor_id = ? AND vso.is_active = TRUE
                ORDER BY vc.category_name, vs.service_name
            ");
            $stmt->execute([$vendor_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get vendor services error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the total revenue generated for a specific vendor from completed bookings.
     * @param int $vendorId The ID of the vendor profile.
     * @return float Total revenue or 0.00 if no completed bookings.
     */
    public function getTotalRevenue($vendorId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT SUM(final_amount) AS total_revenue
                FROM bookings b
                WHERE b.vendor_id = ? AND b.status = 'completed'
            ");
            $stmt->execute([$vendorId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (float)($result['total_revenue'] ?? 0.00);
        } catch (PDOException $e) {
            error_log("Error getting total revenue for vendor {$vendorId}: " . $e->getMessage());
            return 0.00;
        }
    }

    /**
     * Get the count of active service offerings for a specific vendor.
     * @param int $vendorId The ID of the vendor profile.
     * @return int Count of active service offerings.
     */
    public function getActiveServiceOfferingsCount($vendorId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) AS active_count
                FROM vendor_service_offerings
                WHERE vendor_id = ? AND is_active = TRUE
            ");
            $stmt->execute([$vendorId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($result['active_count'] ?? 0);
        } catch (PDOException $e) {
            error_log("Error getting active service offerings count for vendor {$vendorId}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get a list of recent service offerings for a vendor, including their prices.
     * @param int $vendorId The ID of the vendor profile.
     * @param int $limit The maximum number of offerings to retrieve.
     * @return array An array of service offerings.
     */
    public function getRecentServiceOfferings($vendorId, $limit = 5) {
        try {
            $stmt = $this->conn->prepare("
                SELECT vso.id, vs.service_name, vso.price_range_min, vso.price_range_max, vso.created_at
                FROM vendor_service_offerings vso
                JOIN vendor_services vs ON vso.service_id = vs.id
                WHERE vso.vendor_id = ? AND vso.is_active = TRUE
                ORDER BY vso.created_at DESC
                LIMIT ?
            ");
            $stmt->bindParam(1, $vendorId, PDO::PARAM_INT);
            $stmt->bindParam(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting recent service offerings for vendor {$vendorId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * NEW METHOD: Get a single service offering by its ID
     * Now also fetches associated packages and their images
     */
    public function getServiceOfferingById($service_offering_id, $vendor_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT vso.*, vs.service_name, vc.category_name
                FROM vendor_service_offerings vso
                JOIN vendor_services vs ON vso.service_id = vs.id
                JOIN vendor_categories vc ON vs.category_id = vc.id
                WHERE vso.id = ? AND vso.vendor_id = ?
            ");
            $stmt->execute([$service_offering_id, $vendor_id]);
            $offering = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($offering) {
                // Fetch packages for this service offering
                $offering['packages'] = $this->getPackagesByServiceOfferingId($service_offering_id);
            }
            return $offering;
        } catch (PDOException $e) {
            error_log("Get service offering by ID error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * NEW METHOD: Get service_id from service_offering_id
     * @param int $serviceOfferingId The ID from vendor_service_offerings
     * @return int|false The service_id from vendor_services, or false if not found
     */
    public function getServiceIdByOfferingId(int $serviceOfferingId) {
        try {
            $stmt = $this->conn->prepare("SELECT service_id FROM vendor_service_offerings WHERE id = ?");
            $stmt->execute([$serviceOfferingId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['service_id'] ?? false;
        } catch (PDOException $e) {
            error_log("Vendor.class.php getServiceIdByOfferingId error: " . $e->getMessage());
            return false;
        }
    }


    // NEW METHOD: Get all packages for a specific service offering
    public function getPackagesByServiceOfferingId($service_offering_id) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM service_packages WHERE service_offering_id = ? ORDER BY display_order ASC, created_at ASC");
            $stmt->execute([$service_offering_id]);
            $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($packages as &$package) {
                $package['images'] = $this->getServicePackageImages($package['id']);
            }
            return $packages;
        } catch (PDOException $e) {
            error_log("Get packages by service offering ID error: " . $e->getMessage());
            return [];
        }
    }

    // NEW METHOD: Add a new service package
    public function addServicePackage($service_offering_id, $data) {
        try {
            $stmt = $this->conn->prepare("INSERT INTO service_packages
                (service_offering_id, package_name, package_description, price_min, price_max, is_active, display_order)
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([
                $service_offering_id,
                $data['package_name'],
                $data['package_description'] ?? null,
                $data['price_min'] ?? null,
                $data['price_max'] ?? null,
                $data['is_active'] ?? 1,
                $data['display_order'] ?? 0
            ]);
            return $result ? $this->conn->lastInsertId() : false;
        } catch (PDOException $e) {
            error_log("Add service package error: " . $e->getMessage());
            throw new Exception("Failed to add package: " . $e->getMessage());
        }
    }

    // NEW METHOD: Update a service package
    public function updateServicePackage($package_id, $service_offering_id, $data) {
        try {
            $query = "UPDATE service_packages SET
                package_name = ?,
                package_description = ?,
                price_min = ?,
                price_max = ?,
                is_active = ?,
                display_order = ?,
                updated_at = NOW()
                WHERE id = ? AND service_offering_id = ?"; // Ensure ownership through service_offering_id

            $params = [
                $data['package_name'],
                $data['package_description'] ?? null,
                $data['price_min'] ?? null,
                $data['price_max'] ?? null,
                $data['is_active'] ?? 1,
                $data['display_order'] ?? 0,
                $package_id,
                $service_offering_id
            ];

            $stmt = $this->conn->prepare($query);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Update service package error: " . $e->getMessage());
            throw new Exception("Failed to update package: " . $e->getMessage());
        }
    }

    // NEW METHOD: Delete a service package
    public function deleteServicePackage($package_id, $service_offering_id) {
        try {
            // REMOVED: $this->conn->beginTransaction(); // This method now relies on caller's transaction

            // Fetch images to delete physical files
            $images_to_delete = $this->getServicePackageImages($package_id);

            // Delete package from database
            $stmt = $this->conn->prepare("DELETE FROM service_packages WHERE id = ? AND service_offering_id = ?");
            $success = $stmt->execute([$package_id, $service_offering_id]);

            if ($success) {
                require_once __DIR__ . '/UploadHandler.class.php';
                $uploader = new UploadHandler();

                foreach ($images_to_delete as $image) {
                    $relative_path_from_web_root = str_replace(BASE_URL, '', $image['image_url']);
                    $physical_path = realpath(__DIR__ . '/../../') . '/' . $relative_path_from_web_root;

                    if (file_exists($physical_path)) {
                        $uploader->deleteFile(basename($physical_path), dirname($relative_path_from_web_root) . '/');
                    } else {
                        error_log("File not found for deletion during package delete: " . $physical_path);
                    }
                }
                // REMOVED: $this->conn->commit(); // This method now relies on caller's transaction
                return true;
            }
            // REMOVED: $this->conn->rollBack(); // This method now relies on caller's transaction
            return false;
        } catch (PDOException $e) {
            // REMOVED: $this->conn->rollBack(); // This method now relies on caller's transaction
            error_log("Delete service package error: " . $e->getMessage());
            throw new Exception("Failed to delete package: " . $e->getMessage());
        } catch (Exception $e) {
            // REMOVED: $this->conn->rollBack(); // This method now relies on caller's transaction
            error_log("Delete service package general error: " . $e->getMessage());
            throw $e;
        }
    }

    // NEW METHOD: Add images for a service package
    public function addServicePackageImages($service_package_id, $image_urls) {
        if (empty($image_urls)) {
            return true;
        }
        try {
            $sql = "INSERT INTO service_package_images (service_package_id, image_url) VALUES ";
            $values = [];
            $params = [];
            foreach ($image_urls as $url) {
                $values[] = "(?, ?)";
                $params[] = $service_package_id;
                $params[] = $url;
            }
            $sql .= implode(", ", $values);
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Add service package images error: " . $e->getMessage());
            return false;
        }
    }

    // NEW METHOD: Get images for a service package
    public function getServicePackageImages($service_package_id) {
        try {
            $stmt = $this->conn->prepare("SELECT id, image_url FROM service_package_images WHERE service_package_id = ? ORDER BY display_order ASC, created_at ASC");
            $stmt->execute([$service_package_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get service package images error: " . $e->getMessage());
            return [];
        }
    }

    // NEW METHOD: Delete a specific image from a service package
    public function deleteServicePackageImage($image_id, $service_package_id) {
        try {
            // REMOVED: $this->conn->beginTransaction(); // This method now relies on caller's transaction
            // Get image URL to delete physical file
            $stmt = $this->conn->prepare("SELECT image_url FROM service_package_images WHERE id = ? AND service_package_id = ?");
            $stmt->execute([$image_id, $service_package_id]);
            $image_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($image_data) {
                require_once __DIR__ . '/UploadHandler.class.php';
                $uploader = new UploadHandler();
                $relative_path_from_web_root = str_replace(BASE_URL, '', $image_data['image_url']);
                $physical_path = realpath(__DIR__ . '/../../') . '/' . $relative_path_from_web_root;

                // Delete from DB
                $stmt_delete_db = $this->conn->prepare("DELETE FROM service_package_images WHERE id = ? AND service_package_id = ?");
                $success = $stmt_delete_db->execute([$image_id, $service_package_id]);

                // Delete physical file only if DB delete was successful
                if ($success) {
                    if (file_exists($physical_path)) {
                        $uploader->deleteFile(basename($physical_path), dirname($relative_path_from_web_root) . '/');
                    } else {
                        error_log("File not found for deletion (service package image): " . $physical_path);
                    }
                }
                // REMOVED: $this->conn->commit(); // This method now relies on caller's transaction
                return true;
            }
            // REMOVED: $this->conn->rollBack(); // This method now relies on caller's transaction
            return false;
        } catch (PDOException $e) {
            // REMOVED: $this->conn->rollBack(); // This method now relies on caller's transaction
            error_log("Delete service package image error: " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            // REMOVED: $this->conn->rollBack(); // This method now relies on caller's transaction
            error_log("Delete service package image general error: " . $e->getMessage());
            return false;
        }
    }

    // Add a new portfolio item.
    public function addPortfolioItem($vendor_id, $data) {
        try {
            $stmt = $this->conn->prepare("INSERT INTO vendor_portfolios
                (vendor_id, title, description, event_type_id,
                 venue_name, venue_address, venue_city, venue_state, venue_country, venue_postal_code,
                 video_url, project_date, project_charges, client_testimonial, is_featured)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $result = $stmt->execute([
                $vendor_id,
                $data['title'],
                $data['description'] ?? null,
                $data['event_type_id'] ?? null,
                $data['venue_name'] ?? null,
                $data['venue_address'] ?? null,
                $data['venue_city'] ?? null,
                $data['venue_state'] ?? null,
                $data['venue_country'] ?? null,
                $data['venue_postal_code'] ?? null,
                $data['video_url'] ?? null,
                $data['project_date'] ?? null,
                $data['project_charges'] ?? null,
                $data['client_testimonial'] ?? null,
                $data['is_featured'] ?? false
            ]);

            if ($result) {
                return $this->conn->lastInsertId(); // Return the ID
            }
            return false;
        } catch (PDOException $e) {
            error_log("Add portfolio item error: " . $e->getMessage());
            return false;
        }
    }

    // Updates an existing portfolio item.
    public function updatePortfolioItem($portfolioItemId, $vendorId, $data) {
        try {
            $query = "UPDATE vendor_portfolios SET
                title = ?,
                description = ?,
                event_type_id = ?,
                venue_name = ?,
                venue_address = ?,
                venue_city = ?,
                venue_state = ?,
                venue_country = ?,
                venue_postal_code = ?,
                video_url = ?,
                project_date = ?,
                project_charges = ?,
                client_testimonial = ?,
                is_featured = ?,
                updated_at = NOW()
                WHERE id = ? AND vendor_id = ?"; // Ensure vendor ownership

            $params = [
                $data['title'],
                $data['description'] ?? null,
                $data['event_type_id'] ?? null,
                $data['venue_name'] ?? null,
                $data['venue_address'] ?? null,
                $data['venue_city'] ?? null,
                $data['venue_state'] ?? null,
                $data['venue_country'] ?? null,
                $data['venue_postal_code'] ?? null,
                $data['video_url'] ?? null,
                $data['project_date'] ?? null,
                $data['project_charges'] ?? null,
                $data['client_testimonial'] ?? null,
                $data['is_featured'] ?? false,
                $portfolioItemId,
                $vendorId
            ];

            $stmt = $this->conn->prepare($query);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Update portfolio item error: " . $e->getMessage());
            return false;
        }
    }


    // Deletes a portfolio item.
    public function deletePortfolioItem($portfolioItemId, $vendorId) {
        try {
            // First, get all image URLs associated with this portfolio item
            $images = $this->getPortfolioImagesByItemId($portfolioItemId);

            // REMOVED: $this->conn->beginTransaction(); // This method now relies on caller's transaction

            // Delete portfolio item from database (this will cascade delete from portfolio_images)
            $stmt = $this->conn->prepare("DELETE FROM vendor_portfolios WHERE id = ? AND vendor_id = ?");
            $success = $stmt->execute([$portfolioItemId, $vendorId]);

            if ($success) {
                // Ensure UploadHandler is available. This class will use it.
                require_once __DIR__ . '/UploadHandler.class.php';
                $uploader = new UploadHandler();

                foreach ($images as $image) {
                    $relative_path_from_web_root = str_replace(BASE_URL, '', $image['image_url']);
                    $physical_path = realpath(__DIR__ . '/../../') . '/' . $relative_path_from_web_root;

                    if (file_exists($physical_path)) {
                        $uploader->deleteFile(basename($physical_path), dirname($relative_path_from_web_root) . '/');
                    } else {
                        error_log("File not found for deletion: " . $physical_path);
                    }
                }
                // REMOVED: $this->conn->commit(); // This method now relies on caller's transaction
                return true;
            } else {
                // REMOVED: $this->conn->rollBack(); // This method now relies on caller's transaction
                return false;
            }
        } catch (PDOException $e) {
            // REMOVED: $this->conn->rollBack(); // This method now relies on caller's transaction
            error_log("Delete portfolio item PDO error: " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            // REMOVED: $this->conn->rollBack(); // This method now relies on caller's transaction
            error_log("Delete portfolio item general error: " . $e->getMessage());
            return false;
        }
    }


    /**
     * Add multiple image URLs for a portfolio item.
     * @param int $portfolioItemId
     * @param array $imageUrls Array of image URLs to save.
     * @return bool True on success, false on failure.
     */
    public function addPortfolioImages($portfolioItemId, $imageUrls) {
        if (empty($imageUrls)) {
            return true; // Nothing to add
        }
        try {
            $sql = "INSERT INTO portfolio_images (portfolio_item_id, image_url) VALUES ";
            $values = [];
            $params = [];
            foreach ($imageUrls as $url) {
                $values[] = "(?, ?)";
                $params[] = $portfolioItemId;
                $params[] = $url;
            }
            $sql .= implode(", ", $values);
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Add portfolio images error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all images for a specific portfolio item.
     * @param int $portfolioItemId
     * @return array Array of image data (id, image_url).
     */
    public function getPortfolioImagesByItemId($portfolioItemId) {
        try {
            $stmt = $this->conn->prepare("SELECT id, image_url FROM portfolio_images WHERE portfolio_item_id = ? ORDER BY created_at ASC");
            $stmt->execute([$portfolioItemId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get portfolio images by item ID error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Delete a specific image from a portfolio item.
     * @param int $imageId The ID of the image record in portfolio_images.
     * @param int $portfolioItemId The ID of the parent portfolio item (for verification).
     * @param int $vendorId The ID of the vendor owning the item (for security).
     * @return bool True on success, false on failure.
     */
    public function deletePortfolioImage($imageId, $portfolioItemId, $vendorId) {
        try {
            // REMOVED: $this->conn->beginTransaction(); // This method now relies on caller's transaction
            // First, get the image URL to delete the physical file
            $stmt = $this->conn->prepare("
                SELECT pi.image_url
                FROM portfolio_images pi
                JOIN vendor_portfolios vp ON pi.portfolio_item_id = vp.id
                WHERE pi.id = ? AND pi.portfolio_item_id = ? AND vp.vendor_id = ?
            ");
            $stmt->execute([$imageId, $portfolioItemId, $vendorId]);
            $image_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($image_data) {
                // Ensure UploadHandler is available.
                require_once __DIR__ . '/UploadHandler.class.php';
                $uploader = new UploadHandler();

                // Adjust path for physical deletion (similar to deletePortfolioItem)
                $relative_path_from_web_root = str_replace(BASE_URL, '', $image_data['image_url']);
                $physical_path = realpath(__DIR__ . '/../../') . '/' . $relative_path_from_web_root;

                // Delete from DB
                $stmt_delete_db = $this->conn->prepare("DELETE FROM portfolio_images WHERE id = ? AND portfolio_item_id = ?");
                $success = $stmt_delete_db->execute([$imageId, $portfolioItemId]);

                // Delete physical file only if DB delete was successful
                if ($success) {
                    if (file_exists($physical_path)) {
                        $uploader->deleteFile(basename($physical_path), dirname($relative_path_from_web_root) . '/');
                    } else {
                        error_log("File not found for deletion (single image): " . $physical_path);
                    }
                }
                // REMOVED: $this->conn->commit(); // This method now relies on caller's transaction
                return true;
            }
            // REMOVED: $this->conn->rollBack(); // This method now relies on caller's transaction
            return false; // Image not found or not owned by vendor
        } catch (PDOException $e) {
            // REMOVED: $this->conn->rollBack(); // This method now relies on caller's transaction
            error_log("Delete portfolio image error: " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            // REMOVED: $this->conn->rollBack(); // This method now relies on caller's transaction
            error_log("Delete portfolio image general error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add multiple services for a portfolio item.
     * @param int $portfolioItemId
     * @param array $serviceIds Array of service IDs.
     * @return bool True on success, false on failure.
     */
    public function addPortfolioItemServices($portfolioItemId, $serviceIds) {
        if (empty($serviceIds)) {
            return true; // Nothing to add
        }
        try {
            $sql = "INSERT INTO portfolio_item_services (portfolio_item_id, service_id) VALUES ";
            $values = [];
            $params = [];
            foreach ($serviceIds as $service_id) {
                $values[] = "(?, ?)";
                $params[] = $portfolioItemId;
                $params[] = $service_id;
            }
            $sql .= implode(", ", $values);
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            // Log error, but don't necessarily fail the whole portfolio item creation/update
            error_log("Add portfolio item services error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update (delete and re-insert) services for a portfolio item.
     * @param int $portfolioItemId
     * @param array $serviceIds Array of service IDs.
     * @return bool True on success, false on failure.
     */
    public function updatePortfolioItemServices($portfolioItemId, $serviceIds) {
        try {
            // Delete existing services for this portfolio item
            $stmt_delete = $this->conn->prepare("DELETE FROM portfolio_item_services WHERE portfolio_item_id = ?");
            $stmt_delete->execute([$portfolioItemId]);

            // Add new services (if any are provided)
            $success = $this->addPortfolioItemServices($portfolioItemId, $serviceIds);

            return $success; // Return success status of adding services
        } catch (PDOException $e) {
            error_log("Update portfolio item services error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get services associated with a portfolio item.
     * @param int $portfolioItemId
     * @return array Array of service data (id, service_name).
     */
    public function getPortfolioItemServices($portfolioItemId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT pis.service_id, vs.service_name
                FROM portfolio_item_services pis
                JOIN vendor_services vs ON pis.service_id = vs.id
                WHERE pis.portfolio_item_id = ?
                ORDER BY vs.service_name ASC
            ");
            $stmt->execute([$portfolioItemId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get portfolio item services error: " . $e->getMessage());
            return [];
        }
    }


    /**
     * Retrieves a single portfolio item by its ID.
     * Modified to include associated images and services.
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
            $item_details = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($item_details) {
                $item_details['images'] = $this->getPortfolioImagesByItemId($portfolioItemId);
                $item_details['services_provided'] = $this->getPortfolioItemServices($portfolioItemId); // New: Fetch services
            }
            return $item_details;
        } catch (PDOException $e) {
            error_log("Get portfolio item by ID error: " . $e->getMessage());
            return false;
        }
    }


    /**
     * Get vendor portfolio (used in vendor_portfolio.php and vendor_profile.php)
     * Modified to include only the first image if multiple exist.
     */
    public function getVendorPortfolio($vendor_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT vp.*, et.type_name as event_type_name,
                       (SELECT image_url FROM portfolio_images WHERE portfolio_item_id = vp.id ORDER BY created_at ASC LIMIT 1) as main_image_url
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
        try {
            $stmt = $this->conn->prepare("
                SELECT
                    id,
                    date,
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
        } catch (PDOException $e) {
            error_log("Vendor.class.php getAvailability error: " . $e->getMessage() . " for vendor: {$vendorId}, start: {$startDate}, end: {$endDate}");
            return false; // Return false on error
        }
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
            // Redirect them to a page where they can complete their their vendor profile.
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
        }  catch (PDOException $e) {
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

    /**
     * Search vendors by business name, city, or services offered.
     * @param string|null $search_query The search term. If null, returns all active/verified vendors.
     * @param int $limit Max number of results to return.
     * @return array An array of matching vendor data.
     */
    public function searchVendors($search_query = null, $limit = 20) {
        try {
            $sql = "
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
                WHERE vp.verified = TRUE -- Only search verified vendors
            ";

            $params = [];

            if ($search_query) {
                $sql .= " AND (
                    vp.business_name LIKE ? OR
                    vp.business_city LIKE ? OR
                    vs.service_name LIKE ?
                )";
                $like_param = '%' . $search_query . '%';
                $params[] = $like_param;
                $params[] = $like_param;
                $params[] = $like_param;
            }

            $sql .= "
                GROUP BY vp.id
                ORDER BY vp.rating DESC, vp.total_reviews DESC
                LIMIT ?
            ";
            $params[] = $limit;

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error searching vendors: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all vendor profiles for admin panel.
     * Includes user email, name, and profile image if available.
     * @return array
     */
    public function getAllVendorProfiles() {
        try {
            $stmt = $this->conn->prepare("
                SELECT
                    vp.id,
                    vp.business_name,
                    vp.business_city,
                    vp.rating,
                    vp.total_reviews,
                    vp.verified,
                    u.email,
                    u.first_name,
                    u.last_name,
                    up.profile_image
                FROM vendor_profiles vp
                JOIN users u ON vp.user_id = u.id
                LEFT JOIN user_profiles up ON u.id = up.user_id
                GROUP BY vp.id /* Ensure one row per vendor profile */
                ORDER BY vp.created_at DESC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in Vendor::getAllVendorProfiles(): " . $e->getMessage());
            return [];
        }
    }

    /**
     * NEW METHOD: Add multiple images for a service offering
     */
    public function addServiceOfferingImages($service_offering_id, $image_urls) {
        if (empty($image_urls)) {
            return true;
        }
        try {
            $sql = "INSERT INTO service_offering_images (service_offering_id, image_url) VALUES ";
            $values = [];
            $params = [];
            foreach ($image_urls as $url) {
                $values[] = "(?, ?)";
                $params[] = $service_offering_id;
                $params[] = $url;
            }
            $sql .= implode(", ", $values);
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Add service offering images error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * NEW METHOD: Get all images for a specific service offering
     */
    public function getServiceOfferingImages($service_offering_id) {
        try {
            $stmt = $this->conn->prepare("SELECT id, image_url FROM service_offering_images WHERE service_offering_id = ? ORDER BY created_at ASC");
            $stmt->execute([$service_offering_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get service offering images error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * NEW METHOD: Delete a specific image from a service offering
     */
    public function deleteServiceOfferingImage($image_id, $service_offering_id, $vendor_id) {
        try {
            // First, get the image URL and verify ownership
            $stmt = $this->conn->prepare("
                SELECT soi.image_url
                FROM service_offering_images soi
                JOIN vendor_service_offerings vso ON soi.service_offering_id = vso.id
                WHERE soi.id = ? AND soi.service_offering_id = ? AND vso.vendor_id = ?
            ");
            $stmt->execute([$image_id, $service_offering_id, $vendor_id]);
            $image_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($image_data) {
                require_once __DIR__ . '/UploadHandler.class.php';
                $uploader = new UploadHandler();

                // Adjust path for physical deletion (similar to deletePortfolioItem)
                $relative_path_from_web_root = str_replace(BASE_URL, '', $image_data['image_url']);
                $physical_path = realpath(__DIR__ . '/../../') . '/' . $relative_path_from_web_root;

                // Delete from DB
                $stmt_delete_db = $this->conn->prepare("DELETE FROM service_offering_images WHERE id = ? AND service_offering_id = ?");
                $success = $stmt_delete_db->execute([$image_id, $service_offering_id]);

                // Delete physical file only if DB delete was successful
                if ($success) {
                    if (file_exists($physical_path)) {
                        $uploader->deleteFile(basename($physical_path), dirname($relative_path_from_web_root) . '/');
                    } else {
                        error_log("File not found for deletion (service offering image): " . $physical_path);
                    }
                }
                return $success;
            }
            return false; // Image not found or not owned by vendor
        } catch (PDOException $e) {
            error_log("Delete service offering image error: " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            error_log("Delete service offering image general error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Updates a vendor's verification status.
     * @param int $vendorProfileId The ID of the vendor_profile.
     * @param bool $status True for verified, false for unverified.
     * @return bool True on success, false on failure.
     */
    public function updateVendorVerificationStatus($vendorProfileId, $status) {
        try {
            $stmt = $this->conn->prepare("UPDATE vendor_profiles SET verified = ? WHERE id = ?");
            return $stmt->execute([$status ? 1 : 0, $vendorProfileId]);
        } catch (PDOException $e) {
            error_log("Failed to update vendor verification status for ID {$vendorProfileId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletes a vendor profile. This cascades to delete related records
     * like portfolio items, service offerings, and availability.
     * @param int $vendorProfileId The ID of the vendor_profile to delete.
     * @return bool True on success, false on failure.
     */
    public function deleteVendorProfile($vendorProfileId) {
        try {
            // REMOVED: $this->conn->beginTransaction(); // This method now relies on caller's transaction

            // Fetch any associated physical files (portfolio images, service offering images, package images)
            // This is complex and would ideally be handled by a dedicated file cleanup process
            // or by triggering cascading deletes that call a custom function, which MySQL doesn't natively support.
            // For simplicity here, we'll assume cascade delete in DB handles records, and client manages files
            // or rely on deletePortfolioItem/deleteServiceOffering/deleteServicePackage which include file deletion.

            // Delete from vendor_profiles. This should trigger cascade deletes if DB constraints are set up.
            // It's important that foreign keys on `vendor_id` columns in other tables
            // (e.g., vendor_portfolios, vendor_service_offerings, vendor_availability)
            // are set to `ON DELETE CASCADE`. The provided SQL dump confirms this.
            $stmt = $this->conn->prepare("DELETE FROM vendor_profiles WHERE id = ?");
            $success = $stmt->execute([$vendorProfileId]);

            if ($success) {
                // REMOVED: $this->conn->commit(); // This method now relies on caller's transaction
                return true;
            } else {
                // REMOVED: $this->conn->rollBack(); // This method now relies on caller's transaction
                error_log("Failed to delete vendor profile ID {$vendorProfileId}: " . implode(" | ", $stmt->errorInfo()));
                return false;
            }
        } catch (PDOException $e) {
            // REMOVED: $this->conn->rollBack(); // This method now relies on caller's transaction
            error_log("PDOException deleting vendor profile ID {$vendorProfileId}: " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            // REMOVED: $this->conn->rollBack(); // This method now relies on caller's transaction
            error_log("General Exception deleting vendor profile ID {$vendorProfileId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get booking statistics for a specific vendor.
     * @param int $vendorId The ID of the vendor profile.
     * @return array
     */
    public function getVendorBookingStats($vendorId) {
        try {
            $stmt = $this->conn->prepare("
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
        } catch (PDOException $e) {
            error_log("Error getting vendor booking stats for vendor {$vendorId}: " . $e->getMessage());
            return [
                'total_bookings' => 0,
                'pending_bookings' => 0,
                'confirmed_bookings' => 0,
                'total_revenue' => 0
            ];
        }
    }

    /**
     * Get upcoming bookings for a vendor.
     * @param int $vendorId The ID of the vendor profile.
     * @param int $limit Max number of bookings to return.
     * @return array
     */
    public function getVendorUpcomingBookings($vendorId, $limit = 5) {
        try {
            $stmt = $this->conn->prepare("
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
        } catch (PDOException $e) {
            error_log("Error getting vendor upcoming bookings for vendor {$vendorId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get recent bookings for a vendor.
     * @param int $vendorId The ID of the vendor profile.
     * @param int $limit Max number of bookings to return.
     * @return array
     */
    public function getVendorRecentBookings($vendorId, $limit = 5) {
        try {
            $stmt = $this->conn->prepare("
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
        } catch (PDOException $e) {
            error_log("Error getting vendor recent bookings for vendor {$vendorId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update services offered by a vendor. This method now manages inserting new offerings,
     * updating existing ones (price/description), and deleting those no longer selected.
     * It ensures data consistency in `vendor_service_offerings`.
     *
     * @param int $vendorProfileId The ID of the vendor's profile.
     * @param array $newServiceOfferingsData Array of arrays, each containing 'service_id', 'min_price', 'max_price'.
     * @return bool True on success, false on failure.
     */
    public function updateVendorServiceOfferings($vendorProfileId, array $newServiceOfferingsData) {
        try {
            // REMOVED: $this->conn->beginTransaction(); // This method now relies on caller's transaction

            // 1. Fetch current service offerings for this vendor
            $currentOfferings = $this->getVendorServices($vendorProfileId);
            $currentServiceIds = array_column($currentOfferings, 'service_id');
            $currentOfferingsMap = [];
            foreach ($currentOfferings as $offering) {
                $currentOfferingsMap[$offering['service_id']] = $offering;
            }

            // 2. Process new/updated offerings
            $newlySelectedServiceIds = array_column($newServiceOfferingsData, 'service_id');

            foreach ($newServiceOfferingsData as $newOffering) {
                $serviceId = $newOffering['service_id'];
                $minPrice = $newOffering['min_price'] ?? null;
                $maxPrice = $newOffering['max_price'] ?? null;
                // Description for this overall offering can be added/updated here if it were in the form

                if (in_array($serviceId, $currentServiceIds)) {
                    // Service exists: Update prices/description for existing entry
                    $existingOffering = $currentOfferingsMap[$serviceId];
                    // Only update if changes are detected to minimize writes
                    if ($existingOffering['price_range_min'] != $minPrice || $existingOffering['price_range_max'] != $maxPrice) {
                        $stmt = $this->conn->prepare("
                            UPDATE vendor_service_offerings
                            SET price_range_min = ?, price_range_max = ?, updated_at = NOW()
                            WHERE vendor_id = ? AND service_id = ?
                        ");
                        $stmt->execute([$minPrice, $maxPrice, $vendorProfileId, $serviceId]);
                    }
                } else {
                    // Service is new: Insert new offering
                    $stmt = $this->conn->prepare("
                        INSERT INTO vendor_service_offerings
                        (vendor_id, service_id, price_range_min, price_range_max, created_at)
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$vendorProfileId, $serviceId, $minPrice, $maxPrice]);
                }
            }

            // 3. Delete offerings no longer selected
            $servicesToDelete = array_diff($currentServiceIds, $newlySelectedServiceIds);
            if (!empty($servicesToDelete)) {
                $placeholders = implode(',', array_fill(0, count($servicesToDelete), '?'));
                $stmt = $this->conn->prepare("
                    DELETE FROM vendor_service_offerings
                    WHERE vendor_id = ? AND service_id IN ($placeholders)
                ");
                $stmt->execute(array_merge([$vendorProfileId], $servicesToDelete));
            }

            // REMOVED: $this->conn->commit(); // This method now relies on caller's transaction
            return true;
        } catch (PDOException $e) {
            // REMOVED: $this->conn->rollBack(); // This method now relies on caller's transaction
            error_log("Error in updateVendorServiceOfferings: " . $e->getMessage());
            // Re-throw to allow higher-level error handling
            throw new Exception("Database error updating vendor service offerings: " . $e->getMessage());
        } catch (Exception $e) {
            // REMOVED: $this->conn->rollBack(); // This method now relies on caller's transaction
            error_log("General error in updateVendorServiceOfferings: " . $e->getMessage());
            throw $e;
        }
    }


}
