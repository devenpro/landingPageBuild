<?php
// site/public/admin/blocks.php — Block Library (v2 Stage 3).
//
// Replaces the v1 flat content_blocks table with a hierarchical view:
// left pane lists block definitions (hero, features, faq, …), right
// pane edits the active block's fields. Each block is a reusable unit
// that one or more pages can include via block('<slug>'); per-page
// overrides live in page_fields, not here.
//
// Save flow:
//   - "Save fields" POSTs all field values to /api/blocks.php (action=save_fields)
//   - "Create field" / "Delete field" / "Save block meta" all go through
//     the same API endpoint with different action values
//
// Query params: ?block=<slug> selects the active block; defaults to the
// first row from content_blocks.

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';
require __DIR__ . '/_layout.php';
auth_require_login();

$pdo = db();

$blocks = $pdo->query(
    "SELECT cb.*, COUNT(cbf.id) AS field_count
       FROM content_blocks cb
       LEFT JOIN content_block_fields cbf ON cbf.block_id = cb.id
      WHERE cb.status != 'archived'
      GROUP BY cb.id
      ORDER BY cb.category, cb.slug"
)->fetchAll();

$active_slug = (string) ($_GET['block'] ?? ($blocks[0]['slug'] ?? ''));
$active = null;
foreach ($blocks as $b) {
    if ($b['slug'] === $active_slug) { $active = $b; break; }
}
if ($active === null && $blocks !== []) {
    $active = $blocks[0];
    $active_slug = $active['slug'];
}

$fields = [];
if ($active !== null) {
    $stmt = $pdo->prepare(
        'SELECT * FROM content_block_fields
          WHERE block_id = :b
          ORDER BY position, field_key'
    );
    $stmt->execute([':b' => $active['id']]);
    $fields = $stmt->fetchAll();
}

$saved   = isset($_GET['saved']);
$created = isset($_GET['created']);
$error   = (string) ($_GET['error'] ?? '');

admin_head('Blocks', 'blocks');
?>
    <div class="flex flex-wrap items-baseline justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-ink-900">Block library</h1>
            <p class="mt-2 text-ink-600">Reusable content blocks. Each block has its own fields; pages include blocks and may override specific fields via <code class="rounded bg-ink-100 px-1.5 py-0.5">page_fields</code>.</p>
        </div>
        <span class="text-sm text-ink-500"><?= count($blocks) ?> block<?= count($blocks) === 1 ? '' : 's' ?></span>
    </div>

    <?php if ($saved): ?>
        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-900">Saved.</div>
    <?php endif; ?>
    <?php if ($created): ?>
        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-900">Block created.</div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-900"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="mt-6 grid gap-4 lg:grid-cols-[260px_1fr]">
        <!-- Block list -->
        <aside class="rounded-xl border border-ink-100 bg-white">
            <div class="flex items-center justify-between border-b border-ink-100 px-3 py-2">
                <div class="text-xs font-semibold uppercase tracking-wider text-ink-500">Blocks</div>
                <a href="#new-block" class="text-xs font-medium text-brand-700 hover:text-brand-800">+ New</a>
            </div>
            <?php if ($blocks === []): ?>
                <div class="px-3 py-6 text-sm text-ink-500">No blocks yet.</div>
            <?php else: ?>
                <ul class="divide-y divide-ink-100">
                    <?php foreach ($blocks as $b):
                        $is_active = $b['slug'] === $active_slug;
                        $cls = $is_active ? 'bg-brand-50 border-l-2 border-brand-600' : 'hover:bg-ink-50';
                    ?>
                        <li>
                            <a href="?block=<?= e($b['slug']) ?>" class="block px-3 py-2 text-sm <?= $cls ?>">
                                <div class="flex items-baseline justify-between gap-2">
                                    <span class="font-medium text-ink-800"><?= e($b['name']) ?></span>
                                    <span class="text-xs text-ink-500"><?= (int)$b['field_count'] ?></span>
                                </div>
                                <div class="mt-0.5 text-xs text-ink-500"><code><?= e($b['slug']) ?></code><?= $b['status'] !== 'active' ? ' · ' . e($b['status']) : '' ?></div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </aside>

        <!-- Block editor -->
        <section class="rounded-xl border border-ink-100 bg-white">
            <?php if ($active === null): ?>
                <div class="px-4 py-6 text-sm text-ink-500">No block selected.</div>
            <?php else: ?>
                <header class="border-b border-ink-100 px-4 py-3">
                    <form method="post" action="/api/blocks.php" class="space-y-3">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="save_meta">
                        <input type="hidden" name="id" value="<?= (int)$active['id'] ?>">
                        <input type="hidden" name="slug" value="<?= e($active['slug']) ?>">

                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <h2 class="text-lg font-semibold text-ink-900"><?= e($active['name']) ?></h2>
                            <a href="#" onclick="event.preventDefault();if(confirm('Delete block <?= e($active['slug']) ?> and all its fields?')){document.getElementById('delete-block-form').submit();}" class="text-xs text-rose-700 hover:text-rose-800">Delete block</a>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="text-xs font-medium uppercase tracking-wider text-ink-500" for="block_name">Name</label>
                                <input id="block_name" name="name" type="text" value="<?= e($active['name']) ?>" required
                                       class="mt-1 w-full rounded-md border border-ink-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                            </div>
                            <div>
                                <label class="text-xs font-medium uppercase tracking-wider text-ink-500" for="block_category">Category</label>
                                <input id="block_category" name="category" type="text" value="<?= e($active['category']) ?>"
                                       class="mt-1 w-full rounded-md border border-ink-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                            </div>
                        </div>
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wider text-ink-500" for="block_desc">Description</label>
                            <textarea id="block_desc" name="description" rows="2"
                                      class="mt-1 w-full rounded-md border border-ink-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"><?= e($active['description'] ?? '') ?></textarea>
                        </div>
                        <div class="text-right">
                            <button type="submit" class="rounded-md border border-ink-300 bg-white px-3 py-1.5 text-xs font-medium text-ink-700 hover:border-ink-400 hover:bg-ink-50">Save block info</button>
                        </div>
                    </form>
                </header>

                <!-- Fields editor -->
                <form method="post" action="/api/blocks.php" class="space-y-3 p-4">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="save_fields">
                    <input type="hidden" name="block_id" value="<?= (int)$active['id'] ?>">
                    <input type="hidden" name="slug" value="<?= e($active['slug']) ?>">

                    <h3 class="text-sm font-semibold uppercase tracking-wider text-ink-500">Fields (<?= count($fields) ?>)</h3>

                    <?php if ($fields === []): ?>
                        <p class="text-sm text-ink-500">No fields yet. Add one below.</p>
                    <?php endif; ?>

                    <?php foreach ($fields as $i => $f):
                        $is_long = mb_strlen((string)$f['value']) > 80 || $f['type'] === 'list' || $f['type'] === 'html';
                    ?>
                        <div class="rounded-lg border border-ink-100 bg-ink-50/50 p-3">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <code class="text-sm font-medium text-ink-900"><?= e($f['field_key']) ?></code>
                                <span class="rounded bg-brand-50 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wider text-brand-700"><?= e($f['type']) ?></span>
                            </div>
                            <?php if ($is_long): ?>
                                <textarea name="fields[<?= e($f['field_key']) ?>][value]" rows="3"
                                          class="mt-2 w-full rounded-md border border-ink-200 bg-white px-3 py-2 font-mono text-xs"><?= e((string)$f['value']) ?></textarea>
                            <?php else: ?>
                                <input name="fields[<?= e($f['field_key']) ?>][value]" type="text" value="<?= e((string)$f['value']) ?>"
                                       class="mt-2 w-full rounded-md border border-ink-200 bg-white px-3 py-2 text-sm">
                            <?php endif; ?>
                            <input type="hidden" name="fields[<?= e($f['field_key']) ?>][type]" value="<?= e($f['type']) ?>">
                        </div>
                    <?php endforeach; ?>

                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs text-ink-500">Updates here are live on the site immediately.</p>
                        <button type="submit" class="rounded-md border border-brand-600 bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Save fields</button>
                    </div>
                </form>

                <!-- Add new field -->
                <details class="border-t border-ink-100 px-4 py-3">
                    <summary class="cursor-pointer text-sm font-medium text-brand-700">+ Add field to <?= e($active['name']) ?></summary>
                    <form method="post" action="/api/blocks.php" class="mt-3 grid gap-2 sm:grid-cols-[1fr_120px_auto]">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="add_field">
                        <input type="hidden" name="block_id" value="<?= (int)$active['id'] ?>">
                        <input type="hidden" name="slug" value="<?= e($active['slug']) ?>">

                        <input name="field_key" type="text" placeholder="field_key (e.g. headline)" required
                               class="rounded-md border border-ink-200 bg-white px-3 py-2 text-sm">
                        <select name="type" class="rounded-md border border-ink-200 bg-white px-3 py-2 text-sm">
                            <?php foreach (['text','image','video','icon','list','seo','html'] as $t): ?>
                                <option value="<?= $t ?>"><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="rounded-md border border-brand-600 bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Add field</button>
                    </form>
                </details>

                <!-- Hidden delete form -->
                <form id="delete-block-form" method="post" action="/api/blocks.php" class="hidden">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$active['id'] ?>">
                </form>
            <?php endif; ?>

            <!-- New block form -->
            <details id="new-block" class="border-t border-ink-100 px-4 py-3">
                <summary class="cursor-pointer text-sm font-medium text-brand-700">+ Create new block</summary>
                <form method="post" action="/api/blocks.php" class="mt-3 grid gap-2 sm:grid-cols-2">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="create">

                    <div>
                        <label class="text-xs font-medium uppercase tracking-wider text-ink-500" for="nb_slug">Slug</label>
                        <input id="nb_slug" name="slug" type="text" required pattern="[a-z0-9](?:[a-z0-9-_]*[a-z0-9])?"
                               class="mt-1 w-full rounded-md border border-ink-200 bg-white px-3 py-2 font-mono text-xs">
                    </div>
                    <div>
                        <label class="text-xs font-medium uppercase tracking-wider text-ink-500" for="nb_name">Name</label>
                        <input id="nb_name" name="name" type="text" required
                               class="mt-1 w-full rounded-md border border-ink-200 bg-white px-3 py-2 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-xs font-medium uppercase tracking-wider text-ink-500" for="nb_desc">Description (optional)</label>
                        <textarea id="nb_desc" name="description" rows="2" class="mt-1 w-full rounded-md border border-ink-200 bg-white px-3 py-2 text-sm"></textarea>
                    </div>
                    <div class="sm:col-span-2 text-right">
                        <button type="submit" class="rounded-md border border-brand-600 bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Create</button>
                    </div>
                </form>
            </details>
        </section>
    </div>
<?php
admin_foot();
