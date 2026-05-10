<?php
// core/lib/ai/providers/gemini.php — Google Gemini adapter.
//
// Endpoint: https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent
// Auth:     ?key=API_KEY query string
//
// Message-shape translation:
//   our 'system' role           → top-level systemInstruction
//   our 'user' role             → contents[].role = 'user'
//   our 'assistant' role        → contents[].role = 'model'  (Gemini's own term)
//
// Cost: tokens are recorded; cost_usd is logged as 0. Per-model pricing
// changes too often (and silently — old aliases get retired) to embed
// here. Admins read authoritative spend on the Google Cloud console;
// a Phase 14 polish item will plumb a per-call cost lookup.

declare(strict_types=1);

const GUA_GEMINI_DEFAULT_MODEL = 'gemini-2.5-flash';
const GUA_GEMINI_ENDPOINT      = 'https://generativelanguage.googleapis.com/v1beta/models/';
const GUA_GEMINI_TIMEOUT_SEC   = 30;

function ai_provider_gemini_chat(string $api_key, array $messages, array $opts = []): array
{
    $model = (string)($opts['model'] ?? GUA_GEMINI_DEFAULT_MODEL);

    $system_text = '';
    $contents    = [];
    foreach ($messages as $m) {
        $role = (string)($m['role']    ?? 'user');
        $text = (string)($m['content'] ?? '');
        if ($role === 'system') {
            $system_text .= ($system_text === '' ? '' : "\n\n") . $text;
            continue;
        }
        $contents[] = [
            'role'  => $role === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $text]],
        ];
    }

    $body = ['contents' => $contents];
    if ($system_text !== '') {
        $body['systemInstruction'] = ['parts' => [['text' => $system_text]]];
    }
    $gen_config = [];
    if (isset($opts['temperature'])) $gen_config['temperature']     = (float)$opts['temperature'];
    if (isset($opts['max_tokens']))  $gen_config['maxOutputTokens'] = (int)$opts['max_tokens'];
    if ($gen_config !== []) {
        $body['generationConfig'] = $gen_config;
    }

    $url = GUA_GEMINI_ENDPOINT . rawurlencode($model) . ':generateContent?key=' . rawurlencode($api_key);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => GUA_GEMINI_TIMEOUT_SEC,
    ]);
    $raw   = curl_exec($ch);
    $errno = curl_errno($ch);
    $err   = curl_error($ch);
    $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        throw new RuntimeException("Gemini transport error ($errno): $err");
    }
    if ($code < 200 || $code >= 300) {
        $msg = $raw;
        $decoded = json_decode((string)$raw, true);
        if (is_array($decoded) && isset($decoded['error']['message'])) {
            $msg = $decoded['error']['message'];
        }
        throw new RuntimeException("Gemini HTTP $code: $msg");
    }

    $resp = json_decode((string)$raw, true);
    if (!is_array($resp)) {
        throw new RuntimeException('Gemini returned non-JSON body.');
    }

    $text = '';
    foreach ($resp['candidates'][0]['content']['parts'] ?? [] as $part) {
        if (isset($part['text'])) $text .= $part['text'];
    }

    $tokens_in   = (int)($resp['usageMetadata']['promptTokenCount']     ?? 0);
    $tokens_vis  = (int)($resp['usageMetadata']['candidatesTokenCount'] ?? 0);
    $tokens_thought = (int)($resp['usageMetadata']['thoughtsTokenCount'] ?? 0);
    // Gemini 2.x "thinking" models bill hidden reasoning tokens alongside
    // the visible response. Sum them into tokens_out so the daily cap and
    // spend reports reflect actual usage. If a caller sets a tight
    // max_tokens budget the model can spend it all on thoughts and
    // produce empty text — caller decides whether that's an error.
    $tokens_out  = $tokens_vis + $tokens_thought;

    return [
        'text'       => $text,
        'model'      => $model,
        'tokens_in'  => $tokens_in,
        'tokens_out' => $tokens_out,
        'cost_usd'   => 0.0,
        'raw'        => $resp,
    ];
}
