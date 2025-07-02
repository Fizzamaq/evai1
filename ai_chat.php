<?php
// public/ai_chat.php
// TEMPORARY: Enable full error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../includes/config.php';
require_once '../includes/ai_functions.php'; // For AI_Assistant class
require_once '../classes/Event.class.php';   // For Event class (if needed for some helper function)
require_once '../classes/User.class.php';    // For User data
require_once '../classes/Vendor.class.php';  // For Vendor data (e.g., fetching categories/services)


// Retrieve user_id immediately after session_start and require authentication.
$user_id = $_SESSION['user_id'] ?? null;
$user_type = $_SESSION['user_type'] ?? null;

if (!$user_id) { // If user_id is not set or null, redirect/exit
    // For AJAX requests, send a JSON error response instead of redirecting
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'User not authenticated. Please log in.', 'redirect' => BASE_URL . 'public/login.php']);
        exit();
    } else {
        header("Location: " . BASE_URL . "public/login.php");
        exit();
    }
}

// NEW: Redirect vendors away from the AI chat page
if ($user_type == 2) { // Assuming 2 is the user_type_id for vendors
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Access denied. Vendors cannot use AI Assistant.', 'redirect' => BASE_URL . 'public/vendor_dashboard.php']);
    } else {
        $_SESSION['error_message'] = "Access denied. Vendors cannot use the AI Assistant.";
        header("Location: " . BASE_URL . "public/vendor_dashboard.php");
    }
    exit();
}

$ai_assistant = new AI_Assistant($pdo);
$event_class = new Event($pdo);
$vendor_class = new Vendor($pdo); // Instantiate Vendor class to fetch services and categories

$recommended_vendors = [];
$form_data = $_SESSION['ai_form_data'] ?? []; // Retain form data on reload/error
$form_errors = $_SESSION['ai_form_errors'] ?? []; // Retain errors
unset($_SESSION['ai_form_data'], $_SESSION['ai_form_errors']);

// Fetch necessary data for form dropdowns
$event_types = dbFetchAll("SELECT id, type_name FROM event_types WHERE is_active = TRUE ORDER BY type_name ASC");
// $all_services = dbFetchAll("SELECT id, service_name FROM vendor_services WHERE is_active = TRUE ORDER BY service_name ASC"); // No longer needed directly
$vendor_categories = $vendor_class->getAllVendorCategories(); // For grouping services

// Set minimum date for event_date to today
$min_event_date = date('Y-m-d');

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['get_recommendations'])) {
    $event_details_input = [
        'event_type_id' => filter_var($_POST['event_type_id'] ?? '', FILTER_VALIDATE_INT),
        'budget_min' => filter_var($_POST['budget_min'] ?? '', FILTER_VALIDATE_FLOAT),
        'budget_max' => filter_var($_POST['budget_max'] ?? '', FILTER_VALIDATE_FLOAT),
        'event_date' => $_POST['event_date'] ?? '',
        'location_string' => trim($_POST['location_string'] ?? ''),
        'service_ids' => $_POST['services'] ?? [] // Array of selected service IDs (now from checkboxes)
    ];

    $validation_errors = [];

    if (empty($event_details_input['event_type_id'])) {
        $validation_errors[] = "Event Type is required.";
    }
    if (empty($event_details_input['event_date'])) {
        $validation_errors[] = "Event Date is required.";
    } elseif (strtotime($event_details_input['event_date']) < strtotime('today')) {
        $validation_errors[] = "Event date cannot be in the past.";
    }
    if (empty($event_details_input['location_string'])) {
        $validation_errors[] = "Event Location is required.";
    }
    
    // Make budget fields compulsory and validate
    if (empty(trim($_POST['budget_min'])) || !is_numeric($event_details_input['budget_min'])) {
        $validation_errors[] = "Minimum Budget is required and must be a valid number.";
    }
    if (empty(trim($_POST['budget_max'])) || !is_numeric($event_details_input['budget_max'])) {
        $validation_errors[] = "Maximum Budget is required and must be a valid number.";
    }
    if (is_numeric($event_details_input['budget_min']) && is_numeric($event_details_input['budget_max']) && $event_details_input['budget_min'] > $event_details_input['budget_max']) {
        $validation_errors[] = "Minimum budget cannot be greater than maximum budget.";
    }

    if (empty($event_details_input['service_ids'])) {
        $validation_errors[] = "At least one preferred service is required.";
    }

    if (!empty($validation_errors)) {
        $_SESSION['ai_form_data'] = $_POST;
        $_SESSION['ai_form_errors'] = $validation_errors;
        header("Location: " . BASE_URL . "public/ai_chat.php");
        exit();
    }

    try {
        // Fetch full event type name for context
        $selected_event_type = dbFetch("SELECT type_name FROM event_types WHERE id = ?", [$event_details_input['event_type_id']]);
        
        // Prepare event data for AI Assistant
        $event_data_for_ai = [
            'event_type_name' => $selected_event_type['type_name'] ?? 'General Event',
            'budget_min' => $event_details_input['budget_min'],
            'budget_max' => $event_details_input['budget_max'],
            'event_date' => $event_details_input['event_date'],
            'location_string' => $event_details_input['location_string'],
            'service_ids' => $event_details_input['service_ids']
        ];

        $recommended_vendors = $ai_assistant->getVendorRecommendationsFromForm($event_data_for_ai);

    } catch (Exception $e) {
        $_SESSION['ai_form_data'] = $_POST;
        $_SESSION['ai_form_errors'] = ["An error occurred during recommendations: " . htmlspecialchars($e->getMessage())];
        header("Location: " . BASE_URL . "public/ai_chat.php");
        exit();
    }
}


// INCLUDE HEADER AND FOOTER ONLY FOR NON-AJAX REQUESTS (Normal page load)
include 'header.php';
?>
<div class="ai-recommendation-container">
    <div class="ai-form-section">
        <h1>Find Your Perfect Event Vendors with AI</h1>
        <p class="subtitle">Tell us about your event, and our AI will suggest the best vendors for your needs.</p>

        <?php if (!empty($form_errors)): ?>
            <div class="alert error">
                <ul>
                    <?php foreach ($form_errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>public/ai_chat.php" method="POST" class="ai-event-form">
            <div class="form-group">
                <label for="event_type_id">Event Type <span class="required">*</span></label>
                <select id="event_type_id" name="event_type_id" required>
                    <option value="">Select event type</option>
                    <?php foreach ($event_types as $type): ?>
                        <option value="<?= htmlspecialchars($type['id']) ?>"
                            <?= (isset($form_data['event_type_id']) && $form_data['event_type_id'] == $type['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($type['type_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="budget_min">Minimum Budget (PKR) <span class="required">*</span></label>
                    <input type="number" id="budget_min" name="budget_min" min="0" step="100" required
                           value="<?= htmlspecialchars($form_data['budget_min'] ?? '') ?>" placeholder="e.g., 5000">
                </div>
                <div class="form-group">
                    <label for="budget_max">Maximum Budget (PKR) <span class="required">*</span></label>
                    <input type="number" id="budget_max" name="budget_max" min="0" step="100" required
                           value="<?= htmlspecialchars($form_data['budget_max'] ?? '') ?>" placeholder="e.g., 10000">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="event_date">Event Date <span class="required">*</span></label>
                    <input type="date" id="event_date" name="event_date" required
                           value="<?= htmlspecialchars($form_data['event_date'] ?? '') ?>" min="<?= $min_event_date ?>">
                </div>
                <div class="form-group">
                    <label for="location_string">Event Location <span class="required">*</span></label>
                    <input type="text" id="location_string" name="location_string" required
                           value="<?= htmlspecialchars($form_data['location_string'] ?? '') ?>"
                           placeholder="e.g., Lahore, Pakistan or The Grand Ballroom">
                </div>
            </div>

            <div class="form-group">
                <label>Preferred Services <span class="required">*</span></label>
                <div class="services-checkbox-grid">
                    <?php
                    $selected_services_from_form = $form_data['services'] ?? [];
                    foreach ($vendor_categories as $category): ?>
                        <div class="service-category-group">
                            <h4><?= htmlspecialchars($category['category_name']) ?></h4>
                            <div class="checkbox-group-inner">
                                <?php
                                // Filter all_services by current category_id
                                $stmt_category_services = $pdo->prepare("SELECT id, service_name FROM vendor_services WHERE category_id = ? AND is_active = TRUE ORDER BY service_name ASC");
                                $stmt_category_services->execute([$category['id']]);
                                $services_in_category = $stmt_category_services->fetchAll(PDO::FETCH_ASSOC);

                                foreach ($services_in_category as $service): ?>
                                    <div class="checkbox-item">
                                        <input type="checkbox"
                                               id="service_<?= htmlspecialchars($service['id']) ?>"
                                               name="services[]"
                                               value="<?= htmlspecialchars($service['id']) ?>"
                                               <?= in_array($service['id'], $selected_services_from_form) ? 'checked' : '' ?>>
                                        <label for="service_<?= htmlspecialchars($service['id']) ?>"><?= htmlspecialchars($service['service_name']) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (empty($vendor_categories)): ?>
                    <p class="text-subtle">No service categories found.</p>
                <?php endif; ?>
            </div>

            <button type="submit" name="get_recommendations" class="btn btn-primary btn-large">Get Recommendations</button>
        </form>
    </div>

    <?php if (!empty($recommended_vendors)): ?>
        <div class="ai-recommendations-results">
            <h2>Top Recommended Vendors</h2>
            <p class="subtitle">Based on your preferences, here are the best matches:</p>

            <div class="vendor-recommendations-grid">
                <?php foreach ($recommended_vendors as $vendor_item): ?>
                    <div class="vendor-card-item">
                        <div class="vendor-card-image" style="background-image: url('<?= ASSETS_PATH ?>uploads/users/<?= htmlspecialchars($vendor_item['profile_image'] ?: 'default-avatar.jpg') ?>')"></div>
                        <div class="vendor-card-content">
                            <h3><?= htmlspecialchars($vendor_item['business_name']) ?></h3>
                            <p class="vendor-city"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($vendor_item['business_city']) ?></p>
                            <div class="vendor-card-rating">
                                <?php
                                $rating = round($vendor_item['rating'] * 2) / 2;
                                for ($i = 1; $i <= 5; $i++):
                                    if ($rating >= $i) { echo '<i class="fas fa-star"></i>'; }
                                    elseif ($rating > ($i - 1) && $rating < $i) { echo '<i class="fas fa-star-half-alt"></i>'; }
                                    else { echo '<i class="far fa-star"></i>'; }
                                endfor;
                                ?>
                                <span><?= number_format($vendor_item['rating'], 1) ?> (<?= $vendor_item['total_reviews'] ?> Reviews)</span>
                            </div>
                            <?php if (!empty($vendor_item['offered_services_names'])): ?>
                                <p class="vendor-services">Services: <?= htmlspecialchars($vendor_item['offered_services_names']) ?></p>
                            <?php endif; ?>
                            <a href="<?= BASE_URL ?>public/vendor_profile.php?id=<?= htmlspecialchars($vendor_item['id']) ?>" class="btn btn-sm btn-secondary">View Profile</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (empty($recommended_vendors)): ?>
                <div class="empty-state">No vendors found matching your criteria. Try adjusting your preferences.</div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
