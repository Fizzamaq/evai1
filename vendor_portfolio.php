<?php
// public/vendor_portfolio.php
// session_start(); // Removed: handled by config.php
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
$uploader = new UploadHandler(); // Instantiate UploadHandler

$user_data = $user->getUserById($_SESSION['user_id']);
$vendor_data = $vendor->getVendorByUserId($_SESSION['user_id']);

if (!$vendor_data) {
    $_SESSION['error_message'] = "Vendor profile not found. Please complete your vendor registration.";
    // Redirect to vendor_dashboard.php, not the generic dashboard
    header('Location: ' . BASE_URL . 'public/vendor_dashboard.php'); 
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_portfolio_item'])) {
        $image_url = null;

        try {
            if (isset($_FILES['portfolio_image']) && $_FILES['portfolio_image']['error'] === UPLOAD_ERR_OK) {
                $uploaded_filename = $uploader->handleUpload($_FILES['portfolio_image'], 'vendors/');
                $image_url = 'assets/uploads/vendors/' . $uploaded_filename; // Path relative to BASE_URL
            }

            $portfolio_data = [
                'title' => trim($_POST['title']),
                'description' => trim($_POST['description'] ?? ''),
                'event_type_id' => !empty($_POST['event_type_id']) ? (int)$_POST['event_type_id'] : null,
                'image_url' => $image_url,
                'video_url' => trim($_POST['video_url'] ?? ''), 
                'project_date' => !empty($_POST['project_date']) ? $_POST['project_date'] : null,
                'client_testimonial' => trim($_POST['testimonial'] ?? ''),
                'is_featured' => isset($_POST['is_featured']) ? 1 : 0
            ];

            if ($vendor->addPortfolioItem($vendor_data['id'], $portfolio_data)) {
                $_SESSION['success_message'] = 'Portfolio item added successfully!';
            } else {
                $_SESSION['error_message'] = 'Failed to add portfolio item.';
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Upload/Save Error: ' . $e->getMessage();
        }

        header('Location: ' . BASE_URL . 'public/vendor_portfolio.php');
        exit();
    }
}

// Get vendor portfolio items
$portfolio_items = $vendor->getVendorPortfolio($vendor_data['id']);

// Get event types for dropdown
try {
    // Ensure dbFetchAll is available (from db.php included by config.php)
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
    <link rel="stylesheet" href="../assets/css/vendor.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="portfolio-container">
        <div class="portfolio-header">
            <div>
                <h1>My Portfolio</h1>
                <p>Showcase your best work to attract more clients</p>
            </div>
            <div>
                <a href="vendor_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert success"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert error"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
        <?php endif; ?>

        <?php if (empty($portfolio_items)): ?>
            <div class="empty-state">
                <h3>No Portfolio Items Yet</h3>
                <p>Add your first portfolio item to showcase your work</p>
            </div>
        <?php else: ?>
            <div class="portfolio-grid">
                <?php foreach ($portfolio_items as $item): ?>
                    <div class="portfolio-item">
                        <?php if ($item['image_url']): ?>
                            <div class="portfolio-image" style="background-image: url('<?= BASE_URL . htmlspecialchars($item['image_url']) ?>')"></div>
                        <?php else: ?>
                            <div class="portfolio-image" style="background-image: url('<?= ASSETS_PATH ?>images/default-portfolio.jpg')">
                                <i class="fas fa-image" style="font-size: 3em; color: var(--text-subtle);"></i>
                            </div>
                        <?php endif; ?>

                        <div class="portfolio-content">
                            <div class="portfolio-title"><?php echo htmlspecialchars($item['title']); ?></div>

                            <div class="portfolio-meta">
                                <?php if ($item['event_type_name']): ?>
                                    <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($item['event_type_name']); ?></span>
                                <?php endif; ?>
                                <?php if ($item['project_date']): ?>
                                    <span><i class="fas fa-calendar-alt"></i> <?php echo date('M Y', strtotime($item['project_date'])); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="portfolio-description">
                                <?php echo htmlspecialchars(substr($item['description'] ?? 'No description available', 0, 120)); ?>
                                <?php if (strlen($item['description'] ?? '') > 120): ?>...<?php endif; ?>
                            </div>

                            <?php if ($item['client_testimonial']): ?>
                                <div class="portfolio-testimonial">
                                    "<?php echo htmlspecialchars(substr($item['client_testimonial'], 0, 100)); ?>"
                                    <?php if (strlen($item['client_testimonial']) > 100): ?>...<?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="portfolio-form">
            <h2>Add New Portfolio Item</h2>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group">
                        <label for="title">Title <span class="required">*</span></label>
                        <input type="text" id="title" name="title" required>
                    </div>

                    <div class="form-group">
                        <label for="event_type_id">Event Type</label>
                        <select id="event_type_id" name="event_type_id">
                            <option value="">Select event type</option>
                            <?php foreach ($event_types as $type): ?>
                                <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['type_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="4"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="project_date">Project Date</label>
                        <input type="date" id="project_date" name="project_date">
                    </div>

                    <div class="form-group">
                        <label for="portfolio_image">Image</label>
                        <input type="file" id="portfolio_image" name="portfolio_image" accept="image/*">
                    </div>
                </div>

                <div class="form-group">
                    <label for="video_url">Video URL (e.g., YouTube link)</label>
                    <input type="url" id="video_url" name="video_url" placeholder="http://youtube.com/watch?v=...">
                </div>

                <div class="form-group">
                    <label for="testimonial">Client Testimonial</label>
                    <textarea id="testimonial" name="testimonial" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <div class="featured-checkbox">
                        <input type="checkbox" id="is_featured" name="is_featured">
                        <label for="is_featured">Feature this item in my profile</label>
                    </div>
                </div>

                <button type="submit" name="add_portfolio_item" class="btn btn-primary">Add Portfolio Item</button>
            </form>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        // Simple form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.portfolio-form form');

            form.addEventListener('submit', function(e) {
                const titleInput = document.getElementById('title');
                let isValid = true;

                if (!titleInput.value.trim()) {
                    isValid = false;
                    // Replaced alert with a more user-friendly visual cue
                    titleInput.style.borderColor = 'var(--error-color)';
                    titleInput.focus();
                    const errorMessage = document.createElement('p');
                    errorMessage.style.color = 'var(--error-color)';
                    errorMessage.textContent = 'Title is required.';
                    titleInput.parentNode.insertBefore(errorMessage, titleInput.nextSibling);
                } else {
                    titleInput.style.borderColor = 'var(--border-color)'; // Reset border
                    const existingError = titleInput.parentNode.querySelector('p[style*="color: var(--error-color)"]');
                    if (existingError) existingError.remove();
                }

                if (!isValid) {
                    e.preventDefault();
                }
            });

            // No image preview logic provided, but leaving the event listener example
            const imageInput = document.getElementById('portfolio_image');
            if (imageInput) {
                imageInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        console.log('File selected:', file.name);
                    }
                });
            }
        });
    </script>
</body>
</html>
