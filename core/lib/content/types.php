<?php
// core/lib/content/types.php — content type registry (v2 Stage 4).
//
// Read-only API for now; types are seeded by migrations. Stage 4 ships
// 3 built-in types; Stage 5 adds Location Services (depends on taxonomy).
// Custom type creation via the admin UI is deferred to v2.1+.

declare(strict_types=1);

require_once __DIR__ . '/../db.php';

function content_types_all(?string $status = 'active'): array
{
    try {
        if ($status === null) {
            $stmt = db()->query('SELECT * FROM content_types ORDER BY sort_order, name');
        } else {
            $stmt = db()->prepare('SELECT * FROM content_types WHERE status = :s ORDER BY sort_order, name');
            $stmt->execute([':s' => $status]);
        }
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function content_type_by_slug(string $slug): ?array
{
    try {
        $stmt = db()->prepare('SELECT * FROM content_types WHERE slug = :s LIMIT 1');
        $stmt->execute([':s' => $slug]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    } catch (Throwable $e) {
        return null;
    }
}

function content_type_by_id(int $id): ?array
{
    try {
        $stmt = db()->prepare('SELECT * FROM content_types WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    } catch (Throwable $e) {
        return null;
    }
}

function content_types_routable(): array
{
    try {
        return db()->query(
            "SELECT * FROM content_types
              WHERE is_routable = 1 AND status = 'active'
              ORDER BY sort_order"
        )->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Decode schema_json into the field-list shape used by the admin form renderer.
 * Returns an array of {key, label, type, required, default} dicts; empty on
 * decode failure.
 */
function content_type_fields(array $type): array
{
    $raw = $type['schema_json'] ?? '';
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || !isset($decoded['fields']) || !is_array($decoded['fields'])) {
        return [];
    }
    $out = [];
    foreach ($decoded['fields'] as $f) {
        if (!is_array($f) || empty($f['key'])) continue;
        $out[] = [
            'key'      => (string)$f['key'],
            'label'    => (string)($f['label'] ?? $f['key']),
            'type'     => (string)($f['type']  ?? 'text'),
            'required' => !empty($f['required']),
            'default'  => $f['default'] ?? '',
        ];
    }
    return $out;
}
