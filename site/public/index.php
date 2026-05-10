<?php
// site/public/index.php — front controller (Phase 3 placeholder).
//
// Phase 3 ships a hardcoded section list — same render path as Phase 2.
// Phase 4 introduces the pages table + URL-based routing; this file
// becomes a router that looks up the page by slug and either includes
// a file-based page from site/pages/ or renders a data-driven page
// from sections_json.

declare(strict_types=1);

require __DIR__ . '/../../core/lib/bootstrap.php';
require __DIR__ . '/../layout.php';

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
    require __DIR__ . '/../sections/' . $name . '.php';
}

layout_foot();
