<?php
// site/sections/final_cta.php — closing CTA + waitlist form.
// Form posts to /api/form.php. JS swaps to a success message on 200/2xx;
// without JS the browser navigates to /api/form.php which returns JSON
// (graceful but ugly — Phase 14 polish: render an HTML success page on
// no-JS POST). CSRF token comes from core/lib/csrf.php (session-bound).
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

        <form id="waitlist-form"
              action="/api/form.php"
              method="post"
              novalidate
              class="mx-auto mt-10 grid gap-4 rounded-2xl border border-ink-100 bg-white p-6 shadow-sm sm:p-8">

            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

            <!-- Honeypot — humans don't fill this; bots usually do. CSS-hidden but valid. -->
            <div aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">
                <label for="website">Leave this empty</label>
                <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
            </div>

            <div>
                <label for="full_name" class="mb-1.5 block text-sm font-medium text-ink-800">
                    <span data-edit="text" data-key="form.full_name_label"><?= e(c('form.full_name_label')) ?></span>
                    <span class="text-brand-600" aria-hidden="true">*</span>
                </label>
                <input id="full_name" name="full_name" type="text" required maxlength="100" autocomplete="name"
                       class="w-full rounded-lg border border-ink-200 bg-white px-3 py-2.5 text-base text-ink-900 placeholder:text-ink-500 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-ink-800">
                        <span data-edit="text" data-key="form.email_label"><?= e(c('form.email_label')) ?></span>
                        <span class="text-brand-600" aria-hidden="true">*</span>
                    </label>
                    <input id="email" name="email" type="email" required maxlength="150" autocomplete="email"
                           class="w-full rounded-lg border border-ink-200 bg-white px-3 py-2.5 text-base text-ink-900 placeholder:text-ink-500 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div>
                    <label for="phone" class="mb-1.5 block text-sm font-medium text-ink-800">
                        <span data-edit="text" data-key="form.phone_label"><?= e(c('form.phone_label')) ?></span>
                        <span class="text-brand-600" aria-hidden="true">*</span>
                    </label>
                    <input id="phone" name="phone" type="tel" required maxlength="25" autocomplete="tel"
                           placeholder="<?= e(c('form.phone_placeholder')) ?>"
                           class="w-full rounded-lg border border-ink-200 bg-white px-3 py-2.5 text-base text-ink-900 placeholder:text-ink-500 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="role" class="mb-1.5 block text-sm font-medium text-ink-800">
                        <span data-edit="text" data-key="form.role_label"><?= e(c('form.role_label')) ?></span>
                        <span class="text-brand-600" aria-hidden="true">*</span>
                    </label>
                    <select id="role" name="role" required
                            class="w-full rounded-lg border border-ink-200 bg-white px-3 py-2.5 text-base text-ink-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <option value=""><?= e(c('form.role_placeholder')) ?></option>
                        <?php foreach (c_list('form.role_options', ['Freelancer','Agency Owner','In-house Marketer','Other']) as $opt): ?>
                            <option><?= e($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="clients_managed" class="mb-1.5 block text-sm font-medium text-ink-800">
                        <span data-edit="text" data-key="form.clients_label"><?= e(c('form.clients_label')) ?></span>
                    </label>
                    <select id="clients_managed" name="clients_managed"
                            class="w-full rounded-lg border border-ink-200 bg-white px-3 py-2.5 text-base text-ink-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <option value=""><?= e(c('form.clients_placeholder')) ?></option>
                        <?php foreach (c_list('form.clients_options', ['1–3','4–10','10+','Just exploring']) as $opt): ?>
                            <option><?= e($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label for="bottleneck" class="mb-1.5 block text-sm font-medium text-ink-800">
                    <span data-edit="text" data-key="form.bottleneck_label"><?= e(c('form.bottleneck_label')) ?></span>
                </label>
                <textarea id="bottleneck" name="bottleneck" maxlength="500" rows="3"
                          placeholder="<?= e(c('form.bottleneck_placeholder')) ?>"
                          class="w-full resize-none rounded-lg border border-ink-200 bg-white px-3 py-2.5 text-base text-ink-900 placeholder:text-ink-500 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500"></textarea>
            </div>

            <button id="waitlist-submit" type="submit"
                    class="inline-flex items-center justify-center gap-1.5 rounded-full bg-brand-600 px-6 py-3 text-base font-medium text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60">
                <span id="waitlist-submit-label" data-edit="text" data-key="form.submit_label"><?= e(c('form.submit_label')) ?></span>
                <i data-lucide="arrow-right" class="h-4 w-4" aria-hidden="true"></i>
            </button>

            <p class="text-center text-xs text-ink-500">
                <span data-edit="text" data-key="form.privacy_note"><?= e(c('form.privacy_note')) ?></span>
            </p>

            <div id="form-error" role="alert" aria-live="polite"
                 class="hidden rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800"></div>
        </form>

        <div id="form-success" hidden
             class="mx-auto mt-10 max-w-md rounded-2xl border border-brand-200 bg-white p-8 text-center shadow-sm">
            <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-brand-100 text-brand-700">
                <i data-lucide="check" class="h-6 w-6" aria-hidden="true"></i>
            </div>
            <h3 class="mt-4 text-xl font-semibold text-ink-900">
                <span data-edit="text" data-key="form.success_heading"><?= e(c('form.success_heading')) ?></span>
            </h3>
            <p class="mt-2 text-sm leading-relaxed text-ink-600">
                <span data-edit="text" data-key="form.success_body"><?= e(c('form.success_body')) ?></span>
            </p>
        </div>
    </div>
</section>

<script src="/assets/js/form.js" defer></script>
