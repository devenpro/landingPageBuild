-- core/migrations/0013_ai_providers_v2.sql — AI providers v2 (v2 Stage 8).
--
-- v1/early-v2 shipped three provider adapters (huggingface, gemini,
-- openrouter), each with a hard-coded default model and no way for the
-- admin to discover what models a given key actually has access to.
-- Stage 8 adds:
--   - Three new first-class providers: anthropic, openai, grok (xAI).
--   - A live model-fetch capability — each adapter exposes _list_models()
--     which hits the provider's /models endpoint with the stored key.
--     Results are cached in ai_model_cache so the admin UI is snappy and
--     we don't hammer the upstream API on every page render.
--   - Per-provider default-model settings (anthropic_default_model,
--     openai_default_model, grok_default_model, gemini_default_model,
--     openrouter_default_model) so each provider can be configured
--     independently of the others. hf_default_model already exists.
--
-- The provider allowlist itself lives in core/lib/ai/keys.php (PHP
-- constant), updated in the same commit. ai_default_provider's
-- description is patched here so the admin settings UI shows the full
-- list of valid values.
--
-- Cache invalidation: rows live for 24h (ai_model_cache_ttl_hours
-- setting). Admin can force-refresh from /admin/ai-keys.php — that
-- DELETEs the row for the provider before re-fetching. Empty model
-- lists are NOT cached (so a transient API outage doesn't pin "no
-- models available" for a day).

CREATE TABLE IF NOT EXISTS ai_model_cache (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  provider      TEXT NOT NULL UNIQUE,                     -- one row per provider
  models_json   TEXT NOT NULL,                            -- JSON array: [{id, label, context_window?, ...}]
  fetched_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  source_key_id INTEGER REFERENCES ai_provider_keys(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_ai_model_cache_provider ON ai_model_cache(provider);

-- Patch the description of ai_default_provider to list all six providers.
-- Re-INSERT OR REPLACE keeps the admin's chosen value intact (only
-- description changes — value column is preserved by re-reading it).
UPDATE site_settings
   SET description = 'Used when an admin AI tool or the chatbot does not specify a provider. One of: huggingface, gemini, openrouter, anthropic, openai, grok.'
 WHERE key = 'ai_default_provider';

-- Per-provider default-model settings. value=NULL on seed so settings_get()
-- falls through to the adapter's built-in default (e.g. Claude Haiku, gpt-4o-mini)
-- until admin overrides via UI.
INSERT OR IGNORE INTO site_settings
  (key, value_type, group_name, label, description, default_value) VALUES
  ('anthropic_default_model', 'string', 'ai', 'Anthropic default model',
    'Default Claude model when provider=anthropic and no per-call override. Admin can browse live models on /admin/ai-keys.php once a key is stored.',
    'claude-haiku-4-5-20251001'),
  ('openai_default_model',    'string', 'ai', 'OpenAI default model',
    'Default OpenAI model when provider=openai and no per-call override.',
    'gpt-4o-mini'),
  ('grok_default_model',      'string', 'ai', 'Grok (xAI) default model',
    'Default Grok model when provider=grok and no per-call override.',
    'grok-2-latest'),
  ('gemini_default_model',    'string', 'ai', 'Gemini default model',
    'Default Gemini model when provider=gemini and no per-call override.',
    'gemini-2.5-flash'),
  ('openrouter_default_model','string', 'ai', 'OpenRouter default model',
    'Default OpenRouter model slug when provider=openrouter and no per-call override.',
    'anthropic/claude-3.5-haiku'),
  ('ai_model_cache_ttl_hours','number', 'ai', 'Model cache TTL (hours)',
    'How long /admin/ai-keys.php may serve cached model lists before re-fetching from the upstream provider.',
    '24');
