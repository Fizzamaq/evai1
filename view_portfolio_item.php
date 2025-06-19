<?php
// public/view_portfolio_item.php
session_start();
require_once '../includes/config.php';
require_once '../classes/User.class.php';
require_once '../classes/Vendor.class.php';

// include 'header.php';

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
            max-width: 1200px;
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
        /* Styles for the main content layout: carousel right, details left */
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
            .portfolio-details-left,
            .portfolio-carousel-right {
                max-width: 100%; /* Ensures flex items don't overflow */
            }
        }
        .portfolio-details-left {
            flex: 1; /* Take available space */
        }
        .portfolio-carousel-right {
            flex: 1; /* Take available space */
        }
        /* Styles for Swiper Carousel */
        .portfolio-carousel-wrapper {
            width: 100%;
            /* Removed fixed height */
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
        .swiper-button-next,
        .swiper-button-prev {
            color: var(--white) !important;
            background-color: rgba(0, 0, 0, 0.5);
            padding: 10px;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            font-size: 1.2em;
        }
        .swiper-pagination-bullet {
            background: var(--primary-color) !important;
            opacity: 0.7 !important;
        }
        .swiper-pagination-bullet-active {
            opacity: 1 !important;
        }

        .portfolio-detail-section {
            margin-bottom: var(--spacing-lg);
            padding: var(--spacing-md);
            background: var(--background-light);
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .portfolio-detail-section h2 {
            font-size: 1.8em;
            color: var(--text-dark);
            margin-bottom: var(--spacing-md);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: var(--spacing-sm);
        }
        .detail-item p {
            margin-bottom: var(--spacing-xs);
            font-size: 1.05em;
        }
        .detail-item strong {
            color: var(--text-dark);
        }
        .detail-item a {
            word-break: break-all; /* Break long URLs */
        }
        .client-testimonial-quote {
            font-style: italic;
            border-left: 4px solid var(--primary-color);
            padding-left: var(--spacing-md);
            margin-top: var(--spacing-md);
            color: var(--text-dark);
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: var(--spacing-xl);
            font-size: 1.1em;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="portfolio-detail-container">
        <div class="portfolio-detail-header">
            <h1><?= htmlspecialchars($item_details['title']) ?></h1>
            <?php if ($vendor_profile): ?>
                <p>Creation by <a href="<?= BASE_URL ?>public/vendor_profile.php?id=<?= htmlspecialchars($vendor_profile['id']) ?>"><?= htmlspecialchars($vendor_profile['business_name']) ?></a></p>
            <?php endif; ?>
        </div>

        <div class="portfolio-content-layout">
            <div class="portfolio-details-left">
                <div class="portfolio-detail-section">
                    <h2>Details</h2>
                    <div class="detail-item">
                        <p><strong>Description:</strong> <?= nl2br(htmlspecialchars($item_details['description'] ?? 'No description provided.')) ?></p>
                    </div>
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
                            <p><strong>Date:</strong> <?= date('F j, Y', strtotime($item_details['project_date'])) ?></p>
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
                </div>

                <?php if (!empty($item_details['client_testimonial'])): ?>
                    <div class="portfolio-detail-section">
                        <h2>Client Testimonial</h2>
                        <p class="client-testimonial-quote">"<?= nl2br(htmlspecialchars($item_details['client_testimonial'])) ?>"</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="portfolio-carousel-right">
                <?php if (!empty($item_details['images'])): ?>
                    <div class="portfolio-carousel-wrapper swiper">
                        <div class="swiper-wrapper">
                            <?php foreach ($item_details['images'] as $image): ?>
                                <div class="swiper-slide">
                                    <img src="<?= BASE_URL . htmlspecialchars($image['image_url']) ?>" alt="<?= htmlspecialchars($item_details['title']) ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="swiper-pagination"></div>
                        <div class="swiper-button-next"></div>
                        <div class="swiper-button-prev"></div>
                    </div>
                <?php else: ?>
                    <div class="portfolio-carousel-wrapper" style="display: flex; justify-content: center; align-items: center; background-color: var(--background-light);">
                        <div class="portfolio-placeholder" style="position: static; width: auto; height: auto; border: none;">
                            <i class="fas fa-image" style="font-size: 4em; color: var(--text-subtle);"></i>
                            <span style="margin-top: 10px; color: var(--text-subtle);">No Images Available</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="back-link">
            <a href="<?= BASE_URL ?>public/vendor_profile.php?id=<?= htmlspecialchars($vendor_profile['id'] ?? '') ?>" class="btn btn-secondary">Back to <?= htmlspecialchars($vendor_profile['business_name'] ?? 'Vendor') ?>'s Profile</a>
        </div>
    </div>

    <?php include 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Swiper only if images exist
            if (document.querySelector('.portfolio-carousel-wrapper .swiper-slide')) {
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
                    navigation: {
                        nextEl: '.swiper-button-next',
                        prevEl: '.swiper-button-prev',
                    },
                    grabCursor: true, // Show grab cursor when hovering over slides
                    slidesPerView: 1, // Display one slide at a time
                    spaceBetween: 0, // No space between slides
                });
            }
        });
    </script>
</body>
</html>
