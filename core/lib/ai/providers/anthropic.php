<?php
// core/lib/ai/providers/anthropic.php — Anthropic Claude adapter (v2 Stage 8).
//
// Endpoint: https://api.anthropic.com/v1/messages
// Auth:     x-api-key: <KEY> + anthropic-version: 2023-06-01
//
// Anthropic's Messages API is NOT OpenAI-compatible:
//   - System messages are hoisted out of the messages array into a
//     top-level `system` field (multiple system messages are joined
//     with two newlines, matching the Gemini adapter's pattern).
//   - max_tokens is REQUIRED on every request — Anthropic refuses
//     to default it. We use 4096 if the caller doesn't set one.
//   - Response shape: content is an array of blocks; we concatenate
//     all type='text' blocks for the .text field. usage.input_tokens
//     and usage.output_tokens are the token counts.
//
// Cost: tokens are recorded; cost_usd is logged as 0. Per-model
// pricing is published at https://anthropic.com/pricing — keeping a
// lookup table here means stale numbers within weeks. Admin reads
// authoritative spend on the Anthropic console.

declare(strict_types=1);

const GUA_ANTHROPIC_DEFAULT_MODEL_FALLBACK = 'claude-haiku-4-5-20251001';
const GUA_ANTHROPIC_ENDPOINT               = 'https://api.anthropic.com/v1/messages';
const GUA_ANTHROPIC_MODELS_ENDPOINT        = 'https://api.anthropic.com/v1/models';
const GUA_ANTHROPIC_VERSION                = '2023-06-01';
const GUA_ANTHROPIC_TIMEOUT_SEC            = 60;

function ai_provider_anthropic_chat(string $api_key, array $messages, array $opts = []): array
{
    $model = (string)($opts['model'] ?? (defined('GUA_ANTHROPIC_DEFAULT_MODEL') ? GUA_ANTHROPIC_DEFAULT_MODEL : ''));
    if ($model === '') {
        $model = GUA_ANTHROPIC_DEFAULT_MODEL_FALLBACK;
    }

    $system_text = '';
    $clean       = [];
    foreach ($messages as $m) {
        $role = (string)($m['role']    ?? 'user');
        $text = (string)($m['content'] ?? '');
        if ($role === 'system') {
            $system_text .= ($system_text === '' ? '' : "\n\n") . $text;
            continue;
        }
        if (!in_array($role, ['user', 'assistant'], true)) {
            $role = 'user';
        }
        $clean[] = ['role' => $role, 'content' => $text];
    }

    $body = [
        'model'      => $model,
        'max_tokens' => (int)($opts['max_tokens'] ?? 4096),
        'messages'   => $clean,
    ];
    if ($system_text !== '') {
        $body['system'] = $system_text;
    }
    if (isset($opts['temperature'])) {
        $body['temperature'] = (float)$opts['temperature'];
    }

    $headers = [
        'x-api-key: ' . $api_key,
        'anthropic-version: ' . GUA_ANTHROPIC_VERSION,
        'Content-Type: application/json',
    ];

    $ch = curl_init(GUA_ANTHROPIC_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => GUA_ANTHROPIC_TIMEOUT_SEC,
    ]);
    $raw   = curl_exec($ch);
    $errno = curl_errno($ch);
    $err   = curl_error($ch);
    $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        throw new RuntimeException("Anthropic transport error ($errno): $err");
    }
    if ($code < 200 || $code >= 300) {
        $msg = $raw;
        $decoded = json_decode((string)$raw, true);
        if (is_array($decoded) && isset($decoded['error']['message'])) {
            $msg = $decoded['error']['message'];
        } elseif (is_array($decoded) && isset($decoded['error']) && is_string($decoded['error'])) {
            $msg = $decoded['error'];
        }
        throw new RuntimeException("Anthropic HTTP $code: $msg");
    }

    $resp = json_decode((string)$raw, true);
    if (!is_array($resp)) {
        throw new RuntimeException('Anthropic returned non-JSON body.');
    }

    $text = '';
    foreach ($resp['content'] ?? [] as $block) {
        if (($block['type'] ?? '') === 'text') {
            $text .= (string)($block['text'] ?? '');
        }
    }

    $tokens_in  = (int)($resp['usage']['input_tokens']  ?? 0);
    $tokens_out = (int)($resp['usage']['output_tokens'] ?? 0);
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

/**
 * Fetch the list of models the given API key has access to.
 * Returns an array of normalised model rows: [{id, label, context_window?}]
 * Throws RuntimeException on transport / HTTP error.
 */
function ai_provider_anthropic_list_models(string $api_key): array
{
    $headers = [
        'x-api-key: ' . $api_key,
        'anthropic-version: ' . GUA_ANTHROPIC_VERSION,
        'Accept: application/json',
    ];

    $ch = curl_init(GUA_ANTHROPIC_MODELS_ENDPOINT . '?limit=1000');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => GUA_ANTHROPIC_TIMEOUT_SEC,
    ]);
    $raw   = curl_exec($ch);
    $errno = curl_errno($ch);
    $err   = curl_error($ch);
    $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        throw new RuntimeException("Anthropic transport error ($errno): $err");
    }
    if ($code < 200 || $code >= 300) {
        throw new RuntimeException("Anthropic models HTTP $code: " . substr((string)$raw, 0, 300));
    }
    $resp = json_decode((string)$raw, true);
    if (!is_array($resp) || !isset($resp['data']) || !is_array($resp['data'])) {
        throw new RuntimeException('Anthropic models endpoint returned unexpected shape.');
    }

    $models = [];
    foreach ($resp['data'] as $row) {
        $id = (string)($row['id'] ?? '');
        if ($id === '') continue;
        $models[] = [
            'id'      => $id,
            'label'   => (string)($row['display_name'] ?? $id),
            'created' => (string)($row['created_at'] ?? ''),
        ];
    }
    return $models;
}
