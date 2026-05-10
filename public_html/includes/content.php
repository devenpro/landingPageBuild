<?php
// includes/content.php — DB-backed getters for content_blocks rows.
// Section partials call c('hero.headline') (text/icon/image/video) or
// c_list('footer.legal_links') (JSON-decoded list). Phase 5 will add a
// setter behind /api/content.php. Values not found return the default
// argument so a missing seed key never fatals the page.

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function content_all(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    foreach (db()->query('SELECT key, value, type FROM content_blocks') as $row) {
        $cache[$row['key']] = ['value' => $row['value'], 'type' => $row['type']];
    }
    return $cache;
}

function c(string $key, string $default = ''): string
{
    $row = content_all()[$key] ?? null;
    return $row['value'] ?? $default;
}

function c_list(string $key, array $default = []): array
{
    $row = content_all()[$key] ?? null;
    if ($row === null) {
        return $default;
    }
    $decoded = json_decode($row['value'], true);
    return is_array($decoded) ? $decoded : $default;
}

function c_type(string $key): ?string
{
    return content_all()[$key]['type'] ?? null;
}
