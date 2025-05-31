<?php
// TEMPORARY: Enable full error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../includes/config.php';
require_once '../classes/User.class.php';     // Include User class
require_once '../classes/Vendor.class.php';   // Include Vendor class
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
    $vendor_obj = new Vendor($pdo); // Instantiate Vendor object for portfolio actions

    // --- Process User Profile Data ---
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
        $user->updateProfileImage($userId, $uploaded_filename); // Call updateProfileImage
    } else if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Handle other file upload errors, but only if a file was actually attempted to be uploaded
        throw new Exception("File upload error for profile image: " . $_FILES['profile_image']['error']);
    }

    // Update general user profile
    if (!$user->updateProfile($userId, $user_profile_data)) {
        throw new Exception("Failed to update personal profile data.");
    }

    // --- Process Vendor Profile Data (if user is a vendor) ---
    if ($is_vendor) {
        // Fetch current vendor data to ensure we have the vendor_profile_id
        $vendor_data = $vendor_obj->getVendorByUserId($userId);
        $vendor_profile_id = $vendor_data['id'] ?? null;

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
        $vendor_profile_id_returned = $vendor_obj->registerVendor($userId, $vendor_profile_data);

        if (!$vendor_profile_id_returned) {
            throw new Exception("Failed to update vendor profile data.");
        }
        // Ensure vendor_profile_id is set for subsequent actions
        $vendor_profile_id = $vendor_profile_id_returned; 

        // Handle vendor services offered
        $services_offered = $_POST['services_offered'] ?? []; // This will be an array of service IDs
        if (!is_array($services_offered)) {
            $services_offered = []; // Ensure it's an array
        }

        if (!$vendor_obj->updateVendorServiceOfferings($vendor_profile_id, $services_offered)) {
            throw new Exception("Failed to update vendor service offerings.");
        }

        // --- Handle NEW Portfolio Item Submission ---
        // Check if portfolio item data is provided AND if an image was uploaded successfully
        // We check for 'portfolio_title' as the primary indicator that a new portfolio item is being added
        if (isset($_POST['portfolio_title']) && !empty(trim($_POST['portfolio_title']))) {
            $portfolio_image_url = null;
            
            // Only attempt file upload if a file was selected and there's no error
            if (isset($_FILES['portfolio_image_upload']) && $_FILES['portfolio_image_upload']['error'] === UPLOAD_ERR_OK) {
                $uploaded_portfolio_filename = $uploader->handleUpload($_FILES['portfolio_image_upload'], 'vendors/');
                $portfolio_image_url = 'assets/uploads/vendors/' . $uploaded_portfolio_filename;
            } else if (isset($_FILES['portfolio_image_upload']) && $_FILES['portfolio_image_upload']['error'] !== UPLOAD_ERR_NO_FILE) {
                // If a file was attempted but failed, throw an error
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

            // Use the vendor_profile_id obtained from the registration/update
            if (!$vendor_obj->addPortfolioItem($vendor_profile_id, $portfolio_data)) {
                // If adding portfolio item fails, it should not halt the entire profile update process
                // but should set an error message.
                $_SESSION['profile_error'] = ($_SESSION['profile_error'] ?? '') . " Failed to add new portfolio item.";
                error_log("Failed to add portfolio item for vendor $vendor_profile_id during profile update.");
            } else {
                $_SESSION['profile_success_portfolio'] = "New portfolio item added!"; // Specific success message for portfolio
            }
        }
    }

    $_SESSION['profile_success'] = "Profile updated successfully!";
    // Append portfolio success message if it exists
    if (isset($_SESSION['profile_success_portfolio'])) {
        $_SESSION['profile_success'] .= " " . $_SESSION['profile_success_portfolio'];
        unset($_SESSION['profile_success_portfolio']);
    }

    // Redirect based on user type after successful update
    if ($is_vendor) {
        // Redirect to edit_profile.php so they can see the form cleared and success message
        header("Location: " . BASE_URL . "public/edit_profile.php"); 
    } else {
        header("Location: " . BASE_URL . "public/profile.php");
    }
    exit();

} catch (Exception $e) {
    $_SESSION['profile_error'] = $e->getMessage();
    error_log("Profile update error for user $userId: " . $e->getMessage()); // Log detailed error
    // For debugging, you might want to uncomment the 'die()' below temporarily
    // die("Error: " . $e->getMessage()); 
    header("Location: " . BASE_URL . "public/edit_profile.php");
    exit();
}
