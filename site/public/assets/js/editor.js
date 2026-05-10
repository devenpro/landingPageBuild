// site/public/assets/js/editor.js — inline editor for logged-in admins.
//
// Activated by site/layout.php only when auth_current_user() is non-null.
// Public (logged-out) visitors never receive this file.
//
// Behaviour:
//   - Every [data-edit][data-key] element is interactive.
//   - text  -> click makes it contentEditable; Enter/blur stages the change.
//   - icon  -> click prompts for a Lucide icon name.
//   - image -> click prompts for an image URL (Phase 12 will add a media picker).
//   - video -> click prompts for a video URL.
//
// Staged changes live in window.guaPendingChanges and are batched into a
// single PATCH /api/content.php call when Save is clicked. The endpoint
// accepts {changes:[{key,value},...]}.
//
// Page-scoped writes: if <meta name="content-prefix"> is set (data-driven
// pages), keys are scoped to "<prefix>.<key>" so edits land on the
// page-scoped row, not the global one. File-based pages (no prefix)
// write to the global key, which matches Phase 7's behaviour from the
// admin content editor.

(function () {
    'use strict';

    const csrfMeta   = document.querySelector('meta[name="csrf-token"]');
    const prefixMeta = document.querySelector('meta[name="content-prefix"]');
    const csrfToken  = csrfMeta ? csrfMeta.getAttribute('content') : '';
    const contentPrefix = (prefixMeta && prefixMeta.getAttribute('content')) || '';

    const bar      = document.getElementById('edit-mode-bar');
    const counter  = document.getElementById('edit-mode-counter');
    const prefixUi = document.getElementById('edit-mode-prefix');
    const saveBtn  = document.getElementById('edit-mode-save');
    const discBtn  = document.getElementById('edit-mode-discard');
    if (!bar || !counter || !saveBtn || !discBtn) return;

    if (prefixUi && contentPrefix !== '') {
        prefixUi.textContent = 'scope: ' + contentPrefix;
        prefixUi.classList.remove('hidden');
    }

    /** Map<scopedKey, {value, type}> — last value wins, dedupes repeated edits */
    const pending = new Map();
    window.guaPendingChanges = pending;

    function scopedKey(key) {
        return contentPrefix !== '' ? contentPrefix + '.' + key : key;
    }

    function stage(key, type, value) {
        pending.set(scopedKey(key), { value: value, type: type });
        updateCounter();
    }

    function updateCounter() {
        const n = pending.size;
        counter.textContent = n === 0
            ? 'No unsaved changes'
            : n + ' unsaved change' + (n === 1 ? '' : 's');
        saveBtn.disabled = n === 0;
        discBtn.disabled = n === 0;
        // Warn before navigating away
        window.onbeforeunload = n === 0 ? null : function () { return 'You have unsaved changes.'; };
    }
    updateCounter();

    // ----- Toast --------------------------------------------------------------

    function toast(message, kind /* 'ok' | 'err' */) {
        let stack = document.getElementById('toast-stack');
        if (!stack) {
            stack = document.createElement('div');
            stack.id = 'toast-stack';
            stack.style.cssText = 'position:fixed;bottom:5rem;right:1rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem;';
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

    // ----- Per-element handlers -----------------------------------------------

    document.querySelectorAll('[data-edit][data-key]').forEach(function (el) {
        const type = el.getAttribute('data-edit');
        const key  = el.getAttribute('data-key');
        if (!key) return;

        if (type === 'text') {
            el.setAttribute('tabindex', '0');
            el.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (el.isContentEditable) return;
                el.contentEditable = 'true';
                el.classList.add('is-editing');
                el.focus();
                // Place caret at end
                const range = document.createRange();
                range.selectNodeContents(el);
                range.collapse(false);
                const sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(range);
            });
            el.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    el.blur();
                }
                if (e.key === 'Escape') {
                    e.preventDefault();
                    el.blur();
                }
                if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
                    e.preventDefault();
                    el.blur();
                    saveBtn.click();
                }
            });
            el.addEventListener('blur', function () {
                el.contentEditable = 'false';
                el.classList.remove('is-editing');
                const newValue = el.innerText.replace(/\s+\n/g, '\n').trim();
                stage(key, 'text', newValue);
            });
            return;
        }

        if (type === 'icon') {
            el.style.cursor = 'pointer';
            el.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const current = el.getAttribute('data-lucide') || '';
                const next = window.prompt(
                    'Lucide icon name\n(e.g. calendar-days, sparkles, send — see lucide.dev/icons)',
                    current
                );
                if (next === null || next === current) return;
                const cleaned = next.trim().toLowerCase();
                if (!/^[a-z0-9-]+$/.test(cleaned)) {
                    toast('Invalid icon name', 'err');
                    return;
                }
                el.setAttribute('data-lucide', cleaned);
                stage(key, 'icon', cleaned);
            });
            return;
        }

        if (type === 'image' || type === 'video') {
            el.style.cursor = 'pointer';
            el.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const current = el.getAttribute('src') || '';
                const next = window.prompt(
                    type === 'image'
                        ? 'Image URL or path\n(Phase 12 adds an upload picker)'
                        : 'Video URL or path',
                    current
                );
                if (next === null || next === current) return;
                el.setAttribute('src', next.trim());
                stage(key, type, next.trim());
            });
            return;
        }
    });

    // Click outside any editable while one is being edited -> blur it
    document.addEventListener('click', function (e) {
        const active = document.activeElement;
        if (active && active.matches && active.matches('[data-edit="text"][contenteditable="true"]') && !active.contains(e.target)) {
            active.blur();
        }
    });

    // ----- Save / Discard -----------------------------------------------------

    async function save() {
        if (pending.size === 0) return;
        saveBtn.disabled = true;
        const originalLabel = saveBtn.textContent;
        saveBtn.textContent = 'Saving…';

        const changes = [];
        pending.forEach(function (entry, key) {
            changes.push({ key: key, value: entry.value, type: entry.type });
        });

        try {
            const res = await fetch('/api/content.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ changes: changes }),
            });
            let body = null;
            try { body = await res.json(); } catch (_) {}

            if (res.ok && body && body.ok) {
                const applied  = body.applied  || 0;
                const inserted = body.inserted || 0;
                pending.clear();
                updateCounter();
                let msg = 'Saved ' + applied + ' change' + (applied === 1 ? '' : 's');
                if (inserted > 0) {
                    msg += ' (' + inserted + ' new)';
                }
                toast(msg, 'ok');
                // Reload so the visible page matches the DB exactly — covers
                // icons (where the SVG element wasn't re-rendered), images
                // (where content_blocks src may differ from element src),
                // and any text where editing inserted whitespace differently.
                setTimeout(function () { window.location.reload(); }, 700);
            } else {
                const msg = (body && body.error) || ('HTTP ' + res.status);
                toast('Save failed: ' + msg, 'err');
            }
        } catch (err) {
            toast('Network error', 'err');
        } finally {
            saveBtn.disabled = pending.size === 0;
            saveBtn.textContent = originalLabel;
        }
    }

    function discard() {
        if (pending.size === 0) return;
        if (!window.confirm('Discard ' + pending.size + ' unsaved change' + (pending.size === 1 ? '' : 's') + '?')) return;
        pending.clear();
        updateCounter();
        window.onbeforeunload = null;
        window.location.reload();
    }

    saveBtn.addEventListener('click', save);
    discBtn.addEventListener('click', discard);

    // Cmd/Ctrl+S anywhere in the document saves
    document.addEventListener('keydown', function (e) {
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's') {
            e.preventDefault();
            save();
        }
    });
})();
