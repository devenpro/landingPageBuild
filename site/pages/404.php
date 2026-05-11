<?php
// site/pages/404.php — file-based 404 fallback. Router sets HTTP 404
// before requiring this file. Reuses the navbar + footer for a coherent
// look; the body is a centred message with CTAs back to home and a
// search hint (Phase 7+ might add real search).

declare(strict_types=1);

require __DIR__ . '/../layout.php';

layout_head($page ?? null);

require __DIR__ . '/../sections/navbar.php';
?>

<main id="main-content" class="mx-auto flex min-h-[70vh] max-w-3xl flex-col items-center justify-center px-4 py-16 text-center sm:px-6">
    <p class="mb-3 text-sm font-medium uppercase tracking-wider text-brand-700">404</p>
    <h1 class="text-4xl font-semibold tracking-tight text-ink-900 sm:text-5xl">Page not found</h1>
    <p class="mt-4 max-w-xl text-lg text-ink-600">
        The page you're looking for doesn't exist, or it might have moved. Head back to the home page and start over.
    </p>
    <div class="mt-8">
        <a href="/"
           class="inline-flex items-center gap-1.5 rounded-full bg-brand-600 px-6 py-3 text-base font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
            <?= lucide('arrow-left', 'h-4 w-4') ?>
            Back to home
        </a>
    </div>
</main>

<?php
require __DIR__ . '/../sections/footer.php';
layout_foot();
