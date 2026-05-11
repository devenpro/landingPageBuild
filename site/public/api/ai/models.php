<?php
// site/public/api/ai/models.php — admin-only model-discovery endpoint (v2 Stage 8).
//
// GET  ?provider=<slug>             → cached models for that provider (auto-fetch on miss)
// GET  ?provider=<slug>&refresh=1   → force a live fetch, replace cache
// POST { provider, refresh? }       → same, JSON body
//
// Auth:  must be a logged-in admin.
// CSRF:  required on POST (X-CSRF-Token header or `csrf` body field).
// Body:  list of normalised model rows + cache metadata.
//
// Response shape on success:
//   { ok: true, provider, source: 'cache'|'live', fetched_at, count, models: [{id,label,...}] }
// On failure: { ok: false, error: <message> } with appropriate HTTP code.

declare(strict_types=1);

require __DIR__ . '/../../../../core/lib/bootstrap.php';
require_once __DIR__ . '/../../../../core/lib/ai/models.php';

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

$body = $_GET;
if ($method === 'POST') {
    $body  = $_POST;
    $ctype = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ctype, 'application/json') !== false) {
        $raw     = file_get_contents('php://input');
        $decoded = json_decode($raw === false ? '' : $raw, true);
        if (is_array($decoded)) {
            $body = $decoded;
        }
    }
    $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $body['csrf'] ?? $_POST['csrf'] ?? '');
    if (!csrf_check($token)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

$provider = trim((string)($body['provider'] ?? ''));
$refresh  = !empty($body['refresh']) && $body['refresh'] !== '0' && $body['refresh'] !== 'false';

if ($provider === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Missing provider']);
    exit;
}

try {
    $result = ai_models_for_provider($provider, $refresh);
    echo json_encode([
        'ok'         => true,
        'provider'   => $provider,
        'source'     => $result['source'],
        'fetched_at' => $result['fetched_at'],
        'count'      => count($result['models']),
        'models'     => $result['models'],
    ]);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
