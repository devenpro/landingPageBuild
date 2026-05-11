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
| 10 | AI key management (BYO + libsodium) + provider abstraction (HuggingFace, Gemini, OpenRouter) | ✅ | [#12](https://github.com/devenpro/landingPageBuild/pull/12) + [#13](https://github.com/devenpro/landingPageBuild/pull/13) | merged into `main` |
| 11 | Admin AI tools — page suggestions, AI page generation | ✅ | [#14](https://github.com/devenpro/landingPageBuild/pull/14) | merged into `main` |
| 12 | Media library + uploads UI | ✅ | [#15](https://github.com/devenpro/landingPageBuild/pull/15) | merged into `main` |
| 13 | Frontend AI features — chatbot widget + rate limiting | ✅ | [#16](https://github.com/devenpro/landingPageBuild/pull/16) | merged into `main` |
| 14 | Polish — motion, SEO, JSON-LD, a11y, Lighthouse, Tailwind compile-down, CSV export, no-JS form fallback | 🚧 | this branch (Round A: admin + SEO) | `claude/review-next-tasks-tmBYG` |
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
- ✅ Round D-2 (this branch) — WCAG AA pass. Replaced every below-3:1 focus ring (`ring-brand-200/300/400`) and below-4.5:1 text color (`text-ink-300/400`) with `ring-brand-500` / `text-ink-500` across all PHP templates (23 + 6 ring usages, 41 text usages). Added a skip-to-main link in `site/layout.php`, wrapped `home.php` content in `<main id="main-content">`, gave `404.php`'s existing `<main>` the same id. `form.js` now sets `aria-invalid="true"` + `aria-describedby` on inputs with errors and clears both on revalidation. Chat widget closes on Escape and returns focus to the bubble. Admin Content filter, Forms search, and Media upload inputs got `aria-label` attributes

Still to land:

- JSON-LD `Product` (needs CMS field support)
- Lighthouse ≥ 95 mobile (Round D-3)

### Phase 15 — Launch

- DNS for production subdomain
- Final QA pass
- Content entry through admin
- Tag `core/VERSION` as `v1.0.0` in git
- Deploy + smoke test on cPanel
