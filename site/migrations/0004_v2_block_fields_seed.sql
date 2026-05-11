-- site/migrations/0004_v2_block_fields_seed.sql — fresh-install fix (v2 Stage 6.1).
--
-- Why this exists: on a fresh install the migration runner executes ALL
-- core migrations before ALL site migrations. Stage 3's core migration
-- (0008_blocks_split.sql) renames v1 `content_blocks` to `legacy_content`
-- and immediately tries to populate `content_blocks` (defs) and
-- `content_block_fields` by parsing rows in `legacy_content`. But
-- `legacy_content` is empty at that point because site/0001_seed.sql
-- hasn't run yet. The result is: tables exist but content_block_fields
-- has zero rows even after migration.
--
-- This site migration runs AFTER site/0001_seed.sql + site/0003_form_seed.sql
-- have populated legacy_content. It replays the same INSERT FROM SELECT
-- logic from 0008_blocks_split.sql to populate content_blocks (defs) +
-- content_block_fields. INSERT OR IGNORE makes it safe to run on
-- existing installs that already have block fields populated.
--
-- After this migration:
--   - legacy_content has the v1 seed rows (read-fallback for c())
--   - content_blocks has one row per distinct section prefix (hero, faq, …)
--   - content_block_fields has the per-block field values that the new
--     admin /admin/blocks.php UI displays
--   - c('hero.headline') resolves via the new tables (not the legacy fallback)

-- Block definitions from distinct section prefixes
INSERT OR IGNORE INTO content_blocks (slug, name, category, status)
SELECT DISTINCT
  substr(key, 1, instr(key, '.') - 1)  AS slug,
  substr(key, 1, instr(key, '.') - 1)  AS name,
  'section',
  'active'
FROM legacy_content
WHERE instr(key, '.') > 0
  AND key NOT LIKE 'page.%';

-- Field values for every non-page legacy row
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

-- Page-scoped rows ("page.<slug>.<field>") → page_fields
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
