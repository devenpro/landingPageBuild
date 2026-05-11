<?php
// core/lib/brand/sync.php — disk mirror for brand items (v2 Stage 2).
//
// Each brand_items row mirrors to .brand/<category>/<slug>.md as YAML
// frontmatter + body. The DB is the source of truth; the disk mirror
// exists so Claude Code (desktop or mobile) can read and edit the same
// content via repo-aware sessions. Disk → DB sync is manual: the admin
// reviews a drift list at /admin/brand-sync.php and approves per-item.
//
// Path safety:
//   - Item slugs go through brand_assert_slug() before reaching here.
//   - Every write resolves the target path and verifies it stays under
//     <repo>/.brand/ — guards against path traversal via crafted slugs
//     that somehow bypass the regex (defence in depth).
//
// Hash conventions:
//   - body_hash = sha256 of body (DB column, recomputed on every save)
//   - disk_hash = sha256 of body the last time we wrote/pulled to/from
//     disk. brand_sync_dirty() compares disk_hash to a fresh hash of
//     the on-disk file to detect drift.

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

function brand_root(): string
{
    return GUA_PROJECT_ROOT . '/.brand';
}

/**
 * Path on disk for a (category_slug, item_slug). Resolves to an absolute
 * path under .brand/. The directory may not exist yet.
 */
function brand_item_path(string $category_slug, string $item_slug): string
{
    return brand_root() . '/' . $category_slug . '/' . $item_slug . '.md';
}

/**
 * Hash content the same way both columns use. Normalises trailing
 * \r/\n before hashing so a trailing newline added by an editor
 * doesn't register as drift.
 */
function brand_hash(string $body): string
{
    return hash('sha256', rtrim($body, "\r\n"));
}

/**
 * Write a DB row to its on-disk file. Creates the category directory if
 * missing, updates disk_hash on the DB row to match.
 */
function brand_sync_item(int $item_id): void
{
    $stmt = db()->prepare(
        'SELECT i.*, c.slug AS category_slug
           FROM brand_items i
           JOIN brand_categories c ON c.id = i.category_id
          WHERE i.id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $item_id]);
    $row = $stmt->fetch();
    if ($row === false) {
        return;
    }

    $path = brand_assert_under_root(brand_item_path($row['category_slug'], $row['slug']));
    $dir  = dirname($path);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("brand: cannot create directory $dir");
        }
    }

    $body = (string)$row['body'];
    $content = brand_render_frontmatter($row) . $body . ($body !== '' && !str_ends_with($body, "\n") ? "\n" : '');

    if (file_put_contents($path, $content) === false) {
        throw new RuntimeException("brand: cannot write $path");
    }

    $upd = db()->prepare('UPDATE brand_items SET disk_hash = :h WHERE id = :id');
    $upd->execute([':h' => brand_hash($body), ':id' => $item_id]);
}

/**
 * Delete the on-disk file for a row before the row itself is deleted.
 * Silent if the file is already gone.
 */
function brand_sync_delete(int $item_id): void
{
    $stmt = db()->prepare(
        'SELECT i.slug, c.slug AS category_slug
           FROM brand_items i
           JOIN brand_categories c ON c.id = i.category_id
          WHERE i.id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $item_id]);
    $row = $stmt->fetch();
    if ($row === false) {
        return;
    }
    $path = brand_assert_under_root(brand_item_path($row['category_slug'], $row['slug']));
    if (is_file($path)) {
        @unlink($path);
    }
}

/**
 * Regenerate .brand/INDEX.md — a one-liner per category linking to its items.
 * Cheap; called after every save and delete so the index doesn't drift.
 */
function brand_sync_index(): void
{
    $root = brand_root();
    if (!is_dir($root)) {
        if (!mkdir($root, 0755, true) && !is_dir($root)) {
            return; // can't create — give up silently
        }
    }

    $cats = db()->query(
        'SELECT * FROM brand_categories ORDER BY sort_order, label'
    )->fetchAll();
    $items_by_cat = [];
    $rows = db()->query(
        "SELECT i.*, c.slug AS category_slug
           FROM brand_items i
           JOIN brand_categories c ON c.id = i.category_id
          WHERE i.status = 'active'
          ORDER BY c.sort_order, i.title"
    )->fetchAll();
    foreach ($rows as $r) {
        $items_by_cat[$r['category_slug']][] = $r;
    }

    $lines = [
        '# Brand Context Library',
        '',
        '> Source of truth: the admin panel at `/admin/brand.php`. This directory is a mirror so Claude Code (and any other repo-aware tool) can read and edit the same content. After editing on disk, open `/admin/brand-sync.php` to merge changes back into the DB.',
        '',
    ];
    foreach ($cats as $c) {
        $lines[] = '## ' . $c['label'] . ' — `' . $c['slug'] . '`';
        if (!empty($c['description'])) {
            $lines[] = '';
            $lines[] = $c['description'];
        }
        $lines[] = '';
        $cat_items = $items_by_cat[$c['slug']] ?? [];
        if ($cat_items === []) {
            $lines[] = '_(no items yet)_';
        } else {
            foreach ($cat_items as $i) {
                $lines[] = '- [`' . $i['slug'] . '.md`](' . $c['slug'] . '/' . $i['slug'] . '.md) — ' . $i['title'];
            }
        }
        $lines[] = '';
    }

    @file_put_contents($root . '/INDEX.md', implode("\n", $lines));
}

/**
 * Bulk regenerate every item's disk file. Used after Stage 10 bootstrap or
 * after restoring from a snapshot.
 */
function brand_sync_all(): int
{
    $rows = db()->query('SELECT id FROM brand_items')->fetchAll();
    $n = 0;
    foreach ($rows as $r) {
        brand_sync_item((int)$r['id']);
        $n++;
    }
    brand_sync_index();
    return $n;
}

/**
 * Scan every active item, compare DB body_hash → on-disk hash (computed
 * fresh from the file body). Returns one entry per drift detected with
 * enough context for the admin sync UI to display a diff.
 *
 * Drift states:
 *   'disk_changed'    — file present, body differs from disk_hash
 *   'disk_missing'    — file expected per disk_hash but no file on disk
 *   'disk_only'       — file present but disk_hash is NULL (never synced)
 *                        — typically a Claude Code-created file we should ingest
 */
function brand_sync_dirty(): array
{
    $rows = db()->query(
        "SELECT i.*, c.slug AS category_slug
           FROM brand_items i
           JOIN brand_categories c ON c.id = i.category_id
          WHERE i.status != 'archived'"
    )->fetchAll();

    $dirty = [];
    foreach ($rows as $r) {
        $path = brand_item_path($r['category_slug'], $r['slug']);
        $on_disk = is_file($path);

        if (!$on_disk) {
            if ($r['disk_hash'] !== null) {
                $dirty[] = [
                    'state'     => 'disk_missing',
                    'item_id'   => (int)$r['id'],
                    'category'  => $r['category_slug'],
                    'slug'      => $r['slug'],
                    'title'     => $r['title'],
                    'path'      => $path,
                    'db_hash'   => $r['body_hash'],
                    'disk_hash' => $r['disk_hash'],
                ];
            }
            continue;
        }

        $parsed = brand_parse_file($path);
        $on_disk_body_hash = brand_hash($parsed['body']);

        if ($r['disk_hash'] === null) {
            // File appeared without us writing it — admin edit via Claude Code,
            // or seed file present from a fresh clone.
            if ($on_disk_body_hash !== $r['body_hash']) {
                $dirty[] = [
                    'state'        => 'disk_only',
                    'item_id'      => (int)$r['id'],
                    'category'     => $r['category_slug'],
                    'slug'         => $r['slug'],
                    'title'        => $r['title'],
                    'path'         => $path,
                    'db_hash'      => $r['body_hash'],
                    'disk_hash'    => $on_disk_body_hash,
                    'db_body'      => $r['body'],
                    'disk_body'    => $parsed['body'],
                ];
            }
            continue;
        }

        if ($on_disk_body_hash !== $r['disk_hash']) {
            $dirty[] = [
                'state'        => 'disk_changed',
                'item_id'      => (int)$r['id'],
                'category'     => $r['category_slug'],
                'slug'         => $r['slug'],
                'title'        => $r['title'],
                'path'         => $path,
                'db_hash'      => $r['body_hash'],
                'disk_hash'    => $on_disk_body_hash,
                'db_body'      => $r['body'],
                'disk_body'    => $parsed['body'],
            ];
        }
    }

    return $dirty;
}

/**
 * Apply a manual sync action chosen by the admin.
 *
 * strategy:
 *   'accept_disk' — DB takes the on-disk body (and disk_hash matches).
 *   'keep_db'     — overwrite disk with the DB body.
 *   'manual'      — caller supplies $manual_body; DB and disk both adopt it.
 *
 * Returns the new version number.
 */
function brand_sync_pull(int $item_id, string $strategy, ?string $manual_body = null, ?int $user_id = null): int
{
    require_once __DIR__ . '/items.php';

    $existing = brand_item_by_id($item_id);
    if ($existing === null) {
        throw new InvalidArgumentException("brand_sync_pull: id $item_id not found");
    }

    if ($strategy === 'accept_disk') {
        $stmt = db()->prepare(
            'SELECT i.slug, c.slug AS cs FROM brand_items i
              JOIN brand_categories c ON c.id = i.category_id
              WHERE i.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $item_id]);
        $r = $stmt->fetch();
        $path = brand_item_path($r['cs'], $r['slug']);
        if (!is_file($path)) {
            throw new RuntimeException("brand_sync_pull: disk file missing at $path");
        }
        $parsed = brand_parse_file($path);
        return brand_item_update($item_id, [
            'body'   => $parsed['body'],
            'source' => 'disk',
        ], $user_id);
    }

    if ($strategy === 'keep_db') {
        // Just re-sync; bump disk_hash on the DB row.
        brand_sync_item($item_id);
        return (int)$existing['version'];
    }

    if ($strategy === 'manual') {
        if ($manual_body === null) {
            throw new InvalidArgumentException('brand_sync_pull: manual strategy requires $manual_body');
        }
        return brand_item_update($item_id, [
            'body'   => $manual_body,
            'source' => 'admin',
        ], $user_id);
    }

    throw new InvalidArgumentException("brand_sync_pull: unknown strategy '$strategy'");
}

/* ---------- frontmatter render / parse ---------- */

function brand_render_frontmatter(array $row): string
{
    $fm = [
        'id'         => (int)$row['id'],
        'category'   => $row['category_slug'],
        'slug'       => $row['slug'],
        'kind'       => $row['kind'],
        'title'      => $row['title'],
        'version'    => (int)$row['version'],
        'body_hash'  => $row['body_hash'],
        'updated_at' => $row['updated_at'],
        'source'     => $row['source'],
    ];
    $lines = ["---"];
    foreach ($fm as $k => $v) {
        // Single-line scalars only — values are controlled by the schema.
        $lines[] = $k . ': ' . brand_yaml_scalar($v);
    }
    $lines[] = '---';
    return implode("\n", $lines) . "\n";
}

/**
 * Quote a YAML scalar conservatively: anything with special chars
 * (colons, leading dashes, quotes, control chars) gets double-quoted
 * with embedded backslashes/quotes escaped.
 */
function brand_yaml_scalar($v): string
{
    if (is_int($v) || is_float($v)) {
        return (string)$v;
    }
    $s = (string)$v;
    if ($s === '') {
        return '""';
    }
    if (preg_match('/[:#\[\]\{\}&*!|>\'"%@`,?\\\\\n\r\t]/', $s) || preg_match('/^[\-\?\s]/', $s)) {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $s) . '"';
    }
    return $s;
}

/**
 * Parse a .brand/ markdown file. Returns ['frontmatter' => [...], 'body' => '...'].
 * Frontmatter is permissive — we only need the body for hashing and the metadata
 * we wrote is informational (we never trust disk metadata over DB).
 */
function brand_parse_file(string $path): array
{
    $raw = file_get_contents($path);
    if ($raw === false) {
        return ['frontmatter' => [], 'body' => ''];
    }
    if (!str_starts_with(ltrim($raw, "\xEF\xBB\xBF"), '---')) {
        return ['frontmatter' => [], 'body' => $raw];
    }
    $stripped = ltrim($raw, "\xEF\xBB\xBF");
    $rest = substr($stripped, 4); // skip "---\n"
    $end = strpos($rest, "\n---");
    if ($end === false) {
        return ['frontmatter' => [], 'body' => $raw];
    }
    $fm_text = substr($rest, 0, $end);
    $body = substr($rest, $end + 4);
    if (str_starts_with($body, "\n")) {
        $body = substr($body, 1);
    }
    $fm = brand_parse_frontmatter($fm_text);
    return ['frontmatter' => $fm, 'body' => $body];
}

function brand_parse_frontmatter(string $text): array
{
    $out = [];
    foreach (preg_split('/\R/', $text) as $line) {
        if (trim($line) === '') continue;
        if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*:\s*(.*)$/', $line, $m)) continue;
        $val = trim($m[2]);
        // Strip wrapping double quotes; unescape \" and \\.
        if (strlen($val) >= 2 && $val[0] === '"' && substr($val, -1) === '"') {
            $val = str_replace(['\\"', '\\\\'], ['"', '\\'], substr($val, 1, -1));
        }
        $out[$m[1]] = $val;
    }
    return $out;
}

/* ---------- path safety ---------- */

/**
 * Resolve $path and assert it stays under .brand/. Returns the canonicalised
 * absolute path. Used by every disk-write call site as defence in depth.
 */
function brand_assert_under_root(string $path): string
{
    $root = brand_root();
    if (!is_dir($root)) {
        if (!mkdir($root, 0755, true) && !is_dir($root)) {
            throw new RuntimeException("brand: cannot create root $root");
        }
    }
    $root_abs = realpath($root);
    if ($root_abs === false) {
        throw new RuntimeException("brand: cannot resolve root $root");
    }

    // For non-existent files, resolve via the parent so realpath returns sane data.
    $abs = realpath($path);
    if ($abs === false) {
        $parent = realpath(dirname($path));
        if ($parent === false) {
            // Parent doesn't exist yet — accept the unresolved path so brand_sync_item
            // can create it, but still verify the prefix textually.
            $abs = $path;
        } else {
            $abs = $parent . DIRECTORY_SEPARATOR . basename($path);
        }
    }

    $abs_norm  = str_replace('\\', '/', $abs);
    $root_norm = str_replace('\\', '/', $root_abs);

    if (!str_starts_with($abs_norm, $root_norm . '/')) {
        throw new RuntimeException("brand: path $path escapes .brand/ (resolved to $abs_norm)");
    }
    return $abs;
}
