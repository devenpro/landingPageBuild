<?php
// core/lib/ai/providers/huggingface.php — HuggingFace Inference Providers adapter.
//
// Endpoint: https://router.huggingface.co/v1/chat/completions
// Auth:     Authorization: Bearer hf_...
// Body:     OpenAI-compatible chat-completions (messages[], model, etc.)
//
// HF's "router" multiplexes over a few backend providers (Together,
// Fireworks, Replicate, etc.) using the same OpenAI-compatible request
// shape, so the wire format is essentially identical to OpenRouter.
// What differs:
//   - Model IDs are HF repo paths ('meta-llama/Llama-3.3-70B-Instruct',
//     'Qwen/Qwen2.5-72B-Instruct', etc.) — not provider/model slugs.
//   - Available models depend on the user's HF subscription. Use
//     GET https://router.huggingface.co/v1/models with the bearer token
//     to discover what's currently routable for that account.
//
// Cost: tokens are recorded; cost_usd is logged as 0. HF's per-model
// pricing varies by backend provider and changes; admins read
// authoritative spend on huggingface.co/settings/billing.

declare(strict_types=1);

require_once __DIR__ . '/../../config.php';

const GUA_HF_DEFAULT_MODEL_FALLBACK = 'meta-llama/Llama-3.3-70B-Instruct';
const GUA_HF_ENDPOINT               = 'https://router.huggingface.co/v1/chat/completions';
const GUA_HF_MODELS_ENDPOINT        = 'https://router.huggingface.co/v1/models';
const GUA_HF_TIMEOUT_SEC            = 60;

function ai_provider_huggingface_chat(string $api_key, array $messages, array $opts = []): array
{
    $model = (string)($opts['model'] ?? (defined('GUA_HF_DEFAULT_MODEL') ? GUA_HF_DEFAULT_MODEL : ''));
    if ($model === '') {
        $model = GUA_HF_DEFAULT_MODEL_FALLBACK;
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

    $ch = curl_init(GUA_HF_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => GUA_HF_TIMEOUT_SEC,
    ]);
    $raw   = curl_exec($ch);
    $errno = curl_errno($ch);
    $err   = curl_error($ch);
    $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        throw new RuntimeException("HuggingFace transport error ($errno): $err");
    }
    if ($code < 200 || $code >= 300) {
        $msg = $raw;
        $decoded = json_decode((string)$raw, true);
        if (is_array($decoded)) {
            if (isset($decoded['error']['message'])) {
                $msg = $decoded['error']['message'];
            } elseif (isset($decoded['error']) && is_string($decoded['error'])) {
                $msg = $decoded['error'];
            } elseif (isset($decoded['message'])) {
                $msg = $decoded['message'];
            }
        }
        throw new RuntimeException("HuggingFace HTTP $code: $msg");
    }

    $resp = json_decode((string)$raw, true);
    if (!is_array($resp)) {
        throw new RuntimeException('HuggingFace returned non-JSON body.');
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
 * Fetch the models routable for the given HF key (v2 Stage 8).
 * HF's router lists OpenAI-style { data: [...] }; each row's id is
 * a model slug ("provider/model-repo" or "model-repo").
 */
function ai_provider_huggingface_list_models(string $api_key): array
{
    $headers = [
        'Authorization: Bearer ' . $api_key,
        'Accept: application/json',
    ];

    $ch = curl_init(GUA_HF_MODELS_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => GUA_HF_TIMEOUT_SEC,
    ]);
    $raw   = curl_exec($ch);
    $errno = curl_errno($ch);
    $err   = curl_error($ch);
    $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        throw new RuntimeException("HuggingFace transport error ($errno): $err");
    }
    if ($code < 200 || $code >= 300) {
        throw new RuntimeException("HuggingFace models HTTP $code: " . substr((string)$raw, 0, 300));
    }
    $resp = json_decode((string)$raw, true);
    if (!is_array($resp) || !isset($resp['data']) || !is_array($resp['data'])) {
        throw new RuntimeException('HuggingFace models endpoint returned unexpected shape.');
    }

    $models = [];
    foreach ($resp['data'] as $row) {
        $id = (string)($row['id'] ?? '');
        if ($id === '') continue;
        $models[] = [
            'id'    => $id,
            'label' => $id,
        ];
    }
    usort($models, fn($a, $b) => strcmp($a['id'], $b['id']));
    return $models;
}
