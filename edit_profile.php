<?php
require_once '../includes/config.php';
require_once '../classes/User.class.php';     // Include User class
require_once '../classes/Vendor.class.php';   // Include Vendor class for vendor profile
require_once '../classes/UploadHandler.class.php'; // Include UploadHandler for portfolio image uploads
include 'header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "public/login.php");
    exit();
}

$user = new User($pdo); // Pass PDO
$profile = $user->getProfile($_SESSION['user_id']); // getProfile is correct

$is_vendor = false;
$vendor_data = null;
$vendor_categories = [];
$vendor_services_by_category = [];
$selected_vendor_services = []; // Array to hold service IDs the vendor already offers

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 2) { // Assuming 2 is vendor type
    $is_vendor = true;
    $vendor = new Vendor($pdo);
    $vendor_data = $vendor->getVendorByUserId($_SESSION['user_id']);

    // Fetch all vendor categories
    $vendor_categories = dbFetchAll("SELECT * FROM vendor_categories ORDER BY category_name ASC");

    // Fetch all vendor services, grouped by category
    $all_vendor_services = dbFetchAll("
        SELECT vs.id, vs.service_name, vc.category_name, vs.category_id
        FROM vendor_services vs
        JOIN vendor_categories vc ON vs.category_id = vc.id
        WHERE vs.is_active = TRUE
        ORDER BY vc.category_name, vs.service_name
    ");

    foreach ($all_vendor_services as $service) {
        $vendor_services_by_category[$service['category_name']][] = $service;
    }

    // Fetch services already offered by this vendor
    if ($vendor_data) {
        $current_offerings = $vendor->getVendorServices($vendor_data['id']);
        $selected_vendor_services = array_column($current_offerings, 'service_id');
    }
}

// Get event types for portfolio dropdown (needed for portfolio form)
// This is now only needed in vendor_portfolio.php, but kept for consistency if other elements need it.
try {
    $event_types = dbFetchAll("SELECT id, type_name FROM event_types WHERE is_active = TRUE"); 
} catch (PDOException $e) {
    $event_types = [];
    error_log("Get event types error for portfolio form: " . $e->getMessage());
}


$error = $_SESSION['profile_error'] ?? null;
$success = $_SESSION['profile_success'] ?? null;
unset($_SESSION['profile_error'], $_SESSION['profile_success']);
?>
<link rel="stylesheet" href="<?= ASSETS_PATH ?>css/edit_profile.css">

<div class="profile-container">
    <h1>Edit Profile</h1>

    <?php if ($error): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (isset($success)): /* Added check for success variable existence */ ?>
        <div class="alert success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form action="process_profile.php" method="post" enctype="multipart/form-data" id="editProfileMultiStepForm">
        <div class="multi-step-form-container">
            <div class="step-navigation-header">
                <h1>Complete Your Profile</h1>
                <div class="step-indicators">
                    <div class="step-indicator" data-step="0">
                        <span class="step-indicator-number">1</span> Personal Info
                    </div>
                    <?php if ($is_vendor): ?>
                    <div class="step-indicator" data-step="1">
                        <span class="step-indicator-number">2</span> Business Info
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="step-personal-info" class="step-content" data-step="0">
                <h2>Personal Information</h2>
                <div class="form-group">
                    <label>Profile Picture</label>
                    <input type="file" name="profile_image" accept="image/*">
                    <?php if (!empty($profile['profile_image'])): ?>
                        <p style="margin-top: 10px;">Current: <img src="<?= ASSETS_PATH ?>uploads/users/<?= htmlspecialchars($profile['profile_image']) ?>" alt="Profile Picture" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover;"></p>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" value="<?= htmlspecialchars($profile['first_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" value="<?= htmlspecialchars($profile['last_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Address</label>
                    <input type="text" name="address" value="<?= htmlspecialchars($profile['address'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>City</label>
                    <input type="text" name="city" value="<?= htmlspecialchars($profile['city'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>State</label>
                    <input type="text" name="state" value="<?= htmlspecialchars($profile['state'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label>Country</label>
                    <input type="text" name="country" value="<?= htmlspecialchars($profile['country'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Postal Code</label>
                    <input type="text" name="postal_code" value="<?= htmlspecialchars($profile['postal_code'] ?? '') ?>">
                </div>

                <div class="form-actions-step">
                    <span></span> <button type="button" class="btn btn-primary btn-next-step">Next</button>
                </div>
            </div>

            <?php if ($is_vendor): ?>
            <div id="step-business-info" class="step-content" data-step="1">
                <h2>Business Information</h2>
                <p>Please complete your business details to activate your vendor profile.</p>
                <div class="form-group">
                    <label>Business Name <span class="required">*</span></label>
                    <input type="text" name="business_name" value="<?= htmlspecialchars($vendor_data['business_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Business License (Optional)</label>
                    <input type="text" name="business_license" value="<?= htmlspecialchars($vendor_data['business_license'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Tax ID (Optional)</label>
                    <input type="text" name="tax_id" value="<?= htmlspecialchars($vendor_data['tax_id'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Website (Optional)</label>
                    <input type="url" name="website" value="<?= htmlspecialchars($vendor_data['website'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Business Address <span class="required">*</span></label>
                    <input type="text" name="business_address" value="<?= htmlspecialchars($vendor_data['business_address'] ?? '') ?>" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Business City <span class="required">*</span></label>
                        <input type="text" name="business_city" value="<?= htmlspecialchars($vendor_data['business_city'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Business State <span class="required">*</span></label>
                        <input type="text" name="business_state" value="<?= htmlspecialchars($vendor_data['business_state'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Business Country <span class="required">*</span></label>
                        <input type="text" name="business_country" value="<?= htmlspecialchars($vendor_data['business_country'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Business Postal Code <span class="required">*</span></label>
                        <input type="text" name="business_postal_code" value="<?= htmlspecialchars($vendor_data['business_postal_code'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Service Radius (miles) (Optional)</label>
                        <input type="number" name="service_radius" value="<?= htmlspecialchars($vendor_data['service_radius'] ?? '') ?>" min="0">
                    </div>
                    <div class="form-group">
                        <label>Years of Experience (Optional)</label>
                        <input type="number" name="experience_years" value="<?= htmlspecialchars($vendor_data['experience_years'] ?? '') ?>" min="0">
                    </div>
                </div>
                
                <h3 style="margin-top: 40px;">Services Offered</h3>
                <p>Select the types of services you provide:</p>
                <div class="service-categories-container">
                    <?php foreach ($vendor_categories as $category): ?>
                        <div class="service-category-group">
                            <h4><?= htmlspecialchars($category['category_name']) ?></h4>
                            <div class="services-checkbox-grid">
                                <?php if (isset($vendor_services_by_category[$category['category_name']])): ?>
                                    <?php foreach ($vendor_services_by_category[$category['category_name']] as $service): ?>
                                        <div class="form-group-checkbox">
                                            <input type="checkbox" 
                                                   id="service_<?= $service['id'] ?>" 
                                                   name="services_offered[]" 
                                                   value="<?= $service['id'] ?>"
                                                   <?= in_array($service['id'], $selected_vendor_services) ? 'checked' : '' ?>>
                                            <label for="service_<?= $service['id'] ?>"><?= htmlspecialchars($service['service_name']) ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-subtle">No services defined for this category yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="form-actions-step">
                    <button type="button" class="btn btn-secondary btn-prev-step">Previous</button>
                    <button type="submit" name="save_profile_changes" class="btn btn-primary">Save All Changes</button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </form>

    <?php /* The 'Add New Portfolio Item' section is removed from here */ ?>
</div>
<?php include 'footer.php'; ?>

<script src="<?= ASSETS_PATH ?>js/edit_profile.js"></script>
