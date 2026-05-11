<?php
// site/public/admin/brand.php — Brand Context Library editor (v2 Stage 2).
//
// Three-panel layout:
//   - Left rail: categories with item counts and required/missing badges
//   - Middle: items in the active category
//   - Right: the active item's editor (title, kind, body, always_on, status)
//
// Active category and item come from query params (?cat=…&item=…) so a
// link from the dashboard banner can deep-link straight to a specific row.
//
// Save flow goes through /api/brand/items.php (POST). After save the API
// redirects back here with ?saved=1 (or ?error=…) — no JS required.

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';
require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../../../core/lib/brand/categories.php';
require_once __DIR__ . '/../../../core/lib/brand/items.php';
require_once __DIR__ . '/../../../core/lib/brand/sync.php';
require_once __DIR__ . '/../../../core/lib/brand/audit.php';

auth_require_login();

$cats   = brand_categories_all();
$counts = brand_category_item_counts();
$dirty  = brand_sync_dirty();
$audit  = brand_audit();

$active_cat_slug = (string) ($_GET['cat'] ?? ($cats[0]['slug'] ?? ''));
$active_cat = brand_category_by_slug($active_cat_slug);
if ($active_cat === null && $cats !== []) {
    $active_cat = $cats[0];
    $active_cat_slug = $active_cat['slug'];
}

$items = $active_cat ? brand_items_by_category((int)$active_cat['id'], null) : [];
$active_item_id = (int) ($_GET['item'] ?? ($items[0]['id'] ?? 0));
$active_item = $active_item_id > 0 ? brand_item_by_id($active_item_id) : null;
if ($active_item !== null && (int)$active_item['category_id'] !== (int)$active_cat['id']) {
    // mismatched query params — fall back to first item of the active cat
    $active_item = $items[0] ?? null;
    $active_item_id = $active_item['id'] ?? 0;
}

$saved = isset($_GET['saved']);
$error = (string) ($_GET['error'] ?? '');
$created = isset($_GET['created']);

admin_head('Brand Context Library', 'brand');
?>
    <div class="flex flex-wrap items-baseline justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-ink-900">Brand Context Library</h1>
            <p class="mt-2 text-ink-600">Categorised brand knowledge that AI tools (chatbot, page generator, ad copywriter) read as ground truth.</p>
        </div>
        <div class="flex items-center gap-2 text-sm">
            <span class="rounded-full bg-ink-100 px-3 py-1 font-medium text-ink-700">Score: <?= (int)$audit['score'] ?>%</span>
            <span class="rounded-full bg-ink-100 px-3 py-1 text-ink-500"><?= (int)$audit['totals']['items_ok'] ?> ok · <?= (int)$audit['totals']['items_missing'] ?> missing · <?= (int)$audit['totals']['items_stale'] ?> awaiting review</span>
        </div>
    </div>

    <?php if ($dirty !== []): ?>
        <div class="mt-4 flex items-center justify-between gap-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <div>
                <strong><?= count($dirty) ?> disk change<?= count($dirty) === 1 ? '' : 's' ?> detected</strong> in <code class="rounded bg-amber-100 px-1.5 py-0.5">.brand/</code> — Claude Code or another tool edited the files outside the admin.
            </div>
            <a href="/admin/brand-sync.php" class="inline-flex items-center gap-1.5 rounded-md border border-amber-300 bg-white px-3 py-1.5 font-medium text-amber-900 hover:border-amber-400 hover:bg-amber-100">Review &amp; merge</a>
        </div>
    <?php endif; ?>

    <?php if ($saved): ?>
        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-900">Item saved.</div>
    <?php endif; ?>
    <?php if ($created): ?>
        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-900">Item created.</div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-900"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="mt-6 grid gap-4 lg:grid-cols-[220px_280px_1fr]">
        <!-- Categories rail -->
        <aside class="rounded-xl border border-ink-100 bg-white">
            <div class="border-b border-ink-100 px-3 py-2 text-xs font-semibold uppercase tracking-wider text-ink-500">Categories</div>
            <ul class="divide-y divide-ink-100">
                <?php foreach ($cats as $c):
                    $is_active = $c['slug'] === $active_cat_slug;
                    $count = (int)($counts[(int)$c['id']] ?? 0);
                    $cls = $is_active ? 'bg-brand-50 border-l-2 border-brand-600' : 'hover:bg-ink-50';
                ?>
                    <li>
                        <a href="?cat=<?= e($c['slug']) ?>" class="block px-3 py-2 text-sm <?= $cls ?>">
                            <div class="flex items-baseline justify-between gap-2">
                                <span class="font-medium text-ink-800"><?= e($c['label']) ?></span>
                                <?php if ((int)$c['required'] === 1): ?>
                                    <span class="text-[10px] font-semibold uppercase tracking-wider text-rose-600">req</span>
                                <?php endif; ?>
                            </div>
                            <div class="mt-0.5 text-xs text-ink-500"><?= $count ?> item<?= $count === 1 ? '' : 's' ?></div>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </aside>

        <!-- Items list -->
        <section class="rounded-xl border border-ink-100 bg-white">
            <div class="flex items-center justify-between border-b border-ink-100 px-3 py-2">
                <div class="text-xs font-semibold uppercase tracking-wider text-ink-500"><?= e($active_cat['label'] ?? '') ?> items</div>
                <a href="#new-item" class="text-xs font-medium text-brand-700 hover:text-brand-800">+ New item</a>
            </div>
            <?php if ($items === []): ?>
                <div class="px-3 py-6 text-sm text-ink-500">No items yet.</div>
            <?php else: ?>
                <ul class="divide-y divide-ink-100">
                    <?php foreach ($items as $i):
                        $is_active = (int)$i['id'] === $active_item_id;
                        $cls = $is_active ? 'bg-brand-50 border-l-2 border-brand-600' : 'hover:bg-ink-50';
                        $body_len = strlen((string)$i['body']);
                        $is_empty = $body_len === 0;
                        $unreviewed = $i['source'] === 'ai' && (int)$i['ai_reviewed'] === 0;
                    ?>
                        <li>
                            <a href="?cat=<?= e($active_cat_slug) ?>&item=<?= (int)$i['id'] ?>" class="block px-3 py-2.5 text-sm <?= $cls ?>">
                                <div class="flex items-baseline justify-between gap-2">
                                    <span class="font-medium text-ink-800"><?= e($i['title']) ?></span>
                                    <?php if ($unreviewed): ?>
                                        <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-amber-800">AI · review</span>
                                    <?php elseif ($is_empty): ?>
                                        <span class="rounded bg-ink-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-ink-500">empty</span>
                                    <?php elseif ((int)$i['always_on'] === 1): ?>
                                        <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-emerald-800">always-on</span>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-0.5 text-xs text-ink-500"><code><?= e($i['slug']) ?></code> · <?= e($i['kind']) ?> · v<?= (int)$i['version'] ?></div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!-- Editor -->
        <section class="rounded-xl border border-ink-100 bg-white">
            <?php if ($active_item === null): ?>
                <div class="px-4 py-6 text-sm text-ink-500">Select an item on the left or create a new one below.</div>
            <?php else: ?>
                <form method="post" action="/api/brand/items.php" class="space-y-4 p-4">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= (int)$active_item['id'] ?>">
                    <input type="hidden" name="cat" value="<?= e($active_cat_slug) ?>">

                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <div>
                            <h2 class="text-lg font-semibold text-ink-900"><?= e($active_item['title']) ?></h2>
                            <div class="mt-0.5 text-xs text-ink-500">
                                <?= e($active_cat['label']) ?> · <code><?= e($active_item['slug']) ?></code> · v<?= (int)$active_item['version'] ?> · source: <?= e($active_item['source']) ?>
                                <?php if ($active_item['source'] === 'ai' && (int)$active_item['ai_reviewed'] === 0): ?>
                                    · <span class="font-semibold text-amber-700">awaiting review</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <a href="#" onclick="event.preventDefault();if(confirm('Delete this item? .brand/<?= e($active_cat_slug) ?>/<?= e($active_item['slug']) ?>.md will also be removed.')){document.getElementById('delete-form').submit();}" class="text-xs text-rose-700 hover:text-rose-800">Delete</a>
                    </div>

                    <div>
                        <label class="text-xs font-medium uppercase tracking-wider text-ink-500" for="brand_title">Title</label>
                        <input id="brand_title" name="title" type="text" value="<?= e($active_item['title']) ?>" required
                               class="mt-1 w-full rounded-md border border-ink-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wider text-ink-500" for="brand_kind">Kind</label>
                            <select id="brand_kind" name="kind" class="mt-1 w-full rounded-md border border-ink-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                                <?php foreach (['markdown','facts','links','refs'] as $k): ?>
                                    <option value="<?= $k ?>" <?= $active_item['kind'] === $k ? 'selected' : '' ?>><?= $k ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wider text-ink-500" for="brand_status">Status</label>
                            <select id="brand_status" name="status" class="mt-1 w-full rounded-md border border-ink-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                                <?php foreach (['active','draft','archived'] as $s): ?>
                                    <option value="<?= $s ?>" <?= $active_item['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="mt-6 inline-flex items-center gap-2 text-sm text-ink-700">
                                <input type="checkbox" name="always_on" value="1" <?= (int)$active_item['always_on'] === 1 ? 'checked' : '' ?>>
                                Always inject in chat prompts
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-medium uppercase tracking-wider text-ink-500" for="brand_body">Body</label>
                        <textarea id="brand_body" name="body" rows="14"
                                  class="mt-1 w-full rounded-md border border-ink-200 bg-white px-3 py-2 font-mono text-xs focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"><?= e($active_item['body']) ?></textarea>
                        <p class="mt-1 text-xs text-ink-500">Mirrors to <code>.brand/<?= e($active_cat_slug) ?>/<?= e($active_item['slug']) ?>.md</code> on save. Leave blank for a placeholder.</p>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs text-ink-500">Saving marks <code>ai_reviewed=1</code> if it was awaiting review.</p>
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-md border border-brand-600 bg-brand-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-1">Save</button>
                    </div>
                </form>

                <!-- Hidden delete form to keep the click handler simple -->
                <form id="delete-form" method="post" action="/api/brand/items.php" class="hidden">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$active_item['id'] ?>">
                    <input type="hidden" name="cat" value="<?= e($active_cat_slug) ?>">
                </form>
            <?php endif; ?>

            <!-- New-item form -->
            <details id="new-item" class="border-t border-ink-100 px-4 py-3" <?= $active_item === null ? 'open' : '' ?>>
                <summary class="cursor-pointer text-sm font-medium text-brand-700">+ New item in <?= e($active_cat['label'] ?? '') ?></summary>
                <form method="post" action="/api/brand/items.php" class="mt-3 space-y-3">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="cat" value="<?= e($active_cat_slug) ?>">
                    <input type="hidden" name="category_id" value="<?= (int)($active_cat['id'] ?? 0) ?>">

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wider text-ink-500" for="new_title">Title</label>
                            <input id="new_title" name="title" type="text" required class="mt-1 w-full rounded-md border border-ink-200 bg-white px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wider text-ink-500" for="new_slug">Slug</label>
                            <input id="new_slug" name="slug" type="text" required pattern="[a-z0-9](?:[a-z0-9-]*[a-z0-9])?" class="mt-1 w-full rounded-md border border-ink-200 bg-white px-3 py-2 font-mono text-xs">
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-medium uppercase tracking-wider text-ink-500" for="new_kind">Kind</label>
                        <select id="new_kind" name="kind" class="mt-1 w-full rounded-md border border-ink-200 bg-white px-3 py-2 text-sm sm:w-auto">
                            <?php foreach (['markdown','facts','links','refs'] as $k): ?>
                                <option value="<?= $k ?>"><?= $k ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-medium uppercase tracking-wider text-ink-500" for="new_body">Body (optional)</label>
                        <textarea id="new_body" name="body" rows="6" class="mt-1 w-full rounded-md border border-ink-200 bg-white px-3 py-2 font-mono text-xs"></textarea>
                    </div>

                    <div class="text-right">
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-md border border-brand-600 bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Create</button>
                    </div>
                </form>
            </details>
        </section>
    </div>
<?php
admin_foot();
