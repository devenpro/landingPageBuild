-- core/migrations/0011_forms.sql — multi-form CRUD + per-form webhooks (v2 Stage 6).
--
-- v1 hard-coded the waitlist form in site/sections/final_cta.php and
-- validated against fixed columns in form_submissions. /api/form.php
-- fired a single GUA_WEBHOOK_URL from .env. Stage 6 introduces:
--
--   forms          — multiple admin-defined forms with slugs
--   form_fields    — per-form field definitions (label, type, validation)
--   form_webhooks  — per-form outbound webhooks with payload templates
--
-- form_submissions gains form_id + data_json columns (existing rows get
-- form_id=1, the seeded waitlist form, and data_json reconstructed from
-- the v1 columns via UPDATE). The legacy columns stay readable for one
-- release. webhook_deliveries gains form_id + webhook_id so the admin
-- inbox can show which webhook fired for each submission.

CREATE TABLE IF NOT EXISTS forms (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  slug          TEXT NOT NULL UNIQUE,
  name          TEXT NOT NULL,
  description   TEXT,
  status        TEXT NOT NULL DEFAULT 'active'
                     CHECK(status IN ('active','draft','archived')),
  settings_json TEXT,                                     -- success_heading, success_body, success_redirect, notification_email, retention_days, anti_spam, etc.
  is_builtin    INTEGER NOT NULL DEFAULT 0,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by    INTEGER REFERENCES admin_users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS form_fields (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  form_id         INTEGER NOT NULL REFERENCES forms(id) ON DELETE CASCADE,
  position        INTEGER NOT NULL DEFAULT 0,
  type            TEXT NOT NULL DEFAULT 'text'
                       CHECK(type IN ('text','email','phone','textarea','select','radio','checkbox','file','hidden','url','number','date')),
  name            TEXT NOT NULL,
  label           TEXT NOT NULL,
  placeholder     TEXT,
  default_value   TEXT,
  required        INTEGER NOT NULL DEFAULT 0,
  options_json    TEXT,                                   -- for select / radio / checkbox: ["label/value", ...]
  validation_json TEXT,                                   -- max_length, min_length, regex pattern, custom error message
  help_text       TEXT,
  UNIQUE(form_id, name)
);
CREATE INDEX IF NOT EXISTS idx_form_fields_form ON form_fields(form_id);

CREATE TABLE IF NOT EXISTS form_webhooks (
  id                    INTEGER PRIMARY KEY AUTOINCREMENT,
  form_id               INTEGER NOT NULL REFERENCES forms(id) ON DELETE CASCADE,
  name                  TEXT NOT NULL DEFAULT 'Webhook',
  url                   TEXT NOT NULL,
  method                TEXT NOT NULL DEFAULT 'POST',
  headers_json          TEXT,                             -- JSON object of header → value
  payload_template_json TEXT,                             -- JSON template; values may reference {{field_name}} or {{meta.<key>}}
  fire_on_json          TEXT,                             -- optional conditions: only fire when fields match these constraints
  signing_secret        TEXT,                             -- HMAC-signs the payload, sent as X-Signature
  max_retries           INTEGER NOT NULL DEFAULT 6,
  enabled               INTEGER NOT NULL DEFAULT 1,
  created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_form_webhooks_form ON form_webhooks(form_id);

-- form_submissions: add form_id + data_json so multi-form submissions work.
-- Legacy fixed columns stay readable for one release; new submissions
-- store everything in data_json. (SQLite ALTER TABLE only supports ADD;
-- migration is purely additive.)
ALTER TABLE form_submissions ADD COLUMN form_id   INTEGER;
ALTER TABLE form_submissions ADD COLUMN data_json TEXT DEFAULT '{}';
CREATE INDEX IF NOT EXISTS idx_form_submissions_form ON form_submissions(form_id, submitted_at);

-- webhook_deliveries: track which form + webhook produced each delivery.
ALTER TABLE webhook_deliveries ADD COLUMN form_id    INTEGER;
ALTER TABLE webhook_deliveries ADD COLUMN webhook_id INTEGER;
CREATE INDEX IF NOT EXISTS idx_webhook_deliveries_form_webhook ON webhook_deliveries(form_id, webhook_id);

-- Seed: waitlist as form #1. Settings mirror v1's success message keys.
INSERT OR IGNORE INTO forms
  (id, slug, name, description, status, settings_json, is_builtin) VALUES
  (1, 'waitlist', 'Waitlist',
    'Public sign-up form on the home page (v1 default).',
    'active',
    '{"success_heading":"You''re on the list","success_body":"Thanks — we''ll be in touch shortly.","redirect_url":null,"notification_email":null,"retention_days":null,"honeypot":"website"}',
    1);

-- Seed: waitlist fields (matching the v1 hard-coded form).
INSERT OR IGNORE INTO form_fields
  (form_id, position, type, name, label, placeholder, required, validation_json) VALUES
  (1, 0, 'text',     'full_name',       'Full name',                   NULL, 1, '{"max_length":100}'),
  (1, 1, 'email',    'email',           'Work email',                  NULL, 1, '{"max_length":150}'),
  (1, 2, 'phone',    'phone',           'Phone',                       NULL, 1, '{"max_length":40}'),
  (1, 3, 'text',     'role',            'Your role',                   NULL, 1, '{"max_length":80}'),
  (1, 4, 'text',     'clients_managed', 'Clients you manage (count)',  '5–20', 0, '{"max_length":80}'),
  (1, 5, 'textarea', 'bottleneck',      'Biggest bottleneck right now', NULL, 0, '{"max_length":1000}');

-- Backfill form_id and reconstruct data_json for existing v1 submissions.
UPDATE form_submissions SET form_id = 1 WHERE form_id IS NULL;
UPDATE form_submissions
   SET data_json = json_object(
        'full_name',       full_name,
        'email',           email,
        'phone',           phone,
        'role',            role,
        'clients_managed', clients_managed,
        'bottleneck',      bottleneck
   )
 WHERE form_id = 1 AND (data_json = '{}' OR data_json IS NULL);
