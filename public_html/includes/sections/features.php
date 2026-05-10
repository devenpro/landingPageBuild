<?php
// includes/sections/features.php — 6-card grid (3 cols on desktop, 2 on
// tablet, 1 on mobile). Each card has an editable Lucide icon + title + body.
?>
<section id="features" class="bg-white py-20 sm:py-28">
    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="text-3xl font-semibold tracking-tight text-ink-900 sm:text-4xl">
                <span data-edit="text" data-key="features.heading"><?= e(c('features.heading')) ?></span>
            </h2>
            <p class="mt-4 text-lg text-ink-600">
                <span data-edit="text" data-key="features.subheading"><?= e(c('features.subheading')) ?></span>
            </p>
        </div>

        <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <?php for ($i = 1; $i <= 6; $i++): ?>
                <div class="group rounded-2xl border border-ink-100 bg-white p-6 transition hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-lg hover:shadow-brand-900/5">
                    <div class="grid h-11 w-11 place-items-center rounded-xl bg-brand-50 text-brand-700">
                        <i data-edit="icon" data-key="feature.<?= $i ?>.icon"
                           data-lucide="<?= e(c("feature.$i.icon")) ?>"
                           class="h-5 w-5" aria-hidden="true"></i>
                    </div>
                    <h3 class="mt-5 text-lg font-semibold text-ink-900">
                        <span data-edit="text" data-key="feature.<?= $i ?>.title"><?= e(c("feature.$i.title")) ?></span>
                    </h3>
                    <p class="mt-2 text-sm leading-relaxed text-ink-600">
                        <span data-edit="text" data-key="feature.<?= $i ?>.body"><?= e(c("feature.$i.body")) ?></span>
                    </p>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</section>
