-- core/migrations/0008_blocks_split.sql — split content_blocks into reusable
-- block library + page-scoped fields (v2 Stage 3).
--
-- v1 stored all content (including page-scoped variants prefixed with
-- "page.<slug>.") in one flat content_blocks(key, value, type) table.
-- v2 splits this into:
--
--   content_blocks         block DEFINITIONS (hero, features, faq, …)
--   content_block_fields   field VALUES for each block (1 row per field)
--   page_fields            per-page overrides (1 row per (page, field))
--
-- The v1 table is renamed to legacy_content and kept as a read-fallback
-- for one release in case any caller still references it directly.
--
-- All keys in legacy_content of form "<prefix>.<rest>" become:
--   - block content_blocks(slug=<prefix>)
--   - field content_block_fields(block_id=…, field_key=<rest>, value, type)
--
-- All keys of form "page.<page_slug>.<rest>" become:
--   - page_fields(page_id=…, field_key=<rest>, value, type)
-- (zero such rows exist in current data, but the migration covers them
-- in case a site has been using page-scoped overrides from Phase 9.)

-- 1. Rename v1 table out of the way
ALTER TABLE content_blocks RENAME TO legacy_content;
DROP INDEX IF EXISTS idx_content_blocks_key;
CREATE INDEX IF NOT EXISTS idx_legacy_content_key ON legacy_content(key);

-- 2. New block definitions table
CREATE TABLE content_blocks (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  slug            TEXT NOT NULL UNIQUE,
  name            TEXT NOT NULL,
  description     TEXT,
  category        TEXT NOT NULL DEFAULT 'section',
  status          TEXT NOT NULL DEFAULT 'active'
                       CHECK(status IN ('active','draft','archived')),
  schema_json     TEXT,
  preview_partial TEXT,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by      INTEGER REFERENCES admin_users(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_content_blocks_category ON content_blocks(category);

-- 3. Field values for each block
CREATE TABLE content_block_fields (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  block_id    INTEGER NOT NULL REFERENCES content_blocks(id) ON DELETE CASCADE,
  field_key   TEXT NOT NULL,
  value       TEXT,
  type        TEXT NOT NULL DEFAULT 'text'
                   CHECK(type IN ('text','image','video','icon','list','seo','html')),
  position    INTEGER NOT NULL DEFAULT 0,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by  INTEGER REFERENCES admin_users(id) ON DELETE SET NULL,
  UNIQUE(block_id, field_key)
);
CREATE INDEX IF NOT EXISTS idx_content_block_fields_block ON content_block_fields(block_id);

-- 4. Page-scoped field overrides
CREATE TABLE page_fields (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  page_id     INTEGER NOT NULL REFERENCES pages(id) ON DELETE CASCADE,
  field_key   TEXT NOT NULL,
  value       TEXT,
  type        TEXT NOT NULL DEFAULT 'text'
                   CHECK(type IN ('text','image','video','icon','list','seo','html')),
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by  INTEGER REFERENCES admin_users(id) ON DELETE SET NULL,
  UNIQUE(page_id, field_key)
);
CREATE INDEX IF NOT EXISTS idx_page_fields_page ON page_fields(page_id);

-- 5. Populate block definitions from legacy section prefixes.
--    A "section prefix" is everything before the first '.' in the legacy
--    key, excluding "page.*" rows which go to page_fields below.
INSERT OR IGNORE INTO content_blocks (slug, name, category, status)
SELECT DISTINCT
  substr(key, 1, instr(key, '.') - 1)  AS slug,
  substr(key, 1, instr(key, '.') - 1)  AS name,
  'section',
  'active'
FROM legacy_content
WHERE instr(key, '.') > 0
  AND key NOT LIKE 'page.%';

-- 6. Move field values for every non-page legacy row.
--    field_key = everything after the first dot in the legacy key.
INSERT OR IGNORE INTO content_block_fields
  (block_id, field_key, value, type, updated_at, updated_by)
SELECT
  cb.id,
  substr(lc.key, instr(lc.key, '.') + 1),
  lc.value,
  lc.type,
  lc.updated_at,
  lc.updated_by
FROM legacy_content lc
JOIN content_blocks cb
  ON cb.slug = substr(lc.key, 1, instr(lc.key, '.') - 1)
WHERE instr(lc.key, '.') > 0
  AND lc.key NOT LIKE 'page.%';

-- 7. Move page-scoped rows ("page.<slug>.<field>") to page_fields.
--    Joins legacy_content to pages by matching the slug substring.
INSERT OR IGNORE INTO page_fields
  (page_id, field_key, value, type, updated_at, updated_by)
SELECT
  p.id,
  substr(lc.key, length('page.' || p.slug || '.') + 1),
  lc.value,
  lc.type,
  lc.updated_at,
  lc.updated_by
FROM legacy_content lc
JOIN pages p
  ON lc.key LIKE 'page.' || p.slug || '.%';
