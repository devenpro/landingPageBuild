<?php
// .claude/scripts/check-mode.php — PreToolUse hook for Write/Edit/MultiEdit.
//
// Reads the current workflow mode from .claude-mode (gitignored, set by
// /core-mode or /site-mode slash commands). Validates the target file path
// against the mode's allow-list. Exit 0 = allow, exit 2 = block (Claude
// Code surfaces the stderr message back to the model).
//
// Allow-lists:
//   core mode  → core/**, repo-level docs/configs, .claude/**
//   site mode  → site/**, data/**, .env
//   no mode    → block all writes with prompt to set a mode

declare(strict_types=1);

$repo_root = realpath(__DIR__ . '/../..');

// Read tool call from stdin
$raw = stream_get_contents(STDIN);
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    // Malformed input — fail open (don't break Claude Code on hook bug)
    exit(0);
}

$tool_input = $payload['tool_input'] ?? [];
$file_path  = $tool_input['file_path'] ?? '';
if ($file_path === '') {
    // No file path on this tool call — let it through
    exit(0);
}

// Normalise the target path to an absolute path under the repo
$abs = realpath($file_path);
if ($abs === false) {
    // File doesn't exist yet (Write creating new file). Resolve via parent.
    $parent = realpath(dirname($file_path));
    $abs = $parent === false ? $file_path : $parent . DIRECTORY_SEPARATOR . basename($file_path);
}
$abs = str_replace('\\', '/', $abs);
$root = str_replace('\\', '/', $repo_root);

// Outside the repo entirely? Allow (e.g. editing ~/.claude/* or similar)
if (!str_starts_with($abs, $root . '/')) {
    exit(0);
}
$rel = substr($abs, strlen($root) + 1);

// Read current mode
$mode_file = $repo_root . '/.claude-mode';
$mode = is_file($mode_file) ? trim(file_get_contents($mode_file)) : '';

if ($mode === '') {
    fwrite(STDERR, "No workflow mode active. Run /core-mode or /site-mode before editing.\n");
    exit(2);
}

// Allow-lists keyed by mode (path prefixes, '/' separator)
$allow = [
    'core' => [
        'core/',
        '.claude/',
        'README.md',
        'BUILD_BRIEF.md',
        'MULTI_SITE.md',
        'SETUP_GUIDE.md',
        'AI_GUIDE.md',
        'AGENTS.md',
        'PHASE_STATUS.md',
        '.gitignore',
        '.cpanel.yml',
        '.env.example',
    ],
    'site' => [
        'site/',
        'data/',
        '.env',
    ],
];

if (!isset($allow[$mode])) {
    fwrite(STDERR, "Unknown workflow mode '$mode' in .claude-mode. Use /core-mode or /site-mode.\n");
    exit(2);
}

foreach ($allow[$mode] as $prefix) {
    if (str_ends_with($prefix, '/')) {
        if (str_starts_with($rel, $prefix)) {
            exit(0);
        }
    } else {
        if ($rel === $prefix) {
            exit(0);
        }
    }
}

fwrite(STDERR, "Edit blocked by /$mode-mode: $rel is outside this mode's allow-list.\n");
fwrite(STDERR, "Allowed in $mode mode: " . implode(', ', $allow[$mode]) . "\n");
fwrite(STDERR, "Switch with /core-mode or /site-mode if you need to edit a different area.\n");
exit(2);
