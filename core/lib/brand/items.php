<?php
// core/lib/brand/items.php — CRUD for brand_items (v2 Stage 2).
//
// All writes recompute body_hash, write a brand_item_history row, and call
// brand_sync_item() so the disk mirror in .brand/ stays in step with the DB.
// Path safety + hashing helpers live in core/lib/brand/sync.php.

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/categories.php';
require_once __DIR__ . '/sync.php';

const BRAND_VALID_KINDS    = ['markdown', 'facts', 'links', 'refs'];
const BRAND_VALID_STATUSES = ['active', 'draft', 'archived'];
const BRAND_VALID_SOURCES  = ['admin', 'ai', 'imported', 'bootstrap', 'disk'];

function brand_items_by_category(int $category_id, ?string $status = 'active'): array
{
    if ($status === null) {
        $stmt = db()->prepare(
            'SELECT * FROM brand_items WHERE category_id = :c ORDER BY title'
        );
        $stmt->execute([':c' => $category_id]);
    } else {
        $stmt = db()->prepare(
            'SELECT * FROM brand_items WHERE category_id = :c AND status = :s ORDER BY title'
        );
        $stmt->execute([':c' => $category_id, ':s' => $status]);
    }
    return $stmt->fetchAll();
}

function brand_item_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM brand_items WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function brand_item_by_slug(string $category_slug, string $item_slug): ?array
{
    $stmt = db()->prepare(
        'SELECT i.* FROM brand_items i
           JOIN brand_categories c ON c.id = i.category_id
          WHERE c.slug = :cs AND i.slug = :is
          LIMIT 1'
    );
    $stmt->execute([':cs' => $category_slug, ':is' => $item_slug]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * Create a brand item. Returns the new row id.
 * Validates slug format and resolves on (category_id, slug) collision.
 */
function brand_item_create(array $fields, ?int $user_id = null): int
{
    $category_id = (int)$fields['category_id'];
    $slug        = (string)($fields['slug'] ?? '');
    $title       = trim((string)($fields['title'] ?? ''));
    $kind        = (string)($fields['kind'] ?? 'markdown');
    $body        = (string)($fields['body'] ?? '');
    $status      = (string)($fields['status'] ?? 'active');
    $source      = (string)($fields['source'] ?? 'admin');
    $source_meta = $fields['source_meta'] ?? null;
    $always_on   = !empty($fields['always_on']) ? 1 : 0;
    $ai_reviewed = array_key_exists('ai_reviewed', $fields)
                 ? (int)(bool)$fields['ai_reviewed']
                 : ($source === 'ai' ? 0 : 1);

    brand_assert_slug($slug);
    if ($title === '') {
        throw new InvalidArgumentException('brand_item: title is required');
    }
    if (!in_array($kind, BRAND_VALID_KINDS, true)) {
        throw new InvalidArgumentException("brand_item: invalid kind '$kind'");
    }
    if (!in_array($status, BRAND_VALID_STATUSES, true)) {
        throw new InvalidArgumentException("brand_item: invalid status '$status'");
    }
    if (!in_array($source, BRAND_VALID_SOURCES, true)) {
        throw new InvalidArgumentException("brand_item: invalid source '$source'");
    }
    if (brand_category_by_id($category_id) === null) {
        throw new InvalidArgumentException("brand_item: category id $category_id not found");
    }

    $body_hash = brand_hash($body);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO brand_items
                (category_id, slug, title, kind, body, body_hash, status,
                 source, source_meta, ai_reviewed, always_on, version,
                 created_at, updated_at, updated_by)
             VALUES
                (:c, :s, :t, :k, :b, :h, :st, :src, :sm, :ar, :ao, 1,
                 CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, :u)'
        );
        $stmt->execute([
            ':c'   => $category_id,
            ':s'   => $slug,
            ':t'   => $title,
            ':k'   => $kind,
            ':b'   => $body,
            ':h'   => $body_hash,
            ':st'  => $status,
            ':src' => $source,
            ':sm'  => is_array($source_meta) ? json_encode($source_meta) : $source_meta,
            ':ar'  => $ai_reviewed,
            ':ao'  => $always_on,
            ':u'   => $user_id,
        ]);
        $id = (int)$pdo->lastInsertId();
        brand_item_history_snapshot($id, 1, $title, $kind, $body, $source, $source_meta, $user_id);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    brand_sync_item($id);
    brand_sync_index();
    return $id;
}

/**
 * Update a brand item. Pass only the fields to change. Bumps version,
 * recomputes body_hash if body changed, writes a history row, syncs to disk.
 * Returns the new version number.
 */
function brand_item_update(int $id, array $fields, ?int $user_id = null): int
{
    $existing = brand_item_by_id($id);
    if ($existing === null) {
        throw new InvalidArgumentException("brand_item: id $id not found");
    }

    $title = array_key_exists('title', $fields) ? trim((string)$fields['title']) : $existing['title'];
    $kind  = array_key_exists('kind',  $fields) ? (string)$fields['kind']        : $existing['kind'];
    $body  = array_key_exists('body',  $fields) ? (string)$fields['body']        : $existing['body'];
    $status = array_key_exists('status', $fields) ? (string)$fields['status']    : $existing['status'];
    $source = array_key_exists('source', $fields) ? (string)$fields['source']    : $existing['source'];
    $source_meta = array_key_exists('source_meta', $fields) ? $fields['source_meta'] : $existing['source_meta'];
    $always_on = array_key_exists('always_on', $fields) ? (int)(bool)$fields['always_on'] : (int)$existing['always_on'];
    // When the admin saves any change, mark ai_reviewed=1 (the review gate is satisfied)
    $ai_reviewed = array_key_exists('ai_reviewed', $fields)
                 ? (int)(bool)$fields['ai_reviewed']
                 : 1;

    if ($title === '') {
        throw new InvalidArgumentException('brand_item: title is required');
    }
    if (!in_array($kind, BRAND_VALID_KINDS, true)) {
        throw new InvalidArgumentException("brand_item: invalid kind '$kind'");
    }
    if (!in_array($status, BRAND_VALID_STATUSES, true)) {
        throw new InvalidArgumentException("brand_item: invalid status '$status'");
    }
    if (!in_array($source, BRAND_VALID_SOURCES, true)) {
        throw new InvalidArgumentException("brand_item: invalid source '$source'");
    }

    $body_hash = brand_hash($body);
    $version = (int)$existing['version'] + 1;

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'UPDATE brand_items
                SET title = :t, kind = :k, body = :b, body_hash = :h,
                    status = :st, source = :src, source_meta = :sm,
                    ai_reviewed = :ar, always_on = :ao, version = :v,
                    updated_at = CURRENT_TIMESTAMP, updated_by = :u
              WHERE id = :id'
        );
        $stmt->execute([
            ':t'   => $title,
            ':k'   => $kind,
            ':b'   => $body,
            ':h'   => $body_hash,
            ':st'  => $status,
            ':src' => $source,
            ':sm'  => is_array($source_meta) ? json_encode($source_meta) : $source_meta,
            ':ar'  => $ai_reviewed,
            ':ao'  => $always_on,
            ':v'   => $version,
            ':u'   => $user_id,
            ':id'  => $id,
        ]);
        brand_item_history_snapshot($id, $version, $title, $kind, $body, $source, $source_meta, $user_id);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    brand_sync_item($id);
    brand_sync_index();
    return $version;
}

/**
 * Delete a brand item. Also removes the on-disk mirror.
 */
function brand_item_delete(int $id): void
{
    $existing = brand_item_by_id($id);
    if ($existing === null) {
        return;
    }
    brand_sync_delete($id);
    $stmt = db()->prepare('DELETE FROM brand_items WHERE id = :id');
    $stmt->execute([':id' => $id]);
    brand_sync_index();
}

function brand_item_history(int $item_id): array
{
    $stmt = db()->prepare(
        'SELECT * FROM brand_item_history WHERE item_id = :i ORDER BY version DESC'
    );
    $stmt->execute([':i' => $item_id]);
    return $stmt->fetchAll();
}

function brand_item_history_snapshot(
    int $item_id,
    int $version,
    string $title,
    string $kind,
    string $body,
    string $source,
    $source_meta,
    ?int $user_id
): void {
    $stmt = db()->prepare(
        'INSERT INTO brand_item_history
            (item_id, version, title, kind, body, source, source_meta, created_by)
         VALUES (:i, :v, :t, :k, :b, :src, :sm, :u)'
    );
    $stmt->execute([
        ':i'   => $item_id,
        ':v'   => $version,
        ':t'   => $title,
        ':k'   => $kind,
        ':b'   => $body,
        ':src' => $source,
        ':sm'  => is_array($source_meta) ? json_encode($source_meta) : $source_meta,
        ':u'   => $user_id,
    ]);
}

/**
 * Return all active items that count as "ready" for AI prompt context:
 * status='active' AND ai_reviewed=1 (so AI-generated unreviewed rows are excluded).
 */
function brand_items_for_prompt_context(array $category_slugs): array
{
    if ($category_slugs === []) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($category_slugs), '?'));
    $stmt = db()->prepare(
        "SELECT i.*, c.slug AS category_slug, c.label AS category_label
           FROM brand_items i
           JOIN brand_categories c ON c.id = i.category_id
          WHERE c.slug IN ($placeholders)
            AND i.status = 'active'
            AND i.ai_reviewed = 1
            AND length(i.body) > 0
          ORDER BY c.sort_order, i.title"
    );
    $stmt->execute($category_slugs);
    return $stmt->fetchAll();
}

/**
 * Active items with always_on=1 AND ai_reviewed=1 — used by the chatbot.
 */
function brand_items_always_on(): array
{
    return db()->query(
        "SELECT i.*, c.slug AS category_slug, c.label AS category_label
           FROM brand_items i
           JOIN brand_categories c ON c.id = i.category_id
          WHERE i.always_on = 1
            AND i.status = 'active'
            AND i.ai_reviewed = 1
            AND length(i.body) > 0
          ORDER BY c.sort_order, i.title"
    )->fetchAll();
}

/**
 * Strict slug validator: lowercase a-z 0-9 and single hyphens; no leading/
 * trailing hyphen. Throws on failure — callers should let it propagate so
 * API endpoints can return a clean 400.
 */
function brand_assert_slug(string $slug): void
{
    if (!preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $slug)) {
        throw new InvalidArgumentException(
            "brand: slug '$slug' must match ^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$ — "
            . 'lowercase letters/digits/hyphens, no leading or trailing hyphen.'
        );
    }
}
