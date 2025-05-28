<?php
require_once '../includes/config.php';
require_once '../includes/ai_functions.php';
require_once '../classes/Event.class.php'; // Required for createEvent or similar
require_once '../classes/Vendor.class.php'; // For vendor name lookup in suggestions
include 'header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "public/login.php");
    exit();
}

$ai = new AI_Assistant($pdo);
$suggestions = [];
$error = null;
$eventTypesForForm = dbFetchAll("SELECT id, type_name FROM event_types"); // Get event types for form
$vendorServices = dbFetchAll("SELECT id, service_name FROM vendor_services"); // For service name lookup
$serviceMap = array_column($vendorServices, 'service_name', 'id');


// Pre-fill form from POST data if there was a previous submission with errors
$eventType = $_POST['event_type'] ?? '';
$guestCount = $_POST['guest_count'] ?? '';
$budget = $_POST['budget'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eventType = $_POST['event_type'] ?? '';
    $guestCount = $_POST['guest_count'] ?? 0;
    $budget = $_POST['budget'] ?? 0;

    if (empty($eventType) || !is_numeric($guestCount) || $guestCount <= 0 || !is_numeric($budget) || $budget <= 0) {
        $error = "Please provide valid Event Type, Guest Count, and Budget.";
    } else {
        try {
            $prompt = "I want to plan a {$eventType} event for {$guestCount} guests with a budget of \${$budget}. Suggest suitable event details and required services, also estimate costs for each service. Respond in JSON.";
            $aiResponse = $ai->generateEventRecommendation($prompt); // This returns formatted event data with services

            if ($aiResponse && isset($aiResponse['services'])) {
                $suggestions = [];
                foreach ($aiResponse['services'] as $service) {
                    $serviceName = $serviceMap[$service['service_id']] ?? 'Unknown Service'; // Lookup service name
                    $suggestions[] = [
                        'name' => 'Suggested Vendor for ' . $serviceName, // Placeholder vendor name
                        'service' => $serviceName,
                        'cost' => $service['budget'] ?? rand(1000, 5000) // Use AI budget or random
                    ];
                }
                // You could also store the AI response in a session or a temp table
                // to allow the user to easily create an event from these suggestions.
            } else {
                $error = "AI could not generate detailed suggestions. Please try a different prompt.";
            }
        } catch (Exception $e) {
            $error = "An AI error occurred: " . $e->getMessage();
        }
    }
}
?>
<div class="ai-chat-container">
    <h2>AI Event Planning Assistant</h2>

    <?php if ($error): ?>
        <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" class="ai-chat-form">
        <div class="form-group">
            <label>Event Type</label>
            <select name="event_type" required>
                <option value="">Select event type</option>
                <?php foreach($eventTypesForForm as $type): ?>
                    <option value="<?= htmlspecialchars($type['type_name']) ?>" <?= ($eventType == $type['type_name']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($type['type_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Expected Guest Count</label>
                <input type="number" name="guest_count" min="1" required value="<?= htmlspecialchars($guestCount) ?>">
            </div>
            <div class="form-group">
                <label>Approximate Budget ($)</label>
                <input type="number" name="budget" min="100" required value="<?= htmlspecialchars($budget) ?>">
            </div>
        </div>

        <button type="submit" class="btn">Get Recommendations</button>
    </form>

    <?php if (!empty($suggestions)): ?>
    <div class="ai-results">
        <h3>AI Suggestions (for Services & Estimated Costs)</h3>
        <div class="vendor-cards">
            <?php foreach ($suggestions as $vendor): ?>
            <div class="vendor-card">
                <h4><?= htmlspecialchars($vendor['name']) ?></h4>
                <p>Service: <?= htmlspecialchars($vendor['service']) ?></p>
                <p>Estimated Cost: $<?= number_format($vendor['cost'], 2) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <p style="margin-top: 20px; text-align: center; color: #636e72;">
            These are AI-generated estimates. For actual event planning, proceed to create an event and find real vendors.
        </p>
    </div>
    <?php endif; ?>
</div>
<?php include 'footer.php'; ?>