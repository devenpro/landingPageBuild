-- site/migrations/0002_pages_seed.sql — seed file-based pages for this site.
-- Re-running is safe via ON CONFLICT(slug) DO NOTHING — admin edits to
-- title / status / SEO survive future seed runs.

INSERT INTO pages (slug, title, status, is_file_based, file_path, layout,
                   seo_title, seo_description)
VALUES
  ('home', 'Home', 'published', 1, 'home.php', 'default', NULL, NULL),
  ('404',  'Page not found', 'published', 1, '404.php', 'default',
   'Page not found — Go Ultra AI',
   'The page you are looking for could not be found.')
ON CONFLICT(slug) DO NOTHING;
