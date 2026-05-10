<?php
// core/lib/pages.php — page lookup + URL routing for the front controller.
//
// Phase 4 ships file-based rendering (page row points at a PHP file in
// site/pages/). Data-driven pages (sections_json) get a placeholder
// render that explains where to find them; the actual data-driven render
// path lands in Phase 8 alongside admin page CRUD.
//
// Slug parsing: the front controller passes REQUEST_URI; we strip query
// string, leading/trailing slashes, and treat '/' as the 'home' slug.
// Multi-segment slugs are preserved (e.g. /services/seo-bangalore →
// 'services/seo-bangalore') so future programmatic pages can use them.

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

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
            // Last-resort fallback if even 404 isn't seeded — should never happen
            // in production but worth handling for first-boot or broken DBs.
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
        $file = $page['file_path'] ?? '';
        if ($file === '') {
            http_response_code(500);
            echo 'Page misconfigured: is_file_based=1 but file_path is empty';
            return;
        }

        // Path-traversal guard: realpath the requested file and confirm it
        // sits under site/pages/ before requiring it.
        $pages_dir = realpath(GUA_SITE_PATH . '/pages');
        $target = realpath(GUA_SITE_PATH . '/pages/' . $file);
        if ($pages_dir === false || $target === false ||
            !str_starts_with($target, $pages_dir . DIRECTORY_SEPARATOR)) {
            http_response_code(500);
            echo 'Page misconfigured: file_path resolves outside site/pages/';
            return;
        }

        // Make $page available to the included file (used by layout for SEO).
        require $target;
        return;
    }

    // Data-driven render path — Phase 8 implements this. For now, a clear
    // placeholder so admin-created data-driven pages don't 500 in dev.
    require __DIR__ . '/pages_data_driven_placeholder.php';
}
