<?php
// site/public/api/blocks.php — admin-only CRUD for content blocks and their fields (v2 Stage 3).
//
// Form-driven (the admin page is server-rendered). $_POST['action'] selects:
//   create        — create a new content_blocks row
//   save_meta     — update slug/name/description/category on an existing block
//   delete        — remove a block and its fields (FK cascade)
//   add_field     — append a new field to a block
//   save_fields   — bulk-update field values for a block
//
// Redirects back to /admin/blocks.php?block=<slug> with ?saved=1, ?created=1
// or ?error=…

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';

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

$slug = (string) ($_POST['slug'] ?? '');
$back = '/admin/blocks.php' . ($slug !== '' ? ('?block=' . urlencode($slug)) : '');

$token = (string) ($_POST['csrf'] ?? '');
if (!csrf_check($token)) {
    header('Location: ' . $back . (str_contains($back, '?') ? '&' : '?') . 'error=' . urlencode('Invalid CSRF token'));
    exit;
}

$action = (string) ($_POST['action'] ?? '');
$pdo = db();

function blocks_redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function blocks_assert_slug(string $s): void
{
    if (!preg_match('/^[a-z0-9](?:[a-z0-9_\-]*[a-z0-9])?$/', $s)) {
        throw new InvalidArgumentException("Block slug must match ^[a-z0-9](?:[a-z0-9_\-]*[a-z0-9])?$");
    }
}

$VALID_TYPES = ['text','image','video','icon','list','seo','html'];

try {
    switch ($action) {
        case 'create': {
            $new_slug = trim((string)($_POST['slug'] ?? ''));
            blocks_assert_slug($new_slug);
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '') {
                throw new InvalidArgumentException('Name is required');
            }
            $description = trim((string)($_POST['description'] ?? ''));
            $stmt = $pdo->prepare(
                "INSERT INTO content_blocks (slug, name, description, category, status, created_at, updated_at, updated_by)
                 VALUES (:s, :n, :d, 'section', 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, :u)"
            );
            $stmt->execute([':s' => $new_slug, ':n' => $name, ':d' => $description, ':u' => $user['id']]);
            blocks_redirect('/admin/blocks.php?block=' . urlencode($new_slug) . '&created=1');
        }

        case 'save_meta': {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '') {
                throw new InvalidArgumentException('Name is required');
            }
            $description = trim((string)($_POST['description'] ?? ''));
            $category = trim((string)($_POST['category'] ?? 'section'));
            if ($category === '') $category = 'section';
            $stmt = $pdo->prepare(
                'UPDATE content_blocks
                    SET name = :n, description = :d, category = :c,
                        updated_at = CURRENT_TIMESTAMP, updated_by = :u
                  WHERE id = :id'
            );
            $stmt->execute([':n' => $name, ':d' => $description, ':c' => $category, ':u' => $user['id'], ':id' => $id]);
            blocks_redirect($back . (str_contains($back, '?') ? '&' : '?') . 'saved=1');
        }

        case 'delete': {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare('DELETE FROM content_blocks WHERE id = :id')->execute([':id' => $id]);
            blocks_redirect('/admin/blocks.php?saved=1');
        }

        case 'add_field': {
            $block_id = (int)($_POST['block_id'] ?? 0);
            $field_key = trim((string)($_POST['field_key'] ?? ''));
            $type = (string)($_POST['type'] ?? 'text');
            if ($field_key === '' || !preg_match('/^[a-z0-9](?:[a-z0-9_\.\-]*[a-z0-9])?$/', $field_key)) {
                throw new InvalidArgumentException('field_key must be lowercase letters/digits with hyphens/underscores/dots, no leading/trailing punctuation');
            }
            if (!in_array($type, $VALID_TYPES, true)) {
                $type = 'text';
            }
            $stmt = $pdo->prepare(
                'INSERT INTO content_block_fields (block_id, field_key, value, type, position, updated_at, updated_by)
                 VALUES (:b, :k, "", :t, (
                    SELECT COALESCE(MAX(position), -1) + 1 FROM content_block_fields WHERE block_id = :b
                 ), CURRENT_TIMESTAMP, :u)'
            );
            $stmt->execute([':b' => $block_id, ':k' => $field_key, ':t' => $type, ':u' => $user['id']]);
            blocks_redirect($back . (str_contains($back, '?') ? '&' : '?') . 'saved=1');
        }

        case 'save_fields': {
            $block_id = (int)($_POST['block_id'] ?? 0);
            $fields = $_POST['fields'] ?? [];
            if (!is_array($fields)) {
                $fields = [];
            }
            $pdo->beginTransaction();
            $upsert = $pdo->prepare(
                'INSERT INTO content_block_fields (block_id, field_key, value, type, updated_at, updated_by)
                 VALUES (:b, :k, :v, :t, CURRENT_TIMESTAMP, :u)
                 ON CONFLICT(block_id, field_key) DO UPDATE SET
                    value = excluded.value,
                    type = excluded.type,
                    updated_at = CURRENT_TIMESTAMP,
                    updated_by = excluded.updated_by'
            );
            foreach ($fields as $key => $data) {
                if (!is_string($key) || !is_array($data)) continue;
                $value = isset($data['value']) ? (string)$data['value'] : '';
                $type  = isset($data['type'])  ? (string)$data['type']  : 'text';
                if (!in_array($type, $VALID_TYPES, true)) $type = 'text';
                $upsert->execute([':b' => $block_id, ':k' => $key, ':v' => $value, ':t' => $type, ':u' => $user['id']]);
            }
            $pdo->commit();
            blocks_redirect($back . (str_contains($back, '?') ? '&' : '?') . 'saved=1');
        }

        default:
            blocks_redirect($back . (str_contains($back, '?') ? '&' : '?') . 'error=' . urlencode("Unknown action '$action'"));
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    blocks_redirect($back . (str_contains($back, '?') ? '&' : '?') . 'error=' . urlencode($e->getMessage()));
}
