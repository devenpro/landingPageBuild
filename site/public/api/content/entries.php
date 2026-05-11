<?php
// site/public/api/content/entries.php — admin-only CRUD for content_entries (v2 Stage 4).
//
// Form-driven (the Content Types hub is server-rendered). $_POST['action']:
//   create — create a new entry for the given type
//   update — save title / slug / status / data / SEO fields
//   delete — drop the entry
//
// Redirects back to /admin/content-types.php?type=<slug>[&entry=<id>] with
// ?saved=1 / ?created=1 / ?error=...

declare(strict_types=1);

require __DIR__ . '/../../../../core/lib/bootstrap.php';
require_once __DIR__ . '/../../../../core/lib/content/types.php';
require_once __DIR__ . '/../../../../core/lib/content/entries.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo 'Method not allowed';
    exit;
}

$user = auth_current_user();
if ($user === null) {
    http_response_code(401);
    echo 'Not authenticated';
    exit;
}

$type_slug = (string)($_POST['type'] ?? '');
$back = '/admin/content-types.php' . ($type_slug !== '' ? ('?type=' . urlencode($type_slug)) : '');

$token = (string)($_POST['csrf'] ?? '');
if (!csrf_check($token)) {
    header('Location: ' . $back . (str_contains($back, '?') ? '&' : '?') . 'error=' . urlencode('Invalid CSRF token'));
    exit;
}

$action = (string)($_POST['action'] ?? '');

function entries_redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

try {
    switch ($action) {
        case 'create': {
            $type_id = (int)($_POST['type_id'] ?? 0);
            if ($type_id <= 0) {
                $t = content_type_by_slug($type_slug);
                if ($t === null) {
                    throw new InvalidArgumentException("Unknown type '$type_slug'");
                }
                $type_id = (int)$t['id'];
            }
            $id = content_entry_create([
                'type_id' => $type_id,
                'slug'    => trim((string)($_POST['slug']  ?? '')),
                'title'   => trim((string)($_POST['title'] ?? '')),
                'data'    => [],
                'status'  => 'draft',
            ], (int)$user['id']);
            $sep = str_contains($back, '?') ? '&' : '?';
            entries_redirect($back . $sep . 'entry=' . $id . '&created=1');
        }

        case 'update': {
            $id = (int)($_POST['id'] ?? 0);
            $data_post = $_POST['data'] ?? [];
            if (!is_array($data_post)) $data_post = [];
            // Coerce all values to strings so JSON shape is predictable
            $data = [];
            foreach ($data_post as $k => $v) {
                $data[(string)$k] = is_array($v) ? $v : (string)$v;
            }
            $updates = [
                'title'  => trim((string)($_POST['title']  ?? '')),
                'slug'   => trim((string)($_POST['slug']   ?? '')),
                'status' => (string)($_POST['status']      ?? 'published'),
                'data'   => $data,
            ];
            // Only touch optional fields when the form actually submitted them.
            // Otherwise the trigger-set default (e.g. robots='noindex,nofollow'
            // on Ad Landing Pages) would be clobbered on every save.
            foreach (['seo_title', 'seo_description', 'seo_og_image', 'robots'] as $opt) {
                if (array_key_exists($opt, $_POST)) {
                    $val = trim((string)$_POST[$opt]);
                    $updates[$opt] = $val === '' ? null : $val;
                }
            }
            content_entry_update($id, $updates, (int)$user['id']);
            $sep = str_contains($back, '?') ? '&' : '?';
            entries_redirect($back . $sep . 'entry=' . $id . '&saved=1');
        }

        case 'delete': {
            $id = (int)($_POST['id'] ?? 0);
            content_entry_delete($id);
            $sep = str_contains($back, '?') ? '&' : '?';
            entries_redirect($back . $sep . 'saved=1');
        }

        default:
            $sep = str_contains($back, '?') ? '&' : '?';
            entries_redirect($back . $sep . 'error=' . urlencode("Unknown action '$action'"));
    }
} catch (Throwable $e) {
    $sep = str_contains($back, '?') ? '&' : '?';
    entries_redirect($back . $sep . 'error=' . urlencode($e->getMessage()));
}
