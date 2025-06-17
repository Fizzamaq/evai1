<?php
// public/vendor_profile.php
require_once '../includes/config.php';
require_once '../classes/User.class.php';
require_once '../classes/Vendor.class.php';
require_once '../classes/Review.class.php';
require_once '../classes/Event.class.php';

include 'header.php';

// Ensure vendor ID is provided in the URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid vendor ID provided.";
    header('Location: ' . BASE_URL . 'public/index.php');
    exit();
}

$vendor_id = (int)$_GET['id'];

$vendor = new Vendor($pdo);
$review = new Review($pdo);
$event = new Event($pdo);

// Fetch main vendor profile data including user and user_profile data
$vendor_profile = $vendor->getVendorProfileById($vendor_id);

if (!$vendor_profile) {
    $_SESSION['error_message'] = "Vendor not found.";
    header('Location: ' . BASE_URL . 'public/index.php');
    exit();
}

// Fetch vendor's portfolio items
$portfolio_items = $vendor->getVendorPortfolio($vendor_profile['id']);

// Fetch vendor's reviews
$vendor_reviews = $review->getReviewsForEntity($vendor_profile['user_id'], 'vendor');

// Fetch logged-in user's events for chat initiation
$user_events = [];
if (isset($_SESSION['user_id'])) {
    $user_events = $event->getUserEvents($_SESSION['user_id']);
}
$vendor_profile_id_js = $vendor_profile['id'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($vendor_profile['business_name']) ?>'s Profile - EventCraftAI</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/vendor_profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
</head>
<body>
    <div class="vendor-profile-container">
        <div class="profile-header-section">
            <div class="profile-avatar">
                <?php if (!empty($vendor_profile['profile_image'])): ?>
                    <img src="<?= BASE_URL ?>assets/uploads/users/<?= htmlspecialchars($vendor_profile['profile_image']) ?>" alt="<?= htmlspecialchars($vendor_profile['business_name']) ?> Profile Picture">
                <?php else: ?>
                    <img src="<?= BASE_URL ?>assets/images/default-avatar.jpg" alt="Default Avatar">
                <?php endif; ?>
            </div>
            <div class="profile-info-main">
                <h1><?= htmlspecialchars($vendor_profile['business_name']) ?></h1>
                <p class="tagline">Providing exceptional services for your events.</p>
                <div class="rating-display">
                    <?php
                    $rating = round($vendor_profile['rating'] * 2) / 2;
                    for ($i = 1; $i <= 5; $i++):
                        if ($rating >= $i) {
                            echo '<i class="fas fa-star"></i>';
                        } elseif ($rating > ($i - 1) && $rating < $i) {
                            echo '<i class="fas fa-star-half-alt"></i>';
                        } else {
                            echo '<i class="far fa-star"></i>';
                        }
                    endfor;
                    ?>
                    <span><?= number_format($vendor_profile['rating'], 1) ?? 'N/A' ?> (<?= $vendor_profile['total_reviews'] ?? 0 ?> reviews)</span>
                </div>
                <div class="contact-buttons">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="<?= BASE_URL ?>public/chat.php?vendor_id=<?= htmlspecialchars($vendor_profile['user_id']) ?>" class="btn btn-primary">
                            <i class="fas fa-comment"></i> Message Vendor
                        </a>
                        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 1): ?>
                            <a href="<?= BASE_URL ?>public/book_vendor.php?vendor_id=<?= htmlspecialchars($vendor_profile['id']) ?>" class="btn btn-success" id="book-now-btn">
                                <i class="fas fa-calendar-check"></i> Book Now
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>public/login.php" class="btn btn-primary">Login to Message</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="profile-section">
            <h2>Business Details</h2>
            <div class="details-grid">
                <p><strong>Location:</strong> <?= htmlspecialchars($vendor_profile['business_city']) ?>, <?= htmlspecialchars($vendor_profile['business_state']) ?>, <?= htmlspecialchars($vendor_profile['business_country']) ?></p>
                <?php if (!empty($vendor_profile['business_address'])): ?>
                    <p><strong>Address:</strong> <?= htmlspecialchars($vendor_profile['business_address']) ?>, <?= htmlspecialchars($vendor_profile['business_postal_code']) ?></p>
                <?php endif; ?>
                <?php if (!empty($vendor_profile['website'])): ?>
                    <p><strong>Website:</strong> <a href="<?= htmlspecialchars($vendor_profile['website']) ?>" target="_blank"><?= htmlspecialchars($vendor_profile['website']) ?></a></p>
                <?php endif; ?>
                <?php if (!empty($vendor_profile['experience_years'])): ?>
                    <p><strong>Experience:</strong> <?= htmlspecialchars($vendor_profile['experience_years']) ?> Years</p>
                <?php endif; ?>
                <?php if (!empty($vendor_profile['min_budget']) && !empty($vendor_profile['max_budget'])): ?>
                    <p><strong>Budget Range:</strong> $<?= number_format($vendor_profile['min_budget'], 0) ?> - $<?= number_format($vendor_profile['max_budget'], 0) ?></p>
                <?php endif; ?>
                <?php if (!empty($vendor_profile['offered_services_names'])): ?>
                    <p><strong>Services:</strong> <?= htmlspecialchars($vendor_profile['offered_services_names']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_type'] == 1): ?>
        <div class="profile-section availability-section">
            <h2>Availability Overview</h2>
            <div id="public-availability-calendar"></div>
            <div class="calendar-legend">
                <span class="legend-item"><span class="legend-color available-color"></span> Available</span>
                <span class="legend-item"><span class="legend-color booked-color"></span> Booked</span>
                <span class="legend-item"><span class="legend-color blocked-color"></span> Blocked</span>
                <span class="legend-item"><span class="legend-color holiday-color"></span> Holiday</span>
            </div>
        </div>
        <?php endif; ?>

        <div class="profile-section">
            <h2>Portfolio</h2>
            <?php if (!empty($portfolio_items)): ?>
                <div class="portfolio-grid">
                    <?php foreach ($portfolio_items as $item): ?>
                        <div class="portfolio-item-card">
                            <a href="<?= BASE_URL ?>public/view_portfolio_item.php?id=<?= $item['id'] ?>" class="portfolio-item-link-area">
                                <div class="portfolio-image-wrapper">
                                    <?php if (!empty($item['image_url'])): ?>
                                        <img src="<?= BASE_URL ?>assets/uploads/vendors/<?= htmlspecialchars(basename($item['image_url'])) ?>" alt="<?= htmlspecialchars($item['title']) ?>">
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
                                            <p class="testimonial-overlay">"<?= htmlspecialchars(substr($item['client_testimonial'], 0, 100)) ?><?= (strlen($item['client_testimonial']) > 100) ? '...' : '' ?>"</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="portfolio-description-content">
                                    <h3><?= htmlspecialchars($item['title']) ?></h3>
                                    <p><?= htmlspecialchars(substr($item['description'] ?? 'No description available.', 0, 150)) ?><?= (strlen($item['description'] ?? '') > 150) ? '...' : '' ?></p>
                                    <?php if (!empty($item['project_charges'])): ?>
                                        <p class="project-charges-summary">Charges: PKR <?= number_format($item['project_charges'], 0) ?></p>
                                    <?php endif; ?>
                                    <div class="portfolio-meta-info">
                                        <?php if (!empty($item['event_type_name'])): ?>
                                            <span><i class="fas fa-tag"></i> <?= htmlspecialchars($item['event_type_name']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($item['project_date'])): ?>
                                            <span><i class="fas fa-calendar-alt"></i> <?= date('M Y', strtotime($item['project_date'])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No portfolio items added yet.</p>
            <?php endif; ?>
        </div>

        <div class="profile-section">
            <h2>Reviews (<?= $vendor_profile['total_reviews'] ?? 0 ?>)</h2>
            <?php if (!empty($vendor_reviews)): ?>
                <div class="reviews-list">
                    <?php foreach ($vendor_reviews as $review_item): ?>
                        <div class="review-card">
                            <div class="reviewer-info">
                                <div class="reviewer-avatar">
                                    <?php if (!empty($review_item['profile_image'])): ?>
                                        <img src="<?= BASE_URL ?>assets/uploads/users/<?= htmlspecialchars($review_item['profile_image']) ?>" alt="Reviewer Avatar">
                                    <?php else: ?>
                                        <img src="<?= BASE_URL ?>assets/images/default-avatar.jpg" alt="Default Avatar">
                                    <?php endif; ?>
                                </div>
                                <span class="reviewer-name"><?= htmlspecialchars($review_item['first_name'] ?? 'Anonymous') ?></span>
                                <span class="review-date"><?= date('M j, Y', strtotime($review_item['created_at'])) ?></span>
                            </div>
                            <div class="review-rating-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?= ($review_item['rating'] >= $i) ? 'filled' : '' ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <h3><?= htmlspecialchars($review_item['review_title']) ?></h3>
                            <p><?= nl2br(htmlspecialchars($review_item['review_content'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No reviews for this vendor yet.</p>
            <?php endif; ?>
        </div>

    </div>

    <?php include 'footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('public-availability-calendar');
            const bookNowBtn = document.getElementById('book-now-btn');
            const vendorProfileId = <?= json_encode($vendor_profile_id_js) ?>;

            if (calendarEl) {
                const calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: ''
                    },
                    events: function(fetchInfo, successCallback, failureCallback) {
                        fetch(`<?= BASE_URL ?>public/availability.php?vendor_id=${vendorProfileId}&start=${fetchInfo.startStr}&end=${fetchInfo.endStr}`)
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error('Network response was not ok');
                                }
                                return response.json();
                            })
                            .then(data => {
                                const formattedEvents = data.map(event => {
                                    let eventColor = '';
                                    if (event.status === 'available') {
                                        eventColor = '#4CAF50';
                                    } else if (event.status === 'booked') {
                                        eventColor = '#F44336';
                                    } else if (event.status === 'blocked') {
                                        eventColor = '#B0BEC5';
                                    } else if (event.status === 'holiday') {
                                        eventColor = '#FF9800';
                                    }

                                    return {
                                        id: event.id,
                                        title: '',
                                        start: event.date + 'T' + event.start_time,
                                        end: event.date + 'T' + event.end_time,
                                        allDay: false,
                                        display: 'background',
                                        color: eventColor,
                                        extendedProps: {
                                            status: event.status
                                        }
                                    };
                                });
                                successCallback(formattedEvents);
                            })
                            .catch(error => {
                                console.error('Error fetching availability:', error);
                                // The message display removal was requested by the user previously.
                                // const calendarContainer = document.getElementById('public-availability-calendar');
                                // if (calendarContainer) {
                                //     calendarContainer.innerHTML = '<p class="text-subtle">Failed to load calendar. Please try again.</p>';
                                // }
                                failureCallback(error);
                            });
                    },
                    eventContent: function(arg) {
                        return { html: `<div></div>` };
                    },
                    selectable: false,
                    editable: false,
                    eventClick: function(info) {
                        info.jsEvent.preventDefault();
                        const clickedDate = info.event.startStr.slice(0, 10);
                        if (bookNowBtn && info.event.extendedProps.status === 'available') {
                            bookNowBtn.href = `<?= BASE_URL ?>public/book_vendor.php?vendor_id=${vendorProfileId}&prefill_date=${clickedDate}`;
                        } else if (bookNowBtn) {
                            bookNowBtn.href = `<?= BASE_URL ?>public/book_vendor.php?vendor_id=${vendorProfileId}`;
                        }
                    }
                });
                calendar.render();
            }
        });
    </script>
</body>
</html>
