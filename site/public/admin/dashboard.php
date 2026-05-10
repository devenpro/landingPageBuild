<?php
// site/public/admin/dashboard.php — Phase 6 placeholder. Confirms the
// auth stack works end-to-end: requires login, shows the signed-in
// admin, and exposes a CSRF-protected logout button. Phase 7 fills
// this with real cards (content blocks, pages, media, forms inbox,
// AI tools, settings).

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';
auth_require_login();

$user = auth_current_user();
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Admin — <?= e(GUA_SITE_NAME) ?></title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="preload" href="/assets/fonts/InterVariable.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: { extend: {
          fontFamily: { sans: ['"InterVariable"', 'system-ui', 'sans-serif'] },
          colors: {
            brand: { 50:'#f5f3ff',100:'#ede9fe',200:'#ddd6fe',300:'#c4b5fd',400:'#a78bfa',500:'#8b5cf6',600:'#7c3aed',700:'#6d28d9',800:'#5b21b6',900:'#4c1d95' },
            ink: { 50:'#f9fafb',100:'#f3f4f6',200:'#e5e7eb',300:'#d1d5db',400:'#9ca3af',500:'#6b7280',600:'#4b5563',700:'#374151',800:'#1f2937',900:'#111827' },
          },
        }},
      };
    </script>
</head>
<body class="font-sans bg-ink-50/60 text-ink-800 antialiased">
    <header class="border-b border-ink-100 bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
            <a href="/admin/dashboard.php" class="flex items-center gap-2 text-base font-semibold text-ink-900">
                <span class="grid h-7 w-7 place-items-center rounded-md bg-brand-600 text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/></svg>
                </span>
                <span><?= e(GUA_SITE_NAME) ?> <span class="text-ink-400">/ admin</span></span>
            </a>

            <div class="flex items-center gap-4 text-sm">
                <span class="hidden text-ink-500 sm:inline"><?= e($user['email']) ?></span>
                <a href="/" class="text-ink-500 hover:text-ink-800">View site</a>
                <form method="post" action="/admin/logout.php" class="inline">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 rounded-full border border-ink-200 bg-white px-3 py-1.5 text-sm text-ink-700 hover:border-ink-300 hover:bg-ink-50">
                        Logout
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-12 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-semibold tracking-tight text-ink-900">Welcome back, <?= e($user['email']) ?></h1>
        <p class="mt-2 text-ink-600">You're signed in. The full admin panel lands in Phase 7.</p>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-2xl border border-dashed border-ink-200 bg-white/60 p-6 text-sm text-ink-500">
                <h3 class="text-base font-semibold text-ink-700">Content blocks</h3>
                <p class="mt-1.5">CRUD UI lands in Phase 7.</p>
            </div>
            <div class="rounded-2xl border border-dashed border-ink-200 bg-white/60 p-6 text-sm text-ink-500">
                <h3 class="text-base font-semibold text-ink-700">Pages</h3>
                <p class="mt-1.5">Page CRUD UI lands in Phase 8.</p>
            </div>
            <div class="rounded-2xl border border-dashed border-ink-200 bg-white/60 p-6 text-sm text-ink-500">
                <h3 class="text-base font-semibold text-ink-700">Form submissions</h3>
                <p class="mt-1.5">Inbox lands in Phase 7.</p>
            </div>
            <div class="rounded-2xl border border-dashed border-ink-200 bg-white/60 p-6 text-sm text-ink-500">
                <h3 class="text-base font-semibold text-ink-700">Media library</h3>
                <p class="mt-1.5">Lands in Phase 12.</p>
            </div>
            <div class="rounded-2xl border border-dashed border-ink-200 bg-white/60 p-6 text-sm text-ink-500">
                <h3 class="text-base font-semibold text-ink-700">AI keys</h3>
                <p class="mt-1.5">Lands in Phase 10.</p>
            </div>
            <div class="rounded-2xl border border-dashed border-ink-200 bg-white/60 p-6 text-sm text-ink-500">
                <h3 class="text-base font-semibold text-ink-700">AI tools</h3>
                <p class="mt-1.5">Page suggestions + generation in Phase 11.</p>
            </div>
        </div>
    </main>
</body>
</html>
