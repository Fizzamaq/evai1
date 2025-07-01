<?php
session_start();
require_once '../includes/config.php';
require_once '../classes/User.class.php';
require_once '../classes/Vendor.class.php';
require_once '../classes/UploadHandler.class.php'; // For image management helper
require_once '../includes/auth.php'; // For CSRF token generation

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

$user = new User($pdo);
$vendor_obj = new Vendor($pdo);
$uploader = new UploadHandler();

// Verify vendor access
$vendor_obj->verifyVendorAccess();

// $_SESSION['vendor_id'] is set by verifyVendorAccess()
$vendor_profile_id = $_SESSION['vendor_id'];

// Get all service offerings for this vendor (these are the *tabs*)
$all_vendor_offerings = $vendor_obj->getVendorServices($vendor_profile_id);

// If no offerings, redirect to edit_profile to select some services first
if (empty($all_vendor_offerings)) {
    $_SESSION['error_message'] = "You haven't selected any services yet. Please go to your profile to select services first.";
    header('Location: ' . BASE_URL . 'public/edit_profile.php');
    exit();
}

// Prepare data for forms within tabs
$services_data_for_tabs = [];
foreach ($all_vendor_offerings as $offering) {
    $offering_id = $offering['id'];
    
    // Fetch the full service offering details, including packages and their images
    $full_offering_details = $vendor_obj->getServiceOfferingById($offering_id, $vendor_profile_id);
    
    if ($full_offering_details) {
        $services_data_for_tabs[$offering_id] = $full_offering_details;
    }
}

// Determine active tab (default to the first one)
$active_offering_id = $_GET['id'] ?? null;
if (!array_key_exists($active_offering_id, $services_data_for_tabs)) {
    $active_offering_id = reset($all_vendor_offerings)['id']; // Set to the first offering's ID
}

$error_message = $_SESSION['error_message'] ?? null;
$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['error_message'], $_SESSION['success_message']);

$csrf_token = generateCSRFToken();

include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage My Services - EventCraftAI</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/vendor.css">
    <link rel="stylesheet" href="../assets/css/vendor_services.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
</head>
<body>
    <div class="manage-services-container">
        <div class="manage-services-header">
            <h1>Manage My Services & Packages</h1>
            <p>Customize pricing, add details, and showcase your specific service packages with images.</p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <div class="service-tabs">
            <?php foreach ($all_vendor_offerings as $tab_offering): ?>
                <button class="tab-button <?= ($tab_offering['id'] == $active_offering_id) ? 'active' : '' ?>"
                        data-offering-id="<?= htmlspecialchars($tab_offering['id']) ?>">
                    <?= htmlspecialchars($tab_offering['service_name']) ?>
                    <?php if ($tab_offering['package_count'] > 0): ?>
                        (<?= $tab_offering['package_count'] ?>)
                    <?php endif; ?>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="tab-contents">
            <?php foreach ($services_data_for_tabs as $offering_id => $offering): ?>
                <div id="tab-content-<?= htmlspecialchars($offering_id) ?>" 
                     class="tab-content <?= ($offering_id == $active_offering_id) ? 'active' : '' ?>">
                    
                    <?php /* Removed: Overall Service Details Section */ ?>

                    <div class="packages-section">
                        <h3>Your Packages for <?= htmlspecialchars($offering['service_name']) ?></h3>
                        <div class="add-package-button-container">
                            <button type="button" class="btn btn-secondary add-package-btn" data-offering-id="<?= htmlspecialchars($offering['id']) ?>">
                                <i class="fas fa-plus"></i> Add New Package
                            </button>
                        </div>

                        <div class="packages-grid">
                            <?php if (empty($offering['packages'])): ?>
                                <div class="empty-state">
                                    <p>No packages defined for this service yet. Click "Add New Package" to create one!</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($offering['packages'] as $package): ?>
                                    <div class="package-card">
                                        <div class="package-image-wrapper lightbox-trigger" data-images="<?= htmlspecialchars(json_encode(array_column($package['images'], 'image_url'))) ?>">
                                            <?php if (!empty($package['images'])): ?>
                                                <img src="<?= BASE_URL . htmlspecialchars($package['images'][0]['image_url']) ?>" alt="<?= htmlspecialchars($package['package_name']) ?> Image">
                                                <?php if (count($package['images']) > 1): ?>
                                                    <span class="image-count-overlay">+<?= count($package['images']) - 1 ?></span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <div class="package-placeholder"><i class="fas fa-image"></i></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="package-details-content">
                                            <h4><?= htmlspecialchars($package['package_name']) ?></h4>
                                            <p class="price-display">
                                                PKR <?= number_format($package['price_min'] ?? 0, 0) ?> - 
                                                PKR <?= number_format($package['price_max'] ?? 0, 0) ?>
                                            </p>
                                            <p class="package-description">
                                                <?= nl2br(htmlspecialchars(substr($package['package_description'] ?? 'No description provided.', 0, 100))) ?><?= (strlen($package['package_description'] ?? '') > 100) ? '...' : '' ?>
                                            </p>
                                        </div>
                                        <div class="package-actions">
                                            <button type="button" class="btn btn-sm btn-info edit-package-btn"
                                                    data-package-id="<?= htmlspecialchars($package['id']) ?>"
                                                    data-offering-id="<?= htmlspecialchars($offering['id']) ?>">Edit</button>
                                            <form method="POST" action="<?= BASE_URL ?>public/process_service_package.php" onsubmit="return confirm('Are you sure you want to delete this package? This will also remove its images.');">
                                                <input type="hidden" name="action" value="delete_package">
                                                <input type="hidden" name="package_id" value="<?= htmlspecialchars($package['id']) ?>">
                                                <input type="hidden" name="service_offering_id" value="<?= htmlspecialchars($offering['id']) ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div id="package-form-modal-<?= htmlspecialchars($offering['id']) ?>" class="package-form-container" style="display: none;">
                        <h3><span id="package-form-title-<?= htmlspecialchars($offering['id']) ?>">Add New Package</span></h3>
                        <form id="package-form-<?= htmlspecialchars($offering['id']) ?>" action="<?= BASE_URL ?>public/process_service_package.php" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <input type="hidden" name="service_offering_id" value="<?= htmlspecialchars($offering['id']) ?>">
                            <input type="hidden" name="action" id="package-form-action-<?= htmlspecialchars($offering['id']) ?>" value="add_package">
                            <input type="hidden" name="package_id" id="package-form-package-id-<?= htmlspecialchars($offering['id']) ?>" value="">

                            <div class="form-group">
                                <label for="package_name_<?= htmlspecialchars($offering['id']) ?>">Package Name <span class="required">*</span></label>
                                <input type="text" id="package_name_<?= htmlspecialchars($offering['id']) ?>" name="package_name" required>
                            </div>
                            <div class="form-group">
                                <label for="package_description_<?= htmlspecialchars($offering['id']) ?>">Package Description</label>
                                <textarea id="package_description_<?= htmlspecialchars($offering['id']) ?>" name="package_description" rows="4"></textarea>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="package_price_min_<?= htmlspecialchars($offering['id']) ?>">Min Price (PKR)</label>
                                    <input type="number" id="package_price_min_<?= htmlspecialchars($offering['id']) ?>" name="price_min" step="0.01" min="0">
                                </div>
                                <div class="form-group">
                                    <label for="package_price_max_<?= htmlspecialchars($offering['id']) ?>">Max Price (PKR)</label>
                                    <input type="number" id="package_price_max_<?= htmlspecialchars($offering['id']) ?>" name="price_max" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="package_display_order_<?= htmlspecialchars($offering['id']) ?>">Display Order (Lower is first)</label>
                                <input type="number" id="package_display_order_<?= htmlspecialchars($offering['id']) ?>" name="display_order" min="0" value="0">
                            </div>
                            <div class="form-group">
                                <label for="package_is_active_<?= htmlspecialchars($offering['id']) ?>">
                                    <input type="checkbox" id="package_is_active_<?= htmlspecialchars($offering['id']) ?>" name="is_active" value="1" checked> Is Active
                                </label>
                            </div>

                            <div class="form-group current-images-section-<?= htmlspecialchars($offering['id']) ?>" style="display: none;">
                                <label>Current Package Images</label>
                                <div class="current-images-grid" id="current-package-images-grid-<?= htmlspecialchars($offering['id']) ?>">
                                    </div>
                                <small class="text-muted">Click 'x' to mark an image for deletion. It will be removed when you save.</small>
                            </div>

                            <div class="form-group">
                                <label for="package_new_images_<?= htmlspecialchars($offering['id']) ?>">Add New Package Images (Up to 10 total)</label>
                                <input type="file" id="package_new_images_<?= htmlspecialchars($offering['id']) ?>" name="new_images[]" accept="image/*" multiple>
                                <small class="text-muted">You can select multiple images. Max 10 images including existing ones.</small>
                                <div class="image-preview-grid" id="package-new-image-preview-grid-<?= htmlspecialchars($offering['id']) ?>"></div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Save Package</button>
                                <button type="button" class="btn btn-secondary cancel-package-form-btn" data-offering-id="<?= htmlspecialchars($offering['id']) ?>">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="lightbox-overlay" id="lightboxOverlay">
        <div class="lightbox-content">
            <img src="" alt="Enlarged Image" id="lightboxImage">
        </div>
        <span class="lightbox-close" id="lightboxClose">&times;</span>
    </div>

    <?php include 'footer.php'; ?>

    <script src="<?= ASSETS_PATH ?>js/vendor_manage_services.js"></script>
    <script>
        // Pass PHP data to JavaScript
        const ALL_SERVICE_OFFERINGS_DATA = <?= json_encode($services_data_for_tabs) ?>;
        const BASE_URL = '<?= BASE_URL ?>';
    </script>
</body>
</html>