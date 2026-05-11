-- core/migrations/0007_brand_context.sql — Brand Context Library (v2 Stage 2).
--
-- Categorised brand knowledge that the admin curates once and that AI
-- prompts read from automatically. Replaces the v1 pattern where every
-- AI call started from scratch with no source-of-truth for brand voice,
-- audience, or services.
--
-- Editing model:
--   - Admin edits via /admin/brand.php — DB is the source of truth.
--   - Each save also writes a markdown file under .brand/<category>/<slug>.md
--     so Claude Code (desktop or mobile) can read/edit the same content
--     during repo-aware sessions.
--   - Disk edits do NOT auto-update the DB. Admin sees a drift banner on
--     the dashboard / brand page and pulls changes via /admin/brand-sync.php
--     after reviewing a per-item diff.
--
-- Hashing:
--   - body_hash = sha256 of the current DB body. Recomputed on every save.
--   - disk_hash = sha256 of the body the last time we wrote (or pulled)
--     it to/from disk. brand_sync_dirty() compares disk_hash to a fresh
--     hash of the on-disk file; difference = drift.
--
-- AI review gate (Stage 10 bootstrap depends on this):
--   - source='ai' AND ai_reviewed=0 → excluded by the prompt context
--     assembler. Admin must open and save the item to mark ai_reviewed=1.

CREATE TABLE IF NOT EXISTS brand_categories (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  slug         TEXT NOT NULL UNIQUE,
  label        TEXT NOT NULL,
  description  TEXT,
  sort_order   INTEGER NOT NULL DEFAULT 100,
  is_builtin   INTEGER NOT NULL DEFAULT 0,
  required     INTEGER NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS brand_items (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  category_id  INTEGER NOT NULL REFERENCES brand_categories(id) ON DELETE CASCADE,
  slug         TEXT NOT NULL,
  title        TEXT NOT NULL,
  kind         TEXT NOT NULL DEFAULT 'markdown'
                    CHECK(kind IN ('markdown','facts','links','refs')),
  body         TEXT NOT NULL DEFAULT '',
  body_hash    TEXT NOT NULL,                            -- sha256 of body
  disk_hash    TEXT,                                      -- sha256 of body last written to / read from disk
  status       TEXT NOT NULL DEFAULT 'active'
                    CHECK(status IN ('active','draft','archived')),
  source       TEXT NOT NULL DEFAULT 'admin'
                    CHECK(source IN ('admin','ai','imported','bootstrap','disk')),
  source_meta  TEXT,
  ai_reviewed  INTEGER NOT NULL DEFAULT 1,                -- 1 by default; AI-generated rows override to 0
  always_on    INTEGER NOT NULL DEFAULT 0,
  version      INTEGER NOT NULL DEFAULT 1,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by   INTEGER REFERENCES admin_users(id) ON DELETE SET NULL,
  UNIQUE(category_id, slug)
);

CREATE INDEX IF NOT EXISTS idx_brand_items_status     ON brand_items(status);
CREATE INDEX IF NOT EXISTS idx_brand_items_always_on  ON brand_items(always_on);
CREATE INDEX IF NOT EXISTS idx_brand_items_source     ON brand_items(source);

-- Per-save snapshot for restore. Capped retention is the admin UI's job
-- (Stage 2 keeps everything; later stages can prune).
CREATE TABLE IF NOT EXISTS brand_item_history (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  item_id      INTEGER NOT NULL REFERENCES brand_items(id) ON DELETE CASCADE,
  version      INTEGER NOT NULL,
  title        TEXT NOT NULL,
  kind         TEXT NOT NULL,
  body         TEXT NOT NULL,
  source       TEXT NOT NULL,
  source_meta  TEXT,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by   INTEGER REFERENCES admin_users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_brand_item_history_item ON brand_item_history(item_id);

-- Seed: 8 built-in categories. Required ones are flagged so the audit
-- can surface gaps. sort_order keeps the admin UI ordered the way the
-- bootstrap wizard wants to read them.

INSERT OR IGNORE INTO brand_categories
  (slug, label, description, sort_order, is_builtin, required) VALUES
  ('brand_voice',  'Brand Voice',
     'Tone, vocabulary, and writing style. The single biggest lever on every AI-generated word.',
     10, 1, 1),
  ('brand_facts',  'Brand Facts',
     'Verifiable claims: founding date, certifications, awards, headquarters, team size, named customers. AI is forbidden from inventing facts not on this list.',
     20, 1, 1),
  ('audience',     'Audience',
     'Who the site is for: roles, seniority, jobs-to-be-done, objections, vocabulary they use.',
     30, 1, 1),
  ('services',     'Services',
     'What the business sells, with one-line summaries. Service detail pages and ad landing pages pull from this.',
     40, 1, 1),
  ('design_guide', 'Design Guide',
     'Brand colours, typography, layout patterns, image style. Inline editor + AI-generated pages respect these.',
     50, 1, 0),
  ('page_guide',   'Page Guide',
     'How AI should structure new pages: section order, what to lead with, calls-to-action, length expectations.',
     60, 1, 1),
  ('seo',          'SEO',
     'Target keywords, geographic focus, competitive landscape, internal linking conventions.',
     70, 1, 0),
  ('social',       'Social',
     'External links and handles: LinkedIn, X, YouTube, GitHub, etc. Used by the chatbot when asked "where can I follow you".',
     80, 1, 0);

-- Seed: one empty placeholder item per category so the admin sees the
-- slot and the audit knows what's missing. body_hash is sha256('') for
-- empty bodies — see core/lib/brand/sync.php for the hash function.
-- Source 'bootstrap' so the audit treats them as unfilled (not stale).

INSERT OR IGNORE INTO brand_items
  (category_id, slug, title, kind, body, body_hash, status, source, ai_reviewed)
SELECT c.id, 'core-tone', 'Core tone', 'markdown', '',
  'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
  'active', 'bootstrap', 1
FROM brand_categories c WHERE c.slug = 'brand_voice';

INSERT OR IGNORE INTO brand_items
  (category_id, slug, title, kind, body, body_hash, status, source, ai_reviewed)
SELECT c.id, 'company', 'Company facts', 'facts', '',
  'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
  'active', 'bootstrap', 1
FROM brand_categories c WHERE c.slug = 'brand_facts';

INSERT OR IGNORE INTO brand_items
  (category_id, slug, title, kind, body, body_hash, status, source, ai_reviewed)
SELECT c.id, 'primary', 'Primary audience', 'markdown', '',
  'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
  'active', 'bootstrap', 1
FROM brand_categories c WHERE c.slug = 'audience';

INSERT OR IGNORE INTO brand_items
  (category_id, slug, title, kind, body, body_hash, status, source, ai_reviewed)
SELECT c.id, 'service-catalog', 'Service catalog', 'markdown', '',
  'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
  'active', 'bootstrap', 1
FROM brand_categories c WHERE c.slug = 'services';

INSERT OR IGNORE INTO brand_items
  (category_id, slug, title, kind, body, body_hash, status, source, ai_reviewed)
SELECT c.id, 'visual-style', 'Visual style', 'markdown', '',
  'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
  'active', 'bootstrap', 1
FROM brand_categories c WHERE c.slug = 'design_guide';

INSERT OR IGNORE INTO brand_items
  (category_id, slug, title, kind, body, body_hash, status, source, ai_reviewed)
SELECT c.id, 'how-ai-builds-pages', 'How AI should build pages', 'markdown', '',
  'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
  'active', 'bootstrap', 1
FROM brand_categories c WHERE c.slug = 'page_guide';

INSERT OR IGNORE INTO brand_items
  (category_id, slug, title, kind, body, body_hash, status, source, ai_reviewed)
SELECT c.id, 'core-seo', 'Core SEO', 'markdown', '',
  'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
  'active', 'bootstrap', 1
FROM brand_categories c WHERE c.slug = 'seo';

INSERT OR IGNORE INTO brand_items
  (category_id, slug, title, kind, body, body_hash, status, source, ai_reviewed)
SELECT c.id, 'external-links', 'External links', 'links', '',
  'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
  'active', 'bootstrap', 1
FROM brand_categories c WHERE c.slug = 'social';
