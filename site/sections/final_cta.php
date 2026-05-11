<?php
// site/sections/final_cta.php — closing CTA + waitlist form (v2 Stage 6).
//
// v1 had the form HTML inlined here. Stage 6 uses form_render('waitlist')
// so all forms (waitlist + admin-defined) share a single renderer driven
// by the form_fields table. Editing the fields in /admin/forms.php
// reflects on the public site on next request.

require_once __DIR__ . '/../../core/lib/forms.php';
?>
<section id="waitlist" class="bg-gradient-to-b from-white to-brand-50/60 py-20 sm:py-28">
    <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
        <div class="text-center">
            <h2 class="text-3xl font-semibold tracking-tight text-ink-900 sm:text-4xl">
                <span data-edit="text" data-key="final_cta.heading"><?= e(c('final_cta.heading')) ?></span>
            </h2>
            <p class="mx-auto mt-4 max-w-xl text-lg text-ink-600">
                <span data-edit="text" data-key="final_cta.subheading"><?= e(c('final_cta.subheading')) ?></span>
            </p>
        </div>

        <div class="mt-10">
            <?= form_render('waitlist', ['submit_label' => c('form.submit_label', 'Join the waitlist'), 'html_id' => 'waitlist-form']) ?>
        </div>
    </div>
</section>
