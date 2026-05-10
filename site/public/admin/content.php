<?php
// site/public/admin/content.php — content_blocks editor.
//
// Read path: query all blocks, group by section prefix (text before the
// first dot), render as collapsible <details> groups for fast scanning.
// Each row has an inline editable input (text) or textarea (longer text /
// list / seo) sized by current value length. Save button posts to
// /api/content.php and updates the row in place via admin.js.
//
// Update path: per-row save (admin.js handles the AJAX). Phase 9 will
// add batched-save when the public page's inline editor lands; both
// share /api/content.php as the backend.

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';
require __DIR__ . '/_layout.php';
auth_require_login();

$pdo = db();
$rows = $pdo->query(
    'SELECT key, value, type, updated_at FROM content_blocks ORDER BY key'
)->fetchAll(PDO::FETCH_ASSOC);

// Group by section prefix. Keys like "feature.1.title" group under "feature";
// keys like "hero.headline" group under "hero". Bare keys (no dot) go under "_misc".
$groups = [];
foreach ($rows as $r) {
    $section = strpos($r['key'], '.') !== false
        ? substr($r['key'], 0, strpos($r['key'], '.'))
        : '_misc';
    $groups[$section][] = $r;
}
ksort($groups);

admin_head('Content', 'content');
?>
    <div class="flex items-baseline justify-between">
        <h1 class="text-2xl font-semibold tracking-tight text-ink-900">Content blocks</h1>
        <span class="text-sm text-ink-500"><?= count($rows) ?> keys across <?= count($groups) ?> sections</span>
    </div>
    <p class="mt-2 text-ink-600">Edit any block and click Save. Changes are live immediately on the public site.</p>

    <div class="mt-6">
        <input id="content-filter" type="search" placeholder="Filter by key…"
               class="w-full max-w-sm rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">
    </div>

    <div id="content-groups" class="mt-6 space-y-4">
        <?php foreach ($groups as $section => $items): ?>
            <details class="group rounded-2xl border border-ink-100 bg-white" open>
                <summary class="flex cursor-pointer items-center justify-between px-5 py-3 text-left">
                    <div class="flex items-center gap-3">
                        <span class="text-base font-semibold text-ink-900"><?= e($section) ?></span>
                        <span class="rounded-full bg-ink-100 px-2 py-0.5 text-xs text-ink-600"><?= count($items) ?></span>
                    </div>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 text-ink-400 transition group-open:rotate-180"><polyline points="6 9 12 15 18 9"/></svg>
                </summary>
                <div class="divide-y divide-ink-100 border-t border-ink-100">
                    <?php foreach ($items as $row): ?>
                        <?php
                            $is_long = mb_strlen($row['value']) > 80 || $row['type'] === 'list';
                            $value_for_attr = e($row['value']);
                        ?>
                        <div class="content-row p-4 sm:p-5" data-key="<?= e($row['key']) ?>">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2">
                                    <code class="text-sm font-medium text-ink-900"><?= e($row['key']) ?></code>
                                    <span class="rounded bg-brand-50 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wider text-brand-700"><?= e($row['type']) ?></span>
                                </div>
                                <span class="content-row-status text-xs text-ink-400" data-original="<?= e((string)$row['updated_at']) ?>">
                                    updated <?= e((string)$row['updated_at']) ?>
                                </span>
                            </div>
                            <div class="mt-3 flex items-stretch gap-2">
                                <?php if ($is_long): ?>
                                    <textarea
                                        class="content-row-input min-h-[5rem] w-full resize-y rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm font-mono text-ink-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200"
                                        rows="3"><?= $value_for_attr ?></textarea>
                                <?php else: ?>
                                    <input type="text"
                                        class="content-row-input w-full rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm text-ink-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200"
                                        value="<?= $value_for_attr ?>">
                                <?php endif; ?>
                                <button type="button"
                                        class="content-row-save shrink-0 self-start rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-60">
                                    Save
                                </button>
                            </div>
                            <?php if ($row['type'] === 'list'): ?>
                                <p class="mt-1.5 text-xs text-ink-400">Expects JSON array, e.g. <code>["one","two","three"]</code></p>
                            <?php elseif ($row['type'] === 'icon'): ?>
                                <p class="mt-1.5 text-xs text-ink-400">Lucide icon name, e.g. <code>calendar-days</code> &mdash; see <a href="https://lucide.dev/icons" target="_blank" rel="noopener" class="underline">lucide.dev/icons</a></p>
                            <?php elseif ($row['type'] === 'image'): ?>
                                <p class="mt-1.5 text-xs text-ink-400">Image URL or path. Phase 12 adds an upload picker.</p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endforeach; ?>
    </div>
<?php
admin_foot();
