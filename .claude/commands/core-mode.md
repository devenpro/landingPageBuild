---
description: Switch Claude into Core mode — write access limited to core/ and repo-level docs/configs. site/, data/, .env are read-only.
---

You are now in **Core mode**. The project enforces this via a `PreToolUse` hook on `Write`/`Edit`/`MultiEdit` that reads `.claude-mode` and rejects edits outside the core area.

**Writable in this mode:**
- `core/**` — engine code, scripts, migrations
- `.claude/**` — workflow mode infrastructure itself
- Repo-level docs: `README.md`, `BUILD_BRIEF.md`, `MULTI_SITE.md`, `SETUP_GUIDE.md`, `AI_GUIDE.md`
- Repo-level config: `.gitignore`, `.cpanel.yml`, `.env.example`

**Read-only in this mode:**
- `site/**` — site theme, sections, pages, layout
- `data/**` — site database
- `.env` — site config

If you need to edit something in the read-only area, ask the user to run `/site-mode`. Do not try to bypass the hook (e.g. via Bash redirection); that's documented as a non-supported workaround.

Setting the mode marker now:

!echo "core" > .claude-mode

You're in Core mode. Read whatever you need; writes are restricted to the list above.
