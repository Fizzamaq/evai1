<?php
session_start();
require_once '../includes/config.php';
include 'header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "public/login.php");
    exit();
}

require_once '../classes/User.class.php';
require_once '../classes/Vendor.class.php'; // Include Vendor class
$user = new User($pdo);
$profile = $user->getProfile($_SESSION['user_id']);

$is_vendor = false;
$vendor_data = null;
$vendor_services_offered = [];
// Removed: $portfolio_items = []; // No longer fetching for display on profile.php

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

        // Removed: Fetch vendor's portfolio items for display on their profile page
        // $portfolio_items = $vendor->getVendorPortfolio($vendor_data['id']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Profile</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <?php /* if ($is_vendor): ?>
    <link rel="stylesheet" href="../assets/css/vendor_profile.css">
    <?php endif; */ ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="profile-container">
        <h1>Your Profile</h1>

        <?php if (isset($_SESSION['profile_success'])): ?>
            <div class="alert success" id="profile-success-alert"><?= htmlspecialchars($_SESSION['profile_success']) ?></div>
            <?php unset($_SESSION['profile_success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['profile_error'])): ?>
            <div class="alert error" id="profile-error-alert"><?= htmlspecialchars($_SESSION['profile_error']) ?></div>
            <?php unset($_SESSION['profile_error']); ?>
        <?php endif; ?>

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
                <p><strong>Member Since:</b> <?= date('F Y', strtotime($profile['created_at'])) ?>
</p>

                <div class="profile-actions-row">
                    <a href="edit_profile.php" class="btn">Edit Profile</a>
                    <?php if (isset($is_vendor) && $is_vendor && isset($vendor_data) && $vendor_data): // Only show for vendors with a complete profile?>
                        <a href="<?= BASE_URL ?>public/vendor_manage_services.php" class="btn btn-secondary">Manage Services</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (isset($is_vendor) && $is_vendor && isset($vendor_data) && $vendor_data): // Display vendor specific info?>
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
                    <h2>Services Offered Overview</h2>
                    <?php if (!empty($vendor_services_offered)): ?>
                        <p>You offer services in the following categories:</p>
                        <ul>
                            <?php foreach ($vendor_services_offered as $category_name => $services): ?>
                                <li>
                                    <strong><?= htmlspecialchars($category_name) ?>:</strong>
                                    <?php
                                        $service_names = array_column($services, 'service_name');
                                        echo htmlspecialchars(implode(', ', $service_names));
                                    ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p>For detailed pricing and offers, please visit the <a href="<?= BASE_URL ?>public/vendor_manage_services.php">Manage Services</a> page.</p>
                    <?php else: ?>
                        <p>No services currently listed. Go to <a href="edit_profile.php">Edit Profile</a> to add your services.</p>
                    <?php endif; ?>
                </div>
            </div>

        <?php endif; ?>
    </div>
<?php include 'footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const successAlert = document.getElementById('profile-success-alert');
        const errorAlert = document.getElementById('profile-error-alert');

        if (successAlert) {
            setTimeout(function() {
                successAlert.style.display = 'none';
            }, 5000); // 5000 milliseconds = 5 seconds
        }

        if (errorAlert) {
            setTimeout(function() {
                errorAlert.style.display = 'none';
            }, 5000); // 5000 milliseconds = 5 seconds
        }
    });
</script>
