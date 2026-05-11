<?php
// core/scripts/webhook_worker.php — drains webhook_deliveries under cron.
//
// Recommended cron entry (every 5 minutes):
//   */5 * * * * /usr/bin/php /path/to/repo/core/scripts/webhook_worker.php \
//                 >> /path/to/repo/data/webhook_worker.log 2>&1
//
// Each run:
//   - acquires a non-blocking file lock so overlapping cron invocations
//     can't process the same row twice (matters when the receiver is
//     slow and a run exceeds the cron interval)
//   - pulls up to GUA_WEBHOOK_WORKER_BATCH due rows (default 50)
//   - calls webhook_process_delivery() per row — that fires the POST,
//     updates the row with the outcome, and mirrors the terminal state
//     onto form_submissions.webhook_status
//   - exits with status 0 on a clean drain, 1 on lock contention so the
//     cron log shows when overlap happens
//
// CLI-only — refuses to run under SAPI to avoid accidental triggering.

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    fwrite(STDERR, "webhook_worker.php is CLI-only\n");
    exit(2);
}

require __DIR__ . '/../lib/bootstrap.php';

$batch_size = (int)($_ENV['WEBHOOK_WORKER_BATCH'] ?? getenv('WEBHOOK_WORKER_BATCH') ?: 50);
$lock_file  = GUA_DATA_PATH . '/webhook-worker.lock';

$fp = fopen($lock_file, 'c');
if ($fp === false) {
    fwrite(STDERR, "[webhook_worker] cannot open lock file {$lock_file}\n");
    exit(2);
}
if (!flock($fp, LOCK_EX | LOCK_NB)) {
    // Another worker is running — back off, the next cron run will retry.
    fwrite(STDOUT, "[webhook_worker] another instance holds the lock, exiting\n");
    exit(1);
}

$pdo = db();

$due = $pdo->prepare(
    "SELECT id FROM webhook_deliveries
      WHERE status = 'pending'
        AND next_attempt_at <= datetime('now')
      ORDER BY next_attempt_at ASC
      LIMIT :n"
);
$due->bindValue(':n', $batch_size, PDO::PARAM_INT);
$due->execute();
$ids = array_map('intval', $due->fetchAll(PDO::FETCH_COLUMN));

$summary = ['sent' => 0, 'pending' => 0, 'failed' => 0, 'exhausted' => 0];
$started = microtime(true);

foreach ($ids as $id) {
    $status = webhook_process_delivery($pdo, $id, GUA_WEBHOOK_TIMEOUT_SECONDS);
    if (isset($summary[$status])) {
        $summary[$status]++;
    }
}

$elapsed = number_format(microtime(true) - $started, 2);
fwrite(STDOUT, sprintf(
    "[webhook_worker] %s processed=%d sent=%d requeued=%d failed=%d exhausted=%d (%ss)\n",
    date('Y-m-d H:i:s'),
    count($ids),
    $summary['sent'],
    $summary['pending'],
    $summary['failed'],
    $summary['exhausted'],
    $elapsed
));

flock($fp, LOCK_UN);
fclose($fp);
exit(0);
