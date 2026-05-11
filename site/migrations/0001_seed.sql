-- 0003_seed.sql — initial content for every editable key on the page.
-- Re-running is safe: ON CONFLICT(key) DO NOTHING preserves any later edits
-- the admin has made through the inline editor (Phase 5+).

-- =========================================================================
-- SEO
-- =========================================================================
INSERT INTO legacy_content (key, value, type) VALUES
  ('seo.title', 'Go Ultra AI — content for every client, in a fraction of the time', 'seo'),
  ('seo.description', 'AI-powered content strategy and production for freelancers and small agencies. Plan calendars, generate prompts, and ship posts across multiple clients without losing the thinking that makes content actually good.', 'seo'),
  ('seo.og_image', '/og-image.jpg', 'seo')
ON CONFLICT(key) DO NOTHING;

-- =========================================================================
-- Navbar
-- =========================================================================
INSERT INTO legacy_content (key, value, type) VALUES
  ('nav.brand', 'Go Ultra AI', 'text'),
  ('nav.features_label', 'Features', 'text'),
  ('nav.how_label', 'How it works', 'text'),
  ('nav.faq_label', 'FAQ', 'text'),
  ('nav.cta_label', 'Get early access', 'text')
ON CONFLICT(key) DO NOTHING;

-- =========================================================================
-- Hero
-- =========================================================================
INSERT INTO legacy_content (key, value, type) VALUES
  ('hero.eyebrow', 'For freelancers, solo marketers & small agencies', 'text'),
  ('hero.headline', 'Plan a week of social content for every client. In an hour.', 'text'),
  ('hero.subheadline', 'Go Ultra AI is the brain behind your social calendar — content pillars, post ideas, image and video prompts, captions, and ready-to-handoff briefs across every brand you manage. Without losing the thinking that makes content actually good.', 'text'),
  ('hero.cta_label', 'Get early access', 'text'),
  ('hero.cta_secondary_label', 'See how it works', 'text'),
  ('hero.image', '/assets/placeholders/hero-placeholder.svg', 'image'),
  ('hero.image_alt', 'Go Ultra AI dashboard preview showing a multi-client content calendar', 'text')
ON CONFLICT(key) DO NOTHING;

-- =========================================================================
-- Social proof
-- =========================================================================
INSERT INTO legacy_content (key, value, type) VALUES
  ('social_proof.text', 'Built by the team behind CreatiSoul — production partner to brands across India', 'text')
ON CONFLICT(key) DO NOTHING;

-- =========================================================================
-- Features (5 cards)
-- =========================================================================
INSERT INTO legacy_content (key, value, type) VALUES
  ('features.heading', 'Everything you need to run social content for many clients', 'text'),
  ('features.subheading', 'Stop juggling sheets, Notion docs, and ten different AI tabs. One workspace, every client, every step.', 'text'),

  ('feature.1.icon', 'calendar-days', 'icon'),
  ('feature.1.title', 'Multi-client calendar', 'text'),
  ('feature.1.body', 'One calendar across every client. Content pillars, campaign planning, and posting cadence templates by industry.', 'text'),

  ('feature.2.icon', 'sparkles', 'icon'),
  ('feature.2.title', 'AI prompts for visuals', 'text'),
  ('feature.2.body', 'Image prompts for Midjourney, Imagen, Nano Banana Pro. Video prompts for VEO 3.1, Runway, Seedance — including first-frame and last-frame workflows.', 'text'),

  ('feature.3.icon', 'users', 'icon'),
  ('feature.3.title', 'Per-client workspaces', 'text'),
  ('feature.3.body', 'Separate brand kits, scoped asset libraries, and one-click switching between every client you manage.', 'text'),

  ('feature.4.icon', 'message-square-text', 'icon'),
  ('feature.4.title', 'Captions, hooks, hashtags', 'text'),
  ('feature.4.body', 'Caption variations, scroll-stopping hooks, CTAs, hashtags, carousel and Reel scripts — in English or Hinglish.', 'text'),

  ('feature.5.icon', 'file-output', 'icon'),
  ('feature.5.title', 'Export-ready briefs', 'text'),
  ('feature.5.body', 'Hand off to designers, editors, or yourself with a clean brief — prompts, references, copy, and version history per post.', 'text'),

  ('feature.6.icon', 'layout-template', 'icon'),
  ('feature.6.title', 'Cadence templates', 'text'),
  ('feature.6.body', 'Battle-tested posting schedules per industry. Drop one in, customise, and you''re weeks ahead.', 'text')
ON CONFLICT(key) DO NOTHING;

-- =========================================================================
-- How it works (4 steps)
-- =========================================================================
INSERT INTO legacy_content (key, value, type) VALUES
  ('how.heading', 'Set up once. Ship every week.', 'text'),
  ('how.subheading', 'Four steps from blank calendar to client-ready brief.', 'text'),

  ('step.1.icon', 'building-2', 'icon'),
  ('step.1.title', 'Add a client', 'text'),
  ('step.1.body', 'Drop in their brand kit, voice notes, audience, and cadence. Two minutes per client.', 'text'),

  ('step.2.icon', 'calendar-plus', 'icon'),
  ('step.2.title', 'Plan the calendar', 'text'),
  ('step.2.body', 'Pick a cadence template or start blank. AI suggests pillars, themes, and post slots.', 'text'),

  ('step.3.icon', 'wand-sparkles', 'icon'),
  ('step.3.title', 'Generate prompts', 'text'),
  ('step.3.body', 'Click a post, get image and video prompts tuned to the brand and the right tool — Midjourney, VEO, Seedance.', 'text'),

  ('step.4.icon', 'send', 'icon'),
  ('step.4.title', 'Export the brief', 'text'),
  ('step.4.body', 'One-click hand-off to your designer, editor, or yourself. Versioned, neat, ready to ship.', 'text')
ON CONFLICT(key) DO NOTHING;

-- =========================================================================
-- Use cases
-- =========================================================================
INSERT INTO legacy_content (key, value, type) VALUES
  ('use_cases.heading', 'Built for the way you actually work', 'text'),

  ('use_case.1.icon', 'user', 'icon'),
  ('use_case.1.title', 'Solo freelancer', 'text'),
  ('use_case.1.body', 'You''re the strategist, copywriter, and designer. Go Ultra AI gives you a system that scales past the 3-client ceiling.', 'text'),

  ('use_case.2.icon', 'briefcase', 'icon'),
  ('use_case.2.title', 'Small agency owner', 'text'),
  ('use_case.2.body', 'Your team is fast but the planning chaos eats the margin. One workspace, every client, fewer "what are we posting tomorrow" Slacks.', 'text'),

  ('use_case.3.icon', 'building', 'icon'),
  ('use_case.3.title', 'In-house marketer', 'text'),
  ('use_case.3.body', 'You manage one brand but ten content pillars. Stop reinventing prompts every time and start shipping.', 'text')
ON CONFLICT(key) DO NOTHING;

-- =========================================================================
-- Product demo
-- =========================================================================
INSERT INTO legacy_content (key, value, type) VALUES
  ('demo.heading', 'See it in action', 'text'),
  ('demo.subheading', 'A 30-second look at how a week of multi-client content gets planned, prompted, and packaged.', 'text'),
  ('demo.image', '/assets/placeholders/demo-placeholder.svg', 'image'),
  ('demo.image_alt', 'Product demo screenshot showing the prompt generation panel', 'text')
ON CONFLICT(key) DO NOTHING;

-- =========================================================================
-- FAQ
-- =========================================================================
INSERT INTO legacy_content (key, value, type) VALUES
  ('faq.heading', 'Questions you''re probably asking', 'text'),

  ('faq.1.q', 'Is this just another ChatGPT wrapper?', 'text'),
  ('faq.1.a', 'No. The AI part matters, but the value is the workflow built around managing many clients — calendars, brand kits, prompt templates, version history, and export. ChatGPT alone doesn''t structure your week.', 'text'),

  ('faq.2.q', 'Which AI tools do the prompts work with?', 'text'),
  ('faq.2.a', 'Image: Midjourney, Imagen, Nano Banana Pro, plus generic models. Video: VEO 3.1, Runway, Seedance. We add new ones as they ship.', 'text'),

  ('faq.3.q', 'Do you post directly to Instagram, LinkedIn, etc.?', 'text'),
  ('faq.3.a', 'Not yet — and not the headline feature. Go Ultra AI is for planning and producing content. Schedulers like Buffer or Later still do the actual posting.', 'text'),

  ('faq.4.q', 'Is there a free trial?', 'text'),
  ('faq.4.a', 'Early-access spots are free while we onboard a small batch of users. Get on the list and we''ll reach out.', 'text'),

  ('faq.5.q', 'Will it work for non-English content?', 'text'),
  ('faq.5.a', 'Yes — Hinglish is a first-class option, and other Indian languages plus most major world languages are supported.', 'text'),

  ('faq.6.q', 'Where''s my data stored?', 'text'),
  ('faq.6.a', 'Your client info, prompts, and assets stay in your workspace. We don''t train models on your content.', 'text')
ON CONFLICT(key) DO NOTHING;

-- =========================================================================
-- Final CTA
-- =========================================================================
INSERT INTO legacy_content (key, value, type) VALUES
  ('final_cta.heading', 'Get on the early-access list', 'text'),
  ('final_cta.subheading', 'A small batch of freelancers and agencies are getting in first. Tell us a bit about how you work and we''ll be in touch.', 'text')
ON CONFLICT(key) DO NOTHING;

-- =========================================================================
-- Footer
-- =========================================================================
INSERT INTO legacy_content (key, value, type) VALUES
  ('footer.tagline', 'Plan, prompt, and ship social content for every client.', 'text'),
  ('footer.parent_company', 'A CreatiSoul LLP product', 'text'),
  ('footer.copyright', '© 2026 CreatiSoul LLP. All rights reserved.', 'text'),
  ('footer.privacy_label', 'Privacy', 'text'),
  ('footer.terms_label', 'Terms', 'text'),
  ('footer.contact_label', 'Contact', 'text'),
  ('footer.admin_label', 'Admin', 'text')
ON CONFLICT(key) DO NOTHING;
