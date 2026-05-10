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
