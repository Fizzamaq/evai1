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

    // Add fixed header on scroll (existing logic)
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

    // Initialize Swiper for Categories
    const categoriesSwiper = new Swiper('.categories-swiper', {
        slidesPerView: 'auto', // Adjust based on card width
        spaceBetween: 20, // Space between slides
        loop: true, // Infinite loop
        centeredSlides: false, // Keep slides left-aligned
        grabCursor: true, // Makes it visually clear the carousel is draggable
        autoplay: {
            delay: 3000, // Autoplay delay in ms
            disableOnInteraction: false, // Continue autoplay after user interaction
        },
        pagination: {
            el: '.categories-pagination',
            clickable: true,
        },
        // Navigation (Arrows) are hidden via CSS
        // Responsive breakpoints
        breakpoints: {
            // when window width is >= 320px
            320: {
                slidesPerView: 2,
                spaceBetween: 10
            },
            // when window width is >= 480px
            480: {
                slidesPerView: 3,
                spaceBetween: 20
            },
            // when window width is >= 768px
            768: {
                slidesPerView: 4,
                spaceBetween: 20
            },
            // when window width is >= 1024px
            1024: {
                slidesPerView: 5,
                spaceBetween: 30
            }
        }
    });

    // Initialize Swiper for each Vendor Category
    document.querySelectorAll('.vendor-category-section').forEach((section, index) => {
        const swiperContainer = section.querySelector('.swiper[class*="vendors-swiper-"]');
        if (!swiperContainer) return;

        const classList = swiperContainer.classList;
        let categoryId = null;
        for (let i = 0; i < classList.length; i++) {
            if (classList[i].startsWith('vendors-swiper-')) {
                categoryId = classList[i].replace('vendors-swiper-', '');
                break;
            }
        }

        if (categoryId) {
            new Swiper(swiperContainer, {
                slidesPerView: 'auto', // Adjust based on card width
                spaceBetween: 20, // Space between slides
                loop: true, // Infinite loop
                centeredSlides: false, // Keep slides left-aligned
                grabCursor: true, // Makes it visually clear the carousel is draggable
                autoplay: {
                    delay: 4000, // Autoplay delay in ms (slightly different from categories)
                    disableOnInteraction: false,
                },
                pagination: {
                    el: `.vendors-pagination-${categoryId}`,
                    clickable: true,
                },
                // Navigation (Arrows) are hidden via CSS
                // Responsive breakpoints
                breakpoints: {
                    320: {
                        slidesPerView: 1.2, // Show more than 1 for swiping feel
                        spaceBetween: 10
                    },
                    480: {
                        slidesPerView: 1.5,
                        spaceBetween: 15
                    },
                    768: {
                        slidesPerView: 2.2,
                        spaceBetween: 20
                    },
                    1024: {
                        slidesPerView: 3,
                        spaceBetween: 20
                    }
                }
            });
        }
    });
});
