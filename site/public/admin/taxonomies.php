<?php
// site/public/admin/taxonomies.php — taxonomy tree editor (v2 Stage 5).
//
// Two-pane:
//   - Left: list of taxonomies (Locations, Service Categories)
//   - Right: indented tree of the active taxonomy's terms + inline forms
//     to add child, rename, delete, change parent
//
// Drag-and-drop reorder is deferred to Stage 9 (front-end canvas). Stage 5
// ships server-rendered forms — works without JS.

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';
require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../../../core/lib/taxonomy.php';

auth_require_login();

$taxonomies = taxonomies_all();
$active_slug = (string)($_GET['tax'] ?? ($taxonomies[0]['slug'] ?? ''));
$active = null;
foreach ($taxonomies as $t) {
    if ($t['slug'] === $active_slug) { $active = $t; break; }
}
if ($active === null && $taxonomies !== []) {
    $active = $taxonomies[0];
    $active_slug = $active['slug'];
}

$tree = $active ? taxonomy_tree((int)$active['id']) : [];
$flat = $active ? taxonomy_terms_all((int)$active['id']) : []; // for parent dropdown

$saved   = isset($_GET['saved']);
$error   = (string)($_GET['error'] ?? '');

admin_head('Taxonomies', 'taxonomies');

/**
 * Render a term node and recurse into children with indentation.
 */
$render_node = null;
$render_node = function (array $node, int $depth, string $tax_slug, array $flat) use (&$render_node): void {
    $pad = $depth * 16 + 12;
    ?>
    <li class="border-t border-ink-100">
        <div class="flex flex-wrap items-baseline justify-between gap-2 py-2" style="padding-left: <?= $pad ?>px; padding-right: 12px;">
            <div class="flex items-baseline gap-2">
                <?php if ($depth > 0): ?><span class="text-ink-400 text-xs">└</span><?php endif; ?>
                <span class="text-sm font-medium text-ink-800"><?= e($node['name']) ?></span>
                <code class="text-xs text-ink-500"><?= e($node['slug']) ?></code>
            </div>
            <div class="flex items-center gap-2">
                <details class="text-xs">
                    <summary class="cursor-pointer text-brand-700 hover:text-brand-800">+ child</summary>
                    <form method="post" action="/api/taxonomies.php" class="mt-2 flex flex-wrap items-center gap-1">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="tax" value="<?= e($tax_slug) ?>">
                        <input type="hidden" name="parent_id" value="<?= (int)$node['id'] ?>">
                        <input name="name" type="text" placeholder="Name" required class="w-32 rounded-md border border-ink-200 bg-white px-2 py-1 text-xs">
                        <input name="slug" type="text" placeholder="slug" required pattern="[a-z0-9](?:[a-z0-9-]*[a-z0-9])?" class="w-32 rounded-md border border-ink-200 bg-white px-2 py-1 font-mono text-xs">
                        <button type="submit" class="rounded-md bg-brand-600 px-2 py-1 text-xs font-medium text-white hover:bg-brand-700">Add</button>
                    </form>
                </details>
                <details class="text-xs">
                    <summary class="cursor-pointer text-ink-700 hover:text-ink-900">edit</summary>
                    <form method="post" action="/api/taxonomies.php" class="mt-2 flex flex-wrap items-center gap-1">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="tax" value="<?= e($tax_slug) ?>">
                        <input type="hidden" name="id" value="<?= (int)$node['id'] ?>">
                        <input name="name" type="text" value="<?= e($node['name']) ?>" required class="w-32 rounded-md border border-ink-200 bg-white px-2 py-1 text-xs">
                        <input name="slug" type="text" value="<?= e($node['slug']) ?>" required pattern="[a-z0-9](?:[a-z0-9-]*[a-z0-9])?" class="w-32 rounded-md border border-ink-200 bg-white px-2 py-1 font-mono text-xs">
                        <select name="parent_id" class="rounded-md border border-ink-200 bg-white px-2 py-1 text-xs">
                            <option value="">(root)</option>
                            <?php foreach ($flat as $f):
                                if ((int)$f['id'] === (int)$node['id']) continue; // can't be own parent
                            ?>
                                <option value="<?= (int)$f['id'] ?>" <?= (int)$node['parent_id'] === (int)$f['id'] ? 'selected' : '' ?>>
                                    <?= e($f['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="rounded-md bg-brand-600 px-2 py-1 text-xs font-medium text-white hover:bg-brand-700">Save</button>
                    </form>
                </details>
                <form method="post" action="/api/taxonomies.php" class="inline" onsubmit="return confirm('Delete this term and all its children?');">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="tax" value="<?= e($tax_slug) ?>">
                    <input type="hidden" name="id" value="<?= (int)$node['id'] ?>">
                    <button type="submit" class="text-xs text-rose-700 hover:text-rose-800">×</button>
                </form>
            </div>
        </div>
        <?php if (!empty($node['children'])): ?>
            <ul>
                <?php foreach ($node['children'] as $child) $render_node($child, $depth + 1, $tax_slug, $flat); ?>
            </ul>
        <?php endif; ?>
    </li>
    <?php
};
?>
    <div class="flex flex-wrap items-baseline justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-ink-900">Taxonomies</h1>
            <p class="mt-2 text-ink-600">Hierarchical classification for content entries. Used by Location Services (cities) and Service Categories (industry groupings).</p>
        </div>
    </div>

    <?php if ($saved): ?>
        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-900">Saved.</div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-900"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="mt-6 grid gap-4 lg:grid-cols-[220px_1fr]">
        <aside class="rounded-xl border border-ink-100 bg-white">
            <div class="border-b border-ink-100 px-3 py-2 text-xs font-semibold uppercase tracking-wider text-ink-500">Taxonomies</div>
            <ul class="divide-y divide-ink-100">
                <?php foreach ($taxonomies as $t):
                    $is_active = $t['slug'] === $active_slug;
                    $cls = $is_active ? 'bg-brand-50 border-l-2 border-brand-600' : 'hover:bg-ink-50';
                ?>
                    <li>
                        <a href="?tax=<?= e($t['slug']) ?>" class="block px-3 py-2 text-sm <?= $cls ?>">
                            <div class="flex items-baseline justify-between gap-2">
                                <span class="font-medium text-ink-800"><?= e($t['name']) ?></span>
                                <?php if ((int)$t['is_hierarchical'] === 1): ?>
                                    <span class="text-[10px] font-semibold uppercase tracking-wider text-ink-500">tree</span>
                                <?php endif; ?>
                            </div>
                            <div class="mt-0.5 text-xs text-ink-500"><code><?= e($t['slug']) ?></code></div>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </aside>

        <section class="rounded-xl border border-ink-100 bg-white">
            <?php if ($active === null): ?>
                <div class="px-4 py-6 text-sm text-ink-500">Pick a taxonomy on the left.</div>
            <?php else: ?>
                <header class="border-b border-ink-100 px-4 py-3">
                    <h2 class="text-lg font-semibold text-ink-900"><?= e($active['name']) ?></h2>
                    <?php if (!empty($active['description'])): ?>
                        <p class="mt-1 text-xs text-ink-500"><?= e($active['description']) ?></p>
                    <?php endif; ?>
                </header>

                <ul class="overflow-hidden">
                    <?php if ($tree === []): ?>
                        <li class="px-4 py-6 text-sm text-ink-500">No terms yet. Add the first root term below.</li>
                    <?php else: ?>
                        <?php foreach ($tree as $node) $render_node($node, 0, $active_slug, $flat); ?>
                    <?php endif; ?>
                </ul>

                <!-- Add root term -->
                <form method="post" action="/api/taxonomies.php" class="grid gap-2 border-t border-ink-100 p-4 sm:grid-cols-[1fr_1fr_auto]">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="tax" value="<?= e($active_slug) ?>">

                    <input name="name" type="text" placeholder="New root term name" required class="rounded-md border border-ink-200 bg-white px-3 py-2 text-sm">
                    <input name="slug" type="text" placeholder="kebab-slug" required pattern="[a-z0-9](?:[a-z0-9-]*[a-z0-9])?" class="rounded-md border border-ink-200 bg-white px-3 py-2 font-mono text-xs">
                    <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Add root term</button>
                </form>
            <?php endif; ?>
        </section>
    </div>
<?php
admin_foot();
