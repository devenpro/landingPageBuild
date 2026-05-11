-- core/migrations/0014_bootstrap.sql — site bootstrap state (v2 Stage 10).
--
-- v1 and early-v2 left site setup as a manual checklist: edit .env,
-- run migrate.php, run seed_admin.php, log in, then dig through
-- /admin/settings, /admin/brand, /admin/ai-keys, /admin/ai etc.
-- to actually configure a clone for production. Stage 10 ships a
-- guided wizard at /admin/bootstrap.php that walks an admin through
-- the same steps in order; this migration just adds the two settings
-- rows that track wizard state.
--
-- bootstrap_completed   — flips to '1' on the wizard's Done step
-- bootstrap_started_at  — timestamp the wizard was first opened
--                          (used by the dashboard banner to nag only
--                           after the admin has acknowledged the wizard
--                           once; left NULL until first visit)
--
-- Both live in the 'setup' group so they cluster on /admin/settings.php
-- under their own tab.

INSERT OR IGNORE INTO site_settings
  (key, value_type, group_name, label, description, default_value) VALUES
  ('bootstrap_completed',  'boolean', 'setup', 'Bootstrap wizard completed',
    'Set to 1 when the /admin/bootstrap.php wizard has been finished. The dashboard hides its setup banner once this flips.',
    '0'),
  ('bootstrap_started_at', 'string',  'setup', 'Bootstrap wizard first opened',
    'UTC timestamp recorded the first time an admin opens /admin/bootstrap.php. Useful for audit + telemetry.',
    '');
