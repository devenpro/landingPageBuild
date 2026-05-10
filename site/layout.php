<?php
// site/layout.php — page chrome (head + foot).
//
// Loaded by site/public/index.php AFTER core/lib/bootstrap.php has
// already pulled in config/db/content/helpers/auth — so no requires here.
//
// Phase 9 wires inline-edit mode: when an admin is logged in, the head
// emits a CSRF meta + content-prefix meta, the body gets `edit-mode`
// class (CSS rules in styles.css show dashed outlines on [data-edit]
// elements), and the foot renders the EditModeBar + loads editor.js.
// Logged-out visitors see none of this — no extra DOM, no extra script.

declare(strict_types=1);

function layout_head(?array $page = null): void
{
    $title = $page['seo_title']
        ?? ($page['title'] ?? null)
        ?? c('seo.title', 'Go Ultra AI');
    $desc  = $page['seo_description'] ?? c('seo.description', '');
    $og_image = $page['seo_og_image'] ?? c('seo.og_image', '/og-image.jpg');
    $url = defined('GUA_APP_URL') ? GUA_APP_URL : '';

    $admin = auth_current_user();
    $is_editor = $admin !== null;
    $content_prefix = function_exists('content_get_prefix') ? content_get_prefix() : '';
    ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <meta name="description" content="<?= e($desc) ?>">

    <meta property="og:title" content="<?= e($title) ?>">
    <meta property="og:description" content="<?= e($desc) ?>">
    <meta property="og:image" content="<?= e($url . $og_image) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= e($url) ?>">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($title) ?>">
    <meta name="twitter:description" content="<?= e($desc) ?>">
    <meta name="twitter:image" content="<?= e($url . $og_image) ?>">

    <link rel="icon" type="image/svg+xml" href="/favicon.svg">

    <link rel="preload" href="/assets/fonts/InterVariable.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/assets/css/styles.css">

    <?php if ($is_editor): ?>
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="content-prefix" content="<?= e($content_prefix) ?>">
    <?php endif; ?>

    <!-- Tailwind Play CDN — replaced with hand-built styles.css in Phase 14 -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            fontFamily: {
              sans: ['"InterVariable"', '"Inter"', 'system-ui', '-apple-system', 'sans-serif'],
            },
            colors: {
              brand: {
                50:  '#f5f3ff',
                100: '#ede9fe',
                200: '#ddd6fe',
                300: '#c4b5fd',
                400: '#a78bfa',
                500: '#8b5cf6',
                600: '#7c3aed',
                700: '#6d28d9',
                800: '#5b21b6',
                900: '#4c1d95',
              },
              ink: {
                50:  '#f9fafb',
                100: '#f3f4f6',
                200: '#e5e7eb',
                300: '#d1d5db',
                400: '#9ca3af',
                500: '#6b7280',
                600: '#4b5563',
                700: '#374151',
                800: '#1f2937',
                900: '#111827',
              },
            },
          },
        },
      };
    </script>
</head>
<body class="font-sans bg-white text-ink-800 antialiased<?= $is_editor ? ' edit-mode pb-16' : '' ?>">
<?php
}

function layout_foot(): void
{
    $admin = auth_current_user();
    $is_editor = $admin !== null;
    ?>
<!-- Lucide icons (initialised after sections render) -->
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>
  if (window.lucide) { lucide.createIcons(); }
</script>

<?php if ($is_editor): ?>
<div id="edit-mode-bar"
     class="fixed inset-x-0 bottom-0 z-50 border-t border-ink-200 bg-white/95 px-4 py-2.5 shadow-lg shadow-brand-900/5 backdrop-blur sm:px-6 lg:px-8">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-3">
        <div class="flex items-center gap-3 text-sm">
            <span class="flex items-center gap-1.5 font-medium text-ink-900">
                <span class="h-2 w-2 rounded-full bg-brand-500"></span>
                Edit mode
            </span>
            <span id="edit-mode-prefix" class="hidden rounded bg-brand-50 px-1.5 py-0.5 text-xs font-mono text-brand-700 sm:inline-block"></span>
            <span id="edit-mode-counter" class="text-ink-500">No unsaved changes</span>
        </div>
        <div class="flex items-center gap-2">
            <a href="/admin/dashboard.php" class="hidden text-sm text-ink-500 hover:text-ink-800 sm:inline">Admin</a>
            <button type="button" id="edit-mode-discard"
                    class="rounded-lg border border-ink-200 bg-white px-3 py-1.5 text-sm text-ink-700 hover:border-ink-300 hover:bg-ink-50 disabled:cursor-not-allowed disabled:opacity-60">
                Discard
            </button>
            <button type="button" id="edit-mode-save"
                    class="rounded-lg bg-brand-600 px-4 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-60">
                Save
            </button>
            <form method="post" action="/admin/logout.php" class="inline">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="rounded-lg border border-ink-200 bg-white px-3 py-1.5 text-sm text-ink-700 hover:border-ink-300 hover:bg-ink-50">
                    Logout
                </button>
            </form>
        </div>
    </div>
</div>
<script src="/assets/js/editor.js" defer></script>
<?php endif; ?>

<?php if (defined('GUA_AI_CHAT_ENABLED') && GUA_AI_CHAT_ENABLED): ?>
<script src="/assets/js/chat-widget.js" defer></script>
<?php endif; ?>
</body>
</html>
<?php
}
