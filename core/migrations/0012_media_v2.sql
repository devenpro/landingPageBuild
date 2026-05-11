-- core/migrations/0012_media_v2.sql — image processing pipeline (v2 Stage 7).
--
-- v1 stored the raw upload and nothing else: no derivative sizes, no
-- WebP, no alt text on the row (alt lived in content_blocks values).
-- Stage 7 adds:
--   - alt_text + caption on media_assets (so a single edit propagates
--     wherever the image is referenced)
--   - original_width + original_height (captured at upload time)
--   - processed + processing_error flags (so admin can see + retry
--     anything that didn't generate variants)
--   - media_variants table — one row per (asset, preset, mime_type).
--     'preset_name' is the human label ('w320', 'w640', …, 'webp-w320', …).
--     path is repo-relative, e.g. site/public/uploads/variants/12/w320.jpg
--
-- Preset widths and WebP quality live in site_settings (Stage 1) so the
-- admin can tune them per site without code changes. Defaults: 320 / 640 /
-- 960 / 1280 / 1920 / 2560 max-width and WebP quality 82.

ALTER TABLE media_assets ADD COLUMN alt_text TEXT;
ALTER TABLE media_assets ADD COLUMN caption TEXT;
ALTER TABLE media_assets ADD COLUMN original_width  INTEGER;
ALTER TABLE media_assets ADD COLUMN original_height INTEGER;
ALTER TABLE media_assets ADD COLUMN processed       INTEGER NOT NULL DEFAULT 0;
ALTER TABLE media_assets ADD COLUMN processing_error TEXT;

CREATE TABLE IF NOT EXISTS media_variants (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  media_id      INTEGER NOT NULL REFERENCES media_assets(id) ON DELETE CASCADE,
  preset_name   TEXT NOT NULL,                          -- 'w320', 'webp-w640', …
  width         INTEGER NOT NULL,
  height        INTEGER NOT NULL,
  mime_type     TEXT NOT NULL,
  path          TEXT NOT NULL,                          -- repo-relative, e.g. 'site/public/uploads/variants/12/w320.jpg'
  size_bytes    INTEGER NOT NULL,
  generated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(media_id, preset_name)
);
CREATE INDEX IF NOT EXISTS idx_media_variants_media ON media_variants(media_id);

-- Settings rows for media preset config. Read by core/lib/media/processor.php.
INSERT OR IGNORE INTO site_settings
  (key, value_type, group_name, label, description, default_value) VALUES
  ('media_preset_widths', 'json',    'media', 'Image variant widths (px)',
    'Comma-separated max-widths to generate as variants. Each upload produces one variant per width (capped at the original size).',
    '[320, 640, 960, 1280, 1920, 2560]'),
  ('media_webp_enabled',  'boolean', 'media', 'Generate WebP variants',
    'When on, each width also gets a .webp variant alongside the JPEG/PNG. WebP is typically 30-50% smaller.',
    '1'),
  ('media_webp_quality',  'number',  'media', 'WebP quality (1-100)',
    'Lossy quality for WebP variants. 82 is the sweet spot for marketing photos.',
    '82'),
  ('media_jpeg_quality',  'number',  'media', 'JPEG quality (1-100)',
    'Lossy quality for re-encoded JPEG variants when the original is JPEG/PNG.',
    '85');
