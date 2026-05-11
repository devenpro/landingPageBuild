<?php
// site/public/api/sections.php — admin-only sections palette + page reorder (v2 Stage 9).
//
// GET                                  → list all available section partials with category
// POST { action: 'reorder', page_id, sections: [...] }
//                                      → replace pages.sections_json with new order
// POST { action: 'add', page_id, section, after_index? }
//                                      → insert a section at given position (default: end)
// POST { action: 'delete', page_id, index }
//                                      → remove the section at given index
//
// Auth:  must be a logged-in admin.
// CSRF:  required on POST (X-CSRF-Token header or `csrf` body field).
// Body:  JSON only (matches /api/content.php convention).
//
// The discovery list (`GET`) is built by scanning site/sections/*.php and
// classifying each partial as 'layout' (navbar/footer), 'content_type'
// (the *_detail partials reserved for routable content entries), or
// 'general' (the everyday building blocks). The admin can add a section
// from any category but the inline-editor palette filters to 'general'
// + 'layout' by default so detail partials don't leak into ordinary pages.

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    http_response_code(405);
    header('Allow: GET, POST');
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

if (auth_current_user() === null) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

if ($method === 'GET') {
    echo json_encode(['ok' => true, 'sections' => sections_available()]);
    exit;
}

// POST path
$body = $_POST;
$ctype = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($ctype, 'application/json') !== false) {
    $raw     = file_get_contents('php://input');
    $decoded = json_decode($raw === false ? '' : $raw, true);
    if (is_array($decoded)) $body = $decoded;
}
$token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $body['csrf'] ?? $_POST['csrf'] ?? '');
if (!csrf_check($token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$action  = (string)($body['action']  ?? '');
$page_id = (int)   ($body['page_id'] ?? 0);
if ($page_id <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Missing page_id']);
    exit;
}

try {
    $page = page_load_for_edit($page_id);

    if ($action === 'reorder') {
        $sections = $body['sections'] ?? null;
        if (!is_array($sections)) {
            throw new InvalidArgumentException('sections must be an array');
        }
        $new = sections_validate_list($sections);
        page_save_sections($page_id, $new);
        echo json_encode(['ok' => true, 'sections' => $new]);
        exit;
    }

    if ($action === 'add') {
        $section = trim((string)($body['section'] ?? ''));
        sections_validate_one($section);
        $current = page_decode_sections($page);
        $insert_at = isset($body['after_index']) ? (int)$body['after_index'] + 1 : count($current);
        if ($insert_at < 0) $insert_at = 0;
        if ($insert_at > count($current)) $insert_at = count($current);
        array_splice($current, $insert_at, 0, [$section]);
        page_save_sections($page_id, $current);
        echo json_encode(['ok' => true, 'sections' => $current, 'inserted_at' => $insert_at]);
        exit;
    }

    if ($action === 'delete') {
        $index = (int)($body['index'] ?? -1);
        $current = page_decode_sections($page);
        if ($index < 0 || $index >= count($current)) {
            throw new InvalidArgumentException('index out of range');
        }
        array_splice($current, $index, 1);
        page_save_sections($page_id, $current);
        echo json_encode(['ok' => true, 'sections' => $current]);
        exit;
    }

    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Unknown action']);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
} catch (RuntimeException $e) {
    http_response_code(409);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

// ---------- helpers ----------------------------------------------------------

function sections_available(): array
{
    $dir = realpath(GUA_SITE_PATH . '/sections');
    if ($dir === false) return [];

    // Categorise partials. Layout = chrome (navbar/footer); content_type =
    // routable-entry detail partials (Stage 4) that shouldn't be added to
    // ordinary pages; general = everyday building blocks.
    $layout       = ['navbar', 'footer'];
    $content_type = ['service_detail', 'ad_lp_detail', 'location_service_detail'];

    $out = [];
    foreach (glob($dir . '/*.php') as $file) {
        $slug = basename($file, '.php');
        $cat  = in_array($slug, $layout, true)       ? 'layout'
              : (in_array($slug, $content_type, true) ? 'content_type' : 'general');
        $out[] = [
            'slug'     => $slug,
            'label'    => ucwords(str_replace('_', ' ', $slug)),
            'category' => $cat,
        ];
    }
    usort($out, function ($a, $b) {
        // general first, then layout, then content_type; alpha within each
        $rank = ['general' => 0, 'layout' => 1, 'content_type' => 2];
        $r = $rank[$a['category']] - $rank[$b['category']];
        return $r !== 0 ? $r : strcmp($a['slug'], $b['slug']);
    });
    return $out;
}

function sections_validate_one(string $slug): void
{
    if (!preg_match('/^[a-z0-9_]+$/', $slug)) {
        throw new InvalidArgumentException("Invalid section name: '$slug'");
    }
    $dir = realpath(GUA_SITE_PATH . '/sections');
    $f   = realpath(GUA_SITE_PATH . '/sections/' . $slug . '.php');
    if ($dir === false || $f === false || !str_starts_with($f, $dir . DIRECTORY_SEPARATOR)) {
        throw new InvalidArgumentException("Section '$slug' does not exist at site/sections/");
    }
}

function sections_validate_list(array $sections): array
{
    $out = [];
    foreach ($sections as $s) {
        if (!is_string($s)) {
            throw new InvalidArgumentException('Every section must be a string slug');
        }
        sections_validate_one($s);
        $out[] = $s;
    }
    return $out;
}

function page_load_for_edit(int $page_id): array
{
    $stmt = db()->prepare('SELECT id, slug, is_file_based, sections_json FROM pages WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $page_id]);
    $page = $stmt->fetch();
    if ($page === false) {
        throw new RuntimeException("No page with id $page_id");
    }
    if ((int)$page['is_file_based'] === 1) {
        throw new RuntimeException('Cannot reorder sections on a file-based page; edit the PHP file directly.');
    }
    return $page;
}

function page_decode_sections(array $page): array
{
    $decoded = json_decode((string)($page['sections_json'] ?? ''), true);
    if (!is_array($decoded)) return [];
    $out = [];
    foreach ($decoded as $entry) {
        if (is_string($entry)) $out[] = $entry;
        elseif (is_array($entry) && isset($entry['section']) && is_string($entry['section'])) {
            $out[] = $entry['section'];
        }
    }
    return $out;
}

function page_save_sections(int $page_id, array $sections): void
{
    $json = json_encode(array_values($sections), JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('Failed to encode sections as JSON');
    }
    $stmt = db()->prepare(
        "UPDATE pages
            SET sections_json = :json,
                updated_at    = strftime('%Y-%m-%d %H:%M:%S','now')
          WHERE id = :id"
    );
    $stmt->execute([':json' => $json, ':id' => $page_id]);
}
