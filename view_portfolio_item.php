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
        /* This style block is for specific overrides or new rules unique to this page */
        /* General container for the whole portfolio item view */
        .portfolio-detail-container {
            max-width: auto; /* Adjusted to make the carousel and details bigger */
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

        /* Main content layout: details on left, collage on right */
        .portfolio-content-layout {
            display: flex;
            flex-direction: column; /* Stack on small screens */
            gap: var(--spacing-lg);
            margin-top: 0; /* Adjusted to remove space below tabs */
            margin-bottom: var(--spacing-lg);
        }
        @media (min-width: 768px) {
            .portfolio-content-layout {
                flex-direction: row; /* Side-by-side on larger screens */
            }
            .portfolio-details-left {
                flex: 1; /* Takes 1 part of the available space */
                max-width: 100%; /* Ensures flex items don't overflow */
                /* Removed display: flex; flex-direction: column; from here
                   because it is handled by the .tab-content-section style directly
                   or implicit block flow. */
            }
            .portfolio-carousel-right {
                flex: 2; /* Takes 2 parts of the available space, making it bigger */
                max-width: 100%; /* Ensures flex items don't overflow */
            }
            .portfolio-details-left:only-child {
                flex: 0 0 100%;
            }
            .portfolio-carousel-right:only-child {
                flex: 0 0 100%;
            }
        }
        
        /* Tab Navigation (now sticky and full width) */
        .tab-navigation {
            display: flex;
            flex-wrap: wrap;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 5px;
            background: var(--white);
            border-radius: 8px 8px 0 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);

            /* Sticky properties */
            position: sticky;
            padding-top: 0px; /* Space from top of screen */
            top: 140px; /* Adjust based on header height */
            width: 100%;
            left: 0; /* Align to left edge of its container */
            right: 0; /* Align to right edge of its container */
            z-index: 99; /* Ensure it stays above other content but below main header */
            justify-content: space-around; /* Distribute tabs evenly */
            box-sizing: border-box; /* Include padding in width */
            margin-bottom: var(--spacing-md); /* Space below sticky tabs */
        }
        .tab-button {
            background: var(--background-light);
            border: none;
            border-bottom: 3px solid transparent;
            padding: var(--spacing-sm) var(--spacing-md);
            font-size: 1.05em;
            font-weight: 600;
            color: var(--text-subtle);
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
            flex-grow: 1;
            text-align: center;
            border-right: 1px solid var(--border-color);
        }
        .tab-button:last-child {
            border-right: none;
        }
        .tab-button:hover:not(.active) {
            color: var(--primary-color);
            background-color: #E6E6FA;
        }
        .tab-button.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            background-color: var(--white);
            box-shadow: inset 0 -3px 5px rgba(0,0,0,0.05);
            transform: translateY(0);
            position: relative;
        }

        /* Individual content sections (now always visible and scrolled to) */
        .tab-content-section {
            padding: var(--spacing-lg);
            background: var(--background-light);
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
            margin-bottom: var(--spacing-lg); /* Space between sections */
            margin-top: var(--spacing-lg); /* Default margin for sections */
        }

        /* Specific rule for the FIRST tab-content-section directly following the tab-navigation
           when it's in the two-column layout (portfolio-details-left) */
        .portfolio-details-left > .tab-content-section:first-child {
            margin-top: 0; /* Remove top margin for the very first section in the left column */
        }

        .tab-content-section h2 {
            font-size: 1.6em;
            margin-top: 0;
            padding-bottom: var(--spacing-sm);
            border-bottom: 1px solid var(--border-color);
            margin-bottom: var(--spacing-md);
            color: var(--text-dark);
        }
        .detail-item {
            margin-bottom: var(--spacing-sm);
        }
        .detail-item p {
            margin-bottom: 0;
            font-size: 1em;
            color: var(--text-dark);
        }
        .detail-item strong {
            color: var(--primary-color);
            font-weight: 600;
        }
        #services-provided ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: var(--spacing-xs);
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
        #services-provided li::before {
            content: "•";
            color: var(--primary-color);
            font-weight: bold;
            display: inline-block;
            width: 1em;
            margin-left: -1em;
        }
        .client-testimonial-quote {
            font-style: italic;
            border-left: 4px solid var(--primary-color);
            padding-left: var(--spacing-md);
            margin-top: var(--spacing-md);
            color: var(--text-dark);
            background-color: var(--white);
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
            background: rgba(0, 0, 0, 0.85);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
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
            border-radius: 8px;
            overflow: hidden;
        }
        .lightbox-content img {
            display: block;
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            border-radius: 8px;
        }
        .lightbox-close {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 2.5em;
            color: #fff;
            cursor: pointer;
            z-index: 10000;
            background-color: rgba(0, 0, 0, 0.4);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: background-color 0.2s ease;
        }
        .lightbox-close:hover {
            background-color: rgba(255, 0, 0, 0.6);
        }
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

        <div class="tab-navigation profile_tabscontainer___0PX7">
            <button class="tab-button" data-target-id="description-section">Description</button>
            <button class="tab-button" data-target-id="details-section">Details</button>
            <?php if (!empty($item_details['services_provided'])): ?>
                <button class="tab-button" data-target-id="services-provided-section">Services Provided</button>
            <?php endif; ?>
            <?php if (!empty($item_details['client_testimonial'])): ?>
                <button class="tab-button" data-target-id="client-testimonial-section">Reviews</button>
            <?php endif; ?>
        </div>

        <div class="portfolio-content-layout">
            <div class="portfolio-details-left">
                <div id="description-section" class="tab-content-section">
                    <h2>Description</h2>
                    <p><?= nl2br(htmlspecialchars($item_details['description'] ?? 'No description provided.')) ?></p>
                </div>
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
                        <?php for ($i = 1; $i < count($item_details['images']) && $i <= 3; $i++): ?>
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

        <div id="details-section" class="tab-content-section">
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

            <?php if (!empty($item_details['venue_name'])): ?>
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
        <div id="services-provided-section" class="tab-content-section">
            <h2>Services Provided</h2>
            <ul>
                <?php foreach ($item_details['services_provided'] as $service): ?>
                    <li><?= htmlspecialchars($service['service_name']) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (!empty($item_details['client_testimonial'])): ?>
        <div id="client-testimonial-section" class="tab-content-section">
            <h2>Reviews</h2>
            <p class="client-testimonial-quote">"<?= nl2br(htmlspecialchars($item_details['client_testimonial'])) ?>"</p>
        </div>
        <?php endif; ?>

        <div class="back-link">
            <a href="<?= BASE_URL ?>public/vendor_profile.php?id=<?= htmlspecialchars($vendor_profile['id'] ?? '') ?>" class="btn btn-secondary">Back to <?= htmlspecialchars($vendor_profile['business_name'] ?? 'Vendor') ?>'s Profile</a>
        </div>

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
            const tabContents = document.querySelectorAll('.tab-content-section'); // Changed selector

            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const targetId = button.dataset.targetId;
                    const targetElement = document.getElementById(targetId);

                    if (targetElement) {
                        // Remove 'active' class from all buttons
                        tabButtons.forEach(btn => btn.classList.remove('active'));
                        // Add 'active' class to the clicked button
                        button.classList.add('active');

                        // Scroll to the target section
                        // Calculate offset for sticky header/tabs
                        const headerHeight = document.querySelector('.main-header').offsetHeight || 0;
                        const tabsHeight = document.querySelector('.tab-navigation').offsetHeight || 0;
                        const scrollOffset = headerHeight + tabsHeight + 20; // 20px for extra padding

                        window.scrollTo({
                            top: targetElement.offsetTop - scrollOffset,
                            behavior: 'smooth'
                        });
                    }
                });
            });

            // Function to update active tab on scroll
            function updateActiveTabOnScroll() {
                const headerHeight = document.querySelector('.main-header').offsetHeight || 0;
                const tabsHeight = document.querySelector('.tab-navigation').offsetHeight || 0;
                const scrollOffset = headerHeight + tabsHeight + 50; // Add some offset for better visual activation

                tabContents.forEach(section => {
                    // Check if the section is in the viewport, considering the sticky header/tabs
                    if (window.scrollY + scrollOffset >= section.offsetTop && window.scrollY + scrollOffset < (section.offsetTop + section.offsetHeight)) {
                        const targetId = section.id;
                        tabButtons.forEach(button => {
                            if (button.dataset.targetId === targetId) {
                                button.classList.add('active');
                            } else {
                                button.classList.remove('active');
                            }
                        });
                    }
                });
            }

            // Attach scroll listener
            window.addEventListener('scroll', updateActiveTabOnScroll);

            // Set initial active tab on page load (based on URL hash or first section)
            // Use setTimeout to ensure all elements are rendered and their offsetTop is correct
            setTimeout(() => {
                const initialHash = window.location.hash.substring(1);
                if (initialHash && document.getElementById(initialHash)) {
                    const initialTabButton = document.querySelector(`.tab-button[data-target-id="${initialHash}"]`);
                    if (initialTabButton) {
                        initialTabButton.click(); // Simulate click to scroll and activate
                    }
                } else {
                    const firstTabButton = document.querySelector('.tab-button');
                    if (firstTabButton) {
                        firstTabButton.click(); // Activate first tab on load if no hash
                    }
                }
                updateActiveTabOnScroll(); // Run once to set initial active tab correctly
            }, 100); // Small delay to ensure layout is stable
        });
    </script>
</body>
</html>
