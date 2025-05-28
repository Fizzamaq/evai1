<?php
require_once '../includes/config.php';
include 'header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../classes/User.class.php';
require_once '../classes/Vendor.class.php'; // Include Vendor class
$user = new User($pdo);
$profile = $user->getProfile($_SESSION['user_id']);

$is_vendor = false;
$vendor_data = null;
$vendor_services_offered = [];

// Check if the logged-in user is a vendor
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 2) { // Assuming 2 is vendor type
    $is_vendor = true;
    $vendor = new Vendor($pdo);
    $vendor_data = $vendor->getVendorByUserId($_SESSION['user_id']);

    if ($vendor_data) {
        // Fetch the services offered by this vendor
        $vendor_services_offered_raw = $vendor->getVendorServices($vendor_data['id']);
        
        // Group services by category for display
        foreach ($vendor_services_offered_raw as $service) {
            $vendor_services_offered[$service['category_name']][] = $service;
        }
    }
}
?>
<div class="profile-container">
    <h1>Your Profile</h1>
    
    <div class="profile-section">
        <div class="profile-pic">
            <?php if (!empty($profile['profile_image'])): ?>
                <img src="<?= ASSETS_PATH ?>uploads/users/<?= htmlspecialchars($profile['profile_image']) ?>" alt="Profile Picture">
            <?php else: ?>
                <div class="initials" style="background-image: url('<?= ASSETS_PATH ?>images/default-avatar.jpg'); background-size: cover; background-position: center;">
                    <?= substr($profile['first_name'], 0, 1) . substr($profile['last_name'], 0, 1) ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="profile-info">
            <h2><?= htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']) ?></h2>
            <p><strong>Email:</strong> <?= htmlspecialchars($profile['email']) ?></p>
            <p><strong>Member Since:</strong> <?= date('F Y', strtotime($profile['created_at'])) ?></p>
            
            <a href="edit_profile.php" class="btn">Edit Profile</a>
        </div>
    </div>

    <?php if ($is_vendor && $vendor_data): // Display vendor specific info ?>
        <div class="profile-section" style="margin-top: 30px;">
            <div class="profile-info">
                <h2>Business Information</h2>
                <p><strong>Business Name:</strong> <?= htmlspecialchars($vendor_data['business_name']) ?></p>
                <?php if (!empty($vendor_data['website'])): ?>
                    <p><strong>Website:</strong> <a href="<?= htmlspecialchars($vendor_data['website']) ?>" target="_blank"><?= htmlspecialchars($vendor_data['website']) ?></a></p>
                <?php endif; ?>
                <p><strong>Address:</strong> <?= htmlspecialchars($vendor_data['business_address']) ?>, <?= htmlspecialchars($vendor_data['business_city']) ?>, <?= htmlspecialchars($vendor_data['business_state']) ?>, <?= htmlspecialchars($vendor_data['business_postal_code']) ?>, <?= htmlspecialchars($vendor_data['business_country']) ?></p>
                <p><strong>Years of Experience:</strong> <?= htmlspecialchars($vendor_data['experience_years'] ?? 'N/A') ?></p>
                <p><strong>Rating:</strong> <?= htmlspecialchars(number_format($vendor_data['rating'], 1) ?? 'N/A') ?> (<?= htmlspecialchars($vendor_data['total_reviews'] ?? 0) ?> reviews)</p>
            </div>
        </div>

        <div class="profile-section" style="margin-top: 30px;">
            <div class="profile-info">
                <h2>Services Offered</h2>
                <?php if (!empty($vendor_services_offered)): ?>
                    <?php foreach ($vendor_services_offered as $category_name => $services): ?>
                        <h4><?= htmlspecialchars($category_name) ?></h4>
                        <ul>
                            <?php foreach ($services as $service): ?>
                                <li><?= htmlspecialchars($service['service_name']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No services currently listed. Go to <a href="edit_profile.php">Edit Profile</a> to add your services.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php include 'footer.php'; ?>
