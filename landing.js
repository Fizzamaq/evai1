document.addEventListener('DOMContentLoaded', function() {
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelector(this.getAttribute('href')).scrollIntoView({
                behavior: 'smooth'
            });
        });
    });

    // Add fixed header on scroll
    const header = document.querySelector('.main-nav');
    const hero = document.querySelector('.hero');
    
    if (header && hero) {
        const heroHeight = hero.offsetHeight;
        
        window.addEventListener('scroll', function() {
            if (window.scrollY > heroHeight * 0.8) {
                header.classList.add('fixed-header');
            } else {
                header.classList.remove('fixed-header');
            }
        });
    }
});