<?php
// core/lib/settings.php — DB-backed runtime settings (v2 Stage 1).
//
// Resolution order for settings_get($key, $default):
//   1. site_settings.value (this is the admin-editable layer)
//   2. .env via env_get() — same key, uppercased (e.g. site_name → SITE_NAME)
//   3. The $default argument
//
// All site_settings rows are loaded into a per-request cache on first access.
// settings_set() invalidates the cache, so a save followed by a read in the
// same request returns the new value.
//
// Requires the schema from core/migrations/0006_settings.sql. If the table
// is missing (fresh clone, pre-migrate), settings_get() silently falls
// through to .env so the bootstrap chain still works.

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Look up a setting. Returns the typed value if set in DB, else .env, else $default.
 *
 * value_type drives casting:
 *   'string' / 'secret' → string
 *   'number'            → int (or float if it has a dot)
 *   'boolean'           → bool (FILTER_VALIDATE_BOOLEAN)
 *   'json'              → decoded array
 */
function settings_get(string $key, $default = null)
{
    $row = _settings_row($key);

    // Layer 1: DB value (string check guards against value="0" being treated as empty)
    if ($row !== null && $row['value'] !== null && $row['value'] !== '') {
        return _settings_cast($row['value'], (string)$row['value_type']);
    }

    // Layer 2: .env (uppercased key)
    $env_val = env_get(strtoupper($key));
    if ($env_val !== null) {
        $type = $row['value_type'] ?? 'string';
        return _settings_cast($env_val, (string)$type);
    }

    // Layer 3: caller default (returned as-is, no cast)
    return $default;
}

/**
 * Update a setting's value. Pass null to clear (falls back to .env / default).
 * Boolean values are stored as '0' / '1'; arrays as JSON.
 */
function settings_set(string $key, $value, ?int $user_id = null): void
{
    $stored = _settings_serialize($value);
    $stmt = db()->prepare(
        'UPDATE site_settings
            SET value = :v,
                updated_at = CURRENT_TIMESTAMP,
                updated_by = :u
          WHERE key = :k'
    );
    $stmt->execute([':v' => $stored, ':u' => $user_id, ':k' => $key]);
    _settings_cache_clear();
}

/**
 * Return all rows for a group, in insertion order. Used by the admin UI.
 */
function settings_all_in_group(string $group): array
{
    try {
        $stmt = db()->prepare(
            'SELECT * FROM site_settings WHERE group_name = :g ORDER BY id'
        );
        $stmt->execute([':g' => $group]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Return the list of known groups in the order they first appear.
 */
function settings_groups(): array
{
    try {
        $stmt = db()->query(
            'SELECT group_name FROM site_settings GROUP BY group_name ORDER BY MIN(id)'
        );
        return array_map(static fn($r) => $r['group_name'], $stmt->fetchAll());
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Compute the effective source of a setting for UI hints.
 * Returns 'db' | 'env' | 'default'.
 */
function settings_source(string $key): string
{
    $row = _settings_row($key);
    if ($row !== null && $row['value'] !== null && $row['value'] !== '') {
        return 'db';
    }
    if (env_get(strtoupper($key)) !== null) {
        return 'env';
    }
    return 'default';
}

/* ---------- internal ---------- */

/**
 * Per-request row cache. The whole site_settings table is small enough
 * (a few dozen rows) to load in one query and serve from memory.
 *
 * @param array<string,array>|null $set    Replace the cache when non-null.
 * @param bool                     $clear  Drop the cache (forces next read to refetch).
 */
function _settings_cache(?array $set = null, bool $clear = false): ?array
{
    static $rows = null;
    if ($clear) {
        $rows = null;
        return null;
    }
    if ($set !== null) {
        $rows = $set;
    }
    return $rows;
}

function _settings_cache_clear(): void
{
    _settings_cache(clear: true);
}

function _settings_row(string $key): ?array
{
    $rows = _settings_cache();
    if ($rows === null) {
        try {
            $stmt = db()->query('SELECT key, value, value_type FROM site_settings');
            $rows = [];
            foreach ($stmt as $r) {
                $rows[$r['key']] = $r;
            }
        } catch (Throwable $e) {
            // Table not present yet (fresh clone, before migrate.php).
            $rows = [];
        }
        _settings_cache($rows);
    }
    return $rows[$key] ?? null;
}

function _settings_cast(string $value, string $type)
{
    return match ($type) {
        'number'  => str_contains($value, '.') ? (float)$value : (int)$value,
        'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
        'json'    => json_decode($value, true) ?? [],
        default   => $value, // 'string', 'secret', and anything unrecognised
    };
}

function _settings_serialize($value): ?string
{
    if ($value === null) {
        return null;
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if (is_array($value)) {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    return (string)$value;
}
