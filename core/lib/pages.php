<?php
// core/lib/pages.php — page lookup + URL routing for the front controller.
//
// Two render paths:
//   - is_file_based=1 -> require'd from site/pages/<file_path>
//   - is_file_based=0 -> data-driven: walk sections_json, set a content
//     prefix to "page.<slug>", include each section partial in order,
//     then clear the prefix. Page-scoped content blocks (key prefixed
//     with "page.<slug>.") override the section's default keys via the
//     prefix-aware lookup in core/lib/content.php.
//
// Slug parsing: the front controller passes REQUEST_URI; we strip query
// string, leading/trailing slashes, and treat '/' as the 'home' slug.
// Multi-segment slugs are preserved (e.g. /services/seo-bangalore →
// 'services/seo-bangalore') so future programmatic SEO pages can use them.

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/content.php';
require_once __DIR__ . '/content/types.php';
require_once __DIR__ . '/content/entries.php';
require_once __DIR__ . '/taxonomy.php';

function parse_slug(string $request_uri): string
{
    $path = parse_url($request_uri, PHP_URL_PATH);
    if (!is_string($path)) {
        return 'home';
    }
    $path = trim($path, '/');
    return $path === '' ? 'home' : $path;
}

function get_page_by_slug(string $slug): ?array
{
    static $cache = [];
    if (array_key_exists($slug, $cache)) {
        return $cache[$slug];
    }
    $stmt = db()->prepare(
        "SELECT * FROM pages WHERE slug = :slug AND status = 'published' LIMIT 1"
    );
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch();
    return $cache[$slug] = ($row === false ? null : $row);
}

function route_request(?string $request_uri = null): void
{
    $uri = $request_uri ?? ($_SERVER['REQUEST_URI'] ?? '/');
    $slug = parse_slug($uri);

    // 1. Pages win on slug collision.
    $page = get_page_by_slug($slug);
    if ($page !== null) {
        render_page($page);
        return;
    }

    // 2. v2 Stage 4: try each routable content type's route_pattern.
    $match = content_resolve_route($uri);
    if ($match !== null) {
        render_content_entry($match['type'], $match['entry']);
        return;
    }

    // 3. 404
    http_response_code(404);
    $page = get_page_by_slug('404');
    if ($page === null) {
        echo '<!DOCTYPE html><meta charset="utf-8"><title>Page not found</title>';
        echo '<h1>404 — Page not found</h1>';
        echo '<p>The site has no 404 page configured. Run migrations.</p>';
        return;
    }

    render_page($page);
}

/**
 * Try to match the request URI against every routable content type's
 * route_pattern. Returns ['type' => row, 'entry' => row, 'params' => [...]]
 * or null on no match.
 *
 * Supports single-placeholder patterns ('/services/{slug}') and
 * multi-placeholder patterns ('/services/{service_slug}/{location_slug}').
 * Captured placeholders are joined with '/' to form the composite entry slug,
 * preserving the order they appear in route_pattern. For a single placeholder,
 * the composite slug is just that one captured group.
 */
function content_resolve_route(string $uri): ?array
{
    $path = parse_url($uri, PHP_URL_PATH);
    if (!is_string($path)) {
        return null;
    }
    $path = '/' . trim($path, '/');
    if ($path === '/') {
        return null;
    }

    foreach (content_types_routable() as $type) {
        $pattern = (string)($type['route_pattern'] ?? '');
        if ($pattern === '') continue;

        // Collect placeholder names in pattern order — used to compose the
        // entry slug when matching succeeds.
        preg_match_all('/\{([a-z_]+)\}/', $pattern, $pm);
        $placeholder_names = $pm[1] ?? [];
        if ($placeholder_names === []) continue;

        $regex = preg_quote('/' . trim($pattern, '/'), '#');
        $regex = preg_replace(
            '/\\\\\\{([a-z_]+)\\\\\\}/',
            '(?<$1>[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)',
            $regex
        );
        if (!preg_match('#^' . $regex . '$#', $path, $m)) {
            continue;
        }

        // Build the composite slug in pattern order
        $parts = [];
        foreach ($placeholder_names as $name) {
            if (!isset($m[$name])) {
                continue 2; // mismatched capture — try next type
            }
            $parts[] = $m[$name];
        }
        $composite_slug = implode('/', $parts);

        $entry = content_entry_by_slug((int)$type['id'], $composite_slug, 'published');
        if ($entry !== null) {
            return ['type' => $type, 'entry' => $entry, 'params' => $m];
        }
    }
    return null;
}

/**
 * Render a routable content entry. Looks up the type's detail_partial under
 * site/sections/, exposes $entry / $type / $data as globals for the partial,
 * and wraps it in the standard site layout.
 */
function render_content_entry(array $type, array $entry): void
{
    $detail = (string)($type['detail_partial'] ?? '');
    if ($detail === '' || !preg_match('/^[a-z0-9_]+$/', $detail)) {
        http_response_code(500);
        echo 'Content type misconfigured: invalid detail_partial';
        return;
    }
    $partial = GUA_SITE_PATH . '/sections/' . $detail . '.php';
    if (!is_file($partial)) {
        http_response_code(500);
        echo 'Content type misconfigured: detail_partial file not found';
        return;
    }

    // Synthesise a $page-shaped row for the layout. Layout reads slug,
    // title, seo_*, and (for sitemap/canonical) is mostly happy with these.
    $page = [
        'slug'            => 'entry/' . (string)$type['slug'] . '/' . (string)$entry['slug'],
        'title'           => (string)$entry['title'],
        'seo_title'       => (string)($entry['seo_title']       ?: $entry['title']),
        'seo_description' => (string)($entry['seo_description'] ?: ''),
        'seo_og_image'    => $entry['seo_og_image'] ?? null,
        'robots'          => $entry['robots']       ?? null,
        'is_file_based'   => 0,
        'sections_json'   => null,
        'meta_json'       => null,
    ];

    // Expose the entry and decoded data for the partial.
    $GLOBALS['gua_content_entry'] = $entry;
    $GLOBALS['gua_content_type']  = $type;
    $GLOBALS['gua_content_data']  = content_entry_data($entry);

    require GUA_SITE_PATH . '/layout.php';
    layout_head($page);
    require $partial;
    layout_foot();
}

function render_page(array $page): void
{
    if ((int)($page['is_file_based'] ?? 0) === 1) {
        render_file_based_page($page);
        return;
    }
    render_data_driven_page($page);
}

function render_file_based_page(array $page): void
{
    $file = (string) ($page['file_path'] ?? '');
    if ($file === '') {
        http_response_code(500);
        echo 'Page misconfigured: is_file_based=1 but file_path is empty';
        return;
    }

    // Path-traversal guard: realpath the requested file and confirm it
    // sits under site/pages/ before requiring it.
    $pages_dir = realpath(GUA_SITE_PATH . '/pages');
    $target    = realpath(GUA_SITE_PATH . '/pages/' . $file);
    if ($pages_dir === false || $target === false
        || !str_starts_with($target, $pages_dir . DIRECTORY_SEPARATOR)) {
        http_response_code(500);
        echo 'Page misconfigured: file_path resolves outside site/pages/';
        return;
    }

    // Make $page available to the included file (used by layout for SEO).
    require $target;
}

function render_data_driven_page(array $page): void
{
    $slug = (string) ($page['slug'] ?? '');
    $sections = pages_decode_sections($page['sections_json'] ?? null);

    if ($sections === null) {
        http_response_code(500);
        echo 'Page misconfigured: sections_json is not a valid JSON array';
        return;
    }

    // Validate every requested section corresponds to a real partial
    // before we start emitting any output. Cleaner failure mode if the
    // page references a deleted section.
    $sections_dir = realpath(GUA_SITE_PATH . '/sections');
    $files = [];
    foreach ($sections as $name) {
        if (!is_string($name) || !preg_match('/^[a-z0-9_]+$/', $name)) {
            http_response_code(500);
            echo 'Page misconfigured: invalid section name in sections_json';
            return;
        }
        $candidate = realpath(GUA_SITE_PATH . '/sections/' . $name . '.php');
        if ($sections_dir === false || $candidate === false
            || !str_starts_with($candidate, $sections_dir . DIRECTORY_SEPARATOR)) {
            http_response_code(500);
            echo 'Page misconfigured: section "' . htmlspecialchars($name) . '" not found';
            return;
        }
        $files[] = ['slug' => $name, 'path' => $candidate];
    }

    // Set the page-scoped content prefix so c('hero.headline') tries
    // 'page.<slug>.hero.headline' first, falls back to 'hero.headline'.
    content_set_prefix('page.' . $slug);

    // v2 Stage 9: when an admin is logged in, wrap each section include in a
    // thin marker div carrying the slug + index + page id, so editor.js can
    // identify section boundaries for drag-to-reorder and add/delete UX.
    // Public visitors never see these wrappers (no extra DOM cost).
    $is_editor = function_exists('auth_current_user') && auth_current_user() !== null;
    $page_id   = (int)($page['id'] ?? 0);

    require GUA_SITE_PATH . '/layout.php';
    layout_head($page);
    foreach ($files as $i => $f) {
        if ($is_editor) {
            echo '<div class="gua-section" data-gua-section="' . htmlspecialchars($f['slug'], ENT_QUOTES) . '"'
               . ' data-gua-section-index="' . $i . '"'
               . ' data-gua-page-id="' . $page_id . '">';
        }
        require $f['path'];
        if ($is_editor) {
            echo '</div>';
        }
    }
    layout_foot();

    // Clear the prefix so any subsequent content lookups in the same
    // process (unlikely in a normal request, but defensive) revert to
    // the global namespace.
    content_set_prefix('');
}

function pages_decode_sections($json): ?array
{
    if ($json === null || $json === '') {
        return [];
    }
    if (!is_string($json)) {
        return null;
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return null;
    }
    // Allow either a flat array of strings ["hero","features"] or an
    // array of objects [{"section":"hero"}, ...] — we normalize to strings.
    $out = [];
    foreach ($decoded as $entry) {
        if (is_string($entry)) {
            $out[] = $entry;
        } elseif (is_array($entry) && isset($entry['section']) && is_string($entry['section'])) {
            $out[] = $entry['section'];
        } else {
            return null; // malformed
        }
    }
    return $out;
}
