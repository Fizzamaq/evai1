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

    // MODIFIED START: Function to check if the page is long enough to warrant the fixed header
    function isPageLongEnough() {
        // Compare the total scrollable height to the viewport height.
        // If content height is less than (e.g., 120%) of viewport height, it's a "short" page.
        const contentHeight = document.body.scrollHeight;
        const viewportHeight = window.innerHeight;
        return contentHeight > (viewportHeight * 1.2); // Page is "long enough" if content is > 120% of viewport
    }
    // MODIFIED END

    if (mainHeader) {
        // Determine scroll threshold dynamically based on hero section height if on landing page
        // If the page is short, fixedHeader should never be applied.
        const scrollThreshold = heroSection ? (heroSection.offsetHeight * 0.8) : 50; // 80% of hero height, or 50px for other pages

        // Initial state check on load and on resize
        checkAndToggleHeader(); 
        window.addEventListener('resize', checkAndToggleHeader); // Re-check on window resize

        window.addEventListener('scroll', function() {
            toggleHeaderFixedState(scrollThreshold);
        });
    }

    // MODIFIED START: New wrapper function to manage header state for short pages
    function checkAndToggleHeader() {
        if (!isPageLongEnough()) {
            // If the page is short, ensure header is in its default (non-fixed) state
            mainHeader.classList.remove('fixed-header');
            // Ensure no transparency on static header for short pages
            if (mainHeader.classList.contains('main-header-transparent')) {
                 mainHeader.classList.remove('main-header-transparent'); // This will be used in landing.css
            }
            // Ensure content colors are default (white text on blurry black) for short pages
            mainHeader.style.setProperty('color', 'var(--white)', 'important');
            mainHeader.querySelector('.logo').style.setProperty('color', 'var(--white)', 'important');
            mainHeader.querySelector('.mobile-menu-icon').style.setProperty('color', 'var(--white)', 'important');
            mainHeader.querySelectorAll('.main-nav a').forEach(link => link.style.setProperty('color', 'var(--white)', 'important'));

            // Remove the scroll listener as it's not needed for short pages
            window.removeEventListener('scroll', toggleHeaderFixedState);
        } else {
            // If page is long, apply scroll logic
            toggleHeaderFixedState(scrollThreshold); // Run initial check for long pages
            window.addEventListener('scroll', toggleHeaderFixedState); // Add scroll listener
        }
    }
    // MODIFIED END

    function toggleHeaderFixedState(scrollThreshold) {
        const currentScroll = window.scrollY || document.documentElement.scrollTop;

        // Only apply fixed header if page is long enough
        if (!isPageLongEnough()) { // Double-check inside scroll function
             mainHeader.classList.remove('fixed-header');
             return; // Exit if page is short
        }

        if (currentScroll > scrollThreshold) {
            mainHeader.classList.add('fixed-header'); // This class applies the "dynamic island" style
        } else {
            mainHeader.classList.remove('fixed-header'); // Returns to default style (e.g., transparent on landing, blurry black on others)
        }
        
        // Removed: 'scrolled-down' class logic as header should always be visible once fixed
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
