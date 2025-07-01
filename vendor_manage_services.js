document.addEventListener('DOMContentLoaded', function() {
    const tabButtons = document.querySelectorAll('.service-tabs .tab-button');
    const tabContents = document.querySelectorAll('.tab-contents .tab-content');
    const addPackageButtons = document.querySelectorAll('.add-package-btn');
    const cancelPackageFormButtons = document.querySelectorAll('.cancel-package-form-btn');
    const editPackageButtons = document.querySelectorAll('.edit-package-btn');

    // Lightbox elements
    const lightboxOverlay = document.getElementById('lightboxOverlay');
    const lightboxImage = document.getElementById('lightboxImage');
    const lightboxClose = document.getElementById('lightboxClose');

    // --- Tab Switching Logic ---
    function showTab(offeringId) {
        tabButtons.forEach(button => {
            if (button.dataset.offeringId === offeringId) {
                button.classList.add('active');
            } else {
                button.classList.remove('active');
            }
        });

        tabContents.forEach(content => {
            if (content.id === `tab-content-${offeringId}`) {
                content.classList.add('active');
            } else {
                content.classList.remove('active');
            }
        });
        // Update URL without reloading page
        history.replaceState(null, '', `${BASE_URL}public/vendor_manage_services.php?id=${offeringId}`);
    }

    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            showTab(this.dataset.offeringId);
        });
    });

    // Initial tab display based on URL or first item (handled by PHP, but good to have JS fallback)
    const initialOfferingId = new URLSearchParams(window.location.search).get('id');
    if (initialOfferingId) {
        showTab(initialOfferingId);
    } else if (tabButtons.length > 0) {
        // If no ID in URL, activate the first tab
        showTab(tabButtons[0].dataset.offeringId);
    }

    // --- Package Form Management Logic ---

    // Function to reset and hide a package form
    function hidePackageForm(offeringId) {
        const formContainer = document.getElementById(`package-form-modal-${offeringId}`);
        const form = document.getElementById(`package-form-${offeringId}`);
        const formTitle = document.getElementById(`package-form-title-${offeringId}`);
        const currentImagesSection = form.querySelector(`.current-images-section-${offeringId}`);
        const currentImagesGrid = form.querySelector(`#current-package-images-grid-${offeringId}`);
        const newImagesInput = form.querySelector(`#package_new_images_${offeringId}`);
        const newImagePreviewGrid = form.querySelector(`#package-new-image-preview-grid-${offeringId}`);

        form.reset(); // Clear all form fields
        form.elements.action.value = 'add_package'; // Reset action to add
        form.elements.package_id.value = ''; // Clear package ID
        formTitle.textContent = 'Add New Package'; // Reset title
        formContainer.style.display = 'none'; // Hide the form

        // Hide current images section and clear previews
        if (currentImagesSection) currentImagesSection.style.display = 'none';
        if (currentImagesGrid) currentImagesGrid.innerHTML = '';
        if (newImagesInput) newImagesInput.value = ''; // Clear selected files
        if (newImagePreviewGrid) newImagePreviewGrid.innerHTML = '';

        // Reset checkbox state
        const isActiveCheckbox = form.querySelector(`#package_is_active_${offeringId}`);
        if (isActiveCheckbox) isActiveCheckbox.checked = true; // Default to active
    }

    // Event listeners for "Add New Package" buttons
    addPackageButtons.forEach(button => {
        button.addEventListener('click', function() {
            const offeringId = this.dataset.offeringId;
            const formContainer = document.getElementById(`package-form-modal-${offeringId}`);
            hidePackageForm(offeringId); // Ensure it's reset before showing
            formContainer.style.display = 'block'; // Show the form
            formContainer.scrollIntoView({ behavior: 'smooth', block: 'start' }); // Scroll to form
        });
    });

    // Event listeners for "Cancel" buttons on package forms
    cancelPackageFormButtons.forEach(button => {
        button.addEventListener('click', function() {
            const offeringId = this.dataset.offeringId;
            hidePackageForm(offeringId);
        });
    });

    // Event listeners for "Edit" package buttons
    editPackageButtons.forEach(button => {
        button.addEventListener('click', function() {
            const packageId = this.dataset.packageId;
            const offeringId = this.dataset.offeringId;
            const formContainer = document.getElementById(`package-form-modal-${offeringId}`);
            const form = document.getElementById(`package-form-${offeringId}`);
            const formTitle = document.getElementById(`package-form-title-${offeringId}`);
            const currentImagesSection = form.querySelector(`.current-images-section-${offeringId}`);
            const currentImagesGrid = form.querySelector(`#current-package-images-grid-${offeringId}`);
            const newImagesInput = form.querySelector(`#package_new_images_${offeringId}`);
            const newImagePreviewGrid = form.querySelector(`#package-new-image-preview-grid-${offeringId}`);

            // Reset form first
            hidePackageForm(offeringId);

            // Populate form with existing package data
            const offeringData = ALL_SERVICE_OFFERINGS_DATA[offeringId];
            const packageToEdit = offeringData.packages.find(pkg => pkg.id == packageId); // Use == for type coercion

            if (packageToEdit) {
                form.elements.action.value = 'edit_package';
                form.elements.package_id.value = packageToEdit.id;
                formTitle.textContent = `Edit Package: ${packageToEdit.package_name}`;

                form.querySelector(`#package_name_${offeringId}`).value = packageToEdit.package_name;
                form.querySelector(`#package_description_${offeringId}`).value = packageToEdit.package_description;
                form.querySelector(`#package_price_min_${offeringId}`).value = packageToEdit.price_min;
                form.querySelector(`#package_price_max_${offeringId}`).value = packageToEdit.price_max;
                form.querySelector(`#package_display_order_${offeringId}`).value = packageToEdit.display_order;
                form.querySelector(`#package_is_active_${offeringId}`).checked = (packageToEdit.is_active == 1); // Use == for boolean check

                // Display current images
                if (packageToEdit.images && packageToEdit.images.length > 0) {
                    currentImagesSection.style.display = 'block';
                    currentImagesGrid.innerHTML = ''; // Clear previous content
                    packageToEdit.images.forEach(image => {
                        const imageItem = document.createElement('div');
                        imageItem.className = 'current-image-item';
                        imageItem.innerHTML = `
                            <img src="${BASE_URL}${image.image_url}" alt="Package Image">
                            <button type="button" class="delete-image-btn" data-image-id="${image.id}">×</button>
                            <input type="hidden" name="existing_images[${image.id}]" value="keep">
                        `;
                        currentImagesGrid.appendChild(imageItem);
                        // Add event listener to the new delete button
                        addDeleteListenerToImageButton(imageItem.querySelector('.delete-image-btn'));
                    });
                } else {
                    currentImagesSection.style.display = 'none';
                }

                // Clear new image input and preview
                newImagesInput.value = '';
                newImagePreviewGrid.innerHTML = '';

                formContainer.style.display = 'block'; // Show the form
                formContainer.scrollIntoView({ behavior: 'smooth', block: 'start' }); // Scroll to form
            }
        });
    });

    // --- Image Management Logic (for package forms) ---
    // Function to add event listener for delete/undo button on images
    function addDeleteListenerToImageButton(button) {
        button.addEventListener('click', function() {
            const imageItem = this.closest('.current-image-item');
            const imageId = this.dataset.imageId;
            const hiddenInput = imageItem.querySelector(`input[name="existing_images[${imageId}]"]`);

            if (hiddenInput) {
                if (hiddenInput.value === 'keep') { // Mark for deletion
                    hiddenInput.value = 'delete';
                    imageItem.style.opacity = '0.5';
                    imageItem.style.border = '1px solid red';
                    this.textContent = 'Undo';
                    this.classList.remove('delete-image-btn');
                    this.classList.add('undo-delete-image-btn');
                } else { // Undo deletion
                    hiddenInput.value = 'keep';
                    imageItem.style.opacity = '1';
                    imageItem.style.border = '1px solid var(--border-color)';
                    this.textContent = '×';
                    this.classList.remove('undo-delete-image-btn');
                    this.classList.add('delete-image-btn');
                }
            }
        });
    }

    // Apply listeners to all current delete/undo buttons initially (for all tabs)
    document.querySelectorAll('.current-images-grid .delete-image-btn').forEach(addDeleteListenerToImageButton);
    document.querySelectorAll('.current-images-grid .undo-delete-image-btn').forEach(addDeleteListenerToImageButton);

    // Logic for previewing new images for each package form
    document.querySelectorAll('input[name="new_images[]"]').forEach(newImagesInput => {
        newImagesInput.addEventListener('change', function() {
            const formId = this.closest('form').id;
            const offeringId = formId.replace('package-form-', '');
            const newImagePreviewGrid = document.getElementById(`package-new-image-preview-grid-${offeringId}`);
            newImagePreviewGrid.innerHTML = ''; // Clear previous previews
            const newFiles = this.files;
            const maxTotalImages = 10; // Max allowed images including existing ones

            // Count currently kept existing images for this specific form
            let keptExistingImagesCount = 0;
            document.querySelectorAll(`#${formId} .current-image-item input[type="hidden"]`).forEach(input => {
                if (input.value === 'keep') {
                    keptExistingImagesCount++;
                }
            });

            if (newFiles.length + keptExistingImagesCount > maxTotalImages) {
                alert(`You can only have a maximum of ${maxTotalImages} images in total (including existing ones) for this package. Please select fewer new images.`);
                this.value = ''; // Clear selected files
                return;
            }

            if (newFiles) {
                Array.from(newFiles).forEach(file => {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const previewItem = document.createElement('div');
                        previewItem.className = 'image-preview-item';
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        previewItem.appendChild(img);
                        newImagePreviewGrid.appendChild(previewItem);
                    };
                    reader.readAsDataURL(file);
                });
            }
        });
    });

    // --- Form Validation (for all forms on the page) ---
    document.querySelectorAll('.tab-content form').forEach(form => {
        form.addEventListener('submit', function(event) {
            // Validate price ranges (for both overall service and packages)
            const priceMinInput = this.querySelector('input[name="price_min"]');
            const priceMaxInput = this.querySelector('input[name="price_max"]');
            
            if (priceMinInput && priceMaxInput) {
                const priceMin = parseFloat(priceMinInput.value);
                const priceMax = parseFloat(priceMaxInput.value);

                if (!isNaN(priceMin) && !isNaN(priceMax) && priceMin > priceMax) {
                    alert('Minimum price cannot be greater than maximum price.');
                    priceMinInput.focus();
                    event.preventDefault();
                    return;
                }
            }

            // Validate package name for package forms
            if (this.elements.action && (this.elements.action.value === 'add_package' || this.elements.action.value === 'edit_package')) {
                const packageNameInput = this.querySelector('input[name="package_name"]');
                if (!packageNameInput.value.trim()) {
                    alert('Package Name is required.');
                    packageNameInput.focus();
                    event.preventDefault();
                    return;
                }
            }
        });
    });

    // --- Lightbox Functionality ---
    const lightboxTriggers = document.querySelectorAll('.lightbox-trigger'); // Select elements that open lightbox

    lightboxTriggers.forEach(trigger => {
        trigger.addEventListener('click', function(e) {
            e.preventDefault(); // Prevent default link behavior or other actions
            
            // Get image URLs from data-images attribute (JSON string)
            const imageUrlsJson = this.dataset.images;
            let imageUrls = [];
            try {
                imageUrls = JSON.parse(imageUrlsJson);
            } catch (error) {
                console.error("Error parsing image URLs for lightbox:", error);
                return; // Exit if parsing fails
            }

            if (imageUrls.length === 0) {
                return; // No images to display
            }

            // Set the first image as the initial image
            lightboxImage.src = BASE_URL + imageUrls[0];
            lightboxOverlay.classList.add('active'); // Show lightbox
            document.body.style.overflow = 'hidden'; // Prevent background scrolling

            // Optional: Implement a simple carousel within the lightbox if multiple images
            // For now, it just shows the first image. You could add Swiper here for a full gallery.
        });
    });

    // Close lightbox when clicking close button
    lightboxClose.addEventListener('click', function() {
        lightboxOverlay.classList.remove('active');
        document.body.style.overflow = ''; // Restore scrolling
    });

    // Close lightbox when clicking outside the image (on the overlay)
    lightboxOverlay.addEventListener('click', function(e) {
        if (e.target === lightboxOverlay) { // Only close if clicked directly on overlay, not the image itself
            lightboxOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    });

    // Close lightbox with ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && lightboxOverlay.classList.contains('active')) {
            lightboxOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    });

});