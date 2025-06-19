<?php
// public/process_portfolio_item.php
// This file is now primarily used for direct DELETE actions of portfolio items.
// Add/Edit is handled by process_portfolio.php

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
$item_id = $_POST['portfolio_item_id'] ?? null; // Changed from 'item_id' for consistency with process_portfolio.php

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

switch ($action) {
    case 'delete':
        try {
            // deletePortfolioItem method in Vendor.class.php is updated
            // to handle fetching and deleting all associated images from the file system
            // and relying on CASCADE DELETE for database records.
            if ($vendor_obj->deletePortfolioItem($item_id, $vendor_profile_id)) {
                $_SESSION['success_message'] = "Portfolio item deleted successfully.";
            } else {
                $_SESSION['error_message'] = "Failed to delete portfolio item.";
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error deleting portfolio item: " . $e->getMessage();
        }
        break;

    default:
        $_SESSION['error_message'] = "Invalid action.";
        break;
}

header('Location: ' . BASE_URL . 'public/vendor_portfolio.php');
exit();
