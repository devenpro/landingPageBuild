<?php
// site/sections/service_detail.php — render a Services content entry (v2 Stage 4).
//
// Globals exposed by render_content_entry() in core/lib/pages.php:
//   $gua_content_entry  — content_entries row
//   $gua_content_type   — content_types row
//   $gua_content_data   — decoded data_json (associative array)

declare(strict_types=1);

/** @var array $gua_content_entry */
/** @var array $gua_content_data */
$entry = $GLOBALS['gua_content_entry'] ?? [];
$data  = $GLOBALS['gua_content_data']  ?? [];

$title             = (string)($entry['title'] ?? '');
$short_desc        = (string)($data['short_desc']        ?? '');
$long_desc         = (string)($data['long_desc']         ?? '');
$starting_price    = (string)($data['starting_price']    ?? '');
$primary_cta_label = (string)($data['primary_cta_label'] ?? 'Get started');
$primary_cta_url   = (string)($data['primary_cta_url']   ?? '#contact');
$features_raw      = (string)($data['features_list']     ?? '');
$faqs_raw          = (string)($data['faqs_list']         ?? '');

$features = array_values(array_filter(array_map('trim', preg_split('/\R+/', $features_raw))));
$faqs = [];
if ($faqs_raw !== '') {
    $decoded = json_decode($faqs_raw, true);
    if (is_array($decoded)) $faqs = $decoded;
}
?>
<main id="main-content" class="mx-auto max-w-4xl px-4 py-16 sm:px-6 lg:py-24">
    <header class="text-center">
        <p class="text-xs font-semibold uppercase tracking-wider text-brand-600">Service</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-ink-900 sm:text-4xl"><?= e($title) ?></h1>
        <?php if ($short_desc !== ''): ?>
            <p class="mx-auto mt-3 max-w-2xl text-lg text-ink-600"><?= e($short_desc) ?></p>
        <?php endif; ?>
        <?php if ($starting_price !== ''): ?>
            <p class="mt-4 text-sm text-ink-500">Starting at <span class="font-semibold text-ink-800"><?= e($starting_price) ?></span></p>
        <?php endif; ?>
        <a href="<?= e($primary_cta_url) ?>"
           class="mt-6 inline-flex items-center gap-2 rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-brand-700">
            <?= e($primary_cta_label) ?>
        </a>
    </header>

    <?php if ($long_desc !== ''): ?>
        <section class="mx-auto mt-12 max-w-3xl prose prose-ink">
            <?= nl2br(e($long_desc)) ?>
        </section>
    <?php endif; ?>

    <?php if ($features !== []): ?>
        <section class="mt-12">
            <h2 class="text-xl font-semibold text-ink-900">What's included</h2>
            <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                <?php foreach ($features as $f): ?>
                    <li class="flex items-start gap-2 rounded-lg border border-ink-100 bg-white p-3 text-sm text-ink-700">
                        <span class="mt-0.5 inline-block h-2 w-2 shrink-0 rounded-full bg-brand-500"></span>
                        <span><?= e($f) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php if ($faqs !== []): ?>
        <section class="mt-12">
            <h2 class="text-xl font-semibold text-ink-900">FAQs</h2>
            <div class="mt-4 space-y-2">
                <?php foreach ($faqs as $faq):
                    if (!is_array($faq)) continue;
                    $q = (string)($faq['q'] ?? '');
                    $a = (string)($faq['a'] ?? '');
                    if ($q === '' && $a === '') continue;
                ?>
                    <details class="rounded-lg border border-ink-100 bg-white p-4">
                        <summary class="cursor-pointer font-medium text-ink-900"><?= e($q) ?></summary>
                        <p class="mt-2 text-sm text-ink-600"><?= nl2br(e($a)) ?></p>
                    </details>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</main>
