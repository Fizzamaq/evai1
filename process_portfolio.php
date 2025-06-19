<?php
session_start();
require_once '../includes/config.php';
require_once '../classes/User.class.php';
require_once '../classes/Vendor.class.php';
require_once '../classes/UploadHandler.class.php';

$vendor_obj = new Vendor($pdo);
$uploader = new UploadHandler();

// Ensure vendor is logged in and has access
// This method handles redirection if not authenticated or not a complete vendor profile
$vendor_obj->verifyVendorAccess();

// $_SESSION['vendor_id'] should now be set by verifyVendorAccess()
$vendor_id = $_SESSION['vendor_id']; // This is the ID from vendor_profiles table

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Common portfolio data from form
    $portfolio_data = [
        'title' => trim($_POST['portfolio_title']),
        'description' => trim($_POST['portfolio_description'] ?? ''),
        'event_type_id' => !empty($_POST['portfolio_event_type_id']) ? (int)$_POST['portfolio_event_type_id'] : null,
        'video_url' => trim($_POST['portfolio_video_url'] ?? ''),
        'project_date' => !empty($_POST['portfolio_project_date']) ? $_POST['portfolio_project_date'] : null,
        'project_charges' => !empty($_POST['project_charges']) ? (float)$_POST['project_charges'] : null,
        'client_testimonial' => trim($_POST['portfolio_testimonial'] ?? ''),
        'is_featured' => isset($_POST['portfolio_is_featured']) ? 1 : 0
    ];

    $portfolio_item_id = $_POST['portfolio_item_id'] ?? null; // Only set for edit action

    try {
        $pdo->beginTransaction(); // Start a transaction for atomicity

        // Max limit for combined total of existing and new images
        $max_image_limit = 20;

        if ($portfolio_item_id) {
            // --- UPDATE EXISTING PORTFOLIO ITEM ---
            // Verify ownership first
            $existing_item = $vendor_obj->getPortfolioItemById($portfolio_item_id);
            if (!$existing_item || $existing_item['vendor_id'] != $vendor_id) {
                throw new Exception("Portfolio item not found or you don't have permission to edit it.");
            }

            $success = $vendor_obj->updatePortfolioItem($portfolio_item_id, $vendor_id, $portfolio_data);

            if ($success) {
                // Determine images to delete
                $images_to_delete_ids = [];
                $existing_images_to_keep_count = 0;
                if (isset($_POST['existing_images']) && is_array($_POST['existing_images'])) {
                    foreach ($_POST['existing_images'] as $image_id => $action) {
                        if ($action === 'delete') {
                            $images_to_delete_ids[] = (int)$image_id;
                        } else {
                            $existing_images_to_keep_count++;
                        }
                    }
                }

                // Count new uploads
                $new_images_count = 0;
                if (isset($_FILES['portfolio_images_new']) && !empty($_FILES['portfolio_images_new']['name'][0])) {
                    foreach ($_FILES['portfolio_images_new']['name'] as $name) {
                        if (!empty($name)) {
                            $new_images_count++;
                        }
                    }
                }

                // Check total image limit before uploading
                if (($existing_images_to_keep_count + $new_images_count) > $max_image_limit) {
                    throw new Exception("You can only have a maximum of {$max_image_limit} images per portfolio item. Please adjust your selection.");
                }


                // Handle deletion of existing images
                if (!empty($images_to_delete_ids)) {
                    foreach ($images_to_delete_ids as $image_id) {
                        $vendor_obj->deletePortfolioImage($image_id, $portfolio_item_id, $vendor_id);
                    }
                }

                // Handle new image uploads
                if (isset($_FILES['portfolio_images_new']) && !empty($_FILES['portfolio_images_new']['name'][0])) {
                    $uploaded_image_urls = [];
                    foreach ($_FILES['portfolio_images_new']['name'] as $key => $name) {
                        if ($_FILES['portfolio_images_new']['error'][$key] === UPLOAD_ERR_OK) {
                            $file = [
                                'name' => $_FILES['portfolio_images_new']['name'][$key],
                                'type' => $_FILES['portfolio_images_new']['type'][$key],
                                'tmp_name' => $_FILES['portfolio_images_new']['tmp_name'][$key],
                                'error' => $_FILES['portfolio_images_new']['error'][$key],
                                'size' => $_FILES['portfolio_images_new']['size'][$key]
                            ];
                            $uploaded_filename = $uploader->handleUpload($file, 'vendors/portfolio/'); // Specific subfolder
                            $uploaded_image_urls[] = 'assets/uploads/vendors/portfolio/' . $uploaded_filename;
                        } else if ($_FILES['portfolio_images_new']['error'][$key] !== UPLOAD_ERR_NO_FILE) {
                            throw new Exception("File upload error for image " . htmlspecialchars($name) . ": " . $_FILES['portfolio_images_new']['error'][$key]);
                        }
                    }
                    if (!empty($uploaded_image_urls)) {
                        $vendor_obj->addPortfolioImages($portfolio_item_id, $uploaded_image_urls);
                    }
                }

                $pdo->commit();
                $_SESSION['success_message'] = 'Portfolio item updated successfully!';
                header('Location: ' . BASE_URL . 'public/vendor_portfolio.php');
                exit();
            } else {
                throw new Exception("Failed to update portfolio item in database.");
            }

        } else {
            // --- ADD NEW PORTFOLIO ITEM ---
            // Count new uploads for limit check
            $new_images_count = 0;
            if (isset($_FILES['portfolio_images']) && !empty($_FILES['portfolio_images']['name'][0])) {
                foreach ($_FILES['portfolio_images']['name'] as $name) {
                    if (!empty($name)) {
                        $new_images_count++;
                    }
                }
            }

            if ($new_images_count > $max_image_limit) {
                throw new Exception("You can only upload a maximum of {$max_image_limit} images per portfolio item. Please adjust your selection.");
            }

            $new_portfolio_item_id = $vendor_obj->addPortfolioItem($vendor_id, $portfolio_data);

            if ($new_portfolio_item_id) {
                // Handle new image uploads
                if (isset($_FILES['portfolio_images']) && !empty($_FILES['portfolio_images']['name'][0])) {
                    $uploaded_image_urls = [];
                    foreach ($_FILES['portfolio_images']['name'] as $key => $name) {
                        if ($_FILES['portfolio_images']['error'][$key] === UPLOAD_ERR_OK) {
                            $file = [
                                'name' => $_FILES['portfolio_images']['name'][$key],
                                'type' => $_FILES['portfolio_images']['type'][$key],
                                'tmp_name' => $_FILES['portfolio_images']['tmp_name'][$key],
                                'error' => $_FILES['portfolio_images']['error'][$key],
                                'size' => $_FILES['portfolio_images']['size'][$key]
                            ];
                            $uploaded_filename = $uploader->handleUpload($file, 'vendors/portfolio/'); // Specific subfolder
                            $uploaded_image_urls[] = 'assets/uploads/vendors/portfolio/' . $uploaded_filename;
                        } else if ($_FILES['portfolio_images']['error'][$key] !== UPLOAD_ERR_NO_FILE) {
                            throw new Exception("File upload error for image " . htmlspecialchars($name) . ": " . $_FILES['portfolio_images']['error'][$key]);
                        }
                    }
                    if (!empty($uploaded_image_urls)) {
                        $vendor_obj->addPortfolioImages($new_portfolio_item_id, $uploaded_image_urls);
                    }
                }

                $pdo->commit();
                $_SESSION['success_message'] = 'Portfolio item added successfully!';
                header('Location: ' . BASE_URL . 'public/vendor_portfolio.php');
                exit();
            } else {
                throw new Exception("Failed to add portfolio item to database.");
            }
        }
    } catch (Exception $e) {
        $pdo->rollBack(); // Rollback transaction on error
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        // Redirect back to the form with error, preserving original data if possible
        if ($portfolio_item_id) {
            header('Location: ' . BASE_URL . 'public/edit_portfolio_item.php?id=' . $portfolio_item_id);
        } else {
            header('Location: ' . BASE_URL . 'public/add_portfolio_item.php');
        }
        exit();
    }
} else {
    $_SESSION['error_message'] = "Invalid request method.";
    header('Location: ' . BASE_URL . 'public/vendor_portfolio.php');
    exit();
}