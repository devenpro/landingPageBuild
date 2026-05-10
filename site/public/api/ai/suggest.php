<?php
// site/public/api/ai/suggest.php — admin-only "suggest pages" endpoint.
//
// POST { brief: string, provider?: string }
//   → calls ai_chat() with the suggest_pages prompt
//   → parses the model's JSON response
//   → returns { ok: true, suggestions: [...], usage: {...} }
//
// Auth + CSRF + JSON body parsing match /api/ai/keys.php and
// /api/content.php. The endpoint does NOT write anything to pages —
// admin reviews the suggestions and creates pages explicitly via
// /api/pages.php, so a misfire here is harmless.
//
// Provider override: if `provider` is in the body and is a known
// provider, it's used instead of the global default. Otherwise
// ai_default_provider() picks the env-configured default.

declare(strict_types=1);

require __DIR__ . '/../../../../core/lib/bootstrap.php';
require_once __DIR__ . '/../../../../core/lib/ai/client.php';
require_once __DIR__ . '/../../../../core/lib/ai/prompts/suggest_pages.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
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
}

$token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $body['csrf'] ?? $_POST['csrf'] ?? '');
if (!csrf_check($token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$brief = trim((string)($body['brief'] ?? ''));
if ($brief === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Missing or empty brief']);
    exit;
}
if (strlen($brief) > 4000) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Brief too long (max 4000 chars)']);
    exit;
}

$provider = trim((string)($body['provider'] ?? ''));
if ($provider === '' || !in_array($provider, GUA_AI_PROVIDERS, true)) {
    $provider = ai_default_provider();
}

try {
    $result = ai_chat($provider, suggest_pages_messages($brief), [
        'caller'         => 'admin.suggest_pages',
        'skip_ratelimit' => true, // admin-side; we still log everything
        'temperature'    => 0.7,
    ]);
} catch (Throwable $e) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}

try {
    $parsed = ai_parse_json((string)($result['text'] ?? ''));
} catch (RuntimeException $e) {
    http_response_code(502);
    echo json_encode([
        'ok'        => false,
        'error'     => $e->getMessage(),
        'raw_text'  => $result['text'] ?? '',
        'provider'  => $provider,
        'model'     => $result['model'] ?? null,
    ]);
    exit;
}

if (!is_array($parsed) || !isset($parsed['suggestions']) || !is_array($parsed['suggestions'])) {
    http_response_code(502);
    echo json_encode([
        'ok'    => false,
        'error' => 'Model response missing "suggestions" array',
        'raw'   => $parsed,
    ]);
    exit;
}

// Light shape validation — coerce each entry to {slug, title, description, why}
$suggestions = [];
foreach ($parsed['suggestions'] as $s) {
    if (!is_array($s) || !isset($s['slug']) || !isset($s['title'])) continue;
    $slug = trim((string)$s['slug']);
    $slug = ltrim($slug, '/');
    if ($slug === '' || $slug === 'home') continue;
    $suggestions[] = [
        'slug'        => $slug,
        'title'       => (string)$s['title'],
        'description' => (string)($s['description'] ?? ''),
        'why'         => (string)($s['why'] ?? ''),
    ];
}

echo json_encode([
    'ok'          => true,
    'suggestions' => $suggestions,
    'usage' => [
        'provider'   => $provider,
        'model'      => $result['model']      ?? null,
        'tokens_in'  => $result['tokens_in']  ?? 0,
        'tokens_out' => $result['tokens_out'] ?? 0,
    ],
]);
