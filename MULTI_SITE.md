# Multi-site model

This codebase is built so the same engine can run any number of sites independently. Each site is its own git clone of the repo. Updating one site never affects another. Pinning per site is the natural consequence of this model — a site stays on whatever commit/tag it's checked out at until you explicitly upgrade it.

## Topology

```
/home/cswebserver/
└── public_html/
    └── repositories/
        ├── landingPageBuild/             ← Site 1 (lpb.cswebserver.in)
        │   ├── core/        ← shared engine, but THIS clone's copy
        │   ├── site/        ← Site 1's theme + content
        │   ├── data/        ← Site 1's DB
        │   └── .env         ← Site 1's config
        │
        ├── client2-website/               ← Site 2 (clientB.com), if you add one
        │   ├── core/        ← independent copy of the same engine
        │   ├── site/        ← Site 2's theme + content (forked or templated)
        │   ├── data/        ← Site 2's DB (independent)
        │   └── .env         ← Site 2's config
        │
        └── ...
```

Each domain's doc root points into its own clone's `site/public/`. Each clone has its own `data/content.db`, its own admin user, its own AI keys, its own form submissions.

## Core vs site separation

| | `core/` | `site/` |
|---|---|---|
| What | The engine: bootstrap, DB, config, content getters, helpers, scripts, schema | The site: pages, sections, layout, assets, site-specific schema |
| Touched in Claude mode | `/core-mode` | `/site-mode` |
| Updated how often | Rarely (engine is stable) | Often (content + design iteration) |
| Versioned | `core/VERSION` records the version this clone is on | Whatever the site has shipped |

When you bug-fix the engine, the change lives in `core/`. Each site picks it up by pulling on its own schedule. When you redesign a site, the change lives in `site/` of that one clone — other sites don't see it.

## Adding a new site

Two paths depending on whether the new site reuses Site 1's design:

### A) New site, new design

```bash
cd /home/cswebserver/public_html/repositories/
git clone https://github.com/devenpro/landingPageBuild.git client2-website
cd client2-website
# Customize site/ for the new client (sections, layout, copy, brand)
# Set up its .env, doc root, and run migrations per SETUP_GUIDE.md
```

### B) New site, copy Site 1's design as a starting point

```bash
cd /home/cswebserver/public_html/repositories/
cp -R landingPageBuild client2-website
cd client2-website
# Wipe Site 1's runtime state — Site 2 needs its own
rm -rf .git data/*.db data/*.db-* .env site/public/uploads/*
git init && git remote add origin <new-repo-url>
git add . && git commit -m "Fork from Site 1"
# Set up its .env, doc root, and run migrations per SETUP_GUIDE.md
```

Either way, follow [SETUP_GUIDE.md](SETUP_GUIDE.md) §2-5 for the doc root, `.env`, and initial migration.

## Updating a site's core

Each site has its own checkout, so `git pull` only affects that site:

```bash
cd /home/cswebserver/public_html/repositories/landingPageBuild  # only this site
git fetch
git pull origin main
/usr/local/bin/ea-php83 core/scripts/migrate.php
```

Other sites keep running whatever they have. Update them when convenient.

## Pinning to a tagged release

If `core/` reaches a stable tag like `v1.1.0` and you want to keep a site on that exact version:

```bash
cd /home/cswebserver/public_html/repositories/landingPageBuild
git fetch --tags
git checkout v1.1.0
/usr/local/bin/ea-php83 core/scripts/migrate.php
```

The site is now pinned. To upgrade later: `git checkout v1.2.0`. To roll back: `git checkout v1.1.0` (assuming the new schema is backwards-compatible, or restore `data/` from backup first).

## Workflow for the developer (you)

Most work happens in one mode at a time:

| Goal | Mode | Where to edit |
|---|---|---|
| Add a new section type for everyone | `/core-mode` (if it's a structural concept) OR `/site-mode` (if it's just a new partial in this site's library) | depends |
| Tweak the home page hero copy | `/site-mode` | `site/sections/hero.php` or content via admin (Phase 7+) |
| Add a new core helper function | `/core-mode` | `core/lib/helpers.php` |
| Fix a typo in the README | `/core-mode` | `README.md` |
| Add a new AI provider | `/core-mode` | `core/lib/ai/providers/<name>.php` |
| Build out a new page | `/site-mode` | `site/pages/<slug>.php` (file-based) or via admin (data-driven, Phase 8+) |

## Things to be careful about

- **`.env` and `data/`** never travel via git. They are per-site state, manually managed on the server. Backups are your responsibility.
- **`AI_KEYS_MASTER_KEY`** in `.env` encrypts AI keys. If you lose it, those keys are unrecoverable. Back up the value to a password manager.
- **Schema changes** in `core/migrations/*.sql` apply to every site that pulls them. Always make migrations forward-only and additive (no `DROP COLUMN` until you're sure no site needs the old data).
- **Site forks drift over time.** If you customise Site 1 and Site 2 differently, a core update may behave differently on each. Test in a staging clone before pushing to a live site.
