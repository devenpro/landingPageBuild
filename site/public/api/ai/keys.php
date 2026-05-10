<?php
// site/public/api/ai/keys.php — admin-only CRUD for ai_provider_keys.
//
// POST   { provider, label?, api_key }   → store a new key (returns {ok, id})
// DELETE { id }                          → remove a key (returns {ok})
//
// Auth:  must be a logged-in admin.
// CSRF:  X-CSRF-Token header or `csrf` form field.
// Body:  JSON or form-urlencoded (matches /api/content.php convention).
//
// Plaintext API keys arrive over HTTPS in the request body; they're
// passed straight to ai_keys_store() (which encrypts with libsodium)
// and never logged or echoed back.

declare(strict_types=1);

require __DIR__ . '/../../../../core/lib/bootstrap.php';
require_once __DIR__ . '/../../../../core/lib/ai/keys.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST' && $method !== 'DELETE') {
    http_response_code(405);
    header('Allow: POST, DELETE');
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

if (auth_current_user() === null) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

$body = $_POST;
$ctype = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($ctype, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw === false ? '' : $raw, true);
    if (is_array($decoded)) {
        $body = $decoded;
    }
} elseif ($method === 'DELETE' && empty($body)) {
    // PHP doesn't auto-populate $_POST for DELETE bodies; parse manually.
    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
        parse_str($raw, $body);
    }
}

$token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $body['csrf'] ?? $_POST['csrf'] ?? '');
if (!csrf_check($token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

try {
    if ($method === 'POST') {
        $provider = trim((string)($body['provider'] ?? ''));
        $label    = isset($body['label']) ? trim((string)$body['label']) : null;
        if ($label === '') $label = null;
        $api_key  = (string)($body['api_key'] ?? '');

        if ($api_key === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Missing api_key']);
            exit;
        }

        $id = ai_keys_store($provider, $label, $api_key);
        sodium_memzero($api_key);
        echo json_encode(['ok' => true, 'id' => $id]);
        exit;
    }

    // DELETE
    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Missing id']);
        exit;
    }
    $removed = ai_keys_delete($id);
    if (!$removed) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'No key with that id']);
        exit;
    }
    echo json_encode(['ok' => true]);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
