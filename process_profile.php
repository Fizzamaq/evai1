<?php
// TEMPORARY: Enable full error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../includes/config.php';
require_once '../classes/User.class.php';     // Include User class
require_once '../classes/Vendor.class.php';   // Include Vendor class for vendor profile
require_once '../classes/UploadHandler.class.php'; // Corrected: now in classes/ and named UploadHandler.class.php

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "public/login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$is_vendor = (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 2); // Check if current user is a vendor

try {
    $user = new User($pdo); // Pass PDO
    $uploader = new UploadHandler(); // Instantiate UploadHandler
    $vendor_obj = new Vendor($pdo); // Instantiate Vendor object for vendor-specific actions

    // Fetch current vendor data if user is a vendor
    // This call is still useful to determine $vendor_profile_id if it already exists
    // but the error check based on it needs to be moved/removed for initial registration flow.
    $vendor_data = null;
    $vendor_profile_id = null; // Initialize to null
    if ($is_vendor) {
        $vendor_data = $vendor_obj->getVendorByUserId($userId);
        $vendor_profile_id = $vendor_data['id'] ?? null; // This will be null for new vendors
        // Removed the "throw new Exception('Vendor profile not found for this user.')" here.
        // It will now be handled by registerVendor and later checks.
    }

    // --- Handle Profile Information Submission (Personal & Business) ---
    if (isset($_POST['save_profile_changes'])) {
        // Process User Profile Data
        $user_profile_data = [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'address' => $_POST['address'] ?? null,
            'city' => $_POST['city'] ?? null,
            'state' => $_POST['state'] ?? null,
            'country' => $_POST['country'] ?? null,
            'postal_code' => $_POST['postal_code'] ?? null
        ];

        // Handle profile picture upload
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $uploaded_filename = $uploader->handleUpload($_FILES['profile_image'], 'users/');
            $user->updateProfileImage($userId, $uploaded_filename);
        } else if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            throw new Exception("File upload error for profile image: " . $_FILES['profile_image']['error']);
        }

        // Update general user profile
        if (!$user->updateProfile($userId, $user_profile_data)) {
            throw new Exception("Failed to update personal profile data.");
        }

        // --- Process Vendor Profile Data (if user is a vendor and this section was submitted) ---
        if ($is_vendor) {
            $vendor_profile_data = [
                'business_name' => trim($_POST['business_name'] ?? ''),
                'business_license' => trim($_POST['business_license'] ?? ''),
                'tax_id' => trim($_POST['tax_id'] ?? ''),
                'website' => trim($_POST['website'] ?? ''),
                'business_address' => trim($_POST['business_address'] ?? ''),
                'business_city' => trim($_POST['business_city'] ?? ''),
                'business_state' => trim($_POST['business_state'] ?? ''),
                'business_country' => trim($_POST['business_country'] ?? ''),
                'business_postal_code' => trim($_POST['business_postal_code'] ?? ''),
                'service_radius' => !empty($_POST['service_radius']) ? (int)$_POST['service_radius'] : null,
                'min_budget' => !empty($_POST['min_budget']) ? (float)$_POST['min_budget'] : null,
                'max_budget' => !empty($_POST['max_budget']) ? (float)$_POST['max_budget'] : null,
                'experience_years' => !empty($_POST['experience_years']) ? (int)$_POST['experience_years'] : null
            ];

            // Basic validation for required vendor fields
            if (empty($vendor_profile_data['business_name']) || empty($vendor_profile_data['business_address']) ||
                empty($vendor_profile_data['business_city']) || empty($vendor_profile_data['business_state']) ||
                empty($vendor_profile_data['business_country']) || empty($vendor_profile_data['business_postal_code'])) {
                throw new Exception("Please fill in all required business information fields for your vendor profile.");
            }

            // Use registerVendor which handles both insert (if new) and update (if exists)
            // This is where the vendor_profile_id is actually created/updated
            $vendor_profile_id_returned = $vendor_obj->registerVendor($userId, $vendor_profile_data);

            if (!$vendor_profile_id_returned) {
                throw new Exception("Failed to save vendor profile data."); // Changed message to 'save' as it's for both create/update
            }
            // Ensure vendor_profile_id is set for subsequent actions in this request
            $vendor_profile_id = $vendor_profile_id_returned; 

            // Handle vendor services offered
            $services_offered = $_POST['services_offered'] ?? [];
            if (!is_array($services_offered)) {
                $services_offered = [];
            }

            // Debugging: Check received services_offered (Temporarily uncomment to log to error_log)
            /*
            error_log('$_POST[\'services_offered\'] received: ' . print_r($_POST['services_offered'], true));
            error_log('Final $services_offered array: ' . print_r($services_offered, true));
            error_log('Vendor Profile ID before update: ' . var_export($vendor_profile_id, true)); // Confirm it's correct now
            */
            // Do NOT put exit() here unless you want to stop processing completely.
            // Rely on error_log or $_SESSION['profile_error'] for feedback.


            // Add explicit try-catch for service offerings update
            try {
                if (!$vendor_obj->updateVendorServiceOfferings($vendor_profile_id, $services_offered)) {
                    // If method returns false, it failed.
                    throw new Exception("Database operation for updating services failed unexpectedly.");
                }
            } catch (Exception $e) {
                // Catch any exception from updateVendorServiceOfferings
                // Append this error to existing profile_error session message
                $_SESSION['profile_error'] = ($_SESSION['profile_error'] ?? '') . " Error saving services: " . $e->getMessage();
                // Continue with the overall success message if other parts passed, or just show this error.
                // For simplicity, we'll let the main success message take precedence unless this is the only error.
            }
        }

        $_SESSION['profile_success'] = "Profile updated successfully!";
        header("Location: " . BASE_URL . "public/edit_profile.php");
        exit();

    } elseif (isset($_POST['add_portfolio_item'])) { // --- Handle NEW Portfolio Item Submission ---
        // This block only runs if the "Add Portfolio Item" button was pressed
        
        // This check is valid here, as adding a portfolio item ALWAYS requires an existing vendor_profile_id
        if (!$is_vendor || !$vendor_profile_id) {
            throw new Exception("Access denied or vendor profile not found to add portfolio item.");
        }

        // Basic validation for portfolio item fields
        if (empty(trim($_POST['portfolio_title'] ?? ''))) {
            throw new Exception("Portfolio Item Title is required.");
        }

        $portfolio_image_url = null;
        if (isset($_FILES['portfolio_image_upload']) && $_FILES['portfolio_image_upload']['error'] === UPLOAD_ERR_OK) {
            $uploaded_portfolio_filename = $uploader->handleUpload($_FILES['portfolio_image_upload'], 'vendors/');
            $portfolio_image_url = 'assets/uploads/vendors/' . $uploaded_portfolio_filename;
        } else if (isset($_FILES['portfolio_image_upload']) && $_FILES['portfolio_image_upload']['error'] !== UPLOAD_ERR_NO_FILE) {
            throw new Exception("Portfolio image upload error: " . $_FILES['portfolio_image_upload']['error']);
        }

        $portfolio_data = [
            'title' => trim($_POST['portfolio_title']),
            'description' => trim($_POST['portfolio_description'] ?? ''),
            'event_type_id' => !empty($_POST['portfolio_event_type_id']) ? (int)$_POST['portfolio_event_type_id'] : null,
            'image_url' => $portfolio_image_url,
            'video_url' => trim($_POST['portfolio_video_url'] ?? ''), 
            'project_date' => !empty($_POST['portfolio_project_date']) ? $_POST['portfolio_project_date'] : null,
            'client_testimonial' => trim($_POST['portfolio_testimonial'] ?? ''),
            'is_featured' => isset($_POST['portfolio_is_featured']) ? 1 : 0
        ];

        if (!$vendor_obj->addPortfolioItem($vendor_profile_id, $portfolio_data)) {
            throw new Exception("Failed to add new portfolio item. Database error.");
        } else {
            $_SESSION['profile_success'] = "New portfolio item added successfully!"; // Unified success message
            header("Location: " . BASE_URL . "public/edit_profile.php");
            exit();
        }

    } else { // No specific submit button pressed (e.g., direct access or unexpected POST)
        throw new Exception("Invalid form submission detected.");
    }

} catch (Exception $e) {
    $_SESSION['profile_error'] = $e->getMessage();
    error_log("Profile update/portfolio add error for user $userId: " . $e->getMessage()); // Log detailed error
    header("Location: " . BASE_URL . "public/edit_profile.php");
    exit();
}
