<?php
// core/scripts/test_stage_3.php — smoke test for v2 Stage 3 (Content blocks rework).
//
// Exercises:
//   1. Migration shape — legacy_content + content_blocks + content_block_fields + page_fields tables exist
//   2. Data integrity — every legacy_content row has a matching content_block_fields row (or page_fields)
//   3. c() resolution — well-known v1 keys (hero.headline, faq.1.q) resolve through the new tables
//   4. block() helper — returns active block row
//   5. page_fields precedence — a page-scoped override takes priority over a block field
//   6. legacy_content read-fallback — c() falls back when a key isn't in the new tables
//
// Cleans up its test rows. CLI only.

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require __DIR__ . '/../lib/bootstrap.php';

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

echo "Stage 3 smoke test\n";
echo "------------------\n";

$pdo = db();

// 1. Schema present
$tables = ['legacy_content', 'content_blocks', 'content_block_fields', 'page_fields'];
foreach ($tables as $t) {
    $exists = (bool) $pdo->query(
        "SELECT 1 FROM sqlite_master WHERE type='table' AND name='" . $t . "' LIMIT 1"
    )->fetchColumn();
    $assert("table $t exists", true, $exists);
}

// 2. Data integrity — every legacy non-page row has a matching content_block_fields row
$legacy = (int) $pdo->query(
    "SELECT COUNT(*) FROM legacy_content WHERE key NOT LIKE 'page.%' AND instr(key, '.') > 0"
)->fetchColumn();
$migrated = (int) $pdo->query('SELECT COUNT(*) FROM content_block_fields')->fetchColumn();
$assert('non-page legacy rows == content_block_fields rows', $legacy, $migrated);

$blocks = (int) $pdo->query('SELECT COUNT(*) FROM content_blocks')->fetchColumn();
$assert_true('at least one block created from migration', $blocks > 0);

// 3. c() resolution
$assert_true('c(hero.headline) returns non-empty', c('hero.headline') !== '');
$assert_true('c(faq.1.q) returns non-empty (multi-dot field)', c('faq.1.q') !== '');
$assert_true('c(feature.1.title) returns non-empty', c('feature.1.title') !== '');
$assert('c(nonexistent.key, default) returns default', 'fallback', c('nonexistent.key', 'fallback'));

// 4. block() helper
$hero = block_get('hero');
$assert_true('block_get(hero) returns row', $hero !== null && (string)$hero['slug'] === 'hero');

// 5. page_fields precedence — set up: pick the homepage (slug='home'), write a page_fields override
$home_id = (int)$pdo->query("SELECT id FROM pages WHERE slug = 'home' LIMIT 1")->fetchColumn();
$assert_true('home page exists', $home_id > 0);

$override = 'STAGE3 OVERRIDE — should win over block value';
$pdo->prepare(
    'INSERT INTO page_fields (page_id, field_key, value, type) VALUES (:p, :k, :v, "text")
     ON CONFLICT(page_id, field_key) DO UPDATE SET value = excluded.value'
)->execute([':p' => $home_id, ':k' => 'hero.headline', ':v' => $override]);

content_set_prefix('page.home');
$resolved = c('hero.headline');
$assert('page_fields override returned', $override, $resolved);
content_set_prefix('');

// Clean up override
$pdo->prepare(
    'DELETE FROM page_fields WHERE page_id = :p AND field_key = :k'
)->execute([':p' => $home_id, ':k' => 'hero.headline']);

// 6. legacy_content read-fallback — insert a row directly into legacy that doesn't exist elsewhere
$pdo->prepare('INSERT OR IGNORE INTO legacy_content (key, value, type) VALUES (:k, :v, "text")')
    ->execute([':k' => 'stage3.legacy_only', ':v' => 'from-legacy']);

// Bust the c() cache by reloading via a fresh process — easier: just check directly via the helper
// Since content_all() caches in a static, we need a fresh request. Workaround: run a subprocess.
$cmd = 'php -r "require \"' . __DIR__ . '/../lib/bootstrap.php\"; echo c(\"stage3.legacy_only\");"';
$out = trim((string) shell_exec($cmd));
$assert('legacy_content row read by fresh c() call', 'from-legacy', $out);

$pdo->prepare('DELETE FROM legacy_content WHERE key = :k')->execute([':k' => 'stage3.legacy_only']);

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
