document.addEventListener('DOMContentLoaded', function() {
    // Removed: Smooth scrolling for anchor links - now handled by main.js
    // Removed: Header fixed on scroll logic - now handled by main.js


    // Initialize Swiper for the Hero Carousel Background
    new Swiper('.hero-carousel', {
        loop: true, // Infinite loop
        autoplay: {
            delay: 5000, // 5 seconds between slides
            disableOnInteraction: false, // Continue autoplay after user interaction
        },
        speed: 1000, // Transition speed
        effect: 'fade', // Smooth fade effect
        fadeEffect: {
            crossFade: true,
        },
        // Navigation (Arrows) and Pagination (Dots) are optional and can be uncommented here if visible in HTML
        // navigation: {
        //     nextEl: '.swiper-button-next',
        //     prevEl: '.swiper-button-prev',
        // },
        // pagination: {
        //     el: '.swiper-pagination',
        //     clickable: true,
        // },
        on: {
            init: function () {
                // Initial background image setup if needed (hero background is mostly CSS now)
            },
            slideChangeTransitionEnd: function () {
                // Logic for dynamic background images per slide if you choose to implement
            }
        }
    });

    // Initialize Swiper for Categories
    const categoriesSwiper = new Swiper('.categories-swiper', {
        slidesPerView: 'auto', // Adjust based on card width
        spaceBetween: 15, // Reduced space between category cards
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
        // Responsive breakpoints for categories
        breakpoints: {
            320: {
                slidesPerView: 2,
                spaceBetween: 8
            },
            480: {
                slidesPerView: 3,
                spaceBetween: 10
            },
            768: {
                slidesPerView: 4,
                spaceBetween: 15
            },
            1024: {
                slidesPerView: 5,
                spaceBetween: 20
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
                slidesPerView: 'auto',
                spaceBetween: 20, // Space between vendor cards
                loop: true,
                centeredSlides: false,
                grabCursor: true,
                autoplay: {
                    delay: 4000,
                    disableOnInteraction: false,
                },
                pagination: {
                    el: `.vendors-pagination-${categoryId}`,
                    clickable: true,
                },
                // Responsive breakpoints for vendor carousels
                breakpoints: {
                    320: {
                        slidesPerView: 1.2,
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

    // Removed: Smooth scrolling for anchor links - now handled by main.js
});
