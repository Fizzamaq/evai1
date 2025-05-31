<?php
//
require_once '../includes/config.php';
//
include 'header.php';
// Include the Vendor class to fetch data
require_once '../classes/Vendor.class.php';

// If user is already logged in, redirect to their appropriate dashboard
if (isset($_SESSION['user_id'])) {
    $redirect_url = BASE_URL . 'public/dashboard.php'; // Default for customer
    if (isset($_SESSION['user_type'])) {
        if ($_SESSION['user_type'] == 2) { // Vendor
            $redirect_url = BASE_URL . 'public/vendor_dashboard.php';
        } elseif ($_SESSION['user_type'] == 3) { // Admin
            $redirect_url = BASE_URL . 'admin/dashboard.php';
        }
    }
    header("Location: " . $redirect_url);
    exit();
}

// Instantiate Vendor class to fetch data
$vendor = new Vendor($pdo);

// Fetch all vendor categories
$vendor_categories = $vendor->getAllVendorCategories();

// Optional: Fetch featured vendors for a general "Featured" section if still desired,
// otherwise this section can be removed and replaced by category-specific ones.
// $featured_vendors = $vendor->getFeaturedVendorsForHomepage(8);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventCraftAI - Smart Event Planning</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/landing.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
</head>
<body>
    <section class="hero" id="hero-section">
        <div class="hero-content">
            <h1>Plan Perfect Events with AI Assistance</h1>
            <p class="subtitle">From weddings to corporate gatherings, streamline your event planning with smart tools and vendor connections.</p>
            <div class="cta-buttons">
                <a href="register.php" class="btn btn-primary btn-large">Get Started</a>
                <a href="login.php" class="btn btn-secondary btn-large">Login</a>
            </div>
        </div>
        </section>

    <section class="section categories-section">
        <div class="container">
            <h2>Explore Categories</h2>
            <div class="swiper categories-swiper">
                <div class="swiper-wrapper">
                    <?php if (!empty($vendor_categories)): ?>
                        <?php foreach ($vendor_categories as $category): ?>
                            <div class="swiper-slide">
                                <a href="<?= BASE_URL ?>public/vendors.php?category=<?= htmlspecialchars($category['id']) ?>" class="category-card">
                                    <i class="fas fa-<?= htmlspecialchars($category['icon'] ?: 'tags') ?> category-icon"></i>
                                    <h3><?= htmlspecialchars($category['category_name']) ?></h3>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="swiper-slide">
                            <p>No categories found at this time.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="swiper-pagination categories-pagination"></div>
                <div class="swiper-button-next categories-button-next"></div>
                <div class="swiper-button-prev categories-button-prev"></div>
            </div>
        </div>
    </section>

    <?php
    // Loop through each category and display its vendors in a horizontal carousel-like section
    if (!empty($vendor_categories)):
        foreach ($vendor_categories as $category):
            // Fetch vendors for the current category
            $vendors_for_category = $vendor->getVendorsByCategoryId($category['id'], 10); // Limit to 10 vendors per category
    ?>
            <section class="section vendor-category-section">
                <div class="container">
                    <h2><?= htmlspecialchars($category['category_name']) ?> Vendors</h2>
                    <?php if (!empty($vendors_for_category)): ?>
                        <div class="swiper vendors-swiper-<?= htmlspecialchars($category['id']) ?>">
                            <div class="swiper-wrapper">
                                <?php foreach ($vendors_for_category as $vendor_item): ?>
                                    <div class="swiper-slide">
                                        <a href="<?= BASE_URL ?>public/vendor_profile.php?id=<?= htmlspecialchars($vendor_item['id']) ?>" class="vendor-card-item">
                                            <div class="vendor-card-image" style="background-image: url('<?= ASSETS_PATH ?>uploads/users/<?= htmlspecialchars($vendor_item['profile_image'] ?: 'default-avatar.jpg') ?>')"></div>
                                            <div class="vendor-card-content">
                                                <h3><?= htmlspecialchars($vendor_item['business_name']) ?></h3>
                                                <p><?= htmlspecialchars($vendor_item['business_city']) ?></p>
                                                <div class="vendor-card-rating">
                                                    <?php
                                                    $rating = round($vendor_item['rating'] * 2) / 2; // Round to nearest 0.5
                                                    for ($i = 1; $i <= 5; $i++):
                                                        if ($rating >= $i) {
                                                            echo '<i class="fas fa-star"></i>'; // Full star
                                                        } elseif ($rating > ($i - 1) && $rating < $i) {
                                                            echo '<i class="fas fa-star-half-alt"></i>'; // Half star
                                                        } else {
                                                            echo '<i class="far fa-star"></i>'; // Empty star
                                                        }
                                                    endfor;
                                                    ?>
                                                    (<?= htmlspecialchars($vendor_item['total_reviews']) ?> Reviews)
                                                </div>
                                                <?php if (!empty($vendor_item['offered_services'])): ?>
                                                    <p class="vendor-card-services">Services: <?= htmlspecialchars($vendor_item['offered_services']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="swiper-pagination vendors-pagination-<?= htmlspecialchars($category['id']) ?>"></div>
                            <div class="swiper-button-next vendors-button-next-<?= htmlspecialchars($category['id']) ?>"></div>
                            <div class="swiper-button-prev vendors-button-prev-<?= htmlspecialchars($category['id']) ?>"></div>
                        </div>
                    <?php else: ?>
                        <p style="padding-left: var(--spacing-md);">No vendors found for this category at this time.</p>
                    <?php endif; ?>
                </div>
            </section>
    <?php
        endforeach;
    endif;
    ?>

    <section class="section how-it-works-section bg-light">
        <div class="container">
            <h2>How It Works</h2>
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <h3>Create Your Event</h3>
                    <p>Tell us about your event vision, guest count, and budget.</p>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <h3>Get AI Recommendations</h3>
                    <p>Receive smart suggestions for services and potential vendors.</p>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <h3>Connect & Book</h3>
                    <p>Chat with vendors, finalize details, and book services with ease.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section cta-bottom-section">
        <div class="container">
            <h2>Ready to Plan Your Perfect Event?</h2>
            <p class="subtitle">Join EventCraftAI today and make your next event unforgettable.</p>
            <a href="register.php" class="btn btn-primary btn-large">Sign Up Now</a>
        </div>
    </section>

    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="<?= ASSETS_PATH ?>js/landing.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const heroSection = document.getElementById('hero-section');
            // Removed heroLoading as it's for AI generation
            // const heroLoading = document.getElementById('hero-loading');

            // Removed the generateHeroBackground function call to use static image
            /*
            // Function to generate and set AI background image
            async function generateHeroBackground() {
                heroLoading.style.display = 'block'; // Show loading indicator

                const prompt = "A minimalist, abstract background for an event planning AI website, featuring subtle pastel blue and green tones, flowing lines, and a hint of digital elegance. No text or specific objects.";
                const payload = { instances: { prompt: prompt }, parameters: { "sampleCount": 1} };
                const apiKey = ""; // Canvas will provide this at runtime. DO NOT ADD YOUR OWN KEY HERE.
                const apiUrl = `https://generativelanguage.googleapis.com/v1beta/models/imagen-3.0-generate-002:predict?key=${apiKey}`;

                try {
                    const response = await fetch(apiUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });

                    const result = await response.json();

                    if (result.predictions && result.predictions.length > 0 && result.predictions[0].bytesBase64Encoded) {
                        const imageUrl = `data:image/png;base64,${result.predictions[0].bytesBase64Encoded}`;
                        heroSection.style.backgroundImage = `url('${imageUrl}')`;
                        heroSection.style.backgroundSize = 'cover';
                        heroSection.style.backgroundPosition = 'center';
                        heroSection.style.backgroundRepeat = 'no-repeat';
                    } else {
                        console.error('AI image generation failed: No image data received.');
                        // Fallback to gradient if image generation fails
                        heroSection.style.backgroundImage = 'linear-gradient(135deg, var(--primary-color), var(--secondary-color))';
                    }
                } catch (error) {
                    console.error('Error generating AI image:', error);
                    // Fallback to gradient on network/API error
                    heroSection.style.backgroundImage = 'linear-gradient(135deg, var(--primary-color), var(--secondary-color))';
                } finally {
                    heroLoading.style.display = 'none'; // Hide loading indicator
                }
            }
            // Call the function to generate background on page load
            generateHeroBackground();
            */

            // Smooth scrolling for anchor links (if not handled by main.js)
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.querySelector(this.getAttribute('href')).scrollIntoView({
                        behavior: 'smooth'
                    });
                });
            });
        });
    </script>
</body>
</html>
