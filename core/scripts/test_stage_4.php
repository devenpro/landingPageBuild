<?php
// core/scripts/test_stage_4.php — smoke test for v2 Stage 4 (Content types + entries).
//
// Exercises:
//   1. Schema present (content_types, content_entries)
//   2. Seed (3 built-in types: testimonials non-routable, services + ad_landing_pages routable)
//   3. Entry CRUD + slug validation
//   4. Route resolution (/services/{slug} and /lp/{slug} match the right entry)
//   5. content_resolve_route returns null for non-matching paths
//   6. Default robots=noindex,nofollow trigger on ad_landing_pages inserts
//   7. data_json round-trip (write structured data, read back the same shape)
//
// Cleans up its test rows. CLI only.

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/content/types.php';
require_once __DIR__ . '/../lib/content/entries.php';

$failures = [];
$assert = function (string $name, $expected, $actual) use (&$failures): void {
    if ($expected === $actual) {
        echo "  ✓ $name\n";
    } else {
        echo "  ✗ $name\n";
        echo "      expected: " . var_export($expected, true) . "\n";
        echo "      actual:   " . var_export($actual, true) . "\n";
        $failures[] = $name;
    }
};
$assert_true = function (string $name, $cond) use (&$failures): void {
    if ($cond) {
        echo "  ✓ $name\n";
    } else {
        echo "  ✗ $name (expected truthy)\n";
        $failures[] = $name;
    }
};

echo "Stage 4 smoke test\n";
echo "------------------\n";

$pdo = db();

// 1. Schema
foreach (['content_types', 'content_entries'] as $t) {
    $exists = (bool) $pdo->query(
        "SELECT 1 FROM sqlite_master WHERE type='table' AND name='" . $t . "' LIMIT 1"
    )->fetchColumn();
    $assert("table $t exists", true, $exists);
}

// 2. Seed — Stage 4 ships 3 types; Stage 5 added Location Services, so on a
// fresh install all 4 are present. Either is acceptable for this assertion.
$types = content_types_all();
$assert_true('at least 3 built-in types seeded', count($types) >= 3);
$by_slug = [];
foreach ($types as $t) $by_slug[$t['slug']] = $t;
$assert_true('testimonials exists', isset($by_slug['testimonials']));
$assert_true('services exists', isset($by_slug['services']));
$assert_true('ad_landing_pages exists', isset($by_slug['ad_landing_pages']));
$assert('testimonials non-routable', 0, (int)$by_slug['testimonials']['is_routable']);
$assert('services route pattern',  '/services/{slug}', $by_slug['services']['route_pattern']);
$assert('ad_landing_pages route pattern', '/lp/{slug}', $by_slug['ad_landing_pages']['route_pattern']);

// 3. Entry CRUD
$service_id = (int)$by_slug['services']['id'];
$entry_id = content_entry_create([
    'type_id' => $service_id,
    'slug'    => 'stage4-seo-audit',
    'title'   => 'Stage 4 SEO Audit',
    'data'    => [
        'short_desc' => 'Test service entry',
        'long_desc'  => 'A multi-line description.',
        'starting_price' => '₹50,000',
        'primary_cta_label' => 'Book now',
        'primary_cta_url'   => '/contact',
        'features_list' => "Audit one\nAudit two",
        'faqs_list' => json_encode([['q'=>'How long?', 'a'=>'4 weeks']]),
    ],
    'status'  => 'published',
]);
$assert_true('create returns id', $entry_id > 0);

$entry = content_entry_by_id($entry_id);
$assert_true('fetch by id works', $entry !== null);
$assert('slug round-trip',  'stage4-seo-audit', $entry['slug']);
$assert('title round-trip', 'Stage 4 SEO Audit', $entry['title']);

$data = content_entry_data($entry);
$assert('data_json round-trip preserves short_desc', 'Test service entry', $data['short_desc'] ?? '');
$assert('data_json round-trip preserves price',      '₹50,000', $data['starting_price'] ?? '');

// 4. Routing
$match = content_resolve_route('/services/stage4-seo-audit');
$assert_true('content_resolve_route matched /services/stage4-seo-audit', $match !== null);
if ($match !== null) {
    $assert('routed type slug',  'services', $match['type']['slug']);
    $assert('routed entry id',   $entry_id, (int)$match['entry']['id']);
}

// 5. Non-match returns null
$assert('non-matching path returns null', null, content_resolve_route('/nonexistent/foo'));
$assert('home path returns null',         null, content_resolve_route('/'));

// 6. Slug validation rejects bad slug
$rejected = false;
try {
    content_entry_create(['type_id' => $service_id, 'slug' => 'BAD SLUG', 'title' => 'x']);
} catch (InvalidArgumentException $e) {
    $rejected = true;
}
$assert('slug validator rejects bad slug', true, $rejected);

// 7. Ad LP default robots trigger
$adlp_id = (int)$by_slug['ad_landing_pages']['id'];
$adlp_entry_id = content_entry_create([
    'type_id' => $adlp_id,
    'slug'    => 'stage4-test-lp',
    'title'   => 'Stage 4 Test LP',
    'data'    => ['headline' => 'Test'],
    'status'  => 'published',
]);
$adlp_entry = content_entry_by_id($adlp_entry_id);
$assert('ad LP default robots = noindex,nofollow', 'noindex,nofollow', $adlp_entry['robots']);

// Ad LP route resolves
$adlp_match = content_resolve_route('/lp/stage4-test-lp');
$assert_true('ad LP route /lp/{slug} matched', $adlp_match !== null);

// Cleanup
content_entry_delete($entry_id);
content_entry_delete($adlp_entry_id);
$assert('entry deleted', null, content_entry_by_id($entry_id));

echo "\n";
if ($failures === []) {
    echo "PASS — all assertions met.\n";
    exit(0);
}
echo "FAIL — " . count($failures) . " assertion(s) did not match:\n";
foreach ($failures as $f) {
    echo "  - $f\n";
}
exit(1);
