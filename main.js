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

    // Add any other general scripts here
});