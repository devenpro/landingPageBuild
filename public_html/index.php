<?php
// public_html/index.php — Phase 1 hello-world. Confirms the bootstrap chain
// (config -> .env, db -> SQLite) works end-to-end. Phase 2 replaces this with
// the real landing page rendered from content_blocks.

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$count = (int) db()->query('SELECT COUNT(*) FROM content_blocks')->fetchColumn();
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e(GUA_SITE_NAME) ?> — Phase 1</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; max-width: 40rem;
               margin: 4rem auto; padding: 0 1rem; color: #1f2937; }
        h1 { font-size: 2rem; margin-bottom: .5rem; }
        code { background: #f3f4f6; padding: .15rem .4rem; border-radius: .25rem; }
        .ok { color: #16a34a; }
    </style>
</head>
<body>
    <h1>Hello from <?= e(GUA_SITE_NAME) ?></h1>
    <p class="ok">Bootstrap OK. Environment: <code><?= e(GUA_APP_ENV) ?></code></p>
    <p>content_blocks rows: <strong><?= $count ?></strong></p>
    <p><small>Phase 1 scaffolding. The real landing page lands in Phase 2.</small></p>
</body>
</html>
