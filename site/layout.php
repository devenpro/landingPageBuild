<?php
// site/layout.php — page chrome (head + foot). Kept separate from
// index.php so we can swap doc-level concerns (CSP, fonts, JSON-LD) in
// one place. Phase 9's editor.js will be conditionally loaded from foot()
// once admin auth lands.
//
// Loaded by site/public/index.php AFTER core/lib/bootstrap.php has
// already pulled in config/db/content/helpers — so no requires here.

declare(strict_types=1);

function layout_head(?array $page = null): void
{
    $title = $page['seo_title']
        ?? ($page['title'] ?? null)
        ?? c('seo.title', 'Go Ultra AI');
    $desc  = $page['seo_description'] ?? c('seo.description', '');
    $og_image = $page['seo_og_image'] ?? c('seo.og_image', '/og-image.jpg');
    $url = defined('GUA_APP_URL') ? GUA_APP_URL : '';
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

    <!-- Tailwind Play CDN — replaced with hand-built styles.css in Phase 7 -->
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
<body class="font-sans bg-white text-ink-800 antialiased">
<?php
}

function layout_foot(): void
{
    ?>
<!-- Lucide icons (initialised after sections render) -->
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>
  if (window.lucide) { lucide.createIcons(); }
</script>
</body>
</html>
<?php
}
