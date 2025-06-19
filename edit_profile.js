// assets/js/edit_profile.js

document.addEventListener('DOMContentLoaded', function() {
    const multiStepForm = document.getElementById('editProfileMultiStepForm');
    if (!multiStepForm) return;

    const steps = multiStepForm.querySelectorAll('.step-content');
    const stepIndicators = document.querySelectorAll('.step-indicator');
    const nextButtons = multiStepForm.querySelectorAll('.btn-next-step');
    const prevButtons = multiStepForm.querySelectorAll('.btn-prev-step');
    const submitButton = multiStepForm.querySelector('button[type="submit"][name="save_profile_changes"]');

    // Determine if the user is a vendor based on the presence of vendor-specific steps
    const isVendor = steps.length > 1;

    let currentStep = 0; // 0-indexed

    function showStep(stepIndex) {
        steps.forEach((step, index) => {
            if (index === stepIndex) {
                step.classList.add('active');
            } else {
                step.classList.remove('active');
            }
        });
        updateStepIndicators(stepIndex);
        updateButtonVisibility(stepIndex);
    }

    function updateStepIndicators(stepIndex) {
        stepIndicators.forEach((indicator, index) => {
            indicator.classList.remove('active', 'completed');
            if (index === stepIndex) {
                indicator.classList.add('active');
            } else if (index < stepIndex) {
                indicator.classList.add('completed');
            }
        });
    }

    function updateButtonVisibility(stepIndex) {
        // Show/hide prev button
        prevButtons.forEach(btn => {
            if (stepIndex === 0) {
                btn.style.display = 'none';
            } else {
                btn.style.display = 'inline-block';
            }
        });

        // Show/hide next/submit buttons
        nextButtons.forEach(btn => {
            if (stepIndex === steps.length - 1) { // If it's the last step
                btn.style.display = 'none';
            } else {
                btn.style.display = 'inline-block';
            }
        });

        if (submitButton) {
            if (stepIndex === steps.length - 1) { // Only show submit on the last step
                submitButton.style.display = 'inline-block';
            } else {
                submitButton.style.display = 'none';
            }
        }
    }

    function validateStep(stepIndex) {
        let isValid = true;
        const currentStepElement = steps[stepIndex];
        // Select only required inputs, selects, and textareas within the current step
        const inputs = currentStepElement.querySelectorAll('input[required], select[required], textarea[required]');

        inputs.forEach(input => {
            if (!input.value.trim()) {
                isValid = false;
                input.style.borderColor = 'var(--error-color)';
            } else {
                input.style.borderColor = 'var(--border-color)';
            }
        });

        // Specific validation for vendor services if it's the services step
        // This step is always the last for vendors (index 2)
        if (isVendor && stepIndex === 2) { // Assuming step 3 is index 2 for vendors
            const serviceCheckboxes = currentStepElement.querySelectorAll('input[name^="services_offered["][type="checkbox"]');
            let anyServiceSelected = false;
            if (serviceCheckboxes.length > 0) {
                serviceCheckboxes.forEach(checkbox => {
                    if (checkbox.checked) {
                        anyServiceSelected = true;
                        // Optional: Validate price inputs if they are required when checked
                        // const serviceId = checkbox.dataset.serviceId;
                        // const minPriceInput = document.getElementById(`service_${serviceId}_min`);
                        // const maxPriceInput = document.getElementById(`service_${serviceId}_max`);
                        // Add validation for minPriceInput.value and maxPriceInput.value if needed
                    }
                });
                // The user did not specify that at least one service must be selected.
                // If it were a requirement, uncomment the following block:
                // if (!anyServiceSelected) {
                //     isValid = false;
                //     alert('Please select at least one service you offer.');
                // }
            }
        }

        return isValid;
    }

    function nextStep() {
        // For non-vendor users, there's only one step, and "Next Step" is replaced by "Save Changes"
        // so this function should only execute for vendor users or if logic implies multiple steps
        if (isVendor && validateStep(currentStep)) {
            currentStep++;
            if (currentStep < steps.length) {
                showStep(currentStep);
            }
        } else if (!isVendor) {
            // For non-vendor users, the 'Next Step' button is actually 'Save Changes'
            // and it should directly trigger form submission after validation.
            // This case is handled by setting type="submit" for the non-vendor button.
            // If this part of JS is reached for a non-vendor, it means something is off.
            if (validateStep(currentStep)) {
                multiStepForm.submit(); // Manually submit form for non-vendor if their single step button is "Next Step"
            } else {
                alert('Please fill in all required fields.');
            }
            return;
        } else {
            alert('Please fill in all required fields for this step.');
        }
    }


    function prevStep() {
        currentStep--;
        showStep(currentStep);
    }

    // Attach event listeners
    nextButtons.forEach(button => button.addEventListener('click', nextStep));
    prevButtons.forEach(button => button.addEventListener('click', prevStep));

    // Make step indicators clickable
    stepIndicators.forEach((indicator, index) => {
        indicator.addEventListener('click', () => {
            // Only allow navigating to previous or already completed steps,
            // or the next step if current one is validated.
            // If the user clicks on a future step, ensure current step is valid.
            if (index < currentStep || (index === currentStep) || (index === currentStep + 1 && validateStep(currentStep))) {
                currentStep = index;
                showStep(currentStep);
            } else if (index > currentStep) {
                alert('Please complete the current step before proceeding to a future step.');
            }
        });
    });

    // Handle form submission for the last step (for vendors)
    // The non-vendor form's submit button is type="submit" and handled natively.
    if (submitButton && isVendor) {
        submitButton.addEventListener('click', function(e) {
            if (!validateStep(currentStep)) {
                e.preventDefault(); // Prevent form submission
                alert('Please fill in all required fields before saving.');
            }
            // If validation passes, form will naturally submit
        });
    }

    // NEW: Add event listeners for service checkboxes to show/hide price inputs
    const serviceCheckboxes = multiStepForm.querySelectorAll('input[name^="services_offered["][type="checkbox"]');

    serviceCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const serviceId = this.dataset.serviceId;
            const priceInputsDiv = document.getElementById(`price_inputs_${serviceId}`);
            if (priceInputsDiv) {
                if (this.checked) {
                    priceInputsDiv.style.display = 'block';
                } else {
                    priceInputsDiv.style.display = 'none';
                    // Clear values when unchecked
                    priceInputsDiv.querySelectorAll('input[type="number"]').forEach(input => input.value = '');
                }
            }
        });
    });

    // NEW: Initial display logic: ensure price inputs are visible for initially checked checkboxes
    // This runs on page load to set the correct state based on existing data or form persistence
    serviceCheckboxes.forEach(checkbox => {
        const serviceId = checkbox.dataset.serviceId;
        const priceInputsDiv = document.getElementById(`price_inputs_${serviceId}`);
        if (priceInputsDiv && checkbox.checked) {
            priceInputsDiv.style.display = 'block';
        }
    });


    // Initial display
    showStep(currentStep);
});
