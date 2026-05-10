<?php
// core/lib/ai/providers/openrouter.php — OpenRouter adapter.
//
// Endpoint: https://openrouter.ai/api/v1/chat/completions
// Auth:     Authorization: Bearer <KEY>
// Body:     OpenAI-compatible chat-completions
//
// HTTP-Referer + X-Title headers are recommended (used for attribution
// on openrouter.ai's analytics dashboard) — we set them from APP_URL +
// SITE_NAME in .env.
//
// Cost: OpenRouter's response includes usage tokens but cost varies per
// model. We log tokens; cost_usd is left at 0 (admin sees authoritative
// numbers on openrouter.ai). A pricing map is left as a Phase 14 polish
// item — too many models to keep in sync here.

declare(strict_types=1);

require_once __DIR__ . '/../../config.php';

const GUA_OPENROUTER_DEFAULT_MODEL = 'anthropic/claude-3.5-haiku';
const GUA_OPENROUTER_ENDPOINT      = 'https://openrouter.ai/api/v1/chat/completions';
const GUA_OPENROUTER_TIMEOUT_SEC   = 60;

function ai_provider_openrouter_chat(string $api_key, array $messages, array $opts = []): array
{
    $model = (string)($opts['model'] ?? GUA_OPENROUTER_DEFAULT_MODEL);

    $clean = [];
    foreach ($messages as $m) {
        $role = (string)($m['role']    ?? 'user');
        if (!in_array($role, ['system', 'user', 'assistant'], true)) {
            $role = 'user';
        }
        $clean[] = [
            'role'    => $role,
            'content' => (string)($m['content'] ?? ''),
        ];
    }

    $body = [
        'model'    => $model,
        'messages' => $clean,
    ];
    if (isset($opts['temperature'])) $body['temperature'] = (float)$opts['temperature'];
    if (isset($opts['max_tokens']))  $body['max_tokens']  = (int)$opts['max_tokens'];

    $headers = [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json',
    ];
    if (GUA_APP_URL !== '')   $headers[] = 'HTTP-Referer: ' . GUA_APP_URL;
    if (GUA_SITE_NAME !== '') $headers[] = 'X-Title: '      . GUA_SITE_NAME;

    $ch = curl_init(GUA_OPENROUTER_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => GUA_OPENROUTER_TIMEOUT_SEC,
    ]);
    $raw   = curl_exec($ch);
    $errno = curl_errno($ch);
    $err   = curl_error($ch);
    $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        throw new RuntimeException("OpenRouter transport error ($errno): $err");
    }
    if ($code < 200 || $code >= 300) {
        $msg = $raw;
        $decoded = json_decode((string)$raw, true);
        if (is_array($decoded) && isset($decoded['error']['message'])) {
            $msg = $decoded['error']['message'];
        }
        throw new RuntimeException("OpenRouter HTTP $code: $msg");
    }

    $resp = json_decode((string)$raw, true);
    if (!is_array($resp)) {
        throw new RuntimeException('OpenRouter returned non-JSON body.');
    }

    $text       = (string)($resp['choices'][0]['message']['content'] ?? '');
    $tokens_in  = (int)   ($resp['usage']['prompt_tokens']     ?? 0);
    $tokens_out = (int)   ($resp['usage']['completion_tokens'] ?? 0);
    $resp_model = (string)($resp['model'] ?? $model);

    return [
        'text'       => $text,
        'model'      => $resp_model,
        'tokens_in'  => $tokens_in,
        'tokens_out' => $tokens_out,
        'cost_usd'   => 0.0,
        'raw'        => $resp,
    ];
}
