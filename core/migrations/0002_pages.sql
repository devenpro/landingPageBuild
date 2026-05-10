-- core/migrations/0002_pages.sql — pages table for hybrid multi-page routing.
--
-- File-based pages: is_file_based=1, file_path points at site/pages/<file>
-- Data-driven pages: is_file_based=0, sections_json holds an ordered list of
--                    section refs ({"section":"hero","content_prefix":"page.foo.hero"}, …)
--                    (data-driven render path lands in Phase 8)
-- Status: 'draft' | 'published' | 'archived'. Router only serves 'published'.

CREATE TABLE IF NOT EXISTS pages (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  slug TEXT UNIQUE NOT NULL,
  title TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'draft' CHECK(status IN ('draft','published','archived')),
  is_file_based INTEGER NOT NULL DEFAULT 0,
  file_path TEXT,
  layout TEXT NOT NULL DEFAULT 'default',
  sections_json TEXT,
  meta_json TEXT,
  seo_title TEXT,
  seo_description TEXT,
  seo_og_image TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_pages_status_slug ON pages(status, slug);
