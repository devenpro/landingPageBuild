<?php
// site/public/admin/webhooks.php — webhook retry queue (Phase 14 round D).
//
// Lists rows from webhook_deliveries with filter chips by status, plus
// per-row actions:
//   Retry now — resets status to pending and fires the row inline (the
//               admin gets the new status as the page reloads, no need
//               to wait for the next cron tick)
//   Cancel    — terminal status='cancelled'; mirrors 'skipped' onto the
//               form_submissions row
//
// Read-only columns are picked to fit the typical admin question:
// "did this lead's webhook ever actually go through?" — when last
// attempted, what status code came back, what error, how many tries
// burned.

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';
require __DIR__ . '/_layout.php';
auth_require_login();

$pdo = db();

const WEBHOOKS_PER_PAGE = 25;
const WEBHOOKS_MAX_PAGE = 1000;

$valid_filters = ['all', 'pending', 'sent', 'failed', 'exhausted', 'cancelled'];
$filter = (string)($_GET['status'] ?? 'all');
if (!in_array($filter, $valid_filters, true)) {
    $filter = 'all';
}
$page = max(1, min(WEBHOOKS_MAX_PAGE, (int)($_GET['page'] ?? 1)));

// --- POST actions (Retry now / Cancel) --------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check((string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('CSRF check failed.');
    }
    $action = (string)($_POST['action'] ?? '');
    $id     = (int)($_POST['id'] ?? 0);

    if ($id > 0 && $action === 'retry') {
        // Force the row back to pending, due immediately, and fire inline so
        // the admin sees the outcome on the redirect-loaded page.
        $pdo->prepare(
            "UPDATE webhook_deliveries
                SET status = 'pending',
                    next_attempt_at = datetime('now'),
                    updated_at = datetime('now')
              WHERE id = :id"
        )->execute([':id' => $id]);
        webhook_process_delivery($pdo, $id, GUA_WEBHOOK_TIMEOUT_SECONDS);
    } elseif ($id > 0 && $action === 'cancel') {
        $row = $pdo->prepare('SELECT submission_id FROM webhook_deliveries WHERE id = :id');
        $row->execute([':id' => $id]);
        $r = $row->fetch();
        $pdo->prepare(
            "UPDATE webhook_deliveries
                SET status = 'cancelled',
                    updated_at = datetime('now')
              WHERE id = :id"
        )->execute([':id' => $id]);
        if ($r && $r['submission_id'] !== null) {
            webhook_mirror_status_to_submission($pdo, (int)$r['submission_id'], 'cancelled');
        }
    }

    // Preserve the current filter+page across the redirect.
    $q = [];
    if ($filter !== 'all') $q['status'] = $filter;
    if ($page > 1)         $q['page']   = $page;
    header('Location: /admin/webhooks.php' . ($q ? '?' . http_build_query($q) : ''));
    exit;
}

// --- counts (for filter chips) ----------------------------------------------

$counts = ['all' => 0, 'pending' => 0, 'sent' => 0, 'failed' => 0, 'exhausted' => 0, 'cancelled' => 0];
foreach ($pdo->query('SELECT status, COUNT(*) AS n FROM webhook_deliveries GROUP BY status') as $r) {
    $counts[$r['status']] = (int)$r['n'];
    $counts['all'] += (int)$r['n'];
}

// --- list query -------------------------------------------------------------

$where     = '';
$where_par = [];
if ($filter !== 'all') {
    $where = 'WHERE status = :status';
    $where_par[':status'] = $filter;
}

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM webhook_deliveries $where");
$count_stmt->execute($where_par);
$matched = (int)$count_stmt->fetchColumn();

$offset = ($page - 1) * WEBHOOKS_PER_PAGE;
$stmt   = $pdo->prepare(
    "SELECT id, submission_id, target_url, status, attempts, max_attempts,
            next_attempt_at, last_attempt_at, last_http_status, last_error, created_at
       FROM webhook_deliveries
        $where
       ORDER BY id DESC
       LIMIT :lim OFFSET :off"
);
foreach ($where_par as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':lim', WEBHOOKS_PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset,            PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

function webhooks_url(string $status, ?int $page = null): string
{
    $p = [];
    if ($status !== 'all') $p['status'] = $status;
    if ($page !== null)    $p['page']   = $page;
    return '/admin/webhooks.php' . ($p ? '?' . http_build_query($p) : '');
}

function webhooks_status_class(string $s): string
{
    return match ($s) {
        'sent'      => 'bg-emerald-50 text-emerald-700',
        'pending'   => 'bg-amber-50 text-amber-700',
        'failed'    => 'bg-red-50 text-red-700',
        'exhausted' => 'bg-red-50 text-red-700',
        'cancelled' => 'bg-ink-100 text-ink-500',
        default     => 'bg-ink-100 text-ink-500',
    };
}

admin_head('Webhooks', 'webhooks');
?>
    <div class="flex flex-wrap items-baseline justify-between gap-3">
        <h1 class="text-2xl font-semibold tracking-tight text-ink-900">Webhook deliveries</h1>
        <span class="text-sm text-ink-500"><?= (int)$counts['all'] ?> total</span>
    </div>
    <p class="mt-2 text-ink-600">
        Form submissions that hit a transient receiver failure end up here.
        The worker (<code class="text-xs">core/scripts/webhook_worker.php</code>)
        retries with exponential backoff up to 6 times; you can also force
        a retry or cancel a delivery manually.
    </p>

    <nav class="mt-5 flex flex-wrap gap-2 text-sm">
        <?php foreach ($valid_filters as $f):
            $active = $f === $filter;
            $cls    = $active
                ? 'bg-ink-900 text-white'
                : 'border border-ink-200 bg-white text-ink-700 hover:border-brand-300 hover:bg-brand-50';
        ?>
            <a href="<?= e(webhooks_url($f)) ?>" class="rounded-full px-3 py-1.5 <?= $cls ?>">
                <?= ucfirst($f) ?> <span class="ml-1 text-xs opacity-70">(<?= (int)($counts[$f] ?? 0) ?>)</span>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($rows === []): ?>
        <div class="mt-8 rounded-2xl border border-dashed border-ink-200 bg-white/60 p-10 text-center">
            <h2 class="text-base font-medium text-ink-700">
                <?= $filter === 'all' ? 'No deliveries yet' : 'No ' . e($filter) . ' deliveries' ?>
            </h2>
            <p class="mt-1 text-sm text-ink-500">
                <?= $filter === 'all'
                    ? 'A row will appear here the first time a webhook POST fails transiently and gets queued for retry.'
                    : 'Try a different filter or <a href="/admin/webhooks.php" class="underline">view all</a>.' ?>
            </p>
        </div>
    <?php else: ?>
        <div class="mt-6 overflow-hidden rounded-2xl border border-ink-100 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="bg-ink-50/60 text-xs uppercase tracking-wider text-ink-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">When</th>
                        <th class="px-4 py-3 font-medium">Submission</th>
                        <th class="px-4 py-3 font-medium">Target</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Attempts</th>
                        <th class="px-4 py-3 font-medium">Next attempt</th>
                        <th class="px-4 py-3 font-medium">Last error</th>
                        <th class="px-4 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    <?php foreach ($rows as $r): ?>
                        <tr class="align-top hover:bg-ink-50/40">
                            <td class="px-4 py-3 text-ink-500 whitespace-nowrap"><?= e((string)$r['created_at']) ?></td>
                            <td class="px-4 py-3 text-ink-700">
                                <?php if ($r['submission_id'] !== null): ?>
                                    <a href="/admin/forms.php?q=<?= (int)$r['submission_id'] ?>" class="text-brand-700 hover:underline">#<?= (int)$r['submission_id'] ?></a>
                                <?php else: ?>
                                    <span class="text-ink-500">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-ink-600">
                                <code class="text-xs"><?= e((string)$r['target_url']) ?></code>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs <?= webhooks_status_class((string)$r['status']) ?>">
                                    <?= e((string)$r['status']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-ink-700"><?= (int)$r['attempts'] ?>/<?= (int)$r['max_attempts'] ?></td>
                            <td class="px-4 py-3 text-ink-500 whitespace-nowrap">
                                <?= $r['status'] === 'pending' ? e((string)$r['next_attempt_at']) : '<span class="text-ink-500">—</span>' ?>
                            </td>
                            <td class="px-4 py-3 text-ink-600">
                                <?php if ($r['last_error']): ?>
                                    <span title="HTTP <?= (int)$r['last_http_status'] ?>"><?= e(mb_substr((string)$r['last_error'], 0, 80)) ?></span>
                                <?php else: ?>
                                    <span class="text-ink-500">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <?php if (in_array($r['status'], ['pending', 'failed', 'exhausted'], true)): ?>
                                    <form method="post" action="/admin/webhooks.php<?= $filter !== 'all' ? '?status=' . e($filter) : '' ?>" class="inline">
                                        <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="id"     value="<?= (int)$r['id'] ?>">
                                        <input type="hidden" name="action" value="retry">
                                        <button type="submit"
                                                class="rounded-md border border-brand-200 bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700 hover:bg-brand-100">
                                            Retry now
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if (in_array($r['status'], ['pending', 'failed', 'exhausted'], true)): ?>
                                    <form method="post" action="/admin/webhooks.php<?= $filter !== 'all' ? '?status=' . e($filter) : '' ?>" class="inline">
                                        <input type="hidden" name="csrf"   value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="id"     value="<?= (int)$r['id'] ?>">
                                        <input type="hidden" name="action" value="cancel">
                                        <button type="submit"
                                                class="rounded-md border border-ink-200 bg-white px-2.5 py-1 text-xs text-ink-600 hover:border-red-300 hover:bg-red-50 hover:text-red-700">
                                            Cancel
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php
        $last_page = max(1, (int)ceil($matched / WEBHOOKS_PER_PAGE));
        if ($last_page > 1):
        ?>
            <nav class="mt-5 flex items-center justify-between text-sm text-ink-600">
                <div>Page <?= $page ?> of <?= $last_page ?> · <?= $matched ?> row<?= $matched === 1 ? '' : 's' ?></div>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a class="rounded-md border border-ink-200 px-3 py-1 hover:border-brand-300 hover:bg-brand-50" href="<?= e(webhooks_url($filter, $page - 1)) ?>">&larr; Prev</a>
                    <?php endif; ?>
                    <?php if ($page < $last_page): ?>
                        <a class="rounded-md border border-ink-200 px-3 py-1 hover:border-brand-300 hover:bg-brand-50" href="<?= e(webhooks_url($filter, $page + 1)) ?>">Next &rarr;</a>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
<?php admin_foot();
