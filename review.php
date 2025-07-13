<?php
session_start();
require_once '../includes/config.php';
require_once '../classes/Booking.class.php';
require_once '../classes/Review.class.php';

$booking = new Booking($pdo); // Pass PDO
$review = new Review($pdo);   // Pass PDO

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

$booking_id = $_GET['booking_id'] ?? null;
$booking_details = null;
if ($booking_id) {
    $booking_details = $booking->getUserBooking($_SESSION['user_id'], $booking_id);
}

// Verify valid booking for review
if (!$booking_details || $booking_details['status'] !== 'completed' || ($booking_details['is_reviewed'] ?? false)) {
    $_SESSION['error'] = "Invalid booking, booking not completed, or already reviewed.";
    header('Location: ' . BASE_URL . 'public/dashboard.php');
    exit();
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic validation
    if (empty($_POST['rating']) || empty($_POST['title']) || empty($_POST['content']) ||
        empty($_POST['service_quality']) || empty($_POST['communication']) || empty($_POST['value_for_money'])) {
        $_SESSION['error'] = "Please fill in all required review fields and ratings.";
        header('Location: ' . $_SERVER['REQUEST_URI']); // Redirect back to form
        exit();
    }

    $review_data = [
        'booking_id' => $booking_id,
        'reviewer_id' => $_SESSION['user_id'],
        'reviewed_id' => $booking_details['vendor_id'], // Reviewed is the vendor's user ID
        'rating' => (int)$_POST['rating'],
        'review_title' => trim($_POST['title']),
        'review_content' => trim($_POST['content']),
        'service_quality' => (int)$_POST['service_quality'],
        'communication' => (int)$_POST['communication'],
        'value_for_money' => (int)$_POST['value_for_money'],
        'would_recommend' => isset($_POST['recommend']) ? 1 : 0
    ];

    if ($review->submitReview($review_data)) {
        $_SESSION['success'] = "Review submitted successfully!";
        header('Location: ' . BASE_URL . 'public/booking.php?id=' . $booking_id);
        exit();
    } else {
        $_SESSION['error'] = "Failed to submit review. Please try again.";
    }
    header('Location: ' . $_SERVER['REQUEST_URI']); // Redirect back to form on error
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Review - EventCraftAI</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .review-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .rating-stars {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            direction: rtl; /* For right-to-left star display */
            justify-content: flex-end; /* Align stars to the right for RTL */
        }

        .star-input {
            display: none;
        }

        .star-label {
            cursor: pointer;
            font-size: 2em;
            color: #ddd;
            transition: color 0.2s;
        }

        .star-input:checked ~ .star-label,
        .star-label:hover,
        .star-label:hover ~ .star-label {
            color: #ffd700;
        }

        .rating-category {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        /* Alert messages */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="review-container">
        <h1>Review <?php echo htmlspecialchars($booking_details['business_name']); ?></h1>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Overall Rating</label>
                <div class="rating-stars">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <input type="radio" id="star<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" class="star-input" required>
                        <label for="star<?php echo $i; ?>" class="star-label">★</label>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="rating-category">
                <h3>Service Quality</h3>
                <div class="rating-stars">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <input type="radio" id="sq<?php echo $i; ?>" name="service_quality" value="<?php echo $i; ?>" required>
                        <label for="sq<?php echo $i; ?>" class="star-label">★</label>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="rating-category">
                <h3>Communication</h3>
                <div class="rating-stars">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <input type="radio" id="com<?php echo $i; ?>" name="communication" value="<?php echo $i; ?>" required>
                        <label for="com<?php echo $i; ?>" class="star-label">★</label>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="rating-category">
                <h3>Value for Money</h3>
                <div class="rating-stars">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <input type="radio" id="vfm<?php echo $i; ?>" name="value_for_money" value="<?php echo $i; ?>" required>
                        <label for="vfm<?php echo $i; ?>" class="star-label">★</label>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="form-group">
                <label>Review Title</label>
                <input type="text" name="title" required maxlength="200">
            </div>

            <div class="form-group">
                <label>Detailed Review</label>
                <textarea name="content" rows="5" required></textarea>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="recommend"> Would you recommend this vendor?
                </label>
            </div>

            <button type="submit" class="btn btn-primary">Submit Review</button>
        </form>
    </div>

    <script>
        // Star rating interaction
        document.querySelectorAll('.rating-stars .star-label').forEach(label => {
            label.addEventListener('click', (e) => {
                const input = e.target.previousElementSibling;
                const group = input.name;
                // Select all labels in the same group and update their color
                document.querySelectorAll(`.rating-stars input[name="${group}"] + label`).forEach(starLabel => {
                    starLabel.style.color = '#ddd'; // Reset all to grey
                });
                // Color stars up to the selected one
                let currentLabel = e.target;
                while (currentLabel) {
                    currentLabel.style.color = '#ffd700';
                    currentLabel = currentLabel.nextElementSibling; // Go to the next (lower value) star in RTL
                }
            });
        });

        // Set initial star ratings if values are pre-filled (e.g., on form error)
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.rating-stars').forEach(group => {
                const checkedInput = group.querySelector('.star-input:checked');
                if (checkedInput) {
                    let currentLabel = checkedInput.nextElementSibling;
                    while (currentLabel) {
                        currentLabel.style.color = '#ffd700';
                        currentLabel = currentLabel.nextElementSibling;
                    }
                }
            });
        });
    </script>
</body>
</html>
