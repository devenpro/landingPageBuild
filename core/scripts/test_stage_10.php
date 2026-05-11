<?php
// core/scripts/test_stage_10.php — smoke test for v2 Stage 10 (site bootstrap).
//
// Exercises:
//   1. Schema: site_settings has 'bootstrap_completed' and 'bootstrap_started_at'.
//   2. Setting defaults: bootstrap_completed reads as false when unset; flips
//      on settings_set('1') and reads back as true.
//   3. /api/bootstrap.php endpoint file present and defines the 4 actions
//      (save_identity, mark_seen, brand_fill, complete).
//   4. /admin/bootstrap.php wizard file present and renders the 6 step labels.
//   5. Dashboard CTA wired (banner HTML present, gated on !bootstrap_completed).
//   6. brand_fill helper plumbing: brand_audit() runs, brand_item_generate
//      prompt builder returns a 2-message array.
//
// Boundary: no live AI calls (no keys in CI). Live behaviour is manual,
// noted in PHASE_STATUS.md verification.

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/settings.php';
require_once __DIR__ . '/../lib/brand/audit.php';
require_once __DIR__ . '/../lib/ai/prompts/brand_item_generate.php';

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

echo "Stage 10 smoke test\n";
echo "-------------------\n";

$pdo = db();

// 1. Schema — settings keys seeded
$setup_keys = $pdo->query("SELECT key FROM site_settings WHERE group_name = 'setup'")->fetchAll(PDO::FETCH_COLUMN);
foreach (['bootstrap_completed', 'bootstrap_started_at'] as $k) {
    $assert("setting $k seeded under group 'setup'", true, in_array($k, $setup_keys, true));
}

// 2. Round-trip: default → set → read back → clear
$pdo->prepare("UPDATE site_settings SET value = NULL WHERE key = :k")
    ->execute([':k' => 'bootstrap_completed']);
// settings_get is per-request cached — bust by re-requiring fresh state via raw query
$reset_value = $pdo->query("SELECT value FROM site_settings WHERE key = 'bootstrap_completed'")->fetchColumn();
$assert('value defaults to NULL when unset',  null, $reset_value === false ? null : $reset_value);

// Test the prepared statement path (settings_set + value_type='boolean')
settings_set('bootstrap_completed', '1', null);
$after_set = $pdo->query("SELECT value FROM site_settings WHERE key = 'bootstrap_completed'")->fetchColumn();
$assert("settings_set('1') persists '1'", '1', (string)$after_set);

// Clear again so the wizard CTA still shows on first manual visit
$pdo->prepare("UPDATE site_settings SET value = NULL WHERE key = :k")
    ->execute([':k' => 'bootstrap_completed']);

// 3. /api/bootstrap.php presence + action verbs
$api_path = GUA_SITE_PATH . '/public/api/bootstrap.php';
$assert_true('api/bootstrap.php shipped', is_file($api_path));
$api_src = (string) file_get_contents($api_path);
foreach (['save_identity', 'mark_seen', 'brand_fill', 'complete'] as $action) {
    $assert_true("api/bootstrap.php handles action '$action'", str_contains($api_src, "case '$action'"));
}
$assert_true('api/bootstrap.php requires CSRF', str_contains($api_src, 'csrf_check'));
$assert_true('api/bootstrap.php gates on auth_current_user', str_contains($api_src, 'auth_current_user'));

// 4. /admin/bootstrap.php wizard
$wiz_path = GUA_SITE_PATH . '/public/admin/bootstrap.php';
$assert_true('admin/bootstrap.php shipped', is_file($wiz_path));
$wiz_src = (string) file_get_contents($wiz_path);
foreach (['Welcome', 'Identity', 'AI key', 'Brand', 'Pages', 'Done'] as $label) {
    $assert_true("wizard exposes step '$label'", str_contains($wiz_src, $label));
}
$assert_true('wizard auth-required', str_contains($wiz_src, 'auth_require_login'));
$assert_true('wizard stamps bootstrap_started_at on first open',
    str_contains($wiz_src, 'bootstrap_started_at'));

// 5. Dashboard CTA
$dash_src = (string) file_get_contents(GUA_SITE_PATH . '/public/admin/dashboard.php');
$assert_true('dashboard reads bootstrap_completed',  str_contains($dash_src, "settings_get('bootstrap_completed'"));
$assert_true('dashboard renders wizard CTA banner', str_contains($dash_src, 'Run the setup wizard'));
$assert_true('dashboard gates banner on !bootstrap_done', str_contains($dash_src, 'if (!$bootstrap_done)'));

// 6. brand_fill plumbing
$audit = brand_audit();
$assert_true('brand_audit returns score key',         array_key_exists('score',   $audit));
$assert_true('brand_audit returns missing array',     is_array($audit['missing'] ?? null));
$msgs = brand_item_generate_messages('brand_voice', 'Brand Voice', 'A short brief about the test business');
$assert('brand_item_generate_messages returns 2 messages', 2, count($msgs));
$assert('first message is system',  'system', $msgs[0]['role'] ?? '');
$assert('second message is user',   'user',   $msgs[1]['role'] ?? '');
$assert_true('user message includes the brief', str_contains($msgs[1]['content'] ?? '', 'A short brief'));

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
