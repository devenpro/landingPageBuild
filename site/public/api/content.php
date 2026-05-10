<?php
// site/public/api/content.php — admin-only UPSERT endpoint for content_blocks.
//
// Accepts:
//   - JSON body: {"key":"hero.headline","value":"new value","type":"text"}
//   - JSON body: {"changes":[{"key":"...","value":"...","type":"..."}, …]} for batch
//   - form-urlencoded equivalents (csrf=&key=&value=&type=)
//
// 'type' is optional and only used when the row doesn't exist yet
// (Phase 9 inline editor uses this when an admin edits a page-scoped
// key for the first time on a data-driven page). Defaults to 'text'.
// Must be one of: text, image, video, icon, list, seo.
//
// Auth: must be logged in admin (auth_current_user())
// CSRF: X-CSRF-Token header OR csrf form field
// Method: PATCH or POST (Apache shared hosts sometimes drop PATCH; POST is the safe twin)
//
// Returns JSON {ok:true, applied:N, inserted:N, updated_at:'...'} or
// {ok:false, error:'...'} with appropriate HTTP status.

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'PATCH' && $method !== 'POST') {
    http_response_code(405);
    header('Allow: PATCH, POST');
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$user = auth_current_user();
if ($user === null) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

$token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf'] ?? '');
if (!csrf_check($token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Parse body — JSON if Content-Type indicates, else $_POST
$body = $_POST;
$ctype = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($ctype, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw === false ? '' : $raw, true);
    if (is_array($decoded)) {
        $body = $decoded;
    }
}

$VALID_TYPES = ['text', 'image', 'video', 'icon', 'list', 'seo'];

$changes = [];
$normalize = function (array $c) use ($VALID_TYPES): ?array {
    if (!isset($c['key'])) return null;
    $type = isset($c['type']) ? (string) $c['type'] : 'text';
    if (!in_array($type, $VALID_TYPES, true)) {
        $type = 'text';
    }
    return [
        'key'   => (string) $c['key'],
        'value' => (string) ($c['value'] ?? ''),
        'type'  => $type,
    ];
};

if (isset($body['key'])) {
    $c = $normalize($body);
    if ($c) $changes[] = $c;
} elseif (isset($body['changes']) && is_array($body['changes'])) {
    foreach ($body['changes'] as $entry) {
        if (is_array($entry)) {
            $c = $normalize($entry);
            if ($c) $changes[] = $c;
        }
    }
}

if ($changes === []) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No changes in payload']);
    exit;
}

$pdo = db();
$pdo->beginTransaction();
try {
    // SQLite UPSERT: INSERT and update on key conflict. Tracks whether
    // the row already existed via the changes() count returned by SQLite.
    $upsert = $pdo->prepare(
        'INSERT INTO content_blocks (key, value, type, updated_at, updated_by)
         VALUES (:k, :v, :t, CURRENT_TIMESTAMP, :u)
         ON CONFLICT(key) DO UPDATE SET
            value      = excluded.value,
            updated_at = CURRENT_TIMESTAMP,
            updated_by = excluded.updated_by'
    );

    $existed = $pdo->prepare('SELECT 1 FROM content_blocks WHERE key = :k LIMIT 1');

    $updated  = 0;
    $inserted = 0;
    foreach ($changes as $c) {
        $existed->execute([':k' => $c['key']]);
        $was_present = (bool) $existed->fetchColumn();

        $upsert->execute([
            ':k' => $c['key'],
            ':v' => $c['value'],
            ':t' => $c['type'],
            ':u' => $user['id'],
        ]);

        if ($was_present) {
            $updated++;
        } else {
            $inserted++;
        }
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB error: ' . $e->getMessage()]);
    exit;
}

// Read updated_at of the first key for the UI to refresh
$updated_at = null;
$stmt = $pdo->prepare('SELECT updated_at FROM content_blocks WHERE key = :k LIMIT 1');
$stmt->execute([':k' => $changes[0]['key']]);
$updated_at = $stmt->fetchColumn() ?: null;

echo json_encode([
    'ok'         => true,
    'applied'    => $updated + $inserted,
    'updated'    => $updated,
    'inserted'   => $inserted,
    'updated_at' => $updated_at,
]);
