/**
 * SkillSync Universal Form Validation & Input Guard Engine
 * Handles:
 * 1. Global text & character blocking for all numeric value fields
 * 2. Real-time Password Strength Level Indicators
 * 3. Client-side form validations with user-friendly alerts & toasts
 */

(function() {
    'use strict';

    /**
     * Determines if an input field is intended for numeric values only.
     */
    function isNumericField(input) {
        if (!input || !input.tagName || input.tagName !== 'INPUT') return false;
        
        const type = (input.getAttribute('type') || '').toLowerCase();
        const name = (input.getAttribute('name') || '').toLowerCase();
        const id = (input.getAttribute('id') || '').toLowerCase();
        const dataNumeric = input.getAttribute('data-numeric');
        const inputMode = (input.getAttribute('inputmode') || '').toLowerCase();

        if (dataNumeric === 'true' || dataNumeric === '1') return true;
        if (type === 'number' || type === 'tel' || inputMode === 'numeric' || inputMode === 'decimal') return true;

        const numericKeywords = [
            'phone', 'mobile', 'mobileno', 'contactno', 'companycontact',
            'startyear', 'endyear', 'year', 'passingyear', 'gradyear',
            'zip', 'postal', 'postalcode', 'salary', 'salarymin', 'salarymax',
            'experience_years', 'age', 'score', 'percentage', 'duration'
        ];

        // Specific check for 'contact' while excluding contactperson, contactname, etc.
        if (name === 'contact' || id === 'contact' || name === 'companycontact' || id === 'companycontact') return true;

        return numericKeywords.some(keyword => name.includes(keyword) || id.includes(keyword));
    }

    /**
     * Blocks non-numeric keys on keydown.
     */
    function handleNumericKeyDown(e) {
        const input = e.target;
        if (!isNumericField(input)) return;

        // Allow navigation, editing keys, and Ctrl/Cmd combos
        const allowedKeys = [
            'Backspace', 'Delete', 'Tab', 'Escape', 'Enter',
            'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown',
            'Home', 'End'
        ];

        if (allowedKeys.includes(e.key) || e.ctrlKey || e.metaKey) {
            return;
        }

        // Allow decimal point only if data-allow-decimal="true"
        const allowDecimal = input.getAttribute('data-allow-decimal') === 'true';
        if (allowDecimal && (e.key === '.' || e.key === 'Decimal') && !input.value.includes('.')) {
            return;
        }

        // Allow '+' as first char if data-allow-plus="true" (for intl phone numbers)
        const allowPlus = input.getAttribute('data-allow-plus') === 'true';
        if (allowPlus && e.key === '+' && input.selectionStart === 0 && !input.value.includes('+')) {
            return;
        }

        // Block anything that is not a single digit 0-9
        if (!/^[0-9]$/.test(e.key)) {
            e.preventDefault();
        }
    }

    /**
     * Strips non-numeric characters on input (catches IME, drag/drop, edge cases).
     */
    function handleNumericInput(e) {
        const input = e.target;
        if (!isNumericField(input)) return;

        const allowDecimal = input.getAttribute('data-allow-decimal') === 'true';
        const allowPlus = input.getAttribute('data-allow-plus') === 'true';

        let val = input.value;
        let cleaned = '';

        if (allowDecimal) {
            cleaned = val.replace(/[^0-9.]/g, '');
            const parts = cleaned.split('.');
            if (parts.length > 2) {
                cleaned = parts[0] + '.' + parts.slice(1).join('');
            }
        } else if (allowPlus) {
            const hasLeadingPlus = val.startsWith('+');
            cleaned = val.replace(/[^0-9]/g, '');
            if (hasLeadingPlus) cleaned = '+' + cleaned;
        } else {
            cleaned = val.replace(/[^0-9]/g, '');
        }

        // Enforce max length if specified
        const maxDigits = input.getAttribute('data-max-digits');
        if (maxDigits && cleaned.length > parseInt(maxDigits, 10)) {
            cleaned = cleaned.substring(0, parseInt(maxDigits, 10));
        }

        if (val !== cleaned) {
            input.value = cleaned;
        }
    }

    /**
     * Intercepts paste events on numeric fields.
     */
    function handleNumericPaste(e) {
        const input = e.target;
        if (!isNumericField(input)) return;

        e.preventDefault();
        const pastedText = (e.clipboardData || window.clipboardData).getData('text');
        const allowDecimal = input.getAttribute('data-allow-decimal') === 'true';
        const allowPlus = input.getAttribute('data-allow-plus') === 'true';

        let cleaned = '';
        if (allowDecimal) {
            cleaned = pastedText.replace(/[^0-9.]/g, '');
            const parts = cleaned.split('.');
            if (parts.length > 2) {
                cleaned = parts[0] + '.' + parts.slice(1).join('');
            }
        } else if (allowPlus) {
            const hasLeadingPlus = pastedText.trim().startsWith('+');
            cleaned = pastedText.replace(/[^0-9]/g, '');
            if (hasLeadingPlus) cleaned = '+' + cleaned;
        } else {
            cleaned = pastedText.replace(/[^0-9]/g, '');
        }

        const start = input.selectionStart || 0;
        const end = input.selectionEnd || 0;
        const currentVal = input.value;
        const newVal = currentVal.substring(0, start) + cleaned + currentVal.substring(end);

        input.value = newVal;
        input.setSelectionRange(start + cleaned.length, start + cleaned.length);
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }

    /**
     * Determines if an input field is intended for text only (blocks numbers 0-9).
     */
    function isTextOnlyField(input) {
        if (!input || !input.tagName || input.tagName !== 'INPUT') return false;

        const type = (input.getAttribute('type') || '').toLowerCase();
        // Skip non-text inputs
        if (['password', 'email', 'number', 'tel', 'date', 'datetime-local', 'file', 'checkbox', 'radio', 'hidden', 'color', 'range'].includes(type)) {
            return false;
        }

        // If explicitly numeric, do not treat as text-only
        if (isNumericField(input)) return false;

        const dataTextOnly = input.getAttribute('data-text-only') || input.getAttribute('data-alpha');
        if (dataTextOnly === 'true' || dataTextOnly === '1') return true;

        const name = (input.getAttribute('name') || '').toLowerCase();
        const id = (input.getAttribute('id') || '').toLowerCase();

        const textOnlyKeywords = [
            'firstname', 'first_name', 'lastname', 'last_name', 'fullname', 'full_name',
            'contactperson', 'contact_person', 'contactname', 'contact_name',
            'city', 'district', 'province', 'country', 'nationality', 'gender'
        ];

        return textOnlyKeywords.some(keyword => name === keyword || id === keyword || name.includes(keyword) || id.includes(keyword));
    }

    /**
     * Blocks numeric keys on text-only fields on keydown.
     */
    function handleTextOnlyKeyDown(e) {
        const input = e.target;
        if (!isTextOnlyField(input)) return;

        // Allow navigation, editing keys, and Ctrl/Cmd combos
        const allowedKeys = [
            'Backspace', 'Delete', 'Tab', 'Escape', 'Enter',
            'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown',
            'Home', 'End'
        ];

        if (allowedKeys.includes(e.key) || e.ctrlKey || e.metaKey) {
            return;
        }

        // Block anything that is a numeric digit 0-9
        if (/^[0-9]$/.test(e.key)) {
            e.preventDefault();
        }
    }

    /**
     * Strips numbers from text-only fields on input event.
     */
    function handleTextOnlyInput(e) {
        const input = e.target;
        if (!isTextOnlyField(input)) return;

        let val = input.value;
        let cleaned = val.replace(/[0-9]/g, '');

        if (val !== cleaned) {
            input.value = cleaned;
        }
    }

    /**
     * Strips numbers on paste into text-only fields.
     */
    function handleTextOnlyPaste(e) {
        const input = e.target;
        if (!isTextOnlyField(input)) return;

        e.preventDefault();
        const pastedText = (e.clipboardData || window.clipboardData).getData('text');
        const cleaned = pastedText.replace(/[0-9]/g, '');

        const start = input.selectionStart || 0;
        const end = input.selectionEnd || 0;
        const currentVal = input.value;
        const newVal = currentVal.substring(0, start) + cleaned + currentVal.substring(end);

        input.value = newVal;
        input.setSelectionRange(start + cleaned.length, start + cleaned.length);
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }

    /**
     * Password Strength Evaluation
     * Returns score (0 - 4), level text, level color, feedback hints
     */
    window.evaluatePasswordStrength = function(password) {
        if (!password) {
            return {
                score: 0,
                level: 'None',
                label: 'Enter password',
                color: '#6c757d',
                percent: 0,
                hints: []
            };
        }

        let score = 0;
        const hints = [];

        // Check length
        if (password.length >= 8) {
            score++;
        } else {
            hints.push('At least 8 characters');
        }

        // Check lower & uppercase
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) {
            score++;
        } else {
            hints.push('Uppercase & lowercase letters');
        }

        // Check numbers
        if (/[0-9]/.test(password)) {
            score++;
        } else {
            hints.push('At least one numeric digit (0-9)');
        }

        // Check special characters
        if (/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)) {
            score++;
        } else {
            hints.push('At least one special character (!@#$...)');
        }

        // Bonus for length >= 12
        if (password.length >= 12 && score >= 3) {
            score = 4;
        }

        let level = 'Weak';
        let label = 'Weak';
        let color = '#dc2626'; // Red
        let percent = 25;

        switch (score) {
            case 1:
                level = 'Weak';
                label = 'Weak';
                color = '#dc2626'; // Red
                percent = 25;
                break;
            case 2:
                level = 'Fair';
                label = 'Fair / Moderate';
                color = '#f59e0b'; // Amber / Orange
                percent = 50;
                break;
            case 3:
                level = 'Good';
                label = 'Good / Strong';
                color = '#004743'; // SkillSync Brand Dark
                percent = 75;
                break;
            case 4:
                level = 'Strong';
                label = 'Very Strong';
                color = '#10b981'; // Vibrant Emerald Green
                percent = 100;
                break;
            default:
                level = 'Weak';
                label = 'Too Short';
                color = '#dc2626';
                percent = 15;
        }

        return { score, level, label, color, percent, hints };
    };

    /**
     * Initializes Password Strength Indicator UI for a specific password field and confirm field
     */
    window.attachPasswordStrengthIndicator = function(pwdInputId, confirmInputId, indicatorContainerId) {
        const pwdInput = document.getElementById(pwdInputId);
        const confirmInput = confirmInputId ? document.getElementById(confirmInputId) : null;
        const container = document.getElementById(indicatorContainerId);

        if (!pwdInput || !container) return;

        // Build Indicator UI inside container
        container.innerHTML = `
            <div class="password-level-wrapper mt-1 mb-2" style="font-size: 0.78rem;">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted fw-semibold" style="font-size: 0.75rem;">Password Strength:</span>
                    <span class="badge rounded-4px px-2 py-1 font-monospace password-level-badge" style="background-color: #6c757d; color: #fff; font-size: 0.72rem;">Enter password</span>
                </div>
                <div class="progress" style="height: 6px; border-radius: 4px; background-color: #e5e7eb; overflow: hidden;">
                    <div class="progress-bar password-level-bar" role="progressbar" style="width: 0%; background-color: #6c757d; transition: width 0.3s ease, background-color 0.3s ease;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <div class="password-hints text-muted mt-1" style="font-size: 0.72rem; line-height: 1.2;"></div>
                ${confirmInput ? '<div class="password-match-status mt-1 fw-semibold" style="font-size: 0.75rem;"></div>' : ''}
            </div>
        `;

        const badge = container.querySelector('.password-level-badge');
        const bar = container.querySelector('.password-level-bar');
        const hintsEl = container.querySelector('.password-hints');
        const matchEl = container.querySelector('.password-match-status');

        function updateIndicator() {
            const pwd = pwdInput.value;
            const evalRes = window.evaluatePasswordStrength(pwd);

            if (!pwd) {
                badge.style.backgroundColor = '#6c757d';
                badge.style.color = '#fff';
                badge.innerText = 'Enter password';
                bar.style.width = '0%';
                bar.style.backgroundColor = '#6c757d';
                if (hintsEl) hintsEl.innerHTML = '';
            } else {
                badge.style.backgroundColor = evalRes.color;
                badge.style.color = (evalRes.score === 4) ? '#004743' : '#fff';
                if (evalRes.score === 4) badge.style.backgroundColor = '#ACFF78';
                badge.innerText = evalRes.label;
                bar.style.width = evalRes.percent + '%';
                bar.style.backgroundColor = (evalRes.score === 4) ? '#10b981' : evalRes.color;

                if (hintsEl) {
                    if (evalRes.hints.length > 0) {
                        hintsEl.innerHTML = '<span class="text-secondary"><i class="bi bi-info-circle me-1"></i>Needed: ' + evalRes.hints.join(', ') + '</span>';
                    } else {
                        hintsEl.innerHTML = '<span class="text-success"><i class="bi bi-check2-circle me-1"></i>All security requirements met!</span>';
                    }
                }
            }

            // Check confirmation matching
            if (confirmInput && matchEl) {
                const conf = confirmInput.value;
                if (!conf) {
                    matchEl.innerHTML = '';
                } else if (conf === pwd) {
                    matchEl.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Passwords match</span>';
                    confirmInput.style.borderColor = '#10b981';
                } else {
                    matchEl.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>Passwords do not match</span>';
                    confirmInput.style.borderColor = '#dc2626';
                }
            }
        }

        pwdInput.addEventListener('input', updateIndicator);
        if (confirmInput) {
            confirmInput.addEventListener('input', updateIndicator);
        }
    };

    /**
     * Standard Form Validation Handler
     */
    window.validateFormFields = function(form) {
        if (!form) return true;

        const inputs = form.querySelectorAll('input, select, textarea');
        let isValid = true;
        let firstInvalidField = null;
        let errorMessage = '';

        inputs.forEach(input => {
            if (input.disabled || input.type === 'hidden') return;

            const name = (input.getAttribute('name') || '').toLowerCase();
            const val = input.value.trim();
            const isRequired = input.hasAttribute('required');

            // Reset custom validation state
            input.classList.remove('is-invalid');

            // 1. Required Check
            if (isRequired && !val && input.type !== 'checkbox' && input.type !== 'file') {
                isValid = false;
                input.classList.add('is-invalid');
                if (!firstInvalidField) {
                    firstInvalidField = input;
                    const label = input.previousElementSibling?.innerText || input.placeholder || name;
                    errorMessage = `Please complete the required field: ${label.replace('*', '').trim()}`;
                }
                return;
            }

            // Checkbox Required Check
            if (isRequired && input.type === 'checkbox' && !input.checked) {
                isValid = false;
                input.classList.add('is-invalid');
                if (!firstInvalidField) {
                    firstInvalidField = input;
                    errorMessage = 'Please accept the required terms & conditions.';
                }
                return;
            }

            // 2. Email Validation
            if (input.type === 'email' || name.includes('email')) {
                if (val && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
                    isValid = false;
                    input.classList.add('is-invalid');
                    if (!firstInvalidField) {
                        firstInvalidField = input;
                        errorMessage = 'Please enter a valid email address (e.g. name@domain.com).';
                    }
                    return;
                }
            }

            // 3. Numeric & Phone / Mobile Validation
            if (isNumericField(input)) {
                if (name.includes('mobile') || name.includes('phone') || name.includes('contact')) {
                    if (val && val.replace(/[^0-9]/g, '').length < 9) {
                        isValid = false;
                        input.classList.add('is-invalid');
                        if (!firstInvalidField) {
                            firstInvalidField = input;
                            errorMessage = 'Please enter a valid contact phone number (at least 9 digits).';
                        }
                        return;
                    }
                }
                if (name.includes('year') || name.includes('startyear') || name.includes('endyear')) {
                    const yearNum = parseInt(val, 10);
                    if (val && (isNaN(yearNum) || yearNum < 1950 || yearNum > 2035)) {
                        isValid = false;
                        input.classList.add('is-invalid');
                        if (!firstInvalidField) {
                            firstInvalidField = input;
                            errorMessage = 'Please enter a valid 4-digit year (e.g., 2024).';
                        }
                        return;
                    }
                }
            }

            // 4. Password Minimum Length Check
            if (input.type === 'password' && name === 'password') {
                if (val && val.length < 8) {
                    isValid = false;
                    input.classList.add('is-invalid');
                    if (!firstInvalidField) {
                        firstInvalidField = input;
                        errorMessage = 'Password must contain at least 8 characters.';
                    }
                    return;
                }
            }

            // 5. Password Confirmation Matching
            if (input.type === 'password' && (name === 'confirmpassword' || name.includes('confirm'))) {
                const mainPwdInput = form.querySelector('input[type="password"][name="password"]');
                if (mainPwdInput && val !== mainPwdInput.value) {
                    isValid = false;
                    input.classList.add('is-invalid');
                    if (!firstInvalidField) {
                        firstInvalidField = input;
                        errorMessage = 'Passwords do not match.';
                    }
                    return;
                }
            }

            // 6. Text-Only Validation Check (Blocks numbers in text-only fields)
            if (isTextOnlyField(input) && val) {
                if (/[0-9]/.test(val)) {
                    isValid = false;
                    input.classList.add('is-invalid');
                    if (!firstInvalidField) {
                        firstInvalidField = input;
                        const label = input.previousElementSibling?.innerText || input.placeholder || name;
                        errorMessage = `Numbers are not allowed in field: ${label.replace('*', '').trim()}`;
                    }
                    return;
                }
            }
        });

        if (!isValid) {
            if (typeof showToast === 'function') {
                showToast(errorMessage || 'Please fill in all fields correctly.', 'danger');
            } else {
                alert(errorMessage || 'Please fill in all fields correctly.');
            }
            if (firstInvalidField) {
                firstInvalidField.focus();
            }
        }

        return isValid;
    };

    // Attach global listeners on DOMContentLoaded
    document.addEventListener('DOMContentLoaded', function() {
        // Global listeners for numeric-only field guards
        document.addEventListener('keydown', handleNumericKeyDown, true);
        document.addEventListener('input', handleNumericInput, true);
        document.addEventListener('paste', handleNumericPaste, true);

        // Global listeners for text-only field guards (blocking numerics 0-9)
        document.addEventListener('keydown', handleTextOnlyKeyDown, true);
        document.addEventListener('input', handleTextOnlyInput, true);
        document.addEventListener('paste', handleTextOnlyPaste, true);
    });

})();

