<?php
// core/lib/ai/ratelimit.php — per-IP and global daily token cap.
//
// Two layers:
//   1. Per-IP request count in a rolling window — protects the public
//      chatbot (Phase 13) from a single visitor burning budget.
//   2. Global daily token cap — protects the site owner from any
//      runaway loop (their own buggy code, their own admin tools, or a
//      successful abuse of the chatbot).
//
// Both read from ai_calls so there's a single source of truth — no
// separate counter table to keep in sync. SQLite does this in <1ms at
// our scale (100s of rows/day, indexes on created_at/caller).
//
// Defaults are conservative; override in .env when Phase 13 lands:
//   AI_RATE_PER_IP_WINDOW_SECONDS=300
//   AI_RATE_PER_IP_MAX=10
//   AI_RATE_GLOBAL_DAILY_TOKEN_CAP=200000

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/log.php';

const GUA_AI_RATE_PER_IP_WINDOW_SECONDS_DEFAULT = 300;
const GUA_AI_RATE_PER_IP_MAX_DEFAULT            = 10;
const GUA_AI_RATE_GLOBAL_DAILY_TOKEN_CAP_DEFAULT = 200000;

/** Returns true if $ip has exceeded the per-IP window cap. */
function ai_ratelimit_ip_exceeded(string $ip, ?string $caller = null): bool
{
    $window = (int) (getenv('AI_RATE_PER_IP_WINDOW_SECONDS') ?: GUA_AI_RATE_PER_IP_WINDOW_SECONDS_DEFAULT);
    $max    = (int) (getenv('AI_RATE_PER_IP_MAX')            ?: GUA_AI_RATE_PER_IP_MAX_DEFAULT);
    $since  = gmdate('Y-m-d H:i:s', time() - $window);

    $sql = 'SELECT COUNT(*) FROM ai_calls WHERE ip_address = :ip AND created_at >= :since';
    $params = [':ip' => $ip, ':since' => $since];
    if ($caller !== null) {
        $sql .= ' AND caller = :caller';
        $params[':caller'] = $caller;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn() >= $max;
}

/** Returns true if today's UTC token total has hit the global cap. */
function ai_ratelimit_global_exceeded(): bool
{
    $cap = (int) (getenv('AI_RATE_GLOBAL_DAILY_TOKEN_CAP') ?: GUA_AI_RATE_GLOBAL_DAILY_TOKEN_CAP_DEFAULT);
    $since = gmdate('Y-m-d') . ' 00:00:00';
    return ai_log_tokens_since($since) >= $cap;
}
