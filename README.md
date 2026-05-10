# Go Ultra AI — Landing Page

PHP 8 + SQLite landing page for Go Ultra AI (a CreatiSoul LLP product). Designed to deploy to cPanel shared hosting via Git Version Control with no build step.

See [BUILD_BRIEF.md](BUILD_BRIEF.md) for the full spec.

## Local development (XAMPP, Windows)

Prereqs: XAMPP with PHP 8.1+ and the `pdo_sqlite`, `fileinfo`, `session`, `openssl`, `curl` extensions enabled (default on XAMPP).

```powershell
# 1. Clone into XAMPP's htdocs
cd C:\xampp\htdocs
git clone https://github.com/devenpro/landingPageBuild.git
cd landingPageBuild

# 2. Configure
copy .env.example .env
# Edit .env if needed. Default APP_ENV=local works out of the box.

# 3. Run migrations (creates data/content.db)
C:\xampp\php\php.exe scripts\migrate.php

# 4. Create the admin user
C:\xampp\php\php.exe scripts\seed_admin.php

# 5. Start XAMPP Apache, then visit:
#    http://localhost/landingPageBuild/public_html/
```

You should see "Hello from Go Ultra AI" plus a row count from `content_blocks`.

## Layout

```
public_html/   ← cPanel deploys this to /home/cswebserver/public_html/try/
includes/      ← deployed to /home/cswebserver/includes/   (outside webroot)
migrations/    ← deployed to /home/cswebserver/migrations/ (outside webroot)
scripts/       ← deployed to /home/cswebserver/scripts/    (outside webroot)
data/          ← created on host as /home/cswebserver/data/, holds content.db
```

`.env` lives at `/home/cswebserver/.env` in production and is uploaded once manually — deploys never touch it.

## Phase status

- [x] **Phase 1** — Scaffolding (DB, config, migrations, hello-world index)
- [ ] Phase 2 — Static sections from DB
- [ ] Phase 3 — Public form + webhook
- [ ] Phase 4 — Admin auth
- [ ] Phase 5 — Inline editing (text)
- [ ] Phase 6 — Inline editing (media + icons)
- [ ] Phase 7 — Polish + Tailwind compile-down
- [ ] Phase 8 — Launch
