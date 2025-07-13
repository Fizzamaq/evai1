<?php
// public/view_portfolio_item.php
session_start();
require_once '../includes/config.php';
require_once '../classes/User.class.php';
require_once '../classes/Vendor.class.php';

// Ensure portfolio item ID is provided in the URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid portfolio item ID provided.";
    header('Location: ' . BASE_URL . 'public/index.php'); // Redirect to homepage or error page
    exit();
}

$portfolio_item_id = (int)$_GET['id'];

$vendor_obj = new Vendor($pdo); // Use $vendor_obj to avoid name clash with public profile display

// Fetch portfolio item data (getPortfolioItemById now includes all images)
$item_details = $vendor_obj->getPortfolioItemById($portfolio_item_id);

if (!$item_details) {
    $_SESSION['error_message'] = "Portfolio item not found.";
    header('Location: ' . BASE_URL . 'public/vendor_portfolio.php'); // Or a general vendors page
    exit();
}

// Fetch vendor profile data (to link back to the vendor's main profile)
$vendor_profile = $vendor_obj->getVendorProfileById($item_details['vendor_id']);

// ADDED: Fetch other portfolio items from the same vendor, EXCLUDING the current one
$other_portfolio_items = [];
if ($vendor_profile) {
    // Pass the current portfolio_item_id to exclude it from the list
    $other_portfolio_items = $vendor_obj->getVendorPortfolio($vendor_profile['id'], $portfolio_item_id);
}

// include 'header.php'; // Include header AFTER all PHP logic that might set session messages
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($item_details['title']) ?> - Portfolio Item</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/vendor_profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <style>
        .portfolio-detail-container {
            max-width: 1100px; /* Adjusted to make the carousel and details bigger */
            margin: var(--spacing-lg) auto;
            padding: var(--spacing-md);
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .portfolio-detail-header {
            text-align: center;
            margin-bottom: var(--spacing-lg);
            border-bottom: 2px solid var(--border-color);
            padding-bottom: var(--spacing-md);
        }
        .portfolio-detail-header h1 {
            font-size: 2.5em;
            color: var(--primary-color);
            margin-bottom: var(--spacing-sm);
        }
        .portfolio-detail-header p {
            color: var(--text-subtle);
            font-size: 1.1em;
        }
        /* Styles for the main content layout: collage right, details left */
        .portfolio-content-layout {
            display: flex;
            flex-direction: column; /* Stack on small screens */
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
        }
        @media (min-width: 768px) {
            .portfolio-content-layout {
                flex-direction: row; /* Side-by-side on larger screens */
            }
            /* Adjust flex ratios for left (details) and right (collage) columns */
            .portfolio-details-left {
                flex: 1; /* Takes 1 part of the available space */
                max-width: 100%; /* Ensures flex items don't overflow */
            }
            .portfolio-carousel-right { /* Renamed from portfolio-collage-right for consistency, but acts as collage container */
                flex: 2; /* Takes 2 parts of the available space, making it bigger */
                max-width: 100%; /* Ensures flex items don't overflow */
            }
            /* Ensure correct behavior if only one column is present */
            .portfolio-details-left:only-child {
                flex: 0 0 100%;
            }
            .portfolio-carousel-right:only-child {
                flex: 0 0 100%;
            }
        }
        
        /* Styles for Swiper Carousel (retained if needed elsewhere, but replaced for main display) */
        .portfolio-carousel-wrapper {
            width: 100%;
            padding-bottom: 75%; /* Set aspect ratio to 4:3 (height is 75% of width) */
            margin-bottom: var(--spacing-lg);
            position: relative; /* For absolute positioning of children */
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden; /* Ensures rounded corners are applied */
            box-sizing: border-box;
        }
        /* Make swiper-wrapper fill the aspect-ratio defined space */
        .portfolio-carousel-wrapper .swiper-wrapper {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        .portfolio-carousel-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        /* Make images clickable, and remove default link styling */
        .portfolio-carousel-wrapper .swiper-slide a {
            display: block; /* Ensure the link fills the slide for clicking */
            width: 100%;
            height: 100%;
            text-decoration: none; /* Remove underline from clickable images */
            cursor: zoom-in; /* Indicate it's clickable for zoom */
        }

        .swiper-button-next,
        .swiper-button-prev {
            display: none; /* Hide navigation arrows */
        }
        .swiper-pagination-bullet {
            background: var(--primary-color) !important;
            opacity: 0.7 !important;
        }
        .swiper-pagination-bullet-active {
            opacity: 1 !important;
        }

        /* NEW: Styles for the Tabbed Interface */
        .tab-navigation {
            display: flex;
            flex-wrap: wrap; /* Allow tabs to wrap on smaller screens */
            border-bottom: 2px solid var(--border-color);
            margin-bottom: var(--spacing-md);
            padding-bottom: 5px; /* Space for border-bottom of active tab */
            background: var(--white); /* Match container background */
            border-radius: 8px 8px 0 0; /* Rounded top corners */
            overflow: hidden; /* Ensure border radius applies */
            box-shadow: 0 2px 8px rgba(0,0,0,0.05); /* Subtle shadow for tab bar */
        }
        .tab-button {
            background: var(--background-light); /* Light background for inactive tabs */
            border: none;
            border-bottom: 3px solid transparent;
            padding: var(--spacing-sm) var(--spacing-md); /* Increased padding */
            font-size: 1.05em; /* Slightly larger font */
            font-weight: 600;
            color: var(--text-subtle);
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap; /* Prevent tab text from wrapping */
            flex-grow: 1; /* Allow tabs to share space */
            text-align: center; /* Center tab text */
            border-right: 1px solid var(--border-color); /* Separator between tabs */
        }
        .tab-button:last-child {
            border-right: none; /* No separator for the last tab */
        }
        .tab-button:hover:not(.active) {
            color: var(--primary-color);
            background-color: #E6E6FA; /* Lighter hover background */
        }
        .tab-button.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            background-color: var(--white); /* White background for active tab */
            box-shadow: inset 0 -3px 5px rgba(0,0,0,0.05); /* Subtle inner shadow for active tab */
            transform: translateY(0); /* Ensure no unwanted transform from general hover */
            position: relative; /* For z-index if needed */
        }
        .tab-content {
            display: none; /* Hidden by default */
            animation: fadeIn 0.5s ease-out; /* Fade in animation for content */
            padding: var(--spacing-lg); /* Increased padding for content */
            background: var(--background-light); /* Background for tab content */
            border-radius: 0 0 8px 8px; /* Rounded bottom corners */
            box-shadow: 0 4px 10px rgba(0,0,0,0.08); /* Consistent card shadow */
            margin-bottom: var(--spacing-lg); /* Space after the content */
        }
        .tab-content.active {
            display: block; /* Show active tab content */
        }
        .tab-content h2 { /* Adjust heading within tab content */
            font-size: 1.6em; /* Slightly smaller for tab headings */
            margin-top: 0;
            padding-bottom: var(--spacing-sm);
            border-bottom: 1px solid var(--border-color);
            margin-bottom: var(--spacing-md);
            color: var(--text-dark);
        }
        .detail-item {
            margin-bottom: var(--spacing-sm); /* More space between detail items */
        }
        .detail-item p {
            margin-bottom: 0; /* Remove default paragraph margin */
            font-size: 1em; /* Standard font size for details */
            color: var(--text-dark); /* Darker text for details */
        }
        .detail-item strong {
            color: var(--primary-color); /* Primary color for strong text */
            font-weight: 600;
        }
        /* Specific list styling for Services Provided */
        #services-provided ul {
            list-style: none; /* Remove default bullet points */
            padding: 0;
            margin: 0;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); /* Responsive grid for services */
            gap: var(--spacing-xs); /* Small gap between service items */
        }
        #services-provided li {
            background-color: var(--white);
            padding: var(--spacing-sm) var(--spacing-md);
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            font-size: 0.95em;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        #services-provided li::before { /* Custom bullet point */
            content: "•";
            color: var(--primary-color);
            font-weight: bold;
            display: inline-block;
            width: 1em; /* Space for bullet */
            margin-left: -1em; /* Pull bullet left */
        }


        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .client-testimonial-quote {
            font-style: italic;
            border-left: 4px solid var(--primary-color);
            padding-left: var(--spacing-md);
            margin-top: var(--spacing-md);
            color: var(--text-dark);
            background-color: var(--white); /* Added background for quote */
            padding: var(--spacing-md);
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: var(--spacing-xl);
            font-size: 1.1em;
        }

        /* Lightbox/Modal Styles */
        .lightbox-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85); /* Darker overlay */
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999; /* Ensure it's on top of everything */
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        .lightbox-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .lightbox-content {
            position: relative;
            max-width: 90%;
            max-height: 90%;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.6);
            border-radius: 8px; /* Slight roundness */
            overflow: hidden; /* Ensure image doesn't overflow its own box-shadow/border-radius */
        }
        .lightbox-content img {
            display: block; /* Remove extra space below image */
            max-width: 100%;
            max-height: 100%;
            object-fit: contain; /* Contain image within its dimensions */
            border-radius: 8px; /* Match content border-radius */
        }
        .lightbox-close {
            position: absolute;
            top: 15px; /* Slightly more padding */
            right: 15px;
            font-size: 2.5em; /* Larger close button */
            color: #fff;
            cursor: pointer;
            z-index: 10000;
            background-color: rgba(0, 0, 0, 0.4); /* Background for visibility */
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: background-color 0.2s ease;
        }
        .lightbox-close:hover {
            background-color: rgba(255, 0, 0, 0.6); /* Red on hover */
        }
        /* Navigation arrows for lightbox */
        .lightbox-nav-arrow {
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
        .lightbox-nav-arrow:hover {
            background-color: rgba(0, 0, 0, 0.5);
        }
        .lightbox-nav-prev {
            left: 20px;
        }
        .lightbox-nav-next {
            right: 20px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="portfolio-detail-container">
        <div class="portfolio-detail-header">
            <h1><?= htmlspecialchars($item_details['title']) ?></h1>
            <?php if ($vendor_profile): ?>
                <p>Project by <a href="<?= BASE_URL ?>public/vendor_profile.php?id=<?= htmlspecialchars($vendor_profile['id']) ?>"><?= htmlspecialchars($vendor_profile['business_name']) ?></a></p>
            <?php endif; ?>
        </div>

        <div class="portfolio-content-layout">
            <div class="portfolio-details-left">
                <div class="tab-navigation">
                    <button class="tab-button" data-target="description-content">Description</button>
                    <button class="tab-button" data-target="details-content">Details</button>
                    <?php if (!empty($item_details['services_provided'])): ?>
                        <button class="tab-button" data-target="services-provided">Services Provided</button>
                    <?php endif; ?>
                    <?php if (!empty($item_details['client_testimonial'])): ?>
                        <button class="tab-button" data-target="client-testimonial-tab">Reviews</button>
                    <?php endif; ?>
                </div>

                <div id="description-content" class="tab-content">
                    <h2>Description</h2>
                    <p><?= nl2br(htmlspecialchars($item_details['description'] ?? 'No description provided.')) ?></p>
                </div>

                <div id="details-content" class="tab-content">
                    <h2>Details</h2>
                    <?php if (!empty($item_details['project_charges'])): ?>
                        <div class="detail-item">
                            <p><strong>Charges:</strong> PKR <?= number_format($item_details['project_charges'], 2) ?></p>
                        </div>
                    <?php endif; ?>
                    <div class="detail-item">
                        <p><strong>Event Type:</strong> <?= htmlspecialchars($item_details['event_type_name'] ?? 'N/A') ?></p>
                    </div>
                    <?php if (!empty($item_details['project_date'])): ?>
                        <div class="detail-item">
                            <p><strong>Project Date:</strong> <?= date('F j, Y', strtotime($item_details['project_date'])) ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($item_details['video_url'])): ?>
                        <div class="detail-item">
                            <p><strong>Video:</strong> <a href="<?= htmlspecialchars($item_details['video_url']) ?>" target="_blank"><?= htmlspecialchars($item_details['video_url']) ?></a></p>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($item_details['is_featured'])): ?>
                        <div class="detail-item">
                            <p><strong>Status:</strong> Featured Project</p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($item_details['venue_name'])): /* Moved Venue Details inside 'Details' tab */ ?>
                        <div class="detail-item">
                            <p><strong>Venue Name:</strong> <?= htmlspecialchars($item_details['venue_name']) ?></p>
                        </div>
                        <?php if (!empty($item_details['venue_address'])): ?>
                            <div class="detail-item">
                                <p><strong>Address:</strong> <?= htmlspecialchars($item_details['venue_address']) ?>, <?= htmlspecialchars($item_details['venue_city']) ?>, <?= htmlspecialchars($item_details['venue_state']) ?>, <?= htmlspecialchars($item_details['venue_postal_code']) ?>, <?= htmlspecialchars($item_details['venue_country']) ?></p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php if (!empty($item_details['services_provided'])): ?>
                <div id="services-provided" class="tab-content">
                    <h2>Services Provided</h2>
                    <ul>
                        <?php foreach ($item_details['services_provided'] as $service): ?>
                            <li><?= htmlspecialchars($service['service_name']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if (!empty($item_details['client_testimonial'])): /* Client Testimonial moved to 'Reviews' tab */ ?>
                <div id="client-testimonial-tab" class="tab-content">
                    <h2>Reviews</h2>
                    <p class="client-testimonial-quote">"<?= nl2br(htmlspecialchars($item_details['client_testimonial'])) ?>"</p>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($item_details['images'])): ?>
            <div class="portfolio-carousel-right profile_collageContainer__Bn1a_">
                <div class="profile_collageUpperPane__8NcMm">
                    <?php if (isset($item_details['images'][0])): ?>
                        <a href="<?= BASE_URL . htmlspecialchars($item_details['images'][0]['image_url']) ?>" class="lightbox-trigger">
                            <img src="<?= BASE_URL . htmlspecialchars($item_details['images'][0]['image_url']) ?>" alt="<?= htmlspecialchars($item_details['title']) ?> - Main Image">
                        </a>
                    <?php endif; ?>
                </div>
                <?php if (count($item_details['images']) > 1): ?>
                    <div class="profile_collageLowerPane__aFs9q">
                        <?php for ($i = 1; $i < count($item_details['images']) && $i <= 3; $i++): // Display up to 3 more images for the collage ?>
                            <a href="<?= BASE_URL . htmlspecialchars($item_details['images'][$i]['image_url']) ?>" class="lightbox-trigger" style="flex: 1; overflow: hidden; height: 100%; display: flex; align-items: center; justify-content: center; margin-right: 5px;">
                                <img src="<?= BASE_URL . htmlspecialchars($item_details['images'][$i]['image_url']) ?>" alt="<?= htmlspecialchars($item_details['title']) ?> - Image <?= $i + 1 ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
                <div class="portfolio-placeholder" style="height: 400px; display: flex; flex-direction: column; justify-content: center; align-items: center; background: var(--background-light); color: var(--text-subtle); border-radius: 10px;">
                    <i class="fas fa-image" style="font-size: 4em;"></i>
                    <span>No Images Available</span>
                </div>
            <?php endif; ?>
        </div>

        <div class="back-link">
            <a href="<?= BASE_URL ?>public/vendor_profile.php?id=<?= htmlspecialchars($vendor_profile['id'] ?? '') ?>" class="btn btn-secondary">Back to <?= htmlspecialchars($vendor_profile['business_name'] ?? 'Vendor') ?>'s Profile</a>
        </div>

        <?php // ADDED: Section for "More from this Vendor" ?>
        <?php if (!empty($other_portfolio_items)): ?>
            <div class="profile-section" style="margin-top: var(--spacing-xxl);">
                <h2>More from <?= htmlspecialchars($vendor_profile['business_name']) ?></h2>
                <div class="portfolio-grid">
                    <?php foreach ($other_portfolio_items as $item): ?>
                        <div class="portfolio-item-card">
                            <a href="<?= BASE_URL ?>public/view_portfolio_item.php?id=<?= $item['id'] ?>" class="portfolio-item-link-area">
                                <div class="portfolio-image-wrapper">
                                    <?php if (!empty($item['main_image_url'])): ?>
                                        <img src="<?= BASE_URL . htmlspecialchars($item['main_image_url']) ?>" alt="<?= htmlspecialchars($item['title']) ?>">
                                    <?php else: ?>
                                        <div class="portfolio-placeholder">
                                            <i class="fas fa-image"></i>
                                            <span>No Image</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="portfolio-description-content">
                                    <h3><?= htmlspecialchars($item['title']) ?></h3>
                                    <p><?= htmlspecialchars(substr($item['description'] ?? 'No description available.', 0, 150)) ?><?= (strlen($item['description'] ?? '') > 150 ? '...' : '') ?></p>
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
            </div>
        <?php endif; ?>
    </div>

    <div class="lightbox-overlay" id="lightboxOverlay">
        <div class="lightbox-content">
            <img src="" alt="Enlarged Image" id="lightboxImage">
        </div>
        <span class="lightbox-close" id="lightboxClose">&times;</span>
        <span class="lightbox-nav-arrow lightbox-nav-prev" id="lightboxPrev">&lt;</span>
        <span class="lightbox-nav-arrow lightbox-nav-next" id="lightboxNext">&gt;</span>
    </div>

    <?php include 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Swiper Initialization (for other carousels if any, not for main portfolio image collage)
            const swiperWrapper = document.querySelector('.portfolio-carousel-wrapper .swiper-slide');
            if (swiperWrapper) {
                new Swiper('.portfolio-carousel-wrapper', {
                    loop: true, // Enable looping
                    autoplay: {
                        delay: 5000, // 5 seconds between slides
                        disableOnInteraction: false, // Continue autoplay after user interaction
                    },
                    pagination: {
                        el: '.swiper-pagination',
                        clickable: true,
                    },
                    grabCursor: true, // Show grab cursor when hovering over slides
                    slidesPerView: 1, // Display one slide at a time
                    spaceBetween: 0, // No space between slides
                });
            }

            // Lightbox functionality with navigation
            const lightboxOverlay = document.getElementById('lightboxOverlay');
            const lightboxImage = document.getElementById('lightboxImage');
            const lightboxClose = document.getElementById('lightboxClose');
            const lightboxPrev = document.getElementById('lightboxPrev');
            const lightboxNext = document.getElementById('lightboxNext');
            const lightboxTriggers = document.querySelectorAll('.lightbox-trigger');

            let currentImageIndex = 0;
            const images = []; // Array to store all image URLs for navigation

            // Populate the images array from all collage triggers
            lightboxTriggers.forEach(trigger => {
                images.push(trigger.href);
            });

            function openLightbox(index) {
                currentImageIndex = index;
                lightboxImage.src = images[currentImageIndex];
                lightboxOverlay.classList.add('active');
                document.body.style.overflow = 'hidden'; // Prevent background scrolling
                updateNavigationButtons();
            }

            function closeLightbox() {
                lightboxOverlay.classList.remove('active');
                document.body.style.overflow = ''; // Restore scrolling
            }

            function showNextImage() {
                currentImageIndex = (currentImageIndex + 1) % images.length;
                lightboxImage.src = images[currentImageIndex];
                updateNavigationButtons();
            }

            function showPrevImage() {
                currentImageIndex = (currentImageIndex - 1 + images.length) % images.length;
                lightboxImage.src = images[currentImageIndex];
                updateNavigationButtons();
            }

            function updateNavigationButtons() {
                // Hide arrows if there's only one image
                if (images.length <= 1) {
                    lightboxPrev.style.display = 'none';
                    lightboxNext.style.display = 'none';
                } else {
                    lightboxPrev.style.display = 'block';
                    lightboxNext.style.display = 'block';
                }
            }


            lightboxTriggers.forEach((trigger, index) => {
                trigger.addEventListener('click', function(e) {
                    e.preventDefault(); // Prevent default link behavior
                    openLightbox(index);
                });
            });

            lightboxClose.addEventListener('click', closeLightbox);
            lightboxPrev.addEventListener('click', showPrevImage);
            lightboxNext.addEventListener('click', showNextImage);

            // Close lightbox when clicking outside the image (on the overlay)
            lightboxOverlay.addEventListener('click', function(e) {
                if (e.target === lightboxOverlay) { // Only close if clicked directly on overlay, not the image itself
                    closeLightbox();
                }
            });

            // Close lightbox with ESC key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && lightboxOverlay.classList.contains('active')) {
                    closeLightbox();
                }
            });

            // Tabbed interface logic
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');

            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    // Remove 'active' class from all buttons and content
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));

                    // Add 'active' class to the clicked button
                    button.classList.add('active');

                    // Show the corresponding content tab
                    const targetId = button.dataset.target;
                    document.getElementById(targetId).classList.add('active');
                });
            });

            // Set default active tab on page load (Description tab)
            const defaultTabButton = document.querySelector('.tab-button[data-target="description-content"]');
            const defaultTabContent = document.getElementById('description-content');
            if (defaultTabButton && defaultTabContent) {
                defaultTabButton.classList.add('active');
                defaultTabContent.classList.add('active');
            }
        });
    </script>
</body>
</html>
