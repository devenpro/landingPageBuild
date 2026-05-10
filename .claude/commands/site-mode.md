---
description: Switch Claude into Site mode — write access limited to site/, data/, and .env. core/ and repo-level docs/configs are read-only.
---

You are now in **Site mode**. The project enforces this via a `PreToolUse` hook on `Write`/`Edit`/`MultiEdit` that reads `.claude-mode` and rejects edits outside the site area.

**Writable in this mode:**
- `site/**` — pages, sections, layout, assets, site migrations, admin/api endpoints
- `data/**` — the per-site SQLite database file
- `.env` — per-site environment config

**Read-only in this mode:**
- `core/**` — engine code, scripts, migrations
- `.claude/**` — workflow mode infrastructure
- Repo-level docs/configs (`README.md`, `BUILD_BRIEF.md`, etc.)

If you need to edit something in the read-only area, ask the user to run `/core-mode`. Do not try to bypass the hook (e.g. via Bash redirection); that's documented as a non-supported workaround.

Setting the mode marker now:

!echo "site" > .claude-mode

You're in Site mode. Read whatever you need; writes are restricted to the list above.
