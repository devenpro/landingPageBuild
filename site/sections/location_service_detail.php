<?php
// site/sections/location_service_detail.php — render a Location Services entry (v2 Stage 5).
//
// Pulls the parent Service entry (by service_entry_id) and the location
// taxonomy term (by location_term_id) from data_json. Falls back to the
// Service's data when no per-location override exists.
//
// Globals: $gua_content_entry / $gua_content_type / $gua_content_data

declare(strict_types=1);

require_once __DIR__ . '/../../core/lib/content/entries.php';
require_once __DIR__ . '/../../core/lib/taxonomy.php';

$entry = $GLOBALS['gua_content_entry'] ?? [];
$data  = $GLOBALS['gua_content_data']  ?? [];

$service_entry_id = (int)($data['service_entry_id'] ?? 0);
$location_term_id = (int)($data['location_term_id'] ?? 0);

$service       = $service_entry_id > 0 ? content_entry_by_id($service_entry_id) : null;
$service_data  = $service ? content_entry_data($service) : [];
$location_term = $location_term_id > 0 ? taxonomy_term_by_id($location_term_id) : null;
$location_path = $location_term_id > 0 ? term_path($location_term_id) : [];

$title           = (string)($entry['title'] ?? '');
$location_name   = (string)($data['city'] ?? ($location_term['name'] ?? ''));
$region          = (string)($data['region'] ?? '');
$intro           = (string)($data['intro']  ?? ($service_data['short_desc'] ?? ''));
$address         = (string)($data['address']       ?? '');
$phone           = (string)($data['phone']         ?? '');
$map_embed_url   = (string)($data['map_embed_url'] ?? '');

$features_raw = (string)($service_data['features_list'] ?? '');
$features = array_values(array_filter(array_map('trim', preg_split('/\R+/', $features_raw))));

$primary_cta_label = (string)($service_data['primary_cta_label'] ?? 'Get started');
$primary_cta_url   = (string)($service_data['primary_cta_url']   ?? '#contact');
?>
<main id="main-content" class="mx-auto max-w-4xl px-4 py-16 sm:px-6 lg:py-24">
    <?php if ($location_path !== []): ?>
        <nav class="text-xs text-ink-500" aria-label="Breadcrumb">
            <ol class="flex flex-wrap items-center gap-1.5">
                <li><a href="/" class="hover:text-ink-700">Home</a></li>
                <li>›</li>
                <?php if ($service): ?>
                    <li><a href="/services/<?= e($service['slug']) ?>" class="hover:text-ink-700"><?= e($service['title']) ?></a></li>
                    <li>›</li>
                <?php endif; ?>
                <?php foreach ($location_path as $i => $node): ?>
                    <li class="<?= $i === count($location_path) - 1 ? 'text-ink-800 font-medium' : '' ?>"><?= e($node['name']) ?></li>
                    <?php if ($i < count($location_path) - 1): ?><li>›</li><?php endif; ?>
                <?php endforeach; ?>
            </ol>
        </nav>
    <?php endif; ?>

    <header class="mt-4 text-center">
        <p class="text-xs font-semibold uppercase tracking-wider text-brand-600">
            <?= e($service ? $service['title'] : 'Service') ?><?= $location_name !== '' ? ' · ' . e($location_name) : '' ?>
        </p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-ink-900 sm:text-4xl"><?= e($title) ?></h1>
        <?php if ($intro !== ''): ?>
            <p class="mx-auto mt-3 max-w-2xl text-lg text-ink-600"><?= e($intro) ?></p>
        <?php endif; ?>
        <a href="<?= e($primary_cta_url) ?>"
           class="mt-6 inline-flex items-center gap-2 rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-700">
            <?= e($primary_cta_label) ?>
        </a>
    </header>

    <?php if ($features !== []): ?>
        <section class="mt-12">
            <h2 class="text-xl font-semibold text-ink-900">What we deliver in <?= e($location_name ?: 'this location') ?></h2>
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

    <?php if ($address !== '' || $phone !== '' || $map_embed_url !== ''): ?>
        <section class="mt-12 grid gap-4 rounded-2xl border border-ink-100 bg-white p-6 sm:grid-cols-2">
            <div>
                <h2 class="text-lg font-semibold text-ink-900">Visit us</h2>
                <?php if ($address !== ''): ?>
                    <p class="mt-2 text-sm text-ink-600"><?= nl2br(e($address)) ?></p>
                <?php endif; ?>
                <?php if ($region !== ''): ?>
                    <p class="mt-1 text-xs text-ink-500"><?= e($region) ?></p>
                <?php endif; ?>
                <?php if ($phone !== ''): ?>
                    <p class="mt-3 text-sm">
                        <a href="tel:<?= e($phone) ?>" class="font-medium text-brand-700 hover:text-brand-800"><?= e($phone) ?></a>
                    </p>
                <?php endif; ?>
            </div>
            <?php if ($map_embed_url !== ''): ?>
                <div class="aspect-video overflow-hidden rounded-lg border border-ink-100">
                    <iframe src="<?= e($map_embed_url) ?>" loading="lazy" class="h-full w-full" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
