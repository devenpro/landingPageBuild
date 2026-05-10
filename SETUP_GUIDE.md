# cPanel Setup Guide — Go Ultra AI Landing Page

End-to-end deploy walkthrough for cPanel shared hosting. Tested on the `cswebserver` account hosting `lpb.cswebserver.in`. Should work on any cPanel host with **PHP 8.1+** and the **pdo_sqlite** extension.

---

## 1. Clone the repository

cPanel → **Git Version Control** → **Create**.

| Field | Value |
|---|---|
| Clone URL | `https://github.com/devenpro/landingPageBuild.git` |
| Repository Path | `/home/cswebserver/public_html/repositories/landingPageBuild` |
| Repository Name | `landingPageBuild` |

Click **Create**. cPanel will clone the full repo into that path. After it finishes, the folder will contain `public_html/`, `README.md`, `BUILD_BRIEF.md`, `SETUP_GUIDE.md`, `.cpanel.yml`, `.env.example`, etc.

> **Why a folder under `public_html/`?** Some cPanel hosts disallow files outside `public_html/`. Putting the repo *inside* `public_html/` keeps everything in one mountable location. The `public_html/.htaccess` denies web access to `includes/`, `scripts/`, `migrations/`, `data/`, and `.env`, so private files stay private.

---

## 2. Point the domain at the repo's `public_html/`

cPanel → **Domains** → Click **Manage** next to the domain (e.g. `lpb.cswebserver.in`).

In the **New Document Root** field, enter:

```
/public_html/repositories/landingPageBuild/public_html
```

Click **Update**. Apache picks up the change immediately on most hosts.

> If you have a different domain or subdomain (e.g. `try.goultraai.com`), use that domain's Manage page and the same doc-root path.

---

## 3. Upload `.env`

cPanel → **File Manager** → navigate to `/public_html/repositories/landingPageBuild/public_html/`.

If you don't see `.env`, click the **Settings** gear (top-right) → check **Show Hidden Files (dotfiles)** → **Save**.

Click **+ File**, name it `.env`, then click **Edit** on the new file and paste:

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
```

Save and close. Replace `APP_URL` with your actual domain.

> **`.env` is never committed and never overwritten by deploys.** It's the one file you maintain by hand on each server.

---

## 4. Run migrations once

cPanel → **Terminal** (or SSH if available). Run:

```bash
/usr/local/bin/ea-php83 /home/cswebserver/public_html/repositories/landingPageBuild/public_html/scripts/migrate.php
```

You should see:

```
  + 0001_init.sql applied
  + 0002_seed.sql applied
  + 0003_seed.sql applied
Applied 3 migration(s).
DB: /home/cswebserver/public_html/repositories/landingPageBuild/public_html/data/content.db
```

If you get **"Could not open input file"**: double-check the path. If you get **"PDO/SQLite extension not found"**: cPanel → **MultiPHP INI Editor** → select PHP 8.3 → enable `pdo_sqlite` and `sqlite3` → **Apply**.

If `ea-php83` isn't the right binary on your host, check the available versions with `ls /usr/local/bin/ea-php*` and use the highest 8.x version.

---

## 5. Visit the site

Open `https://your-domain.com` in a browser. You should see the full landing page rendering with the seeded brand-voice copy.

If you see a blank page or 500 error, check **cPanel → Errors** for the PHP error log. Most likely causes:

- `.env` missing or has wrong path → step 3
- `data/content.db` missing → step 4 didn't run successfully
- PHP version is older than 8.1 → cPanel → **MultiPHP Manager** → bump to 8.3

---

## 6. Create the admin user (Phase 4 onwards)

```bash
/usr/local/bin/ea-php83 /home/cswebserver/public_html/repositories/landingPageBuild/public_html/scripts/seed_admin.php
```

You'll be prompted for a password. The script reads `ADMIN_EMAIL` from `.env` and writes a bcrypt-hashed row to `admin_users`.

> Phase 4 hasn't shipped admin login yet, but the user is set up and ready for when it lands.

---

## 7. Future deploys

cPanel → **Git Version Control** → click **Manage** next to the repo → **Pull or Deploy** tab.

- **Update from Remote** pulls the latest commit from GitHub
- **Deploy HEAD Commit** runs `.cpanel.yml` (which re-runs `migrate.php` to apply any new SQL)

For a typical deploy: click **Update from Remote**, then **Deploy HEAD Commit**. Site updates in seconds.

---

## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| Browser shows directory listing or `cgi-bin` only | Doc root pointing at wrong folder | Step 2 — re-check the path and click Update |
| 500 error on every page | `.env` missing or PHP error | Check cPanel error log; verify `.env` exists at `public_html/.env` |
| Blank page, no error | PHP errors silenced; likely missing extension | Enable `pdo_sqlite` in MultiPHP INI Editor |
| `migrate.php` says "Could not open input file" | Wrong path | Use absolute path from step 4 |
| Hero/demo images broken | `assets/placeholders/` files missing from server | Re-pull repo; they're committed |
| `/admin/login` 404 | Phase 4 hasn't shipped yet | Wait for the next phase, or apply via direct SQL |

For anything else, check the PHP error log: cPanel → **Errors**.
