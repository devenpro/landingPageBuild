<?php
// core/lib/ai/models.php — provider-agnostic model-discovery facade (v2 Stage 8).
//
// Each provider adapter exposes `ai_provider_<name>_list_models($api_key)`
// which hits the upstream /models endpoint and returns a normalised
// [{id, label, ...}] array. This file dispatches to the right adapter
// and caches results in the ai_model_cache table (24h TTL by default,
// configurable via ai_model_cache_ttl_hours setting).
//
// Callers:
//   - /admin/ai-keys.php  — show model list per stored key
//   - /api/ai/models.php  — admin endpoint (browse + force refresh)
//
// We deliberately keep the cache simple: one row per provider, last
// fetch wins, manual invalidation only. A per-key cache would be more
// precise but the typical site only has one key per provider, and the
// list barely diverges across keys for the same vendor.

declare(strict_types=1);

require_once __DIR__ . '/keys.php';
require_once __DIR__ . '/../settings.php';

/**
 * Return models for $provider. Reads from cache if a fresh row exists,
 * otherwise fetches live and writes the row. Pass force=true to bypass
 * the cache (admin "Refresh" action).
 *
 * Throws RuntimeException if no API key is stored for the provider, or
 * the upstream call fails with no cache to fall back on.
 *
 * Returns ['models' => [...], 'source' => 'cache'|'live', 'fetched_at' => ...]
 */
function ai_models_for_provider(string $provider, bool $force = false): array
{
    ai_keys_validate_provider($provider);

    if (!$force) {
        $cached = ai_models_cache_get($provider);
        if ($cached !== null && !ai_models_cache_is_stale($cached['fetched_at'])) {
            return [
                'models'     => $cached['models'],
                'source'     => 'cache',
                'fetched_at' => $cached['fetched_at'],
            ];
        }
    }

    // Cache miss / forced refresh — fetch live.
    $api_key = ai_keys_get($provider);
    if ($api_key === null) {
        // OpenRouter's /models endpoint works without auth, so allow
        // an empty key only for that provider.
        if ($provider !== 'openrouter') {
            throw new RuntimeException("No '$provider' API key on file. Add one in /admin/ai-keys.php first.");
        }
        $api_key = '';
    }

    $adapter = __DIR__ . '/providers/' . $provider . '.php';
    if (!is_file($adapter)) {
        throw new RuntimeException("Provider adapter missing: $provider");
    }
    require_once $adapter;
    $fn = 'ai_provider_' . $provider . '_list_models';
    if (!function_exists($fn)) {
        throw new RuntimeException("Provider '$provider' does not support live model listing.");
    }

    try {
        $models = $fn($api_key);
    } finally {
        if ($api_key !== '') sodium_memzero($api_key);
    }

    if (!is_array($models)) {
        throw new RuntimeException("Provider '$provider' returned a non-array model list.");
    }
    // Don't cache empty results — they're almost always a transient
    // upstream error (rate-limited /models endpoint, etc.) and we'd
    // rather re-try next request than pin a "no models" answer for
    // the full TTL.
    if ($models !== []) {
        ai_models_cache_put($provider, $models);
    }

    return [
        'models'     => $models,
        'source'     => 'live',
        'fetched_at' => gmdate('Y-m-d H:i:s'),
    ];
}

/**
 * Read the cache row for $provider. Returns null if no row exists.
 * Returns ['models' => [...], 'fetched_at' => 'YYYY-MM-DD HH:MM:SS']
 */
function ai_models_cache_get(string $provider): ?array
{
    $stmt = db()->prepare(
        'SELECT models_json, fetched_at FROM ai_model_cache WHERE provider = :p LIMIT 1'
    );
    $stmt->execute([':p' => $provider]);
    $row = $stmt->fetch();
    if ($row === false) return null;

    $models = json_decode((string)$row['models_json'], true);
    if (!is_array($models)) return null;
    return [
        'models'     => $models,
        'fetched_at' => (string)$row['fetched_at'],
    ];
}

function ai_models_cache_put(string $provider, array $models): void
{
    $json = json_encode($models, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('Failed to encode model list as JSON.');
    }
    $stmt = db()->prepare(
        "INSERT INTO ai_model_cache (provider, models_json, fetched_at)
              VALUES (:p, :j, strftime('%Y-%m-%d %H:%M:%S', 'now'))
         ON CONFLICT(provider) DO UPDATE
            SET models_json = excluded.models_json,
                fetched_at  = excluded.fetched_at"
    );
    $stmt->execute([':p' => $provider, ':j' => $json]);
}

function ai_models_cache_delete(string $provider): bool
{
    $stmt = db()->prepare('DELETE FROM ai_model_cache WHERE provider = :p');
    $stmt->execute([':p' => $provider]);
    return $stmt->rowCount() > 0;
}

function ai_models_cache_is_stale(string $fetched_at): bool
{
    $ttl_h = (int) settings_get('ai_model_cache_ttl_hours', 24);
    if ($ttl_h <= 0) return true;
    $fetched = strtotime($fetched_at . ' UTC');
    if ($fetched === false) return true;
    return (time() - $fetched) >= ($ttl_h * 3600);
}

/**
 * Default model for a given provider, sourced from settings then from
 * the adapter's _FALLBACK constant if no setting is configured. Used
 * by ai_chat() when no $opts['model'] is passed.
 */
function ai_default_model_for(string $provider): string
{
    ai_keys_validate_provider($provider);
    $setting_key = $provider . '_default_model';
    if ($provider === 'huggingface') $setting_key = 'hf_default_model';
    $value = (string) settings_get($setting_key, '');
    if ($value !== '') return $value;

    // Fall back to the adapter's hard-coded fallback constant.
    $adapter = __DIR__ . '/providers/' . $provider . '.php';
    if (is_file($adapter)) require_once $adapter;

    $constants = [
        'huggingface' => 'GUA_HF_DEFAULT_MODEL_FALLBACK',
        'gemini'      => 'GUA_GEMINI_DEFAULT_MODEL_FALLBACK',
        'openrouter'  => 'GUA_OPENROUTER_DEFAULT_MODEL_FALLBACK',
        'anthropic'   => 'GUA_ANTHROPIC_DEFAULT_MODEL_FALLBACK',
        'openai'      => 'GUA_OPENAI_DEFAULT_MODEL_FALLBACK',
        'grok'        => 'GUA_GROK_DEFAULT_MODEL_FALLBACK',
    ];
    $c = $constants[$provider] ?? '';
    return ($c !== '' && defined($c)) ? (string) constant($c) : '';
}
