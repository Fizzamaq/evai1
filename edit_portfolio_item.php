<?php
// public/edit_portfolio_item.php
session_start();
require_once '../includes/config.php';
require_once '../classes/User.class.php';
require_once '../classes/Vendor.class.php';
require_once '../classes/UploadHandler.class.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

$portfolio_item_id = $_GET['id'] ?? null;
if (!$portfolio_item_id) {
    $_SESSION['error_message'] = "No portfolio item specified for editing.";
    header('Location: ' . BASE_URL . 'public/vendor_portfolio.php');
    exit();
}

$user = new User($pdo);
$vendor_obj = new Vendor($pdo); // Renamed to avoid conflict with $vendor_data
$uploader = new UploadHandler();

$user_data = $user->getUserById($_SESSION['user_id']);
$vendor_data = $vendor_obj->getVendorByUserId($_SESSION['user_id']); // This is the vendor profile of the logged-in user

if (!$vendor_data) {
    $_SESSION['error_message'] = "Vendor profile not found. Cannot edit portfolio.";
    header('Location: ' . BASE_URL . 'public/vendor_dashboard.php');
    exit();
}

$item_to_edit = $vendor_obj->getPortfolioItemById($portfolio_item_id);

// Verify ownership of the portfolio item
if (!$item_to_edit || $item_to_edit['vendor_id'] != $vendor_data['id']) {
    $_SESSION['error_message'] = "Portfolio item not found or you don't have permission to edit it.";
    header('Location: ' . BASE_URL . 'public/vendor_portfolio.php');
    exit();
}

// Handle form submission for updating portfolio items
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_portfolio_item'])) {
    $new_image_url = $item_to_edit['image_url']; // Start with existing image URL

    try {
        if (isset($_FILES['portfolio_image_upload']) && $_FILES['portfolio_image_upload']['error'] === UPLOAD_ERR_OK) {
            // A new image was uploaded. Delete the old one if it exists.
            if (!empty($item_to_edit['image_url'])) {
                // Extract filename from the stored URL
                $old_filename = basename($item_to_edit['image_url']);
                $uploader->deleteFile($old_filename, 'vendors/'); // Assuming 'vendors/' is the subfolder
            }
            $uploaded_filename = $uploader->handleUpload($_FILES['portfolio_image_upload'], 'vendors/');
            $new_image_url = 'assets/uploads/vendors/' . $uploaded_filename;
        } else if (isset($_FILES['portfolio_image_upload']) && $_FILES['portfolio_image_upload']['error'] !== UPLOAD_ERR_NO_FILE) {
            throw new Exception("File upload error: " . $_FILES['portfolio_image_upload']['error']);
        }
        // If no new file uploaded and no error, $new_image_url remains the old one.

        $portfolio_data = [
            'title' => trim($_POST['portfolio_title']),
            'description' => trim($_POST['portfolio_description'] ?? ''),
            'event_type_id' => !empty($_POST['portfolio_event_type_id']) ? (int)$_POST['portfolio_event_type_id'] : null,
            'image_url' => $new_image_url,
            'video_url' => trim($_POST['portfolio_video_url'] ?? ''),
            'project_date' => !empty($_POST['portfolio_project_date']) ? $_POST['portfolio_project_date'] : null,
            'project_charges' => !empty($_POST['project_charges']) ? (float)$_POST['project_charges'] : null, // Added project_charges
            'client_testimonial' => trim($_POST['portfolio_testimonial'] ?? ''),
            'is_featured' => isset($_POST['portfolio_is_featured']) ? 1 : 0
        ];

        if (!$vendor_obj->updatePortfolioItem($portfolio_item_id, $vendor_data['id'], $portfolio_data)) {
            throw new Exception("Failed to update portfolio item. Database error.");
        } else {
            $_SESSION['success_message'] = 'Portfolio item updated successfully!';
            header('Location: ' . BASE_URL . 'public/vendor_portfolio.php'); // Redirect back to portfolio list
            exit();
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Update Error: ' . $e->getMessage();
        // Re-fetch item to show latest changes if partial update happened or for next display
        $item_to_edit = $vendor_obj->getPortfolioItemById($portfolio_item_id);
    }
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
unset($_SESSION['error_message'], $_SESSION['success_message']); // Clear messages
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Portfolio Item - EventCraftAI</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/vendor.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/vendor_profile.css">
    <style>
        .edit-portfolio-container {
            max-width: 700px;
            margin: var(--spacing-lg) auto;
            padding: var(--spacing-md);
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .edit-portfolio-container h1 {
            text-align: center;
            margin-bottom: var(--spacing-lg);
            color: var(--primary-color);
            font-size: 2.2em;
        }
        .edit-portfolio-container .form-group {
            margin-bottom: var(--spacing-md);
        }
        .edit-portfolio-container .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: var(--spacing-md);
        }
        .edit-portfolio-container .form-actions .btn {
            width: auto;
        }
        .current-image-preview {
            margin-top: 10px;
            margin-bottom: 15px;
            text-align: center;
        }
        .current-image-preview img {
            max-width: 150px;
            max-height: 150px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="edit-portfolio-container">
        <h1>Edit Portfolio Item</h1>

        <?php if ($success): ?>
            <div class="alert success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="edit_portfolio_item.php?id=<?= htmlspecialchars($portfolio_item_id) ?>" method="post" enctype="multipart/form-data">
            <input type="hidden" name="original_image_url" value="<?= htmlspecialchars($item_to_edit['image_url'] ?? '') ?>">
            <div class="form-group">
                <label for="portfolio_title">Item Title <span class="required">*</span></label>
                <input type="text" id="portfolio_title" name="portfolio_title" value="<?= htmlspecialchars($item_to_edit['title'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="portfolio_description">Description</label>
                <textarea id="portfolio_description" name="portfolio_description" rows="4"><?= htmlspecialchars($item_to_edit['description'] ?? '') ?></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="portfolio_event_type_id">Event Type</label>
                    <select id="portfolio_event_type_id" name="portfolio_event_type_id">
                        <option value="">Select event type</option>
                        <?php foreach ($event_types as $type): ?>
                            <option value="<?php echo $type['id']; ?>" <?= ($item_to_edit['event_type_id'] == $type['id']) ? 'selected' : '' ?>>
                                <?php echo htmlspecialchars($type['type_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="portfolio_project_date">Project Date</label>
                    <input type="date" id="portfolio_project_date" name="portfolio_project_date" value="<?= htmlspecialchars($item_to_edit['project_date'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label for="project_charges">Project Charges (PKR)</label>
                <input type="number" id="project_charges" name="project_charges" step="0.01" min="0" value="<?= htmlspecialchars($item_to_edit['project_charges'] ?? '') ?>" placeholder="e.g., 50000.00">
            </div>
            <div class="form-group">
                <label for="portfolio_image_upload">Image File (Leave blank to keep current)</label>
                <input type="file" id="portfolio_image_upload" name="portfolio_image_upload" accept="image/*">
                <?php if (!empty($item_to_edit['image_url'])): ?>
                    <div class="current-image-preview">
                        <p>Current Image:</p>
                        <img src="<?= BASE_URL . htmlspecialchars($item_to_edit['image_url']) ?>" alt="Current Portfolio Image">
                    </div>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="portfolio_video_url">Video URL (e.g., YouTube link)</label>
                <input type="url" id="portfolio_video_url" name="portfolio_video_url" value="<?= htmlspecialchars($item_to_edit['video_url'] ?? '') ?>" placeholder="http://youtube.com/watch?v=...">
            </div>
            <div class="form-group">
                <label for="portfolio_testimonial">Client Testimonial</label>
                <textarea id="portfolio_testimonial" name="portfolio_testimonial" rows="3"><?= htmlspecialchars($item_to_edit['client_testimonial'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <div class="featured-checkbox">
                    <input type="checkbox" id="portfolio_is_featured" name="portfolio_is_featured" <?= ($item_to_edit['is_featured'] ?? 0) ? 'checked' : '' ?>>
                    <label for="portfolio_is_featured">Feature this item on my profile</label>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" name="update_portfolio_item" class="btn btn-primary">Update Portfolio Item</button>
                <a href="<?= BASE_URL ?>public/vendor_portfolio.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const titleInput = document.getElementById('portfolio_title');
                if (!titleInput.value.trim()) {
                    e.preventDefault();
                    alert('Item Title is required.');
                }
            });
        });
    </script>
</body>
</html>