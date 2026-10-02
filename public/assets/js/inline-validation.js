(function () {
    function getErrorElement(field) {
        const describedBy = field.getAttribute('aria-describedby');
        return describedBy ? document.getElementById(describedBy) : null;
    }

    function valueSignature(field) {
        if (field.type === 'file') {
            return Array.from(field.files || []).map(file => `${file.name}:${file.size}`).join('|');
        }
        if (field.type === 'checkbox' || field.type === 'radio') {
            return field.checked ? field.value || 'checked' : '';
        }
        if (field.matches('[data-validation-group="payment_method"]')) {
            const selected = field.querySelector('input[name="payment_method"]:checked');
            return selected ? selected.value : '';
        }
        return field.value;
    }

    function filesAreValid(field, message) {
        const files = Array.from(field.files || []);
        const minimumMatch = message.match(/at least\s+(\d+)\s+images?/i);
        if (minimumMatch && files.length < Number(minimumMatch[1])) {
            return false;
        }

        const maximumMatch = message.match(/only\s+(\d+)\s+more/i);
        if (maximumMatch && files.length > Number(maximumMatch[1])) {
            return false;
        }

        if (message.toLowerCase().includes('larger than') || message.toLowerCase().includes('too large')) {
            const sizeMatch = message.match(/smaller than\s+([\d.]+)\s*(bytes|kb|mb)/i);
            if (sizeMatch) {
                const units = { bytes: 1, kb: 1024, mb: 1024 * 1024 };
                const maxBytes = Number(sizeMatch[1]) * units[sizeMatch[2].toLowerCase()];
                if (files.some(file => file.size > maxBytes)) {
                    return false;
                }
            }
        }

        if (message.toLowerCase().includes('only jpg') || message.toLowerCase().includes('not a jpg')) {
            const allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (files.some(file => !allowed.includes(file.type))) {
                return false;
            }
        }

        return files.length > 0;
    }

    function errorIsResolved(field, message) {
        const text = message.toLowerCase();
        const value = valueSignature(field).trim();

        if (text.includes('is required')) {
            if (field.type === 'file') {
                return filesAreValid(field, message);
            }
            if (field.type === 'email') {
                return field.value.trim() !== '' && field.validity.valid;
            }
            if (field.type === 'number') {
                return field.value !== '' && field.validity.valid;
            }
            if (field.name === 'billing_phone') {
                return /^[+0-9().\-\s]{7,25}$/.test(field.value.trim());
            }
            if (field.tagName === 'SELECT') {
                return value !== '' && value !== '0';
            }
            if (field.type === 'checkbox') {
                return field.checked;
            }
            return value !== '' && value !== '0';
        }
        if (text.includes('choose at least')) {
            return filesAreValid(field, message);
        }
        if (text.includes('valid email')) {
            return field.value.trim() !== '' && field.validity.valid;
        }
        if (text.includes('must be at least')) {
            const minimumMatch = text.match(/at least\s+(\d+)\s+characters?/);
            return minimumMatch && field.value.length >= Number(minimumMatch[1]);
        }
        if (text.includes('passwords do not match')) {
            const password = field.form.querySelector('[name="password"]');
            return password && password.value === field.value && field.value !== '';
        }
        if (text.includes('must be a number')) {
            return value !== '' && Number.isFinite(Number(value));
        }
        if (text.includes('cannot be negative')) {
            return value !== '' && Number(value) >= 0;
        }
        if (text.includes('valid phone number')) {
            return /^[+0-9().\-\s]{7,25}$/.test(field.value.trim());
        }
        if (text.includes('select a category')) {
            return value !== '' && value !== '0';
        }
        if (text.includes('supported payment method')) {
            return ['cod', 'stripe'].includes(value);
        }
        if (text.includes('stripe sandbox is not configured')) {
            return value === 'cod';
        }
        if (field.type === 'file') {
            return filesAreValid(field, message);
        }
        if (field.type === 'email') {
            return value !== '' && field.validity.valid;
        }
        return value !== '';
    }

    document.querySelectorAll('form').forEach(function (form) {
        const invalidFields = Array.from(form.querySelectorAll('[aria-invalid="true"]'));
        const initialValues = new Map(invalidFields.map(field => [field, valueSignature(field)]));

        function refreshErrors() {
            invalidFields.forEach(function (field) {
                const errorElement = getErrorElement(field);
                if (!errorElement || valueSignature(field) === initialValues.get(field)) {
                    return;
                }

                const messages = Array.from(errorElement.querySelectorAll('span'))
                    .map(message => message.textContent.trim());
                if (!messages.length || !messages.every(message => errorIsResolved(field, message))) {
                    return;
                }

                field.classList.remove('is-invalid');
                field.removeAttribute('aria-invalid');
                field.removeAttribute('aria-describedby');
                const outlinedGroup = field.closest('.input-group-outline.is-invalid');
                if (outlinedGroup) {
                    outlinedGroup.classList.remove('is-invalid');
                }
                errorElement.remove();
            });
        }

        form.addEventListener('input', refreshErrors);
        form.addEventListener('change', refreshErrors);
    });
})();