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
    $page = get_page_by_slug($slug);

    if ($page === null) {
        http_response_code(404);
        $page = get_page_by_slug('404');
        if ($page === null) {
            echo '<!DOCTYPE html><meta charset="utf-8"><title>Page not found</title>';
            echo '<h1>404 — Page not found</h1>';
            echo '<p>The site has no 404 page configured. Run migrations.</p>';
            return;
        }
    }

    render_page($page);
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
        $files[] = $candidate;
    }

    // Set the page-scoped content prefix so c('hero.headline') tries
    // 'page.<slug>.hero.headline' first, falls back to 'hero.headline'.
    content_set_prefix('page.' . $slug);

    require GUA_SITE_PATH . '/layout.php';
    layout_head($page);
    foreach ($files as $f) {
        require $f;
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
