<?php
// core/lib/ai/providers/openai.php — OpenAI Chat Completions adapter (v2 Stage 8).
//
// Endpoint: https://api.openai.com/v1/chat/completions
// Auth:     Authorization: Bearer <KEY>
// Body:     standard OpenAI chat-completions (messages[], model, etc.)
//
// Models endpoint: GET https://api.openai.com/v1/models with same auth.
// Returns every model the key has access to (chat, embedding, image,
// audio); we filter list_models() to ones whose id matches the common
// chat-family patterns so the admin UI isn't flooded with embeddings.
//
// Cost: tokens are recorded; cost_usd is logged as 0. OpenAI's pricing
// shifts model-by-model; admins read authoritative spend on the
// OpenAI dashboard.

declare(strict_types=1);

const GUA_OPENAI_DEFAULT_MODEL_FALLBACK = 'gpt-4o-mini';
const GUA_OPENAI_ENDPOINT               = 'https://api.openai.com/v1/chat/completions';
const GUA_OPENAI_MODELS_ENDPOINT        = 'https://api.openai.com/v1/models';
const GUA_OPENAI_TIMEOUT_SEC            = 60;

function ai_provider_openai_chat(string $api_key, array $messages, array $opts = []): array
{
    $model = (string)($opts['model'] ?? (defined('GUA_OPENAI_DEFAULT_MODEL') ? GUA_OPENAI_DEFAULT_MODEL : ''));
    if ($model === '') {
        $model = GUA_OPENAI_DEFAULT_MODEL_FALLBACK;
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

    $ch = curl_init(GUA_OPENAI_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => GUA_OPENAI_TIMEOUT_SEC,
    ]);
    $raw   = curl_exec($ch);
    $errno = curl_errno($ch);
    $err   = curl_error($ch);
    $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        throw new RuntimeException("OpenAI transport error ($errno): $err");
    }
    if ($code < 200 || $code >= 300) {
        $msg = $raw;
        $decoded = json_decode((string)$raw, true);
        if (is_array($decoded) && isset($decoded['error']['message'])) {
            $msg = $decoded['error']['message'];
        }
        throw new RuntimeException("OpenAI HTTP $code: $msg");
    }

    $resp = json_decode((string)$raw, true);
    if (!is_array($resp)) {
        throw new RuntimeException('OpenAI returned non-JSON body.');
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

/**
 * Fetch model list. Filtered to chat-capable families
 * (gpt-*, o1-*, o3-*, o4-*, chatgpt-*) so admins aren't shown
 * embeddings/tts/image/whisper endpoints they can't use for chat.
 */
function ai_provider_openai_list_models(string $api_key): array
{
    $headers = [
        'Authorization: Bearer ' . $api_key,
        'Accept: application/json',
    ];

    $ch = curl_init(GUA_OPENAI_MODELS_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => GUA_OPENAI_TIMEOUT_SEC,
    ]);
    $raw   = curl_exec($ch);
    $errno = curl_errno($ch);
    $err   = curl_error($ch);
    $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        throw new RuntimeException("OpenAI transport error ($errno): $err");
    }
    if ($code < 200 || $code >= 300) {
        throw new RuntimeException("OpenAI models HTTP $code: " . substr((string)$raw, 0, 300));
    }
    $resp = json_decode((string)$raw, true);
    if (!is_array($resp) || !isset($resp['data']) || !is_array($resp['data'])) {
        throw new RuntimeException('OpenAI models endpoint returned unexpected shape.');
    }

    $chat_pattern = '/^(gpt-|o1|o3|o4|chatgpt-)/i';
    $models = [];
    foreach ($resp['data'] as $row) {
        $id = (string)($row['id'] ?? '');
        if ($id === '') continue;
        if (!preg_match($chat_pattern, $id)) continue;
        // Audio/realtime/tts/transcribe/image families share the gpt-/o-prefix
        // but aren't usable through chat completions — exclude them.
        if (preg_match('/-(audio|realtime|tts|transcribe|image)/i', $id)) continue;
        $models[] = [
            'id'      => $id,
            'label'   => $id,
            'created' => isset($row['created']) ? (string) date('c', (int)$row['created']) : '',
        ];
    }
    usort($models, fn($a, $b) => strcmp($b['id'], $a['id']));
    return $models;
}
