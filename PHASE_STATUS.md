# Phase status — live tracker

Updated: 2026-05-10. Update this file in the same PR that changes phase status, so the tracker stays honest.

Legend: ✅ merged · 🟡 PR open, awaiting merge · ⏸️ closed/superseded · ⏳ pending · 🚧 in flight

| # | Phase | Status | PR | Branch |
|---|---|---|---|---|
| 1 | Scaffolding | ✅ | [#1](https://github.com/devenpro/landingPageBuild/pull/1) | merged into `main` |
| 2 | Static landing page (single-tenant) | ⏸️ | [#2](https://github.com/devenpro/landingPageBuild/pull/2) — *superseded by #3* | `phase-2-static-sections` |
| 3 | Architecture reset — multi-site `core`+`site` + workflow modes | ✅ | [#3](https://github.com/devenpro/landingPageBuild/pull/3) | merged into `main` via [#11](https://github.com/devenpro/landingPageBuild/pull/11) |
| 4 | Pages table + hybrid routing + Home as file-based page | ✅ | [#4](https://github.com/devenpro/landingPageBuild/pull/4) | merged into `main` via [#11](https://github.com/devenpro/landingPageBuild/pull/11) |
| 5 | Public waitlist form + optional outbound webhook | ✅ | [#5](https://github.com/devenpro/landingPageBuild/pull/5) | merged into `main` via [#11](https://github.com/devenpro/landingPageBuild/pull/11) |
| 6 | Admin auth — login, logout, brute-force lockout | ✅ | [#6](https://github.com/devenpro/landingPageBuild/pull/6) | merged into `main` via [#11](https://github.com/devenpro/landingPageBuild/pull/11) |
| 7 | Admin panel base + content_blocks editor + forms inbox | ✅ | [#7](https://github.com/devenpro/landingPageBuild/pull/7) | merged into `main` via [#11](https://github.com/devenpro/landingPageBuild/pull/11) |
| 7.5 | Documentation refresh (BUILD_BRIEF v4, AGENTS, PHASE_STATUS, README, AI_GUIDE) | ✅ | [#8](https://github.com/devenpro/landingPageBuild/pull/8) | merged into `main` via [#11](https://github.com/devenpro/landingPageBuild/pull/11) |
| 8 | Pages CRUD UI + data-driven page renderer | ✅ | [#9](https://github.com/devenpro/landingPageBuild/pull/9) | merged into `main` via [#11](https://github.com/devenpro/landingPageBuild/pull/11) |
| 9 | Inline editing on the public page | ✅ | [#10](https://github.com/devenpro/landingPageBuild/pull/10) | merged into `main` via [#11](https://github.com/devenpro/landingPageBuild/pull/11) |
| 10 | AI key management (BYO + libsodium) + provider abstraction (Gemini, OpenRouter) | 🚧 | this branch | `claude/review-next-tasks-tmBYG` |
| 11 | Admin AI tools — page suggestions, AI page generation | ⏳ | — | — |
| 12 | Media library + uploads UI | ⏳ | — | — |
| 13 | Frontend AI features — chatbot widget + rate limiting | ⏳ | — | — |
| 14 | Polish — motion, SEO, JSON-LD, a11y, Lighthouse, Tailwind compile-down, CSV export, no-JS form fallback | ⏳ | — | — |
| 15 | Launch — DNS, final QA, content entry | ⏳ | — | — |

Phases 3-9 (plus 7.5 docs) were consolidated and landed into `main` via PR [#11](https://github.com/devenpro/landingPageBuild/pull/11) on 2026-05-10. The individual stack PRs (#3-#10) all merged into their bases first; #11 brought the final tip into `main`.

---

## What each shipped phase delivered

### Phase 1 — Scaffolding ✅ ([#1](https://github.com/devenpro/landingPageBuild/pull/1))

- Initial repo layout (then-current: `public_html/` + sibling `includes/`/`scripts/`/`migrations/`/`data/`)
- `.env` loader, PDO wrapper with WAL + foreign keys, helper functions
- Migration runner with `_migrations` tracking table
- Admin user upsert script (`seed_admin.php`)
- Hello-world `index.php` proving the bootstrap chain works
- `.cpanel.yml` deploy hook (later replaced)
- Initial schema migration: `admin_users`, `content_blocks`, `media_assets`, `login_attempts`, `form_submissions`

**Layout was superseded in Phase 3.** The Phase 1 commit is part of `main` history.

### Phase 2 — Static landing page ⏸️ ([#2](https://github.com/devenpro/landingPageBuild/pull/2), closed)

- 10 section partials (navbar, hero, social_proof, features, how_it_works, use_cases, product_demo, faq, final_cta, footer)
- ~85 content keys seeded
- Tailwind Play CDN config + Inter Variable font + Lucide icons
- Self-contained `public_html/` restructure (private dirs moved inside)

**Closed without merging.** All useful content was carried forward into Phase 3 (sections → `site/sections/`, seed → `site/migrations/0001_seed.sql`, layout → `site/layout.php`, assets → `site/public/assets/`). The single-tenant `public_html/`-everything layout was discarded; Phase 3's `core/`+`site/` split is the replacement.

### Phase 3 — Architecture reset 🟡 ([#3](https://github.com/devenpro/landingPageBuild/pull/3))

- **`core/`** (engine): bootstrap, config, db, content, helpers
- **`site/`** (per-site): public/, sections/, layout, migrations
- **`data/`** at repo root (gitignored)
- **`.claude/`** workflow modes (settings.json + check-mode.php hook + slash commands)
- **`MULTI_SITE.md`** documenting fork/pin/upgrade workflow
- **`AI_GUIDE.md`** placeholder for Phases 10-13
- 9 mode-hook scenarios verified

### Phase 4 — Pages + hybrid routing 🟡 ([#4](https://github.com/devenpro/landingPageBuild/pull/4))

- `pages` table (slug, status, is_file_based, file_path, sections_json, meta_json, seo_*)
- `core/lib/pages.php` — `parse_slug()`, `get_page_by_slug()` (memoized), `route_request()`, `render_page()` with `realpath` traversal guard
- Front controller: `site/public/index.php` is now 4 lines (`bootstrap; route_request()`)
- `site/pages/home.php` — extracted home rendering
- `site/pages/404.php` — branded fallback
- Seed: `home` + `404` rows
- `site/layout.php` — `layout_head()` accepts optional `?array $page` for SEO override
- `core/scripts/dev-router.php` — local-dev router for `php -S`

### Phase 5 — Public form + webhook 🟡 ([#5](https://github.com/devenpro/landingPageBuild/pull/5))

- `core/lib/csrf.php` — session-bound tokens (HttpOnly + SameSite=Lax + Secure on HTTPS)
- Bootstrap starts session at top of every HTTP request (CLI skipped) — required for Set-Cookie before output
- Form HTML in `site/sections/final_cta.php` replaces Phase 3 placeholder
- `site/public/api/form.php` — CSRF, honeypot, server-side validation, immediate DB insert, optional webhook (best-effort, status tracked in row)
- `site/public/assets/js/form.js` — fetch-based submit, per-field error display, success card swap
- 18 `form.*` content keys seeded
- Verified: 7 scenarios including UTF-8 round-trip and webhook smoke test against httpbin.org

### Phase 6 — Admin auth 🟡 ([#6](https://github.com/devenpro/landingPageBuild/pull/6))

- `core/lib/auth.php` — login (`session_regenerate_id` to prevent fixation), logout, current_user (8h sliding inactivity), require_login, brute-force lockout (5 fails / 10 min per IP)
- `site/public/admin/login.php` — branded login page
- `site/public/admin/logout.php` — POST-only with CSRF (GET → 405)
- `site/public/admin/dashboard.php` — placeholder confirming auth works
- `site/public/admin/.htaccess` — block dir listing
- Footer admin link: `/admin/login` → `/admin/login.php`
- Bug fixed during build: `auth_is_locked_out` was using local-time `DateTimeImmutable` against UTC SQLite timestamps — switched to `gmdate()`. See AGENTS.md gotchas.
- Verified: 9 scenarios

### Phase 7 — Admin panel base 🟡 ([#7](https://github.com/devenpro/landingPageBuild/pull/7))

- `site/public/admin/_layout.php` — `admin_head()`/`admin_foot()` helpers + sub-nav with active-page highlighting
- Refactored dashboard to use the layout, with stat cards (104 content / 2 pages / 0 forms)
- `site/public/admin/content.php` — full editor for `content_blocks`, grouped by section prefix into 15 collapsible details, live key filter, per-row save with type-specific hints
- `site/public/admin/forms.php` — read-only inbox (200-row cap), status badges, expandable detail rows
- `site/public/api/content.php` — admin-only PATCH endpoint, accepts `{key,value}` or `{changes:[...]}`, reports `missing_keys`
- `site/public/assets/js/admin.js` — per-row save AJAX, Cmd/Ctrl+Enter shortcut, toast notifications, live filter
- Verified: 7 scenarios

### Phase 7.5 — Documentation refresh ✅ ([#8](https://github.com/devenpro/landingPageBuild/pull/8))

- BUILD_BRIEF.md v4 (full rewrite, supersedes v3)
- AGENTS.md (new — operating manual for agents)
- PHASE_STATUS.md (this file)
- README.md phase checklist updated
- AI_GUIDE.md refreshed with current decisions

### Phase 8 — Pages CRUD UI + data-driven render ✅ ([#9](https://github.com/devenpro/landingPageBuild/pull/9))

- `/admin/pages.php` (list + create + edit + status toggle)
- `/api/pages.php` (POST/PATCH/DELETE with auth + CSRF)
- Data-driven renderer (`render_page()`'s `is_file_based=0` branch) walks `sections_json` and includes the right partials with page-scoped content keys
- Page-scoped content key convention: `page.<slug>.<section>.<field>`

### Phase 9 — Inline editing on public page ✅ ([#10](https://github.com/devenpro/landingPageBuild/pull/10))

- `editor.js` loaded only when admin session is active
- Click-to-edit on `[data-edit]` markers (already wrapped in every section)
- EditModeBar: Save (batched), Discard, Logout, unsaved-changes counter
- Reuses `/api/content.php` PATCH endpoint with `{changes:[...]}`
- Hover/active visual states from `site/public/assets/css/styles.css` `.edit-mode` rules

---

## What each pending phase will deliver

### Phase 10 — AI keys + provider abstraction 🚧 (in flight)

- `core/lib/crypto.php` — libsodium secretbox wrapper
- `core/lib/ai/keys.php` — store/list/decrypt provider keys
- `core/lib/ai/client.php` — provider-agnostic facade
- `core/lib/ai/providers/{gemini,openrouter}.php` — v1 adapters
- `core/lib/ai/log.php` — every call logged to `ai_calls`
- `core/lib/ai/ratelimit.php` — per-IP + global daily token cap
- Schema migrations: `ai_provider_keys`, `ai_calls`
- `/admin/settings.php` UI for adding/managing keys

### Phase 11 — Admin AI tools

- `/admin/ai.php` — UI for "suggest pages" and "generate page from brief"
- `core/lib/ai/prompts/{suggest_pages,generate_page}.php` — prompt templates
- Generated pages land as `pages` rows with `status='draft'`, `meta_json` recording the generation source

### Phase 12 — Media library + upload UI

- `/admin/media.php` — gallery with thumbnails, delete
- `/api/upload.php` — already partially designed; finalises MIME whitelist, finfo verification, sanitised filenames, size caps
- Image picker integrated into content editor (replaces the URL text input for `image`-type blocks)

### Phase 13 — Frontend chatbot

- Floating widget on the public site
- `/api/chat.php` — rate-limited (per-IP + daily global cap), uses admin's stored keys
- `core/lib/ai/prompts/chat.php` — system prompt with sanitisation
- Optional persistence in `ai_chat_messages` (toggleable via `.env`)

### Phase 14 — Polish

- Hand-built `styles.css` replacing Tailwind Play CDN
- JSON-LD `Organization`/`WebSite`/`Product`
- Sitemap.xml regenerated from `pages` table
- `prefers-reduced-motion` overrides
- WCAG AA pass
- Lighthouse ≥ 95 mobile
- CSV export of form submissions
- Pagination + search on Forms inbox
- No-JS HTML success page for the waitlist form
- Webhook retry queue for transient failures
- Per-IP rate limiting on form POST

### Phase 15 — Launch

- DNS for production subdomain
- Final QA pass
- Content entry through admin
- Tag `core/VERSION` as `v1.0.0` in git
- Deploy + smoke test on cPanel
