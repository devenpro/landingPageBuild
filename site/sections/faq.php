<?php
// includes/sections/faq.php — accordion built on <details>/<summary> so it
// works without JS. 6 Q&A pairs from the seed.
?>
<section id="faq" class="bg-white py-20 sm:py-28">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        <div class="text-center">
            <h2 class="text-3xl font-semibold tracking-tight text-ink-900 sm:text-4xl">
                <span data-edit="text" data-key="faq.heading"><?= e(c('faq.heading')) ?></span>
            </h2>
        </div>

        <div class="mt-10 divide-y divide-ink-100 rounded-2xl border border-ink-100 bg-white">
            <?php for ($i = 1; $i <= 6; $i++): ?>
                <details class="group p-5 sm:p-6">
                    <summary class="flex cursor-pointer items-center justify-between gap-4 text-left text-base font-medium text-ink-900 [&::-webkit-details-marker]:hidden">
                        <span data-edit="text" data-key="faq.<?= $i ?>.q"><?= e(c("faq.$i.q")) ?></span>
                        <?= lucide('chevron-down', 'h-5 w-5 shrink-0 text-ink-500 transition group-open:rotate-180') ?>
                    </summary>
                    <p class="mt-3 text-sm leading-relaxed text-ink-600">
                        <span data-edit="text" data-key="faq.<?= $i ?>.a"><?= e(c("faq.$i.a")) ?></span>
                    </p>
                </details>
            <?php endfor; ?>
        </div>
    </div>
</section>
