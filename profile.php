<?php
session_start();
require_once '../includes/config.php';
require_once '../classes/User.class.php';
require_once '../classes/Vendor.class.php';
require_once '../classes/Review.class.php';

include 'header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "public/login.php");
    exit();
}

$user_obj = new User($pdo);
$profile_data = $user_obj->getProfile($_SESSION['user_id']);

$is_vendor = false;
$vendor_data = null;
$vendor_services_by_category = [];
$portfolio_items = [];
$vendor_reviews = [];

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 2) {
    $is_vendor = true;
    $vendor_obj = new Vendor($pdo);
    $review_obj = new Review($pdo);
    $vendor_data = $vendor_obj->getVendorByUserId($_SESSION['user_id']);

    if ($vendor_data) {
        $vendor_services_offered_raw = $vendor_obj->getVendorServices($vendor_data['id']);
        foreach ($vendor_services_offered_raw as $service) {
            $full_offering = $vendor_obj->getServiceOfferingById($service['id'], $vendor_data['id']);
            if ($full_offering) {
                $vendor_services_by_category[$full_offering['category_name']][] = $full_offering;
            }
        }
        $portfolio_items = $vendor_obj->getVendorPortfolio($vendor_data['id']);
        $vendor_reviews = $review_obj->getReviewsForEntity($vendor_data['user_id'], 'vendor');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Profile - EventCraftAI</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/profile_redesign.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="profile-container">
        <div class="profile-header">
            <div class="profile-avatar">
                <?php if (!empty($profile_data['profile_image'])): ?>
                    <img src="<?= BASE_URL ?>assets/uploads/users/<?= htmlspecialchars($profile_data['profile_image']) ?>" alt="Profile Picture">
                <?php else: ?>
                    <div class="initials">
                        <?= substr($profile_data['first_name'], 0, 1) . substr($profile_data['last_name'], 0, 1) ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="profile-info-main">
                <h1><?= htmlspecialchars($profile_data['first_name'] . ' ' . $profile_data['last_name']) ?></h1>
                <?php if ($is_vendor && $vendor_data): ?>
                    <p class="tagline"><?= htmlspecialchars($vendor_data['business_name']) ?></p>
                    <div class="rating-display">
                        <?php
                        $rating = round($vendor_data['rating'] * 2) / 2;
                        for ($i = 1; $i <= 5; $i++):
                            if ($rating >= $i) { echo '<i class="fas fa-star filled"></i>'; }
                            else { echo '<i class="far fa-star"></i>'; }
                        endfor;
                        ?>
                        <span><?= number_format($vendor_data['rating'], 1) ?> (<?= $vendor_data['total_reviews'] ?> reviews)</span>
                    </div>
                <?php else: ?>
                    <p class="tagline">Event Planner</p>
                <?php endif; ?>
                <div class="action-buttons">
                    <a href="edit_profile.php" class="btn btn-primary">Edit Profile</a>
                    <?php if ($is_vendor && $vendor_data): ?>
                        <a href="vendor_manage_services.php" class="btn btn-secondary">Manage Services</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="profile-content">
            <div class="tab-navigation">
                <button class="tab-button active" data-tab="details">Details</button>
                <?php if ($is_vendor && $vendor_data): ?>
                    <button class="tab-button" data-tab="services">Services</button>
                    <button class="tab-button" data-tab="portfolio">Portfolio</button>
                    <button class="tab-button" data-tab="reviews">Reviews</button>
                <?php endif; ?>
            </div>

            <div id="tab-details" class="tab-content active">
                <div class="content-card">
                    <h3>Personal Information</h3>
                    <p><strong>Email:</strong> <?= htmlspecialchars($profile_data['email']) ?></p>
                    <p><strong>Member Since:</strong> <?= date('F Y', strtotime($profile_data['created_at'])) ?></p>
                    <?php if ($profile_data['address']): ?>
                        <p><strong>Address:</strong> <?= htmlspecialchars($profile_data['address'] . ', ' . $profile_data['city'] . ', ' . $profile_data['country']) ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($is_vendor && $vendor_data): ?>
                    <div class="content-card">
                        <h3>Business Information</h3>
                        <p><strong>Business Name:</strong> <?= htmlspecialchars($vendor_data['business_name']) ?></p>
                        <p><strong>Website:</strong> <a href="<?= htmlspecialchars($vendor_data['website']) ?>" target="_blank"><?= htmlspecialchars($vendor_data['website']) ?></a></p>
                        <p><strong>Business Address:</strong> <?= htmlspecialchars($vendor_data['business_address'] . ', ' . $vendor_data['business_city']) ?></p>
                        <p><strong>Years of Experience:</strong> <?= htmlspecialchars($vendor_data['experience_years'] ?? 'N/A') ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($is_vendor && $vendor_data): ?>
                <div id="tab-services" class="tab-content">
                    <div class="content-card">
                        <h3>Services Offered</h3>
                        <?php if (!empty($vendor_services_by_category)): ?>
                            <div class="services-list">
                                <?php foreach ($vendor_services_by_category as $category_name => $services): ?>
                                    <h4><?= htmlspecialchars($category_name) ?></h4>
                                    <ul>
                                        <?php foreach ($services as $service): ?>
                                            <li>
                                                <strong><?= htmlspecialchars($service['service_name']) ?></strong>
                                                <?php if ($service['price_range_min'] !== null || $service['price_range_max'] !== null): ?>
                                                    (PKR <?= number_format($service['price_range_min'] ?? 0, 0) ?> - <?= number_format($service['price_range_max'] ?? 0, 0) ?>)
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p>No services currently listed.</p>
                        <?php endif; ?>
                        <div style="margin-top: var(--spacing-md);">
                           <a href="vendor_manage_services.php" class="btn btn-primary">Manage Services & Packages</a>
                        </div>
                    </div>
                </div>

                <div id="tab-portfolio" class="tab-content">
                    <div class="content-card">
                        <h3>Portfolio Items</h3>
                        <?php if (!empty($portfolio_items)): ?>
                            <div class="portfolio-grid">
                                <?php foreach ($portfolio_items as $item): ?>
                                    <a href="view_portfolio_item.php?id=<?= $item['id'] ?>" class="portfolio-card-link">
                                        <div class="portfolio-item-card">
                                            <div class="portfolio-image-wrapper">
                                                <?php if (!empty($item['main_image_url'])): ?>
                                                    <img src="<?= BASE_URL . htmlspecialchars($item['main_image_url']) ?>" alt="<?= htmlspecialchars($item['title']) ?>">
                                                <?php else: ?>
                                                    <div class="portfolio-placeholder"><i class="fas fa-image"></i></div>
                                                <?php endif; ?>
                                            </div>
                                            <h4><?= htmlspecialchars($item['title']) ?></h4>
                                            <p><?= htmlspecialchars(substr($item['description'] ?? 'No description available.', 0, 70)) ?>...</p>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p>No portfolio items added yet.</p>
                            <div style="margin-top: var(--spacing-md);">
                                <a href="add_portfolio_item.php" class="btn btn-primary">Add First Item</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div id="tab-reviews" class="tab-content">
                    <div class="content-card">
                        <h3>Reviews (<?= count($vendor_reviews) ?>)</h3>
                        <?php if (!empty($vendor_reviews)): ?>
                            <div class="reviews-list">
                                <?php foreach ($vendor_reviews as $review_item): ?>
                                    <div class="review-card">
                                        <div class="reviewer-info">
                                            <div class="reviewer-avatar">
                                                 <img src="<?= BASE_URL ?>assets/uploads/users/<?= htmlspecialchars($review_item['profile_image'] ?: 'default-avatar.jpg') ?>" alt="Reviewer Avatar">
                                            </div>
                                            <div class="reviewer-details">
                                                <div class="reviewer-name"><?= htmlspecialchars($review_item['first_name'] ?? 'Anonymous') ?></div>
                                                <div class="review-rating-stars">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?= ($review_item['rating'] >= $i) ? 'filled' : '' ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                            <div class="review-date"><?= date('M j, Y', strtotime($review_item['created_at'])) ?></div>
                                        </div>
                                        <h4><?= htmlspecialchars($review_item['review_title']) ?></h4>
                                        <p><?= nl2br(htmlspecialchars($review_item['review_content'])) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p>No reviews for this vendor yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tab switching logic
        const tabs = document.querySelectorAll('.tab-button');
        const contents = document.querySelectorAll('.tab-content');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const target = tab.dataset.tab;
                
                contents.forEach(content => {
                    content.classList.remove('active');
                    if (content.id === `tab-${target}`) {
                        content.classList.add('active');
                    }
                });

                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
            });
        });
    });
    </script>
</body>
</html>
