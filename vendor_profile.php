<?php
// public/vendor_profile.php
require_once '../includes/config.php';
require_once '../classes/User.class.php'; // Needed for default-avatar.jpg path, or if you display reviewer info
require_once '../classes/Vendor.class.php'; // Crucial for fetching vendor data
require_once '../classes/Review.class.php'; // For fetching reviews

include 'header.php'; // Include the main site header

// Ensure vendor ID is provided in the URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // Redirect to a vendors list page or show an error
    $_SESSION['error_message'] = "Invalid vendor ID provided.";
    header('Location: ' . BASE_URL . 'public/index.php'); // Or vendors.php if you have one
    exit();
}

$vendor_id = (int)$_GET['id'];

$vendor = new Vendor($pdo);
$review = new Review($pdo); // Instantiate Review class

// Fetch main vendor profile data including user and user_profile data
$vendor_profile = $vendor->getVendorProfileById($vendor_id);

if (!$vendor_profile) {
    $_SESSION['error_message'] = "Vendor not found.";
    header('Location: ' . BASE_URL . 'public/index.php'); // Or vendors.php
    exit();
}

// Fetch vendor's portfolio items
$portfolio_items = $vendor->getVendorPortfolio($vendor_id);

// Fetch vendor's reviews
$vendor_reviews = $review->getReviewsForEntity($vendor_profile['user_id'], 'vendor'); // Pass user_id for reviewed_id

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($vendor_profile['business_name']) ?>'s Profile - EventCraftAI</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/vendor_profile.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="vendor-profile-container">
        <div class="profile-header-section">
            <div class="profile-avatar">
                <?php if (!empty($vendor_profile['profile_image'])): ?>
                    <img src="<?= BASE_URL ?>assets/uploads/users/<?= htmlspecialchars($vendor_profile['profile_image']) ?>" alt="<?= htmlspecialchars($vendor_profile['business_name']) ?> Profile Picture">
                <?php else: ?>
                    <img src="<?= BASE_URL ?>assets/images/default-avatar.jpg" alt="Default Avatar">
                <?php endif; ?>
            </div>
            <div class="profile-info-main">
                <h1><?= htmlspecialchars($vendor_profile['business_name']) ?></h1>
                <p class="tagline">Providing exceptional services for your events.</p>
                <div class="rating-display">
                    <?php
                    $rating = round($vendor_profile['rating'] * 2) / 2; // Round to nearest 0.5
                    for ($i = 1; $i <= 5; $i++):
                        if ($rating >= $i) {
                            echo '<i class="fas fa-star"></i>'; // Full star
                        } elseif ($rating > ($i - 1) && $rating < $i) {
                            echo '<i class="fas fa-star-half-alt"></i>'; // Half star
                        } else {
                            echo '<i class="far fa-star"></i>'; // Empty star
                        }
                    endfor;
                    ?>
                    <span><?= number_format($vendor_profile['rating'], 1) ?? 'N/A' ?> (<?= $vendor_profile['total_reviews'] ?? 0 ?> reviews)</span>
                </div>
                <div class="contact-buttons">
                    <?php if (isset($_SESSION['user_id'])): // Only allow contact if logged in ?>
                        <a href="<?= BASE_URL ?>public/chat.php?vendor_id=<?= htmlspecialchars($vendor_profile['user_id']) ?>&event_id=YOUR_EVENT_ID" class="btn btn-primary">
                            <i class="fas fa-comment"></i> Message Vendor
                        </a>
                        <?php else: ?>
                        <a href="<?= BASE_URL ?>public/login.php" class="btn btn-primary">Login to Message</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="profile-section">
            <h2>Business Details</h2>
            <div class="details-grid">
                <p><strong>Location:</strong> <?= htmlspecialchars($vendor_profile['business_city']) ?>, <?= htmlspecialchars($vendor_profile['business_state']) ?>, <?= htmlspecialchars($vendor_profile['business_country']) ?></p>
                <?php if (!empty($vendor_profile['business_address'])): ?>
                    <p><strong>Address:</strong> <?= htmlspecialchars($vendor_profile['business_address']) ?>, <?= htmlspecialchars($vendor_profile['business_postal_code']) ?></p>
                <?php endif; ?>
                <?php if (!empty($vendor_profile['website'])): ?>
                    <p><strong>Website:</strong> <a href="<?= htmlspecialchars($vendor_profile['website']) ?>" target="_blank"><?= htmlspecialchars($vendor_profile['website']) ?></a></p>
                <?php endif; ?>
                <?php if (!empty($vendor_profile['experience_years'])): ?>
                    <p><strong>Experience:</strong> <?= htmlspecialchars($vendor_profile['experience_years']) ?> Years</p>
                <?php endif; ?>
                <?php if (!empty($vendor_profile['min_budget']) && !empty($vendor_profile['max_budget'])): ?>
                    <p><strong>Budget Range:</strong> $<?= number_format($vendor_profile['min_budget'], 0) ?> - $<?= number_format($vendor_profile['max_budget'], 0) ?></p>
                <?php endif; ?>
                <?php if (!empty($vendor_profile['offered_services_names'])): ?>
                    <p><strong>Services:</strong> <?= htmlspecialchars($vendor_profile['offered_services_names']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="profile-section">
            <h2>Portfolio</h2>
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
                                <p><?= htmlspecialchars(substr($item['description'] ?? 'No description available.', 0, 150)) ?><?= (strlen($item['description'] ?? '') > 150) ? '...' : '' ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No portfolio items added yet.</p>
            <?php endif; ?>
        </div>

        <div class="profile-section">
            <h2>Reviews (<?= $vendor_profile['total_reviews'] ?? 0 ?>)</h2>
            <?php if (!empty($vendor_reviews)): ?>
                <div class="reviews-list">
                    <?php foreach ($vendor_reviews as $review_item): ?>
                        <div class="review-card">
                            <div class="reviewer-info">
                                <div class="reviewer-avatar">
                                    <?php if (!empty($review_item['profile_image'])): ?>
                                        <img src="<?= BASE_URL ?>assets/uploads/users/<?= htmlspecialchars($review_item['profile_image']) ?>" alt="Reviewer Avatar">
                                    <?php else: ?>
                                        <img src="<?= BASE_URL ?>assets/images/default-avatar.jpg" alt="Default Avatar">
                                    <?php endif; ?>
                                </div>
                                <span class="reviewer-name"><?= htmlspecialchars($review_item['first_name'] ?? 'Anonymous') ?></span>
                                <span class="review-date"><?= date('M j, Y', strtotime($review_item['created_at'])) ?></span>
                            </div>
                            <div class="review-rating-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?= ($review_item['rating'] >= $i) ? 'filled' : '' ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <h3><?= htmlspecialchars($review_item['review_title']) ?></h3>
                            <p><?= nl2br(htmlspecialchars($review_item['review_content'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No reviews for this vendor yet.</p>
            <?php endif; ?>
        </div>

    </div>

    <?php include 'footer.php'; // Include the main site footer ?>
</body>
</html>
