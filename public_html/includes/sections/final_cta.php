<?php
// includes/sections/final_cta.php — closing CTA + waitlist form anchor.
// Real form HTML lands in Phase 3; for now this is heading + a placeholder
// note so the page tells a complete story end-to-end.
?>
<section id="waitlist" class="bg-gradient-to-b from-white to-brand-50/60 py-20 sm:py-28">
    <div class="mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8">
        <h2 class="text-3xl font-semibold tracking-tight text-ink-900 sm:text-4xl">
            <span data-edit="text" data-key="final_cta.heading"><?= e(c('final_cta.heading')) ?></span>
        </h2>
        <p class="mx-auto mt-4 max-w-xl text-lg text-ink-600">
            <span data-edit="text" data-key="final_cta.subheading"><?= e(c('final_cta.subheading')) ?></span>
        </p>

        <div class="mx-auto mt-8 max-w-md rounded-2xl border border-dashed border-brand-300 bg-white/70 p-6 text-sm text-ink-500">
            <i data-lucide="construction" class="mx-auto mb-2 h-5 w-5 text-brand-600" aria-hidden="true"></i>
            Waitlist form lands in Phase 3.
        </div>
    </div>
</section>
