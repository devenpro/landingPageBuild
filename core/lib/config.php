<?php
// core/lib/config.php — load .env, expose typed constants.
//
// PROJECT_ROOT = three dirs up from this file (core/lib/config.php → repo root).
// .env lives at repo root. Per-site DB / uploads / sections live under site/
// and data/ which are also at repo root. .env may override DB_PATH /
// SITE_PATH / CORE_PATH if you want to relocate things outside the repo.

declare(strict_types=1);

if (defined('GUA_CONFIG_LOADED')) {
    return;
}
define('GUA_CONFIG_LOADED', true);

define('GUA_PROJECT_ROOT', dirname(__DIR__, 2));

$env_file = GUA_PROJECT_ROOT . '/.env';
if (!is_file($env_file)) {
    throw new RuntimeException(
        'No .env file found at ' . $env_file . '. Copy .env.example to .env and configure it.'
    );
}

$env = [];
foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }
    if (!str_contains($line, '=')) {
        continue;
    }
    [$k, $v] = array_map('trim', explode('=', $line, 2));
    if ($k === '') {
        continue;
    }
    if (strlen($v) >= 2) {
        $first = $v[0];
        $last = $v[strlen($v) - 1];
        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
            $v = substr($v, 1, -1);
        }
    }
    $env[$k] = $v;
}

define('GUA_APP_ENV',                $env['APP_ENV']                ?? 'production');
define('GUA_APP_URL',                $env['APP_URL']                ?? '');
define('GUA_SITE_NAME',              $env['SITE_NAME']              ?? 'Go Ultra AI');
define('GUA_ADMIN_EMAIL',            $env['ADMIN_EMAIL']            ?? '');
define('GUA_SESSION_LIFETIME_HOURS', (int)($env['SESSION_LIFETIME_HOURS'] ?? 8));
define('GUA_WEBHOOK_URL',            $env['WEBHOOK_URL']            ?? '');
define('GUA_WEBHOOK_TIMEOUT_SECONDS',(int)($env['WEBHOOK_TIMEOUT_SECONDS'] ?? 10));
define('GUA_MAX_IMAGE_BYTES',        (int)($env['MAX_IMAGE_BYTES']  ?? 5_242_880));
define('GUA_MAX_VIDEO_BYTES',        (int)($env['MAX_VIDEO_BYTES']  ?? 52_428_800));

// Path constants — defaults are repo-relative. .env may override.
define('GUA_CORE_PATH', $env['CORE_PATH'] ?? GUA_PROJECT_ROOT . '/core');
define('GUA_SITE_PATH', $env['SITE_PATH'] ?? GUA_PROJECT_ROOT . '/site');
define('GUA_DATA_PATH', $env['DATA_PATH'] ?? GUA_PROJECT_ROOT . '/data');
define('GUA_DB_PATH',   $env['DB_PATH']   ?? GUA_DATA_PATH . '/content.db');

// AI master key (Phase 10) — read but don't error if absent (Phase 10 will require it)
define('GUA_AI_KEYS_MASTER_KEY', $env['AI_KEYS_MASTER_KEY'] ?? '');

// Default AI provider used when a caller doesn't specify one. Must be a
// value in GUA_AI_PROVIDERS (huggingface | gemini | openrouter). The
// site still works if no key is stored for this provider — calls just
// throw with a clear "no key on file" message — but admin AI tools and
// the chatbot will land here first.
define('GUA_AI_DEFAULT_PROVIDER', $env['AI_DEFAULT_PROVIDER'] ?? 'huggingface');

// Default HuggingFace model. HF Router supports many; pick one your
// account has access to. Override per-call via $opts['model'] or
// globally here. Verify with: curl -H "Authorization: Bearer $KEY"
// https://router.huggingface.co/v1/models
define('GUA_HF_DEFAULT_MODEL', $env['HF_DEFAULT_MODEL'] ?? 'meta-llama/Llama-3.3-70B-Instruct');

// Core version — read from VERSION file
$ver_file = GUA_CORE_PATH . '/VERSION';
define('GUA_CORE_VERSION', is_file($ver_file) ? trim(file_get_contents($ver_file)) : 'unknown');
