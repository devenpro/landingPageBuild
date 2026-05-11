<?php
// site/public/admin/_layout.php — admin chrome.
// admin_head($title, $active='') opens the document and renders the nav.
// admin_foot() closes it. Each admin page passes the slug of the active
// nav item (one of: 'dashboard', 'content', 'pages', 'forms', 'media',
// 'ai_keys', 'ai_tools', 'settings') so the right link is highlighted.
//
// Tailwind config lives in one place here so dashboard / content / forms
// pages don't repeat it. Phase 14 swaps the Tailwind CDN for a built
// stylesheet — when that happens, this is the one file to update.

declare(strict_types=1);

function admin_nav_items(): array
{
    return [
        ['slug' => 'dashboard', 'label' => 'Dashboard', 'href' => '/admin/dashboard.php', 'live' => true],
        ['slug' => 'content',   'label' => 'Content',   'href' => '/admin/content.php',   'live' => true],
        ['slug' => 'pages',     'label' => 'Pages',     'href' => '/admin/pages.php',     'live' => true],
        ['slug' => 'forms',     'label' => 'Forms',     'href' => '/admin/forms.php',     'live' => true],
        ['slug' => 'webhooks',  'label' => 'Webhooks',  'href' => '/admin/webhooks.php',  'live' => true],
        ['slug' => 'media',     'label' => 'Media',     'href' => '/admin/media.php',     'live' => true],
        ['slug' => 'ai_keys',   'label' => 'AI keys',   'href' => '/admin/ai-keys.php',   'live' => true],
        ['slug' => 'ai_tools',  'label' => 'AI tools',  'href' => '/admin/ai.php',        'live' => true],
        ['slug' => 'settings',  'label' => 'Settings',  'href' => '#',                    'live' => false, 'phase' => 10],
    ];
}

function admin_head(string $title, string $active = ''): void
{
    $user = auth_current_user(); // null guard up to caller (admin pages call auth_require_login first)
    ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title) ?> — <?= e(GUA_SITE_NAME) ?> admin</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="preload" href="/assets/fonts/InterVariable.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/assets/css/styles.css">
</head>
<body class="font-sans bg-ink-50/60 text-ink-800 antialiased">
    <header class="border-b border-ink-100 bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
            <a href="/admin/dashboard.php" class="flex items-center gap-2 text-base font-semibold text-ink-900">
                <span class="grid h-7 w-7 place-items-center rounded-md bg-brand-600 text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/></svg>
                </span>
                <span><?= e(GUA_SITE_NAME) ?> <span class="text-ink-500">/ admin</span></span>
            </a>
            <div class="flex items-center gap-4 text-sm">
                <span class="hidden text-ink-500 sm:inline"><?= $user ? e($user['email']) : '' ?></span>
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
        <nav class="mx-auto flex max-w-6xl gap-1 overflow-x-auto px-4 sm:px-6 lg:px-8">
            <?php foreach (admin_nav_items() as $item):
                $is_active = $item['slug'] === $active;
                $base_class = 'whitespace-nowrap border-b-2 px-3 py-2.5 text-sm transition';
                if ($is_active) {
                    $cls = $base_class . ' border-brand-600 font-medium text-ink-900';
                } elseif ($item['live']) {
                    $cls = $base_class . ' border-transparent text-ink-500 hover:text-ink-800';
                } else {
                    $cls = $base_class . ' cursor-not-allowed border-transparent text-ink-500';
                }
            ?>
                <?php if ($item['live']): ?>
                    <a href="<?= e($item['href']) ?>" class="<?= $cls ?>"><?= e($item['label']) ?></a>
                <?php else: ?>
                    <span class="<?= $cls ?>" title="Lands in Phase <?= (int)$item['phase'] ?>">
                        <?= e($item['label']) ?> <span class="ml-1 text-[10px] uppercase tracking-wider">soon</span>
                    </span>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
<?php
}

function admin_foot(): void
{
    ?>
    </main>
    <script src="/assets/js/admin.js" defer></script>
</body>
</html>
<?php
}
