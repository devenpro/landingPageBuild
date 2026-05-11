# CLAUDE.md — quick orientation for Claude Code sessions

You're working in a multi-tenant multi-page website builder (PHP 8.1 + SQLite + Tailwind + vanilla JS, deployed via cPanel, one git clone per site). For full operating instructions read [AGENTS.md](AGENTS.md) — mode rules, common commands, conventions, gotchas. For architecture read [BUILD_BRIEF.md](BUILD_BRIEF.md). For phase status read [PHASE_STATUS.md](PHASE_STATUS.md).

## Brand Context Library

When you generate, rewrite, or critique any user-facing content, **read [.brand/INDEX.md](.brand/INDEX.md) first**. It is a mirror of the per-site Brand Context Library — voice, audience, services, design guide, page-build conventions, SEO targets, social links. The admin curates it through `/admin/brand.php`; the AI prompts in this codebase (`core/lib/ai/prompts/*`) inject relevant categories into every generation call automatically.

You may edit `.brand/*.md` files directly. The DB is the source of truth, so disk changes are not auto-applied — the admin reviews drift at `/admin/brand-sync.php` and either accepts, keeps the DB version, or merges manually. To make your edits land in production:

1. Edit the relevant `.brand/<category>/<slug>.md` file.
2. Commit and push (these files are git-tracked so a mobile session and a desktop session see the same content).
3. Tell the admin to open `/admin/brand-sync.php` and pull your changes.

Slug rule for new files: lowercase letters, digits, single hyphens; no leading or trailing hyphen. Match `^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$`.

## Mode

Before editing anything, set a workflow mode:

- `/core-mode` — for changes under `core/`, repo-root docs, `.claude/`, `.brand/`
- `/site-mode` — for changes under `site/`, `data/`, `.env`, `.brand/`

The PreToolUse hook (`.claude/scripts/check-mode.php`) blocks edits outside the active mode's allow-list. `.brand/` is editable in either mode so you don't need to switch when iterating on brand content alongside code.
