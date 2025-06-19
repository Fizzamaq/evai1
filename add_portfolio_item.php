<?php
session_start();
require_once '../includes/config.php';
require_once '../classes/User.class.php';
require_once '../classes/Vendor.class.php';
include 'header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

$user = new User($pdo);
$vendor_obj = new Vendor($pdo);
$vendor_data = $vendor_obj->getVendorByUserId($_SESSION['user_id']);

if (!$vendor_data) {
    $_SESSION['error_message'] = "Vendor profile not found.";
    header('Location: ' . BASE_URL . 'public/vendor_dashboard.php');
    exit();
}

// Get event types for dropdown
try {
    $event_types = dbFetchAll("SELECT id, type_name FROM event_types WHERE is_active = TRUE");
} catch (PDOException $e) {
    $event_types = [];
    error_log("Get event types error: " . $e->getMessage());
}

$error = $_SESSION['error_message'] ?? null;
$success = $_SESSION['success_message'] ?? null;
unset($_SESSION['error_message'], $_SESSION['success_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Portfolio Item - EventCraftAI</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/vendor.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .add-portfolio-container {
            max-width: 700px;
            margin: var(--spacing-lg) auto;
            padding: var(--spacing-md);
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .add-portfolio-container h1 {
            text-align: center;
            margin-bottom: var(--spacing-lg);
            color: var(--primary-color);
            font-size: 2.2em;
        }
        .add-portfolio-container .form-group {
            margin-bottom: var(--spacing-md);
        }
        .add-portfolio-container .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: var(--spacing-md);
        }
        .add-portfolio-container .form-actions .btn {
            width: auto;
        }
    </style>
</head>
<body>
    <div class="add-portfolio-container">
        <h1>Add New Portfolio Item</h1>

        <?php if ($success): ?>
            <div class="alert success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="process_portfolio.php" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="portfolio_title">Item Title <span class="required">*</span></label>
                <input type="text" id="portfolio_title" name="portfolio_title" required>
            </div>
            <div class="form-group">
                <label for="portfolio_description">Description</label>
                <textarea id="portfolio_description" name="portfolio_description" rows="4"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="portfolio_event_type_id">Event Type</label>
                    <select id="portfolio_event_type_id" name="portfolio_event_type_id">
                        <option value="">Select event type</option>
                        <?php foreach ($event_types as $type): ?>
                            <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['type_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="portfolio_project_date">Project Date</label>
                    <input type="date" id="portfolio_project_date" name="portfolio_project_date">
                </div>
            </div>
            <div class="form-group">
                <label for="project_charges">Project Charges (PKR)</label>
                <input type="number" id="project_charges" name="project_charges" step="0.01" min="0" placeholder="e.g., 50000.00">
            </div>
            <div class="form-group">
                <label for="portfolio_images">Image Files (Up to 20)</label>
                <input type="file" id="portfolio_images" name="portfolio_images[]" accept="image/*" multiple>
                <small class="text-muted">You can select multiple images. Maximum 20 images allowed.</small>
            </div>
            <div class="form-group">
                <label for="portfolio_video_url">Video URL (e.g., YouTube link)</label>
                <input type="url" id="portfolio_video_url" name="portfolio_video_url" placeholder="http://youtube.com/...">
            </div>
            <div class="form-group">
                <label for="portfolio_testimonial">Client Testimonial</label>
                <textarea id="portfolio_testimonial" name="portfolio_testimonial" rows="3"></textarea>
            </div>
            <div class="form-group">
                <div class="featured-checkbox">
                    <input type="checkbox" id="portfolio_is_featured" name="portfolio_is_featured">
                    <label for="portfolio_is_featured">Feature this item on my profile</label>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" name="add_portfolio_item" class="btn btn-primary">Add Portfolio Item</button>
                <a href="<?= BASE_URL ?>public/vendor_portfolio.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>
