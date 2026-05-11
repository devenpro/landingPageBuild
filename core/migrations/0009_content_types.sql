-- core/migrations/0009_content_types.sql — content types + entries (v2 Stage 4).
--
-- Replaces v1's "everything is a page" model. v1 had a single `pages` table
-- and that was it — testimonials lived as content_blocks rows, services as
-- adhoc pages, ad landing pages had no first-class home at all.
--
-- v2 introduces:
--   content_types     registry of types (Testimonials, Services, Ad LPs, …)
--   content_entries   instances of those types (one row per testimonial,
--                     one per service, one per ad LP)
--
-- Each type's data_json is type-specific (a testimonial's quote vs a
-- service's price). Routable types render via the type's detail_partial
-- (a file in site/sections/). Pages still own their slug namespace — page
-- lookups win on slug collision with a routable type.
--
-- Location Services depend on Stage 5 (taxonomy) and ship with that
-- migration. Stage 4 seeds three types: Testimonials (non-routable),
-- Services (routable /services/{slug}), Ad Landing Pages (routable /lp/{slug}).

CREATE TABLE IF NOT EXISTS content_types (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  slug            TEXT NOT NULL UNIQUE,
  name            TEXT NOT NULL,
  description     TEXT,
  is_routable     INTEGER NOT NULL DEFAULT 0,
  route_pattern   TEXT,                   -- e.g. '/services/{slug}'; NULL when non-routable
  detail_partial  TEXT,                   -- partial name (no .php) under site/sections/
  list_partial    TEXT,                   -- optional listing/index partial
  schema_json     TEXT,                   -- field schema for data_json (informational; admin UI uses this)
  is_builtin      INTEGER NOT NULL DEFAULT 0,
  status          TEXT NOT NULL DEFAULT 'active'
                       CHECK(status IN ('active','draft','archived')),
  sort_order      INTEGER NOT NULL DEFAULT 100,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS content_entries (
  id               INTEGER PRIMARY KEY AUTOINCREMENT,
  type_id          INTEGER NOT NULL REFERENCES content_types(id) ON DELETE CASCADE,
  slug             TEXT,                  -- nullable for non-routable types
  title            TEXT NOT NULL,
  data_json        TEXT NOT NULL DEFAULT '{}',
  seo_title        TEXT,
  seo_description  TEXT,
  seo_og_image     TEXT,
  robots           TEXT,                  -- e.g. 'noindex,nofollow' (Ad LPs default to this)
  status           TEXT NOT NULL DEFAULT 'published'
                        CHECK(status IN ('draft','published','archived')),
  position         INTEGER NOT NULL DEFAULT 0,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_by       INTEGER REFERENCES admin_users(id) ON DELETE SET NULL,
  UNIQUE(type_id, slug)
);
CREATE INDEX IF NOT EXISTS idx_content_entries_type_status ON content_entries(type_id, status);

-- Seed built-in types

INSERT OR IGNORE INTO content_types
  (slug, name, description, is_routable, route_pattern, detail_partial, schema_json, is_builtin, sort_order) VALUES
  ('testimonials',  'Testimonials',
    'Customer / client quotes. Rendered by the testimonials carousel block — non-routable.',
    0, NULL, NULL,
    '{"fields":[{"key":"name","label":"Name","type":"text","required":true},{"key":"role","label":"Role / title","type":"text"},{"key":"company","label":"Company","type":"text"},{"key":"quote","label":"Quote","type":"textarea","required":true},{"key":"rating","label":"Rating (1-5)","type":"number"},{"key":"photo_url","label":"Photo URL","type":"text"}]}',
    1, 10),

  ('services',     'Services',
    'Service detail pages. Each entry routes to /services/{slug}.',
    1, '/services/{slug}', 'service_detail',
    '{"fields":[{"key":"short_desc","label":"Short description","type":"textarea"},{"key":"long_desc","label":"Long description","type":"textarea"},{"key":"icon","label":"Lucide icon","type":"text"},{"key":"starting_price","label":"Starting price","type":"text"},{"key":"primary_cta_label","label":"Primary CTA label","type":"text","default":"Get started"},{"key":"primary_cta_url","label":"Primary CTA URL","type":"text"},{"key":"features_list","label":"Features (one per line)","type":"textarea"},{"key":"faqs_list","label":"FAQs (JSON array of {q,a})","type":"textarea"}]}',
    1, 20),

  ('ad_landing_pages', 'Ad Landing Pages',
    'Paid-traffic landing pages (Meta / Google / LinkedIn / TikTok). Routes to /lp/{slug}. Defaults to noindex,nofollow so they do not compete with organic SEO.',
    1, '/lp/{slug}', 'ad_lp_detail',
    '{"fields":[{"key":"campaign_id","label":"Campaign ID","type":"text"},{"key":"source","label":"Source (meta/google/linkedin/tiktok/other)","type":"text"},{"key":"utm_campaign","label":"UTM campaign","type":"text"},{"key":"utm_source","label":"UTM source","type":"text"},{"key":"utm_medium","label":"UTM medium","type":"text"},{"key":"utm_content","label":"UTM content","type":"text"},{"key":"meta_pixel_id","label":"Meta Pixel ID","type":"text"},{"key":"google_tag_id","label":"Google Tag ID","type":"text"},{"key":"conversion_event","label":"Conversion event name","type":"text"},{"key":"eyebrow","label":"Eyebrow","type":"text"},{"key":"headline","label":"Headline","type":"text","required":true},{"key":"subheadline","label":"Subheadline","type":"textarea"},{"key":"hero_image_url","label":"Hero image URL","type":"text"},{"key":"primary_cta_label","label":"Primary CTA label","type":"text","default":"Get started"},{"key":"primary_cta_action","label":"Primary CTA action (form|url|phone)","type":"text","default":"form"},{"key":"primary_cta_target","label":"Primary CTA target (URL/phone/form id)","type":"text"},{"key":"secondary_cta_label","label":"Secondary CTA label","type":"text"},{"key":"benefits_list","label":"Benefits (one per line)","type":"textarea"},{"key":"thank_you_redirect_url","label":"Thank-you redirect URL","type":"text"}]}',
    1, 30);

-- Default robots for Ad Landing Pages: noindex,nofollow. Applied via a trigger
-- so new entries get the right default even if the admin form doesn't set it.
CREATE TRIGGER IF NOT EXISTS trg_ad_lp_default_robots
AFTER INSERT ON content_entries
FOR EACH ROW
WHEN NEW.robots IS NULL
  AND (SELECT slug FROM content_types WHERE id = NEW.type_id) = 'ad_landing_pages'
BEGIN
  UPDATE content_entries SET robots = 'noindex,nofollow' WHERE id = NEW.id;
END;
