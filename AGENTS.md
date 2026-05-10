# AGENTS.md — operating manual

For Claude Code (desktop or mobile) and future autonomous agents picking up work on this repo. Self-contained — read this plus [BUILD_BRIEF.md](BUILD_BRIEF.md) and [PHASE_STATUS.md](PHASE_STATUS.md) and you're caught up.

---

## Quick start (cold clone → working site in 5 minutes)

```bash
# 1. Clone
git clone https://github.com/devenpro/landingPageBuild.git
cd landingPageBuild

# 2. Configure (per-machine; .env is gitignored)
cp .env.example .env
# Edit .env: set APP_ENV=local and APP_URL=http://localhost/landingPageBuild/site/public

# 3. Migrate (creates data/content.db)
php core/scripts/migrate.php

# 4. Seed admin user (prompts for password, min 8 chars)
php core/scripts/seed_admin.php

# 5. Run the site
# Option A: XAMPP Apache (uses site/public/.htaccess for routing — preferred)
#    Visit http://localhost/landingPageBuild/site/public/
#
# Option B: PHP built-in server (uses dev-router.php to mimic mod_rewrite)
php -S 127.0.0.1:8765 -t site/public core/scripts/dev-router.php
#    Visit http://127.0.0.1:8765/
```

Public site: `/`. Admin login: `/admin/login.php`.

---

## Project layout (one-liner)

`core/` (engine) + `site/` (per-site theme/content) + `data/` (per-site DB) + `.env` (per-site config) + `.claude/` (workflow modes). Full diagram in [BUILD_BRIEF.md §4](BUILD_BRIEF.md).

---

## Workflow modes (Claude Code)

Two modes, hard-enforced via a `PreToolUse` hook on `Write`/`Edit`/`MultiEdit`. **Set the mode at the start of every session before editing anything.**

| Mode | Writable | Read-only |
|---|---|---|
| `/core-mode` | `core/**`, `.claude/**`, repo-root docs/configs (`README.md`, `BUILD_BRIEF.md`, `MULTI_SITE.md`, `SETUP_GUIDE.md`, `AI_GUIDE.md`, `AGENTS.md`, `PHASE_STATUS.md`, `.gitignore`, `.cpanel.yml`, `.env.example`) | `site/**`, `data/**`, `.env` |
| `/site-mode` | `site/**`, `data/**`, `.env` | `core/**`, `.claude/**`, repo-root docs/configs |
| (none set) | nothing | everything |

Switch mid-session by running the other slash command. Don't try to bypass the hook with `Bash` redirection — the rule is documented and intentional.

If you're invoked outside Claude Code (CLI cron, script, etc.), you can set the mode manually: `echo "core" > .claude-mode` or `echo "site" > .claude-mode`. The hook reads this file on every Write/Edit attempt.

---

## Common commands

### Database

```bash
# Apply pending migrations (idempotent; safe to re-run)
php core/scripts/migrate.php

# Inspect tables
php -r 'foreach (new PDO("sqlite:data/content.db")->query("SELECT name FROM sqlite_master WHERE type=\"table\" ORDER BY name") as $r) echo $r["name"], "\n";'

# Quick row count
php -r 'echo (int)(new PDO("sqlite:data/content.db"))->query("SELECT COUNT(*) FROM content_blocks")->fetchColumn(), "\n";'

# Reseed admin (prompts for new password)
php core/scripts/seed_admin.php
```

### Local dev server

```bash
# PHP built-in server with the routing-aware dev-router (NEEDED for clean URLs)
php -S 127.0.0.1:8765 -t site/public core/scripts/dev-router.php
```

Without `dev-router.php` as the third argument, non-existent paths (e.g. `/about`) won't reach the front controller.

### Logging in via curl (for testing admin endpoints)

```bash
JAR=/tmp/jar.txt; rm -f $JAR

# Get login form + capture CSRF
curl -sS -c $JAR -o /tmp/login.html http://127.0.0.1:8765/admin/login.php
TOKEN=$(grep -oE 'name="csrf" value="[^"]+"' /tmp/login.html | head -1 | sed 's/.*value="//;s/"//')

# POST credentials
curl -sS -b $JAR -c $JAR -o /dev/null \
  -d "csrf=$TOKEN&email=admin@example.com&password=YOUR_PASSWORD" \
  http://127.0.0.1:8765/admin/login.php

# Now use $JAR for authenticated requests
curl -sS -b $JAR http://127.0.0.1:8765/admin/dashboard.php
```

### Testing the workflow-mode hook directly

```bash
# Mock a Write call and check the verdict
echo '{"tool_name":"Write","tool_input":{"file_path":"C:/xampp/htdocs/landingPageBuild/site/sections/hero.php"}}' \
  | php .claude/scripts/check-mode.php; echo "exit=$?"
# exit=0 = allow, exit=2 = block
```

---

## Verification patterns

### Page renders end-to-end

```bash
curl -sS -o /tmp/p.html -w "%{http_code} %{size_download}b\n" http://127.0.0.1:8765/
grep -c "data-edit" /tmp/p.html  # ~80+ markers expected on the home page
```

### API endpoint behaves correctly

Use a cookie jar so PHPSESSID + CSRF persist. Test method check (405), unauth (401), bad CSRF (403), valid request (200), invalid input (422).

### Mode hook blocks the right paths

Run the hook against several `file_path` values across all four mode states (`core`, `site`, none, unknown). Verify exit codes and stderr messages.

---

## Gotchas (caught during build — don't re-discover)

1. **SQLite `CURRENT_TIMESTAMP` is UTC.** When comparing in PHP, use `gmdate('Y-m-d H:i:s', time() - $seconds)` not `(new DateTimeImmutable('-N minutes'))->format(...)` (which uses the local timezone). Caught in Phase 6: lockout silently failed on IST servers because cutoff was 5h30m in the future of stored timestamps.

2. **Windows Git Bash transcodes UTF-8 to cp1252** when passing args to native binaries. If you're testing endpoints with non-ASCII data (e.g. en-dash `–`), the byte sequence sent isn't UTF-8 — it's cp1252. Use percent-encoded UTF-8 in test data: `clients_managed=4%E2%80%9310` (not `clients_managed=4–10`). Real browsers always send UTF-8, so this is a test artifact only.

3. **PHP built-in server doesn't honor `.htaccess`.** Use `core/scripts/dev-router.php` as the router script. Apache/cPanel use `.htaccess` directly in production; dev-router doesn't ship to production.

4. **Session must start before any output.** `csrf_session_start()` is called from `core/lib/bootstrap.php` at the top of every HTTP request (not from inside sections). If you call it later, `Set-Cookie: PHPSESSID=...` can't go out — headers are already sent — and CSRF check fails on the next request.

5. **PHP CLI on Windows can't easily disable terminal echo.** `seed_admin.php` warns about this and prints "Password (visible on Windows)". On Linux/cPanel it uses `stty -echo`. Acceptable trade-off for a single-admin tool.

6. **cPanel Git VC default clone path is `/home/USER/repositories/<name>/`** (outside `public_html/`). The current `lpb.cswebserver.in` setup overrides this to `/home/cswebserver/public_html/repositories/landingPageBuild/` and points the domain doc root at `<repo>/site/public/`. See [SETUP_GUIDE.md](SETUP_GUIDE.md).

7. **`php -S` can't be backgrounded cleanly from these tool wrappers.** When killed via `taskkill`, the background-task event reports `exit code 1` even though the kill succeeded. Ignore the exit code; check `netstat` for the port.

8. **Migration ordering matters across sources.** `migrate.php` applies all `core/migrations/*.sql` first, then all `site/migrations/*.sql`. If a site migration depends on a table created by a core migration, you're fine. The reverse (core migration depending on a site table) would break — never write that.

9. **`updated_by` on `content_blocks`** — set this to the current admin's ID on every UPDATE so audit trails work later. `/api/content.php` does this.

10. **JSON responses must set `Cache-Control: no-store`** to prevent intermediary caching of authenticated payloads. Both `/api/form.php` and `/api/content.php` do this.

---

## Commit + PR conventions

### Commit message structure

```
Phase N: <short title>

Brief paragraph: why this change exists (the problem it solves, not just the
diff). Usually 2-4 sentences.

Why:
- Bullet on the underlying motivation
- Bullet on what was blocking before this

Changes:
- file/path/here.php (new): one-line description of what it does
- file/path/elsewhere.php: what changed
- ...

Verified locally:
- One bullet per scenario tested
- Include exit codes, response bodies, before/after states

Out of scope (later phases):
- What you deliberately didn't do, with the phase that will

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
```

### PR conventions

- One PR per phase
- Title: `Phase N: <short title>` matching the lead commit
- Body uses GitHub Markdown with: Summary / Why / What's in (subsections per area) / Verified locally (table) / Out of scope / Test plan (checkboxes)
- For stacked PRs (Phase N+1 depends on N), set `--base phase-N-branch-name` so the diff stays clean

### gh CLI (already authenticated on dev box)

```bash
gh pr create --base <base-branch> --head <head-branch> --title "..." --body "$(cat <<'EOF' ... EOF)"
gh pr view <number> --json state,mergedAt,mergeable
gh pr comment <number> --body "..."
```

### Stacked-PR pattern

When work continues before a prior PR merges:
1. Branch `phase-(N+1)` off `phase-N` (not `main`)
2. Open PR `--base phase-N` (not `main`)
3. GitHub will auto-retarget against `main` once `phase-N` merges (the diff collapses)
4. Merge order: lowest phase first

---

## Where to find each doc

| File | What it covers | When to read |
|---|---|---|
| [README.md](README.md) | Quickstart + phase checklist + layout summary | First time touching the repo |
| [BUILD_BRIEF.md](BUILD_BRIEF.md) | Vision, architecture, data model, routing, constraints, all 15 phases | Before designing anything substantial |
| **AGENTS.md** (this file) | Operating manual — mode rules, commands, gotchas, conventions | Start of every session |
| [PHASE_STATUS.md](PHASE_STATUS.md) | Live per-phase tracker — what's shipped, what's deferred | When you don't know what to work on |
| [MULTI_SITE.md](MULTI_SITE.md) | Multi-site / fork / pin / upgrade workflow | When adding or upgrading a deployment |
| [SETUP_GUIDE.md](SETUP_GUIDE.md) | cPanel deploy walkthrough | When deploying or troubleshooting prod |
| [AI_GUIDE.md](AI_GUIDE.md) | Phases 10-13 design — BYO keys, encryption, providers, prompts | Before starting AI work |
| `.claude/commands/{core,site}-mode.md` | What each mode allows | When the mode hook surprises you |

---

## Fresh-agent runbook

If you're starting cold (mobile session, new conversation, autonomous agent picking up):

1. **Read** [BUILD_BRIEF.md](BUILD_BRIEF.md) §1-4 (vision + architecture + layout) — 3 min
2. **Read** [PHASE_STATUS.md](PHASE_STATUS.md) — figure out what's done and what's next
3. **Skim** the gotchas section above
4. **Set the mode** for the work you're about to do: `/core-mode` or `/site-mode`
5. **Verify the dev environment**: `php core/scripts/migrate.php` should print "Nothing to apply" on a clean checkout. If it errors, fix `.env` and DB extension first (`pdo_sqlite`, `sodium`).
6. **Branch** off the right base (latest `main` if last PR is merged, else the open PR branch you're stacking on)
7. **Work** following the patterns in this doc
8. **Verify** with the patterns above (`curl` + cookie jar + DB inspection)
9. **Commit + PR** following the conventions above

If anything in this doc is wrong because the codebase moved, **fix the doc in the same PR as the code change**. The doc is the agreement; out-of-date docs are bugs.
