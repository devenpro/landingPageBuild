<?php
// includes/sections/navbar.php — sticky top nav with brand mark and CTA.
// data-edit/data-key markers are wired up by Phase 5's editor.js. They're
// inert for logged-out visitors.
?>
<header class="sticky top-0 z-40 w-full border-b border-ink-100 bg-white/80 backdrop-blur">
    <nav class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
        <a href="/" class="flex items-center gap-2 text-base font-semibold tracking-tight text-ink-900">
            <span class="grid h-7 w-7 place-items-center rounded-md bg-brand-600 text-white">
                <?= lucide('zap', 'h-4 w-4') ?>
            </span>
            <span data-edit="text" data-key="nav.brand"><?= e(c('nav.brand')) ?></span>
        </a>

        <div class="hidden items-center gap-8 text-sm text-ink-600 md:flex">
            <a href="#features" class="hover:text-ink-900" data-edit="text" data-key="nav.features_label"><?= e(c('nav.features_label')) ?></a>
            <a href="#how" class="hover:text-ink-900" data-edit="text" data-key="nav.how_label"><?= e(c('nav.how_label')) ?></a>
            <a href="#faq" class="hover:text-ink-900" data-edit="text" data-key="nav.faq_label"><?= e(c('nav.faq_label')) ?></a>
        </div>

        <a href="#waitlist"
           class="inline-flex items-center gap-1.5 rounded-full bg-brand-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
            <span data-edit="text" data-key="nav.cta_label"><?= e(c('nav.cta_label')) ?></span>
            <?= lucide('arrow-right', 'h-4 w-4') ?>
        </a>
    </nav>
</header>
