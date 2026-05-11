<?php
// core/lib/ai/providers/grok.php — xAI Grok adapter (v2 Stage 8).
//
// Endpoint: https://api.x.ai/v1/chat/completions
// Auth:     Authorization: Bearer <KEY>
// Body:     OpenAI-compatible chat-completions (xAI matches OpenAI's
//           wire format intentionally, including streaming + tool-use).
//
// Models endpoint: GET https://api.x.ai/v1/language-models with the
// same bearer token. Returns rows that include id, input/output
// modalities, and pricing; we surface id + label so the admin UI can
// list available models without per-model network calls.
//
// Cost: tokens are recorded; cost_usd is logged as 0. xAI's pricing
// is published per model on the console — keeping a lookup here would
// rot quickly.

declare(strict_types=1);

const GUA_GROK_DEFAULT_MODEL_FALLBACK = 'grok-2-latest';
const GUA_GROK_ENDPOINT               = 'https://api.x.ai/v1/chat/completions';
const GUA_GROK_MODELS_ENDPOINT        = 'https://api.x.ai/v1/language-models';
const GUA_GROK_TIMEOUT_SEC            = 60;

function ai_provider_grok_chat(string $api_key, array $messages, array $opts = []): array
{
    $model = (string)($opts['model'] ?? (defined('GUA_GROK_DEFAULT_MODEL') ? GUA_GROK_DEFAULT_MODEL : ''));
    if ($model === '') {
        $model = GUA_GROK_DEFAULT_MODEL_FALLBACK;
    }

    $clean = [];
    foreach ($messages as $m) {
        $role = (string)($m['role'] ?? 'user');
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

    $ch = curl_init(GUA_GROK_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => GUA_GROK_TIMEOUT_SEC,
    ]);
    $raw   = curl_exec($ch);
    $errno = curl_errno($ch);
    $err   = curl_error($ch);
    $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        throw new RuntimeException("Grok transport error ($errno): $err");
    }
    if ($code < 200 || $code >= 300) {
        $msg = $raw;
        $decoded = json_decode((string)$raw, true);
        if (is_array($decoded) && isset($decoded['error']['message'])) {
            $msg = $decoded['error']['message'];
        } elseif (is_array($decoded) && isset($decoded['error']) && is_string($decoded['error'])) {
            $msg = $decoded['error'];
        }
        throw new RuntimeException("Grok HTTP $code: $msg");
    }

    $resp = json_decode((string)$raw, true);
    if (!is_array($resp)) {
        throw new RuntimeException('Grok returned non-JSON body.');
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

function ai_provider_grok_list_models(string $api_key): array
{
    $headers = [
        'Authorization: Bearer ' . $api_key,
        'Accept: application/json',
    ];

    $ch = curl_init(GUA_GROK_MODELS_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => GUA_GROK_TIMEOUT_SEC,
    ]);
    $raw   = curl_exec($ch);
    $errno = curl_errno($ch);
    $err   = curl_error($ch);
    $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        throw new RuntimeException("Grok transport error ($errno): $err");
    }
    if ($code < 200 || $code >= 300) {
        throw new RuntimeException("Grok models HTTP $code: " . substr((string)$raw, 0, 300));
    }
    $resp = json_decode((string)$raw, true);
    if (!is_array($resp)) {
        throw new RuntimeException('Grok models endpoint returned unexpected shape.');
    }
    // xAI's response shape: { models: [{id, ...}] } for language-models,
    // but they also have a /v1/models endpoint with { data: [...] } OpenAI-style.
    // Accept either.
    $rows = $resp['models'] ?? $resp['data'] ?? [];
    if (!is_array($rows)) {
        throw new RuntimeException('Grok models endpoint returned unexpected shape.');
    }

    $models = [];
    foreach ($rows as $row) {
        $id = (string)($row['id'] ?? '');
        if ($id === '') continue;
        $models[] = [
            'id'      => $id,
            'label'   => $id,
            'created' => isset($row['created']) ? (string) date('c', (int)$row['created']) : '',
        ];
    }
    usort($models, fn($a, $b) => strcmp($b['id'], $a['id']));
    return $models;
}
