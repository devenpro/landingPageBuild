<?php
// site/public/admin/dashboard.php — admin home page after login.
// Renders quick-link cards to each admin tool. Live tools (Content,
// Forms in Phase 7) link out; future tools show as dashed placeholders
// with the phase number that will activate them.

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';
require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../../../core/lib/settings.php';
auth_require_login();

$user = auth_current_user();

// Quick stats
$pdo = db();
// v2 Stage 3: content_blocks now holds block definitions; field values
// moved to content_block_fields. Count fields for the "content rows" stat.
$content_count = (int) $pdo->query('SELECT COUNT(*) FROM content_block_fields')->fetchColumn();
$pages_count   = (int) $pdo->query("SELECT COUNT(*) FROM pages WHERE status = 'published'")->fetchColumn();
$forms_count   = (int) $pdo->query('SELECT COUNT(*) FROM form_submissions')->fetchColumn();

// v2 Stage 10: gentle nudge to run the setup wizard on a fresh clone.
$bootstrap_done = (bool) settings_get('bootstrap_completed', false);

admin_head('Dashboard', 'dashboard');
?>
    <h1 class="text-2xl font-semibold tracking-tight text-ink-900">Welcome back, <?= e($user['email']) ?></h1>
    <p class="mt-2 text-ink-600">Manage content, pages, and submissions from here. AI tools land in Phases 10-11.</p>

<?php if (!$bootstrap_done): ?>
    <a href="/admin/bootstrap.php"
       class="mt-5 flex items-start gap-3 rounded-2xl border border-brand-200 bg-brand-50 px-4 py-3 transition hover:border-brand-400 hover:bg-brand-100">
        <span class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-full bg-brand-600 text-white">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/><path d="M20 3v4"/><path d="M22 5h-4"/><path d="M4 17v2"/><path d="M5 18H3"/></svg>
        </span>
        <span class="flex-1">
            <span class="block text-sm font-semibold text-brand-900">Run the setup wizard</span>
            <span class="mt-0.5 block text-xs text-brand-800">Identity, AI key, brand context, first page — guided in 5 steps so you don't have to remember the order.</span>
        </span>
        <span class="self-center text-brand-700">→</span>
    </a>
<?php endif; ?>

    <div class="mt-6 grid gap-3 sm:grid-cols-3">
        <a href="/admin/content.php" class="block rounded-xl border border-ink-100 bg-white p-4 transition hover:border-brand-200 hover:shadow-sm">
            <div class="text-xs font-medium uppercase tracking-wider text-ink-500">Content blocks</div>
            <div class="mt-1 text-2xl font-semibold text-ink-900"><?= $content_count ?></div>
            <div class="mt-1 text-xs text-brand-700">Edit content &rarr;</div>
        </a>
        <a href="/admin/pages.php" class="block rounded-xl border border-ink-100 bg-white p-4 transition hover:border-brand-200 hover:shadow-sm">
            <div class="text-xs font-medium uppercase tracking-wider text-ink-500">Pages</div>
            <div class="mt-1 text-2xl font-semibold text-ink-900"><?= $pages_count ?></div>
            <div class="mt-1 text-xs text-brand-700">Manage pages &rarr;</div>
        </a>
        <a href="/admin/forms.php" class="block rounded-xl border border-ink-100 bg-white p-4 transition hover:border-brand-200 hover:shadow-sm">
            <div class="text-xs font-medium uppercase tracking-wider text-ink-500">Form submissions</div>
            <div class="mt-1 text-2xl font-semibold text-ink-900"><?= $forms_count ?></div>
            <div class="mt-1 text-xs text-brand-700">Open inbox &rarr;</div>
        </a>
    </div>

    <h2 class="mt-10 text-sm font-semibold uppercase tracking-wider text-ink-500">Coming soon</h2>
    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <div class="rounded-xl border border-dashed border-ink-200 bg-white/60 p-4 text-sm text-ink-500">
            <div class="font-semibold text-ink-700">Inline editing</div>
            <div class="mt-1 text-xs">Edit text/images on the live page — Phase 9</div>
        </div>
        <div class="rounded-xl border border-dashed border-ink-200 bg-white/60 p-4 text-sm text-ink-500">
            <div class="font-semibold text-ink-700">AI keys + tools</div>
            <div class="mt-1 text-xs">BYO Gemini / OpenRouter, page generation — Phases 10-11</div>
        </div>
        <div class="rounded-xl border border-dashed border-ink-200 bg-white/60 p-4 text-sm text-ink-500">
            <div class="font-semibold text-ink-700">Media library</div>
            <div class="mt-1 text-xs">Upload + manage images/videos — Phase 12</div>
        </div>
        <div class="rounded-xl border border-dashed border-ink-200 bg-white/60 p-4 text-sm text-ink-500">
            <div class="font-semibold text-ink-700">Frontend chatbot</div>
            <div class="mt-1 text-xs">AI for site visitors — Phase 13</div>
        </div>
    </div>
<?php
admin_foot();
