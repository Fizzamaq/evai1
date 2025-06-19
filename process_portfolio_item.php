<?php
// public/process_portfolio_item.php
session_start();
require_once '../includes/config.php';
require_once '../classes/User.class.php';
require_once '../classes/Vendor.class.php';
require_once '../classes/UploadHandler.class.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    $_SESSION['error_message'] = "Invalid request.";
    header('Location: ' . BASE_URL . 'public/vendor_portfolio.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'];
$item_id = $_POST['item_id'] ?? null;

$vendor_obj = new Vendor($pdo);
$uploader = new UploadHandler();

// Verify vendor profile exists for the current user
$vendor_data = $vendor_obj->getVendorByUserId($user_id);
if (!$vendor_data) {
    $_SESSION['error_message'] = "Vendor profile not found.";
    header('Location: ' . BASE_URL . 'public/vendor_dashboard.php');
    exit();
}
$vendor_profile_id = $vendor_data['id'];

if (!$item_id) {
    $_SESSION['error_message'] = "No portfolio item specified.";
    header('Location: ' . BASE_URL . 'public/vendor_portfolio.php');
    exit();
}

// Fetch the item to verify ownership and get image_url for deletion
$item_to_process = $vendor_obj->getPortfolioItemById($item_id);
if (!$item_to_process || $item_to_process['vendor_id'] != $vendor_profile_id) {
    $_SESSION['error_message'] = "Portfolio item not found or you don't have permission to perform this action.";
    header('Location: ' . BASE_URL . 'public/vendor_portfolio.php');
    exit();
}

switch ($action) {
    case 'delete':
        try {
            // Delete associated image file from server
            if (!empty($item_to_process['image_url'])) {
                $filename_to_delete = basename($item_to_process['image_url']);
                $uploader->deleteFile($filename_to_delete, 'vendors/'); // Assuming 'vendors/' is the subfolder
            }

            if ($vendor_obj->deletePortfolioItem($item_id, $vendor_profile_id)) {
                $_SESSION['success_message'] = "Portfolio item deleted successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to delete portfolio item from database.";
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error deleting portfolio item: " . $e->getMessage();
        }
        break;

    // Add more actions here if needed in the future (e.g., 'feature', 'unfeature')

    default:
        $_SESSION['error_message'] = "Invalid action.";
        break;
}

header('Location: ' . BASE_URL . 'public/vendor_portfolio.php');
exit();
?>