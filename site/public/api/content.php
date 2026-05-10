<?php
// site/public/api/content.php — admin-only PATCH endpoint for content_blocks.
//
// Accepts:
//   - JSON body: {"key":"hero.headline","value":"new value"}
//   - JSON body: {"changes":[{"key":"...","value":"..."}, …]} for batch
//   - form-urlencoded equivalents (csrf=&key=&value=)
//
// Auth: must be logged in admin (auth_current_user())
// CSRF: X-CSRF-Token header OR csrf form field
// Method: PATCH or POST (Apache shared hosts sometimes drop PATCH; POST is the safe twin)
//
// Returns JSON {ok:true, applied:N, updated_at:'...'} or
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

$changes = [];
if (isset($body['key'])) {
    $changes[] = ['key' => (string) $body['key'], 'value' => (string) ($body['value'] ?? '')];
} elseif (isset($body['changes']) && is_array($body['changes'])) {
    foreach ($body['changes'] as $c) {
        if (is_array($c) && isset($c['key'])) {
            $changes[] = ['key' => (string) $c['key'], 'value' => (string) ($c['value'] ?? '')];
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
    $upd = $pdo->prepare(
        'UPDATE content_blocks
         SET value = :v, updated_at = CURRENT_TIMESTAMP, updated_by = :u
         WHERE key = :k'
    );
    $applied = 0;
    $missing = [];
    foreach ($changes as $c) {
        $upd->execute([':v' => $c['value'], ':u' => $user['id'], ':k' => $c['key']]);
        if ($upd->rowCount() > 0) {
            $applied++;
        } else {
            $missing[] = $c['key'];
        }
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB error: ' . $e->getMessage()]);
    exit;
}

// Read updated_at for the (first) updated key so the UI can refresh it
$updated_at = null;
if ($applied > 0) {
    $stmt = $pdo->prepare('SELECT updated_at FROM content_blocks WHERE key = :k LIMIT 1');
    $stmt->execute([':k' => $changes[0]['key']]);
    $updated_at = $stmt->fetchColumn() ?: null;
}

$response = ['ok' => true, 'applied' => $applied, 'updated_at' => $updated_at];
if ($missing !== []) {
    $response['missing_keys'] = $missing;
}
echo json_encode($response);
