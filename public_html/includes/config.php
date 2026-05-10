<?php
// includes/config.php — load .env, expose typed constants.
// Local mode auto-resolves data/ and includes/ as siblings of public_html/
// so dev works on XAMPP without per-machine path edits. Production reads
// DB_PATH and INCLUDES_PATH from .env so files can live outside the webroot.

declare(strict_types=1);

if (defined('GUA_CONFIG_LOADED')) {
    return;
}
define('GUA_CONFIG_LOADED', true);

// Project root = parent of this includes/ dir, both locally and in prod
// (in prod, includes/ is at /home/cswebserver/, so PROJECT_ROOT = /home/cswebserver/).
define('GUA_PROJECT_ROOT', dirname(__DIR__));

$env_paths = [
    GUA_PROJECT_ROOT . '/.env',
    GUA_PROJECT_ROOT . '/../.env', // fallback when includes/ is at /home/cswebserver/
];

$env_file = null;
foreach ($env_paths as $path) {
    if (is_file($path)) {
        $env_file = $path;
        break;
    }
}

if ($env_file === null) {
    throw new RuntimeException(
        'No .env file found. Copy .env.example to .env and configure it.'
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
    // Strip optional surrounding quotes on the value
    if (strlen($v) >= 2) {
        $first = $v[0];
        $last = $v[strlen($v) - 1];
        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
            $v = substr($v, 1, -1);
        }
    }
    $env[$k] = $v;
}

$app_env = $env['APP_ENV'] ?? 'production';

define('GUA_APP_ENV', $app_env);
define('GUA_APP_URL', $env['APP_URL'] ?? '');
define('GUA_SITE_NAME', $env['SITE_NAME'] ?? 'Go Ultra AI');
define('GUA_ADMIN_EMAIL', $env['ADMIN_EMAIL'] ?? '');
define('GUA_SESSION_LIFETIME_HOURS', (int)($env['SESSION_LIFETIME_HOURS'] ?? 8));
define('GUA_WEBHOOK_URL', $env['WEBHOOK_URL'] ?? '');
define('GUA_WEBHOOK_TIMEOUT_SECONDS', (int)($env['WEBHOOK_TIMEOUT_SECONDS'] ?? 10));
define('GUA_MAX_IMAGE_BYTES', (int)($env['MAX_IMAGE_BYTES'] ?? 5_242_880));
define('GUA_MAX_VIDEO_BYTES', (int)($env['MAX_VIDEO_BYTES'] ?? 52_428_800));

if ($app_env === 'local') {
    define('GUA_DB_PATH', GUA_PROJECT_ROOT . '/data/content.db');
    define('GUA_INCLUDES_PATH', GUA_PROJECT_ROOT . '/includes');
} else {
    if (empty($env['DB_PATH']) || empty($env['INCLUDES_PATH'])) {
        throw new RuntimeException(
            'Production env requires DB_PATH and INCLUDES_PATH in .env.'
        );
    }
    define('GUA_DB_PATH', $env['DB_PATH']);
    define('GUA_INCLUDES_PATH', $env['INCLUDES_PATH']);
}

define('GUA_MIGRATIONS_PATH', dirname(GUA_INCLUDES_PATH) . '/migrations');
