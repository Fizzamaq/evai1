<?php
session_start();
require_once '../includes/config.php';
require_once '../classes/User.class.php';
require_once '../classes/Vendor.class.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

$user = new User($pdo);
$vendor_obj = new Vendor($pdo);

// Verify vendor access
$vendor_obj->verifyVendorAccess();

// $_SESSION['vendor_id'] is set by verifyVendorAccess()
$vendor_profile_id = $_SESSION['vendor_id'];

// Get all vendor services that are NOT currently offered by this vendor
try {
    // Subquery to get service_ids already offered by the vendor
    $stmt_offered_services = $pdo->prepare("SELECT service_id FROM vendor_service_offerings WHERE vendor_id = ?");
    $stmt_offered_services->execute([$vendor_profile_id]);
    $offered_service_ids = array_column($stmt_offered_services->fetchAll(PDO::FETCH_ASSOC), 'service_id');

    // Get all active services, excluding those already offered
    $placeholders = '';
    if (!empty($offered_service_ids)) {
        $placeholders = implode(',', array_fill(0, count($offered_service_ids), '?'));
        $sql = "SELECT vs.id, vs.service_name, vc.category_name
                FROM vendor_services vs
                JOIN vendor_categories vc ON vs.category_id = vc.id
                WHERE vs.is_active = TRUE AND vs.id NOT IN ($placeholders)
                ORDER BY vc.category_name, vs.service_name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($offered_service_ids);
    } else {
        // If no services are offered yet, just get all active services
        $sql = "SELECT vs.id, vs.service_name, vc.category_name
                FROM vendor_services vs
                JOIN vendor_categories vc ON vs.category_id = vc.id
                WHERE vs.is_active = TRUE
                ORDER BY vc.category_name, vs.service_name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    }
    
    $available_services_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $available_services_by_category = [];
    foreach ($available_services_raw as $service) {
        $available_services_by_category[$service['category_name']][] = $service;
    }

} catch (PDOException $e) {
    $available_services_by_category = [];
    error_log("Get available services error: " . $e->getMessage());
    $_SESSION['error_message'] = "Error loading available services: " . $e->getMessage();
}

$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['error_message']);

include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Service Offering - EventCraftAI</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/vendor.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Specific styles for this page */
        .add-service-container {
            max-width: 700px;
            margin: var(--spacing-lg) auto;
            padding: var(--spacing-md);
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .add-service-container h1 {
            text-align: center;
            margin-bottom: var(--spacing-lg);
            color: var(--primary-color);
            font-size: 2.2em;
        }
        .add-service-container .form-group {
            margin-bottom: var(--spacing-md);
        }
        .add-service-container .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: var(--spacing-md);
        }
        .add-service-container .form-actions .btn {
            width: auto;
        }
        .service-categories-container {
            margin-top: var(--spacing-md);
            padding: var(--spacing-md);
            background: var(--background-light);
            border-radius: 8px;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);
        }
        .service-category-group {
            margin-bottom: var(--spacing-lg);
        }
        .service-category-group h4 {
            margin-top: 0;
            margin-bottom: var(--spacing-md);
            color: var(--primary-color);
            border-bottom: 1px dashed var(--border-color);
            padding-bottom: var(--spacing-sm);
        }
        .services-radio-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-sm);
        }
        .form-group-radio {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: var(--white);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            transition: background-color 0.2s ease;
            cursor: pointer;
        }
        .form-group-radio:hover {
            background-color: var(--background-light);
        }
        .form-group-radio input[type="radio"] {
            margin-right: 0;
            width: auto;
            transform: scale(1.1);
        }
        .form-group-radio label {
            margin-bottom: 0;
            font-weight: 500;
            color: var(--text-dark);
            cursor: pointer;
        }
        .price-inputs {
            margin-top: var(--spacing-sm);
            padding-top: var(--spacing-sm);
            border-top: 1px dashed var(--border-color);
        }
        .image-preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 10px;
            margin-top: 15px;
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
    <div class="add-service-container">
        <h1>Add New Service Offering</h1>

        <?php if ($error_message): ?>
            <div class="alert error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>public/process_service_offering.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">

            <div class="form-group">
                <label for="service_id">Select Service <span class="required">*</span></label>
                <div class="service-categories-container">
                    <?php if (empty($available_services_by_category)): ?>
                        <p class="text-subtle">No more services to add or all services are already offered. You can manage existing ones.</p>
                    <?php else: ?>
                        <?php foreach ($available_services_by_category as $category_name => $services): ?>
                            <div class="service-category-group">
                                <h4><?= htmlspecialchars($category_name) ?></h4>
                                <div class="services-radio-grid">
                                    <?php foreach ($services as $service): ?>
                                        <div class="form-group-radio">
                                            <input type="radio"
                                                   id="service_<?= $service['id'] ?>"
                                                   name="service_id"
                                                   value="<?= $service['id'] ?>"
                                                   required>
                                            <label for="service_<?= $service['id'] ?>"><?= htmlspecialchars($service['service_name']) ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="price_min">Minimum Price (PKR)</label>
                    <input type="number" id="price_min" name="price_min" step="0.01" min="0" placeholder="e.g., 50000.00">
                </div>
                <div class="form-group">
                    <label for="price_max">Maximum Price (PKR)</label>
                    <input type="number" id="price_max" name="price_max" step="0.01" min="0" placeholder="e.g., 100000.00">
                </div>
            </div>

            <div class="form-group">
                <label for="description">Description / Offer Details</label>
                <textarea id="description" name="description" rows="5" placeholder="Describe this service offering, packages, or any special offers."></textarea>
            </div>

            <div class="form-group">
                <label for="images">Images for this Service Offering (Up to 10)</label>
                <input type="file" id="images" name="images[]" accept="image/*" multiple>
                <small class="text-muted">You can select multiple images to showcase this service. Max 10 images allowed.</small>
                <div class="image-preview-grid" id="image-preview-grid"></div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Add Service Offering</button>
                <a href="<?= BASE_URL ?>public/vendor_manage_services.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const imagesInput = document.getElementById('images');
            const imagePreviewGrid = document.getElementById('image-preview-grid');
            const maxImages = 10;

            imagesInput.addEventListener('change', function() {
                imagePreviewGrid.innerHTML = ''; // Clear previous previews
                const files = this.files;

                if (files.length > maxImages) {
                    alert(`You can only upload a maximum of ${maxImages} images.`);
                    this.value = ''; // Clear selected files
                    return;
                }

                if (files) {
                    Array.from(files).forEach(file => {
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            const previewItem = document.createElement('div');
                            previewItem.className = 'image-preview-item';
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            previewItem.appendChild(img);
                            imagePreviewGrid.appendChild(previewItem);
                        };
                        reader.readAsDataURL(file);
                    });
                }
            });

            // Basic client-side validation before submission
            document.querySelector('form').addEventListener('submit', function(event) {
                const selectedService = document.querySelector('input[name="service_id"]:checked');
                if (!selectedService) {
                    alert('Please select a service from the list.');
                    event.preventDefault();
                }

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