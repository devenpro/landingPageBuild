<?php
// core/lib/config.php — load .env, expose paths + secrets + env_get().
//
// PROJECT_ROOT = three dirs up from this file (core/lib/config.php → repo root).
// .env lives at repo root. Per-site DB / uploads / sections live under site/
// and data/ which are also at repo root. .env may override DB_PATH /
// SITE_PATH / CORE_PATH if you want to relocate things outside the repo.
//
// v2 Stage 1: runtime knobs (SITE_NAME, APP_URL, etc.) moved to DB-backed
// site_settings — see core/lib/settings.php and core/lib/runtime_constants.php.
// This file now only defines paths + secrets that must be available before
// the DB itself loads, plus env_get() so settings_get() can fall back to .env.

declare(strict_types=1);

if (defined('GUA_CONFIG_LOADED')) {
    return;
}
define('GUA_CONFIG_LOADED', true);

define('GUA_PROJECT_ROOT', dirname(__DIR__, 2));

/**
 * Parse .env once and cache it. Returns the raw key/value map.
 *
 * Used directly here for path/secret constants and exposed via env_get()
 * so settings_get() can use .env as a fallback layer.
 */
function _env_load(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

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

    return $cache = $env;
}

/**
 * Look up a value in .env. Returns $default when the key is missing or
 * the value is the empty string. Used by settings_get() as the .env layer.
 */
function env_get(string $key, ?string $default = null): ?string
{
    $env = _env_load();
    $val = $env[$key] ?? null;
    return ($val === null || $val === '') ? $default : $val;
}

$env = _env_load();

// Path constants — defaults are repo-relative. .env may override. These
// must be defined before db.php loads, so they stay in config.php and
// don't go through settings_get().
define('GUA_CORE_PATH', $env['CORE_PATH'] ?? GUA_PROJECT_ROOT . '/core');
define('GUA_SITE_PATH', $env['SITE_PATH'] ?? GUA_PROJECT_ROOT . '/site');
define('GUA_DATA_PATH', $env['DATA_PATH'] ?? GUA_PROJECT_ROOT . '/data');
define('GUA_DB_PATH',   $env['DB_PATH']   ?? GUA_DATA_PATH . '/content.db');

// AI master key (Phase 10) — secret, .env-only. If lost, all stored API
// keys become unreadable. By design — back it up.
define('GUA_AI_KEYS_MASTER_KEY', $env['AI_KEYS_MASTER_KEY'] ?? '');

// Core version — read from VERSION file
$ver_file = GUA_CORE_PATH . '/VERSION';
define('GUA_CORE_VERSION', is_file($ver_file) ? trim(file_get_contents($ver_file)) : 'unknown');
