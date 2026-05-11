<?php
// site/public/admin/forms.php — waitlist inbox.
//
// Phase 14 round A: pagination, search, CSV export. Search matches
// across full_name, email, phone, role, bottleneck (LIKE %q%, case
// insensitive). Pagination defaults to 25 per page; pages are 1-indexed
// in the URL so admins can share links. CSV export streams the matching
// (search-filtered) rows directly — no buffering, no row cap — so the
// admin can grab everything in one go.

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';
require __DIR__ . '/_layout.php';
auth_require_login();

$pdo = db();

const FORMS_PER_PAGE = 25;
const FORMS_MAX_PAGE = 1000; // sanity bound; 1000 * 25 = 25k rows max via UI

$q = trim((string) ($_GET['q'] ?? ''));
$page = max(1, min(FORMS_MAX_PAGE, (int) ($_GET['page'] ?? 1)));
$export = (string) ($_GET['export'] ?? '');

// Build the WHERE clause once; reused by count, list, and export.
$where     = '';
$where_par = [];
if ($q !== '') {
    $where = 'WHERE full_name LIKE :q OR email LIKE :q OR phone LIKE :q
              OR role LIKE :q OR bottleneck LIKE :q';
    $where_par[':q'] = '%' . $q . '%';
}

// ---------------- CSV export -----------------------------------------------

if ($export === 'csv') {
    $sql = "SELECT id, full_name, email, phone, role, clients_managed, bottleneck,
                   user_agent, referrer, ip_address, webhook_status, submitted_at
            FROM form_submissions
            $where
            ORDER BY submitted_at DESC, id DESC";
    $stmt = $pdo->prepare($sql);
    foreach ($where_par as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();

    // Filename includes UTC date + 'q' marker so multiple exports don't
    // overwrite each other in the admin's downloads folder.
    $stamp = gmdate('Ymd-His');
    $tag   = $q !== '' ? '-search' : '';
    $fname = "form-submissions{$tag}-{$stamp}.csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    header('Cache-Control: no-store');

    $out = fopen('php://output', 'w');
    // BOM so Excel opens UTF-8 correctly.
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, [
        'id', 'submitted_at_utc', 'full_name', 'email', 'phone', 'role',
        'clients_managed', 'bottleneck', 'webhook_status',
        'ip_address', 'referrer', 'user_agent',
    ]);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, [
            $r['id'], $r['submitted_at'], $r['full_name'], $r['email'],
            $r['phone'], $r['role'], $r['clients_managed'] ?? '',
            $r['bottleneck'] ?? '', $r['webhook_status'] ?? '',
            $r['ip_address'] ?? '', $r['referrer'] ?? '', $r['user_agent'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

// ---------------- Listing (paginated) --------------------------------------

$count_sql = "SELECT COUNT(*) FROM form_submissions $where";
$cstmt = $pdo->prepare($count_sql);
foreach ($where_par as $k => $v) $cstmt->bindValue($k, $v);
$cstmt->execute();
$matched = (int) $cstmt->fetchColumn();
$total   = $q === '' ? $matched : (int) $pdo->query('SELECT COUNT(*) FROM form_submissions')->fetchColumn();

$last_page = max(1, (int) ceil($matched / FORMS_PER_PAGE));
if ($page > $last_page) $page = $last_page;
$offset = ($page - 1) * FORMS_PER_PAGE;

$list_sql = "SELECT id, full_name, email, phone, role, clients_managed, bottleneck,
                    user_agent, referrer, ip_address, webhook_status, webhook_response,
                    submitted_at
             FROM form_submissions
             $where
             ORDER BY submitted_at DESC, id DESC
             LIMIT :lim OFFSET :off";
$stmt = $pdo->prepare($list_sql);
foreach ($where_par as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':lim', FORMS_PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset,         PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper to build URLs that preserve the current q while changing page/export.
function forms_url(string $q, ?int $page, ?string $export = null): string
{
    $p = [];
    if ($q !== '')         $p['q']      = $q;
    if ($page !== null)    $p['page']   = $page;
    if ($export !== null)  $p['export'] = $export;
    return '/admin/forms.php' . ($p ? '?' . http_build_query($p) : '');
}

admin_head('Forms', 'forms');
?>
    <div class="flex flex-wrap items-baseline justify-between gap-3">
        <h1 class="text-2xl font-semibold tracking-tight text-ink-900">Form submissions</h1>
        <span class="text-sm text-ink-500">
            <?php if ($q !== ''): ?>
                <?= $matched ?> match<?= $matched === 1 ? '' : 'es' ?> for "<?= e($q) ?>" in <?= $total ?> total
            <?php else: ?>
                <?= $total ?> total
            <?php endif; ?>
        </span>
    </div>
    <p class="mt-2 text-ink-600">Click a row to expand details. Export downloads the search-filtered set; no row cap.</p>

    <form method="get" action="/admin/forms.php" class="mt-5 flex flex-wrap items-center gap-2">
        <input type="search" name="q" value="<?= e($q) ?>"
               placeholder="Search name, email, phone, role, bottleneck…"
               class="w-72 max-w-full rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">
        <button type="submit" class="rounded-lg bg-ink-900 px-3 py-2 text-sm font-medium text-white hover:bg-ink-800">Search</button>
        <?php if ($q !== ''): ?>
            <a href="/admin/forms.php" class="text-sm text-ink-500 hover:text-ink-800">Clear</a>
        <?php endif; ?>
        <a href="<?= e(forms_url($q, null, 'csv')) ?>"
           class="ml-auto inline-flex items-center gap-1.5 rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm text-ink-700 hover:border-brand-300 hover:bg-brand-50">
            Export CSV<?= $q !== '' ? ' (filtered)' : '' ?>
        </a>
    </form>

    <?php if ($rows === []): ?>
        <div class="mt-8 rounded-2xl border border-dashed border-ink-200 bg-white/60 p-10 text-center">
            <h2 class="text-base font-medium text-ink-700"><?= $q !== '' ? 'No matches' : 'No submissions yet' ?></h2>
            <p class="mt-1 text-sm text-ink-500">
                <?= $q !== ''
                    ? 'Nothing matched <code>' . e($q) . '</code>. Try a different term or <a href="/admin/forms.php" class="underline">clear the search</a>.'
                    : "When someone fills out the waitlist form on your site, they'll show up here." ?>
            </p>
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
                            'queued'  => 'bg-amber-50 text-amber-700',
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

        <?php if ($last_page > 1): ?>
            <nav class="mt-5 flex items-center justify-between text-sm" aria-label="Pagination">
                <span class="text-ink-500">
                    Page <?= $page ?> of <?= $last_page ?>
                    <span class="text-ink-400">·</span>
                    Showing <?= $offset + 1 ?>–<?= min($offset + count($rows), $matched) ?> of <?= $matched ?>
                </span>
                <div class="flex items-center gap-1">
                    <?php if ($page > 1): ?>
                        <a href="<?= e(forms_url($q, $page - 1)) ?>" class="rounded-md border border-ink-200 bg-white px-3 py-1.5 text-ink-700 hover:border-brand-300 hover:bg-brand-50">← Prev</a>
                    <?php else: ?>
                        <span class="rounded-md border border-ink-100 bg-ink-50 px-3 py-1.5 text-ink-300">← Prev</span>
                    <?php endif; ?>
                    <?php if ($page < $last_page): ?>
                        <a href="<?= e(forms_url($q, $page + 1)) ?>" class="rounded-md border border-ink-200 bg-white px-3 py-1.5 text-ink-700 hover:border-brand-300 hover:bg-brand-50">Next →</a>
                    <?php else: ?>
                        <span class="rounded-md border border-ink-100 bg-ink-50 px-3 py-1.5 text-ink-300">Next →</span>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
<?php
admin_foot();
