<?php
// site/pages/home.php — file-based Home page. Required from the router
// after a successful pages-table lookup for slug='home'. The router
// makes $page (the pages row) available in scope so this file can use
// page-level SEO if it wants to override the layout defaults.

declare(strict_types=1);

require __DIR__ . '/../layout.php';

layout_head($page ?? null);

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
