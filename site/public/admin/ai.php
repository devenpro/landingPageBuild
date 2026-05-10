<?php
// site/public/admin/ai.php — admin AI tools (Phase 11).
//
// Two tools share this page:
//   1. Suggest pages — "given my business brief, what pages should I add?"
//      → POSTs to /api/ai/suggest.php, renders an inline list. Each
//        suggestion has a "Use as draft" button that prefills the
//        Generate form below.
//   2. Generate page — "build me a draft for slug X about Y"
//      → POSTs to /api/ai/generate.php, server creates a draft pages
//        row + page-scoped content_blocks rows, response returns a link
//        to the new draft for editing.
//
// Both tools call the configured default provider (AI_DEFAULT_PROVIDER
// in .env) unless an admin overrides per-call. The default is shown in
// the page header so admins know which provider their spend is hitting.

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';
require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../../../core/lib/ai/client.php';
require_once __DIR__ . '/../../../core/lib/ai/keys.php';

auth_require_login();

$default_provider = ai_default_provider();

// Master-key health (same banner as /admin/ai-keys.php) — calls without
// a master key will throw before doing useful work, so warn upfront.
$master_key_ok  = false;
$master_key_err = null;
try {
    require_once __DIR__ . '/../../../core/lib/crypto.php';
    crypto_master_key();
    $master_key_ok = true;
} catch (Throwable $e) {
    $master_key_err = $e->getMessage();
}

// Count keys per provider so we can warn if no key is on file for the default.
$key_counts = [];
if ($master_key_ok) {
    $rows = db()->query('SELECT provider, COUNT(*) AS n FROM ai_provider_keys GROUP BY provider')->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($rows as $p => $n) $key_counts[(string)$p] = (int)$n;
}
$has_default_key = ($key_counts[$default_provider] ?? 0) > 0;

admin_head('AI tools', 'ai_tools');
?>
    <div class="flex items-baseline justify-between">
        <h1 class="text-2xl font-semibold tracking-tight text-ink-900">AI tools</h1>
        <span class="text-sm text-ink-500">
            Provider: <code class="rounded bg-ink-100 px-1.5 py-0.5 text-ink-800"><?= e($default_provider) ?></code>
        </span>
    </div>
    <p class="mt-2 text-ink-600">Suggest pages your site is missing, or generate a draft page from a brief. Drafts land in <a href="/admin/pages.php" class="text-brand-700 hover:underline">Pages</a> with status “draft” — review and publish from there.</p>

    <?php if (!$master_key_ok): ?>
        <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <div class="font-medium">Master key not configured</div>
            <div class="mt-1"><?= e((string)$master_key_err) ?></div>
            <div class="mt-2">Set <code>AI_KEYS_MASTER_KEY</code> in <code>.env</code>, then add a provider key on <a href="/admin/ai-keys.php" class="underline">AI keys</a>.</div>
        </div>
    <?php elseif (!$has_default_key): ?>
        <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            No <strong><?= e($default_provider) ?></strong> key on file. Add one on <a href="/admin/ai-keys.php" class="underline">AI keys</a> before running these tools, or change <code>AI_DEFAULT_PROVIDER</code> in <code>.env</code> to a provider you do have a key for.
        </div>
    <?php endif; ?>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">

        <!-- ============================ Suggest pages ============================ -->
        <section class="rounded-2xl border border-ink-100 bg-white">
            <header class="border-b border-ink-100 px-4 py-3">
                <h2 class="text-sm font-medium text-ink-700">Suggest pages</h2>
                <p class="mt-1 text-xs text-ink-500">Paste a paragraph about your business; get a shortlist of pages worth building.</p>
            </header>
            <form id="suggest-form" class="space-y-3 p-4">
                <label class="block text-xs font-medium text-ink-600">
                    Brief
                    <textarea name="brief" rows="5" maxlength="4000" required
                              placeholder="E.g. We're a Bangalore-based SEO agency for D2C founders; flagship product is a 30-day audit-and-fix sprint."
                              class="mt-1 block w-full rounded-md border border-ink-200 bg-white px-3 py-2 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-200"></textarea>
                </label>
                <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-md bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:bg-ink-300">
                    Suggest pages
                </button>
            </form>
            <div id="suggest-result" class="border-t border-ink-100 px-4 py-3 text-sm" hidden></div>
        </section>

        <!-- ============================ Generate page ============================ -->
        <section class="rounded-2xl border border-ink-100 bg-white">
            <header class="border-b border-ink-100 px-4 py-3">
                <h2 class="text-sm font-medium text-ink-700">Generate page</h2>
                <p class="mt-1 text-xs text-ink-500">Create a draft hero + features + CTA from a brief. Lands as a draft you can review and publish.</p>
            </header>
            <form id="generate-form" class="space-y-3 p-4">
                <label class="block text-xs font-medium text-ink-600">
                    Slug (optional)
                    <input type="text" name="slug" maxlength="200"
                           placeholder="e.g. services/seo-audit (leave blank to let AI pick)"
                           class="mt-1 block w-full rounded-md border border-ink-200 bg-white px-3 py-2 text-sm placeholder:text-ink-300 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-200">
                </label>
                <label class="block text-xs font-medium text-ink-600">
                    Brief
                    <textarea name="brief" rows="5" maxlength="4000" required
                              placeholder="What's the page about? Who's it for? What action should visitors take?"
                              class="mt-1 block w-full rounded-md border border-ink-200 bg-white px-3 py-2 text-sm focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-200"></textarea>
                </label>
                <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-md bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:bg-ink-300">
                    Generate draft
                </button>
            </form>
            <div id="generate-result" class="border-t border-ink-100 px-4 py-3 text-sm" hidden></div>
        </section>

    </div>

    <script>
    (function () {
        'use strict';
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        function toast(msg, kind) {
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
                'padding:.6rem 1rem','border-radius:.5rem','font-size:.875rem',
                'box-shadow:0 4px 16px rgba(0,0,0,.08)',
                'background:' + (ok ? '#ecfdf5' : '#fef2f2'),
                'color:' + (ok ? '#047857' : '#b91c1c'),
                'border:1px solid ' + (ok ? '#a7f3d0' : '#fecaca'),
            ].join(';');
            t.textContent = msg;
            stack.appendChild(t);
            setTimeout(() => t.remove(), 3500);
        }

        function escapeHtml(s) {
            return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        }

        // --------- Suggest ---------
        const sForm = document.getElementById('suggest-form');
        const sOut  = document.getElementById('suggest-result');
        const gForm = document.getElementById('generate-form');

        sForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const brief = new FormData(sForm).get('brief');
            const btn = sForm.querySelector('button[type="submit"]');
            btn.disabled = true; const orig = btn.textContent; btn.textContent = 'Thinking…';
            sOut.hidden = false;
            sOut.innerHTML = '<div class="text-ink-500">Calling provider, this can take 5–15s for the first call…</div>';
            try {
                const res = await fetch('/api/ai/suggest.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                    credentials: 'same-origin',
                    body: JSON.stringify({ brief: brief }),
                });
                const body = await res.json().catch(() => null);
                if (!res.ok || !body || !body.ok) {
                    const err = (body && body.error) || ('HTTP ' + res.status);
                    sOut.innerHTML = '<div class="text-red-700">' + escapeHtml(err) + '</div>';
                    return;
                }
                const list = body.suggestions || [];
                if (list.length === 0) {
                    sOut.innerHTML = '<div class="text-ink-500">Model returned no suggestions. Try a longer brief.</div>';
                    return;
                }
                const usage = body.usage || {};
                let html = '<div class="mb-2 text-xs text-ink-400">' +
                    escapeHtml(usage.provider || '') + ' · ' + escapeHtml(usage.model || '') +
                    ' · ' + (usage.tokens_in || 0) + ' in / ' + (usage.tokens_out || 0) + ' out' +
                    '</div><ul class="divide-y divide-ink-100">';
                for (const s of list) {
                    html += '<li class="py-3">' +
                        '<div class="flex items-baseline justify-between gap-3">' +
                            '<div>' +
                                '<code class="rounded bg-ink-100 px-1.5 py-0.5 text-xs text-ink-700">/' + escapeHtml(s.slug) + '</code> ' +
                                '<span class="ml-1 font-medium text-ink-900">' + escapeHtml(s.title) + '</span>' +
                            '</div>' +
                            '<button type="button" class="suggest-use rounded-md border border-ink-200 bg-white px-2.5 py-1 text-xs text-ink-700 hover:border-brand-300 hover:bg-brand-50" data-slug="' + escapeHtml(s.slug) + '" data-title="' + escapeHtml(s.title) + '" data-desc="' + escapeHtml(s.description) + '">' +
                                'Use as draft' +
                            '</button>' +
                        '</div>' +
                        (s.description ? '<div class="mt-1 text-ink-700">' + escapeHtml(s.description) + '</div>' : '') +
                        (s.why ? '<div class="mt-1 text-xs text-ink-400">' + escapeHtml(s.why) + '</div>' : '') +
                        '</li>';
                }
                html += '</ul>';
                sOut.innerHTML = html;

                // Wire "Use as draft" buttons → prefill Generate form.
                sOut.querySelectorAll('.suggest-use').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const slugEl  = gForm.querySelector('[name="slug"]');
                        const briefEl = gForm.querySelector('[name="brief"]');
                        if (slugEl)  slugEl.value  = btn.getAttribute('data-slug') || '';
                        if (briefEl) briefEl.value = btn.getAttribute('data-title') + '. ' + (btn.getAttribute('data-desc') || '');
                        briefEl?.focus();
                        briefEl?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        toast('Prefilled below — review and click Generate', 'ok');
                    });
                });
            } catch (err) {
                sOut.innerHTML = '<div class="text-red-700">Network error: ' + escapeHtml(err.message) + '</div>';
            } finally {
                btn.disabled = false; btn.textContent = orig;
            }
        });

        // --------- Generate ---------
        const gOut = document.getElementById('generate-result');
        gForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(gForm);
            const payload = {
                brief: fd.get('brief'),
                slug:  (fd.get('slug') || '').toString().trim(),
            };
            if (!payload.slug) delete payload.slug;
            const btn = gForm.querySelector('button[type="submit"]');
            btn.disabled = true; const orig = btn.textContent; btn.textContent = 'Generating…';
            gOut.hidden = false;
            gOut.innerHTML = '<div class="text-ink-500">Calling provider, this can take 10–30s…</div>';
            try {
                const res = await fetch('/api/ai/generate.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload),
                });
                const body = await res.json().catch(() => null);
                if (!res.ok || !body || !body.ok) {
                    const err = (body && body.error) || ('HTTP ' + res.status);
                    gOut.innerHTML = '<div class="text-red-700">' + escapeHtml(err) + '</div>';
                    return;
                }
                const usage = body.usage || {};
                gOut.innerHTML = '<div class="text-emerald-700 font-medium">Draft created.</div>' +
                    '<div class="mt-1 text-ink-700">Slug <code class="rounded bg-ink-100 px-1.5 py-0.5 text-xs">/' + escapeHtml(body.slug) + '</code> · ' + (body.keys_written || 0) + ' content keys written</div>' +
                    '<div class="mt-2 flex flex-wrap gap-2">' +
                        '<a href="/admin/pages.php?action=edit&id=' + (body.page_id || 0) + '" class="rounded-md border border-ink-200 bg-white px-2.5 py-1 text-xs text-ink-700 hover:border-brand-300 hover:bg-brand-50">Edit in Pages</a>' +
                        '<a href="/admin/content.php?q=page.' + escapeHtml(body.slug) + '." class="rounded-md border border-ink-200 bg-white px-2.5 py-1 text-xs text-ink-700 hover:border-brand-300 hover:bg-brand-50">Edit content keys</a>' +
                    '</div>' +
                    '<div class="mt-2 text-xs text-ink-400">' +
                        escapeHtml(usage.provider || '') + ' · ' + escapeHtml(usage.model || '') +
                        ' · ' + (usage.tokens_in || 0) + ' in / ' + (usage.tokens_out || 0) + ' out' +
                    '</div>';
                gForm.reset();
                toast('Draft created at /' + body.slug, 'ok');
            } catch (err) {
                gOut.innerHTML = '<div class="text-red-700">Network error: ' + escapeHtml(err.message) + '</div>';
            } finally {
                btn.disabled = false; btn.textContent = orig;
            }
        });
    })();
    </script>
<?php
admin_foot();
