<?php
session_start();
require_once '../includes/config.php';
require_once '../classes/User.class.php';
require_once '../classes/Vendor.class.php';
require_once '../classes/UploadHandler.class.php'; // Needed for displaying images, not for handling upload directly in this file
include 'header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

$portfolio_item_id = $_GET['id'] ?? null;
if (!$portfolio_item_id || !is_numeric($portfolio_item_id)) {
    $_SESSION['error_message'] = "Invalid portfolio item ID provided.";
    header('Location: ' . BASE_URL . 'public/vendor_portfolio.php');
    exit();
}

$user = new User($pdo);
$vendor_obj = new Vendor($pdo);
$uploader = new UploadHandler(); // Still needed to help generate image URLs

$user_data = $user->getUserById($_SESSION['user_id']);
$vendor_data = $vendor_obj->getVendorByUserId($_SESSION['user_id']);

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

// NEW: Fetch all images associated with this portfolio item (now comes from $item_to_edit['images'])
$existing_images = $item_to_edit['images'] ?? [];

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
        .current-images-preview {
            margin-top: 10px;
            margin-bottom: 15px;
            text-align: center;
        }
        .current-images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .current-image-item {
            position: relative;
            width: 100px;
            height: 100px;
            overflow: hidden;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            display: inline-block; /* For alignment in grid */
        }
        .current-image-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .current-image-item .delete-image-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(255, 0, 0, 0.7);
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            font-size: 0.8em;
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
            opacity: 0.8;
        }
        .current-image-item .delete-image-btn:hover {
            opacity: 1;
        }
        /* Style for undo delete button */
        .undo-delete-image-btn {
            background: rgba(0, 128, 0, 0.7); /* Greenish for undo */
        }
    </style>
</head>
<body>
    <div class="edit-portfolio-container">
        <h1>Edit Portfolio Item</h1>

        <?php if ($success): ?>
            <div class="alert success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="process_portfolio.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="portfolio_item_id" value="<?= htmlspecialchars($portfolio_item_id) ?>">
            
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
                <label>Current Images</label>
                <?php if (!empty($existing_images)): ?>
                    <div class="current-images-preview">
                        <div class="current-images-grid">
                            <?php foreach ($existing_images as $image): ?>
                                <div class="current-image-item">
                                    <img src="<?= BASE_URL . htmlspecialchars($image['image_url']) ?>" alt="Portfolio Image">
                                    <button type="button" class="delete-image-btn" data-image-id="<?= htmlspecialchars($image['id']) ?>">×</button>
                                    <input type="hidden" name="existing_images[<?= htmlspecialchars($image['id']) ?>]" value="keep">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <p>No images currently uploaded for this item.</p>
                <?php endif; ?>
                <small class="text-muted">Click 'x' to mark an image for deletion. It will be removed when you save.</small>
            </div>

            <div class="form-group">
                <label for="portfolio_images_new">Add More Images (Up to 20 total)</label>
                <input type="file" id="portfolio_images_new" name="portfolio_images_new[]" accept="image/*" multiple>
                <small class="text-muted">You can select multiple images. Max 20 images including existing ones.</small>
            </div>

            <div class="form-group">
                <label for="portfolio_video_url">Video URL (e.g., YouTube link)</label>
                <input type="url" id="portfolio_video_url" name="portfolio_video_url" value="<?= htmlspecialchars($item_to_edit['video_url'] ?? '') ?>" placeholder="http://youtube.com/...">
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Function to add event listener for delete button
            function addDeleteListener(button) {
                button.addEventListener('click', function() {
                    const imageItem = this.closest('.current-image-item');
                    const imageId = this.dataset.imageId;
                    const hiddenInput = imageItem.querySelector(`input[name="existing_images[${imageId}]"]`);

                    if (hiddenInput) {
                        if (hiddenInput.value === 'keep') { // Mark for deletion
                            hiddenInput.value = 'delete';
                            imageItem.style.opacity = '0.5';
                            imageItem.style.border = '1px solid red';
                            this.textContent = 'Undo';
                            this.classList.remove('delete-image-btn');
                            this.classList.add('undo-delete-image-btn');
                        } else { // Undo deletion
                            hiddenInput.value = 'keep';
                            imageItem.style.opacity = '1';
                            imageItem.style.border = '1px solid var(--border-color)';
                            this.textContent = '×';
                            this.classList.remove('undo-delete-image-btn');
                            this.classList.add('delete-image-btn');
                        }
                    }
                });
            }

            // Apply listeners to all current delete buttons
            document.querySelectorAll('.delete-image-btn').forEach(addDeleteListener);
            document.querySelectorAll('.undo-delete-image-btn').forEach(addDeleteListener); // Also apply to any pre-existing undo buttons on page load


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
