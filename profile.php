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
                                    <li>
                                        <?= htmlspecialchars($service['service_name']) ?>
                                        <?php if (!empty($service['price_range_min']) || !empty($service['price_range_max'])): ?>
                                            (PKR <?= number_format($service['price_range_min'] ?? 0) ?> - <?= number_format($service['price_range_max'] ?? 0) ?>)
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No services currently listed. Go to <a href="edit_profile.php">Edit Profile</a> to add your services.</p>
                    <?php endif; ?>
                </div>
            </div>

            <?php /*
            <div class="profile-section" style="margin-top: 30px;">
                <h2>My Portfolio Items</h2>
                <?php if (!empty($portfolio_items)): ?>
                    <div class="portfolio-grid">
                        <?php foreach ($portfolio_items as $item): ?>
                            <div class="portfolio-item-card">
                                <div class="portfolio-image-wrapper">
                                    <?php if ($item['image_url']): ?>
                                        <img src="<?= BASE_URL . htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['title']) ?>">
                                    <?php else: ?>
                                        <div class="portfolio-placeholder">
                                            <i class="fas fa-image"></i>
                                            <span>No Image</span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="portfolio-item-overlay">
                                        <?php if (!empty($item['video_url'])): ?>
                                            <a href="<?= htmlspecialchars($item['video_url']) ?>" target="_blank" class="btn btn-sm btn-light-overlay"><i class="fas fa-video"></i> Watch Video</a>
                                        <?php endif; ?>
                                        <?php if (!empty($item['client_testimonial'])): ?>
                                            <p class="testimonial-overlay">"<?= htmlspecialchars(substr($item['client_testimonial'], 0, 100)) ?><?= (strlen($item['client_testimonial']) > 100) ? '...' : '' ?>"</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="portfolio-description-content">
                                    <h3><?= htmlspecialchars($item['title']) ?></h3>
                                    <p><?= htmlspecialchars(substr($item['description'] ?? 'No description available.', 0, 100)) ?><?= (strlen($item['description'] ?? '') > 100) ? '...' : '' ?></p>
                                    <div class="portfolio-meta-info">
                                        <?php if ($item['event_type_name']): ?>
                                            <span><i class="fas fa-tag"></i> <?= htmlspecialchars($item['event_type_name']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($item['project_date']): ?>
                                            <span><i class="fas fa-calendar-alt"></i> <?= date('M Y', strtotime($item['project_date'])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>No portfolio items added yet. Go to <a href="edit_profile.php">Edit Profile</a> to add your first item!</p>
                <?php endif; ?>
            </div>
            */ ?>
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
