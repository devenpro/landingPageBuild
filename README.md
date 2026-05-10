# Go Ultra AI — Landing Page

PHP 8 + SQLite landing page for Go Ultra AI (a CreatiSoul LLP product). Designed to deploy to cPanel shared hosting via Git Version Control with no build step.

See [BUILD_BRIEF.md](BUILD_BRIEF.md) for the full spec and [SETUP_GUIDE.md](SETUP_GUIDE.md) for the cPanel deploy walkthrough.

## Layout

The entire repo is self-contained under `public_html/`. The doc root for the domain points directly into that folder. No file copying happens at deploy.

```
landingPageBuild/
├── public_html/             ← doc root
│   ├── index.php            ← entry point
│   ├── .htaccess            ← deny private dirs + sensitive files
│   ├── .env                 ← runtime config (uploaded once, gitignored)
│   ├── assets/              ← CSS, JS, fonts, placeholder SVGs
│   ├── uploads/             ← user-uploaded media (writable, gitignored)
│   ├── includes/            ← PHP partials (denied via .htaccess)
│   ├── scripts/             ← CLI tools (migrate, seed_admin)
│   ├── migrations/          ← SQL files
│   └── data/                ← SQLite DB (denied via .htaccess)
├── README.md
├── SETUP_GUIDE.md
├── BUILD_BRIEF.md
└── .cpanel.yml              ← deploy hook (just runs migrations)
```

## Local development (XAMPP, Windows)

Prereqs: XAMPP with PHP 8.1+ and the `pdo_sqlite`, `fileinfo`, `session`, `openssl`, `curl` extensions enabled (default on XAMPP).

```powershell
# 1. Clone into XAMPP's htdocs
cd C:\xampp\htdocs
git clone https://github.com/devenpro/landingPageBuild.git
cd landingPageBuild

# 2. Configure
copy .env.example public_html\.env
# Edit public_html\.env — set APP_ENV=local and APP_URL=http://localhost/landingPageBuild/public_html

# 3. Run migrations (creates public_html/data/content.db)
C:\xampp\php\php.exe public_html\scripts\migrate.php

# 4. Create the admin user
C:\xampp\php\php.exe public_html\scripts\seed_admin.php

# 5. Start XAMPP Apache, then visit:
#    http://localhost/landingPageBuild/public_html/
```

## Production deploy (cPanel)

See [SETUP_GUIDE.md](SETUP_GUIDE.md) for the full walkthrough. The short version:

1. cPanel → Git Version Control → Clone the repo into a folder under `public_html/`
2. cPanel → Domains → Set the domain's doc root to `<repo>/public_html/`
3. Upload `.env` to `<repo>/public_html/.env` via File Manager
4. Run `php public_html/scripts/migrate.php` once via Terminal/SSH
5. Set up cPanel Git Version Control "Deploy HEAD Commit" — `.cpanel.yml` re-runs migrations on each deploy

## Phase status

- [x] **Phase 1** — Scaffolding
- [x] **Phase 2** — Static landing page rendered from DB (current PR; includes self-contained-layout fix)
- [ ] Phase 3 — Public form + webhook
- [ ] Phase 4 — Admin auth
- [ ] Phase 5 — Inline editing (text)
- [ ] Phase 6 — Inline editing (media + icons)
- [ ] Phase 7 — Polish + Tailwind compile-down
- [ ] Phase 8 — Launch
