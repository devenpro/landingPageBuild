<?php
// site/public/admin/dashboard.php — admin home page after login.
// Renders quick-link cards to each admin tool. Live tools (Content,
// Forms in Phase 7) link out; future tools show as dashed placeholders
// with the phase number that will activate them.

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';
require __DIR__ . '/_layout.php';
auth_require_login();

$user = auth_current_user();

// Quick stats
$pdo = db();
$content_count = (int) $pdo->query('SELECT COUNT(*) FROM content_blocks')->fetchColumn();
$pages_count   = (int) $pdo->query("SELECT COUNT(*) FROM pages WHERE status = 'published'")->fetchColumn();
$forms_count   = (int) $pdo->query('SELECT COUNT(*) FROM form_submissions')->fetchColumn();

admin_head('Dashboard', 'dashboard');
?>
    <h1 class="text-2xl font-semibold tracking-tight text-ink-900">Welcome back, <?= e($user['email']) ?></h1>
    <p class="mt-2 text-ink-600">Manage content, pages, and submissions from here. AI tools land in Phases 10-11.</p>

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
