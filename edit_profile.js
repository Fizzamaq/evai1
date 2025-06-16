// assets/js/edit_profile.js

document.addEventListener('DOMContentLoaded', function() {
    const multiStepForm = document.getElementById('editProfileMultiStepForm');
    if (!multiStepForm) return;

    const steps = multiStepForm.querySelectorAll('.step-content');
    const stepIndicators = document.querySelectorAll('.step-indicator');
    const nextButtons = multiStepForm.querySelectorAll('.btn-next-step');
    const prevButtons = multiStepForm.querySelectorAll('.btn-prev-step');
    const submitButton = multiStepForm.querySelector('button[type="submit"][name="save_profile_changes"]');
    
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
            if (stepIndex === steps.length - 1) {
                btn.style.display = 'none';
            } else {
                btn.style.display = 'inline-block';
            }
        });

        if (submitButton) {
            if (stepIndex === steps.length - 1) {
                submitButton.style.display = 'inline-block';
            } else {
                submitButton.style.display = 'none';
            }
        }
    }

    function validateStep(stepIndex) {
        let isValid = true;
        const currentStepElement = steps[stepIndex];
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
        if (currentStepElement.id === 'step-business-info') {
            const serviceCheckboxes = currentStepElement.querySelectorAll('input[name="services_offered[]"]');
            let anyServiceSelected = false;
            if (serviceCheckboxes.length > 0) {
                serviceCheckboxes.forEach(checkbox => {
                    if (checkbox.checked) {
                        anyServiceSelected = true;
                    }
                });
                if (!anyServiceSelected) {
                    isValid = false;
                    alert('Please select at least one service you offer.');
                }
            }
        }

        return isValid;
    }

    function nextStep() {
        if (validateStep(currentStep)) {
            currentStep++;
            if (currentStep < steps.length) {
                showStep(currentStep);
            }
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

    // Initial display
    showStep(currentStep);

    // Make step indicators clickable
    stepIndicators.forEach((indicator, index) => {
        indicator.addEventListener('click', () => {
            // Only allow navigating to previous or already completed steps,
            // or the next step if current one is validated.
            if (index < currentStep || (index === currentStep && validateStep(currentStep))) {
                currentStep = index;
                showStep(currentStep);
            } else if (index > currentStep) {
                alert('Please complete the current step before proceeding.');
            }
        });
    });

    // Add submit functionality to the last step's button
    if (submitButton) {
        submitButton.addEventListener('click', function(e) {
            if (!validateStep(currentStep)) {
                e.preventDefault(); // Prevent form submission
                alert('Please fill in all required fields before saving.');
            }
            // If validation passes, form will naturally submit
        });
    }
});