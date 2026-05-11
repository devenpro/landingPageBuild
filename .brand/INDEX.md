# Brand Context Library

> Source of truth: the admin panel at `/admin/brand.php`. This directory is a mirror so Claude Code (and any other repo-aware tool) can read and edit the same content. After editing on disk, open `/admin/brand-sync.php` to merge changes back into the DB.

## Brand Voice — `brand_voice`

Tone, vocabulary, and writing style. The single biggest lever on every AI-generated word.

- [`core-tone.md`](brand_voice/core-tone.md) — Core tone

## Brand Facts — `brand_facts`

Verifiable claims: founding date, certifications, awards, headquarters, team size, named customers. AI is forbidden from inventing facts not on this list.

- [`company.md`](brand_facts/company.md) — Company facts

## Audience — `audience`

Who the site is for: roles, seniority, jobs-to-be-done, objections, vocabulary they use.

- [`primary.md`](audience/primary.md) — Primary audience

## Services — `services`

What the business sells, with one-line summaries. Service detail pages and ad landing pages pull from this.

- [`service-catalog.md`](services/service-catalog.md) — Service catalog

## Design Guide — `design_guide`

Brand colours, typography, layout patterns, image style. Inline editor + AI-generated pages respect these.

- [`visual-style.md`](design_guide/visual-style.md) — Visual style

## Page Guide — `page_guide`

How AI should structure new pages: section order, what to lead with, calls-to-action, length expectations.

- [`how-ai-builds-pages.md`](page_guide/how-ai-builds-pages.md) — How AI should build pages

## SEO — `seo`

Target keywords, geographic focus, competitive landscape, internal linking conventions.

- [`core-seo.md`](seo/core-seo.md) — Core SEO

## Social — `social`

External links and handles: LinkedIn, X, YouTube, GitHub, etc. Used by the chatbot when asked "where can I follow you".

- [`external-links.md`](social/external-links.md) — External links
