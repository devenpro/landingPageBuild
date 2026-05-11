<?php
// site/public/admin/bootstrap.php — site-bootstrap wizard (v2 Stage 10).
//
// Server-rendered 5-step flow that walks a fresh site clone through:
//   1. Welcome       — show the audit + next-step preview
//   2. Identity      — site_name / app_url / admin_email
//   3. AI key        — gate on the default provider having a key stored
//   4. Brand fill    — AI-fill missing required brand items from a brief
//   5. Initial pages — link to /admin/ai.php with a pre-filled brief
//   6. Done          — flip bootstrap_completed=1 + back to dashboard
//
// State is URL-driven (?step=N). All mutations go through
// POST /api/bootstrap.php via fetch (JSON). On success, JS redirects to
// the next step so the URL stays the canonical place to bookmark.

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';
require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../../../core/lib/settings.php';
require_once __DIR__ . '/../../../core/lib/brand/audit.php';
require_once __DIR__ . '/../../../core/lib/ai/keys.php';
require_once __DIR__ . '/../../../core/lib/ai/client.php';

auth_require_login();

$user = auth_current_user();
$step = max(1, min(6, (int)($_GET['step'] ?? 1)));

// Record first-open timestamp via a same-request settings_set if missing.
// Cheaper than fetch() from the page and the wizard URL is the natural trigger.
if ((string)settings_get('bootstrap_started_at', '') === '') {
    settings_set('bootstrap_started_at', gmdate('Y-m-d H:i:s'), (int)$user['id']);
}

$bootstrap_done = (bool) settings_get('bootstrap_completed', false);
$audit          = brand_audit();
$default_prov   = ai_default_provider();
$has_default_key = false;
try { $has_default_key = ai_keys_get($default_prov) !== null; } catch (Throwable $e) { $has_default_key = false; }
$pages_count   = (int) db()->query("SELECT COUNT(*) FROM pages WHERE status = 'published'")->fetchColumn();

$site_name_val   = (string) settings_get('site_name',   '');
$app_url_val     = (string) settings_get('app_url',     '');
$admin_email_val = (string) settings_get('admin_email', '');

admin_head('Setup wizard', 'dashboard');
?>
    <div class="flex items-baseline justify-between">
        <h1 class="text-2xl font-semibold tracking-tight text-ink-900">Setup wizard</h1>
        <?php if ($bootstrap_done): ?>
            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">Already completed</span>
        <?php else: ?>
            <span class="text-sm text-ink-500">Step <?= $step ?> of 6</span>
        <?php endif; ?>
    </div>

    <ol class="mt-4 flex flex-wrap items-center gap-2 text-xs text-ink-500">
        <?php $labels = ['Welcome', 'Identity', 'AI key', 'Brand', 'Pages', 'Done'];
              foreach ($labels as $i => $label):
                $n = $i + 1; $active = $n === $step; $past = $n < $step;
        ?>
        <li class="flex items-center gap-2">
            <a href="?step=<?= $n ?>"
               class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 <?= $active ? 'bg-brand-600 text-white' : ($past ? 'bg-brand-50 text-brand-700 hover:bg-brand-100' : 'bg-ink-100 text-ink-600 hover:bg-ink-200') ?>">
                <span class="font-mono text-[10px] opacity-70"><?= $n ?></span>
                <?= e($label) ?>
            </a>
            <?php if ($n < count($labels)): ?><span class="text-ink-300">→</span><?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ol>

<?php if ($step === 1): ?>
    <section class="mt-6 rounded-2xl border border-ink-100 bg-white p-6">
        <h2 class="text-lg font-semibold text-ink-900">Welcome — let's get this site production-ready</h2>
        <p class="mt-2 text-ink-600">The wizard walks through the four things every clone needs before public traffic: identity, an AI key for content tools, a filled Brand Context Library, and at least one initial page.</p>
        <dl class="mt-5 grid gap-3 sm:grid-cols-2">
            <div class="rounded-lg border border-ink-100 bg-ink-50/40 p-3">
                <dt class="text-xs uppercase tracking-wider text-ink-500">Brand library score</dt>
                <dd class="mt-1 text-2xl font-semibold text-ink-900"><?= (int)$audit['score'] ?>%</dd>
                <dd class="text-xs text-ink-500"><?= (int)$audit['totals']['required_filled'] ?> / <?= (int)$audit['totals']['required'] ?> required categories filled</dd>
            </div>
            <div class="rounded-lg border border-ink-100 bg-ink-50/40 p-3">
                <dt class="text-xs uppercase tracking-wider text-ink-500">Default AI provider</dt>
                <dd class="mt-1 text-2xl font-semibold text-ink-900"><?= e($default_prov) ?></dd>
                <dd class="text-xs <?= $has_default_key ? 'text-emerald-700' : 'text-amber-700' ?>">
                    <?= $has_default_key ? 'Key on file ✓' : 'No key yet — step 3 will help' ?>
                </dd>
            </div>
            <div class="rounded-lg border border-ink-100 bg-ink-50/40 p-3">
                <dt class="text-xs uppercase tracking-wider text-ink-500">Published pages</dt>
                <dd class="mt-1 text-2xl font-semibold text-ink-900"><?= $pages_count ?></dd>
                <dd class="text-xs text-ink-500">Step 5 generates a first one from a brief</dd>
            </div>
            <div class="rounded-lg border border-ink-100 bg-ink-50/40 p-3">
                <dt class="text-xs uppercase tracking-wider text-ink-500">Bootstrap completed</dt>
                <dd class="mt-1 text-2xl font-semibold text-ink-900"><?= $bootstrap_done ? 'Yes' : 'No' ?></dd>
                <dd class="text-xs text-ink-500"><?= $bootstrap_done ? 'You can re-run any step if needed.' : 'Flag flips at the final step.' ?></dd>
            </div>
        </dl>
        <div class="mt-6 flex justify-end">
            <a href="?step=2" class="inline-flex items-center gap-1.5 rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                Start → Identity
            </a>
        </div>
    </section>

<?php elseif ($step === 2): ?>
    <section class="mt-6 rounded-2xl border border-ink-100 bg-white p-6">
        <h2 class="text-lg font-semibold text-ink-900">Step 2 — Site identity</h2>
        <p class="mt-1 text-sm text-ink-600">These values appear in page titles, OG tags, JSON-LD, sitemaps, and the admin chrome. You can edit them later under <a class="text-brand-700 underline" href="/admin/settings.php?tab=general">Settings → General</a>.</p>
        <form id="bs-form-identity" class="mt-5 grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="font-medium text-ink-700">Site name</span>
                <input type="text" name="site_name" required maxlength="120" value="<?= e($site_name_val) ?>"
                       class="mt-1 block w-full rounded-md border border-ink-200 bg-white px-3 py-2 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500">
            </label>
            <label class="block text-sm">
                <span class="font-medium text-ink-700">Public site URL</span>
                <input type="url" name="app_url" placeholder="https://example.com" value="<?= e($app_url_val) ?>"
                       class="mt-1 block w-full rounded-md border border-ink-200 bg-white px-3 py-2 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500">
                <span class="mt-1 block text-[11px] text-ink-500">Canonical https URL with no trailing slash.</span>
            </label>
            <label class="block text-sm sm:col-span-2">
                <span class="font-medium text-ink-700">Admin email</span>
                <input type="email" name="admin_email" placeholder="you@example.com" value="<?= e($admin_email_val) ?>"
                       class="mt-1 block w-full rounded-md border border-ink-200 bg-white px-3 py-2 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500">
                <span class="mt-1 block text-[11px] text-ink-500">Used in form notification headers.</span>
            </label>
            <div class="sm:col-span-2 flex items-center justify-between">
                <a href="?step=1" class="text-sm text-ink-500 hover:text-ink-800">← Back</a>
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-60">
                    Save &amp; continue →
                </button>
            </div>
        </form>
    </section>

<?php elseif ($step === 3): ?>
    <section class="mt-6 rounded-2xl border border-ink-100 bg-white p-6">
        <h2 class="text-lg font-semibold text-ink-900">Step 3 — AI key</h2>
        <p class="mt-1 text-sm text-ink-600">The wizard's brand-fill (step 4) and page-generation (step 5) call your default provider (<code class="rounded bg-ink-100 px-1 py-0.5 text-ink-800"><?= e($default_prov) ?></code>) so a key needs to be on file.</p>
        <?php if ($has_default_key): ?>
            <div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                A key for <strong><?= e($default_prov) ?></strong> is on file. You're ready for the next step.
            </div>
            <div class="mt-6 flex items-center justify-between">
                <a href="?step=2" class="text-sm text-ink-500 hover:text-ink-800">← Back</a>
                <a href="?step=4" class="inline-flex items-center gap-1.5 rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                    Continue → Brand fill
                </a>
            </div>
        <?php else: ?>
            <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                No key on file for <strong><?= e($default_prov) ?></strong>. Add one, then come back to this wizard.
            </div>
            <div class="mt-6 flex items-center justify-between">
                <a href="?step=2" class="text-sm text-ink-500 hover:text-ink-800">← Back</a>
                <div class="flex items-center gap-2">
                    <a href="/admin/ai-keys.php" class="inline-flex items-center gap-1.5 rounded-md border border-ink-200 bg-white px-3 py-2 text-sm text-ink-700 hover:border-ink-300 hover:bg-ink-50">
                        Add a key →
                    </a>
                    <a href="?step=4" class="inline-flex items-center gap-1.5 rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                        Skip for now
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </section>

<?php elseif ($step === 4): ?>
    <section class="mt-6 rounded-2xl border border-ink-100 bg-white p-6">
        <h2 class="text-lg font-semibold text-ink-900">Step 4 — Brand context fill</h2>
        <p class="mt-1 text-sm text-ink-600">Paste a 1–2 paragraph brief about the business; the wizard generates draft items for every missing required category in one batch. Items land as <em>ai-generated, awaiting review</em> so they don't auto-leak into downstream prompts until you open and approve them at <a class="text-brand-700 underline" href="/admin/brand.php">Brand</a>.</p>
        <?php
            $required_missing = array_values(array_filter(
                $audit['missing'],
                static fn($m) => !empty($m['required'])
            ));
        ?>
        <?php if ($required_missing): ?>
            <div class="mt-4 rounded-md border border-ink-100 bg-ink-50/40 px-3 py-2 text-xs text-ink-600">
                Will fill <strong><?= count($required_missing) ?></strong> required item<?= count($required_missing) === 1 ? '' : 's' ?>: <?php
                $labels = array_map(static fn($m) => $m['category'], $required_missing);
                echo e(implode(', ', array_unique($labels)));
                ?>.
            </div>
        <?php else: ?>
            <div class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                All required brand categories are filled. You can still re-fill them if you want.
            </div>
        <?php endif; ?>
        <form id="bs-form-brand" class="mt-4 space-y-3">
            <label class="block text-sm">
                <span class="font-medium text-ink-700">Brief</span>
                <textarea name="brief" rows="6" minlength="30" placeholder="What does the business do? Who is it for? What tone of voice? What sets it apart? Locations / services? Anything else the AI should treat as fact."
                          class="mt-1 block w-full rounded-md border border-ink-200 bg-white px-3 py-2 font-sans focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500"></textarea>
                <span class="mt-1 block text-[11px] text-ink-500">At least 30 characters. Longer is better — the brief is reused as context for every category.</span>
            </label>
            <div id="bs-brand-result" class="hidden rounded-md border bg-white px-3 py-2 text-sm"></div>
            <div class="flex items-center justify-between">
                <a href="?step=3" class="text-sm text-ink-500 hover:text-ink-800">← Back</a>
                <div class="flex items-center gap-2">
                    <a href="?step=5" class="text-sm text-ink-500 hover:text-ink-800">Skip</a>
                    <button type="submit" id="bs-brand-submit" class="inline-flex items-center gap-1.5 rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-60">
                        Fill with AI
                    </button>
                </div>
            </div>
        </form>
    </section>

<?php elseif ($step === 5): ?>
    <section class="mt-6 rounded-2xl border border-ink-100 bg-white p-6">
        <h2 class="text-lg font-semibold text-ink-900">Step 5 — Initial pages</h2>
        <p class="mt-1 text-sm text-ink-600">Use the existing AI Tools page to generate your first page from a brief. It'll land as a draft you can review and publish at <a class="text-brand-700 underline" href="/admin/pages.php">Pages</a>.</p>
        <div class="mt-4 rounded-md border border-ink-100 bg-ink-50/40 px-3 py-3 text-sm text-ink-700">
            Currently <strong><?= $pages_count ?></strong> page<?= $pages_count === 1 ? '' : 's' ?> published. The seed includes <code>home</code> and <code>404</code> by default; generation adds more without touching those.
        </div>
        <div class="mt-6 flex items-center justify-between">
            <a href="?step=4" class="text-sm text-ink-500 hover:text-ink-800">← Back</a>
            <div class="flex items-center gap-2">
                <a href="/admin/ai.php" class="inline-flex items-center gap-1.5 rounded-md border border-ink-200 bg-white px-3 py-2 text-sm text-ink-700 hover:border-ink-300 hover:bg-ink-50">
                    Open AI Tools ↗
                </a>
                <a href="?step=6" class="inline-flex items-center gap-1.5 rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                    Continue →
                </a>
            </div>
        </div>
    </section>

<?php elseif ($step === 6): ?>
    <section class="mt-6 rounded-2xl border border-ink-100 bg-white p-6 text-center">
        <h2 class="text-xl font-semibold text-ink-900">All done</h2>
        <p class="mt-2 text-ink-600">Click the button below to mark this site bootstrapped. The dashboard banner will go away and the wizard becomes optional reading.</p>
        <button type="button" id="bs-complete"
                class="mt-6 inline-flex items-center gap-1.5 rounded-md bg-brand-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-60">
            Mark bootstrap complete
        </button>
        <div id="bs-complete-result" class="hidden mt-4 rounded-md border bg-white px-3 py-2 text-sm"></div>
    </section>
<?php endif; ?>

<script>
(function () {
    'use strict';
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    async function postJson(payload) {
        const res = await fetch('/api/bootstrap.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrf,
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });
        const body = await res.json().catch(function () { return null; });
        return { ok: res.ok && body && body.ok, status: res.status, body: body };
    }

    function showBanner(el, msg, kind /* ok|err */) {
        el.classList.remove('hidden', 'border-emerald-200', 'bg-emerald-50', 'text-emerald-800',
                              'border-red-200', 'bg-red-50', 'text-red-800');
        if (kind === 'ok') {
            el.classList.add('border-emerald-200', 'bg-emerald-50', 'text-emerald-800');
        } else {
            el.classList.add('border-red-200', 'bg-red-50', 'text-red-800');
        }
        el.textContent = msg;
    }

    // Step 2 form
    const id_form = document.getElementById('bs-form-identity');
    if (id_form) {
        id_form.addEventListener('submit', async function (e) {
            e.preventDefault();
            const fd = new FormData(id_form);
            const btn = id_form.querySelector('button[type="submit"]');
            btn.disabled = true; const orig = btn.textContent; btn.textContent = 'Saving…';
            const r = await postJson({
                action: 'save_identity',
                site_name:   fd.get('site_name'),
                app_url:     fd.get('app_url'),
                admin_email: fd.get('admin_email'),
            });
            if (r.ok) {
                window.location.href = '?step=3';
            } else {
                btn.disabled = false; btn.textContent = orig;
                const fields = (r.body && r.body.errors) || {};
                const top = (r.body && r.body.error) || ('HTTP ' + r.status);
                alert(top + (Object.keys(fields).length ? '\n\n' + Object.entries(fields).map(([k,v]) => k + ': ' + v).join('\n') : ''));
            }
        });
    }

    // Step 4 form (brand fill)
    const brand_form = document.getElementById('bs-form-brand');
    if (brand_form) {
        brand_form.addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = document.getElementById('bs-brand-submit');
            const out = document.getElementById('bs-brand-result');
            const brief = brand_form.querySelector('textarea[name="brief"]').value;
            btn.disabled = true; const orig = btn.textContent; btn.textContent = 'Generating…';
            showBanner(out, 'Generating drafts — this can take 30-60s for several categories…', 'ok');
            const r = await postJson({ action: 'brand_fill', brief: brief });
            if (r.ok) {
                const filled = (r.body && r.body.filled) || [];
                const errors = (r.body && r.body.errors) || [];
                let msg = 'Filled ' + filled.length + ' / ' + (r.body.attempted || 0) + ' categor' +
                         ((r.body.attempted === 1) ? 'y' : 'ies') + '.';
                if (errors.length) msg += ' (' + errors.length + ' error' + (errors.length === 1 ? '' : 's') + ')';
                msg += ' Review at /admin/brand.php.';
                showBanner(out, msg, errors.length ? 'err' : 'ok');
                btn.disabled = false; btn.textContent = 'Continue → step 5';
                btn.type = 'button';
                btn.addEventListener('click', function () { window.location.href = '?step=5'; }, { once: true });
            } else {
                btn.disabled = false; btn.textContent = orig;
                showBanner(out, (r.body && r.body.error) || ('HTTP ' + r.status), 'err');
            }
        });
    }

    // Step 6 complete
    const done_btn = document.getElementById('bs-complete');
    if (done_btn) {
        done_btn.addEventListener('click', async function () {
            const out = document.getElementById('bs-complete-result');
            done_btn.disabled = true; const orig = done_btn.textContent; done_btn.textContent = 'Saving…';
            const r = await postJson({ action: 'complete' });
            if (r.ok) {
                showBanner(out, 'Bootstrap complete — redirecting to dashboard…', 'ok');
                setTimeout(function () { window.location.href = '/admin/dashboard.php'; }, 800);
            } else {
                done_btn.disabled = false; done_btn.textContent = orig;
                showBanner(out, (r.body && r.body.error) || ('HTTP ' + r.status), 'err');
            }
        });
    }
})();
</script>
<?php
admin_foot();
