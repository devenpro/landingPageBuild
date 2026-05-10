<?php
// includes/sections/how_it_works.php — 4-step horizontal flow on desktop,
// vertical stack on mobile.
?>
<section id="how" class="bg-ink-50/40 py-20 sm:py-28">
    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="text-3xl font-semibold tracking-tight text-ink-900 sm:text-4xl">
                <span data-edit="text" data-key="how.heading"><?= e(c('how.heading')) ?></span>
            </h2>
            <p class="mt-4 text-lg text-ink-600">
                <span data-edit="text" data-key="how.subheading"><?= e(c('how.subheading')) ?></span>
            </p>
        </div>

        <ol class="mt-14 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
            <?php for ($i = 1; $i <= 4; $i++): ?>
                <li class="relative">
                    <div class="flex items-center gap-3">
                        <span class="grid h-9 w-9 place-items-center rounded-full bg-brand-600 text-sm font-semibold text-white"><?= $i ?></span>
                        <i data-edit="icon" data-key="step.<?= $i ?>.icon"
                           data-lucide="<?= e(c("step.$i.icon")) ?>"
                           class="h-5 w-5 text-brand-700" aria-hidden="true"></i>
                    </div>
                    <h3 class="mt-4 text-base font-semibold text-ink-900">
                        <span data-edit="text" data-key="step.<?= $i ?>.title"><?= e(c("step.$i.title")) ?></span>
                    </h3>
                    <p class="mt-2 text-sm leading-relaxed text-ink-600">
                        <span data-edit="text" data-key="step.<?= $i ?>.body"><?= e(c("step.$i.body")) ?></span>
                    </p>
                </li>
            <?php endfor; ?>
        </ol>
    </div>
</section>
