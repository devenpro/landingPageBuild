<?php
// site/public/admin/brand-sync.php — drift resolution UI for the BCL (v2 Stage 2).
//
// Lists every item where the on-disk file differs from the DB. For each
// row, shows the title, the current diff (DB body vs disk body), and three
// action buttons:
//   - Accept disk : DB takes the on-disk body (overwrites DB)
//   - Keep DB     : disk file rewritten from DB body (overwrites disk)
//   - Manual      : open a textarea pre-filled with the disk body, edit
//                   freely, save replaces both.
//
// Diffs are inline word-level for short bodies; long bodies fall back to
// side-by-side full text. Either way the admin can see what changed.

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';
require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../../../core/lib/brand/sync.php';

auth_require_login();

$dirty = brand_sync_dirty();
$saved = isset($_GET['saved']);
$error = (string) ($_GET['error'] ?? '');

admin_head('Brand sync', 'brand');
?>
    <div class="flex items-baseline justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-ink-900">Brand sync</h1>
            <p class="mt-2 text-ink-600">Reconcile on-disk edits made via Claude Code (or other repo-aware tools) back into the canonical DB.</p>
        </div>
        <a href="/admin/brand.php" class="text-sm text-brand-700 hover:text-brand-800">&larr; Back to library</a>
    </div>

    <?php if ($saved): ?>
        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-900">Sync action applied.</div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-900"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($dirty === []): ?>
        <div class="mt-6 rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-6 text-center text-sm text-emerald-900">
            No drift detected — DB and disk are in sync.
        </div>
    <?php else: ?>
        <p class="mt-4 text-sm text-ink-500"><?= count($dirty) ?> item<?= count($dirty) === 1 ? '' : 's' ?> with disk drift.</p>

        <div class="mt-4 space-y-4">
            <?php foreach ($dirty as $d): ?>
                <article class="rounded-xl border border-ink-100 bg-white p-4">
                    <header class="flex flex-wrap items-baseline justify-between gap-2">
                        <div>
                            <h2 class="text-base font-semibold text-ink-900"><?= e($d['title']) ?></h2>
                            <div class="mt-0.5 text-xs text-ink-500"><?= e($d['category']) ?> / <code><?= e($d['slug']) ?></code> · <code><?= e($d['path']) ?></code></div>
                        </div>
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold uppercase tracking-wider text-amber-800"><?= e($d['state']) ?></span>
                    </header>

                    <?php if ($d['state'] === 'disk_missing'): ?>
                        <p class="mt-3 text-sm text-ink-600">The on-disk file was deleted but the DB row remains. Re-creating the file from the DB body, or marking the item archived, are the typical resolutions.</p>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <form method="post" action="/api/brand/sync.php" class="inline">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="strategy" value="keep_db">
                                <input type="hidden" name="item_id" value="<?= (int)$d['item_id'] ?>">
                                <button type="submit" class="rounded-md border border-brand-600 bg-brand-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-700">Re-write disk from DB</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="mt-3 grid gap-3 lg:grid-cols-2">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wider text-ink-500">DB (current)</div>
                                <pre class="mt-1 max-h-72 overflow-auto rounded-md border border-ink-100 bg-ink-50 p-2 font-mono text-xs"><?= e((string)($d['db_body'] ?? '')) ?></pre>
                            </div>
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wider text-ink-500">Disk</div>
                                <pre class="mt-1 max-h-72 overflow-auto rounded-md border border-ink-100 bg-ink-50 p-2 font-mono text-xs"><?= e((string)($d['disk_body'] ?? '')) ?></pre>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <form method="post" action="/api/brand/sync.php" class="inline">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="strategy" value="accept_disk">
                                <input type="hidden" name="item_id" value="<?= (int)$d['item_id'] ?>">
                                <button type="submit" class="rounded-md border border-emerald-600 bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-700">Accept disk</button>
                            </form>
                            <form method="post" action="/api/brand/sync.php" class="inline">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="strategy" value="keep_db">
                                <input type="hidden" name="item_id" value="<?= (int)$d['item_id'] ?>">
                                <button type="submit" class="rounded-md border border-ink-200 bg-white px-3 py-1.5 text-xs font-medium text-ink-700 hover:border-ink-300 hover:bg-ink-50">Keep DB</button>
                            </form>
                            <details class="inline">
                                <summary class="cursor-pointer rounded-md border border-ink-200 bg-white px-3 py-1.5 text-xs font-medium text-ink-700 hover:border-ink-300 hover:bg-ink-50">Manual merge…</summary>
                                <form method="post" action="/api/brand/sync.php" class="mt-3 space-y-2">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="strategy" value="manual">
                                    <input type="hidden" name="item_id" value="<?= (int)$d['item_id'] ?>">
                                    <textarea name="manual_body" rows="10" class="w-full rounded-md border border-ink-200 bg-white px-3 py-2 font-mono text-xs"><?= e((string)($d['disk_body'] ?? '')) ?></textarea>
                                    <button type="submit" class="rounded-md border border-brand-600 bg-brand-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-700">Save merged body</button>
                                </form>
                            </details>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php
admin_foot();
