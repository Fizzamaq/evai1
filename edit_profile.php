<?php
require_once '../includes/config.php';
require_once '../classes/User.class.php'; // Include User class
require_once '../classes/Vendor.class.php'; // Include Vendor class for vendor profile
include 'header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "public/login.php");
    exit();
}

$user = new User($pdo); // Pass PDO
$profile = $user->getProfile($_SESSION['user_id']); // getProfile is correct

$is_vendor = false;
$vendor_data = null;
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 2) { // Assuming 2 is vendor type
    $is_vendor = true;
    $vendor = new Vendor($pdo);
    $vendor_data = $vendor->getVendorByUserId($_SESSION['user_id']);
}

$error = $_SESSION['profile_error'] ?? null;
$success = $_SESSION['profile_success'] ?? null;
unset($_SESSION['profile_error'], $_SESSION['profile_success']);
?>
<div class="profile-container">
    <h1>Edit Profile</h1>

    <?php if ($error): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form action="process_profile.php" method="post" enctype="multipart/form-data">
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

        <?php if ($is_vendor): ?>
            <h2 style="margin-top: 40px;">Business Information</h2>
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
            <div class="form-row">
                <div class="form-group">
                    <label>Minimum Budget (Optional)</label>
                    <input type="number" name="min_budget" value="<?= htmlspecialchars($vendor_data['min_budget'] ?? '') ?>" step="0.01" min="0">
                </div>
                <div class="form-group">
                    <label>Maximum Budget (Optional)</label>
                    <input type="number" name="max_budget" value="<?= htmlspecialchars($vendor_data['max_budget'] ?? '') ?>" step="0.01" min="0">
                </div>
            </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary">Save Changes</button>
    </form>
</div>
<?php include 'footer.php'; ?>
