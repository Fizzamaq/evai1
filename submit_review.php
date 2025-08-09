<?php
session_start();
require_once '../includes/config.php';
require_once '../classes/Booking.class.php';
require_once '../classes/User.class.php';
require_once '../classes/Review.class.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'public/login.php');
    exit();
}

$booking_id = $_GET['booking_id'] ?? null;

if (!$booking_id || !is_numeric($booking_id)) {
    $_SESSION['error_message'] = "Invalid booking ID provided.";
    header('Location: ' . BASE_URL . 'public/events.php');
    exit();
}

$booking_obj = new Booking($pdo);
$booking_details = $booking_obj->getBooking((int)$booking_id);

if (!$booking_details || $booking_details['user_id'] != $_SESSION['user_id']) {
    $_SESSION['error_message'] = "Access denied or booking not found.";
    header('Location: ' . BASE_URL . 'public/events.php');
    exit();
}

// Check if the booking has already been reviewed
if ($booking_details['is_reviewed'] == 1) {
    $_SESSION['error_message'] = "This booking has already been reviewed.";
    header('Location: ' . BASE_URL . 'public/events.php');
    exit();
}

// Check if the event date has passed
if (strtotime($booking_details['service_date']) >= time()) {
    $_SESSION['error_message'] = "You can only review a vendor after the event date has passed.";
    header('Location: ' . BASE_URL . 'public/events.php');
    exit();
}

// Get vendor and service information for display
$vendor_obj = new Vendor($pdo);
$vendor_info = $vendor_obj->getVendorProfileByUserId($booking_details['vendor_id']);
$service_name = $booking_details['service_name'];

include 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Review - EventCraftAI</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .review-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 40px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .review-container h1 {
            color: var(--primary-color);
            text-align: center;
            margin-bottom: 20px;
        }
        .review-container p {
            text-align: center;
            color: var(--text-subtle);
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 25px;
        }
        .rating-stars {
            text-align: center;
            margin-bottom: 20px;
        }
        .rating-stars .fa-star {
            font-size: 2em;
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s;
        }
        .rating-stars .fa-star.active,
        .rating-stars .fa-star:hover {
            color: #FFD700;
        }
        .rating-stars .fa-star.active ~ .fa-star {
            color: #ddd;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="review-container">
        <h1>Submit a Review</h1>
        <p>Your feedback helps the community. Please rate your experience with **<?= htmlspecialchars($vendor_info['business_name'] ?? 'Vendor') ?>** for the service **<?= htmlspecialchars($service_name ?? 'N/A') ?>**.</p>
        
        <form id="reviewForm">
            <input type="hidden" name="booking_id" value="<?= htmlspecialchars($booking_id) ?>">
            <input type="hidden" name="vendor_id" value="<?= htmlspecialchars($booking_details['vendor_id']) ?>">
            <input type="hidden" id="ratingInput" name="rating" value="0">

            <div class="form-group rating-stars">
                <i class="fas fa-star" data-rating="1"></i>
                <i class="fas fa-star" data-rating="2"></i>
                <i class="fas fa-star" data-rating="3"></i>
                <i class="fas fa-star" data-rating="4"></i>
                <i class="fas fa-star" data-rating="5"></i>
            </div>
            
            <div class="form-group">
                <label for="reviewText">Your Review:</label>
                <textarea id="reviewText" name="review_text" rows="6" placeholder="Share your experience..." required></textarea>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary btn-large">Submit Review</button>
            </div>
        </form>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const stars = document.querySelectorAll('.rating-stars .fa-star');
            const ratingInput = document.getElementById('ratingInput');
            const reviewForm = document.getElementById('reviewForm');
            let currentRating = 0;

            stars.forEach(star => {
                star.addEventListener('click', () => {
                    const rating = star.dataset.rating;
                    currentRating = rating;
                    ratingInput.value = rating;
                    stars.forEach(s => {
                        if (s.dataset.rating <= rating) {
                            s.classList.add('active');
                        } else {
                            s.classList.remove('active');
                        }
                    });
                });
            });

            reviewForm.addEventListener('submit', async function(e) {
                e.preventDefault();

                if (currentRating === 0) {
                    alert('Please select a star rating.');
                    return;
                }

                const formData = new FormData(reviewForm);
                const reviewData = Object.fromEntries(formData.entries());

                try {
                    const response = await fetch('<?= BASE_URL ?>api/submit_review.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(reviewData)
                    });

                    const result = await response.json();

                    if (result.success) {
                        alert(result.message);
                        window.location.href = '<?= BASE_URL ?>public/events.php';
                    } else {
                        alert(result.message || 'Failed to submit review.');
                    }
                } catch (error) {
                    console.error('Error submitting review:', error);
                    alert('An error occurred. Please try again.');
                }
            });
        });
    </script>
</body>
</html>
