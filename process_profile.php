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
    $vendor_data = null;
    $vendor_profile_id = null; // Initialize to null
    if ($is_vendor) {
        $vendor_data = $vendor_obj->getVendorByUserId($userId);
        $vendor_profile_id = $vendor_data['id'] ?? null; 
    }

    // --- Handle Profile Information Submission (Personal & Business) ---
    // This block processes `save_profile_changes` from edit_profile.php
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
            $vendor_profile_id_returned = $vendor_obj->registerVendor($userId, $vendor_profile_data);

            if (!$vendor_profile_id_returned) {
                throw new Exception("Failed to save vendor profile data.");
            }
            $vendor_profile_id = $vendor_profile_id_returned; 

            // Handle vendor services offered
            $services_offered = $_POST['services_offered'] ?? [];
            if (!is_array($services_offered)) {
                $services_offered = [];
            }
            
            try {
                if (!$vendor_obj->updateVendorServiceOfferings($vendor_profile_id, $services_offered)) {
                    throw new Exception("Database operation for updating services failed unexpectedly.");
                }
            } catch (Exception $e) {
                $_SESSION['profile_error'] = ($_SESSION['profile_error'] ?? '') . " Error saving services: " . $e->getMessage();
            }
        }

        $_SESSION['profile_success'] = "Profile updated successfully!";
        header("Location: " . BASE_URL . "public/edit_profile.php");
        exit();

    } else { // No specific submit button pressed (e.g., direct access or unexpected POST)
        throw new Exception("Invalid form submission detected.");
    }

} catch (Exception $e) {
    $_SESSION['profile_error'] = $e->getMessage();
    error_log("Profile update/portfolio add error for user $userId: " . $e->getMessage());
    header("Location: " . BASE_URL . "public/edit_profile.php");
    exit();
}
