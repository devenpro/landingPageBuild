<?php
// core/scripts/test_stage_8.php — smoke test for v2 Stage 8 (AI providers v2).
//
// Exercises:
//   1. Schema: ai_model_cache table exists.
//   2. Settings: per-provider default-model rows + ai_model_cache_ttl_hours seeded.
//   3. Provider allowlist: GUA_AI_PROVIDERS contains all 6 providers.
//   4. Adapters load: each adapter file exists and registers
//      ai_provider_<name>_chat() AND ai_provider_<name>_list_models().
//   5. Runtime defaults: GUA_*_DEFAULT_MODEL constants are defined.
//   6. ai_default_model_for() returns a non-empty model per provider.
//   7. Model cache round-trip: put/get/delete via ai_models_cache_* helpers.
//   8. Staleness: ai_models_cache_is_stale() respects TTL.
//
// Does NOT make live API calls (no keys available in CI); live calls are
// covered by manual verification described in PHASE_STATUS.md.

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/ai/keys.php';
require_once __DIR__ . '/../lib/ai/models.php';

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

echo "Stage 8 smoke test\n";
echo "------------------\n";

$pdo = db();

// 1. Schema
$has_table = (bool) $pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='ai_model_cache'")->fetchColumn();
$assert('ai_model_cache table exists', true, $has_table);

$cols = array_column($pdo->query('PRAGMA table_info(ai_model_cache)')->fetchAll(), 'name');
foreach (['provider', 'models_json', 'fetched_at', 'source_key_id'] as $c) {
    $assert("ai_model_cache has $c", true, in_array($c, $cols, true));
}

// 2. Settings rows
$ai_keys = $pdo->query("SELECT key FROM site_settings WHERE group_name = 'ai'")->fetchAll(PDO::FETCH_COLUMN);
$expected_new = [
    'anthropic_default_model',
    'openai_default_model',
    'grok_default_model',
    'gemini_default_model',
    'openrouter_default_model',
    'ai_model_cache_ttl_hours',
];
foreach ($expected_new as $k) {
    $assert("setting $k seeded", true, in_array($k, $ai_keys, true));
}

// 3. Provider allowlist
$expected_providers = ['huggingface', 'gemini', 'openrouter', 'anthropic', 'openai', 'grok'];
foreach ($expected_providers as $p) {
    $assert_true("provider '$p' in allowlist", in_array($p, GUA_AI_PROVIDERS, true));
}

// 4. Adapters load and register both functions
foreach ($expected_providers as $p) {
    $path = __DIR__ . '/../lib/ai/providers/' . $p . '.php';
    $assert_true("adapter file $p.php exists", is_file($path));
    if (!is_file($path)) continue;
    require_once $path;
    $chat = 'ai_provider_' . $p . '_chat';
    $list = 'ai_provider_' . $p . '_list_models';
    $assert_true("$chat() registered",       function_exists($chat));
    $assert_true("$list() registered",       function_exists($list));
}

// 5. Runtime constants
foreach (['GUA_HF_DEFAULT_MODEL', 'GUA_GEMINI_DEFAULT_MODEL', 'GUA_OPENROUTER_DEFAULT_MODEL',
          'GUA_ANTHROPIC_DEFAULT_MODEL', 'GUA_OPENAI_DEFAULT_MODEL', 'GUA_GROK_DEFAULT_MODEL'] as $c) {
    $assert_true("$c defined", defined($c));
}

// 6. ai_default_model_for() returns a non-empty model per provider
foreach ($expected_providers as $p) {
    $m = ai_default_model_for($p);
    $assert_true("default model for $p is non-empty", $m !== '');
}

// 7. Cache round-trip
$test_models = [
    ['id' => 'test-model-a', 'label' => 'Test Model A'],
    ['id' => 'test-model-b', 'label' => 'Test Model B'],
];
ai_models_cache_delete('anthropic'); // clean slate
$assert('cache_get returns null when empty', null, ai_models_cache_get('anthropic'));
ai_models_cache_put('anthropic', $test_models);
$got = ai_models_cache_get('anthropic');
$assert_true('cache_get returns row after put', is_array($got));
$assert('cache_get returns 2 models',          2,           count($got['models'] ?? []));
$assert('first model id round-tripped',        'test-model-a', $got['models'][0]['id'] ?? '');
$assert_true('cache_delete returns true',      ai_models_cache_delete('anthropic'));
$assert('cache_get returns null after delete', null, ai_models_cache_get('anthropic'));

// 8. Staleness
$assert('fresh row not stale', false, ai_models_cache_is_stale(gmdate('Y-m-d H:i:s')));
$old = gmdate('Y-m-d H:i:s', time() - (48 * 3600));
$assert('48h-old row is stale', true, ai_models_cache_is_stale($old));

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
