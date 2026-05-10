# Go Ultra AI — Build Brief (v4)

> **Status:** v4 supersedes the original v3 single-tenant landing-page brief. The v3 scope (one landing page with inline editing) was expanded mid-build into a self-hosted multi-page CMS with optional AI integration. v3 is preserved in git history if anyone needs to consult the original scope. **This file is the source of truth.**

For agent / mobile workflow conventions, read [AGENTS.md](AGENTS.md). For per-phase status and PR links, read [PHASE_STATUS.md](PHASE_STATUS.md). For deploy procedures, read [SETUP_GUIDE.md](SETUP_GUIDE.md). For the multi-site model, read [MULTI_SITE.md](MULTI_SITE.md). For AI feature design, read [AI_GUIDE.md](AI_GUIDE.md).

---

## 1. Vision

A self-hosted PHP + SQLite product for building marketing and content sites with optional AI assistance, designed for **freelancers, solo marketers, and small agencies** who manage multiple clients' web presence. Each site = one git clone of this repo, fully isolated, pinnable to a specific code version. The same codebase ships:

- A multi-page public site (file-based critical pages + data-driven marketing pages)
- An admin panel for managing pages, content, media, and form submissions
- AI features (BYO API keys) that suggest pages, generate page content, and power a frontend chatbot

The first deployment target is **`lpb.cswebserver.in`** (the marketing site for Go Ultra AI itself, a CreatiSoul LLP product). Subsequent sites will fork this repo and customise `site/`.

### What we are NOT building

- **Not a SaaS** — single-tenant per clone; no signup, billing, or app-level multi-tenancy
- **Not a Webflow visual builder** — admin uses HTML forms + an inline editor, no drag-and-drop canvas
- **Not a plugin/theme marketplace**
- **Not multi-language at v1** — English / Hinglish only; i18n is a future-phase question

---

## 2. Audience and brand voice

**Primary audience:** Indian freelancers and small agency operators managing 3-15 clients each.

**Secondary:** global solo marketers; small in-house marketing teams.

**Brand voice for the marketing site (Go Ultra AI itself):** confident, practical, slightly irreverent. Hinglish phrasing welcome where it lands naturally.

**Core value prop (for the marketing site):** "Plan, prompt, and produce social content for multiple clients in a fraction of the time — without losing the thinking that makes good content actually good."

---

## 3. Architecture

### 3.1 Core vs site separation

Two top-level directories with hard separation enforced by Claude Code workflow modes:

- **`core/`** — versioned engine: bootstrap, config, DB, content getters, helpers, auth, CSRF, page router, scripts, schema migrations. Theoretically reusable across sites; in practice each site clones the whole repo (`core/` + `site/`) so cores can drift between sites at the user's choice.
- **`site/`** — per-site theme + content + admin + API endpoints. Sections, layouts, file-based pages, site-specific schema additions, public assets.

### 3.2 Multi-site model

Each site is its own clone of this repo. Sites are 100% isolated — own DB at `data/content.db`, own `.env`, own uploads, own admin user. Updating one site never affects another. Pinning per site is the natural consequence of git: each clone stays on whatever commit/tag it's checked out at until you explicitly upgrade.

See [MULTI_SITE.md](MULTI_SITE.md) for fork/upgrade/pinning workflows.

### 3.3 Workflow modes (Claude Code)

Hard-enforced by a `PreToolUse` hook in `.claude/settings.json`:

- **`/core-mode`** — writable: `core/**`, `.claude/**`, repo-root docs/configs. Read-only: `site/**`, `data/**`, `.env`.
- **`/site-mode`** — writable: `site/**`, `data/**`, `.env`. Read-only: `core/**`, `.claude/**`, repo-root docs/configs.
- **No mode set** — all writes blocked with a helpful message.

The hook script is `.claude/scripts/check-mode.php`; mode marker is `.claude-mode` (gitignored).

---

## 4. Repo layout

```
landingPageBuild/                       single git repo, one clone per site
│
├── core/                               versioned engine (touched in /core-mode)
│   ├── VERSION                         "1.0.0" — pinning reference
│   ├── lib/
│   │   ├── bootstrap.php               single entry: requires everything below
│   │   ├── config.php                  .env loader + path resolution
│   │   ├── db.php                      memoized PDO (WAL + FK)
│   │   ├── content.php                 c() / c_list() / c_type() getters
│   │   ├── csrf.php                    session-bound CSRF tokens
│   │   ├── auth.php                    login, logout, lockout, current_user
│   │   ├── pages.php                   route_request, get_page_by_slug, render_page
│   │   ├── pages_data_driven_placeholder.php   503 stub until Phase 8
│   │   └── helpers.php                 e() / slugify() / now_iso() / json_safe()
│   ├── scripts/
│   │   ├── migrate.php                 scans BOTH core/migrations/ and site/migrations/
│   │   ├── seed_admin.php              CLI bcrypt admin upsert
│   │   └── dev-router.php              router for php -S (mimics mod_rewrite)
│   └── migrations/
│       ├── 0001_init.sql               admin_users, content_blocks, media_assets,
│       │                                login_attempts, form_submissions
│       └── 0002_pages.sql              pages
│
├── site/                               per-site theme + content (touched in /site-mode)
│   ├── public/                         doc root — point cPanel here
│   │   ├── index.php                   front controller (calls route_request)
│   │   ├── .htaccess                   HTTPS, rewrite-to-index, deny rules, cache TTLs
│   │   ├── favicon.svg, robots.txt, sitemap.xml
│   │   ├── admin/
│   │   │   ├── _layout.php             admin chrome (head, nav, foot helpers)
│   │   │   ├── login.php
│   │   │   ├── logout.php
│   │   │   ├── dashboard.php
│   │   │   ├── content.php             content_blocks editor
│   │   │   └── forms.php               waitlist submissions inbox
│   │   ├── api/
│   │   │   ├── form.php                public POST endpoint for the waitlist
│   │   │   └── content.php             admin-only PATCH for content_blocks
│   │   ├── assets/
│   │   │   ├── css/styles.css
│   │   │   ├── fonts/InterVariable.woff2
│   │   │   ├── js/{form.js, admin.js}
│   │   │   └── placeholders/{hero,demo}-placeholder.svg
│   │   └── uploads/                    runtime media (gitignored)
│   ├── pages/                          file-based pages
│   │   ├── home.php
│   │   └── 404.php
│   ├── sections/                       reusable section partials (10 today)
│   ├── layout.php                      head/foot, accepts ?array $page for SEO override
│   └── migrations/
│       ├── 0001_seed.sql               ~85 content keys for the marketing site
│       ├── 0002_pages_seed.sql         home + 404 page rows
│       └── 0003_form_seed.sql          18 form.* labels and option lists
│
├── data/                               per-site SQLite DB (gitignored)
├── .env                                per-site config (gitignored)
├── .env.example
├── .gitignore
├── .cpanel.yml                         deploy hook (just runs migrate)
├── .claude/                            workflow modes
│   ├── settings.json
│   ├── commands/{core-mode,site-mode}.md
│   └── scripts/check-mode.php
├── .claude-mode                        gitignored — current mode marker
├── README.md, BUILD_BRIEF.md (this file), MULTI_SITE.md
├── SETUP_GUIDE.md, AGENTS.md, PHASE_STATUS.md, AI_GUIDE.md
```

---

## 5. Data model

### Shipped tables

| Table | Purpose | Phase |
|---|---|---|
| `_migrations` | Tracks applied migrations by `(source, name)` (`source` = `'core'` or `'site'`) | 1 |
| `admin_users` | id, email (unique), password_hash, created_at | 1 |
| `content_blocks` | key (unique), value, type (`text`/`image`/`video`/`icon`/`list`/`seo`), updated_at, updated_by | 1 |
| `media_assets` | filename (unique), original_name, mime_type, size_bytes, kind (`image`/`video`), uploaded_at, uploaded_by | 1 (table only; upload UI in Phase 12) |
| `login_attempts` | ip_address, email, success, attempted_at — drives brute-force lockout | 1 |
| `form_submissions` | full_name, email, phone, role, clients_managed, bottleneck, UA / referer / IP, webhook_status (`sent`/`failed`/`skipped`), webhook_response, submitted_at | 1 (table) + 5 (writes) |
| `pages` | slug (unique), title, status (`draft`/`published`/`archived`), is_file_based, file_path, layout, sections_json, meta_json, seo_*, created_at, updated_at | 4 |

### Planned tables

| Table | Purpose | Phase |
|---|---|---|
| `ai_provider_keys` | provider, label, encrypted_key (libsodium secretbox), nonce, last_used_at | 10 |
| `ai_calls` | provider, model, prompt_template, tokens_in/out, cost_estimate_usd, success, error_msg, caller, caller_ip, called_at | 10 |
| `ai_chat_messages` | session_id, role, content, created_at — frontend chat persistence (toggleable) | 13 |

---

## 6. Routing model

`site/public/index.php` is a 4-line front controller: `require core/lib/bootstrap.php; route_request();`.

`route_request()` (in `core/lib/pages.php`):

1. Parse `REQUEST_URI` → slug (`/` → `home`; `/services/seo-bangalore` → `services/seo-bangalore`)
2. Lookup `pages` row by slug, `status = 'published'`
3. **If `is_file_based = 1`**: `realpath`-validate that `file_path` resolves under `site/pages/`, then `require` it
4. **Else (data-driven)**: Phase 8 implements; for now requires the placeholder
5. **No row found**: `http_response_code(404)` + serve the `404` page (also a published `pages` row)

`site/public/.htaccess` rewrites all non-file URLs to `index.php`. Apache mod_rewrite handles this in production; `core/scripts/dev-router.php` does the equivalent for local `php -S`.

For local-SEO programmatic pages: AI generation populates `pages` rows with descriptive slugs (`services/seo-bangalore`) and JSON-encoded section content. Admin reviews, flips status to `published`. Phase 8 ships the data-driven render path; Phase 11 ships the AI generator.

---

## 7. Constraints

- **PHP 8.1+** (`pdo_sqlite`, `fileinfo`, `session`, `openssl`, `curl`, `sodium` extensions)
- **SQLite 3** via PDO; no other DB
- **Vanilla JS only**; no React, no Vue, no build step
- **Tailwind via Play CDN** through Phase 13; hand-built `styles.css` in Phase 14
- **Lucide icons via CDN** (icons referenced by name in DB)
- **Self-hosted Inter font** at `site/public/assets/fonts/InterVariable.woff2`
- **No Composer, no npm.** Files on disk = files in production.
- **Total source under 2 MB** uncompressed
- **cPanel-deployable** via Git Version Control with `.cpanel.yml` hook
- **No external runtime services** other than: outbound webhook to user's n8n / Zapier (optional, set `WEBHOOK_URL` in `.env`); AI provider APIs (Phases 10-13, BYO keys)

---

## 8. Phased delivery

| # | Phase | Status | PR |
|---|---|---|---|
| 1 | Scaffolding (DB, config, migrations, hello-world) | ✅ merged | #1 |
| 2 | Static landing page (single-tenant — *superseded*) | ⏸️ closed | #2 |
| 3 | Architecture reset — multi-site `core/`+`site/` + workflow modes | 🟡 open | #3 |
| 4 | Pages table + hybrid routing + Home as file-based page | 🟡 open | #4 |
| 5 | Public waitlist form + optional outbound webhook | 🟡 open | #5 |
| 6 | Admin auth — login, logout, brute-force lockout | 🟡 open | #6 |
| 7 | Admin panel base + content_blocks editor + forms inbox | 🟡 open | #7 |
| 7.5 | Documentation refresh (this PR) | 🟡 in flight | #8 |
| 8 | Pages CRUD UI + data-driven page renderer | pending | — |
| 9 | Inline editing on the public page | pending | — |
| 10 | AI key management (BYO + libsodium encryption) + provider abstraction (Gemini, OpenRouter) | pending | — |
| 11 | Admin AI tools — page suggestions, AI page generation | pending | — |
| 12 | Media library + uploads UI | pending | — |
| 13 | Frontend AI features — chatbot widget + rate limiting | pending | — |
| 14 | Polish — motion, SEO, JSON-LD, a11y, Lighthouse, Tailwind compile-down, CSV export, no-JS form fallback | pending | — |
| 15 | Launch — DNS, final QA, content entry | pending | — |

See [PHASE_STATUS.md](PHASE_STATUS.md) for the live tracker (per-phase deferrals, gotchas caught, and what each PR shipped).

---

## 9. Working style

- **One PR per phase.** Stacked PRs are fine when phases build on each other; rebase or close if merges happen out of order.
- **Commit messages**: `Phase N: <title>` then a body with **why**, **what changed**, **verified locally**, and **out of scope**.
- **Mode-gated edits**: `/core-mode` for engine work, `/site-mode` for theme/content. Don't shell out (Bash redirection) to bypass the hook.
- **All PHP files** start with a top-level comment explaining purpose.
- **`// TODO: VERIFY` comments** wherever you're making an assumption the user should check.
- **All DB writes** via PDO prepared statements, named bound params. No string concatenation into SQL.
- **All user input escaped on output** via `e()` (htmlspecialchars wrapper).
- **All file paths from user input** normalised with `realpath()` and prefix-checked against an allow-list.
- **Don't over-engineer.** Single admin per site. No roles, no draft/publish split for content_blocks (saves go straight live).

---

## 10. Configuration (`.env`)

See `.env.example` for the canonical template. Per-site, gitignored, lives at repo root. Key vars:

- `APP_ENV` — `local` | `production`
- `APP_URL` — public URL of the site (no trailing slash)
- `SITE_NAME` — used in titles
- `ADMIN_EMAIL` — `seed_admin.php` writes the row keyed by this
- `SESSION_LIFETIME_HOURS` — default 8, sliding-window inactivity timeout
- `WEBHOOK_URL` — optional outbound POST target for form submissions; blank = skipped
- `WEBHOOK_TIMEOUT_SECONDS` — default 10
- `MAX_IMAGE_BYTES` / `MAX_VIDEO_BYTES` — Phase 12 upload caps
- `AI_KEYS_MASTER_KEY` — base64-encoded 32 bytes for libsodium encryption of stored AI provider keys (Phase 10+)
- Path overrides (`CORE_PATH`, `SITE_PATH`, `DATA_PATH`, `DB_PATH`) — only set if you want files outside the repo on a host that allows it

---

## 11. Performance, SEO, accessibility (target)

- Lighthouse ≥ 95 on Performance / Accessibility / Best Practices / SEO (logged-out, mobile) — Phase 14 chases this
- Open Graph + Twitter Card meta in `<head>` (sourced from `seo.*` content_blocks AND per-page `seo_*` columns)
- JSON-LD `Organization` + `WebSite` + `Product` (Phase 14)
- `sitemap.xml` regenerated from `pages` table (Phase 14)
- Self-hosted Inter font with `preload` + `font-display: swap`
- Lazy-load below-the-fold images
- WCAG AA contrast minimum, semantic HTML, full keyboard navigation, visible focus states, `aria-live` for form errors

---

## 12. Design direction

- **Aesthetic:** modern, AI-confident, warm. No cold-blue-gradient AI cliché.
- **Palette:** brand violet (`#7c3aed` family) + neutral inks (gray scale) + near-white background. Avoid default dark mode.
- **Type:** Inter Variable. Big headline scale (≥60px desktop), generous line-height.
- **Visuals:** real product UI screenshots > generic AI art. **No robot/brain stock imagery.**
- **Motion:** subtle scroll reveals, gentle hover lifts. Respect `prefers-reduced-motion` (Phase 14).
- **Mobile:** mobile-first. Hero readable in one phone screen. Tap targets 44 px+.
- **Edit mode visual:** dashed violet outline + pencil icon on hover (Phase 9 inline editor).

---

## 13. References

- [README.md](README.md) — quickstart and phase status
- [SETUP_GUIDE.md](SETUP_GUIDE.md) — cPanel deploy walkthrough
- [MULTI_SITE.md](MULTI_SITE.md) — multi-site / fork / pinning
- [AGENTS.md](AGENTS.md) — operating manual for Claude Code and future agents
- [AI_GUIDE.md](AI_GUIDE.md) — AI feature design (Phases 10-13)
- [PHASE_STATUS.md](PHASE_STATUS.md) — live per-phase tracker

---

## 14. What changed since v3

For anyone consulting v3 in git history:

- **Scope expanded** from "single landing page with inline editing" to "multi-page CMS with AI integration and multi-site support"
- **Layout reorganized** from `public_html/`-centric to `core/` + `site/` split
- **Routing introduced** (`pages` table, front controller) — v3 had a static `index.php`
- **AI features added** as Phases 10-13 (BYO keys, page generation, chatbot)
- **Workflow modes added** for Claude Code (hooks-enforced)
- **Phase plan grew** from 8 phases to 15

If you find code that contradicts this brief, the brief is wrong — open a fix-it commit.
