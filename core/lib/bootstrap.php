<?php
// core/lib/bootstrap.php — single entry point used by site/public/index.php
// and any other consumer that needs the full core stack ready. Pulls in
// config, DB, content getters, and helpers in dependency order. Side-
// effect-free beyond constants/functions; safe to require multiple times.

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/content.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/pages.php';
require_once __DIR__ . '/webhook.php';

// Start the session before any output so Set-Cookie headers can be sent.
// CSRF tokens (Phase 5) and admin auth (Phase 6+) both depend on $_SESSION.
// CLI scripts (migrate, seed_admin) skip this — no session needed.
if (PHP_SAPI !== 'cli') {
    csrf_session_start();
}
