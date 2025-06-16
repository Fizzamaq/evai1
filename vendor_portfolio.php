<?php
// public/vendor_portfolio.php
require_once '../includes/config.php'; // Includes session_start() and database connection
require_once '../classes/User.class.php'; // Needed for default-avatar.jpg path, or if you display reviewer info
require_once '../classes/Vendor.class.php'; // Crucial for fetching vendor data and adding/updating/deleting portfolio items
require_once '../classes/UploadHandler.class.php'; // Include UploadHandler for image uploads

include 'header.php'; // Include the main site header (This is the only inclusion here)

// Ensure user is logged in and is a vendor
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

$user = new User($pdo); // Pass PDO connection to User class
$vendor = new Vendor($pdo); // Pass PDO connection to Vendor class
$uploader = new UploadHandler(); // Instantiate UploadHandler

$user_data = $user->getUserById($_SESSION['user_id']); // Fetch general user data
$vendor_data = $vendor->getVendorByUserId($_SESSION['user_id']); // Fetch vendor-specific data

// If vendor profile is not found, redirect to complete it.
if (!$vendor_data) {
    $_SESSION['error_message'] = "Vendor profile not found. Please complete your vendor registration.";
    header('Location: ' . BASE_URL . 'public/edit_profile.php');
    exit();
}

// Handle form submissions for adding portfolio items
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_portfolio_item'])) {
    $image_url = null;

    try {
        // Handle file upload for portfolio image
        if (isset($_FILES['portfolio_image_upload']) && $_FILES['portfolio_image_upload']['error'] === UPLOAD_ERR_OK) {
            $uploaded_filename = $uploader->handleUpload($_FILES['portfolio_image_upload'], 'vendors/');
            $image_url = 'assets/uploads/vendors/' . $uploaded_filename; // Path relative to BASE_URL
        } else if (isset($_FILES['portfolio_image_upload']) && $_FILES['portfolio_image_upload']['error'] !== UPLOAD_ERR_NO_FILE) {
            // Throw an exception if there was an upload error, but no file was provided
            throw new Exception("File upload error: " . $_FILES['portfolio_image_upload']['error']);
        }

        // Prepare portfolio data
        $portfolio_data = [
            'title' => trim($_POST['portfolio_title']),
            'description' => trim($_POST['portfolio_description'] ?? ''),
            'event_type_id' => !empty($_POST['portfolio_event_type_id']) ? (int)$_POST['portfolio_event_type_id'] : null,
            'image_url' => $image_url,
            'video_url' => trim($_POST['portfolio_video_url'] ?? ''), 
            'project_date' => !empty($_POST['portfolio_project_date']) ? $_POST['portfolio_project_date'] : null,
            'client_testimonial' => trim($_POST['portfolio_testimonial'] ?? ''),
            'is_featured' => isset($_POST['portfolio_is_featured']) ? 1 : 0
        ];

        // Add portfolio item using the Vendor class
        if ($vendor->addPortfolioItem($vendor_data['id'], $portfolio_data)) {
            $_SESSION['success_message'] = 'Portfolio item added successfully!';
        } else {
            $_SESSION['error_message'] = 'Failed to add portfolio item. Database error.';
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Upload/Save Error: ' . $e->getMessage();
    }

    // Redirect to the same page to prevent form re-submission on refresh
    header('Location: ' . BASE_URL . 'public/vendor_portfolio.php');
    exit();
}

// Handle form submissions for updating portfolio items
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_portfolio_item'])) {
    $item_id = (int)$_POST['item_id'];
    $current_image_url = trim($_POST['current_image_url'] ?? ''); // Hidden field for current image path
    $new_image_url = null;

    try {
        // Handle new file upload for portfolio image
        if (isset($_FILES['edit_portfolio_image_upload']) && $_FILES['edit_portfolio_image_upload']['error'] === UPLOAD_ERR_OK) {
            $uploaded_filename = $uploader->handleUpload($_FILES['edit_portfolio_image_upload'], 'vendors/');
            $new_image_url = 'assets/uploads/vendors/' . $uploaded_filename;
            // Optionally delete old image if new one is uploaded
            if (!empty($current_image_url) && strpos($current_image_url, 'assets/uploads/vendors/') === 0) {
                $old_filename = basename($current_image_url);
                $uploader->deleteFile($old_filename, 'vendors/');
            }
        } else if (isset($_FILES['edit_portfolio_image_upload']) && $_FILES['edit_portfolio_image_upload']['error'] !== UPLOAD_ERR_NO_FILE) {
            throw new Exception("File upload error for new image: " . $_FILES['edit_portfolio_image_upload']['error']);
        }
        
        // Use new image URL if uploaded, otherwise retain current
        $final_image_url = ($new_image_url !== null) ? $new_image_url : $current_image_url;


        $portfolio_data = [
            'title' => trim($_POST['edit_portfolio_title']),
            'description' => trim($_POST['edit_portfolio_description'] ?? ''),
            'event_type_id' => !empty($_POST['edit_portfolio_event_type_id']) ? (int)$_POST['edit_portfolio_event_type_id'] : null,
            'image_url' => $final_image_url, // Use the determined image URL
            'video_url' => trim($_POST['edit_portfolio_video_url'] ?? ''), 
            'project_date' => !empty($_POST['edit_portfolio_project_date']) ? $_POST['edit_portfolio_project_date'] : null,
            'client_testimonial' => trim($_POST['edit_portfolio_testimonial'] ?? ''),
            'is_featured' => isset($_POST['edit_portfolio_is_featured']) ? 1 : 0
        ];

        // Update portfolio item using the Vendor class
        if ($vendor->updatePortfolioItem($item_id, $vendor_data['id'], $portfolio_data)) {
            $_SESSION['success_message'] = 'Portfolio item updated successfully!';
        } else {
            $_SESSION['error_message'] = 'Failed to update portfolio item. Database error.';
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Update/Upload Error: ' . $e->getMessage();
    }

    header('Location: ' . BASE_URL . 'public/vendor_portfolio.php');
    exit();
}

// Handle form submissions for deleting portfolio items
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_portfolio_item'])) {
    $item_id = (int)$_POST['item_id'];
    $image_to_delete = trim($_POST['item_image_url'] ?? ''); // Get image URL to delete file

    try {
        if ($vendor->deletePortfolioItem($item_id, $vendor_data['id'])) {
            // If deletion from DB is successful, attempt to delete the physical image file
            if (!empty($image_to_delete) && strpos($image_to_delete, 'assets/uploads/vendors/') === 0) {
                $filename = basename($image_to_delete);
                $uploader->deleteFile($filename, 'vendors/');
            }
            $_SESSION['success_message'] = 'Portfolio item deleted successfully!';
        } else {
            $_SESSION['error_message'] = 'Failed to delete portfolio item.';
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Deletion Error: ' . $e->getMessage();
    }
    header('Location: ' . BASE_URL . 'public/vendor_portfolio.php');
    exit();
}


// Get vendor portfolio items for display
$portfolio_items = $vendor->getVendorPortfolio($vendor_data['id']);

// Get event types for dropdowns in portfolio forms (Add and Edit)
try {
    $event_types = dbFetchAll("SELECT id, type_name FROM event_types WHERE is_active = TRUE ORDER BY type_name ASC"); 
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
    <link rel="stylesheet" href="../assets/css/vendor_profile.css"> </head>
<body>
    <?php include 'header.php'; ?>

    <div class="portfolio-container">
        <div class="portfolio-header">
            <div>
                <h1>My Portfolio</h1>
                <p>Manage and view your public portfolio items</p>
            </div>
            <div>
                <a href="vendor_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                <button id="toggle-add-portfolio-form" class="btn btn-primary">Add New Portfolio Item</button>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert success"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert error"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
        <?php endif; ?>

        <div class="portfolio-form" id="add-portfolio-form" style="display: none;">
            <h2>Add New Portfolio Item</h2>
            <form method="POST" enctype="multipart/form-data">
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
                    <label for="portfolio_image_upload">Image File</label>
                    <input type="file" id="portfolio_image_upload" name="portfolio_image_upload" accept="image/*">
                </div>
                <div class="form-group">
                    <label for="portfolio_video_url">Video URL (e.g., YouTube link)</label>
                    <input type="url" id="portfolio_video_url" name="portfolio_video_url" placeholder="http://youtube.com/watch?v=...">
                </div>
                <div class="form-group">
                    <label for="portfolio_testimonial">Client Testimonial</label>
                    <textarea id="portfolio_testimonial" name="portfolio_testimonial" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <div class="featured-checkbox">
                        <input type="checkbox" id="portfolio_is_featured" name="portfolio_is_featured">
                        <label for="portfolio_is_featured">Feature this item in my profile</label>
                    </div>
                </div>
                <button type="submit" name="add_portfolio_item" class="btn btn-primary">Add Portfolio Item</button>
            </form>
        </div>

        <div class="portfolio-form" id="edit-portfolio-form" style="display: none;">
            <h2>Edit Portfolio Item</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" id="edit_item_id" name="item_id">
                <input type="hidden" id="current_image_url" name="current_image_url">

                <div class="form-group">
                    <label for="edit_portfolio_title">Item Title <span class="required">*</span></label>
                    <input type="text" id="edit_portfolio_title" name="edit_portfolio_title" required>
                </div>
                <div class="form-group">
                    <label for="edit_portfolio_description">Description</label>
                    <textarea id="edit_portfolio_description" name="edit_portfolio_description" rows="4"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_portfolio_event_type_id">Event Type</label>
                        <select id="edit_portfolio_event_type_id" name="edit_portfolio_event_type_id">
                            <option value="">Select event type</option>
                            <?php foreach ($event_types as $type): ?>
                                <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['type_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_portfolio_project_date">Project Date</label>
                        <input type="date" id="edit_portfolio_project_date" name="edit_portfolio_project_date">
                    </div>
                </div>
                <div class="form-group">
                    <label>Current Image</label>
                    <div id="current_portfolio_image_display"></div>
                    <label for="edit_portfolio_image_upload" style="margin-top: 10px;">Replace Image (Optional)</label>
                    <input type="file" id="edit_portfolio_image_upload" name="edit_portfolio_image_upload" accept="image/*">
                </div>
                <div class="form-group">
                    <label for="edit_portfolio_video_url">Video URL (e.g., YouTube link)</label>
                    <input type="url" id="edit_portfolio_video_url" name="edit_portfolio_video_url" placeholder="http://youtube.com/watch?v=...">
                </div>
                <div class="form-group">
                    <label for="edit_portfolio_testimonial">Client Testimonial</label>
                    <textarea id="edit_portfolio_testimonial" name="edit_portfolio_testimonial" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <div class="featured-checkbox">
                        <input type="checkbox" id="edit_portfolio_is_featured" name="edit_portfolio_is_featured">
                        <label for="edit_portfolio_is_featured">Feature this item in my profile</label>
                    </div>
                </div>
                <div class="form-actions-step" style="border-top: none; padding-top: 0;">
                    <button type="submit" name="update_portfolio_item" class="btn btn-primary">Update Portfolio Item</button>
                    <button type="button" id="cancel_edit_button" class="btn btn-secondary">Cancel Edit</button>
                </div>
            </form>
        </div>


        <div class="portfolio-items-display-section">
            <h2>Your Current Portfolio Items</h2>
            <?php if (empty($portfolio_items)): ?>
                <div class="empty-state">
                    <h3>No Portfolio Items Yet</h3>
                    <p>Click "Add New Portfolio Item" to showcase your work!</p>
                </div>
            <?php else: ?>
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
                                        <p class="testimonial-overlay">"<?= htmlspecialchars(substr($item['client_testimonial'], 0, 100)) ?><?= (strlen($item['client_testimonial']) > 100 ? '...' : '') ?>"</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="portfolio-description-content">
                                <h3><?= htmlspecialchars($item['title']) ?></h3>
                                <p><?= htmlspecialchars(substr($item['description'] ?? 'No description available.', 0, 150)) ?><?= (strlen($item['description'] ?? '') > 150 ? '...' : '') ?></p>
                                <div class="portfolio-meta-info">
                                    <?php if ($item['event_type_name']): ?>
                                        <span><i class="fas fa-tag"></i> <?= htmlspecialchars($item['event_type_name']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($item['project_date']): ?>
                                        <span><i class="fas fa-calendar-alt"></i> <?= date('M Y', strtotime($item['project_date'])); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="portfolio-actions" style="margin-top: 15px;">
                                    <button type="button" class="btn btn-sm btn-secondary edit-portfolio-item"
                                            data-id="<?= htmlspecialchars($item['id']) ?>"
                                            data-title="<?= htmlspecialchars($item['title']) ?>"
                                            data-description="<?= htmlspecialchars($item['description'] ?? '') ?>"
                                            data-event_type_id="<?= htmlspecialchars($item['event_type_id'] ?? '') ?>"
                                            data-image_url="<?= htmlspecialchars($item['image_url'] ?? '') ?>"
                                            data-video_url="<?= htmlspecialchars($item['video_url'] ?? '') ?>"
                                            data-project_date="<?= htmlspecialchars($item['project_date'] ?? '') ?>"
                                            data-client_testimonial="<?= htmlspecialchars($item['client_testimonial'] ?? '') ?>"
                                            data-is_featured="<?= htmlspecialchars($item['is_featured'] ?? 0) ?>">
                                        Edit
                                    </button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this portfolio item? This action cannot be undone.');">
                                        <input type="hidden" name="item_id" value="<?= htmlspecialchars($item['id']) ?>">
                                        <input type="hidden" name="item_image_url" value="<?= htmlspecialchars($item['image_url'] ?? '') ?>">
                                        <button type="submit" name="delete_portfolio_item" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleAddButton = document.getElementById('toggle-add-portfolio-form');
            const addPortfolioForm = document.getElementById('add-portfolio-form');
            const editPortfolioForm = document.getElementById('edit-portfolio-form');
            const cancelEditButton = document.getElementById('cancel_edit_button');
            const portfolioItemsContainer = document.querySelector('.portfolio-items-display-section');

            // Toggle Add Form Visibility
            if (toggleAddButton && addPortfolioForm) {
                toggleAddButton.addEventListener('click', function() {
                    // Hide edit form if shown
                    editPortfolioForm.style.display = 'none';

                    if (addPortfolioForm.style.display === 'none') {
                        addPortfolioForm.style.display = 'block';
                        toggleAddButton.textContent = 'Hide Add Form';
                        addPortfolioForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    } else {
                        addPortfolioForm.style.display = 'none';
                        toggleAddButton.textContent = 'Add New Portfolio Item';
                    }
                });
            }

            // Populate and Show Edit Form
            document.querySelectorAll('.edit-portfolio-item').forEach(button => {
                button.addEventListener('click', function() {
                    // Hide add form if shown
                    addPortfolioForm.style.display = 'none';
                    toggleAddButton.textContent = 'Add New Portfolio Item';

                    const itemId = this.dataset.id;
                    const title = this.dataset.title;
                    const description = this.dataset.description;
                    const eventTypeId = this.dataset.event_type_id;
                    const imageUrl = this.dataset.image_url;
                    const videoUrl = this.dataset.video_url;
                    const projectDate = this.dataset.project_date;
                    const clientTestimonial = this.dataset.client_testimonial;
                    const isFeatured = this.dataset.is_featured === '1';

                    // Populate the edit form fields
                    document.getElementById('edit_item_id').value = itemId;
                    document.getElementById('edit_portfolio_title').value = title;
                    document.getElementById('edit_portfolio_description').value = description;
                    document.getElementById('edit_portfolio_event_type_id').value = eventTypeId;
                    document.getElementById('edit_portfolio_video_url').value = videoUrl;
                    document.getElementById('edit_portfolio_project_date').value = projectDate;
                    document.getElementById('edit_portfolio_testimonial').value = clientTestimonial;
                    document.getElementById('edit_portfolio_is_featured').checked = isFeatured;
                    document.getElementById('current_image_url').value = imageUrl; // Set hidden field for current image path

                    const currentImageDisplay = document.getElementById('current_portfolio_image_display');
                    currentImageDisplay.innerHTML = ''; // Clear previous image
                    if (imageUrl) {
                        const imgElement = document.createElement('img');
                        imgElement.src = `<?= BASE_URL ?>${imageUrl}`; // Ensure correct base URL is prepended
                        imgElement.alt = "Current Portfolio Image";
                        imgElement.style.maxWidth = '100px';
                        imgElement.style.maxHeight = '100px';
                        imgElement.style.marginTop = '5px';
                        imgElement.style.borderRadius = '8px';
                        currentImageDisplay.appendChild(imgElement);
                    } else {
                        currentImageDisplay.innerHTML = '<p style="font-size: 0.9em; color: var(--text-subtle);">No current image.</p>';
                    }
                    
                    // Show the edit form
                    editPortfolioForm.style.display = 'block';
                    editPortfolioForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            });

            // Cancel Edit Button
            if (cancelEditButton) {
                cancelEditButton.addEventListener('click', function() {
                    editPortfolioForm.style.display = 'none';
                });
            }

            // Basic client-side validation for Add Portfolio form
            const addForm = addPortfolioForm.querySelector('form');
            if (addForm) {
                addForm.addEventListener('submit', function(e) {
                    const titleInput = document.getElementById('portfolio_title');
                    let isValid = true;
                    if (!titleInput.value.trim()) {
                        isValid = false;
                        titleInput.style.borderColor = 'var(--error-color)';
                        titleInput.focus();
                    } else {
                        titleInput.style.borderColor = 'var(--border-color)';
                    }
                    if (!isValid) e.preventDefault();
                });
            }

            // Basic client-side validation for Edit Portfolio form
            const editForm = editPortfolioForm.querySelector('form');
            if (editForm) {
                editForm.addEventListener('submit', function(e) {
                    const titleInput = document.getElementById('edit_portfolio_title');
                    let isValid = true;
                    if (!titleInput.value.trim()) {
                        isValid = false;
                        titleInput.style.borderColor = 'var(--error-color)';
                        titleInput.focus();
                    } else {
                        titleInput.style.borderColor = 'var(--border-color)';
                    }
                    // Validate image file type if a new one is selected
                    const imageUploadInput = document.getElementById('edit_portfolio_image_upload');
                    if (imageUploadInput && imageUploadInput.files.length > 0) {
                        const file = imageUploadInput.files[0];
                        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                        if (!allowedTypes.includes(file.type)) {
                            isValid = false;
                            alert('Invalid image file type. Only JPG, PNG, and GIF are allowed.');
                            imageUploadInput.value = ''; // Clear selected file
                        }
                        const maxSize = 2 * 1024 * 1024; // 2MB
                        if (file.size > maxSize) {
                            isValid = false;
                            alert('File too large. Maximum size is 2MB.');
                            imageUploadInput.value = ''; // Clear selected file
                        }
                    }

                    if (!isValid) e.preventDefault();
                });
            }
        });
    </script>
</body>
</html>
