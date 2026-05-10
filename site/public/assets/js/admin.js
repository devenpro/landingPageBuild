// site/public/assets/js/admin.js — admin panel JS.
// 1. Per-row save on /admin/content.php (POSTs to /api/content.php).
// 2. Live filter on the content editor's key list.
// 3. Toast notifications for save success/failure.
//
// CSRF token comes from <meta name="csrf-token"> in the admin layout.

(function () {
    'use strict';

    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

    // ----- Toast helper -----------------------------------------------------
    function toast(message, kind /* 'ok' | 'err' */) {
        let stack = document.getElementById('toast-stack');
        if (!stack) {
            stack = document.createElement('div');
            stack.id = 'toast-stack';
            stack.style.cssText = 'position:fixed;bottom:1rem;right:1rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem;';
            document.body.appendChild(stack);
        }
        const t = document.createElement('div');
        const ok = kind === 'ok';
        t.style.cssText = [
            'padding:.6rem 1rem',
            'border-radius:.5rem',
            'font-size:.875rem',
            'box-shadow:0 4px 16px rgba(0,0,0,.08)',
            'transition:opacity .2s, transform .2s',
            'background:' + (ok ? '#ecfdf5' : '#fef2f2'),
            'color:' + (ok ? '#047857' : '#b91c1c'),
            'border:1px solid ' + (ok ? '#a7f3d0' : '#fecaca'),
        ].join(';');
        t.textContent = message;
        stack.appendChild(t);
        setTimeout(function () {
            t.style.opacity = '0';
            t.style.transform = 'translateY(.5rem)';
            setTimeout(function () { t.remove(); }, 200);
        }, 2400);
    }

    // ----- Per-row save on /admin/content.php -------------------------------
    document.querySelectorAll('.content-row').forEach(function (row) {
        const key = row.getAttribute('data-key');
        const input = row.querySelector('.content-row-input');
        const button = row.querySelector('.content-row-save');
        const status = row.querySelector('.content-row-status');
        if (!key || !input || !button) return;

        const originalValue = input.value;
        let lastSaved = originalValue;

        async function save() {
            const value = input.value;
            if (value === lastSaved) {
                toast('No changes', 'ok');
                return;
            }
            button.disabled = true;
            const originalLabel = button.textContent;
            button.textContent = 'Saving…';

            try {
                const res = await fetch('/api/content.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken,
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ key: key, value: value }),
                });
                let body = null;
                try { body = await res.json(); } catch (_) { /* non-JSON */ }

                if (res.ok && body && body.ok) {
                    lastSaved = value;
                    if (status && body.updated_at) {
                        status.textContent = 'updated ' + body.updated_at;
                    }
                    toast('Saved ' + key, 'ok');
                } else {
                    const msg = (body && body.error) || ('HTTP ' + res.status);
                    toast('Failed: ' + msg, 'err');
                }
            } catch (err) {
                toast('Network error', 'err');
            } finally {
                button.disabled = false;
                button.textContent = originalLabel;
            }
        }

        button.addEventListener('click', save);
        // Cmd/Ctrl+Enter saves the focused row
        input.addEventListener('keydown', function (e) {
            if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
                e.preventDefault();
                save();
            }
        });
    });

    // ----- Live filter on content editor ------------------------------------
    const filter = document.getElementById('content-filter');
    if (filter) {
        const rows = Array.from(document.querySelectorAll('.content-row'));
        const groups = Array.from(document.querySelectorAll('#content-groups > details'));
        filter.addEventListener('input', function () {
            const q = filter.value.trim().toLowerCase();
            rows.forEach(function (row) {
                const key = (row.getAttribute('data-key') || '').toLowerCase();
                row.style.display = (q === '' || key.includes(q)) ? '' : 'none';
            });
            // Hide groups that have no visible rows; expand groups that have matches
            groups.forEach(function (g) {
                const visible = g.querySelectorAll('.content-row:not([style*="display: none"])').length;
                g.style.display = visible > 0 ? '' : 'none';
                if (q !== '' && visible > 0) g.open = true;
            });
        });
    }
})();
