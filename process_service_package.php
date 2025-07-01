<?php
session_start();
require_once '../includes/config.php';
require_once '../classes/User.class.php';
require_once '../classes/Vendor.class.php';
require_once '../classes/UploadHandler.class.php';

// Enable error reporting temporarily for debugging. REMOVE IN PRODUCTION!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$vendor_obj = new Vendor($pdo);
$uploader = new UploadHandler();

// Ensure vendor is logged in and has access
$vendor_obj->verifyVendorAccess();

// $_SESSION['vendor_id'] should now be set by verifyVendorAccess()
$vendor_profile_id = $_SESSION['vendor_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $service_offering_id = $_POST['service_offering_id'] ?? null; // The parent service offering ID
    $package_id = $_POST['package_id'] ?? null; // The specific package ID being acted upon

    // Common data for add/edit operations on a package
    $package_data = [
        'package_name' => trim($_POST['package_name'] ?? ''),
        'package_description' => trim($_POST['package_description'] ?? ''),
        'price_min' => !empty($_POST['price_min']) ? (float)$_POST['price_min'] : null,
        'price_max' => !empty($_POST['price_max']) ? (float)$_POST['price_max'] : null,
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'display_order' => !empty($_POST['display_order']) ? (int)$_POST['display_order'] : 0,
    ];

    try {
        // This script's overall transaction is removed because individual Vendor class methods
        // now handle their own database transactions for atomicity.

        switch ($action) {
            case 'add_package':
                if (empty($service_offering_id) || empty($package_data['package_name'])) {
                    throw new Exception("Missing package name or parent service ID to add a package.");
                }

                // Verify that the service_offering_id belongs to this vendor
                $parent_service_offering = $vendor_obj->getServiceOfferingById($service_offering_id, $vendor_profile_id);
                if (!$parent_service_offering) {
                    throw new Exception("Parent service offering not found or access denied.");
                }

                // Vendor::addServicePackage handles its own internal transaction
                $new_package_id = $vendor_obj->addServicePackage((int)$service_offering_id, $package_data);

                if (!$new_package_id) {
                    throw new Exception("Failed to add service package to database.");
                }

                // Handle image uploads for the new package
                $new_images_count = 0;
                if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                    foreach ($_FILES['images']['name'] as $name) {
                        if (!empty($name)) {
                            $new_images_count++;
                        }
                    }
                }
                if ($new_images_count > 10) { // Max 10 images for a package
                    throw new Exception("You can only upload a maximum of 10 images per package.");
                }

                if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                    $uploaded_image_urls = [];
                    foreach ($_FILES['images']['name'] as $key => $name) {
                        if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                            $file = [
                                'name' => $_FILES['images']['name'][$key],
                                'type' => $_FILES['images']['type'][$key],
                                'tmp_name' => $_FILES['images']['tmp_name'][$key],
                                'error' => $_FILES['images']['error'][$key],
                                'size' => $_FILES['images']['size'][$key]
                            ];
                            $uploaded_filename = $uploader->handleUpload($file, 'vendors/service_packages/'); // Dedicated subfolder
                            $uploaded_image_urls[] = 'assets/uploads/vendors/service_packages/' . $uploaded_filename;
                        } else if ($_FILES['images']['error'][$key] !== UPLOAD_ERR_NO_FILE) {
                            throw new Exception("File upload error for image " . htmlspecialchars($name) . ": " . $_FILES['images']['error'][$key]);
                        }
                    }
                    if (!empty($uploaded_image_urls)) {
                        $vendor_obj->addServicePackageImages($new_package_id, $uploaded_image_urls);
                    }
                }

                $_SESSION['success_message'] = 'Package added successfully!';
                header('Location: ' . BASE_URL . 'public/vendor_manage_services.php?id=' . $service_offering_id);
                exit();

            case 'edit_package':
                if (empty($package_id) || empty($service_offering_id) || empty($package_data['package_name'])) {
                    throw new Exception("Missing package ID, parent service ID, or package name for editing.");
                }
                
                // Verify ownership by fetching the parent service offering
                $parent_service_offering = $vendor_obj->getServiceOfferingById($service_offering_id, $vendor_profile_id);
                if (!$parent_service_offering) {
                    throw new Exception("Parent service offering not found or access denied.");
                }
                // Also verify that the package belongs to this service offering
                $package_exists = false;
                foreach ($parent_service_offering['packages'] as $pkg) {
                    if ($pkg['id'] == $package_id) {
                        $package_exists = true;
                        break;
                    }
                }
                if (!$package_exists) {
                    throw new Exception("Package not found under specified service offering or access denied.");
                }

                // Vendor::updateServicePackage handles its own internal transaction
                $update_success = $vendor_obj->updateServicePackage((int)$package_id, (int)$service_offering_id, $package_data);
                
                if (!$update_success) {
                    throw new Exception("Failed to update service package details.");
                }

                // Handle image deletions and new uploads
                $images_to_delete_ids = [];
                $existing_images_to_keep_count = 0;
                if (isset($_POST['existing_images']) && is_array($_POST['existing_images'])) {
                    foreach ($_POST['existing_images'] as $image_id => $action_val) {
                        if ($action_val === 'delete') {
                            $images_to_delete_ids[] = (int)$image_id;
                        } else {
                            $existing_images_to_keep_count++;
                        }
                    }
                }
                
                $new_images_count = 0;
                if (isset($_FILES['new_images']) && !empty($_FILES['new_images']['name'][0])) {
                    foreach ($_FILES['new_images']['name'] as $name) {
                        if (!empty($name)) {
                            $new_images_count++;
                        }
                    }
                }

                if (($existing_images_to_keep_count + $new_images_count) > 10) { // Max 10 images total per package
                    throw new Exception("You can only have a maximum of 10 images in total (including existing ones) for this package. Please adjust your selection.");
                }

                if (!empty($images_to_delete_ids)) {
                    foreach ($images_to_delete_ids as $image_id) {
                        $vendor_obj->deleteServicePackageImage($image_id, (int)$package_id);
                    }
                }

                if (isset($_FILES['new_images']) && !empty($_FILES['new_images']['name'][0])) {
                    $uploaded_image_urls = [];
                    foreach ($_FILES['new_images']['name'] as $key => $name) {
                        if ($_FILES['new_images']['error'][$key] === UPLOAD_ERR_OK) {
                            $file = [
                                'name' => $_FILES['new_images']['name'][$key],
                                'type' => $_FILES['new_images']['type'][$key],
                                'tmp_name' => $_FILES['new_images']['tmp_name'][$key],
                                'error' => $_FILES['new_images']['error'][$key],
                                'size' => $_FILES['new_images']['size'][$key]
                            ];
                            $uploaded_filename = $uploader->handleUpload($file, 'vendors/service_packages/'); // Dedicated subfolder
                            $uploaded_image_urls[] = 'assets/uploads/vendors/service_packages/' . $uploaded_filename;
                        } else if ($_FILES['new_images']['error'][$key] !== UPLOAD_ERR_NO_FILE) {
                            throw new Exception("File upload error for image " . htmlspecialchars($name) . ": " . $_FILES['new_images']['error'][$key]);
                        }
                    }
                    if (!empty($uploaded_image_urls)) {
                        $vendor_obj->addServicePackageImages((int)$package_id, $uploaded_image_urls);
                    }
                }
                
                $_SESSION['success_message'] = 'Package updated successfully!';
                header('Location: ' . BASE_URL . 'public/vendor_manage_services.php?id=' . $service_offering_id);
                exit();

            case 'delete_package':
                if (empty($package_id) || empty($service_offering_id)) {
                    throw new Exception("Missing package ID or parent service ID for deletion.");
                }
                
                // Verify ownership by fetching the parent service offering
                $parent_service_offering = $vendor_obj->getServiceOfferingById($service_offering_id, $vendor_profile_id);
                if (!$parent_service_offering) {
                    throw new Exception("Parent service offering not found or access denied.");
                }
                // Also verify that the package belongs to this service offering
                $package_exists = false;
                foreach ($parent_service_offering['packages'] as $pkg) {
                    if ($pkg['id'] == $package_id) {
                        $package_exists = true;
                        break;
                    }
                }
                if (!$package_exists) {
                    throw new Exception("Package not found under specified service offering or access denied.");
                }

                // Vendor::deleteServicePackage handles its own internal transaction
                if ($vendor_obj->deleteServicePackage((int)$package_id, (int)$service_offering_id)) {
                    $_SESSION['success_message'] = 'Package deleted successfully!';
                } else {
                    throw new Exception('Failed to delete package.');
                }
                header('Location: ' . BASE_URL . 'public/vendor_manage_services.php?id=' . $service_offering_id);
                exit();

            default:
                throw new Exception("Invalid action specified.");
        }
    } catch (Exception $e) {
        // If an exception occurs, catch it here and set an error message.
        // No $pdo->rollBack() here as individual Vendor methods handle their own transactions.
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        error_log("Process Service Package Error for Vendor ID {$vendor_profile_id}, Action '{$action}': " . $e->getMessage());
        
        // Redirect back to the service management page, trying to retain the active tab
        $redirect_url = BASE_URL . 'public/vendor_manage_services.php';
        if ($service_offering_id) {
            $redirect_url .= '?id=' . $service_offering_id;
        }
        header('Location: ' . $redirect_url);
        exit();
    }
} else {
    $_SESSION['error_message'] = "Invalid request method.";
    header('Location: ' . BASE_URL . 'public/vendor_manage_services.php');
    exit();
}