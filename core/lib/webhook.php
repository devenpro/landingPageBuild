<?php
// core/lib/webhook.php — webhook delivery + retry queue (Phase 14 round D).
//
// Two callers:
//   - site/public/api/form.php tries an inline POST on submit, then enqueues
//     for retry on transient failure so we never lose a lead to a 504.
//   - core/scripts/webhook_worker.php drains the queue under cron.
//
// Transient vs permanent: 2xx is success, 4xx is treated as a permanent
// configuration error (won't auto-retry — admin must click Retry now after
// fixing the receiver), and everything else (5xx, timeouts, connection
// refused) is transient and re-queues with exponential backoff. This
// matches Stripe/GitHub/Slack webhook semantics.

declare(strict_types=1);

/** One-shot HTTP POST. Returns a normalized result. No DB writes here. */
/**
 * v2 Stage 6 added optional $extra_headers and $method so per-form webhooks
 * can send custom auth headers (Slack signing, Bearer tokens) and use
 * non-POST verbs. Legacy callers pass three args and get POST + JSON as before.
 */
function webhook_post(string $url, array $payload, int $timeout_seconds, array $extra_headers = [], string $method = 'POST'): array
{
    $headers = ['Content-Type: application/json'];
    foreach ($extra_headers as $h) {
        if (is_string($h) && $h !== '') $headers[] = $h;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => json_safe($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout_seconds,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_USERAGENT      => 'GoUltraAI-Webhook/1.0',
    ]);
    $response  = curl_exec($ch);
    $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err       = curl_error($ch);
    curl_close($ch);

    $ok    = $response !== false && $http_code >= 200 && $http_code < 300;
    $perm  = $http_code >= 400 && $http_code < 500;        // permanent client error
    $body  = is_string($response) ? mb_substr($response, 0, 1000) : null;
    $error = $err !== '' ? $err : ($ok ? null : "HTTP {$http_code}");

    return [
        'ok'        => $ok,
        'permanent' => !$ok && $perm,                       // 4xx → don't auto-retry
        'http_code' => $http_code,
        'response'  => $body,
        'error'     => $error,
    ];
}

/**
 * Backoff schedule for the Nth retry attempt (1-indexed: the value of
 * `attempts` AFTER the attempt that just failed). Returns seconds.
 *
 * Roughly geometric, capped at 24h. Covers ~40h before exhaustion at the
 * default max_attempts=6 — enough for typical receiver outages without
 * sitting on a stuck row for a week.
 */
function webhook_backoff_seconds(int $attempts): int
{
    static $schedule = [60, 300, 1800, 7200, 43200, 86400];
    $i = max(0, min(count($schedule) - 1, $attempts - 1));
    return $schedule[$i];
}

/**
 * Enqueue a delivery. Used after the inline POST in /api/form.php failed
 * transiently. Returns the new row id. $delay_seconds 0 means "fire on
 * the next worker run" (default — the inline POST already failed, no
 * point waiting; the worker may pick it up within the cron interval).
 */
function webhook_enqueue(
    PDO $pdo,
    ?int $submission_id,
    string $url,
    array $payload,
    int $delay_seconds = 60,
    int $max_attempts = 6
): int {
    $stmt = $pdo->prepare(
        'INSERT INTO webhook_deliveries
            (submission_id, target_url, payload_json, status, max_attempts, next_attempt_at)
         VALUES
            (:sid, :url, :payload, \'pending\',
             :max, datetime(\'now\', :delay))'
    );
    $stmt->execute([
        ':sid'     => $submission_id,
        ':url'     => $url,
        ':payload' => json_safe($payload),
        ':max'     => $max_attempts,
        ':delay'   => sprintf('+%d seconds', max(0, $delay_seconds)),
    ]);
    return (int)$pdo->lastInsertId();
}

/**
 * Mirror a delivery's terminal state onto form_submissions.webhook_status so
 * the Forms inbox shows the live truth without a JOIN. Status values mapped:
 *   sent      -> sent
 *   exhausted -> failed     (admins look at the queue for the gory detail)
 *   failed    -> failed
 *   cancelled -> skipped    (admin chose not to deliver)
 *   pending   -> queued     (set at enqueue time)
 *
 * Safe to call when submission_id is null (delivery was created without a
 * row — e.g. admin re-fired a one-off); it just no-ops.
 */
function webhook_mirror_status_to_submission(PDO $pdo, ?int $submission_id, string $delivery_status): void
{
    if ($submission_id === null) {
        return;
    }
    $mapped = match ($delivery_status) {
        'sent'      => 'sent',
        'exhausted', 'failed' => 'failed',
        'cancelled' => 'skipped',
        default     => 'queued',
    };
    $pdo->prepare('UPDATE form_submissions SET webhook_status = :s WHERE id = :id')
        ->execute([':s' => $mapped, ':id' => $submission_id]);
}

/**
 * Process a single queue row. Reads it, fires the POST, updates the row
 * with the outcome, and mirrors the resulting state onto the submission.
 *
 * Returns the new status ('sent' | 'pending' | 'failed' | 'exhausted').
 * Called from both the worker and the admin "Retry now" action.
 */
function webhook_process_delivery(PDO $pdo, int $delivery_id, int $timeout_seconds): string
{
    $row = $pdo->prepare('SELECT * FROM webhook_deliveries WHERE id = :id');
    $row->execute([':id' => $delivery_id]);
    $d = $row->fetch();
    if (!$d || $d['status'] !== 'pending') {
        return $d['status'] ?? 'unknown';
    }

    $payload = json_decode((string)$d['payload_json'], true);
    if (!is_array($payload)) {
        $payload = [];
    }

    $result   = webhook_post((string)$d['target_url'], $payload, $timeout_seconds);
    $attempts = (int)$d['attempts'] + 1;
    $max      = (int)$d['max_attempts'];

    if ($result['ok']) {
        $status     = 'sent';
        $next_delay = '+0 seconds';
    } elseif ($result['permanent']) {
        // 4xx — receiver is actively rejecting. Don't auto-retry; the
        // admin Retry-now action will reset status to pending.
        $status     = 'failed';
        $next_delay = '+0 seconds';
    } elseif ($attempts >= $max) {
        $status     = 'exhausted';
        $next_delay = '+0 seconds';
    } else {
        $status     = 'pending';
        $next_delay = sprintf('+%d seconds', webhook_backoff_seconds($attempts));
    }

    $upd = $pdo->prepare(
        'UPDATE webhook_deliveries
            SET status = :status,
                attempts = :attempts,
                last_attempt_at = datetime(\'now\'),
                last_http_status = :http,
                last_error = :err,
                last_response = :resp,
                next_attempt_at = datetime(\'now\', :delay),
                updated_at = datetime(\'now\')
          WHERE id = :id'
    );
    $upd->execute([
        ':status'   => $status,
        ':attempts' => $attempts,
        ':http'     => $result['http_code'] ?: null,
        ':err'      => $result['error'],
        ':resp'     => $result['response'],
        ':delay'    => $next_delay,
        ':id'       => $delivery_id,
    ]);

    webhook_mirror_status_to_submission($pdo, $d['submission_id'] !== null ? (int)$d['submission_id'] : null, $status);
    return $status;
}
