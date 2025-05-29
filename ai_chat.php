<?php
// TEMPORARY: Enable full error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../includes/config.php';
require_once '../includes/ai_functions.php'; // This includes AI_Assistant class
require_once '../classes/Event.class.php'; 
require_once '../classes/Vendor.class.php'; 
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

// Debugging output for initial form data
echo '<pre style="background: #e0f2f7; padding: 10px; border: 1px solid #b2e0ed; margin-bottom: 10px;">';
echo 'DEBUG: Initial Form Data:<br>';
echo 'Event Type: ' . htmlspecialchars($eventType) . '<br>';
echo 'Guest Count: ' . htmlspecialchars($guestCount) . '<br>';
echo 'Budget: ' . htmlspecialchars($budget) . '<br>';
echo '</pre>';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Re-assign from POST for clarity within this block
    $eventType = $_POST['event_type'] ?? '';
    $guestCount = $_POST['guest_count'] ?? 0;
    $budget = $_POST['budget'] ?? 0;

    echo '<pre style="background: #e6f7d9; padding: 10px; border: 1px solid #c9e2b1; margin-bottom: 10px;">';
    echo 'DEBUG: POST Request Detected.<br>';
    echo 'Event Type (POST): ' . htmlspecialchars($eventType) . '<br>';
    echo 'Guest Count (POST): ' . htmlspecialchars($guestCount) . '<br>';
    echo 'Budget (POST): ' . htmlspecialchars($budget) . '<br>';
    echo '</pre>';

    if (empty($eventType) || !is_numeric($guestCount) || $guestCount <= 0 || !is_numeric($budget) || $budget <= 0) {
        $error = "Please provide valid Event Type, Guest Count, and Budget.";
        echo '<pre style="background: #ffe0b2; padding: 10px; border: 1px solid #ffcc80; margin-bottom: 10px;">';
        echo 'DEBUG: Validation Failed. Error: ' . htmlspecialchars($error) . '<br>';
        echo '</pre>';
    } else {
        try {
            $prompt = "I want to plan a {$eventType} event for {$guestCount} guests with a budget of \${$budget}. Suggest suitable event details and required services, also estimate costs for each service. Respond in JSON.";
            
            echo '<pre style="background: #d1e7dd; padding: 10px; border: 1px solid #a3cfb7; margin-bottom: 10px;">';
            echo 'DEBUG: Prompt for AI: ' . htmlspecialchars($prompt) . '<br>';
            echo 'DEBUG: Calling AI Assistant...<br>';
            echo '</pre>';

            $aiResponse = $ai->generateEventRecommendation($prompt); // This returns formatted event data with services

            echo '<pre style="background: #d4edda; padding: 10px; border: 1px solid #c3e6cb; margin-bottom: 10px;">';
            echo 'DEBUG: AI Assistant Raw Response (before formatting):<br>';
            var_dump($aiResponse); // Dump the full AI response
            echo '</pre>';

            if ($aiResponse && isset($aiResponse['services'])) {
                $suggestions = [];
                foreach ($aiResponse['services'] as $service) {
                    $serviceName = $serviceMap[$service['service_id']] ?? 'Unknown Service';
                    $suggestions[] = [
                        'name' => 'Suggested Vendor for ' . $serviceName, 
                        'service' => $serviceName,
                        'cost' => $service['budget'] ?? rand(1000, 5000) 
                    ];
                }
                echo '<pre style="background: #d1ecf1; padding: 10px; border: 1px solid #bee5eb; margin-bottom: 10px;">';
                echo 'DEBUG: Formatted Suggestions:<br>';
                var_dump($suggestions);
                echo '</pre>';
            } else {
                $error = "AI could not generate detailed suggestions. Please try a different prompt or check AI response structure.";
                echo '<pre style="background: #ffe0b2; padding: 10px; border: 1px solid #ffcc80; margin-bottom: 10px;">';
                echo 'DEBUG: AI Response Issue. Error: ' . htmlspecialchars($error) . '<br>';
                echo '</pre>';
            }
        } catch (Exception $e) {
            $error = "An AI error occurred: " . $e->getMessage();
            echo '<pre style="background: #f8d7da; padding: 10px; border: 1px solid #f5c6cb; margin-bottom: 10px;">';
            echo 'DEBUG: Exception Caught. Error: ' . htmlspecialchars($e->getMessage()) . '<br>';
            echo 'DEBUG: Exception Trace: ' . htmlspecialchars($e->getTraceAsString()) . '<br>';
            echo '</pre>';
        }
    }
}
?>
<div class="ai-chat-container">
    <h2>AI Event Planning Assistant</h2>

    <?php if (isset($error)): // Only display error if it's set and not empty ?>
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
