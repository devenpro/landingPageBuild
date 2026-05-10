<?php
// site/public/admin/forms.php — read-only inbox of waitlist submissions.
// Most-recent first, capped at 200 rows for now (Phase 14 polish: add
// pagination + CSV export). Each row is expandable to reveal the
// optional bottleneck text + UA / referrer / IP for context.

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';
require __DIR__ . '/_layout.php';
auth_require_login();

$pdo = db();
$rows = $pdo->query(
    'SELECT id, full_name, email, phone, role, clients_managed, bottleneck,
            user_agent, referrer, ip_address, webhook_status, webhook_response,
            submitted_at
     FROM form_submissions
     ORDER BY submitted_at DESC, id DESC
     LIMIT 200'
)->fetchAll(PDO::FETCH_ASSOC);

$total = (int) $pdo->query('SELECT COUNT(*) FROM form_submissions')->fetchColumn();

admin_head('Forms', 'forms');
?>
    <div class="flex items-baseline justify-between">
        <h1 class="text-2xl font-semibold tracking-tight text-ink-900">Form submissions</h1>
        <span class="text-sm text-ink-500">
            <?php if ($total > count($rows)): ?>
                Showing latest <?= count($rows) ?> of <?= $total ?>
            <?php else: ?>
                <?= $total ?> total
            <?php endif; ?>
        </span>
    </div>
    <p class="mt-2 text-ink-600">Click a row to expand details. Export to CSV lands in Phase 14.</p>

    <?php if ($rows === []): ?>
        <div class="mt-8 rounded-2xl border border-dashed border-ink-200 bg-white/60 p-10 text-center">
            <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-ink-100 text-ink-400">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6"><path d="M22 12A10 10 0 0 1 12 22a10 10 0 0 1 0-20"/><path d="M22 4 12 14.01l-3-3"/></svg>
            </div>
            <h2 class="mt-4 text-base font-medium text-ink-700">No submissions yet</h2>
            <p class="mt-1 text-sm text-ink-500">When someone fills out the waitlist form on your site, they'll show up here.</p>
        </div>
    <?php else: ?>
        <div class="mt-6 overflow-hidden rounded-2xl border border-ink-100 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="bg-ink-50/60 text-xs uppercase tracking-wider text-ink-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">When</th>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Contact</th>
                        <th class="px-4 py-3 font-medium">Role</th>
                        <th class="px-4 py-3 font-medium">Clients</th>
                        <th class="px-4 py-3 font-medium">Webhook</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    <?php foreach ($rows as $r):
                        $hook = $r['webhook_status'] ?? null;
                        $hook_class = match ($hook) {
                            'sent'    => 'bg-emerald-50 text-emerald-700',
                            'failed'  => 'bg-red-50 text-red-700',
                            'skipped' => 'bg-ink-100 text-ink-500',
                            default   => 'bg-ink-100 text-ink-400',
                        };
                    ?>
                        <tr class="form-row align-top hover:bg-ink-50/40">
                            <td class="px-4 py-3 text-ink-500 whitespace-nowrap"><?= e((string)$r['submitted_at']) ?></td>
                            <td class="px-4 py-3 font-medium text-ink-900"><?= e($r['full_name']) ?></td>
                            <td class="px-4 py-3 text-ink-700">
                                <div><a href="mailto:<?= e($r['email']) ?>" class="hover:text-brand-700"><?= e($r['email']) ?></a></div>
                                <div class="text-xs text-ink-500"><?= e($r['phone']) ?></div>
                            </td>
                            <td class="px-4 py-3 text-ink-700"><?= e($r['role']) ?></td>
                            <td class="px-4 py-3 text-ink-700"><?= e((string)($r['clients_managed'] ?? '—')) ?></td>
                            <td class="px-4 py-3">
                                <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium <?= $hook_class ?>">
                                    <?= e($hook ?? 'none') ?>
                                </span>
                            </td>
                        </tr>
                        <?php if (!empty($r['bottleneck']) || !empty($r['user_agent']) || !empty($r['ip_address'])): ?>
                            <tr class="form-row-detail bg-ink-50/40">
                                <td colspan="6" class="px-4 pb-4 pt-0">
                                    <div class="rounded-lg border border-ink-100 bg-white p-3 text-xs">
                                        <?php if (!empty($r['bottleneck'])): ?>
                                            <div class="mb-2">
                                                <div class="font-medium text-ink-700">Bottleneck</div>
                                                <div class="mt-1 text-ink-600 whitespace-pre-wrap"><?= e($r['bottleneck']) ?></div>
                                            </div>
                                        <?php endif; ?>
                                        <div class="grid gap-2 text-ink-500 sm:grid-cols-3">
                                            <div><span class="text-ink-400">IP:</span> <?= e((string)($r['ip_address'] ?? '—')) ?></div>
                                            <div><span class="text-ink-400">Referrer:</span> <?= e((string)($r['referrer'] ?? '—')) ?></div>
                                            <div class="truncate" title="<?= e((string)($r['user_agent'] ?? '')) ?>"><span class="text-ink-400">UA:</span> <?= e((string)($r['user_agent'] ?? '—')) ?></div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php
admin_foot();
