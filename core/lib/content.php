<?php
// core/lib/content.php — DB-backed content getters (v2 Stage 3).
//
// Resolution order for c('hero.headline') when content_set_prefix is
// "page.<slug>":
//   1. page_fields(page_id where pages.slug=<slug>, field_key='hero.headline')
//   2. content_block_fields(block where slug='hero', field_key='headline')
//   3. legacy_content(key='hero.headline')   — v1 fallback, one release only
//
// Without a prefix, step 1 is skipped. Step 3 covers any v1 rows not yet
// migrated (the 0008 migration moves everything, but the fallback is the
// safety net for rolled-back / dual-running environments).
//
// block('pricing') renders a block by slug — used by data-driven pages
// and the upcoming block-picker UI in Stage 9.

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Load every block field once per request, keyed by "<block_slug>.<field_key>"
 * so existing c() callers (c('hero.headline')) keep working unchanged.
 * Falls back to legacy_content for any keys not in the new tables.
 */
function content_all(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = [];

    // Layer 1: block fields (active blocks only)
    try {
        $rows = db()->query(
            "SELECT cb.slug AS block_slug, cbf.field_key, cbf.value, cbf.type
               FROM content_block_fields cbf
               JOIN content_blocks cb ON cb.id = cbf.block_id
              WHERE cb.status = 'active'"
        );
        foreach ($rows as $r) {
            $key = $r['block_slug'] . '.' . $r['field_key'];
            $cache[$key] = ['value' => $r['value'], 'type' => $r['type']];
        }
    } catch (Throwable $e) {
        // Tables don't exist yet (pre-migrate). Fine.
    }

    // Layer 2: legacy_content fallback (v1 rows not in new tables)
    try {
        $rows = db()->query('SELECT key, value, type FROM legacy_content');
        foreach ($rows as $r) {
            if (!isset($cache[$r['key']])) {
                $cache[$r['key']] = ['value' => $r['value'], 'type' => $r['type']];
            }
        }
    } catch (Throwable $e) {
        // legacy_content doesn't exist (post-final-cleanup). Fine.
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
 * Resolve a key, honouring the active prefix. Returns ['value' => ..., 'type' => ...]
 * or null if no match.
 *
 * - If prefix is "page.<slug>", check page_fields(page.slug=<slug>, field=$key) first
 * - Then check the global block field for $key (block.slug + field_key reconstructed)
 * - Then fall back to legacy_content
 */
function content_lookup(string $key): ?array
{
    $prefix = content_get_prefix();

    if ($prefix !== '' && str_starts_with($prefix, 'page.')) {
        $page_slug = substr($prefix, 5); // strip 'page.'
        $field = page_field_lookup($page_slug, $key);
        if ($field !== null) {
            return $field;
        }
    }

    $all = content_all();
    return $all[$key] ?? null;
}

/**
 * Look up a single page_fields row. Cached per (page_slug, field_key) for the
 * request to keep section rendering cheap.
 */
function page_field_lookup(string $page_slug, string $field_key): ?array
{
    static $cache = [];
    $ck = $page_slug . '|' . $field_key;
    if (array_key_exists($ck, $cache)) {
        return $cache[$ck];
    }

    try {
        $stmt = db()->prepare(
            'SELECT pf.value, pf.type
               FROM page_fields pf
               JOIN pages p ON p.id = pf.page_id
              WHERE p.slug = :s AND pf.field_key = :k
              LIMIT 1'
        );
        $stmt->execute([':s' => $page_slug, ':k' => $field_key]);
        $row = $stmt->fetch();
    } catch (Throwable $e) {
        $row = false;
    }
    return $cache[$ck] = ($row !== false ? ['value' => $row['value'], 'type' => $row['type']] : null);
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

/**
 * Look up a block definition by slug.
 */
function block_get(string $slug): ?array
{
    try {
        $stmt = db()->prepare(
            "SELECT * FROM content_blocks WHERE slug = :s AND status = 'active' LIMIT 1"
        );
        $stmt->execute([':s' => $slug]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Render a block by slug. Looks for site/sections/<slug>.php and includes
 * it (which uses c() to render its content). Returns the rendered HTML or
 * '' if the partial doesn't exist.
 */
function block(string $slug): string
{
    $block = block_get($slug);
    if ($block === null) {
        return '';
    }
    $partial = $block['preview_partial']
        ?: (defined('GUA_SITE_PATH') ? GUA_SITE_PATH . '/sections/' . $slug . '.php' : '');
    if (!$partial || !is_file($partial)) {
        return '';
    }
    ob_start();
    include $partial;
    return (string) ob_get_clean();
}
