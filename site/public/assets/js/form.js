// site/public/assets/js/form.js — vanilla JS waitlist form handler.
// Submits as JSON to /api/form.php, swaps the form for the success card
// on 200, surfaces field errors on 422, generic error on anything else.
// Falls back to a normal form POST if JS fails to load (the form has a
// real action="/api/form.php" attribute).

(function () {
    'use strict';

    const form = document.getElementById('waitlist-form');
    if (!form) return;

    const errorBox = document.getElementById('form-error');
    const successBox = document.getElementById('form-success');
    const submitBtn = document.getElementById('waitlist-submit');
    const submitLabel = document.getElementById('waitlist-submit-label');
    const originalLabel = submitLabel ? submitLabel.textContent : 'Submit';

    function showError(message) {
        if (!errorBox) return;
        errorBox.textContent = message;
        errorBox.classList.remove('hidden');
    }

    function clearError() {
        if (!errorBox) return;
        errorBox.textContent = '';
        errorBox.classList.add('hidden');
    }

    function clearFieldErrors() {
        form.querySelectorAll('[data-field-error]').forEach(function (el) {
            el.remove();
        });
        form.querySelectorAll('.is-invalid').forEach(function (el) {
            el.classList.remove('is-invalid', 'border-red-400', 'ring-red-200');
        });
    }

    function showFieldError(name, message) {
        const input = form.elements.namedItem(name);
        if (!input) return;
        input.classList.add('is-invalid', 'border-red-400');
        const note = document.createElement('p');
        note.setAttribute('data-field-error', '');
        note.className = 'mt-1 text-xs text-red-600';
        note.textContent = message;
        input.parentNode.appendChild(note);
    }

    function setSubmitting(isSubmitting) {
        if (!submitBtn) return;
        submitBtn.disabled = isSubmitting;
        if (submitLabel) {
            submitLabel.textContent = isSubmitting ? 'Sending…' : originalLabel;
        }
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        clearError();
        clearFieldErrors();
        setSubmitting(true);

        const data = new FormData(form);

        try {
            const res = await fetch(form.action, {
                method: 'POST',
                body: data,
                headers: { 'X-Requested-With': 'fetch', 'Accept': 'application/json' },
                credentials: 'same-origin',
            });

            let body = null;
            try { body = await res.json(); } catch (_) { /* non-JSON */ }

            if (res.ok && body && body.ok) {
                form.hidden = true;
                if (successBox) {
                    successBox.hidden = false;
                    const reduceMotion = window.matchMedia
                        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                    successBox.scrollIntoView({
                        behavior: reduceMotion ? 'auto' : 'smooth',
                        block: 'center',
                    });
                }
                return;
            }

            if (res.status === 422 && body && body.errors) {
                Object.keys(body.errors).forEach(function (k) {
                    showFieldError(k, body.errors[k]);
                });
                showError('Please fix the highlighted fields and try again.');
            } else if (body && body.error) {
                showError(body.error);
            } else {
                showError('Something went wrong. Please try again.');
            }
        } catch (err) {
            showError('Network error. Check your connection and try again.');
        } finally {
            setSubmitting(false);
        }
    });

    // Light client-side validation hint on blur — clears the error if the
    // input is now valid. Server is still authoritative.
    form.querySelectorAll('input, select, textarea').forEach(function (el) {
        el.addEventListener('blur', function () {
            if (el.classList.contains('is-invalid') && el.checkValidity()) {
                el.classList.remove('is-invalid', 'border-red-400');
                const note = el.parentNode.querySelector('[data-field-error]');
                if (note) note.remove();
            }
        });
    });
})();
