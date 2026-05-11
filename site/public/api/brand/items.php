<?php
// site/public/api/brand/items.php — admin-only CRUD for brand_items (v2 Stage 2).
//
// Form-driven (the admin pages are server-rendered, no AJAX). Action is
// taken from $_POST['action']: create | update | delete.
// Redirects back to /admin/brand.php?cat=<x>[&item=<id>] with ?saved=1 /
// ?created=1 / ?error=...

declare(strict_types=1);

require __DIR__ . '/../../../../core/lib/bootstrap.php';
require_once __DIR__ . '/../../../../core/lib/brand/items.php';

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

$cat = (string) ($_POST['cat'] ?? '');
$back = '/admin/brand.php' . ($cat !== '' ? ('?cat=' . urlencode($cat)) : '');

$token = (string) ($_POST['csrf'] ?? '');
if (!csrf_check($token)) {
    header('Location: ' . $back . (str_contains($back, '?') ? '&' : '?') . 'error=' . urlencode('Invalid CSRF token'));
    exit;
}

$action = (string) ($_POST['action'] ?? '');

try {
    switch ($action) {
        case 'create': {
            $id = brand_item_create([
                'category_id' => (int)($_POST['category_id'] ?? 0),
                'slug'        => trim((string)($_POST['slug'] ?? '')),
                'title'       => trim((string)($_POST['title'] ?? '')),
                'kind'        => (string)($_POST['kind'] ?? 'markdown'),
                'body'        => (string)($_POST['body'] ?? ''),
                'source'      => 'admin',
            ], (int)$user['id']);
            $sep = str_contains($back, '?') ? '&' : '?';
            header('Location: ' . $back . $sep . 'item=' . $id . '&created=1');
            exit;
        }

        case 'update': {
            $id = (int)($_POST['id'] ?? 0);
            brand_item_update($id, [
                'title'     => trim((string)($_POST['title'] ?? '')),
                'kind'      => (string)($_POST['kind'] ?? 'markdown'),
                'status'    => (string)($_POST['status'] ?? 'active'),
                'body'      => (string)($_POST['body'] ?? ''),
                'always_on' => isset($_POST['always_on']) && (string)$_POST['always_on'] === '1' ? 1 : 0,
                'source'    => 'admin',
            ], (int)$user['id']);
            $sep = str_contains($back, '?') ? '&' : '?';
            header('Location: ' . $back . $sep . 'item=' . $id . '&saved=1');
            exit;
        }

        case 'delete': {
            $id = (int)($_POST['id'] ?? 0);
            brand_item_delete($id);
            $sep = str_contains($back, '?') ? '&' : '?';
            header('Location: ' . $back . $sep . 'saved=1');
            exit;
        }

        default:
            $sep = str_contains($back, '?') ? '&' : '?';
            header('Location: ' . $back . $sep . 'error=' . urlencode("Unknown action '$action'"));
            exit;
    }
} catch (Throwable $e) {
    $sep = str_contains($back, '?') ? '&' : '?';
    header('Location: ' . $back . $sep . 'error=' . urlencode($e->getMessage()));
    exit;
}
