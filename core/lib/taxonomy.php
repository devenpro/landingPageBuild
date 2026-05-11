<?php
// core/lib/taxonomy.php — hierarchical taxonomies + content-entry term assignments (v2 Stage 5).
//
// Two builtin taxonomies seed at migrate time: 'locations' and
// 'service_categories'. Both are hierarchical (parent_id self-reference).
// Stage 5 admin UI populates the trees; later stages read them for
// internal routing, breadcrumbs, and SEO topical authority.

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/** All taxonomies, ordered by sort_order. */
function taxonomies_all(): array
{
    try {
        return db()->query('SELECT * FROM taxonomies ORDER BY sort_order, name')->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function taxonomy_by_slug(string $slug): ?array
{
    try {
        $stmt = db()->prepare('SELECT * FROM taxonomies WHERE slug = :s LIMIT 1');
        $stmt->execute([':s' => $slug]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    } catch (Throwable $e) {
        return null;
    }
}

function taxonomy_by_id(int $id): ?array
{
    try {
        $stmt = db()->prepare('SELECT * FROM taxonomies WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    } catch (Throwable $e) {
        return null;
    }
}

/** Direct children of a parent. parent_id=null returns top-level terms. */
function taxonomy_terms(string $taxonomy_slug, ?int $parent_id = null): array
{
    $tax = taxonomy_by_slug($taxonomy_slug);
    if ($tax === null) return [];
    if ($parent_id === null) {
        $stmt = db()->prepare(
            'SELECT * FROM taxonomy_terms WHERE taxonomy_id = :t AND parent_id IS NULL
              ORDER BY position, name'
        );
        $stmt->execute([':t' => $tax['id']]);
    } else {
        $stmt = db()->prepare(
            'SELECT * FROM taxonomy_terms WHERE taxonomy_id = :t AND parent_id = :p
              ORDER BY position, name'
        );
        $stmt->execute([':t' => $tax['id'], ':p' => $parent_id]);
    }
    return $stmt->fetchAll();
}

/** Every term in a taxonomy (flat). Pair with taxonomy_tree() for the nested view. */
function taxonomy_terms_all(int $taxonomy_id): array
{
    $stmt = db()->prepare(
        'SELECT * FROM taxonomy_terms WHERE taxonomy_id = :t ORDER BY position, name'
    );
    $stmt->execute([':t' => $taxonomy_id]);
    return $stmt->fetchAll();
}

function taxonomy_term_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM taxonomy_terms WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function taxonomy_term_by_slug(int $taxonomy_id, string $slug): ?array
{
    $stmt = db()->prepare(
        'SELECT * FROM taxonomy_terms WHERE taxonomy_id = :t AND slug = :s LIMIT 1'
    );
    $stmt->execute([':t' => $taxonomy_id, ':s' => $slug]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * Nested tree representation of a taxonomy. Each node has children[].
 * Useful for the admin UI's indented list.
 */
function taxonomy_tree(int $taxonomy_id): array
{
    $rows = taxonomy_terms_all($taxonomy_id);
    $by_id = [];
    foreach ($rows as $r) {
        $r['children'] = [];
        $by_id[(int)$r['id']] = $r;
    }
    $roots = [];
    foreach ($by_id as $id => $node) {
        $pid = $node['parent_id'] !== null ? (int)$node['parent_id'] : null;
        if ($pid !== null && isset($by_id[$pid])) {
            $by_id[$pid]['children'][] = &$by_id[$id];
        } else {
            $roots[] = &$by_id[$id];
        }
    }
    return $roots;
}

/**
 * Ordered list of ancestors for a term (root → … → term). Used for
 * breadcrumb rendering on location pages.
 */
function term_path(int $term_id): array
{
    $path = [];
    $current_id = $term_id;
    $safety = 0;
    while ($current_id !== null) {
        if (++$safety > 50) break; // cycle guard
        $row = taxonomy_term_by_id($current_id);
        if ($row === null) break;
        array_unshift($path, $row);
        $current_id = $row['parent_id'] !== null ? (int)$row['parent_id'] : null;
    }
    return $path;
}

/* ---------- Term CRUD ---------- */

function taxonomy_assert_slug(string $slug): void
{
    if (!preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $slug)) {
        throw new InvalidArgumentException("taxonomy: slug '$slug' must match ^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$");
    }
}

function term_create(int $taxonomy_id, string $slug, string $name, ?int $parent_id = null, string $description = ''): int
{
    taxonomy_assert_slug($slug);
    if (taxonomy_by_id($taxonomy_id) === null) {
        throw new InvalidArgumentException("term_create: taxonomy_id $taxonomy_id not found");
    }
    if ($parent_id !== null && taxonomy_term_by_id($parent_id) === null) {
        throw new InvalidArgumentException("term_create: parent_id $parent_id not found");
    }
    $stmt = db()->prepare(
        'INSERT INTO taxonomy_terms (taxonomy_id, parent_id, slug, name, description, position, created_at)
         VALUES (:t, :p, :s, :n, :d, (SELECT COALESCE(MAX(position), -1) + 1 FROM taxonomy_terms WHERE taxonomy_id = :t AND ((:p IS NULL AND parent_id IS NULL) OR parent_id = :p)), CURRENT_TIMESTAMP)'
    );
    $stmt->execute([':t' => $taxonomy_id, ':p' => $parent_id, ':s' => $slug, ':n' => $name, ':d' => $description]);
    return (int) db()->lastInsertId();
}

function term_update(int $term_id, array $fields): void
{
    $existing = taxonomy_term_by_id($term_id);
    if ($existing === null) {
        throw new InvalidArgumentException("term_update: id $term_id not found");
    }
    $slug = array_key_exists('slug', $fields) ? (string)$fields['slug'] : (string)$existing['slug'];
    taxonomy_assert_slug($slug);
    $name = array_key_exists('name', $fields) ? (string)$fields['name'] : (string)$existing['name'];
    $description = array_key_exists('description', $fields) ? (string)$fields['description'] : (string)($existing['description'] ?? '');
    $parent_id = array_key_exists('parent_id', $fields)
        ? ($fields['parent_id'] === null || $fields['parent_id'] === '' ? null : (int)$fields['parent_id'])
        : ($existing['parent_id'] !== null ? (int)$existing['parent_id'] : null);
    // Reject cycles: parent_id can't be self or a descendant
    if ($parent_id !== null && $parent_id === $term_id) {
        throw new InvalidArgumentException('term_update: cannot parent a term to itself');
    }
    if ($parent_id !== null && term_is_descendant($parent_id, $term_id)) {
        throw new InvalidArgumentException('term_update: would create a cycle (parent is a descendant)');
    }
    $stmt = db()->prepare(
        'UPDATE taxonomy_terms
            SET slug = :s, name = :n, description = :d, parent_id = :p
          WHERE id = :id'
    );
    $stmt->execute([':s' => $slug, ':n' => $name, ':d' => $description, ':p' => $parent_id, ':id' => $term_id]);
}

function term_delete(int $term_id): void
{
    db()->prepare('DELETE FROM taxonomy_terms WHERE id = :id')->execute([':id' => $term_id]);
}

/** True if $candidate_id is a descendant of $ancestor_id in the same taxonomy. */
function term_is_descendant(int $candidate_id, int $ancestor_id): bool
{
    $current = taxonomy_term_by_id($candidate_id);
    $safety = 0;
    while ($current !== null && $current['parent_id'] !== null) {
        if (++$safety > 50) return false;
        if ((int)$current['parent_id'] === $ancestor_id) return true;
        $current = taxonomy_term_by_id((int)$current['parent_id']);
    }
    return false;
}

/* ---------- Entry assignments ---------- */

function entry_terms(int $entry_id, ?string $taxonomy_slug = null): array
{
    if ($taxonomy_slug === null) {
        $stmt = db()->prepare(
            'SELECT t.*, tax.slug AS taxonomy_slug
               FROM entry_taxonomy_terms ett
               JOIN taxonomy_terms t   ON t.id = ett.term_id
               JOIN taxonomies tax     ON tax.id = t.taxonomy_id
              WHERE ett.entry_id = :e
              ORDER BY tax.sort_order, t.name'
        );
        $stmt->execute([':e' => $entry_id]);
    } else {
        $stmt = db()->prepare(
            'SELECT t.*, tax.slug AS taxonomy_slug
               FROM entry_taxonomy_terms ett
               JOIN taxonomy_terms t   ON t.id = ett.term_id
               JOIN taxonomies tax     ON tax.id = t.taxonomy_id
              WHERE ett.entry_id = :e AND tax.slug = :ts
              ORDER BY t.name'
        );
        $stmt->execute([':e' => $entry_id, ':ts' => $taxonomy_slug]);
    }
    return $stmt->fetchAll();
}

/**
 * Replace the entry's assigned terms with $term_ids. Use null for $term_ids
 * to clear assignments. $type_id is denormalised into entry_taxonomy_terms
 * for fast filtering.
 */
function entry_terms_set(int $entry_id, int $type_id, array $term_ids): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM entry_taxonomy_terms WHERE entry_id = :e')->execute([':e' => $entry_id]);
        if ($term_ids !== []) {
            $ins = $pdo->prepare(
                'INSERT OR IGNORE INTO entry_taxonomy_terms (entry_id, term_id, type_id) VALUES (:e, :t, :tp)'
            );
            foreach ($term_ids as $tid) {
                $tid = (int)$tid;
                if ($tid > 0) {
                    $ins->execute([':e' => $entry_id, ':t' => $tid, ':tp' => $type_id]);
                }
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Find content entries linked to a term (denormalised type filter for speed).
 * Used by Location Services routing and future block list partials.
 */
function entries_for_term(int $term_id, ?int $type_id = null): array
{
    if ($type_id === null) {
        $stmt = db()->prepare(
            'SELECT ce.* FROM entry_taxonomy_terms ett
               JOIN content_entries ce ON ce.id = ett.entry_id
              WHERE ett.term_id = :t'
        );
        $stmt->execute([':t' => $term_id]);
    } else {
        $stmt = db()->prepare(
            'SELECT ce.* FROM entry_taxonomy_terms ett
               JOIN content_entries ce ON ce.id = ett.entry_id
              WHERE ett.term_id = :t AND ett.type_id = :tp'
        );
        $stmt->execute([':t' => $term_id, ':tp' => $type_id]);
    }
    return $stmt->fetchAll();
}

/**
 * Which taxonomies apply to a content type? A taxonomy applies if its
 * applies_to_type_ids_json is NULL (= all routable) or its JSON array
 * includes the type's id.
 */
function taxonomies_for_type(int $type_id): array
{
    $all = taxonomies_all();
    $out = [];
    foreach ($all as $tax) {
        $raw = $tax['applies_to_type_ids_json'];
        if ($raw === null || $raw === '') {
            $out[] = $tax;
            continue;
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && in_array($type_id, array_map('intval', $decoded), true)) {
            $out[] = $tax;
        }
    }
    return $out;
}
