<?php
// site/public/sitemap.php — dynamic XML sitemap.
//
// Reachable as /sitemap.xml in production (rewrite in
// site/public/.htaccess) and via dev-router in local. Lists every
// 'published' page from the pages table. lastmod is the row's
// updated_at if present, else created_at, normalised to W3C format.
// changefreq + priority are deliberately omitted: Google's crawler
// has been ignoring them for years and stale defaults look worse
// than absent fields.

declare(strict_types=1);

require __DIR__ . '/../../core/lib/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=600'); // crawler-friendly; 10 min is plenty

$base = rtrim(GUA_APP_URL, '/');
if ($base === '') {
    // No APP_URL configured — fall back to current host so dev still works.
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base   = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

$rows = db()->query(
    "SELECT slug, COALESCE(updated_at, created_at) AS lastmod
     FROM pages
     WHERE status = 'published'
     ORDER BY (slug = 'home') DESC, slug ASC"
)->fetchAll(PDO::FETCH_ASSOC);

echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";

foreach ($rows as $r) {
    $slug = (string) $r['slug'];
    $loc  = $base . ($slug === 'home' ? '/' : '/' . $slug);

    $lastmod = '';
    if (!empty($r['lastmod'])) {
        $ts = strtotime((string) $r['lastmod'] . ' UTC');
        if ($ts !== false) {
            $lastmod = gmdate('Y-m-d\TH:i:s\Z', $ts);
        }
    }

    echo "  <url>\n";
    echo "    <loc>", htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8'), "</loc>\n";
    if ($lastmod !== '') {
        echo "    <lastmod>$lastmod</lastmod>\n";
    }
    echo "  </url>\n";
}

echo '</urlset>', "\n";
