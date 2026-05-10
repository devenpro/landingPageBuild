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
// Cost estimate: only computed for the two models we explicitly recommend
// (flash + pro). Anything else logs cost_usd=0; admin can look up actuals
// on the Google Cloud console.

declare(strict_types=1);

const GUA_GEMINI_DEFAULT_MODEL = 'gemini-1.5-flash';
const GUA_GEMINI_ENDPOINT      = 'https://generativelanguage.googleapis.com/v1beta/models/';
const GUA_GEMINI_TIMEOUT_SEC   = 30;

// Per 1M tokens, USD. Last reviewed 2026-05; verify before relying on for
// alerts. Out-of-bounds models fall back to 0 (logged but unpriced).
const GUA_GEMINI_PRICING = [
    'gemini-1.5-flash'     => ['in' => 0.075, 'out' => 0.30],
    'gemini-1.5-flash-8b'  => ['in' => 0.0375,'out' => 0.15],
    'gemini-1.5-pro'       => ['in' => 1.25,  'out' => 5.00],
];

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

    $tokens_in  = (int)($resp['usageMetadata']['promptTokenCount']     ?? 0);
    $tokens_out = (int)($resp['usageMetadata']['candidatesTokenCount'] ?? 0);

    $cost = 0.0;
    if (isset(GUA_GEMINI_PRICING[$model])) {
        $p = GUA_GEMINI_PRICING[$model];
        $cost = ($tokens_in / 1_000_000) * $p['in'] + ($tokens_out / 1_000_000) * $p['out'];
    }

    return [
        'text'       => $text,
        'model'      => $model,
        'tokens_in'  => $tokens_in,
        'tokens_out' => $tokens_out,
        'cost_usd'   => $cost,
        'raw'        => $resp,
    ];
}
