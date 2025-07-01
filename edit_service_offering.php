<?php
session_start();
require_once '../includes/config.php';
require_once '../classes/User.class.php';
require_once '../classes/Vendor.class.php';
require_once '../classes/UploadHandler.class.php'; // Needed for image paths

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

$service_offering_id = $_GET['id'] ?? null;
if (!$service_offering_id || !is_numeric($service_offering_id)) {
    $_SESSION['error_message'] = "Invalid service offering ID provided.";
    header('Location: ' . BASE_URL . 'public/vendor_services_management.php');
    exit();
}

$user = new User($pdo);
$vendor_obj = new Vendor($pdo);
$uploader = new UploadHandler(); // To generate image URLs if needed

// Verify vendor access
$vendor_obj->verifyVendorAccess();

// $_SESSION['vendor_id'] is set by verifyVendorAccess()
$vendor_profile_id = $_SESSION['vendor_id'];

// Get the specific service offering to edit
$offering_to_edit = $vendor_obj->getServiceOfferingById($service_offering_id, $vendor_profile_id);

if (!$offering_to_edit) {
    $_SESSION['error_message'] = "Service offering not found or you don't have permission to edit it.";
    header('Location: ' . BASE_URL . 'public/vendor_services_management.php');
    exit();
}

// Get all vendor services (to display the name of the offered service)
// No need for 'available services' as we are editing an existing one
$all_vendor_services_raw = dbFetchAll("SELECT id, service_name, category_id FROM vendor_services WHERE is_active = TRUE");
$all_vendor_services = [];
foreach ($all_vendor_services_raw as $service) {
    $all_vendor_services[$service['id']] = $service['service_name'];
}

// Existing images associated with this service offering
$existing_images = $offering_to_edit['images'] ?? [];

$error_message = $_SESSION['error_message'] ?? null;
$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['error_message'], $_SESSION['success_message']);

include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Service Offering - EventCraftAI</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/vendor.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Specific styles for this page */
        .edit-service-container {
            max-width: 700px;
            margin: var(--spacing-lg) auto;
            padding: var(--spacing-md);
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .edit-service-container h1 {
            text-align: center;
            margin-bottom: var(--spacing-lg);
            color: var(--primary-color);
            font-size: 2.2em;
        }
        .edit-service-container .form-group {
            margin-bottom: var(--spacing-md);
        }
        .edit-service-container .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: var(--spacing-md);
        }
        .edit-service-container .form-actions .btn {
            width: auto;
        }
        .current-images-preview {
            margin-top: 10px;
            margin-bottom: 15px;
            text-align: center;
        }
        .current-images-grid {
            display: flex; /* Use flex for image display */
            flex-wrap: wrap; /* Allow images to wrap */
            gap: 10px;
            margin-top: 10px;
            justify-content: center;
        }
        .current-image-item {
            position: relative;
            width: 100px;
            height: 100px;
            overflow: hidden;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background-color: var(--background-light);
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
            z-index: 10; /* Ensure button is on top of image */
        }
        .current-image-item .delete-image-btn:hover {
            opacity: 1;
        }
        .undo-delete-image-btn {
            background: rgba(0, 128, 0, 0.7); /* Greenish for undo */
        }
        .image-preview-grid {
            display: flex; /* Use flex for new image previews */
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
            justify-content: center;
        }
        .image-preview-item {
            width: 100px;
            height: 100px;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #f0f0f0;
        }
        .image-preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .image-preview-item i.fas.fa-image {
            font-size: 2em;
            color: #ccc;
        }
    </style>
</head>
<body>
    <div class="edit-service-container">
        <h1>Edit Service Offering: <?= htmlspecialchars($all_vendor_services[$offering_to_edit['service_id']] ?? 'Unknown Service') ?></h1>

        <?php if ($success_message): ?>
            <div class="alert success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <form action="process_service_offering.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="service_offering_id" value="<?= htmlspecialchars($offering_to_edit['id']) ?>">
            <input type="hidden" name="service_id" value="<?= htmlspecialchars($offering_to_edit['service_id']) ?>">

            <div class="form-group">
                <label>Service Name</label>
                <input type="text" value="<?= htmlspecialchars($all_vendor_services[$offering_to_edit['service_id']] ?? 'N/A') ?>" readonly disabled>
                <small class="text-muted">You cannot change the service type once added.</small>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="price_min">Minimum Price (PKR)</label>
                    <input type="number" id="price_min" name="price_min" step="0.01" min="0" 
                           value="<?= htmlspecialchars($offering_to_edit['price_range_min'] ?? '') ?>" placeholder="e.g., 50000.00">
                </div>
                <div class="form-group">
                    <label for="price_max">Maximum Price (PKR)</label>
                    <input type="number" id="price_max" name="price_max" step="0.01" min="0" 
                           value="<?= htmlspecialchars($offering_to_edit['price_range_max'] ?? '') ?>" placeholder="e.g., 100000.00">
                </div>
            </div>

            <div class="form-group">
                <label for="description">Description / Offer Details</label>
                <textarea id="description" name="description" rows="5" 
                          placeholder="Describe this service offering, packages, or any special offers."><?= htmlspecialchars($offering_to_edit['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label>Current Images</label>
                <?php if (!empty($existing_images)): ?>
                    <div class="current-images-preview">
                        <div class="current-images-grid">
                            <?php foreach ($existing_images as $image): ?>
                                <div class="current-image-item">
                                    <img src="<?= BASE_URL . htmlspecialchars($image['image_url']) ?>" alt="Service Offering Image">
                                    <button type="button" class="delete-image-btn" data-image-id="<?= htmlspecialchars($image['id']) ?>">×</button>
                                    <input type="hidden" name="existing_images[<?= htmlspecialchars($image['id']) ?>]" value="keep">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <p>No images currently uploaded for this service offering.</p>
                <?php endif; ?>
                <small class="text-muted">Click 'x' to mark an image for deletion. It will be removed when you save.</small>
            </div>

            <div class="form-group">
                <label for="new_images">Add More Images (Up to 10 total)</label>
                <input type="file" id="new_images" name="new_images[]" accept="image/*" multiple>
                <small class="text-muted">You can select multiple images. Max 10 images including existing ones.</small>
                <div class="image-preview-grid" id="image-preview-grid"></div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update Service Offering</button>
                <a href="<?= BASE_URL ?>public/vendor_services_management.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Logic for deleting/undoing deletion of existing images
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

            // Apply listeners to all current delete/undo buttons
            document.querySelectorAll('.delete-image-btn').forEach(addDeleteListener);
            document.querySelectorAll('.undo-delete-image-btn').forEach(addDeleteListener);

            // Logic for previewing new images
            const newImagesInput = document.getElementById('new_images');
            const newImagePreviewGrid = document.getElementById('image-preview-grid');
            const maxTotalImages = 10; // Max allowed images including existing ones

            newImagesInput.addEventListener('change', function() {
                newImagePreviewGrid.innerHTML = ''; // Clear previous previews
                const newFiles = this.files;
                
                // Count currently kept existing images
                let keptExistingImagesCount = 0;
                document.querySelectorAll('.current-image-item input[type="hidden"]').forEach(input => {
                    if (input.value === 'keep') {
                        keptExistingImagesCount++;
                    }
                });

                if (newFiles.length + keptExistingImagesCount > maxTotalImages) {
                    alert(`You can only have a maximum of ${maxTotalImages} images in total (including existing ones). Please select fewer new images.`);
                    this.value = ''; // Clear selected files
                    return;
                }

                if (newFiles) {
                    Array.from(newFiles).forEach(file => {
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            const previewItem = document.createElement('div');
                            previewItem.className = 'image-preview-item';
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            previewItem.appendChild(img);
                            newImagePreviewGrid.appendChild(previewItem);
                        };
                        reader.readAsDataURL(file);
                    });
                }
            });

            // Basic client-side validation before submission
            document.querySelector('form').addEventListener('submit', function(event) {
                const priceMin = document.getElementById('price_min');
                const priceMax = document.getElementById('price_max');
                
                if (priceMin.value && priceMax.value && parseFloat(priceMin.value) > parseFloat(priceMax.value)) {
                    alert('Minimum price cannot be greater than maximum price.');
                    priceMin.focus();
                    event.preventDefault();
                }
            });
        });
    </script>
</body>
</html>