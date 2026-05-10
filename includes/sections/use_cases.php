<?php
// includes/sections/use_cases.php — 3-card persona grid.
?>
<section class="bg-white py-20 sm:py-28">
    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="text-3xl font-semibold tracking-tight text-ink-900 sm:text-4xl">
                <span data-edit="text" data-key="use_cases.heading"><?= e(c('use_cases.heading')) ?></span>
            </h2>
        </div>

        <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <?php for ($i = 1; $i <= 3; $i++): ?>
                <div class="rounded-2xl border border-ink-100 bg-gradient-to-br from-white to-brand-50/40 p-7">
                    <div class="grid h-11 w-11 place-items-center rounded-xl bg-white text-brand-700 shadow-sm ring-1 ring-ink-100">
                        <i data-edit="icon" data-key="use_case.<?= $i ?>.icon"
                           data-lucide="<?= e(c("use_case.$i.icon")) ?>"
                           class="h-5 w-5" aria-hidden="true"></i>
                    </div>
                    <h3 class="mt-5 text-lg font-semibold text-ink-900">
                        <span data-edit="text" data-key="use_case.<?= $i ?>.title"><?= e(c("use_case.$i.title")) ?></span>
                    </h3>
                    <p class="mt-2 text-sm leading-relaxed text-ink-600">
                        <span data-edit="text" data-key="use_case.<?= $i ?>.body"><?= e(c("use_case.$i.body")) ?></span>
                    </p>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</section>
