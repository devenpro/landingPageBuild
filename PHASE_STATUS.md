# Phase status — live tracker

Updated: 2026-05-11. Update this file in the same PR that changes phase status, so the tracker stays honest.

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
| 10 | AI key management (BYO + libsodium) + provider abstraction (HuggingFace, Gemini, OpenRouter) | ✅ | [#12](https://github.com/devenpro/landingPageBuild/pull/12) + [#13](https://github.com/devenpro/landingPageBuild/pull/13) | merged into `main` |
| 11 | Admin AI tools — page suggestions, AI page generation | ✅ | [#14](https://github.com/devenpro/landingPageBuild/pull/14) | merged into `main` |
| 12 | Media library + uploads UI | ✅ | [#15](https://github.com/devenpro/landingPageBuild/pull/15) | merged into `main` |
| 13 | Frontend AI features — chatbot widget + rate limiting | ✅ | [#16](https://github.com/devenpro/landingPageBuild/pull/16) | merged into `main` |
| 14 | Polish — motion, SEO, JSON-LD, a11y, Lighthouse, Tailwind compile-down, CSV export, no-JS form fallback | ✅ (D-3) | [#17](https://github.com/devenpro/landingPageBuild/pull/17) [#18](https://github.com/devenpro/landingPageBuild/pull/18) [#19](https://github.com/devenpro/landingPageBuild/pull/19) [#20](https://github.com/devenpro/landingPageBuild/pull/20) [#21](https://github.com/devenpro/landingPageBuild/pull/21) + this branch | merged D-1 through D-2; D-3 in flight |
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

### Phase 10 — AI keys + provider abstraction ✅ ([#12](https://github.com/devenpro/landingPageBuild/pull/12) + [#13](https://github.com/devenpro/landingPageBuild/pull/13))

- `core/lib/crypto.php` — libsodium secretbox wrapper
- `core/lib/ai/keys.php` — store/list/decrypt provider keys, three-provider whitelist (`huggingface`, `gemini`, `openrouter`)
- `core/lib/ai/client.php` — `ai_chat()` facade + `ai_default_provider()` helper
- `core/lib/ai/providers/{huggingface,gemini,openrouter}.php` — real adapters (HF Router, Gemini 2.5, OpenRouter)
- `core/lib/ai/log.php` — every call logged to `ai_calls`
- `core/lib/ai/ratelimit.php` — per-IP + global daily token cap
- Schema migration `0003_ai_keys.sql`: `ai_provider_keys`, `ai_calls`
- `/admin/ai-keys.php` UI for adding/managing keys (`/api/ai/keys.php` admin POST/DELETE)
- `AI_DEFAULT_PROVIDER` and `HF_DEFAULT_MODEL` env knobs (defaults: `huggingface` / `meta-llama/Llama-3.3-70B-Instruct`)
- Live smoke verified against `gemini-2.5-flash` (719ms PONG round-trip with thinking-tokens accounted)

---

### Phase 11 — Admin AI tools ✅ ([#14](https://github.com/devenpro/landingPageBuild/pull/14))

- `/admin/ai.php` — two-card UI: Suggest pages + Generate page (with default-provider banner and master-key/key-on-file warnings)
- `core/lib/ai/prompts/{suggest_pages,generate_page}.php` — prompt templates with strict JSON output contracts
- `core/lib/ai/client.php` gains `ai_parse_json()` for tolerant JSON extraction (strips markdown fences if models add them)
- `/api/ai/suggest.php` — admin POST → list of suggestions
- `/api/ai/generate.php` — admin POST → draft `pages` row + page-scoped `content_blocks` rows in one transaction; slug conflicts resolve via `-2`/`-3` suffix; `meta_json` records provider, model, tokens, brief excerpt, admin user, timestamp
- Verified live with Gemini: 7 contextually relevant suggestions for an SEO-agency brief; full-page generation produced 24 content_blocks with correct types and contextual copy

### Phase 12 — Media library + upload UI ✅ ([#15](https://github.com/devenpro/landingPageBuild/pull/15))

- `/api/upload.php` — admin-only multipart POST. MIME via `finfo_file` (server-side, ignores client-supplied type), MIME↔extension allowlist, size cap by kind, generated filenames (`<unix>-<rand>-<safe-orig>.<ext>`), atomic move + DB insert with rollback
- `/api/media.php` — admin GET (list, optional `?kind` filter, `?limit` up to 500) + DELETE (row + on-disk file together, realpath-checked)
- `/admin/media.php` — gallery grid (200-cap), inline upload form, copy-URL, delete with toast feedback
- `/admin/content.php` — image- and video-typed rows now have a "Browse media" button + `<dialog>`-based picker modal that lazy-loads the gallery filtered by kind and fills the input on click
- Verified live: PHP-as-PNG attack rejected by finfo; MIME/ext mismatch rejected; valid PNG + SVG round-tripped end-to-end

### Phase 13 — Frontend chatbot ✅ ([#16](https://github.com/devenpro/landingPageBuild/pull/16))

- `core/migrations/0004_chat.sql` — `ai_chat_messages` table (session_id, role, content, ip, ua, created_at)
- `core/lib/ai/prompts/chat.php` — system prompt built at request time from current `content_blocks` (hero + features + faq), with explicit anti-extraction rules and a sanitised `chat_messages()` builder that caps each message at 4000 chars
- `core/lib/config.php` — `GUA_AI_CHAT_ENABLED` + `GUA_AI_CHAT_PERSIST` knobs, both default OFF
- `/api/chat.php` — public POST, rate-limited via Phase 10's `core/lib/ai/ratelimit.php` (no `skip_ratelimit`); 404s when disabled (hides config from probes); 429 on rate-limit, 502 on provider error; persistence is best-effort
- `site/public/assets/js/chat-widget.js` + `styles.css` — floating bubble + slide-up panel, conversation in localStorage, Enter-to-send, mobile responsive, self-contained CSS (no Tailwind classes)
- `site/layout.php` — emits `<script src="chat-widget.js" defer>` only when `GUA_AI_CHAT_ENABLED`
- Verified live with Gemini: response was contextually accurate, drawn directly from `hero.headline`; persistence wrote both turns to `ai_chat_messages`; disabled state correctly returned 404

## What each pending phase will deliver

### Phase 14 — Polish 🚧 (in flight)

Shipped so far:

- ✅ Round A ([#17](https://github.com/devenpro/landingPageBuild/pull/17)) — JSON-LD `Organization` + `WebSite`, dynamic `/sitemap.xml` from the `pages` table, CSV export + pagination + search on the Forms inbox
- ✅ Round B ([#18](https://github.com/devenpro/landingPageBuild/pull/18)) — per-IP rate limit on `POST /api/form.php` (5/hr default, configurable via `FORM_RATE_PER_IP_*`), no-JS HTML success/error/rate-limit page for plain form POSTs, `prefers-reduced-motion` honoured by JS-driven smooth scrolls
- ✅ Round C ([#19](https://github.com/devenpro/landingPageBuild/pull/19)) — Tailwind Play CDN dropped from all three layouts (`site/layout.php`, `site/public/admin/_layout.php`, `site/public/admin/login.php`). Tailwind v3.4 standalone CLI compiles `site/assets-src/styles.css` to `site/public/assets/css/styles.css` (29.5KB minified, committed). Build runs via `core/build/build-css.sh`; binary downloads on demand into `bin/` (gitignored)
- ✅ Round D-1 ([#20](https://github.com/devenpro/landingPageBuild/pull/20)) — webhook retry queue. `webhook_deliveries` table backs an out-of-band retry loop for transient receiver failures (5xx, timeouts, network errors). `/api/form.php` tries one inline POST then enqueues on transient failure; permanent 4xx stays marked `failed`. `core/scripts/webhook_worker.php` drains the queue under cron with exponential backoff (1m → 5m → 30m → 2h → 12h → 24h, 6 attempts max), holds a file lock to prevent overlap. New admin page `/admin/webhooks.php` shows the queue with status filter chips, **Retry now**, and **Cancel** actions. Cron entry documented in SETUP_GUIDE.md §6.5
- ✅ Round D-2 ([#21](https://github.com/devenpro/landingPageBuild/pull/21)) — WCAG AA pass. Replaced every below-3:1 focus ring (`ring-brand-200/300/400`) and below-4.5:1 text color (`text-ink-300/400`) with `ring-brand-500` / `text-ink-500` across all PHP templates (23 + 6 ring usages, 41 text usages). Added a skip-to-main link in `site/layout.php`, wrapped `home.php` content in `<main id="main-content">`, gave `404.php`'s existing `<main>` the same id. `form.js` now sets `aria-invalid="true"` + `aria-describedby` on inputs with errors and clears both on revalidation. Chat widget closes on Escape and returns focus to the bubble. Admin Content filter, Forms search, and Media upload inputs got `aria-label` attributes
- ✅ Round D-3 (this branch) — Lighthouse mobile pass. Replaced the `cdn.unpkg.com/lucide@latest` UMD bundle (~358KB, render-blocking, floating version) with a 21KB curated PHP path map (`core/lib/lucide-icons.php`) and a server-side `lucide()` helper that emits inline SVGs across 9 templates. The unpkg `<script>` and `createIcons()` call are gone — public visitors load **zero third-party JS**. Hero and product-demo `<img>` got intrinsic `width`/`height`/`decoding="async"` (eliminates CLS). Added `<link rel="canonical">` + `<meta name="theme-color">` to `site/layout.php`. `.htaccess` gained `mod_brotli` + `mod_deflate` filters, `Cache-Control: public, max-age=31536000, immutable` on hashed assets, and HSTS (HTTPS-only). `core/build/extract-lucide.php` is checked in for regenerating the icon map when adding new icons

Still to land:

- JSON-LD `Product` (deferred — needs CMS schema for product fields)

Phase 14 is otherwise complete: all four rounds (admin polish + SEO, form rate-limit + no-JS fallback + reduced motion, Tailwind compile-down, webhook retry + WCAG + Lighthouse) have shipped.

### Phase 15 — Launch

- DNS for production subdomain
- Final QA pass
- Content entry through admin
- Tag `core/VERSION` as `v1.0.0` in git
- Deploy + smoke test on cPanel

---

## V2 — Multi-page website builder enhancements

11-stage v2 plan (Stage 0 + Stages 1–10). Each stage ships on its own branch; Stage 0 (this entry) initiates on the active feature branch.

Legend: ✅ merged · 🟡 PR open, awaiting merge · ⏸️ closed/superseded · ⏳ pending · 🚧 in flight

| # | Stage | Status | Branch / PR | core/VERSION |
|---|---|---|---|---|
| 0 | Framing cleanup — remove stale "landing page builder" wording | 🟡 | [#23](https://github.com/devenpro/landingPageBuild/pull/23) | 1.0.1 |
| 1 | Settings foundation | 🟡 | [#23](https://github.com/devenpro/landingPageBuild/pull/23) | 1.1.0 |
| 2 | Brand Context Library | 🟡 | [#23](https://github.com/devenpro/landingPageBuild/pull/23) | 1.2.0 |
| 3 | Content blocks rework | 🟡 | [#24](https://github.com/devenpro/landingPageBuild/pull/24) (stacked on #23) | 1.3.0 |
| 4 | Content types + Content Manage hub (Pages, Testimonials, Services, Ad Landing Pages) | 🚧 | `v2/stage-4-content-types` (stacked on #24) | 1.4.0 |
| 5 | Taxonomy | ⏳ | — | 1.5.0 |
| 6 | Forms builder | ⏳ | — | 1.6.0 |
| 7 | Media v2 (crop/resize/WebP) | ⏳ | — | 1.7.0 |
| 8 | AI providers v2 (Grok / Anthropic / OpenAI + live model fetch) | ⏳ | — | 1.8.0 |
| 9 | Front-end canvas polish | ⏳ | — | 1.9.0 |
| 10 | Site Bootstrap | ⏳ | — | 2.0.0 |

### v2 Stage 0 — Framing cleanup 🟡 ([#23](https://github.com/devenpro/landingPageBuild/pull/23))

Scrubbed stale "landing page builder" wording from active code and docs so the system's identity as a multi-page website CMS is consistent everywhere. The repo started as a single-page tool (v1 Phase 2) and was expanded to a full website CMS at v1 Phase 3 — most active docs were updated then, but a few prompt strings and comments still narrowed the framing.

- `core/lib/ai/prompts/generate_page.php` — system prompt updated to "page draft for a multi-page business website" (was "landing-page draft for a small business"). Added a hard rule explicitly enumerating valid page types the model can produce: service detail page, location-service page, company-profile page, ad landing page, or generic marketing page. Top-of-file comment updated to "credible business / service / campaign page" (was "credible landing page").
- `site/public/admin/dashboard.php:2` — comment changed from "landing page after login" → "admin home page after login".
- `SETUP_GUIDE.md:102` — "You should see the full landing page" → "You should see the live site (home page)".
- `README.md:3` — subtitle "marketing/landing sites" → "multi-page business websites".
- `core/VERSION` — `1.0.0` → `1.0.1`.

Historical references in `BUILD_BRIEF.md` (lines 3, 202, 292), `PHASE_STATUS.md` (Phase 2 entries above), and `README.md` (Phase 2 tracker entry) are intentionally preserved — they correctly label v1 Phase 2 as "Static landing page (superseded)" and removing them would erase project history.

Verification: `grep -rin "landing page" core/ site/ README.md SETUP_GUIDE.md AGENTS.md AI_GUIDE.md MULTI_SITE.md PHASE_STATUS.md` returns only the 2 intentional hits in `generate_page.php` (the new hard rule listing "ad landing page" as one valid page type) plus the 3 historical Phase 2 tracker entries. No active code or non-historical doc describes the system as a "landing page builder".

Rollback: revert the commit. Schema unchanged, no DB migration to undo.

### v2 Stage 1 — Settings foundation 🟡 ([#23](https://github.com/devenpro/landingPageBuild/pull/23))

DB-backed runtime settings replacing the v1 pattern of one `.env` knob per config value. Admin can edit values from `/admin/settings.php` and they take effect on the next request without a redeploy.

**Schema** (`core/migrations/0006_settings.sql`)
- `site_settings(id, key, value, value_type, group_name, label, description, is_secret, default_value, updated_at, updated_by)` with `idx_site_settings_group` for group lookups.
- Seeded with 13 metadata rows for every existing user-facing `.env` knob (5 General, 4 AI, 2 Webhooks, 2 Media). `value` is NULL on seed so resolution falls through to `.env` until admin overrides via UI.

**Library** (`core/lib/settings.php`)
- `settings_get($key, $default)` — three-layer fallback: `site_settings.value` → `.env` (uppercased key) → `$default`. Per-request cache loads all rows in one query.
- `settings_set($key, $value, $user_id)` — persists and clears the cache so same-request reads see the new value.
- `settings_all_in_group($group)`, `settings_groups()`, `settings_source($key)` for the admin UI.
- Type casting per `value_type`: string / number (int or float) / boolean (`FILTER_VALIDATE_BOOLEAN`) / json (decoded array) / secret.

**Bootstrap chain rewire**
- `core/lib/config.php` now exposes `env_get($key, $default)` and only defines paths + secrets at boot (`GUA_PROJECT_ROOT`, `GUA_CORE_PATH`, `GUA_SITE_PATH`, `GUA_DATA_PATH`, `GUA_DB_PATH`, `GUA_AI_KEYS_MASTER_KEY`, `GUA_CORE_VERSION`).
- `core/lib/runtime_constants.php` (new) defines the 13 legacy `GUA_*` runtime constants via `settings_get()`. Loaded by `bootstrap.php` after `db.php` and `settings.php`. Existing v1 callers (auth, layout, sections, AI providers, form handler, chatbot endpoint, sitemap, admin pages) see DB values transparently — no other code changes required.

**Admin UI** (`site/public/admin/settings.php`)
- Tab-per-group nav (`?tab=general|ai|webhooks|media`) — only groups with rows render, so empty groups won't appear until later stages add settings.
- Per-row source badge: green "DB override" / gray "from .env" / italic "default" + the effective value displayed underneath.
- Inputs adapt to `value_type`: text input, number input, boolean tri-state select (empty / On / Off), JSON textarea, password input for `is_secret=1`.
- Empty input on save = clear DB value (NULL), restoring `.env` / default fallback.

**Save endpoint** (`site/public/api/settings.php`)
- POST-only, CSRF-checked, admin-only. Form-urlencoded body (no JSON path in Stage 1 — the page is server-rendered, the form is plain HTML).
- Per-key validation matching `value_type` (number must be numeric, boolean must be `'0'`/`'1'`, JSON must parse).
- Redirects back to `/admin/settings.php?tab=<x>&saved=1` on success or `&error=…` on validation failure.

**Nav** — `site/public/admin/_layout.php` flips the `settings` entry from `live:false` to `live:true` and sets `href` to `/admin/settings.php`.

**Smoke test** — `core/scripts/test_stage_1.php` (14 assertions): seed count, three-layer resolution, save+read cycle, type casting (number/boolean), clearing restores fallback, group queries. End-to-end manual verification: dev server boots, login flow works, page renders all 4 tabs and 13 settings, save persists to DB, source badge updates correctly.

**Rollback**: revert the commit and `DROP TABLE site_settings`. Existing constants resolved via `settings_get()` would fall back to `.env` for the runtime knobs, so no behavior change for sites that haven't customized any setting via UI.

### v2 Stage 2 — Brand Context Library 🟡 ([#23](https://github.com/devenpro/landingPageBuild/pull/23))

Categorised brand knowledge curated by the admin and injected into every AI prompt as ground truth. Replaces the v1 pattern where each AI call started from scratch with no source-of-truth for brand voice, audience, services, design guide, or page-build conventions. Editable from both the admin panel AND Claude Code (disk mirror at `.brand/`); the DB is canonical and the admin reconciles drift manually.

**Schema** (`core/migrations/0007_brand_context.sql`)
- `brand_categories` — 8 built-in: brand_voice, brand_facts, audience, services, design_guide, page_guide, seo, social. Five flagged `required` for the audit.
- `brand_items` — body + body_hash + disk_hash + status + source + ai_reviewed + always_on + version. UNIQUE(category_id, slug).
- `brand_item_history` — per-save snapshot for future restore UI.
- Seeded with one empty placeholder item per category (8 rows). `source='bootstrap'`, `ai_reviewed=1`, body=''.

**Library** (`core/lib/brand/`)
- `categories.php` — `brand_categories_all()`, `brand_category_by_slug()`, `brand_category_item_counts()`.
- `items.php` — CRUD with versioning, history snapshots, slug regex validation (`^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$`). Update auto-marks `ai_reviewed=1` when an admin saves.
- `sync.php` — disk write/delete, YAML frontmatter renderer + permissive parser, drift detection (states: `disk_changed`, `disk_missing`, `disk_only`), `brand_sync_pull($id, $strategy, $manual_body)` for accept_disk / keep_db / manual reconciliation. `brand_hash()` normalises trailing CR/LF so editor-added newlines don't register as drift. `brand_assert_under_root()` resolves paths and refuses anything outside `.brand/`.
- `audit.php` — `brand_audit()` returns `{score, missing, stale, ok, totals}`. Score = % of required categories with at least one filled-and-reviewed item. AI-generated unreviewed items count as `stale` (not blocking, but flagged).

**AI prompt integration** (`core/lib/ai/`)
- `brand_context.php` — three assemblers: `brand_context_summary()` (200-word digest of voice/facts/audience/services for chat), `brand_context_for_categories(slugs)` (full bodies, 4K per category / 8K global cap, used by page generation and suggest-pages), `brand_context_always()` (always_on items only, persistent chatbot anchor). All assemblers exclude `source='ai' AND ai_reviewed=0` items via `brand_items_for_prompt_context()`.
- `prompts/brand_item_generate.php` — new prompt for the admin "AI fill" button and Stage 10 bootstrap gap-fill. Returns JSON `{title, body}`. Items it generates are saved with `source='ai', ai_reviewed=0` so they don't influence other prompts until admin opens and saves them.
- `prompts/chat.php` — prepends `brand_context_summary()` + `brand_context_always()` to the public chatbot system prompt above the "Site context" block.
- `prompts/suggest_pages.php` — prepends `brand_context_for_categories(['brand_voice','audience','services'])` to the user message before the brief.
- `prompts/generate_page.php` — prepends `brand_context_for_categories(['brand_voice','audience','services','design_guide','page_guide'])` to the user message.

**Claude Code workflow**
- `.claude/scripts/check-mode.php` now allows `.brand/**` writes in both core and site mode (no need to switch modes when iterating brand content alongside code).
- New repo-root `CLAUDE.md` — ~30 lines pointing to `.brand/INDEX.md`, explaining the manual-sync model and the slug rule.
- `.brand/` is git-tracked by default so a desktop session and a mobile session see the same content. Disk file format: YAML frontmatter (id, category, slug, kind, title, version, body_hash, updated_at, source) + body. `INDEX.md` auto-regenerated after every save and delete.

**Admin UI**
- `/admin/brand.php` (`site/public/admin/brand.php`) — three-pane layout: categories rail with required/missing badges + item counts → items list with empty/always-on/AI-review state pills → editor (title, slug, kind, body textarea, always_on toggle, status). Save flows through `/api/brand/items.php`. Drift banner appears at top when `brand_sync_dirty()` returns rows, deep-linking to `/admin/brand-sync.php`. Audit score badge at top right.
- `/admin/brand-sync.php` (`site/public/admin/brand-sync.php`) — drift resolution UI. Per item: side-by-side DB vs disk body, three actions (Accept disk / Keep DB / Manual merge with textarea pre-filled from disk body).

**API**
- `POST /api/brand/items.php` — form-driven create / update / delete. CSRF, auth, redirect-back with `?saved=1` / `?created=1` / `?error=...`.
- `POST /api/brand/sync.php` — apply a drift resolution strategy. CSRF, auth.
- `GET /api/brand/audit.php` — JSON audit result for the Stage 10 bootstrap wizard and the dashboard banner.

**Nav** — `_layout.php` adds 'Brand' between 'Pages' and 'Forms'.

**Smoke test** — `core/scripts/test_stage_2.php` (20 assertions): seed shape, CRUD with disk mirroring, slug validator, drift detection (file edited externally), sync strategies (accept_disk overwrites DB body + bumps version, source→'disk'), AI-prompt context assembly filtering `ai_reviewed=0` items, audit score sanity, disk cleanup on delete. End-to-end manual verification: admin renders three-pane layout with all 8 categories, audit JSON endpoint returns expected shape, item creation via HTTP form POST persists to DB and writes disk file with frontmatter.

**Rollback**: revert the commit, `DROP TABLE brand_item_history; DROP TABLE brand_items; DROP TABLE brand_categories`, and delete `.brand/`. Existing AI prompts revert to v1 behavior with no brand-context injection.

### v2 Stage 3 — Content blocks rework 🚧 (`v2/stage-3-blocks`, stacked on #23)

Splits the v1 flat `content_blocks(key, value, type)` table into three tables: reusable block definitions, per-field values, and per-page overrides. Replaces the single keyed namespace where reusable content and page-scoped overrides shared rows with no enforced structure.

**Schema** (`core/migrations/0008_blocks_split.sql`)
- The v1 `content_blocks` table is renamed to `legacy_content` and kept as a read-fallback for one release.
- New `content_blocks(id, slug UNIQUE, name, description, category, status, schema_json, preview_partial, ...)` — block DEFINITIONS.
- New `content_block_fields(id, block_id, field_key, value, type, position, ...)` UNIQUE(block_id, field_key) — VALUES for each block's fields.
- New `page_fields(id, page_id, field_key, value, type, ...)` UNIQUE(page_id, field_key) — per-page overrides.
- Data migration runs inline in pure SQL (no separate PHP script): parses each legacy key on the first dot to derive a block slug + field_key, then inserts into the new tables. Page-scoped `page.<slug>.<field>` rows go to `page_fields` via a join against `pages.slug`. On a fresh dataset all 104 v1 rows migrate to 15 blocks with their fields; zero `page_fields` rows since no page-scoped overrides existed in v1.

**Resolution chain** (`core/lib/content.php`)
- `c('hero.headline')` with `content_set_prefix('page.about')` now resolves: `page_fields(page.slug='about', field_key='hero.headline')` → `content_block_fields(block.slug='hero', field_key='headline')` → `legacy_content(key='hero.headline')`. The legacy fallback covers any row not yet migrated and is the safety net during the deprecation window.
- `block_get($slug)` returns the block row; `block($slug)` includes `site/sections/<slug>.php` (or `preview_partial` if set) and returns the rendered HTML.
- All v1 callers (section partials, layout, sitemap, JSON-LD) continue to use `c('section.field')` unchanged.

**Writes** (`site/public/api/content.php`)
- Parses incoming keys via regex: `page.<slug>.<field>` → `page_fields` upsert; `<block_slug>.<field>` → `content_block_fields` upsert. Auto-creates the block definition if the slug doesn't exist yet (covers the inline editor adding new sections).
- Existing inline editor (`editor.js`) needs no changes — it still sends concatenated `<prefix>.<key>` strings.
- `site/public/api/ai/generate.php` updated to write directly to `page_fields` instead of the renamed table.
- `site/public/admin/content.php` rewritten to read the flat (key, value, type) view by joining `content_block_fields` with `content_blocks` so the v1 admin content editor continues to work.
- `site/public/admin/dashboard.php` content-count badge now counts `content_block_fields` rows.

**Admin UI**
- New `/admin/blocks.php` (`site/public/admin/blocks.php`) — left pane lists blocks (with field counts), right pane has block-meta form + per-field editor + "add field" form. Saves go through `/api/blocks.php` with action verbs (`create`, `save_meta`, `delete`, `add_field`, `save_fields`). Slug regex matches the v2 convention.
- Nav: `Blocks` slotted between `Content` and `Pages` in `_layout.php`.

**Smoke test** — `core/scripts/test_stage_3.php` (14 assertions): all four tables exist, non-page legacy row count == new field row count, `c()` resolves migrated keys for hero / faq / feature partials, `block_get()` works, page_fields override beats block field when `content_set_prefix('page.home')` is set, legacy_content read-fallback covers rows not in the new tables. End-to-end manual verification: dev server boot → homepage 200 with all hero / features / faq content present → admin login 302 → admin/blocks.php renders all 15 blocks → faq block detail shows correct fields → API block-create round-trip works.

**Rollback**: tricky because the rename is destructive on the v1 schema. Path: copy `legacy_content` rows back into a freshly-created v1-shaped `content_blocks` table, drop the v2 tables. `rollback_blocks.php` is a one-liner script left for a future stage; for now treat the rename as one-way.

### v2 Stage 4 — Content types + Content Manage hub 🚧 (`v2/stage-4-content-types`, stacked on #24)

First-class content types replace v1's "everything is a page" model. v1 stored testimonials as ad-hoc `content_blocks` rows and had no first-class home for ad landing pages. Stage 4 ships three built-in types: Testimonials (non-routable), Services (routable `/services/{slug}`), and Ad Landing Pages (routable `/lp/{slug}`). Location Services is deferred to Stage 5 since it depends on taxonomy.

**Schema** (`core/migrations/0009_content_types.sql`)
- `content_types(id, slug UNIQUE, name, description, is_routable, route_pattern, detail_partial, list_partial, schema_json, is_builtin, status, sort_order, ...)` — type registry.
- `content_entries(id, type_id, slug, title, data_json, seo_title, seo_description, seo_og_image, robots, status, position, ...)` UNIQUE(type_id, slug). `slug` nullable for non-routable types. `data_json` holds type-specific fields whose shape is documented in the type's `schema_json` (used by the admin form renderer).
- Trigger `trg_ad_lp_default_robots` sets `robots='noindex,nofollow'` on every new ad-landing-pages entry where the field wasn't explicitly provided, so paid-traffic pages don't compete with organic SEO out of the box.

**Library** (`core/lib/content/`)
- `types.php` — read-only registry (`content_types_all`, `content_type_by_slug`, `content_types_routable`, `content_type_fields` decoding `schema_json`).
- `entries.php` — CRUD with slug validation (same regex as Stage 2), status enum, JSON round-trip for `data_json`. `content_entry_update()` merges with the existing row so the API can pass only the fields it owns.

**Routing** (`core/lib/pages.php`)
- `route_request()` now: 1) match `pages.slug`, 2) match each routable content type's `route_pattern`, 3) 404. Pages still win on slug collision.
- `content_resolve_route($uri)` quotes the pattern, swaps `{placeholder}` for a named-capture slug regex, and looks up the published entry. Returns `['type' => …, 'entry' => …, 'params' => […]]`.
- `render_content_entry($type, $entry)` synthesises a `$page`-shaped array for the layout, exposes `$gua_content_entry / $gua_content_type / $gua_content_data` to the partial, then includes `site/sections/<detail_partial>.php` between `layout_head` and `layout_foot`.

**Frontend**
- `site/layout.php` — emits `<meta name="robots" content="…">` when `$page['robots']` is set (used by Ad LPs).
- `site/sections/service_detail.php` — hero / long description / features / FAQs. Pulls from `$gua_content_data`.
- `site/sections/ad_lp_detail.php` — hero / benefits / CTAs. Injects Meta Pixel + Google Tag `<script>` tags only when the corresponding fields are set. Wires `fbq('track', conversion_event)` into the primary CTA's onclick.

**Admin**
- `/admin/content-types.php` — three-pane hub (types rail / entries list / entry editor). Form fields rendered from the type's `schema_json` so per-type fields appear automatically. Optional SEO + robots collapsible. New-entry mini-form below the editor.
- `/api/content/entries.php` — form-driven CRUD (create / update / delete). The update handler only writes the optional `seo_*` and `robots` fields when the form actually submits them, so the create trigger's default doesn't get clobbered on subsequent saves.
- `_layout.php` nav: 'Types' between 'Blocks' and 'Pages'.

**Verification**
- `core/scripts/test_stage_4.php` — 24 assertions covering schema, seed, CRUD, slug validation, data_json round-trip, routing (positive + negative), Ad LP default robots trigger. All pass.
- End-to-end: created a Service entry via the admin API; GET `/services/local-seo-audit` → 200 with title in `<title>`, meta description, hero + CTA + features. Created an Ad LP; GET `/lp/bf-2025-test` → 200 with `<meta name="robots" content="noindex,nofollow">`, Meta Pixel + Google Tag scripts, primary CTA wired to `fbq('track', conversion_event)`.

**Rollback**: revert the commit and `DROP TABLE content_entries; DROP TABLE content_types; DROP TRIGGER IF EXISTS trg_ad_lp_default_robots;`. Routing falls back to pages-only; the 3 detail partials remain in `site/sections/` but become unreachable. No data migration to undo since v1 had no content_types data.
