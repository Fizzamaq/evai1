<?php
session_start();
require_once '../includes/config.php';
require_once '../classes/User.class.php';
require_once '../classes/Event.class.php'; // Required for fetching event types if from DB

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

$user = new User($pdo); // Pass PDO
$event = new Event($pdo); // Pass PDO to fetch event types for dropdown
$user_data = $user->getUserById($_SESSION['user_id']);

// Fetch event types from database
$event_types_from_db = dbFetchAll("SELECT id, type_name FROM event_types WHERE is_active = TRUE");

// Handle form errors and data persistence after redirect
$form_errors = $_SESSION['form_errors'] ?? [];
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_data']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Event - EventCraftAI</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .create-event-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .form-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-bottom: 20px;
        }

        .form-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e1e5e9;
        }

        .form-header h1 {
            color: #2d3436;
            margin-bottom: 10px;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section h3 {
            color: #2d3436;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2d3436;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 6px;
            transition: background-color 0.2s;
        }

        .checkbox-item:hover {
            background-color: #f8f9fa;
        }

        .checkbox-item input[type="checkbox"] {
            width: auto;
            margin: 0;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }

        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .ai-suggestion {
            background: linear-gradient(135deg, #fd79a8 0%, #fdcb6e 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
        }

        .ai-suggestion h3 {
            margin-bottom: 10px;
        }

        .required {
            color: #e74c3c;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="create-event-container">
        <div class="ai-suggestion">
            <h3>🤖 Want AI Help?</h3>
            <p>Let our AI assistant help you plan your event through conversation!</p>
            <a href="ai_chat.php" class="btn" style="background: rgba(255,255,255,0.2); margin-top: 10px;">Try AI Assistant</a>
        </div>

        <div class="form-card">
            <div class="form-header">
                <h1>Create New Event</h1>
                <p>Fill in the details below to start planning your event</p>
            </div>

            <?php if (!empty($form_errors)): ?>
                <div class="alert alert-error">
                    <ul>
                        <?php foreach ($form_errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="process_event.php" method="POST">
                <div class="form-section">
                    <h3>📋 Basic Information</h3>

                    <div class="form-group">
                        <label for="title">Event Title <span class="required">*</span></label>
                        <input type="text" id="title" name="title" required 
                            value="<?= htmlspecialchars($form_data['title'] ?? '') ?>"
                            placeholder="e.g., Sarah's Birthday Party">
                    </div>

                    <div class="form-group">
                        <label for="description">Event Description</label>
                        <textarea id="description" name="description" 
                            placeholder="Describe your event, theme, or any special requirements..."><?= htmlspecialchars($form_data['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="event_type_id">Event Type <span class="required">*</span></label>
                            <select id="event_type_id" name="event_type_id" required>
                                <option value="">Select event type</option>
                                <?php foreach ($event_types_from_db as $type): ?>
                                    <option value="<?= htmlspecialchars($type['id']) ?>"
                                        <?= (isset($form_data['event_type_id']) && $form_data['event_type_id'] == $type['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($type['type_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="status">Event Status</label>
                            <select id="status" name="status">
                                <option value="planning" <?= (isset($form_data['status']) && $form_data['status'] == 'planning') ? 'selected' : '' ?>>Planning</option>
                                <option value="active" <?= (isset($form_data['status']) && $form_data['status'] == 'active') ? 'selected' : '' ?>>Active</option>
                                <option value="completed" <?= (isset($form_data['status']) && $form_data['status'] == 'completed') ? 'selected' : '' ?>>Completed</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>📅 Date & Time</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="event_date">Event Date <span class="required">*</span></label>
                            <input type="date" id="event_date" name="event_date" required 
                                value="<?= htmlspecialchars($form_data['event_date'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label for="event_time">Event Time</label>
                            <input type="time" id="event_time" name="event_time"
                                value="<?= htmlspecialchars($form_data['event_time'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="end_date">End Date (optional)</label>
                            <input type="date" id="end_date" name="end_date"
                                value="<?= htmlspecialchars($form_data['end_date'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label for="end_time">End Time (optional)</label>
                            <input type="time" id="end_time" name="end_time"
                                value="<?= htmlspecialchars($form_data['end_time'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="duration">Expected Duration (hours)</label>
                        <input type="number" id="duration" name="duration" min="1" max="24" 
                            value="<?= htmlspecialchars($form_data['duration'] ?? '') ?>"
                            placeholder="e.g., 4">
                    </div>
                </div>

                <div class="form-section">
                    <h3>📍 Location & Guests</h3>

                    <div class="form-group">
                        <label for="location_string">Full Event Location (e.g., 123 Main St, Anytown, State)</label>
                        <input type="text" id="location_string" name="location_string" 
                            value="<?= htmlspecialchars($form_data['location_string'] ?? '') ?>"
                            placeholder="e.g., 123 Main St, City, State or Venue Name">
                    </div>

                    <div class="form-group">
                        <label for="venue_name">Venue Name (optional)</label>
                        <input type="text" id="venue_name" name="venue_name" 
                            value="<?= htmlspecialchars($form_data['venue_name'] ?? '') ?>"
                            placeholder="e.g., The Grand Ballroom">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="venue_address">Venue Address</label>
                            <input type="text" id="venue_address" name="venue_address" 
                                value="<?= htmlspecialchars($form_data['venue_address'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="venue_city">City</label>
                            <input type="text" id="venue_city" name="venue_city" 
                                value="<?= htmlspecialchars($form_data['venue_city'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="venue_state">State</label>
                            <input type="text" id="venue_state" name="venue_state" 
                                value="<?= htmlspecialchars($form_data['venue_state'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="venue_country">Country</label>
                            <input type="text" id="venue_country" name="venue_country" 
                                value="<?= htmlspecialchars($form_data['venue_country'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="venue_postal_code">Postal Code</label>
                        <input type="text" id="venue_postal_code" name="venue_postal_code" 
                            value="<?= htmlspecialchars($form_data['venue_postal_code'] ?? '') ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="guest_count">Expected Guest Count</label>
                            <input type="number" id="guest_count" name="guest_count" min="1" 
                                value="<?= htmlspecialchars($form_data['guest_count'] ?? '') ?>"
                                placeholder="e.g., 50">
                        </div>

                        <div class="form-group">
                            <label for="budget_min">Minimum Budget ($)</label>
                            <input type="number" id="budget_min" name="budget_min" min="0" step="0.01" 
                                value="<?= htmlspecialchars($form_data['budget_min'] ?? '') ?>"
                                placeholder="e.g., 5000.00">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="budget_max">Maximum Budget ($)</label>
                        <input type="number" id="budget_max" name="budget_max" min="0" step="0.01" 
                            value="<?= htmlspecialchars($form_data['budget_max'] ?? '') ?>"
                            placeholder="e.g., 10000.00">
                    </div>
                </div>

                <div class="form-section">
                    <h3>🎯 Services Needed</h3>
                    <p>Select all services you might need for your event:</p>

                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" id="catering" name="services[]" value="1"
                                <?= (isset($form_data['services']) && in_array('1', $form_data['services'])) ? 'checked' : '' ?>>
                            <label for="catering">🍽️ Catering</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="photography" name="services[]" value="2"
                                <?= (isset($form_data['services']) && in_array('2', $form_data['services'])) ? 'checked' : '' ?>>
                            <label for="photography">📸 Photography</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="decoration" name="services[]" value="3"
                                <?= (isset($form_data['services']) && in_array('3', $form_data['services'])) ? 'checked' : '' ?>>
                            <label for="decoration">🎨 Decoration</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="music_dj" name="services[]" value="5"
                                <?= (isset($form_data['services']) && in_array('5', $form_data['services'])) ? 'checked' : '' ?>>
                            <label for="music_dj">🎵 Music/DJ</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="venue" name="services[]" value="6"
                                <?= (isset($form_data['services']) && in_array('6', $form_data['services'])) ? 'checked' : '' ?>>
                            <label for="venue">🏢 Venue</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="transportation" name="services[]" value="7"
                                <?= (isset($form_data['services']) && in_array('7', $form_data['services'])) ? 'checked' : '' ?>>
                            <label for="transportation">🚗 Transportation</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="entertainment" name="services[]" value="8"
                                <?= (isset($form_data['services']) && in_array('8', $form_data['services'])) ? 'checked' : '' ?>>
                            <label for="entertainment">🎭 Entertainment</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="flowers" name="services[]" value="3"
                                <?= (isset($form_data['services']) && in_array('3', $form_data['services'])) ? 'checked' : '' ?>>
                            <label for="flowers">🌸 Flowers</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="audio_visual" name="services[]" value="8"
                                <?= (isset($form_data['services']) && in_array('8', $form_data['services'])) ? 'checked' : '' ?>>
                            <label for="audio_visual">🔊 Audio/Visual Equipment</label>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3>📝 Additional Details</h3>

                    <div class="form-group">
                        <label for="special_requirements">Special Requirements or Notes</label>
                        <textarea id="special_requirements" name="special_requirements" 
                            placeholder="Any dietary restrictions, accessibility needs, theme preferences, etc..."><?= htmlspecialchars($form_data['special_requirements'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Create Event</button>
                    <a href="events.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        // Set minimum date to today
        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.getElementById('event_date');
            const today = new Date().toISOString().split('T')[0];
            dateInput.min = today;

            // Add form validation
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;

                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.style.borderColor = '#e74c3c';
                    } else {
                        field.style.borderColor = '#ddd';
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                }
            });
        });
    </script>
</body>
</html>