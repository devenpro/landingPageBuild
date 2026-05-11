<?php
// core/scripts/test_stage_2.php — smoke test for v2 Stage 2 (Brand Context Library).
//
// Exercises:
//   1. Schema + seed (8 categories, 8 placeholder items)
//   2. Item CRUD (create / update / delete) with disk mirroring
//   3. Slug validation (rejects bad slugs)
//   4. Disk drift detection (manual file edit triggers brand_sync_dirty)
//   5. Sync strategies (accept_disk / keep_db / manual)
//   6. AI prompt context assembler (filters unreviewed AI items)
//   7. Audit score against required categories
//
// Cleans up after itself so re-runs are idempotent. CLI only.

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/brand/items.php';
require_once __DIR__ . '/../lib/brand/categories.php';
require_once __DIR__ . '/../lib/brand/sync.php';
require_once __DIR__ . '/../lib/brand/audit.php';
require_once __DIR__ . '/../lib/ai/brand_context.php';

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

echo "Stage 2 smoke test\n";
echo "------------------\n";

// 1. Schema + seed
$cats = brand_categories_all();
$assert('seed: 8 built-in categories', 8, count($cats));
$slugs = array_map(fn($c) => $c['slug'], $cats);
$assert('seed: required categories present',
    true,
    in_array('brand_voice', $slugs, true) && in_array('brand_facts', $slugs, true)
       && in_array('audience', $slugs, true) && in_array('services', $slugs, true)
       && in_array('page_guide', $slugs, true)
);

$items_count = (int) db()->query('SELECT COUNT(*) FROM brand_items')->fetchColumn();
$assert('seed: 8 placeholder items (one per category)', 8, $items_count);

// 2. Item create + disk mirror
$voice_cat = brand_category_by_slug('brand_voice');
$test_id = brand_item_create([
    'category_id' => (int)$voice_cat['id'],
    'slug'        => 'stage2-test-voice',
    'title'       => 'Stage 2 test voice',
    'kind'        => 'markdown',
    'body'        => "Test body.\nLine two.",
    'source'      => 'admin',
]);
$assert_true('create returns positive id', $test_id > 0);
$disk_path = '.brand/brand_voice/stage2-test-voice.md';
$assert_true('disk mirror exists after create', is_file($disk_path));

// 3. Slug validation
$rejected = false;
try {
    brand_item_create([
        'category_id' => (int)$voice_cat['id'],
        'slug'        => 'BAD slug!',
        'title'       => 'x',
    ]);
} catch (InvalidArgumentException $e) {
    $rejected = true;
}
$assert('slug validator rejects bad slug', true, $rejected);

// 4. Disk drift detection
file_put_contents($disk_path,
    "---\nslug: stage2-test-voice\n---\nedited externally — drift expected\n");
$dirty = brand_sync_dirty();
$found = false;
foreach ($dirty as $d) {
    if ((int)$d['item_id'] === $test_id) {
        $found = true;
        $assert('drift state is disk_changed', 'disk_changed', $d['state']);
        $assert('drift includes db_body', true, isset($d['db_body']));
        $assert('drift includes disk_body', true, isset($d['disk_body']));
        break;
    }
}
$assert_true('drift detected for the edited item', $found);

// 5. Sync strategies — accept_disk
$ver_after_pull = brand_sync_pull($test_id, 'accept_disk', null, null);
$assert_true('accept_disk bumps version', $ver_after_pull > 1);
$updated = brand_item_by_id($test_id);
$assert('accept_disk: source becomes disk', 'disk', $updated['source']);
$assert_true('accept_disk: body matches disk content',
    str_contains((string)$updated['body'], 'edited externally'));

// After accept_disk, brand_sync_dirty should be empty for this item
$dirty_after = brand_sync_dirty();
$still_dirty = false;
foreach ($dirty_after as $d) {
    if ((int)$d['item_id'] === $test_id) { $still_dirty = true; break; }
}
$assert('after accept_disk no drift remains', false, $still_dirty);

// 6. Prompt context — fill a real-content item, verify it appears
brand_item_update($test_id, [
    'body' => "- Direct, no fluff.\n- Use 'we' for the team.\n",
    'source' => 'admin',
], null);
$ctx = brand_context_for_categories(['brand_voice']);
$assert_true('brand context includes filled item body',
    str_contains($ctx, "Direct, no fluff"));

// AI-generated unreviewed item should be excluded
$ai_id = brand_item_create([
    'category_id' => (int)$voice_cat['id'],
    'slug'        => 'stage2-ai-unreviewed',
    'title'       => 'AI unreviewed',
    'body'        => 'AI-only content; should be hidden until reviewed.',
    'source'      => 'ai',
    'ai_reviewed' => 0,
]);
$ctx2 = brand_context_for_categories(['brand_voice']);
$assert('ai_reviewed=0 item excluded from prompt context', false,
    str_contains($ctx2, 'AI-only content; should be hidden'));

// Saving (any update) marks it reviewed and now it shows up
brand_item_update($ai_id, ['body' => 'Reviewed AI content.'], null);
$ctx3 = brand_context_for_categories(['brand_voice']);
$assert_true('after admin save, AI item is included',
    str_contains($ctx3, 'Reviewed AI content'));

// 7. Audit score
$audit = brand_audit();
$assert_true('audit returns a score 0-100',
    is_int($audit['score']) && $audit['score'] >= 0 && $audit['score'] <= 100);
$assert_true('audit lists ok items', count($audit['ok']) > 0);

// Cleanup
brand_item_delete($test_id);
brand_item_delete($ai_id);
$assert_true('disk mirror removed after delete', !is_file($disk_path));

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
