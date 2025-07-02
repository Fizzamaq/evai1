<?php
// public/vendor_profile.php
session_start();
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
// The getVendorPortfolio method now provides 'main_image_url'
$portfolio_items = $vendor->getVendorPortfolio($vendor_profile['id']);

// Fetch vendor's reviews
$vendor_reviews = $review->getReviewsForEntity($vendor_profile['user_id'], 'vendor');

// Fetch logged-in user's events for chat initiation
$user_events = [];
if (isset($_SESSION['user_id'])) {
    $user_events = $event->getUserEvents($_SESSION['user_id']);
}

// Fetch vendor's service offerings, now including their packages
$vendor_service_offerings_raw = $vendor->getVendorServices($vendor_profile['id']);

// Group services by category for display, and attach packages to each
// This variable will hold ALL service offerings, grouped by category
$vendor_services_grouped = [];
foreach ($vendor_service_offerings_raw as $service_offering) {
    // getServiceOfferingById fetches the offering and its packages
    $full_service_offering = $vendor->getServiceOfferingById($offering['id'], $vendor_profile['id']); // Changed variable from $offering to $service_offering
    if ($full_service_offering) {
        $vendor_services_grouped[$full_service_offering['category_name']][] = $full_service_offering;
    }
}

// This variable will hold ONLY service offerings that have actual packages, grouped by category
$vendor_services_grouped_with_packages = [];
foreach ($vendor_services_grouped as $category_name => $services_in_category) {
    $filtered_services_for_category = [];
    foreach ($services_in_category as $service_offering) {
        if (!empty($service_offering['packages'])) {
            $filtered_services_for_category[] = $service_offering;
        }
    }
    if (!empty($filtered_services_for_category)) {
        $vendor_services_grouped_with_packages[$category_name] = $filtered_services_for_category;
    }
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
    <style>
        /* New styles for service packages on public profile */
        .service-category-section {
            margin-bottom: var(--spacing-lg);
        }
        .service-category-section h4 {
            font-size: 1.5em;
            color: var(--text-dark);
            margin-bottom: var(--spacing-md);
            border-bottom: 1px dashed var(--border-color);
            padding-bottom: var(--spacing-sm);
        }
        .service-packages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: var(--spacing-md);
        }
        .service-package-card {
            background: var(--background-light);
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            padding: var(--spacing-md);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border: 1px solid var(--light-grey-border);
        }
        .service-package-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .service-package-card h5 {
            font-size: 1.2em;
            color: var(--primary-color);
            margin-top: 0;
            margin-bottom: var(--spacing-xs);
        }
        .service-package-card .package-price {
            font-size: 1.1em;
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: var(--spacing-sm);
        }
        .service-package-card .package-description {
            font-size: 0.9em;
            color: var(--text-subtle);
            line-height: 1.4;
            margin-bottom: var(--spacing-md);
            flex-grow: 1;
        }
        .service-package-card .package-images {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: var(--spacing-sm);
            margin-bottom: var(--spacing-md);
        }
        .service-package-card .package-images img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
            border: 1px solid var(--border-color);
        }
        .service-package-card .package-images .image-count-overlay {
            width: 60px;
            height: 60px;
            border-radius: 5px;
            background: rgba(0,0,0,0.6);
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 0.9em;
        }

        .service-package-card .action-button {
            margin-top: auto; /* Push button to bottom */
            text-align: center;
            border-top: 1px dashed var(--border-color);
            padding-top: var(--spacing-md);
        }
        .service-package-card .action-button .btn {
            width: 100%;
            padding: 10px 15px;
            font-size: 0.9em;
        }
    </style>
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
                    <span><?= number_format($vendor_profile['rating'], 1) ?? 'N/A' ?> (<?= htmlspecialchars($vendor_profile['total_reviews'] ?? 0) ?> reviews)</span>
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
                    <p><strong>Overall Budget Range:</strong> $<?= number_format($vendor_profile['min_budget'], 0) ?> - $<?= number_format($vendor_profile['max_budget'], 0) ?></p>
                <?php endif; ?>
                
                <?php if (!empty($vendor_services_grouped)): ?>
                    <div class="services-offered-summary">
                        <p><strong>Services Offered:</strong></p>
                        <?php 
                        $all_service_names = [];
                        foreach ($vendor_services_grouped as $category_name => $services):
                            foreach ($services as $service_offering_item):
                                $all_service_names[] = htmlspecialchars($service_offering_item['service_name']);
                            endforeach;
                        endforeach;
                        // Implode and echo the service names, or display as a list
                        echo '<span>' . implode(', ', $all_service_names) . '</span>';
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="profile-section service-deals-section">
            <h2>Service Deals</h2>
            <?php if (!empty($vendor_services_grouped_with_packages)): // Display only if there are services with packages ?>
                <?php foreach ($vendor_services_grouped_with_packages as $category_name => $services_in_category): ?>
                    <div class="service-category-section">
                        <h4><?= htmlspecialchars($category_name) ?></h4>
                        <?php foreach ($services_in_category as $service_offering): ?>
                            <h5 style="margin-top: var(--spacing-sm); margin-bottom: var(--spacing-xs); color: var(--text-dark);">
                                <?= htmlspecialchars($service_offering['service_name']) ?>
                            </h5>
                            <?php if (!empty($service_offering['description'])): ?>
                                <p style="font-size: 0.9em; color: var(--text-subtle); margin-bottom: var(--spacing-md);">
                                    <?= nl2br(htmlspecialchars($service_offering['description'])) ?>
                                </p>
                            <?php endif; ?>

                            <?php if (!empty($service_offering['packages'])): // This condition should always be true due to $vendor_services_grouped_with_packages filtering ?>
                                <div class="service-packages-grid">
                                    <?php foreach ($service_offering['packages'] as $package): ?>
                                        <div class="service-package-card">
                                            <div>
                                                <h5><?= htmlspecialchars($package['package_name']) ?></h5>
                                                <p class="package-price">
                                                    PKR <?= number_format($package['price_min'] ?? 0, 0) ?> - 
                                                    PKR <?= number_format($package['price_max'] ?? 0, 0) ?>
                                                </p>
                                                <p class="package-description">
                                                    <?= nl2br(htmlspecialchars(substr($package['package_description'] ?? 'No description.', 0, 150))) ?><?= (strlen($package['package_description'] ?? '') > 150 ? '...' : '') ?>
                                                </p>
                                                <?php if (!empty($package['images'])): ?>
                                                    <div class="package-images">
                                                        <?php foreach (array_slice($package['images'], 0, 3) as $index => $img): ?>
                                                            <img src="<?= BASE_URL . htmlspecialchars($img['image_url']) ?>" alt="<?= htmlspecialchars($package['package_name']) ?> Image <?= $index + 1 ?>">
                                                        <?php endforeach; ?>
                                                        <?php if (count($package['images']) > 3): ?>
                                                            <span class="image-count-overlay">+<?= count($package['images']) - 3 ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="action-button">
                                                <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 1): // Only customer can book ?>
                                                    <a href="<?= BASE_URL ?>public/book_vendor.php?vendor_id=<?= htmlspecialchars($vendor_profile['id']) ?>&service_offering_id=<?= htmlspecialchars($service_offering['id']) ?>&package_id=<?= htmlspecialchars($package['id']) ?>" class="btn btn-primary">Book This Package</a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; // End of checking $service_offering['packages'] ?>
                            <hr style="border-top: 1px dashed var(--border-color); margin: var(--spacing-md) 0;">
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: // If no services have packages, display this message ?>
                <p>No service deals currently available from this vendor.</p>
            <?php endif; ?>
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
                            <a href="<?= BASE_URL ?>public/view_portfolio_item.php?id=<?= htmlspecialchars($item['id']) ?>" class="portfolio-item-link-area">
                                <div class="portfolio-image-wrapper">
                                    <?php if (!empty($item['main_image_url'])): ?>
                                        <img src="<?= BASE_URL . htmlspecialchars($item['main_image_url']) ?>" alt="<?= htmlspecialchars($item['title']) ?>">
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
                                            <p class="testimonial-overlay">"<?= htmlspecialchars(substr($item['client_testimonial'] ?? '', 0, 100)) ?><?= (strlen($item['client_testimonial'] ?? '') > 100 ? '...' : '') ?>"</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="portfolio-description-content">
                                    <h3><?= htmlspecialchars($item['title']) ?></h3>
                                    <p><?= htmlspecialchars(substr($item['description'] ?? 'No description available.', 0, 150)) ?><?= (strlen($item['description'] ?? '') > 150 ? '...' : '') ?></p>
                                    <?php if (!empty($item['project_charges'])): ?>
                                        <p class="project-charges-summary">Charges: PKR <?= number_format($item['project_charges'], 2) ?></p>
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
            <h2>Reviews (<?= htmlspecialchars($vendor_profile['total_reviews'] ?? 0) ?>)</h2>
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
                            <p><?= nl2br(htmlspecialchars(substr($review_item['review_content'] ?? '', 0, 200))) ?><?= (strlen($review_item['review_content'] ?? '') > 200 ? '...' : '') ?></p>
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
                    // ADDED: Restrict calendar navigation to current and future dates
                    validRange: {
                        start: '<?= date('Y-m-d') ?>' // Sets the start of the valid range to today
                    },
                    events: function(fetchInfo, successCallback, failureCallback) {
                        fetch(`<?= BASE_URL ?>public/availability.php?vendor_id=${vendorProfileId}&start=${fetchInfo.startStr}&end=${fetchInfo.endStr}`)
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error(`HTTP error! status: ${response.status}`);
                                }
                                return response.json();
                            })
                            .then(data => {
                                const formattedEvents = data.map(event => {
                                    // These colors are defined in vendor_profile.css as `background-color` with `!important`
                                    // and will be applied by FullCalendar
                                    return {
                                        id: event.id,
                                        title: event.status.charAt(0).toUpperCase() + event.status.slice(1), // Display the status
                                        start: event.date + 'T' + event.start_time, // Full datetime string
                                        end: event.date + 'T' + event.end_time,     // Full datetime string
                                        allDay: false,
                                        // REMOVED: display: 'background', This makes events render as foreground events
                                        // color: eventColor, // FullCalendar will use eventColor for background, or use CSS classes
                                        className: `fc-event-${event.status}`, // Add class for specific styling
                                        extendedProps: {
                                            status: event.status
                                        }
                                    };
                                });
                                successCallback(formattedEvents);
                            })
                            .catch(error => {
                                console.error('Error fetching availability:', error);
                                // Optionally display an error message on the dashboard
                                const calendarContainer = document.getElementById('public-availability-calendar');
                                if (calendarContainer) {
                                    calendarContainer.innerHTML = '<p class="text-subtle">Failed to load calendar. Please try again.</p>';
                                }
                                failureCallback(error);
                            });
                    },
                    // MODIFIED: eventContent to display the title and use CSS classes for styling
                    eventContent: function(arg) {
                        // Create a div with the event title inside. FullCalendar will apply the classNames
                        // from the event object (e.g., fc-event-available) which are defined in CSS.
                        return { html: `<div class="fc-event-status-text">${arg.event.title}</div>` };
                    },
                    selectable: false,
                    editable: false,
                    // MODIFIED: eventClick to link to book_vendor.php with prefill_date if status is 'available'
                    eventClick: function(info) {
                        info.jsEvent.preventDefault();
                        const clickedDate = info.event.startStr.slice(0, 10);
                        // Check if the clicked event's status is 'available'
                        if (info.event.extendedProps.status === 'available') {
                             // Construct the URL with vendor_id, prefill_date.
                             // Removed service_offering_id and package_id from here
                             const bookUrl = `<?= BASE_URL ?>public/book_vendor.php?vendor_id=<?= htmlspecialchars($vendor_profile['id']) ?>&prefill_date=${clickedDate}`;
                             window.location.href = bookUrl;
                        } else {
                            // Optionally, alert the user or change the "Book Now" button if the date is not available
                            alert(`This date is ${info.event.extendedProps.status}. Please choose an available date.`);
                        }
                    }
                });
                calendar.render();
            }
        });
    </script>
</body>
</html>
