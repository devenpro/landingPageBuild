# Go Ultra AI — Landing Page Build Brief (for Claude Code) — v3
> **Stack pivoted to PHP 8 + SQLite + vanilla JS** for cPanel hosting. No Node, no build step, no SaaS dependencies. Inline editing and webhook form remain as in v2.
---
## 1. Project Overview
Build a high-conversion landing page for **Go Ultra AI**, a sub-product of **CreatiSoul LLP** (Bangalore-based AI content production agency).
The landing page lives on a **subdomain** (e.g. `try.goultraai.com`) hosted on cPanel. The main brand site stays on Drupal. This codebase is intentionally simple — pure PHP and SQLite — so it deploys with a `git pull` on the server and edits via Claude Code from any device including mobile.
---
## 2. Brand Context
**Go Ultra AI** is an AI-powered content strategy and production platform built for freelancers, solo marketers, and small agencies who manage multiple clients' social media. We turn the chaos of content planning, ideation, and creative production into a structured workflow powered by AI.
**Primary audience:**
- Indian freelancers and agency operators (primary)
- Global solo marketers (secondary)
- Small in-house marketing teams handling 3–15 clients
**Brand voice:** Confident, practical, slightly irreverent. Hinglish phrasing welcome where it lands naturally.
**Core value proposition:** Plan, prompt, and produce social content for multiple clients in a fraction of the time — without losing the thinking that makes good content actually good.
**What we are NOT:** generic Canva clone, post scheduler, enterprise SaaS.
---
## 3. Feature Set to Showcase
1. **Multi-Client Social Media Calendar** — One calendar across clients, content pillars, campaign planning, posting cadence templates by industry.
2. **AI Prompt Generation for Visuals** — Image prompts (Midjourney, Imagen, Nano Banana Pro), video prompts (VEO 3.1, Runway, Seedance), brand-consistent templates per client, first-frame/last-frame workflows.
3. **Multi-Client Workspaces** — Separate brand kits per client, quick-switch, scoped asset libraries.
4. **Post Planning & Copy** — Caption variations, hooks, CTAs, hashtags, carousel/Reel scripts in Hinglish or English.
5. **Workflow Output** — Export-ready briefs, prompt-to-tool handoff, version history per post.
---
## 4. Technical Requirements
### Stack
- **PHP 8.1+** (cPanel default)
- **SQLite 3** via PDO (file-based, lives in `data/` outside web root if host allows)
- **Vanilla JS** for inline editing
- **Tailwind CSS via CDN** (no build step) OR hand-rolled CSS — Claude Code's choice; recommend Tailwind CDN for speed of iteration
- **Lucide icons via CDN** (icons referenced by name string in DB)
- Self-hosted **Inter** font in `assets/fonts/`
### Constraints (these matter)
- **No Composer, no npm, no build step.** Files on disk = files in production.
- **No external service dependencies** at runtime (n8n webhook is the only outbound call, and it's optional — submissions are saved locally too).
- Must work on a standard cPanel shared host with PHP 8.1+, SQLite extension enabled (default), and `mod_rewrite` (default).
- Total uncompressed source under 2 MB.
### Repository structure
```
/
├── public_html/                          ← cPanel deploy target
│   ├── index.php                         ← Landing page (renders from DB)
│   ├── .htaccess                         ← URL rewriting + security headers
│   ├── robots.txt
│   ├── sitemap.xml
│   ├── favicon.svg
│   ├── og-image.jpg
│   ├── admin/
│   │   ├── login.php
│   │   └── logout.php
│   ├── api/
│   │   ├── content.php                   ← PATCH content blocks (auth required)
│   │   ├── upload.php                    ← POST media file
│   │   └── form.php                      ← POST waitlist form
│   ├── assets/
│   │   ├── css/
│   │   │   └── styles.css                ← any custom CSS beyond Tailwind
│   │   ├── js/
│   │   │   ├── editor.js                 ← inline edit logic (loads only if logged in)
│   │   │   ├── form.js                   ← form validation + submit
│   │   │   └── motion.js                 ← scroll reveals
│   │   ├── fonts/
│   │   │   └── Inter-*.woff2
│   │   └── icons/                        ← any custom SVGs
│   └── uploads/                          ← user-uploaded media (writable, 0755)
│       └── .htaccess                     ← block PHP execution here
├── data/                                 ← OUTSIDE web root if possible
│   ├── content.db                        ← SQLite database (writable, 0644)
│   └── .htaccess                         ← deny all (in case it's inside web root)
├── includes/                             ← OUTSIDE web root if possible
│   ├── config.php                        ← env loader
│   ├── db.php                            ← PDO connection + init
│   ├── auth.php                          ← session + password verify
│   ├── content.php                       ← content_blocks getters
│   ├── helpers.php                       ← html escaping, slugify, etc.
│   └── sections/                         ← rendered partials
│       ├── navbar.php
│       ├── hero.php
│       ├── social_proof.php
│       ├── features.php
│       ├── how_it_works.php
│       ├── use_cases.php
│       ├── product_demo.php
│       ├── faq.php
│       ├── final_cta.php
│       └── footer.php
├── migrations/
│   ├── 0001_init.sql
│   └── 0002_seed.sql
├── scripts/
│   ├── migrate.php                       ← run all .sql files in migrations/
│   └── seed_admin.php                    ← create admin user with hashed password
├── .env.example
├── .gitignore
├── .cpanel.yml                           ← cPanel auto-deploy script
├── BUILD_BRIEF.md
├── SETUP_GUIDE.md
└── README.md
```
> If the host doesn't allow files outside `public_html/`, place `data/` and `includes/` inside `public_html/` and rely on `.htaccess` deny rules. Claude Code should detect host constraints during setup and adapt.
### .htaccess essentials (root)
- Rewrite all to `index.php` for clean URLs (only `/admin/login`, `/api/*`, and asset paths bypass)
- Force HTTPS
- Security headers: `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Content-Security-Policy` (loose enough for Tailwind CDN + Lucide)
- Deny access to `.env`, `.git`, `*.md`, `migrations/`, `data/`
### .htaccess for `uploads/`
```
php_flag engine off
<FilesMatch "\.(php|phtml|phar|pl|py|cgi|sh)$">
  Require all denied
</FilesMatch>
```
---
## 5. Sections (build in this order)
1. **Navbar** — Logo, Features link, CTA → form. Footer carries the `/admin/login` link discreetly.
2. **Hero** — Headline, subheadline, primary CTA, hero visual.
3. **Social Proof Strip** — Placeholder OK for v1.
4. **Features Grid** — 5–6 cards (icon + title + 1-line description).
5. **How It Works** — 4 steps: *Add client → Plan calendar → Generate prompts → Export brief*.
6. **Use Cases** — Solo freelancer, Small agency owner, In-house marketer.
7. **Product Demo** — Image or short looping video.
8. **FAQ** — 5–7 objections.
9. **Final CTA + Waitlist Form**.
10. **Footer** — Links, "A CreatiSoul LLP product", social, legal, admin login link.
Every section is a PHP partial under `includes/sections/` that fetches its content via `getContent('section.key')` and renders `<editable>` wrappers around editable values.
---
## 6. Admin Login + Inline Editing
### 6.1 Auth
- Single admin row in `admin_users` table (email + bcrypt password hash)
- Login page: `/admin/login`
- On success: PHP session with `$_SESSION['admin_user_id']`
- Cookie: `HttpOnly`, `Secure`, `SameSite=Strict`
- Session timeout: 8 hours of inactivity
- CSRF token on every state-changing request (login, save, upload, logout)
- Logout: destroys session, redirects to `/`
- Brute-force protection: lockout after 5 failed attempts in 10 minutes (track in DB by IP)
### 6.2 Edit mode
When `$_SESSION['admin_user_id']` is set, the public landing page renders with edit mode on. No separate dashboard.
**EditModeBar** (fixed top, only when logged in):
- "Edit mode: ON" indicator
- **Save** button (sends batched changes)
- **Discard** button (reloads page)
- **Logout** button
- Unsaved changes counter
The `editor.js` file is `<script>`-included **only when the user is logged in** (PHP conditional). Logged-out visitors never download it.
### 6.3 What's editable
| Element | Wrapper class | Behavior in edit mode |
|---|---|---|
| Headlines, body copy, button text, FAQ Q&A | `<span data-edit="text" data-key="hero.headline">` | `contentEditable=true` on click, blur to stage |
| Hero image, demo image | `<img data-edit="image" data-key="hero.image">` | Click → upload modal |
| Demo video | `<video data-edit="video" data-key="demo.video">` | Click → upload modal |
| Feature card icons, step icons | `<i data-edit="icon" data-key="feature.1.icon">` | Click → searchable Lucide picker |
All changes staged in `window.pendingChanges` until Save is clicked. Save sends one `PATCH /api/content.php` with batched payload + CSRF token.
### 6.4 Database schema (`migrations/0001_init.sql`)
```sql
CREATE TABLE IF NOT EXISTS content_blocks (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  key TEXT UNIQUE NOT NULL,
  value TEXT NOT NULL,                       -- plain text, URL, icon name, or JSON-encoded list
  type TEXT NOT NULL CHECK(type IN ('text','image','video','icon','list','seo')),
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_by INTEGER REFERENCES admin_users(id)
);
CREATE INDEX IF NOT EXISTS idx_content_blocks_key ON content_blocks(key);
CREATE TABLE IF NOT EXISTS media_assets (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  filename TEXT NOT NULL UNIQUE,             -- stored name e.g. "1715347200_hero.jpg"
  original_name TEXT NOT NULL,
  mime_type TEXT NOT NULL,
  size_bytes INTEGER NOT NULL,
  kind TEXT NOT NULL CHECK(kind IN ('image','video')),
  uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  uploaded_by INTEGER REFERENCES admin_users(id)
);
CREATE TABLE IF NOT EXISTS admin_users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT UNIQUE NOT NULL,
  password_hash TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS login_attempts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  ip_address TEXT NOT NULL,
  email TEXT,
  success INTEGER NOT NULL,
  attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_login_attempts_ip ON login_attempts(ip_address, attempted_at);
CREATE TABLE IF NOT EXISTS form_submissions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  full_name TEXT NOT NULL,
  email TEXT NOT NULL,
  phone TEXT NOT NULL,
  role TEXT NOT NULL,
  clients_managed TEXT,
  bottleneck TEXT,
  user_agent TEXT,
  referrer TEXT,
  ip_address TEXT,
  webhook_status TEXT,                       -- "sent" | "failed" | "skipped"
  webhook_response TEXT,
  submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```
### 6.5 Seed data (`migrations/0002_seed.sql`)
Insert one row per `content_blocks.key` used anywhere on the page. Use realistic placeholder copy in the brand voice. Examples:
- `hero.headline` — "Plan a week of social content for every client. In an hour."
- `hero.subheadline` — "Go Ultra AI is the brain behind your social calendar..."
- `hero.cta_label` — "Get early access"
- `hero.image` — `/uploads/hero-placeholder.jpg`
- `feature.1.icon` — `calendar-days`
- `feature.1.title` — "Multi-Client Calendar"
- `feature.1.body` — "..."
(Cover all sections; Claude Code should produce the full seed file based on the section list above.)
### 6.6 Upload handling (`api/upload.php`)
- Auth required (session check + CSRF token)
- Accept `multipart/form-data` with one file per request
- **Whitelist** MIME types: `image/jpeg`, `image/png`, `image/webp`, `image/svg+xml`, `video/mp4`, `video/webm`
- **Max size:** 5 MB for images, 50 MB for videos (configurable via `.env`)
- Verify MIME type by reading file header (`finfo_file`), not by trusting the client
- Sanitize filename: lowercase, `[a-z0-9._-]` only, prefix with timestamp
- Save to `public_html/uploads/`
- Insert row into `media_assets`
- Return JSON: `{ "url": "/uploads/1715347200_hero.jpg", "id": 42 }`
- On error, return JSON `{ "error": "message" }` with appropriate status code
### 6.7 Editing UX
- **Hover state in edit mode:** dashed violet outline + small pencil icon (CSS-only)
- **Active edit:** solid violet border
- **Image/video upload:** progress bar, preview after upload, undo before save
- **Keyboard:** `Esc` exits current edit; `Cmd/Ctrl+S` triggers Save
- **Mobile editing:** Text editing works on phone; image upload prefers desktop but should function
- **Optimistic UI:** show changes immediately, rollback + toast on save failure
---
## 7. Public Form (`api/form.php`)
**Fields** (HTML form posts to same endpoint):
- Full Name (required, text, max 100)
- Email (required, email, max 150)
- Phone / WhatsApp (required, with country code, default `+91`)
- Role (required, dropdown: *Freelancer / Agency Owner / In-house Marketer / Other*)
- Clients managed (optional, dropdown: *1–3 / 4–10 / 10+ / Just exploring*)
- Biggest content bottleneck (optional, textarea, max 500)
- Honeypot field (hidden, must be empty)
- CSRF token
**Server behavior:**
1. Validate CSRF token
2. Reject if honeypot non-empty (return generic success to confuse bots)
3. Validate fields server-side (don't trust client)
4. Insert into `form_submissions` immediately (so we don't lose data)
5. Attempt POST to `WEBHOOK_URL` from `.env`:
   ```json
   {
     "full_name": "...",
     "email": "...",
     "phone": "+91...",
     "role": "...",
     "clients_managed": "...",
     "bottleneck": "...",
     "submitted_at": "2026-05-10T10:30:00Z",
     "source": "go-ultra-ai-landing",
     "user_agent": "...",
     "referrer": "..."
   }
   ```
6. Update `webhook_status` and `webhook_response` columns based on outcome
7. Return JSON success regardless of webhook outcome (we have the lead in DB)
8. Client-side: replace form with success message + WhatsApp/Calendly CTA
**Client validation:** `assets/js/form.js` — vanilla JS, no library. Validate on blur and on submit. Disable button + show spinner on submit. Use `fetch()` with JSON, fall back to standard form POST if JS disabled.
**Webhook URL:** stored in `.env` as `WEBHOOK_URL`. Replace `[YOUR_N8N_WEBHOOK_URL]` during setup.
---
## 8. Performance, SEO, Accessibility
- Lighthouse ≥ 95 on Performance, Accessibility, Best Practices, SEO (logged-out, mobile)
- Open Graph + Twitter Card meta in `<head>` (values from `content_blocks` with `seo.*` keys, also editable)
- JSON-LD: `Organization` + `WebSite` + `Product` (in `<head>`)
- `sitemap.xml` and `robots.txt` static files
- Self-host Inter font; preload the weights actually used
- Tailwind CDN: use the JIT script for production-friendly output, OR write critical CSS inline + load Tailwind only in dev (Claude Code's call)
- Lazy-load below-the-fold images with `loading="lazy"`
- All images served as WebP where possible (with JPEG fallback in `<picture>`)
- Total page weight (initial) under 500 KB excluding hero media
- `editor.js` (~10 KB) loads ONLY when admin session is active
**Accessibility:**
- Semantic HTML throughout
- Full keyboard navigation
- Visible focus states
- WCAG AA contrast minimum
- `aria-label` on icon-only buttons
- Form errors via `aria-live="polite"`
---
## 9. Design Direction
- **Aesthetic:** Modern, AI-confident, warm. No cold-blue-gradient AI cliché.
- **Palette:** Bold violet accent (`#7C3AED` family) + neutral grays + near-white background. Avoid default dark mode.
- **Type:** Inter (or Geist if licensing allows). Big headline scale (≥60px desktop), generous line-height.
- **Visuals:** Real product UI screenshots > generic AI art. **No robot/brain stock imagery.**
- **Motion:** Subtle scroll reveals, gentle hover lifts. Respect `prefers-reduced-motion`.
- **Mobile:** Mobile-first. Hero readable in one phone screen. Tap targets 44px+.
- **Edit mode visual:** Dashed violet outline + pencil icon on hover.
---
## 10. Configuration (`.env` / `.env.example`)
See `.env.example` in the repo root for the canonical template. Key changes from earlier drafts:
- `CSRF_SECRET` removed — CSRF tokens are session-bound and rotated on login.
- `INCLUDES_PATH` added so production can resolve outside-webroot includes.
- When `APP_ENV=local`, `DB_PATH` and `INCLUDES_PATH` are auto-resolved as siblings of `public_html/` regardless of what the .env says.
---
## 11. cPanel Deploy Script (`.cpanel.yml`)
See `.cpanel.yml` in repo root. Username `cswebserver` and deploy path `/home/cswebserver/public_html/try/` are pre-filled for this host.
---
## 12. Build Plan (Phased)
**Phase 1 — Scaffolding** ✅
**Phase 2 — Static sections** (next)
**Phase 3 — Public form**
**Phase 4 — Admin auth**
**Phase 5 — Inline editing for text**
**Phase 6 — Inline editing for media + icons**
**Phase 7 — Polish**
**Phase 8 — Launch**
---
## 13. Working Style
- Commit early, commit often, clear messages
- One PR per phase
- **Don't over-engineer.** Single-user admin. No roles, no workflow, no draft/publish split — saves go straight live.
- Every PHP file starts with a top-level comment explaining its purpose
- `// TODO: VERIFY` comments where you're making assumptions I should check
- Edit-mode JS must NOT load for logged-out visitors
- All DB writes go through prepared statements (PDO with bound params) — no string concatenation
- All user input escaped on output via `htmlspecialchars()` helper
- All file paths normalized with `realpath()` and checked against allowed prefixes (no path traversal)
