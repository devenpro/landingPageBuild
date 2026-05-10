-- site/migrations/0003_form_seed.sql — content keys for the waitlist form.
-- Reuses existing final_cta.heading / final_cta.subheading from 0001_seed.sql;
-- adds field labels, dropdown options (JSON lists), and success-state copy.
-- ON CONFLICT(key) DO NOTHING so admin edits to these survive re-seeds.

INSERT INTO content_blocks (key, value, type) VALUES
  ('form.full_name_label',       'Full name',                                            'text'),
  ('form.email_label',           'Email',                                                'text'),
  ('form.phone_label',           'WhatsApp / Phone',                                     'text'),
  ('form.phone_placeholder',     '+91 98xxx xxxxx',                                      'text'),
  ('form.role_label',            'I am a…',                                              'text'),
  ('form.role_placeholder',      'Pick one',                                             'text'),
  ('form.role_options',          '["Freelancer","Agency Owner","In-house Marketer","Other"]', 'list'),
  ('form.clients_label',         'Clients I manage',                                     'text'),
  ('form.clients_placeholder',   'Optional',                                             'text'),
  ('form.clients_options',       '["1–3","4–10","10+","Just exploring"]',      'list'),
  ('form.bottleneck_label',      'Biggest content bottleneck',                           'text'),
  ('form.bottleneck_placeholder','Optional — what slows you down most?',                 'text'),
  ('form.privacy_note',          'We''ll only use this to reach out about early access. No spam.', 'text'),
  ('form.submit_label',          'Get early access',                                     'text'),
  ('form.submitting_label',      'Sending…',                                             'text'),
  ('form.success_heading',       'You''re on the list',                                  'text'),
  ('form.success_body',          'We''ll reach out on WhatsApp with next steps. Usually within 48 hours.', 'text'),
  ('form.error_generic',         'Something went wrong. Please try again, or write to debendra@creatisoul.com.', 'text')
ON CONFLICT(key) DO NOTHING;
