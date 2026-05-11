# Go Ultra AI — Multi-page CMS with AI

Self-hosted PHP+SQLite product for building multi-page business websites with optional AI assistance. Designed to deploy to cPanel shared hosting via Git Version Control with no build step.

See [BUILD_BRIEF.md](BUILD_BRIEF.md) for the full spec, [SETUP_GUIDE.md](SETUP_GUIDE.md) for cPanel deploy, [MULTI_SITE.md](MULTI_SITE.md) for the multi-site model, [AI_GUIDE.md](AI_GUIDE.md) for the AI feature roadmap, and [PHASE_STATUS.md](PHASE_STATUS.md) for the live per-phase tracker.

**For agents (Claude Code on mobile, future autonomous agents):** start with [AGENTS.md](AGENTS.md) — it's the operating manual (mode rules, common commands, gotchas, conventions). Read that plus BUILD_BRIEF and PHASE_STATUS and you're caught up.

## Layout

```
landingPageBuild/                       single git repo, one clone per site
├── core/                               versioned engine (touched in /core-mode)
│   ├── VERSION                         "1.0.0"
│   ├── lib/                            bootstrap, config, db, content, helpers
│   ├── scripts/                        migrate, seed_admin, dev-router
│   ├── build/                          tailwind.config.js + build-css.sh
│   └── migrations/                     core schema
├── site/                               per-site theme + content (touched in /site-mode)
│   ├── public/                         doc root — point cPanel here
│   │   ├── index.php                   front controller
│   │   ├── .htaccess                   security + routing
│   │   ├── assets/                     compiled css, fonts, placeholders
│   │   └── uploads/                    runtime media (gitignored)
│   ├── assets-src/                     CSS source for the Tailwind compile
│   ├── pages/                          file-based pages (Phase 4+)
│   ├── sections/                       reusable section partials
│   ├── layout.php                      head/foot
│   └── migrations/                     site-specific seed
├── data/                               per-site SQLite DB (gitignored)
├── .env                                per-site config (gitignored)
├── .claude/                            workflow modes for Claude Code
└── docs (BUILD_BRIEF, SETUP_GUIDE, MULTI_SITE, AI_GUIDE)
```

## Local development (XAMPP, Windows)

Prereqs: XAMPP with PHP 8.1+ and the `pdo_sqlite`, `fileinfo`, `session`, `openssl`, `curl`, `sodium` extensions enabled (default on XAMPP 8.x).

```powershell
cd C:\xampp\htdocs
git clone https://github.com/devenpro/landingPageBuild.git
cd landingPageBuild

# Configure
copy .env.example .env
# Edit .env: set APP_ENV=local, APP_URL=http://localhost/landingPageBuild/site/public

# Migrate (creates data/content.db)
C:\xampp\php\php.exe core\scripts\migrate.php

# Create admin
C:\xampp\php\php.exe core\scripts\seed_admin.php

# Start XAMPP Apache, visit:
#   http://localhost/landingPageBuild/site/public/
```

## Production deploy (cPanel)

See [SETUP_GUIDE.md](SETUP_GUIDE.md). The short version: clone repo into a folder, point the domain's doc root at `<repo>/site/public/`, upload `.env` to repo root, run `core/scripts/migrate.php`.

## Building styles

`site/public/assets/css/styles.css` is the compiled output of the Tailwind v3 CLI and IS committed to the repo, so cPanel deploys remain build-tool-free. Only re-run the build when you introduce a new utility class in a `.php` file under `site/` or change a theme token in `core/build/tailwind.config.js`.

```bash
# One-shot production build (minified)
core/build/build-css.sh

# Watch mode for local dev (unminified, rebuilds on file save)
core/build/build-css.sh --watch
```

The script downloads the Tailwind CLI standalone binary on first run into `./bin/tailwindcss` (gitignored, ~40MB, platform-specific) and reuses it on subsequent runs. Source CSS lives at `site/assets-src/styles.css`. Config (content paths, brand/ink color scales, Inter font stack) lives at `core/build/tailwind.config.js`.

## Working with Claude Code

Two workflow modes are enforced via PreToolUse hooks. Run the matching slash command at the start of a session before editing:

- `/core-mode` — touch `core/`, repo-level docs/configs, `.claude/`. `site/`/`data/`/`.env` are read-only.
- `/site-mode` — touch `site/`, `data/`, `.env`. `core/` and repo-level configs are read-only.

Without a mode set, all writes are blocked. Switching mid-session: just run the other slash command.

## Phase status

See [PHASE_STATUS.md](PHASE_STATUS.md) for the live tracker. Quick view:

- [x] **Phase 1** — Scaffolding ([PR #1](https://github.com/devenpro/landingPageBuild/pull/1)) — merged
- [ ] Phase 2 — Static landing page ([PR #2](https://github.com/devenpro/landingPageBuild/pull/2)) — *closed/superseded by Phase 3*
- [ ] **Phase 3** — Architecture reset: multi-site `core/`+`site/` + workflow modes ([PR #3](https://github.com/devenpro/landingPageBuild/pull/3)) — open, awaiting merge
- [ ] **Phase 4** — Pages table + hybrid routing + Home as file-based page ([PR #4](https://github.com/devenpro/landingPageBuild/pull/4)) — open
- [ ] **Phase 5** — Public waitlist form + optional webhook ([PR #5](https://github.com/devenpro/landingPageBuild/pull/5)) — open
- [ ] **Phase 6** — Admin auth (login, logout, brute-force lockout) ([PR #6](https://github.com/devenpro/landingPageBuild/pull/6)) — open
- [ ] **Phase 7** — Admin panel base + content editor + forms inbox ([PR #7](https://github.com/devenpro/landingPageBuild/pull/7)) — open
- [ ] **Phase 7.5** — Documentation refresh (BUILD_BRIEF v4, AGENTS, PHASE_STATUS, this file, AI_GUIDE) — this PR
- [ ] Phase 8 — Pages CRUD UI + data-driven page renderer
- [ ] Phase 9 — Inline editing on the public page
- [ ] Phase 10 — AI keys (BYO + libsodium) + provider abstraction (Gemini, OpenRouter)
- [ ] Phase 11 — Admin AI tools (page suggestions, AI page generation)
- [ ] Phase 12 — Media library + uploads UI
- [ ] Phase 13 — Frontend AI features (chatbot, smart forms)
- [ ] Phase 14 — Polish (motion, SEO, JSON-LD, a11y, Lighthouse, Tailwind compile-down, CSV export, no-JS form fallback)
- [ ] Phase 15 — Launch (DNS, final QA, content entry, tag `core/VERSION` as `v1.0.0`)

PRs #3-#7 are stacked. Merge order: lowest first. Each retargets against `main` automatically once its base merges.
