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

    // ----- v2 Stage 9: section-aware UX (drag/add/delete + palette) ----------
    // Only data-driven pages emit [data-gua-section] wrappers (file-based
    // pages like home.php don't, by design — their section order is fixed
    // in PHP). When this block finds no wrappers it exits silently.

    const sectionEls = Array.from(document.querySelectorAll('.gua-section[data-gua-section]'));
    if (sectionEls.length === 0) return;

    const pageId = parseInt(sectionEls[0].getAttribute('data-gua-page-id') || '0', 10);
    if (!pageId) return; // no usable page id → bail

    let availableSections = null; // lazy-loaded on first palette open
    let dragging = null;

    function jsonPost(payload) {
        return fetch('/api/sections.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        }).then(async function (res) {
            const body = await res.json().catch(function () { return null; });
            return { ok: res.ok && body && body.ok, body: body, status: res.status };
        });
    }

    function currentOrder() {
        return Array.from(document.querySelectorAll('.gua-section[data-gua-section]'))
            .map(function (el) { return el.getAttribute('data-gua-section'); });
    }

    function persistOrder() {
        return jsonPost({ action: 'reorder', page_id: pageId, sections: currentOrder() })
            .then(function (r) {
                if (r.ok) {
                    toast('Section order saved', 'ok');
                } else {
                    toast((r.body && r.body.error) || ('Reorder failed: ' + r.status), 'err');
                }
                return r;
            });
    }

    function deleteSection(el) {
        const slug = el.getAttribute('data-gua-section');
        const index = parseInt(el.getAttribute('data-gua-section-index') || '0', 10);
        if (!window.confirm('Remove the "' + slug + '" section from this page?')) return;
        jsonPost({ action: 'delete', page_id: pageId, index: index }).then(function (r) {
            if (r.ok) {
                toast('Section removed', 'ok');
                setTimeout(function () { window.location.reload(); }, 400);
            } else {
                toast((r.body && r.body.error) || ('Delete failed: ' + r.status), 'err');
            }
        });
    }

    function openPalette(afterIndex) {
        const overlay = document.createElement('div');
        overlay.className = 'gua-palette-overlay';
        overlay.innerHTML = ''
            + '<div class="gua-palette" role="dialog" aria-modal="true" aria-label="Add a section">'
            + '  <header class="gua-palette__header">'
            + '    <h2>Add a section</h2>'
            + '    <button type="button" class="gua-palette__close" aria-label="Close">&times;</button>'
            + '  </header>'
            + '  <div class="gua-palette__body"><p class="gua-palette__loading">Loading…</p></div>'
            + '</div>';
        document.body.appendChild(overlay);

        function close() { overlay.remove(); document.removeEventListener('keydown', escClose); }
        function escClose(e) { if (e.key === 'Escape') close(); }
        document.addEventListener('keydown', escClose);
        overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
        overlay.querySelector('.gua-palette__close').addEventListener('click', close);

        function render(list) {
            const body = overlay.querySelector('.gua-palette__body');
            body.innerHTML = '';
            const groups = { general: [], layout: [], content_type: [] };
            list.forEach(function (s) { (groups[s.category] || groups.general).push(s); });
            ['general', 'layout', 'content_type'].forEach(function (cat) {
                if (groups[cat].length === 0) return;
                const h = document.createElement('h3');
                h.textContent = cat === 'content_type' ? 'Content-type details' : cat[0].toUpperCase() + cat.slice(1);
                h.className = 'gua-palette__group';
                body.appendChild(h);
                const ul = document.createElement('ul');
                ul.className = 'gua-palette__list';
                groups[cat].forEach(function (s) {
                    const li = document.createElement('li');
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'gua-palette__item';
                    btn.innerHTML = '<span class="gua-palette__slug">' + s.slug + '</span>'
                                  + '<span class="gua-palette__label">' + s.label + '</span>';
                    btn.addEventListener('click', function () {
                        close();
                        jsonPost({
                            action: 'add',
                            page_id: pageId,
                            section: s.slug,
                            after_index: afterIndex,
                        }).then(function (r) {
                            if (r.ok) {
                                toast('Section added — reloading…', 'ok');
                                setTimeout(function () { window.location.reload(); }, 400);
                            } else {
                                toast((r.body && r.body.error) || ('Add failed: ' + r.status), 'err');
                            }
                        });
                    });
                    li.appendChild(btn);
                    ul.appendChild(li);
                });
                body.appendChild(ul);
            });
        }

        function ensureLoaded() {
            if (availableSections) { render(availableSections); return; }
            fetch('/api/sections.php', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            }).then(async function (res) {
                const body = await res.json().catch(function () { return null; });
                if (res.ok && body && body.ok && Array.isArray(body.sections)) {
                    availableSections = body.sections;
                    render(availableSections);
                } else {
                    overlay.querySelector('.gua-palette__body').innerHTML =
                        '<p class="gua-palette__error">Failed to load section list</p>';
                }
            }).catch(function () {
                overlay.querySelector('.gua-palette__body').innerHTML =
                    '<p class="gua-palette__error">Network error</p>';
            });
        }
        ensureLoaded();
    }

    function makeToolbar(el) {
        const slug  = el.getAttribute('data-gua-section');
        const index = parseInt(el.getAttribute('data-gua-section-index') || '0', 10);

        const bar = document.createElement('div');
        bar.className = 'gua-section-toolbar';
        bar.innerHTML = ''
            + '<span class="gua-section-toolbar__handle" draggable="true" title="Drag to reorder">⠿</span>'
            + '<span class="gua-section-toolbar__slug">' + slug + '</span>'
            + '<button type="button" class="gua-section-toolbar__btn" data-action="add" title="Add section after">+</button>'
            + '<button type="button" class="gua-section-toolbar__btn gua-section-toolbar__btn--danger" data-action="delete" title="Delete section">×</button>';

        bar.querySelector('[data-action="add"]').addEventListener('click', function (e) {
            e.stopPropagation();
            openPalette(index);
        });
        bar.querySelector('[data-action="delete"]').addEventListener('click', function (e) {
            e.stopPropagation();
            deleteSection(el);
        });

        const handle = bar.querySelector('.gua-section-toolbar__handle');
        // Native HTML5 drag-and-drop. The handle is the drag source; the
        // <div class="gua-section"> wrappers are both drop targets and the
        // visible drag image. We toggle the dragging marker class on the
        // host element so CSS can style it. dragover on each section
        // determines insertion direction (before/after based on midpoint).
        handle.addEventListener('dragstart', function (e) {
            dragging = el;
            el.classList.add('gua-section--dragging');
            try { e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', slug); } catch (_) {}
            // Use the section element as drag image so the user sees the whole block.
            try { e.dataTransfer.setDragImage(el, 16, 16); } catch (_) {}
        });
        handle.addEventListener('dragend', function () {
            if (dragging) dragging.classList.remove('gua-section--dragging');
            dragging = null;
            document.querySelectorAll('.gua-section--drop-before, .gua-section--drop-after')
                .forEach(function (n) { n.classList.remove('gua-section--drop-before', 'gua-section--drop-after'); });
        });

        el.appendChild(bar);

        el.addEventListener('dragover', function (e) {
            if (!dragging || dragging === el) return;
            e.preventDefault();
            const rect = el.getBoundingClientRect();
            const mid  = rect.top + rect.height / 2;
            el.classList.toggle('gua-section--drop-before', e.clientY < mid);
            el.classList.toggle('gua-section--drop-after',  e.clientY >= mid);
        });
        el.addEventListener('dragleave', function () {
            el.classList.remove('gua-section--drop-before', 'gua-section--drop-after');
        });
        el.addEventListener('drop', function (e) {
            if (!dragging || dragging === el) return;
            e.preventDefault();
            const before = el.classList.contains('gua-section--drop-before');
            el.classList.remove('gua-section--drop-before', 'gua-section--drop-after');
            if (before) el.parentNode.insertBefore(dragging, el);
            else        el.parentNode.insertBefore(dragging, el.nextSibling);
            // Renumber data-gua-section-index after move
            Array.from(document.querySelectorAll('.gua-section[data-gua-section]'))
                .forEach(function (n, i) { n.setAttribute('data-gua-section-index', String(i)); });
            persistOrder();
        });
    }

    sectionEls.forEach(makeToolbar);

    // "Add section" affordance at the very top (before the first section).
    const firstSection = sectionEls[0];
    if (firstSection) {
        const topBtn = document.createElement('button');
        topBtn.type = 'button';
        topBtn.className = 'gua-section-add-top';
        topBtn.textContent = '+ Add section at top';
        topBtn.addEventListener('click', function () { openPalette(-1); });
        firstSection.parentNode.insertBefore(topBtn, firstSection);
    }
})();
