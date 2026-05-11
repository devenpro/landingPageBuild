<?php
// site/public/api/taxonomies.php — admin-only term CRUD (v2 Stage 5).
//
// POST actions: add, update, delete.
// Redirects back to /admin/taxonomies.php?tax=<slug> with ?saved=1 / ?error=...

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';
require_once __DIR__ . '/../../../core/lib/taxonomy.php';

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

$tax_slug = (string)($_POST['tax'] ?? '');
$back = '/admin/taxonomies.php' . ($tax_slug !== '' ? ('?tax=' . urlencode($tax_slug)) : '');

$token = (string)($_POST['csrf'] ?? '');
if (!csrf_check($token)) {
    header('Location: ' . $back . (str_contains($back, '?') ? '&' : '?') . 'error=' . urlencode('Invalid CSRF token'));
    exit;
}

$action = (string)($_POST['action'] ?? '');
$sep = str_contains($back, '?') ? '&' : '?';

try {
    $tax = taxonomy_by_slug($tax_slug);
    if ($tax === null) {
        throw new InvalidArgumentException("Unknown taxonomy '$tax_slug'");
    }

    switch ($action) {
        case 'add': {
            $name      = trim((string)($_POST['name'] ?? ''));
            $slug      = trim((string)($_POST['slug'] ?? ''));
            $parent_id = $_POST['parent_id'] ?? null;
            $parent_id = ($parent_id === null || $parent_id === '') ? null : (int)$parent_id;
            $description = trim((string)($_POST['description'] ?? ''));
            term_create((int)$tax['id'], $slug, $name, $parent_id, $description);
            header('Location: ' . $back . $sep . 'saved=1');
            exit;
        }

        case 'update': {
            $id = (int)($_POST['id'] ?? 0);
            $fields = [
                'name' => trim((string)($_POST['name'] ?? '')),
                'slug' => trim((string)($_POST['slug'] ?? '')),
            ];
            if (array_key_exists('parent_id', $_POST)) {
                $p = $_POST['parent_id'];
                $fields['parent_id'] = ($p === '' || $p === null) ? null : (int)$p;
            }
            if (array_key_exists('description', $_POST)) {
                $fields['description'] = trim((string)$_POST['description']);
            }
            term_update($id, $fields);
            header('Location: ' . $back . $sep . 'saved=1');
            exit;
        }

        case 'delete': {
            $id = (int)($_POST['id'] ?? 0);
            term_delete($id);
            header('Location: ' . $back . $sep . 'saved=1');
            exit;
        }

        default:
            header('Location: ' . $back . $sep . 'error=' . urlencode("Unknown action '$action'"));
            exit;
    }
} catch (Throwable $e) {
    header('Location: ' . $back . $sep . 'error=' . urlencode($e->getMessage()));
    exit;
}
