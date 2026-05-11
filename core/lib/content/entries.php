<?php
// core/lib/content/entries.php — CRUD + lookups for content_entries (v2 Stage 4).
//
// data_json holds type-specific fields (see content_type_fields()). All
// writes serialise it; all reads decode it via content_entry_data().

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/types.php';

const CONTENT_ENTRY_STATUSES = ['draft', 'published', 'archived'];

/**
 * List entries for a type. status=null returns every status.
 */
function content_entries_for_type(int $type_id, ?string $status = 'published', int $limit = 1000): array
{
    try {
        if ($status === null) {
            $stmt = db()->prepare(
                'SELECT * FROM content_entries
                  WHERE type_id = :tid
                  ORDER BY position, updated_at DESC
                  LIMIT :lim'
            );
            $stmt->bindValue(':tid', $type_id, PDO::PARAM_INT);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        } else {
            $stmt = db()->prepare(
                'SELECT * FROM content_entries
                  WHERE type_id = :tid AND status = :st
                  ORDER BY position, updated_at DESC
                  LIMIT :lim'
            );
            $stmt->bindValue(':tid', $type_id, PDO::PARAM_INT);
            $stmt->bindValue(':st', $status);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function content_entry_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM content_entries WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function content_entry_by_slug(int $type_id, string $slug, ?string $status = 'published'): ?array
{
    if ($status === null) {
        $stmt = db()->prepare(
            'SELECT * FROM content_entries WHERE type_id = :tid AND slug = :s LIMIT 1'
        );
        $stmt->execute([':tid' => $type_id, ':s' => $slug]);
    } else {
        $stmt = db()->prepare(
            'SELECT * FROM content_entries
              WHERE type_id = :tid AND slug = :s AND status = :st
              LIMIT 1'
        );
        $stmt->execute([':tid' => $type_id, ':s' => $slug, ':st' => $status]);
    }
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * Decode data_json from an entry row. Returns an associative array; empty
 * on missing / invalid data.
 */
function content_entry_data(array $entry): array
{
    $raw = $entry['data_json'] ?? '';
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function content_entry_create(array $fields, ?int $user_id = null): int
{
    $type_id = (int)($fields['type_id'] ?? 0);
    if ($type_id <= 0 || content_type_by_id($type_id) === null) {
        throw new InvalidArgumentException("content_entry: invalid type_id $type_id");
    }
    $title = trim((string)($fields['title'] ?? ''));
    if ($title === '') {
        throw new InvalidArgumentException('content_entry: title is required');
    }
    $slug = $fields['slug'] ?? null;
    if ($slug !== null && $slug !== '') {
        $slug = (string)$slug;
        // v2 Stage 5: routable types with multi-placeholder patterns (e.g.
        // location_services at /services/{service_slug}/{location_slug}) use
        // composite slugs separated by '/'. Each segment must match the
        // single-slug rule individually.
        if (!preg_match('#^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(/[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)*$#', $slug)) {
            throw new InvalidArgumentException("content_entry: invalid slug '$slug'");
        }
    } else {
        $slug = null;
    }
    $status = (string)($fields['status'] ?? 'published');
    if (!in_array($status, CONTENT_ENTRY_STATUSES, true)) {
        throw new InvalidArgumentException("content_entry: invalid status '$status'");
    }

    $data = $fields['data'] ?? [];
    if (!is_array($data)) $data = [];

    $stmt = db()->prepare(
        'INSERT INTO content_entries
            (type_id, slug, title, data_json, seo_title, seo_description, seo_og_image,
             robots, status, position, created_at, updated_at, updated_by)
         VALUES
            (:tid, :s, :t, :d, :st, :sd, :soi, :rb, :status, :pos,
             CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, :u)'
    );
    $stmt->execute([
        ':tid'    => $type_id,
        ':s'      => $slug,
        ':t'      => $title,
        ':d'      => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ':st'     => $fields['seo_title']        ?? null,
        ':sd'     => $fields['seo_description']  ?? null,
        ':soi'    => $fields['seo_og_image']     ?? null,
        ':rb'     => $fields['robots']           ?? null,
        ':status' => $status,
        ':pos'    => (int)($fields['position']   ?? 0),
        ':u'      => $user_id,
    ]);
    return (int) db()->lastInsertId();
}

function content_entry_update(int $id, array $fields, ?int $user_id = null): void
{
    $existing = content_entry_by_id($id);
    if ($existing === null) {
        throw new InvalidArgumentException("content_entry: id $id not found");
    }

    $title = array_key_exists('title', $fields)
        ? trim((string)$fields['title']) : (string)$existing['title'];
    if ($title === '') {
        throw new InvalidArgumentException('content_entry: title is required');
    }

    $slug = array_key_exists('slug', $fields) ? $fields['slug'] : $existing['slug'];
    if ($slug !== null && $slug !== '') {
        $slug = (string)$slug;
        // v2 Stage 5: routable types with multi-placeholder patterns (e.g.
        // location_services at /services/{service_slug}/{location_slug}) use
        // composite slugs separated by '/'. Each segment must match the
        // single-slug rule individually.
        if (!preg_match('#^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(/[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)*$#', $slug)) {
            throw new InvalidArgumentException("content_entry: invalid slug '$slug'");
        }
    } else {
        $slug = null;
    }

    $status = array_key_exists('status', $fields)
        ? (string)$fields['status'] : (string)$existing['status'];
    if (!in_array($status, CONTENT_ENTRY_STATUSES, true)) {
        throw new InvalidArgumentException("content_entry: invalid status '$status'");
    }

    $data = array_key_exists('data', $fields)
        ? (is_array($fields['data']) ? $fields['data'] : [])
        : content_entry_data($existing);

    $stmt = db()->prepare(
        'UPDATE content_entries
            SET slug = :s, title = :t, data_json = :d,
                seo_title = :st, seo_description = :sd, seo_og_image = :soi,
                robots = :rb, status = :status, position = :pos,
                updated_at = CURRENT_TIMESTAMP, updated_by = :u
          WHERE id = :id'
    );
    $stmt->execute([
        ':s'      => $slug,
        ':t'      => $title,
        ':d'      => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ':st'     => array_key_exists('seo_title', $fields)       ? $fields['seo_title']       : $existing['seo_title'],
        ':sd'     => array_key_exists('seo_description', $fields) ? $fields['seo_description'] : $existing['seo_description'],
        ':soi'    => array_key_exists('seo_og_image', $fields)    ? $fields['seo_og_image']    : $existing['seo_og_image'],
        ':rb'     => array_key_exists('robots', $fields)          ? $fields['robots']          : $existing['robots'],
        ':status' => $status,
        ':pos'    => array_key_exists('position', $fields) ? (int)$fields['position'] : (int)$existing['position'],
        ':u'      => $user_id,
        ':id'     => $id,
    ]);
}

function content_entry_delete(int $id): void
{
    db()->prepare('DELETE FROM content_entries WHERE id = :id')->execute([':id' => $id]);
}
