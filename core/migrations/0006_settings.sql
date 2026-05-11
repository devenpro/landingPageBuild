-- core/migrations/0006_settings.sql — DB-backed site settings (v2 Stage 1).
--
-- Replaces the v1 pattern of one .env knob per runtime config, where every
-- change required a redeploy. Settings are now stored in this table and
-- exposed through core/lib/settings.php with this resolution order:
--
--   1. site_settings.value (this table)   — admin-editable via /admin/settings.php
--   2. .env                                — per-site override / ops escape hatch
--   3. hard default in calling code         — last-resort fallback
--
-- Existing GUA_* constants are preserved by core/lib/runtime_constants.php,
-- which assigns them via settings_get() at bootstrap so admin UI changes take
-- effect on the next request without code changes.
--
-- Paths and secrets (DB_PATH, AI_KEYS_MASTER_KEY, etc.) intentionally stay
-- in .env exclusively — they're loaded before this table is reachable.
--
-- value_type drives both UI rendering and casting on read. is_secret hides
-- the value in the admin UI (shown as "•••• [edit]"). default_value records
-- the hard-coded fallback for the "Reset to default" button.

CREATE TABLE IF NOT EXISTS site_settings (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  key           TEXT NOT NULL UNIQUE,
  value         TEXT,                                          -- NULL = fall through to .env / default
  value_type    TEXT NOT NULL DEFAULT 'string'
                     CHECK(value_type IN ('string','number','boolean','json','secret')),
  group_name    TEXT NOT NULL,
  label         TEXT NOT NULL,
  description   TEXT,
  is_secret     INTEGER NOT NULL DEFAULT 0,
  default_value TEXT,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by    INTEGER REFERENCES admin_users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_site_settings_group ON site_settings(group_name);

-- Seed metadata for every existing .env knob. value=NULL so settings_get()
-- falls through to .env until admin saves an override via the UI. Metadata
-- (group, label, description, default_value) is the same across all site
-- clones, so it lives in core/migrations rather than per-site seed.

INSERT OR IGNORE INTO site_settings (key, value_type, group_name, label, description, is_secret, default_value) VALUES
  -- General
  ('app_env',                'string',  'general',  'App environment',           'production / staging / development. Affects error verbosity.', 0, 'production'),
  ('app_url',                'string',  'general',  'Public site URL',           'Canonical https URL with no trailing slash. Used in JSON-LD, OG tags, sitemaps, webhook payloads.', 0, ''),
  ('site_name',              'string',  'general',  'Site name',                 'Display name in nav, page titles, and JSON-LD Organization.', 0, 'Go Ultra AI'),
  ('admin_email',            'string',  'general',  'Admin email',               'Used as the seed admin login and in form notification headers.', 0, ''),
  ('session_lifetime_hours', 'number',  'general',  'Admin session lifetime (hours)', 'How long an admin session stays active without activity.', 0, '8'),

  -- AI
  ('ai_default_provider',    'string',  'ai',       'Default AI provider',       'Used when an admin AI tool or the chatbot does not specify a provider. One of: huggingface, gemini, openrouter (Stage 8 adds anthropic, openai, grok).', 0, 'huggingface'),
  ('hf_default_model',       'string',  'ai',       'HuggingFace default model', 'Default model when ai_default_provider=huggingface and no per-call override.', 0, 'meta-llama/Llama-3.3-70B-Instruct'),
  ('ai_chat_enabled',        'boolean', 'ai',       'Public chatbot enabled',    'Renders the floating widget on public pages and opens /api/chat.php to traffic. Off by default until you have set a key for the default provider.', 0, '0'),
  ('ai_chat_persist',        'boolean', 'ai',       'Persist chat transcripts',  'Writes every visitor message and assistant reply to ai_chat_messages for later admin review. Off by default for visitor privacy.', 0, '0'),

  -- Webhooks
  ('webhook_url',            'string',  'webhooks', 'Default form webhook URL',  'Outbound POST target for form submissions when a form does not specify its own webhook. Stage 6 introduces per-form webhooks; this is the v1 fallback.', 0, ''),
  ('webhook_timeout_seconds','number',  'webhooks', 'Webhook timeout (seconds)', 'How long the inline POST waits before queueing for retry.', 0, '10'),

  -- Media
  ('max_image_bytes',        'number',  'media',    'Max image upload size (bytes)', 'Hard cap on uploaded image files. Default 5 MB.', 0, '5242880'),
  ('max_video_bytes',        'number',  'media',    'Max video upload size (bytes)', 'Hard cap on uploaded video files. Default 50 MB.', 0, '52428800');
