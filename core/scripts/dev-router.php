<?php
// core/scripts/dev-router.php — router script for PHP's built-in server.
// Mimics what mod_rewrite does in production: serve real files directly,
// route everything else through the front controller.
//
// Usage:
//   php -S 127.0.0.1:8765 -t site/public core/scripts/dev-router.php
//
// In production (Apache + cPanel), this file is unused — site/public/.htaccess
// handles the rewrite via `RewriteCond %{REQUEST_FILENAME} !-f`.

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// Phase 14: clean URL for the dynamic sitemap. Apache rewrites this via
// site/public/.htaccess; we mirror it here so `php -S` matches prod.
if ($path === '/sitemap.xml') {
    require __DIR__ . '/../../site/public/sitemap.php';
    return;
}

if (is_string($path) && $path !== '/' && is_file(__DIR__ . '/../../site/public' . $path)) {
    return false;  // Let the built-in server send the static file
}

require __DIR__ . '/../../site/public/index.php';
