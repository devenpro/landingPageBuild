<?php
// core/lib/brand/audit.php — health score for the Brand Context Library.
//
// Used in two places:
//   - /admin/dashboard.php banner: "Brand library 60% — N items missing"
//   - Stage 10 bootstrap wizard: gates progression on a minimum score
//
// Scoring rules:
//   - Required categories with zero usable items → 'missing'
//   - Items with empty body (placeholder seed rows) → 'missing'
//   - source='ai' AND ai_reviewed=0 → 'stale' (won't count toward score)
//   - Everything else with body length > 0 → 'ok'

declare(strict_types=1);

require_once __DIR__ . '/categories.php';
require_once __DIR__ . '/items.php';

function brand_audit(): array
{
    $cats = brand_categories_all();
    $items_by_cat = [];
    $rows = db()->query(
        "SELECT i.*, c.slug AS category_slug FROM brand_items i
           JOIN brand_categories c ON c.id = i.category_id
          WHERE i.status = 'active'"
    )->fetchAll();
    foreach ($rows as $r) {
        $items_by_cat[$r['category_slug']][] = $r;
    }

    $missing = [];
    $stale   = [];
    $ok      = [];

    foreach ($cats as $c) {
        $items = $items_by_cat[$c['slug']] ?? [];
        $has_filled = false;
        foreach ($items as $i) {
            if ($i['source'] === 'ai' && (int)$i['ai_reviewed'] === 0) {
                $stale[] = [
                    'category' => $c['slug'],
                    'slug'     => $i['slug'],
                    'title'    => $i['title'],
                    'reason'   => 'ai-generated, awaiting admin review',
                ];
                continue;
            }
            if (strlen((string)$i['body']) === 0) {
                $missing[] = [
                    'category' => $c['slug'],
                    'slug'     => $i['slug'],
                    'title'    => $i['title'],
                    'reason'   => 'body is empty',
                    'required' => (int)$c['required'] === 1,
                ];
                continue;
            }
            $has_filled = true;
            $ok[] = [
                'category' => $c['slug'],
                'slug'     => $i['slug'],
                'title'    => $i['title'],
                'length'   => strlen((string)$i['body']),
            ];
        }
        if (!$has_filled && (int)$c['required'] === 1) {
            $already_missing = false;
            foreach ($missing as $m) {
                if ($m['category'] === $c['slug']) {
                    $already_missing = true;
                    break;
                }
            }
            if (!$already_missing) {
                $missing[] = [
                    'category' => $c['slug'],
                    'slug'     => null,
                    'title'    => $c['label'],
                    'reason'   => 'required category has no filled items',
                    'required' => true,
                ];
            }
        }
    }

    $required_count = 0;
    $required_filled = 0;
    foreach ($cats as $c) {
        if ((int)$c['required'] !== 1) continue;
        $required_count++;
        $items = $items_by_cat[$c['slug']] ?? [];
        foreach ($items as $i) {
            if (
                strlen((string)$i['body']) > 0
                && !($i['source'] === 'ai' && (int)$i['ai_reviewed'] === 0)
            ) {
                $required_filled++;
                break;
            }
        }
    }
    $score = $required_count === 0 ? 100 : (int)round(($required_filled / $required_count) * 100);

    return [
        'score'   => $score,
        'missing' => $missing,
        'stale'   => $stale,
        'ok'      => $ok,
        'totals'  => [
            'categories'      => count($cats),
            'required'        => $required_count,
            'required_filled' => $required_filled,
            'items_ok'        => count($ok),
            'items_missing'   => count($missing),
            'items_stale'     => count($stale),
        ],
    ];
}
