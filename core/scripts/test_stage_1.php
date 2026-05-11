<?php
// core/scripts/test_stage_1.php — smoke test for v2 Stage 1 settings.
//
// Exercises the settings_get / settings_set fallback chain end-to-end:
//   1. site_settings table exists and has the seeded metadata rows
//   2. settings_get falls through DB → .env → default correctly
//   3. settings_set persists, invalidates cache, settings_source updates
//   4. clearing a value (null) restores .env / default fallback
//   5. type casting works for number / boolean / string
//
// Exits 0 on full pass, 1 on any failure with a short diagnostic.
// CLI only.

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

echo "Stage 1 smoke test\n";
echo "------------------\n";

// 1. Schema present
$pdo = db();
$count = (int) $pdo->query('SELECT COUNT(*) FROM site_settings')->fetchColumn();
$assert('seed: 13 metadata rows', 13, $count);

// 2. Resolution chain — site_name comes from .env (SITE_NAME)
$assert('site_name source defaults to env', 'env', settings_source('site_name'));
$assert('site_name reads .env value',       'Go Ultra AI', settings_get('site_name'));

// 3. settings_set then read in same request reflects DB
settings_set('site_name', 'Stage 1 Test Name');
$assert('site_name source after set',  'db', settings_source('site_name'));
$assert('site_name reads DB value',    'Stage 1 Test Name', settings_get('site_name'));

// 4. Type casting — number coerces to int
settings_set('webhook_timeout_seconds', '15');
$webhook_to = settings_get('webhook_timeout_seconds');
$assert('number type returns int',     true, is_int($webhook_to));
$assert('number value matches',        15, $webhook_to);

// 5. Type casting — boolean coerces to bool
settings_set('ai_chat_enabled', '1');
$assert('boolean true cast',           true, settings_get('ai_chat_enabled'));
settings_set('ai_chat_enabled', '0');
$assert('boolean false cast',          false, settings_get('ai_chat_enabled'));

// 6. Clearing a value restores fallback
settings_set('site_name', null);
$assert('cleared → source back to env','env', settings_source('site_name'));
$assert('cleared → reads .env again',  'Go Ultra AI', settings_get('site_name'));

// 7. Default fallback when neither DB nor .env
$assert('unknown key → default',       'fallback', settings_get('nonexistent_key_xyz', 'fallback'));

// 8. Group queries
$general = settings_all_in_group('general');
$assert('general group has 5 settings', 5, count($general));

$groups = settings_groups();
$assert('groups returned in seed order', ['general','ai','webhooks','media'], $groups);

// 9. Reset modified settings to clean state
settings_set('webhook_timeout_seconds', null);
settings_set('ai_chat_enabled', null);

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
