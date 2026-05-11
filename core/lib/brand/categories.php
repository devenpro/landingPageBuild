<?php
// core/lib/brand/categories.php — CRUD for brand_categories (v2 Stage 2).

declare(strict_types=1);

require_once __DIR__ . '/../db.php';

/**
 * Return every category, ordered by sort_order then label.
 */
function brand_categories_all(): array
{
    return db()->query(
        'SELECT * FROM brand_categories ORDER BY sort_order, label'
    )->fetchAll();
}

function brand_category_by_slug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT * FROM brand_categories WHERE slug = :s LIMIT 1');
    $stmt->execute([':s' => $slug]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function brand_category_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM brand_categories WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * Item counts per category, keyed by category id. Active items only.
 */
function brand_category_item_counts(): array
{
    $rows = db()->query(
        "SELECT category_id, COUNT(*) AS n
           FROM brand_items
          WHERE status = 'active'
          GROUP BY category_id"
    )->fetchAll();
    $by_id = [];
    foreach ($rows as $r) {
        $by_id[(int)$r['category_id']] = (int)$r['n'];
    }
    return $by_id;
}
