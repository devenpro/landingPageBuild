-- core/migrations/0005_webhook_queue.sql — durable retry queue for form webhooks.
-- Phase 14 round D. Previous code (rounds A/B/C) ran a single best-effort
-- POST in /api/form.php and gave up on first failure; transient outages on
-- the receiver side (504s, network blips, brief downtime) silently lost
-- the delivery. This table backs an out-of-band retry loop driven by
-- core/scripts/webhook_worker.php under cron.
--
-- Lifecycle:
--   pending   -- sitting in queue, next_attempt_at says when to fire
--   sent      -- 2xx received, terminal
--   failed    -- permanent 4xx (won't retry until admin clicks "Retry now")
--   exhausted -- ran out of attempts, terminal until admin retries
--   cancelled -- admin chose not to deliver, terminal
--
-- Backoff schedule (set in core/lib/webhook.php):
--   attempt 1 retry +60s, 2 +5m, 3 +30m, 4 +2h, 5 +12h, 6 +24h
--   exhausts after ~40h total.

CREATE TABLE IF NOT EXISTS webhook_deliveries (
  id                 INTEGER PRIMARY KEY AUTOINCREMENT,
  submission_id      INTEGER REFERENCES form_submissions(id) ON DELETE SET NULL,
  target_url         TEXT NOT NULL,
  payload_json       TEXT NOT NULL,
  status             TEXT NOT NULL DEFAULT 'pending'
                          CHECK(status IN ('pending','sent','failed','exhausted','cancelled')),
  attempts           INTEGER NOT NULL DEFAULT 0,
  max_attempts       INTEGER NOT NULL DEFAULT 6,
  next_attempt_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_attempt_at    DATETIME,
  last_http_status   INTEGER,
  last_error         TEXT,
  last_response      TEXT,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- The worker hits this index every run — pending + due, sorted by due time.
CREATE INDEX IF NOT EXISTS idx_webhook_deliveries_due
  ON webhook_deliveries(status, next_attempt_at);

-- For the admin page filtering by submission.
CREATE INDEX IF NOT EXISTS idx_webhook_deliveries_submission
  ON webhook_deliveries(submission_id);
