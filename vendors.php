<?php
session_start();
require_once '../includes/config.php';
require_once '../classes/Vendor.class.php';

$vendor = new Vendor($pdo);

$search_query = $_GET['search_query'] ?? null;
$category_id = $_GET['category'] ?? null;

// Fetch vendors based on search query or category ID
if ($search_query) {
    $page_title = "Search Results for: \"" . htmlspecialchars($search_query) . "\"";
    $vendors = $vendor->searchVendors($search_query);
} elseif ($category_id) {
    $category_info = $vendor->getAllVendorCategories(); // Re-fetch all categories to find the name
    $category_name = "Vendors";
    foreach($category_info as $cat) {
        if ($cat['id'] == $category_id) {
            $category_name = htmlspecialchars($cat['category_name']) . " Vendors";
            break;
        }
    }
    $page_title = $category_name;
    $vendors = $vendor->getVendorsByCategoryId($category_id);
} else {
    // If no search query or category, display all vendors or a default set
    $page_title = "All Vendors";
    $vendors = $vendor->searchVendors(); // Use searchVendors with null query to get all verified
}

include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - EventCraftAI</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/vendors.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

    <div class="vendors-container">
        <h1><?= $page_title ?></h1>

        <?php if (!empty($vendors)): ?>
            <div class="vendors-grid">
                <?php foreach ($vendors as $vendor_item): ?>
                    <div class="vendor-card">
                        <a href="<?= BASE_URL ?>public/vendor_profile.php?id=<?= htmlspecialchars($vendor_item['id']) ?>" class="vendor-card-link">
                            <div class="vendor-card-image-wrapper">
                                <?php if (!empty($vendor_item['profile_image'])): ?>
                                    <img src="<?= ASSETS_PATH ?>uploads/users/<?= htmlspecialchars($vendor_item['profile_image']) ?>" alt="<?= htmlspecialchars($vendor_item['business_name']) ?> Profile Picture">
                                <?php else: ?>
                                    <img src="<?= ASSETS_PATH ?>images/default-avatar.jpg" alt="Default Avatar">
                                <?php endif; ?>
                            </div>
                            <div class="vendor-card-content">
                                <h3><?= htmlspecialchars($vendor_item['business_name']) ?></h3>
                                <p class="vendor-location"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($vendor_item['business_city']) ?></p>
                                <div class="vendor-card-rating">
                                    <?php
                                    $rating = round($vendor_item['rating'] * 2) / 2;
                                    for ($i = 1; $i <= 5; $i++):
                                        if ($rating >= $i) { echo '<i class="fas fa-star"></i>'; }
                                        elseif ($rating > ($i - 1) && $rating < $i) { echo '<i class="fas fa-star-half-alt"></i>'; }
                                        else { echo '<i class="far fa-star"></i>'; }
                                    endfor;
                                    ?>
                                    <span>(<?= htmlspecialchars($vendor_item['total_reviews']) ?> Reviews)</span>
                                </div>
                                <?php if (!empty($vendor_item['offered_services'])): ?>
                                    <p class="vendor-services-list">Services: <?= htmlspecialchars($vendor_item['offered_services']) ?></p>
                                <?php endif; ?>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h3>No Vendors Found</h3>
                <p>No vendors matched your search criteria or category.</p>
                <a href="<?= BASE_URL ?>public/vendors.php" class="btn btn-primary" style="margin-top: 20px;">View All Vendors</a>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'footer.php'; ?>

</body>
</html>
