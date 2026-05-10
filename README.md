# Go Ultra AI — Multi-page CMS with AI

Self-hosted PHP+SQLite product for building marketing/landing sites with optional AI assistance. Designed to deploy to cPanel shared hosting via Git Version Control with no build step.

See [BUILD_BRIEF.md](BUILD_BRIEF.md) for the full spec, [SETUP_GUIDE.md](SETUP_GUIDE.md) for cPanel deploy, [MULTI_SITE.md](MULTI_SITE.md) for the multi-site model, and [AI_GUIDE.md](AI_GUIDE.md) for the AI feature roadmap.

## Layout

```
landingPageBuild/                       single git repo, one clone per site
├── core/                               versioned engine (touched in /core-mode)
│   ├── VERSION                         "1.0.0"
│   ├── lib/                            bootstrap, config, db, content, helpers
│   ├── scripts/                        migrate, seed_admin
│   └── migrations/                     core schema
├── site/                               per-site theme + content (touched in /site-mode)
│   ├── public/                         doc root — point cPanel here
│   │   ├── index.php                   front controller
│   │   ├── .htaccess                   security + routing
│   │   ├── assets/                     css, fonts, placeholders
│   │   └── uploads/                    runtime media (gitignored)
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

## Working with Claude Code

Two workflow modes are enforced via PreToolUse hooks. Run the matching slash command at the start of a session before editing:

- `/core-mode` — touch `core/`, repo-level docs/configs, `.claude/`. `site/`/`data/`/`.env` are read-only.
- `/site-mode` — touch `site/`, `data/`, `.env`. `core/` and repo-level configs are read-only.

Without a mode set, all writes are blocked. Switching mid-session: just run the other slash command.

## Phase status

- [x] **Phase 1** — Scaffolding ([PR #1](https://github.com/devenpro/landingPageBuild/pull/1))
- [ ] Phase 2 — Static landing page (paused; superseded by Phase 3)
- [ ] **Phase 3** — Architecture reset: multi-site + workflow modes (current)
- [ ] Phase 4 — Pages table + hybrid routing
- [ ] Phase 5 — Public form + webhook
- [ ] Phase 6 — Admin auth + login
- [ ] Phase 7 — Admin panel base + content_blocks CRUD
- [ ] Phase 8 — Admin pages CRUD
- [ ] Phase 9 — Inline editing
- [ ] Phase 10 — AI key management + provider abstraction
- [ ] Phase 11 — Admin AI tools (page suggestions, AI page generation)
- [ ] Phase 12 — Media library + uploads UI
- [ ] Phase 13 — Frontend AI features (chatbot, smart forms)
- [ ] Phase 14 — Polish (motion, SEO, JSON-LD, a11y, Lighthouse, Tailwind compile-down)
- [ ] Phase 15 — Launch
