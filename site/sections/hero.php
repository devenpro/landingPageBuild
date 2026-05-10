<?php
// includes/sections/hero.php — primary above-the-fold value prop + CTA.
// Hero image is a real <img> so Phase 6's image upload modal can swap it.
?>
<section class="relative overflow-hidden bg-gradient-to-b from-brand-50/60 to-white">
    <div class="mx-auto max-w-6xl px-4 pt-14 pb-16 sm:px-6 sm:pt-20 sm:pb-24 lg:px-8 lg:pt-28">
        <div class="mx-auto max-w-3xl text-center">
            <p class="mb-5 inline-block rounded-full border border-brand-200 bg-brand-50 px-3 py-1 text-xs font-medium uppercase tracking-wider text-brand-700">
                <span data-edit="text" data-key="hero.eyebrow"><?= e(c('hero.eyebrow')) ?></span>
            </p>
            <h1 class="text-4xl font-semibold leading-[1.05] tracking-tight text-ink-900 sm:text-5xl lg:text-6xl">
                <span data-edit="text" data-key="hero.headline"><?= e(c('hero.headline')) ?></span>
            </h1>
            <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-ink-600 sm:text-xl">
                <span data-edit="text" data-key="hero.subheadline"><?= e(c('hero.subheadline')) ?></span>
            </p>
            <div class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <a href="#waitlist"
                   class="inline-flex w-full items-center justify-center gap-1.5 rounded-full bg-brand-600 px-6 py-3 text-base font-medium text-white shadow-sm hover:bg-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-400 focus-visible:ring-offset-2 sm:w-auto">
                    <span data-edit="text" data-key="hero.cta_label"><?= e(c('hero.cta_label')) ?></span>
                    <i data-lucide="arrow-right" class="h-4 w-4" aria-hidden="true"></i>
                </a>
                <a href="#how"
                   class="inline-flex w-full items-center justify-center gap-1.5 rounded-full border border-ink-200 bg-white px-6 py-3 text-base font-medium text-ink-700 hover:border-ink-300 hover:bg-ink-50 sm:w-auto">
                    <span data-edit="text" data-key="hero.cta_secondary_label"><?= e(c('hero.cta_secondary_label')) ?></span>
                </a>
            </div>
        </div>

        <div class="mt-14 sm:mt-20">
            <div class="relative mx-auto max-w-5xl">
                <div class="absolute -inset-x-4 -top-4 -bottom-4 rounded-3xl bg-gradient-to-tr from-brand-200/50 via-brand-100/30 to-transparent blur-2xl" aria-hidden="true"></div>
                <img src="<?= e(c('hero.image')) ?>"
                     alt="<?= e(c('hero.image_alt')) ?>"
                     data-edit="image" data-key="hero.image"
                     class="relative w-full rounded-2xl border border-ink-100 bg-white shadow-2xl shadow-brand-900/5"
                     loading="eager"
                     fetchpriority="high">
            </div>
        </div>
    </div>
</section>
