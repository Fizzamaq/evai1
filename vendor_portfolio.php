<?php
session_start();
require_once '../includes/config.php';
require_once '../classes/User.class.php';
require_once '../classes/Vendor.class.php';
require_once '../classes/UploadHandler.class.php'; // Include UploadHandler

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

$user = new User($pdo); // Pass PDO
$vendor = new Vendor($pdo); // Pass PDO
// $uploader = new UploadHandler(); // Not needed here anymore as form is moved

$user_data = $user->getUserById($_SESSION['user_id']);
$vendor_data = $vendor->getVendorByUserId($_SESSION['user_id']);

if (!$vendor_data) {
    $_SESSION['error_message'] = "Vendor profile not found. Please complete your vendor registration.";
    header('Location: ' . BASE_URL . 'public/vendor_dashboard.php');
    exit();
}

// The portfolio item submission handling logic has been moved to process_portfolio.php
// This block is now removed from here.

// Get vendor portfolio items
// The getVendorPortfolio method in Vendor.class.php now fetches 'main_image_url'
$portfolio_items = $vendor->getVendorPortfolio($vendor_data['id']);

// Get event types for dropdown (not strictly needed here for display, but kept if still used elsewhere)
try {
    $event_types = dbFetchAll("SELECT id, type_name FROM event_types WHERE is_active = TRUE");
} catch (PDOException $e) {
    $event_types = [];
    error_log("Get event types error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Portfolio - EventCraftAI</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/vendor.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/vendor_profile.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="portfolio-container">
        <div class="portfolio-header">
            <div>
                <h1>My Portfolio</h1>
                <p>Manage and view your public portfolio items</p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>public/add_portfolio_item.php" class="btn btn-primary">Add New Portfolio Item</a>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert success"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert error"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
        <?php endif; ?>

        <div class="portfolio-items-display-section">
            <h2>Your Current Portfolio Items</h2>
            <?php if (empty($portfolio_items)): ?>
                <div class="empty-state">
                    <h3>No Portfolio Items Yet</h3>
                    <p>Add your first portfolio item by clicking "Add New Portfolio Item" above!</p>
                </div>
            <?php else: ?>
                <div class="portfolio-grid">
                    <?php foreach ($portfolio_items as $item): ?>
                        <div class="portfolio-item-card">
                            <a href="<?= BASE_URL ?>public/view_portfolio_item.php?id=<?= $item['id'] ?>" class="portfolio-item-link-area">
                                <div class="portfolio-image-wrapper">
                                    <?php if (!empty($item['main_image_url'])): ?>
                                        <img src="<?= BASE_URL . htmlspecialchars($item['main_image_url']) ?>" alt="<?= htmlspecialchars($item['title']) ?>">
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
                                    <?php if (!empty($item['project_charges'])): ?>
                                        <p class="project-charges-summary">Charges: PKR <?= number_format($item['project_charges'], 0) ?></p>
                                    <?php endif; ?>
                                    <div class="portfolio-meta-info">
                                        <?php if ($item['event_type_name']): ?>
                                            <span><i class="fas fa-tag"></i> <?= htmlspecialchars($item['event_type_name']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($item['project_date']): ?>
                                            <span><i class="fas fa-calendar-alt"></i> <?= date('M Y', strtotime($item['project_date'])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                            <div class="portfolio-actions-footer">
                                <a href="<?= BASE_URL ?>public/edit_portfolio_item.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-secondary">Edit</a>
                                <form method="POST" action="<?= BASE_URL ?>public/process_portfolio.php" onsubmit="return confirm('Are you sure you want to delete this portfolio item?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="portfolio_item_id" value="<?= $item['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        // No specific JavaScript needed here as the form is on a separate page now.
    </script>
</body>
</html>
