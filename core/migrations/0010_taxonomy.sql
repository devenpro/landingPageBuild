-- core/migrations/0010_taxonomy.sql — hierarchical taxonomies + Location Services (v2 Stage 5).
--
-- Stage 5 adds two related concepts:
--
--   1. Taxonomies (this migration's primary purpose): hierarchical term
--      registries that classify content entries internally — SEO topical
--      authority + local relevance. Two builtin taxonomies seeded:
--        - locations (hierarchical: country → state → city → area)
--        - service_categories (hierarchical: industry → sub-niche)
--
--   2. Location Services (Stage 4 deferred this to Stage 5): a routable
--      content type at /services/{service_slug}/{location_slug}. Each
--      entry references a Services entry and a location taxonomy term.
--      Composite slug stored as "{service_slug}/{location_slug}" in
--      content_entries.slug so the existing routing infrastructure can
--      resolve it (with Stage 5's multi-placeholder pattern support).
--
-- Taxonomies are NOT auto-rendered as public archive pages. They're
-- purely an internal classification — page generation, breadcrumbs,
-- and routing reference them, but there's no /taxonomy/<slug>/<term>
-- archive landing page in this stage (or in v2 at all without explicit
-- admin opt-in).

CREATE TABLE IF NOT EXISTS taxonomies (
  id                       INTEGER PRIMARY KEY AUTOINCREMENT,
  slug                     TEXT NOT NULL UNIQUE,
  name                     TEXT NOT NULL,
  description              TEXT,
  is_hierarchical          INTEGER NOT NULL DEFAULT 0,
  applies_to_type_ids_json TEXT,                            -- JSON array of content_types.id this taxonomy applies to; NULL = all routable
  is_builtin               INTEGER NOT NULL DEFAULT 0,
  sort_order               INTEGER NOT NULL DEFAULT 100,
  created_at               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS taxonomy_terms (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  taxonomy_id  INTEGER NOT NULL REFERENCES taxonomies(id) ON DELETE CASCADE,
  parent_id    INTEGER REFERENCES taxonomy_terms(id) ON DELETE CASCADE,
  slug         TEXT NOT NULL,
  name         TEXT NOT NULL,
  description  TEXT,
  position     INTEGER NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(taxonomy_id, slug)
);
CREATE INDEX IF NOT EXISTS idx_taxonomy_terms_parent ON taxonomy_terms(parent_id);
CREATE INDEX IF NOT EXISTS idx_taxonomy_terms_taxonomy ON taxonomy_terms(taxonomy_id);

-- Many-to-many: content_entries ↔ taxonomy_terms. type_id denormalised
-- so we can fast-filter "all Services in Mumbai" without re-joining
-- through content_entries.
CREATE TABLE IF NOT EXISTS entry_taxonomy_terms (
  entry_id INTEGER NOT NULL REFERENCES content_entries(id) ON DELETE CASCADE,
  term_id  INTEGER NOT NULL REFERENCES taxonomy_terms(id) ON DELETE CASCADE,
  type_id  INTEGER NOT NULL,
  PRIMARY KEY (entry_id, term_id)
);
CREATE INDEX IF NOT EXISTS idx_entry_terms_term ON entry_taxonomy_terms(term_id);
CREATE INDEX IF NOT EXISTS idx_entry_terms_type ON entry_taxonomy_terms(type_id, term_id);

-- Seed: two builtin taxonomies. Empty trees — admin populates via UI.

INSERT OR IGNORE INTO taxonomies
  (slug, name, description, is_hierarchical, applies_to_type_ids_json, is_builtin, sort_order) VALUES
  ('locations',         'Locations',
    'Geographic hierarchy: country → state → city → area. Drives location-service URLs and city-page SEO.',
    1, NULL, 1, 10),
  ('service_categories','Service Categories',
    'Industry / sub-niche groupings. Used for internal organisation and topical authority signals.',
    1, NULL, 1, 20);

-- Seed: Location Services content type (deferred from Stage 4).
-- Multi-placeholder route pattern; the routing layer (content_resolve_route)
-- knows to compose the matched captures into an entry.slug like
-- "{service_slug}/{location_slug}".
INSERT OR IGNORE INTO content_types
  (slug, name, description, is_routable, route_pattern, detail_partial, schema_json, is_builtin, sort_order) VALUES
  ('location_services', 'Location Services',
    'A Service × Location intersection. Routes to /services/{service_slug}/{location_slug}. Inherits service content with optional per-location overrides.',
    1, '/services/{service_slug}/{location_slug}', 'location_service_detail',
    '{"fields":[{"key":"service_entry_id","label":"Service entry id","type":"number","required":true},{"key":"location_term_id","label":"Location term id","type":"number","required":true},{"key":"city","label":"City / locality (display)","type":"text"},{"key":"region","label":"State / region","type":"text"},{"key":"intro","label":"Location-specific intro","type":"textarea"},{"key":"address","label":"Office / service address","type":"textarea"},{"key":"phone","label":"Local phone","type":"text"},{"key":"map_embed_url","label":"Map embed URL (optional)","type":"text"},{"key":"local_testimonials","label":"Local testimonial entry IDs (comma-separated)","type":"text"}]}',
    1, 40);
