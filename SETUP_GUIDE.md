# cPanel Setup Guide — Go Ultra AI

End-to-end deploy walkthrough for cPanel shared hosting. Tested on the `cswebserver` account hosting `lpb.cswebserver.in`. Should work on any cPanel host with **PHP 8.1+** and the **pdo_sqlite** + **sodium** extensions.

---

## 1. Clone the repository

cPanel → **Git Version Control** → **Create**.

| Field | Value |
|---|---|
| Clone URL | `https://github.com/devenpro/landingPageBuild.git` |
| Repository Path | `/home/cswebserver/public_html/repositories/landingPageBuild` |
| Repository Name | `landingPageBuild` |

Click **Create**. cPanel clones the full repo at that path. After it finishes you should see `core/`, `site/`, `data/`, `README.md`, etc. inside.

> **Why a folder under `public_html/`?** Many cPanel hosts disallow files outside `public_html/`. Putting the repo *inside* it keeps everything in one mountable location. The doc root targets only `site/public/` so private dirs (`core/`, `site/sections/`, `data/`, `.env`) are not directly addressable.

---

## 2. Point the domain at `site/public/`

cPanel → **Domains** → **Manage** next to your domain (e.g. `lpb.cswebserver.in`).

In **New Document Root**, enter:

```
/public_html/repositories/landingPageBuild/site/public
```

Click **Update**.

---

## 3. Upload `.env`

cPanel → **File Manager** → navigate to `/public_html/repositories/landingPageBuild/`.

If you don't see hidden files, click the **Settings** gear (top-right) → check **Show Hidden Files (dotfiles)** → **Save**.

Click **+ File**, name it `.env`, then **Edit** and paste:

```ini
APP_ENV=production
APP_URL=https://lpb.cswebserver.in
SITE_NAME="Go Ultra AI"

ADMIN_EMAIL=debendra@creatisoul.com
SESSION_LIFETIME_HOURS=8

WEBHOOK_URL=
WEBHOOK_TIMEOUT_SECONDS=10

MAX_IMAGE_BYTES=5242880
MAX_VIDEO_BYTES=52428800

# Generate this once locally:
#   php -r 'echo base64_encode(random_bytes(32)), "\n";'
# CRITICAL: keep a secure backup. If lost, all stored AI API keys
# (Phase 10+) become unreadable.
AI_KEYS_MASTER_KEY=
```

Replace `APP_URL` with your actual domain. Generate `AI_KEYS_MASTER_KEY` later when you reach Phase 10 — leave blank for now.

> `.env` is gitignored, never overwritten by deploys, never shipped in clones. It's the one file you maintain by hand on each server.

---

## 4. Run migrations once

cPanel → **Terminal** (or SSH if available):

```bash
/usr/local/bin/ea-php83 /home/cswebserver/public_html/repositories/landingPageBuild/core/scripts/migrate.php
```

Expect:

```
  + [core] 0001_init.sql applied
  + [site] 0001_seed.sql applied
Applied 2 migration(s). 0 already applied.
DB:   /home/cswebserver/public_html/repositories/landingPageBuild/data/content.db
Core: 1.0.0
```

Common issues:

- **"Could not open input file"** → double-check the path. Use the absolute path above.
- **"PDO/SQLite extension not found"** → cPanel → **MultiPHP INI Editor** → select PHP 8.3 → enable `pdo_sqlite` and `sqlite3` → **Apply**.
- **"sodium not found"** → same place, enable `sodium` (needed for AI key encryption from Phase 10).

If `ea-php83` isn't right on your host, list available versions: `ls /usr/local/bin/ea-php*` and use the highest 8.x.

---

## 5. Visit the site

Open `https://your-domain.com` in a browser. You should see the full landing page.

If you see a blank page or 500: cPanel → **Errors** for the PHP error log. Most likely:

- `.env` missing or wrong path → step 3
- `data/content.db` missing → step 4 didn't complete
- PHP < 8.1 → cPanel → **MultiPHP Manager** → bump to 8.3

---

## 6. Create the admin user (Phase 6 onwards)

```bash
/usr/local/bin/ea-php83 /home/cswebserver/public_html/repositories/landingPageBuild/core/scripts/seed_admin.php
```

Prompts for a password. Reads `ADMIN_EMAIL` from `.env`, writes a bcrypt-hashed row to `admin_users`. Phase 6 ships the admin login UI.

---

## 7. Future deploys

cPanel → **Git Version Control** → **Manage** next to repo → **Pull or Deploy**.

- **Update from Remote** pulls the latest commit
- **Deploy HEAD Commit** runs `.cpanel.yml` (re-runs `migrate.php` to apply any new SQL)

For most updates: click **Update from Remote**, then **Deploy HEAD Commit**.

---

## 8. Pinning to a release (later phases)

When `core/` reaches a tagged release (e.g. `v1.1.0`), pinning a site to that exact code:

```bash
cd /home/cswebserver/public_html/repositories/landingPageBuild
git fetch --tags
git checkout v1.1.0
/usr/local/bin/ea-php83 core/scripts/migrate.php
```

Other sites stay on whatever tag/commit they're checked out at. Update them on their own schedule. See [MULTI_SITE.md](MULTI_SITE.md) for the full workflow.

---

## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| Browser shows directory listing or `cgi-bin` only | Doc root pointing wrong | Step 2 — re-check path, click Update |
| 500 error on every page | `.env` missing or PHP error | Check cPanel error log; verify `.env` is at `<repo>/.env` |
| Blank page, no error | PHP errors silenced; missing extension | Enable `pdo_sqlite` (and `sodium` for Phase 10+) |
| `migrate.php` "Could not open input file" | Wrong path | Use the absolute path from step 4 |
| Hero/demo images broken | `assets/placeholders/` missing on server | Re-pull repo; they're committed |
| `/admin/login` 404 | Phase 6 hasn't shipped | Wait, or apply via direct SQL |
