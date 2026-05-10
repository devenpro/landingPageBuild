<?php
// core/lib/content.php — DB-backed getters for content_blocks rows.
//
// Section partials call c('hero.headline') (text/icon/image/video) or
// c_list('footer.legal_links') (JSON-decoded list).
//
// Page scoping (Phase 8): when the data-driven renderer sets
// content_set_prefix('page.<slug>'), every c()/c_list() lookup first
// tries the prefixed key (e.g. 'page.services-seo.hero.headline') and
// falls back to the bare key (e.g. 'hero.headline') if the prefixed
// row doesn't exist. File-based pages don't set a prefix, so they
// behave exactly as before. Phase 9's inline editor will add a way to
// edit either scope from the public page.
//
// Values not found return the default argument so a missing seed key
// never fatals the page.

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

/**
 * Set the active content key prefix. Call before rendering a page's
 * sections; clear (with empty string) afterwards. The data-driven
 * renderer in core/lib/pages.php manages this — partials don't need to
 * touch it.
 */
function content_set_prefix(string $prefix): void
{
    $GLOBALS['gua_content_prefix'] = $prefix;
}

function content_get_prefix(): string
{
    return (string) ($GLOBALS['gua_content_prefix'] ?? '');
}

/**
 * Resolve a key, honouring the active prefix:
 *   - If prefix set AND "<prefix>.<key>" exists -> return that row
 *   - Else if "<key>" exists                    -> return that row
 *   - Else                                       -> null
 */
function content_lookup(string $key): ?array
{
    $all = content_all();
    $prefix = content_get_prefix();
    if ($prefix !== '' && isset($all[$prefix . '.' . $key])) {
        return $all[$prefix . '.' . $key];
    }
    return $all[$key] ?? null;
}

function c(string $key, string $default = ''): string
{
    $row = content_lookup($key);
    return $row['value'] ?? $default;
}

function c_list(string $key, array $default = []): array
{
    $row = content_lookup($key);
    if ($row === null) {
        return $default;
    }
    $decoded = json_decode($row['value'], true);
    return is_array($decoded) ? $decoded : $default;
}

function c_type(string $key): ?string
{
    $row = content_lookup($key);
    return $row['type'] ?? null;
}
