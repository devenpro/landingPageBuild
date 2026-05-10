<?php
// public_html/index.php — Phase 2 landing page. Renders 10 partials in
// order from content_blocks. Phase 5 will wrap admin-only edit-mode bar
// + editor.js around this same render path.

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/content.php';
require_once __DIR__ . '/../includes/layout.php';

layout_head();

$sections = [
    'navbar',
    'hero',
    'social_proof',
    'features',
    'how_it_works',
    'use_cases',
    'product_demo',
    'faq',
    'final_cta',
    'footer',
];

foreach ($sections as $name) {
    require __DIR__ . '/../includes/sections/' . $name . '.php';
}

layout_foot();
