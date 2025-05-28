<?php
// [cite: fizzamaq/evai/evai-270c475187253adadaf42cfe122a431191cf1f80/config.php]
require_once '../includes/config.php';
// [cite: fizzamaq/evai/evai-270c475187253adadaf42cfe122a431191cf1f80/header.php]
include 'header.php';

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
        <div id="hero-loading" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: white; font-size: 1.2em; z-index: 2; display: none;">
            Generating beautiful background...
        </div>
    </section>

    <section class="section features-section">
        <div class="container">
            <h2>Why Choose EventCraftAI?</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <i class="fas fa-robot feature-icon"></i>
                    <h3>AI-Powered Planning</h3>
                    <p>Leverage intelligent recommendations for event types, services, and budget allocation.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-calendar-check feature-icon"></i>
                    <h3>Effortless Management</h3>
                    <p>Organize all your event details, schedules, and communications in one place.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-handshake feature-icon"></i>
                    <h3>Connect with Vendors</h3>
                    <p>Discover and book top-rated vendors tailored to your event's specific needs.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-chart-line feature-icon"></i>
                    <h3>Performance Insights</h3>
                    <p>Track event progress and vendor performance with detailed reports.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-mobile-alt feature-icon"></i>
                    <h3>Mobile Friendly</h3>
                    <p>Manage your events on the go with our fully responsive platform.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-star feature-icon"></i>
                    <h3>Trusted Reviews</h3>
                    <p>Read real client reviews to make informed decisions about vendors.</p>
                </div>
            </div>
        </div>
    </section>

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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const heroSection = document.getElementById('hero-section');
            const heroLoading = document.getElementById('hero-loading');

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
