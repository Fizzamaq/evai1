// assets/js/main.js
// This file can be used for general site-wide JavaScript functionalities.

document.addEventListener('DOMContentLoaded', function() {
    // Example: Smooth scrolling for anchor links (if not handled by landing.js)
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelector(this.getAttribute('href')).scrollIntoView({
                behavior: 'smooth'
            });
        });
    });

    // Example: Basic form submission feedback
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            // You can add client-side validation here before submission
            // For instance, check if required fields are filled.
            // If form uses AJAX, prevent default submission and handle with fetch API.
            // If form is regular POST, ensure server-side validation is robust.
        });
    });

    // --- Global Header Scroll Behavior (Dynamic Island Effect) ---
    const mainHeader = document.querySelector('.main-header');
    const heroSection = document.getElementById('hero-section'); // Only exists on index.php

    // Function to check if the page is long enough to warrant the fixed header
    function isPageLongEnough() {
        const contentHeight = document.body.scrollHeight;
        const viewportHeight = window.innerHeight;
        const result = contentHeight > (viewportHeight * 1.2); 
        console.log('isPageLongEnough: contentHeight=', contentHeight, 'viewportHeight=', viewportHeight, 'Result:', result);
        return result;
    }

    if (mainHeader) {
        // Determine scroll threshold dynamically:
        // - For index.php: 80% of hero section height.
        // - For other pages: A fixed small threshold like 50px.
        const scrollThreshold = heroSection ? (heroSection.offsetHeight * 0.8) : 50; 
        console.log('Initial scrollThreshold:', scrollThreshold, 'Hero Height:', heroSection ? heroSection.offsetHeight : 'N/A');

        // Initial state check and attach/detach scroll listener
        // This function runs on DOMContentLoaded and window resize
        // It decides if the page is long enough for the fixed header effect
        function setupHeaderScroll() {
            if (!isPageLongEnough()) {
                // If the page is short, ensure header is in its default (non-fixed) state
                mainHeader.classList.remove('fixed-header');
                // On landing page (index.php), ensure it is transparent if it's a short page
                if (heroSection) { 
                    mainHeader.classList.add('main-header-transparent'); // Keep transparent for short landing
                    console.log('setupHeaderScroll: Page is short, removing fixed-header, adding main-header-transparent (if index.php).');
                } else {
                    // For short non-index pages, ensure it's default blurry black
                    // This is handled by style.css default .main-header rule
                    console.log('setupHeaderScroll: Non-index page, short, ensuring default header.');
                }

                // Remove the scroll listener as it's not needed for short pages
                window.removeEventListener('scroll', currentScrollHandler); // REMOVED previous listener
            } else {
                // If page is long, apply scroll logic
                toggleHeaderFixedState(scrollThreshold); // Run initial check for long pages
                // MODIFIED: Attach listener correctly to pass the numeric threshold
                window.addEventListener('scroll', currentScrollHandler); // Use the named handler
                console.log('setupHeaderScroll: Page is long, scroll listener added.');
            }
        }

        // MODIFIED: Create a named function for the scroll handler to ensure correct threshold is always passed
        const currentScrollHandler = () => toggleHeaderFixedState(scrollThreshold);

        setupHeaderScroll(); // Run initially
        window.addEventListener('resize', setupHeaderScroll); // Re-run on window resize

        // Function that manages the header's fixed state based on scroll
        function toggleHeaderFixedState(currentScrollThreshold) {
            const currentScroll = window.scrollY || document.documentElement.scrollTop;
            console.log('toggleHeaderFixedState: currentScroll=', currentScroll, 'threshold=', currentScrollThreshold);

            if (currentScroll > currentScrollThreshold) {
                mainHeader.classList.add('fixed-header'); // Applies the "dynamic island" style
                console.log('toggleHeaderFixedState: Scrolled past threshold, adding fixed-header.');
            } else {
                mainHeader.classList.remove('fixed-header'); // Returns to default style
                console.log('toggleHeaderFixedState: Scrolled before threshold, removing fixed-header.');
            }
            
            // On index.php, manage the transparent class based on scrollThreshold
            if (heroSection) { // Only applies to the landing page
                if (currentScroll <= currentScrollThreshold) {
                    mainHeader.classList.add('main-header-transparent'); // Keep transparent while over hero
                    console.log('toggleHeaderFixedState (index.php): Adding main-header-transparent.');
                } else {
                    mainHeader.classList.remove('main-header-transparent'); // Remove transparency when scrolled past hero
                    console.log('toggleHeaderFixedState (index.php): Removing main-header-transparent.');
                }
            }
        }
    }

    // --- Mobile Menu Toggle ---
    const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
    const mainNavLinks = document.getElementById('main-nav-links');

    if (mobileMenuToggle && mainNavLinks) {
        mobileMenuToggle.addEventListener('click', () => {
            mainNavLinks.classList.toggle('active');
        });
    }
});
