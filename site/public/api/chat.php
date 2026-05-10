<?php
// site/public/api/chat.php — public-facing chatbot endpoint (Phase 13).
//
// POST { session_id: string, messages: [{role, content}, …] }
//   → builds the system prompt from current site content
//   → applies per-IP + global daily rate limiting (NOT skipped)
//   → calls ai_chat() with the configured AI_DEFAULT_PROVIDER
//   → optionally persists the new (user, assistant) pair to
//     ai_chat_messages when AI_CHAT_PERSIST=1
//   → returns { ok, reply, session_id, usage }
//
// Differences from the admin endpoints:
// - No auth: anyone on the public site can hit this. The .env flag
//   AI_CHAT_ENABLED gates traffic so an admin who doesn't want the
//   chatbot can disable it without redeploying code.
// - No CSRF: the request comes from a same-origin fetch initiated by
//   the public page, but unauthenticated visitors don't have a session
//   token. We rely on rate limiting + the global daily token cap to
//   protect against abuse instead.
// - Conversation state lives client-side. The widget sends the full
//   transcript on each turn; we never need to look up history. When
//   persistence is on, we append rows for analytics, not for memory.

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';
require_once __DIR__ . '/../../../core/lib/ai/client.php';
require_once __DIR__ . '/../../../core/lib/ai/prompts/chat.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!GUA_AI_CHAT_ENABLED) {
    // Hide the existence of a disabled chat endpoint behind a 404
    // rather than 503 — public crawlers and pen-testing scripts get
    // the same answer they'd see for any non-existent route.
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Not found']);
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw === false ? '' : $raw, true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Body must be JSON']);
    exit;
}

$session_id = trim((string)($body['session_id'] ?? ''));
if ($session_id === '' || strlen($session_id) > 64
    || !preg_match('/^[A-Za-z0-9_-]+$/', $session_id)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid session_id']);
    exit;
}

$messages = $body['messages'] ?? null;
if (!is_array($messages) || $messages === []) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'messages must be a non-empty array']);
    exit;
}
if (count($messages) > 40) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Conversation too long; start a new chat']);
    exit;
}

// The most recent message must be from the visitor; otherwise the
// model has nothing to respond to.
$last = end($messages);
if (!is_array($last) || ($last['role'] ?? '') !== 'user') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Last message must be from the user']);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

try {
    $result = ai_chat(
        ai_default_provider(),
        chat_messages($messages),
        [
            'caller'      => 'public.chat',
            'ip'          => $ip,
            'temperature' => 0.6,
            'max_tokens'  => 600,
        ]
    );
} catch (Throwable $e) {
    // ai_chat() already logged this turn (rate-limit hit, no key on
    // file, provider error, etc.). Surface a public-safe message; the
    // exception text is fine for our 'no key on file' / 'rate limit'
    // cases since they're already user-readable.
    $msg = $e->getMessage();
    $code = (
        str_contains(strtolower($msg), 'rate limit') ||
        str_contains(strtolower($msg), 'daily ai usage')
    ) ? 429 : 502;
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

$reply = (string)($result['text'] ?? '');

// Persist the visitor message + assistant reply if AI_CHAT_PERSIST=1.
// Errors here are logged and swallowed: the visitor still gets their
// reply even if the analytics write fails.
if (GUA_AI_CHAT_PERSIST) {
    try {
        $pdo = db();
        $ins = $pdo->prepare(
            'INSERT INTO ai_chat_messages (session_id, role, content, ip_address, user_agent)
             VALUES (:s, :r, :c, :ip, :ua)'
        );
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        if (strlen($ua) > 500) $ua = substr($ua, 0, 500);

        $ins->execute([
            ':s'  => $session_id,
            ':r'  => 'user',
            ':c'  => (string)($last['content'] ?? ''),
            ':ip' => $ip,
            ':ua' => $ua,
        ]);
        $ins->execute([
            ':s'  => $session_id,
            ':r'  => 'assistant',
            ':c'  => $reply,
            ':ip' => $ip,
            ':ua' => $ua,
        ]);
    } catch (Throwable $e) {
        error_log('ai_chat_messages insert failed: ' . $e->getMessage());
    }
}

echo json_encode([
    'ok'         => true,
    'reply'      => $reply,
    'session_id' => $session_id,
    'usage'      => [
        'provider'   => ai_default_provider(),
        'model'      => $result['model']      ?? null,
        'tokens_in'  => $result['tokens_in']  ?? 0,
        'tokens_out' => $result['tokens_out'] ?? 0,
    ],
]);
