<?php
// includes/sections/footer.php — brand line, parent company, legal links,
// and the discreet admin login link per brief §5 / §6.
?>
<footer class="border-t border-ink-100 bg-white">
    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="flex flex-col items-start justify-between gap-8 sm:flex-row sm:items-center">
            <div>
                <div class="flex items-center gap-2 text-base font-semibold text-ink-900">
                    <span class="grid h-7 w-7 place-items-center rounded-md bg-brand-600 text-white">
                        <?= lucide('zap', 'h-4 w-4') ?>
                    </span>
                    <span><?= e(c('nav.brand')) ?></span>
                </div>
                <p class="mt-3 max-w-md text-sm text-ink-500">
                    <span data-edit="text" data-key="footer.tagline"><?= e(c('footer.tagline')) ?></span>
                </p>
                <p class="mt-2 text-xs text-ink-500">
                    <span data-edit="text" data-key="footer.parent_company"><?= e(c('footer.parent_company')) ?></span>
                </p>
            </div>

            <ul class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-ink-500">
                <li><a href="#" class="hover:text-ink-800" data-edit="text" data-key="footer.privacy_label"><?= e(c('footer.privacy_label')) ?></a></li>
                <li><a href="#" class="hover:text-ink-800" data-edit="text" data-key="footer.terms_label"><?= e(c('footer.terms_label')) ?></a></li>
                <li><a href="#" class="hover:text-ink-800" data-edit="text" data-key="footer.contact_label"><?= e(c('footer.contact_label')) ?></a></li>
                <li><a href="/admin/login.php" class="text-ink-500 hover:text-ink-500" data-edit="text" data-key="footer.admin_label"><?= e(c('footer.admin_label')) ?></a></li>
            </ul>
        </div>

        <div class="mt-10 border-t border-ink-100 pt-6 text-xs text-ink-500">
            <span data-edit="text" data-key="footer.copyright"><?= e(c('footer.copyright')) ?></span>
        </div>
    </div>
</footer>
