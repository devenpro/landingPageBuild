<?php
// core/lib/ai/client.php — provider-agnostic facade.
//
// Callers (admin AI tools in Phase 11, chatbot in Phase 13) talk to
// ai_chat() and never touch provider adapters directly. The adapter is
// chosen from $opts['provider']; the API key is fetched via
// ai_keys_get() so plaintext stays inside this file's call stack.
//
// The contract on every adapter (see core/lib/ai/providers/*.php):
//   ai_provider_<name>_chat(string $api_key, array $messages, array $opts): array
// Returns a normalised result:
//   [
//     'text'        => string,         // assistant's text (concatenated if streamed)
//     'model'       => string,
//     'tokens_in'   => int,
//     'tokens_out'  => int,
//     'cost_usd'    => float,
//     'raw'         => mixed,          // raw provider response, optional
//   ]
//
// Errors throw RuntimeException with a user-readable message; the facade
// catches, logs an 'error' row in ai_calls, and rethrows. We do NOT
// silently fall back to another provider — that surprises billing.

declare(strict_types=1);

require_once __DIR__ . '/keys.php';
require_once __DIR__ . '/log.php';
require_once __DIR__ . '/ratelimit.php';
require_once __DIR__ . '/../config.php';

/**
 * Resolve the configured default provider, falling back to the first
 * entry in GUA_AI_PROVIDERS if .env's value is invalid. Phase 11/13
 * call this when no per-tool override is set.
 */
function ai_default_provider(): string
{
    $p = defined('GUA_AI_DEFAULT_PROVIDER') ? GUA_AI_DEFAULT_PROVIDER : '';
    if (in_array($p, GUA_AI_PROVIDERS, true)) {
        return $p;
    }
    return GUA_AI_PROVIDERS[0];
}

/**
 * Parse a model response that's supposed to be JSON. Strips ```json
 * fences and surrounding prose if the model ignored a "no markdown"
 * instruction (Gemini, in particular, will do this occasionally).
 *
 * Throws RuntimeException with a useful message on parse failure so
 * callers can return 502/422 to the admin instead of silently failing.
 *
 * @return mixed The decoded JSON value (typically an associative array)
 */
function ai_parse_json(string $text)
{
    $t = trim($text);

    // Strip a single leading/trailing markdown code fence pair.
    if (preg_match('/^```(?:json)?\s*\n?(.*?)\n?```$/is', $t, $m)) {
        $t = trim($m[1]);
    }

    // Some models wrap JSON in prose; try to find the first balanced { … }
    // or [ … ] block. Cheap heuristic: pull from the first { or [ to the
    // last matching } or ].
    if ($t !== '' && $t[0] !== '{' && $t[0] !== '[') {
        $first_brace = strpos($t, '{');
        $first_brack = strpos($t, '[');
        $start = $first_brace === false ? $first_brack
            : ($first_brack === false ? $first_brace : min($first_brace, $first_brack));
        if ($start !== false) {
            $open  = $t[$start];
            $close = $open === '{' ? '}' : ']';
            $end   = strrpos($t, $close);
            if ($end !== false && $end > $start) {
                $t = substr($t, $start, $end - $start + 1);
            }
        }
    }

    $decoded = json_decode($t, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException(
            'Model response was not valid JSON: ' . json_last_error_msg()
            . '. First 200 chars: ' . substr($text, 0, 200)
        );
    }
    return $decoded;
}

/**
 * @param string  $provider  one of GUA_AI_PROVIDERS
 * @param array   $messages  [{role: 'system'|'user'|'assistant', content: string}, ...]
 * @param array   $opts      {
 *   model?: string,
 *   key_label?: ?string,     // which stored key to use (null = most-recently-used)
 *   caller?: string,         // tag for ai_calls.caller (e.g. 'admin.suggest_pages')
 *   ip?: string,             // for rate limiting; defaults to REMOTE_ADDR or 'cli'
 *   skip_ratelimit?: bool,   // admin-side calls may bypass per-IP cap
 *   temperature?: float,
 *   max_tokens?: int,
 * }
 * @return array  normalised result (see header comment)
 */
function ai_chat(string $provider, array $messages, array $opts = []): array
{
    ai_keys_validate_provider($provider);

    $caller = $opts['caller'] ?? 'unknown';
    $ip     = $opts['ip']     ?? ($_SERVER['REMOTE_ADDR'] ?? 'cli');
    $skip_rl = (bool)($opts['skip_ratelimit'] ?? false);

    if (!$skip_rl) {
        if (ai_ratelimit_ip_exceeded($ip, $caller)) {
            ai_log_call([
                'provider' => $provider,
                'caller'   => $caller,
                'status'   => 'ratelimit',
                'error_message' => 'per-IP cap',
                'ip_address'   => $ip,
            ]);
            throw new RuntimeException('Rate limit reached for this IP. Try again shortly.');
        }
        if (ai_ratelimit_global_exceeded()) {
            ai_log_call([
                'provider' => $provider,
                'caller'   => $caller,
                'status'   => 'ratelimit',
                'error_message' => 'global daily cap',
                'ip_address'   => $ip,
            ]);
            throw new RuntimeException('Daily AI usage limit reached. Try again tomorrow.');
        }
    }

    $api_key = ai_keys_get($provider, $opts['key_label'] ?? null);
    if ($api_key === null) {
        throw new RuntimeException("No '$provider' API key on file. Add one in admin → settings.");
    }

    $adapter = __DIR__ . '/providers/' . $provider . '.php';
    if (!is_file($adapter)) {
        throw new RuntimeException("Provider adapter missing: $provider");
    }
    require_once $adapter;
    $fn = 'ai_provider_' . $provider . '_chat';
    if (!function_exists($fn)) {
        throw new RuntimeException("Provider adapter '$provider' did not register $fn().");
    }

    $started = microtime(true);
    try {
        $result = $fn($api_key, $messages, $opts);
        sodium_memzero($api_key);

        ai_log_call([
            'provider'          => $provider,
            'model'             => $result['model'] ?? null,
            'caller'            => $caller,
            'tokens_in'         => (int)($result['tokens_in']  ?? 0),
            'tokens_out'        => (int)($result['tokens_out'] ?? 0),
            'cost_estimate_usd' => (float)($result['cost_usd']  ?? 0.0),
            'status'            => 'ok',
            'ip_address'        => $ip,
            'duration_ms'       => (int) round((microtime(true) - $started) * 1000),
        ]);

        return $result;
    } catch (Throwable $e) {
        ai_log_call([
            'provider'      => $provider,
            'caller'        => $caller,
            'status'        => 'error',
            'error_message' => substr($e->getMessage(), 0, 500),
            'ip_address'    => $ip,
            'duration_ms'   => (int) round((microtime(true) - $started) * 1000),
        ]);
        throw $e;
    }
}
