<?php
// site/sections/ad_lp_detail.php — render an Ad Landing Pages content entry (v2 Stage 4).
//
// Pulls every field from data_json (no content_blocks lookups — ad LPs
// own their content). Injects Meta Pixel + Google Tag <script> tags only
// when the corresponding fields are set.
//
// Globals (set by render_content_entry):
//   $gua_content_entry / $gua_content_type / $gua_content_data

declare(strict_types=1);

$entry = $GLOBALS['gua_content_entry'] ?? [];
$data  = $GLOBALS['gua_content_data']  ?? [];

$headline            = (string)($data['headline']            ?? ($entry['title'] ?? ''));
$eyebrow             = (string)($data['eyebrow']             ?? '');
$subheadline         = (string)($data['subheadline']         ?? '');
$hero_image_url      = (string)($data['hero_image_url']      ?? '');
$primary_cta_label   = (string)($data['primary_cta_label']   ?? 'Get started');
$primary_cta_action  = (string)($data['primary_cta_action']  ?? 'form');
$primary_cta_target  = (string)($data['primary_cta_target']  ?? '#waitlist');
$secondary_cta_label = (string)($data['secondary_cta_label'] ?? '');
$benefits_raw        = (string)($data['benefits_list']       ?? '');

$meta_pixel_id    = (string)($data['meta_pixel_id']    ?? '');
$google_tag_id    = (string)($data['google_tag_id']    ?? '');
$conversion_event = (string)($data['conversion_event'] ?? '');

$benefits = array_values(array_filter(array_map('trim', preg_split('/\R+/', $benefits_raw))));

// Resolve primary CTA href based on action
$cta_href = $primary_cta_target;
if ($primary_cta_action === 'phone' && $primary_cta_target !== '') {
    $cta_href = 'tel:' . $primary_cta_target;
}
?>
<?php if ($meta_pixel_id !== ''): ?>
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init', <?= json_encode($meta_pixel_id) ?>);
fbq('track', 'PageView');
</script>
<?php endif; ?>

<?php if ($google_tag_id !== ''): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($google_tag_id) ?>"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', <?= json_encode($google_tag_id) ?>);
</script>
<?php endif; ?>

<main id="main-content">
    <section class="mx-auto max-w-4xl px-4 py-16 text-center sm:px-6 lg:py-24">
        <?php if ($eyebrow !== ''): ?>
            <p class="text-xs font-semibold uppercase tracking-wider text-brand-600"><?= e($eyebrow) ?></p>
        <?php endif; ?>
        <h1 class="mt-2 text-4xl font-semibold tracking-tight text-ink-900 sm:text-5xl"><?= e($headline) ?></h1>
        <?php if ($subheadline !== ''): ?>
            <p class="mx-auto mt-4 max-w-2xl text-lg text-ink-600"><?= e($subheadline) ?></p>
        <?php endif; ?>

        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <a href="<?= e($cta_href) ?>"
               <?php if ($meta_pixel_id !== '' && $conversion_event !== ''): ?>
                   onclick="try{fbq('track', <?= json_encode($conversion_event) ?>);}catch(e){}"
               <?php endif; ?>
               class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-5 py-3 text-base font-medium text-white shadow-sm hover:bg-brand-700">
                <?= e($primary_cta_label) ?>
            </a>
            <?php if ($secondary_cta_label !== ''): ?>
                <a href="#learn-more"
                   class="inline-flex items-center gap-2 rounded-lg border border-ink-200 bg-white px-5 py-3 text-base font-medium text-ink-700 hover:bg-ink-50">
                    <?= e($secondary_cta_label) ?>
                </a>
            <?php endif; ?>
        </div>

        <?php if ($hero_image_url !== ''): ?>
            <img src="<?= e($hero_image_url) ?>" alt=""
                 class="mx-auto mt-12 max-w-3xl rounded-2xl shadow-2xl shadow-brand-900/10"
                 loading="eager" decoding="async">
        <?php endif; ?>
    </section>

    <?php if ($benefits !== []): ?>
        <section id="learn-more" class="mx-auto max-w-4xl px-4 py-12 sm:px-6">
            <h2 class="text-center text-2xl font-semibold text-ink-900">Why this works</h2>
            <ul class="mt-6 grid gap-3 sm:grid-cols-2">
                <?php foreach ($benefits as $b): ?>
                    <li class="flex items-start gap-2 rounded-lg border border-ink-100 bg-white p-4 text-sm text-ink-700">
                        <span class="mt-1 inline-block h-2 w-2 shrink-0 rounded-full bg-brand-500"></span>
                        <span><?= e($b) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</main>
