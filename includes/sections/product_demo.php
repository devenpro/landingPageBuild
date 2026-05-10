<?php
// includes/sections/product_demo.php — single demo image (or video later).
// Lazy-loaded since it sits well below the fold.
?>
<section class="bg-ink-50/40 py-20 sm:py-28">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="text-3xl font-semibold tracking-tight text-ink-900 sm:text-4xl">
                <span data-edit="text" data-key="demo.heading"><?= e(c('demo.heading')) ?></span>
            </h2>
            <p class="mt-4 text-lg text-ink-600">
                <span data-edit="text" data-key="demo.subheading"><?= e(c('demo.subheading')) ?></span>
            </p>
        </div>

        <div class="mt-12">
            <img src="<?= e(c('demo.image')) ?>"
                 alt="<?= e(c('demo.image_alt')) ?>"
                 data-edit="image" data-key="demo.image"
                 class="w-full rounded-2xl border border-ink-100 bg-white shadow-xl shadow-brand-900/5"
                 loading="lazy">
        </div>
    </div>
</section>
