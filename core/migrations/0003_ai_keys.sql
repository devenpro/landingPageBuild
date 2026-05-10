-- core/migrations/0003_ai_keys.sql — Phase 10 schema for AI key storage
-- and per-call audit log.
--
-- ai_provider_keys: BYO keys, encrypted at rest with libsodium secretbox.
-- The plaintext key is encrypted with the per-site master key from .env
-- (AI_KEYS_MASTER_KEY) using crypto_secretbox_easy. Each row stores its
-- own fresh nonce. (provider, label) is unique so admins can keep e.g.
-- a 'personal-free' Gemini key separate from an 'agency-paid' one and
-- pick which to use per AI tool.
--
-- ai_calls: every outbound call to a provider gets one row. Used by the
-- admin AI tools page (Phase 11) to surface spend, and by ratelimit.php
-- to enforce a global daily token cap. caller is a free-form tag set by
-- the caller (e.g. 'admin.suggest_pages', 'admin.generate_page',
-- 'public.chat') so spend can be sliced by feature.

CREATE TABLE IF NOT EXISTS ai_provider_keys (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  provider TEXT NOT NULL,
  label TEXT,
  encrypted_key BLOB NOT NULL,
  nonce BLOB NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  last_used_at DATETIME,
  UNIQUE(provider, label)
);
CREATE INDEX IF NOT EXISTS idx_ai_keys_provider ON ai_provider_keys(provider);

CREATE TABLE IF NOT EXISTS ai_calls (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  provider TEXT NOT NULL,
  model TEXT,
  caller TEXT,
  tokens_in INTEGER NOT NULL DEFAULT 0,
  tokens_out INTEGER NOT NULL DEFAULT 0,
  cost_estimate_usd REAL NOT NULL DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'ok' CHECK(status IN ('ok','error','timeout','ratelimit')),
  error_message TEXT,
  ip_address TEXT,
  duration_ms INTEGER,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_ai_calls_created ON ai_calls(created_at);
CREATE INDEX IF NOT EXISTS idx_ai_calls_caller ON ai_calls(caller, created_at);
CREATE INDEX IF NOT EXISTS idx_ai_calls_provider ON ai_calls(provider, created_at);
