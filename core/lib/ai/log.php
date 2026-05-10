<?php
// core/lib/ai/log.php — write to ai_calls.
//
// Every outbound provider call should be wrapped: ai_log_call() at the
// end with whatever the adapter recorded (status, tokens, duration). The
// admin AI tools page (Phase 11) reads from this table for spend display;
// ratelimit.php reads it for the global daily token cap.
//
// We keep this dumb: no business logic, no enrichment. Adapters know
// their own pricing and pass cost_estimate_usd already computed.

declare(strict_types=1);

require_once __DIR__ . '/../db.php';

/**
 * Insert a row into ai_calls. Returns the new row id.
 *
 * @param array{
 *   provider: string,
 *   model?: ?string,
 *   caller?: ?string,
 *   tokens_in?: int,
 *   tokens_out?: int,
 *   cost_estimate_usd?: float,
 *   status?: string,
 *   error_message?: ?string,
 *   ip_address?: ?string,
 *   duration_ms?: ?int,
 * } $row
 */
function ai_log_call(array $row): int
{
    $stmt = db()->prepare(
        'INSERT INTO ai_calls
         (provider, model, caller, tokens_in, tokens_out, cost_estimate_usd,
          status, error_message, ip_address, duration_ms)
         VALUES (:provider, :model, :caller, :tin, :tout, :cost,
                 :status, :err, :ip, :dur)'
    );
    $stmt->execute([
        ':provider' => $row['provider'],
        ':model'    => $row['model']             ?? null,
        ':caller'   => $row['caller']            ?? null,
        ':tin'      => (int) ($row['tokens_in']  ?? 0),
        ':tout'     => (int) ($row['tokens_out'] ?? 0),
        ':cost'     => (float)($row['cost_estimate_usd'] ?? 0.0),
        ':status'   => $row['status']            ?? 'ok',
        ':err'      => $row['error_message']     ?? null,
        ':ip'       => $row['ip_address']        ?? null,
        ':dur'      => $row['duration_ms']       ?? null,
    ]);
    return (int) db()->lastInsertId();
}

/**
 * Sum tokens out + in across the trailing window (UTC).
 * Used by ratelimit.php for the global daily cap.
 */
function ai_log_tokens_since(string $since_utc): int
{
    $stmt = db()->prepare(
        'SELECT COALESCE(SUM(tokens_in + tokens_out), 0)
         FROM ai_calls WHERE created_at >= :since'
    );
    $stmt->execute([':since' => $since_utc]);
    return (int) $stmt->fetchColumn();
}
