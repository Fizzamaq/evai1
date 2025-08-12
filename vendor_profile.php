<?php
// public/vendor_profile.php
session_start();
require_once '../includes/config.php';
require_once '../classes/User.class.php';
require_once '../classes/Vendor.class.php';
require_once '../classes/Review.class.php';
require_once '../classes/Event.class.php';

include 'header.php';

// TEMPORARY DEBUGGING: Enable full error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// --- DEBUGGING START ---
// Dump the value of $vendor_profile['id'] directly from PHP
// This output will appear at the very top of the HTML source.
// You might need to view page source (Ctrl+U or Cmd+Option+U) to see it clearly.
echo "";
// --- DEBUGGING END ---


if (!$vendor_profile) {
    $_SESSION['error_message'] = "Vendor not found.";
    header('Location: ' . BASE_URL . 'public/index.php'); // Changed to index.php for general error
    exit();
}

// NEW: Track vendor profile view if user is logged in and not viewing their own profile
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $vendor_profile['user_id']) {
    try {
        $stmt = $pdo->prepare("INSERT INTO user_vendor_views (user_id, vendor_profile_id) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user_id'], $vendor_profile['id']]);
    } catch (PDOException $e) {
        // Log the error but don't stop page execution for view tracking issues
        error_log("Error tracking vendor view for user " . $_SESSION['user_id'] . ": " . $e->getMessage());
    }
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
    // Corrected: Pass the actual service_offering_id and vendor_profile_id
    $full_service_offering = $vendor->getServiceOfferingById($service_offering['id'], $vendor_profile['id']);
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

// Ensure vendor_profile['id'] is used for JS, as availability is linked to vendor_profiles.id
// Add a fallback to 0 if $vendor_profile['id'] is not set or invalid, though the check above should prevent this.
$vendor_profile_id_for_js = isset($vendor_profile['id']) ? (int)$vendor_profile['id'] : 0;
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
            cursor: pointer; /* Make the whole card clickable */
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
            overflow: hidden; /* Ensure images don't overflow */
        }
        .service-package-card .package-images img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
            border: 1px solid var(--border-color);
            max-width: 100%; /* Ensure image doesn't exceed its container */
            height: auto; /* Maintain aspect ratio */
        }
        .service-package-card .package-images .image-count-overlay {
            width: 60px;
            height: 60px;
            border-radius: 50%; /* Changed to circle for count overlay */
            background: rgba(0,0,0,0.6);
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 0.9em;
            position: absolute; /* Position over the image */
            top: 0;
            left: 0;
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

        /* Lightbox for Service Package Details */
        .package-details-lightbox-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        .package-details-lightbox-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .package-details-lightbox-content {
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.6);
            width: 90%;
            max-width: 1000px; /* Adjust max-width as needed */
            height: 90%;
            max-height: 700px; /* Adjust max-height as needed */
            display: flex;
            overflow: hidden; /* Ensure content stays within bounds */
            position: relative;
        }
        .package-details-lightbox-left {
            flex: 1;
            padding: var(--spacing-lg);
            overflow-y: auto; /* Enable scrolling for text content */
            display: flex;
            flex-direction: column;
            justify-content: space-between; /* Push button to bottom */
        }
        .package-details-lightbox-right {
            flex: 1.5; /* Give more space to the image */
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background-color: #eee; /* Placeholder background */
            position: relative;
            overflow: hidden; /* Important for image carousel */
        }
        .package-details-lightbox-right img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain; /* Contain image within its area */
        }
        .package-details-lightbox-close {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 2.5em;
            color: #fff;
            cursor: pointer;
            z-index: 10001;
            background-color: rgba(0, 0, 0, 0.4);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: background-color 0.2s ease;
        }
        .package-details-lightbox-close:hover {
            background-color: rgba(255, 0, 0, 0.6);
        }
        .package-details-lightbox-nav-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            font-size: 3em;
            color: #fff;
            cursor: pointer;
            z-index: 10001;
            padding: 10px;
            background-color: rgba(0, 0, 0, 0.3);
            border-radius: 50%;
            transition: background-color 0.2s ease;
        }
        .package-details-lightbox-nav-arrow:hover {
            background-color: rgba(0, 0, 0, 0.5);
        }
        .package-details-lightbox-nav-prev {
            left: 20px;
        }
        .package-details-lightbox-nav-next {
            right: 20px;
        }
        .package-details-lightbox-left h3 {
            color: var(--primary-color);
            font-size: 1.8em;
            margin-top: 0;
            margin-bottom: var(--spacing-sm);
        }
        .package-details-lightbox-left .package-price-large {
            font-size: 1.5em;
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: var(--spacing-md);
        }
        .package-details-lightbox-left p {
            color: var(--text-dark);
            line-height: 1.6;
            margin-bottom: var(--spacing-md);
        }
        .package-details-lightbox-left .btn {
            margin-top: auto; /* Push button to bottom */
            width: 100%;
        }

        /* Calendar Lightbox Styles */
        .calendar-lightbox-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        .calendar-lightbox-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .calendar-lightbox-content {
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.6);
            width: 90%;
            max-width: 900px; /* Max width for calendar */
            height: 90%;
            max-height: 700px; /* Max height for calendar */
            padding: var(--spacing-md);
            position: relative;
            display: flex;
            flex-direction: column;
        }
        .calendar-lightbox-content h3 {
            text-align: center;
            color: var(--primary-color);
            margin-top: 0;
            margin-bottom: var(--spacing-md);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: var(--spacing-sm);
        }
        .calendar-lightbox-close {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 2.5em;
            color: #fff;
            cursor: pointer;
            z-index: 10001;
            background-color: rgba(0, 0, 0, 0.4);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: background-color 0.2s ease;
        }
        .calendar-lightbox-close:hover {
            background-color: rgba(255, 0, 0, 0.6);
        }
        #calendar-lightbox-instance { /* The actual FullCalendar instance inside the lightbox */
            flex-grow: 1; /* Allow calendar to take available height */
            min-height: 300px; /* Adjust as needed, or use a percentage if parent has fixed height */
        }


        @media (max-width: 768px) {
            .package-details-lightbox-content {
                flex-direction: column;
                height: 95%;
                max-height: 95%;
            }
            .package-details-lightbox-right {
                height: 50%; /* Image takes 50% height on mobile */
            }
            .package-details-lightbox-left {
                height: 50%; /* Text takes 50% height on mobile */
                padding: var(--spacing-md);
            }
            .package-details-lightbox-close,
            .package-details-lightbox-nav-arrow {
                font-size: 2em;
                width: 35px;
                height: 35px;
                top: 10px;
            }
            .package-details-lightbox-nav-prev {
                left: 10px;
            }
            .package-details-lightbox-nav-next {
                right: 10px;
            }

            .calendar-lightbox-content {
                width: 95%;
                height: 95%;
                max-width: 95%;
                max-height: 95%;
                padding: var(--spacing-sm);
            }
            .calendar-lightbox-close {
                font-size: 2em;
                width: 35px;
                height: 35px;
                top: 10px;
                right: 10px;
            }
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
                                        <div class="service-package-card" 
                                             data-package-name="<?= htmlspecialchars($package['package_name']) ?>"
                                             data-package-description="<?= htmlspecialchars($package['package_description']) ?>"
                                             data-package-price-min="<?= htmlspecialchars($package['price_min']) ?>"
                                             data-package-price-max="<?= htmlspecialchars($package['price_max']) ?>"
                                             data-package-images="<?= htmlspecialchars(json_encode(array_column($package['images'], 'image_url'))) ?>">
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


        <div class="profile-section availability-section">
            <h2>Availability Overview
            </h2>
            <div id="public-availability-calendar" style="cursor: pointer;"></div> <div class="calendar-legend">
                <span class="legend-item"><span class="legend-color available-color"></span> Available</span>
                <span class="legend-item"><span class="legend-color booked-color"></span> Booked</span>
                <span class="legend-item"><span class="legend-color blocked-color"></span> Blocked</span>
                <span class="legend-item"><span class="legend-color holiday-color"></span> Holiday</span>
            </div>
        </div>

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

    <div class="package-details-lightbox-overlay" id="packageDetailsLightbox">
        <div class="package-details-lightbox-content">
            <div class="package-details-lightbox-left">
                <h3 id="lightboxPackageName"></h3>
                <p class="package-price-large" id="lightboxPackagePrice"></p>
                <p id="lightboxPackageDescription"></p>
                <div class="action-button">
                    <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 1): ?>
                        <a href="#" id="lightboxBookButton" class="btn btn-primary">Book This Package</a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>public/login.php" class="btn btn-primary">Login to Book</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="package-details-lightbox-right">
                <img src="" alt="Package Image" id="lightboxPackageImage">
                <span class="package-details-lightbox-nav-arrow package-details-lightbox-nav-prev" id="lightboxPackagePrev">&lt;</span>
                <span class="package-details-lightbox-nav-arrow package-details-lightbox-nav-next" id="lightboxPackageNext">&gt;</span>
            </div>
        </div>
        <span class="package-details-lightbox-close" id="packageDetailsLightboxClose">&times;</span>
    </div>

    <div class="calendar-lightbox-overlay" id="calendarLightbox">
        <div class="calendar-lightbox-content">
            <span class="calendar-lightbox-close" id="calendarLightboxClose">&times;</span>
            <h3>Vendor Availability</h3>
            <div id="calendar-lightbox-instance"></div>
            <div class="calendar-legend" style="margin-top: var(--spacing-md);">
                <span class="legend-item"><span class="legend-color available-color"></span> Available</span>
                <span class="legend-item"><span class="legend-color booked-color"></span> Booked</span>
                <span class="legend-item"><span class="legend-color blocked-color"></span> Blocked</span>
                <span class="legend-item"><span class="legend-color holiday-color"></span> Holiday</span>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const publicCalendarEl = document.getElementById('public-availability-calendar');
            const calendarLightbox = document.getElementById('calendarLightbox');
            const calendarLightboxClose = document.getElementById('calendarLightboxClose');
            const calendarLightboxInstanceEl = document.getElementById('calendar-lightbox-instance');

            const vendorProfileId = <?= json_encode($vendor_profile_id_for_js) ?>;

            let calendarLightboxInstance = null; // To hold the FullCalendar instance for the lightbox

            // Function to initialize a FullCalendar instance
            function initializeCalendar(element, isLightbox = false) {
                if (typeof vendorProfileId !== 'number' || vendorProfileId <= 0) {
                    element.innerHTML = '<p class="text-subtle">Vendor ID is invalid, cannot display availability calendar.</p>';
                    return null; // Return null if invalid ID
                }

                const calendar = new FullCalendar.Calendar(element, {
                    initialView: 'dayGridMonth',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: isLightbox ? 'dayGridMonth,timeGridWeek,timeGridDay' : '' // More views in lightbox
                    },
                    validRange: {
                        start: '<?= date('Y-m-d') ?>'
                    },
                    events: function(fetchInfo, successCallback, failureCallback) {
                        fetch(`<?= BASE_URL ?>public/availability.php?vendor_id=${vendorProfileId}&start=${fetchInfo.startStr}&end=${fetchInfo.endStr}`)
                            .then(response => {
                                if (!response.ok) {
                                    // If response is not OK, try to read it as text to debug
                                    return response.text().then(text => {
                                        console.error(`Error fetching availability for ${isLightbox ? 'lightbox' : 'public'} calendar: Server response not OK.`, response.status, text);
                                        throw new Error('Server error or invalid JSON response for calendar data.');
                                    });
                                }
                                return response.json(); // Attempt to parse as JSON
                            })
                            .then(data => {
                                successCallback(data);
                            })
                            .catch(error => {
                                console.error(`Error fetching availability for ${isLightbox ? 'lightbox' : 'public'} calendar:`, error);
                                element.innerHTML = '<p class="text-subtle">Failed to load calendar. Please check console for details.</p>';
                                failureCallback(error);
                            });
                    },
                    eventContent: function(arg) {
                        // Only show event title in lightbox calendar
                        if (isLightbox) {
                            return { html: `<div class="fc-event-status-text">${arg.event.title}</div>` };
                        }
                        // For the main public calendar, return an empty string to hide text
                        return { html: '' }; 
                    },
                    selectable: isLightbox, // Allow selection only in lightbox
                    editable: false,
                    eventClick: function(info) {
                        info.jsEvent.preventDefault();
                        const clickedDate = info.event.startStr.slice(0, 10);
                        if (info.event.extendedProps.status === 'available') {
                             const bookUrl = `<?= BASE_URL ?>public/book_vendor.php?vendor_id=<?= htmlspecialchars($vendor_profile['id']) ?>&prefill_date=${clickedDate}`;
                             window.location.href = bookUrl;
                        } else {
                            alert(`This date is ${info.event.extendedProps.status}. Please choose an available date.`);
                        }
                    }
                });
                calendar.render();
                return calendar; // Return the calendar instance
            }

            // --- Initialize Public Calendar on Profile Page ---
            const publicCalendarInstance = initializeCalendar(publicCalendarEl, false);
            
            // Make the public calendar clickable to open lightbox
            if (publicCalendarEl) { // Check if the element exists before adding listener
                publicCalendarEl.addEventListener('click', () => {
                    calendarLightbox.classList.add('active');
                    document.body.style.overflow = 'hidden';

                    // Initialize or update the lightbox calendar
                    if (!calendarLightboxInstance) {
                        calendarLightboxInstance = initializeCalendar(calendarLightboxInstanceEl, true);
                    } else {
                        // If already initialized, update its size and refetch events to ensure it's current
                        calendarLightboxInstance.refetchEvents();
                        calendarLightboxInstance.updateSize(); 
                    }
                });
            }

            // --- Calendar Lightbox Close Logic ---
            calendarLightboxClose.addEventListener('click', () => {
                calendarLightbox.classList.remove('active');
                document.body.style.overflow = '';
            });

            // Close lightbox with ESC key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && calendarLightbox.classList.contains('active')) {
                    calendarLightbox.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });

            // --- Package Details Lightbox Logic (existing) ---
            const packageDetailsLightbox = document.getElementById('packageDetailsLightbox');
            const lightboxPackageName = document.getElementById('lightboxPackageName');
            const lightboxPackagePrice = document.getElementById('lightboxPackagePrice');
            const lightboxPackageDescription = document.getElementById('lightboxPackageDescription');
            const lightboxPackageImage = document.getElementById('lightboxPackageImage');
            const lightboxPackagePrev = document.getElementById('lightboxPackagePrev');
            const lightboxPackageNext = document.getElementById('lightboxPackageNext');
            const lightboxBookButton = document.getElementById('lightboxBookButton'); // This might be null
            const packageDetailsLightboxClose = document.getElementById('packageDetailsLightboxClose');

            let currentPackageImages = [];
            let currentPackageImageIndex = 0;
            let currentBookLink = '';

            document.querySelectorAll('.service-package-card').forEach(card => {
                card.addEventListener('click', function(e) {
                    // Prevent opening lightbox if clicking the "Book This Package" button
                    if (e.target.closest('.action-button')) {
                        return;
                    }

                    const packageName = this.dataset.packageName;
                    const packageDescription = this.dataset.packageDescription;
                    const priceMin = this.dataset.packagePriceMin;
                    const priceMax = this.dataset.packagePriceMax;
                    const imagesJson = this.dataset.packageImages;
                    
                    // Safely get the service_offering_id and package_id from the 'Book This Package' link
                    const bookButtonLinkElement = this.querySelector('.action-button a.btn'); // Get the element
                    let serviceOfferingId = '';
                    let packageId = '';

                    if (bookButtonLinkElement) { // Check if the element exists
                        const urlParams = new URLSearchParams(bookButtonLinkElement.href.split('?')[1]);
                        serviceOfferingId = urlParams.get('service_offering_id') || '';
                        packageId = urlParams.get('package_id') || '';
                    }
                    
                    currentPackageImages = imagesJson ? JSON.parse(imagesJson) : [];
                    currentPackageImageIndex = 0;

                    lightboxPackageName.textContent = packageName;
                    lightboxPackageDescription.innerHTML = packageDescription.replace(/\n/g, '<br>'); // Preserve newlines
                    lightboxPackagePrice.textContent = `PKR ${parseFloat(priceMin).toLocaleString()} - PKR ${parseFloat(priceMax).toLocaleString()}`;
                    
                    // Set the book button link ONLY if the button element exists
                    if (lightboxBookButton) { // Check for null here
                        currentBookLink = `<?= BASE_URL ?>public/book_vendor.php?vendor_id=<?= htmlspecialchars($vendor_profile['id']) ?>&service_offering_id=${serviceOfferingId}&package_id=${packageId}`;
                        lightboxBookButton.href = currentBookLink;
                    }

                    showPackageImage(currentPackageImageIndex);
                    packageDetailsLightbox.classList.add('active');
                    document.body.style.overflow = 'hidden';
                });
            });

            packageDetailsLightboxClose.addEventListener('click', () => {
                packageDetailsLightbox.classList.remove('active');
                document.body.style.overflow = '';
            });

            lightboxPackagePrev.addEventListener('click', () => {
                currentPackageImageIndex = (currentPackageImageIndex - 1 + currentPackageImages.length) % currentPackageImages.length;
                showPackageImage(currentPackageImageIndex);
            });

            lightboxPackageNext.addEventListener('click', () => {
                currentPackageImageIndex = (currentPackageImageIndex + 1) % currentPackageImages.length;
                showPackageImage(currentPackageImageIndex);
            });

            function showPackageImage(index) {
                if (currentPackageImages.length > 0) {
                    lightboxPackageImage.src = '<?= BASE_URL ?>' + currentPackageImages[index];
                    lightboxPackageImage.style.display = 'block';
                    // Show/hide arrows based on image count
                    if (currentPackageImages.length > 1) {
                        lightboxPackagePrev.style.display = 'flex';
                        lightboxPackageNext.style.display = 'flex';
                    } else {
                        lightboxPackagePrev.style.display = 'none';
                        lightboxPackageNext.style.display = 'none';
                    }
                } else {
                    lightboxPackageImage.src = ''; // Clear image
                    lightboxPackageImage.style.display = 'none'; // Hide image container
                    lightboxPackagePrev.style.display = 'none';
                    lightboxPackageNext.style.display = 'none';
                }
            }

            // Close lightbox when clicking outside the content
            packageDetailsLightbox.addEventListener('click', function(e) {
                if (e.target === packageDetailsLightbox) {
                    packageDetailsLightbox.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });

            // Close with ESC key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && packageDetailsLightbox.classList.contains('active')) {
                    packageDetailsLightbox.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });
    </script>
</body>
</html>

