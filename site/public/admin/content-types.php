<?php
// site/public/admin/content-types.php — Content Manage hub (v2 Stage 4).
//
// Three-pane layout:
//   - Left rail: list of content types (Testimonials, Services, Ad LPs)
//   - Middle: entries for the active type
//   - Right: editor for the active entry
//
// Saves go through /api/content/entries.php. Form fields are rendered
// from the type's schema_json (so the admin sees per-type fields without
// us hard-coding them per page).

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';
require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../../../core/lib/content/types.php';
require_once __DIR__ . '/../../../core/lib/content/entries.php';

auth_require_login();

$types = content_types_all(null);
$active_type_slug = (string)($_GET['type'] ?? ($types[0]['slug'] ?? ''));
$active_type = null;
foreach ($types as $t) {
    if ($t['slug'] === $active_type_slug) { $active_type = $t; break; }
}
if ($active_type === null && $types !== []) {
    $active_type = $types[0];
    $active_type_slug = $active_type['slug'];
}

$entries = $active_type ? content_entries_for_type((int)$active_type['id'], null) : [];

$active_entry_id = (int)($_GET['entry'] ?? 0);
$active_entry = null;
if ($active_entry_id > 0) {
    foreach ($entries as $e) {
        if ((int)$e['id'] === $active_entry_id) { $active_entry = $e; break; }
    }
}

$type_fields = $active_type ? content_type_fields($active_type) : [];
$entry_data  = $active_entry ? content_entry_data($active_entry) : [];

$saved   = isset($_GET['saved']);
$created = isset($_GET['created']);
$error   = (string)($_GET['error'] ?? '');

admin_head('Content types', 'content_types');
?>
    <div class="flex flex-wrap items-baseline justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-ink-900">Content types</h1>
            <p class="mt-2 text-ink-600">Testimonials, services, and ad landing pages live as content entries. Routable types resolve to <code class="rounded bg-ink-100 px-1.5 py-0.5">/services/{slug}</code> and <code class="rounded bg-ink-100 px-1.5 py-0.5">/lp/{slug}</code>.</p>
        </div>
    </div>

    <?php if ($saved): ?>
        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-900">Saved.</div>
    <?php endif; ?>
    <?php if ($created): ?>
        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-900">Created.</div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-900"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="mt-6 grid gap-4 lg:grid-cols-[220px_300px_1fr]">
        <!-- Types rail -->
        <aside class="rounded-xl border border-ink-100 bg-white">
            <div class="border-b border-ink-100 px-3 py-2 text-xs font-semibold uppercase tracking-wider text-ink-500">Types</div>
            <ul class="divide-y divide-ink-100">
                <?php foreach ($types as $t):
                    $is_active = $t['slug'] === $active_type_slug;
                    $cls = $is_active ? 'bg-brand-50 border-l-2 border-brand-600' : 'hover:bg-ink-50';
                ?>
                    <li>
                        <a href="?type=<?= e($t['slug']) ?>" class="block px-3 py-2 text-sm <?= $cls ?>">
                            <div class="flex items-baseline justify-between gap-2">
                                <span class="font-medium text-ink-800"><?= e($t['name']) ?></span>
                                <?php if ((int)$t['is_routable'] === 1): ?>
                                    <span class="text-[10px] font-semibold uppercase tracking-wider text-brand-700">routable</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($t['route_pattern'])): ?>
                                <div class="mt-0.5 text-xs text-ink-500"><code><?= e($t['route_pattern']) ?></code></div>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </aside>

        <!-- Entries list -->
        <section class="rounded-xl border border-ink-100 bg-white">
            <div class="flex items-center justify-between border-b border-ink-100 px-3 py-2">
                <div class="text-xs font-semibold uppercase tracking-wider text-ink-500"><?= e($active_type['name'] ?? '') ?> entries</div>
                <a href="#new-entry" class="text-xs font-medium text-brand-700 hover:text-brand-800">+ New</a>
            </div>
            <?php if ($entries === []): ?>
                <div class="px-3 py-6 text-sm text-ink-500">No entries yet.</div>
            <?php else: ?>
                <ul class="divide-y divide-ink-100">
                    <?php foreach ($entries as $en):
                        $is_active = (int)$en['id'] === $active_entry_id;
                        $cls = $is_active ? 'bg-brand-50 border-l-2 border-brand-600' : 'hover:bg-ink-50';
                    ?>
                        <li>
                            <a href="?type=<?= e($active_type_slug) ?>&entry=<?= (int)$en['id'] ?>" class="block px-3 py-2.5 text-sm <?= $cls ?>">
                                <div class="flex items-baseline justify-between gap-2">
                                    <span class="font-medium text-ink-800"><?= e($en['title']) ?></span>
                                    <?php if ($en['status'] !== 'published'): ?>
                                        <span class="rounded bg-ink-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-ink-600"><?= e($en['status']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($en['slug'])): ?>
                                    <div class="mt-0.5 text-xs text-ink-500"><code><?= e($en['slug']) ?></code></div>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <!-- Editor -->
        <section class="rounded-xl border border-ink-100 bg-white">
            <?php if ($active_entry === null): ?>
                <div class="px-4 py-6 text-sm text-ink-500">Pick an entry on the left or create a new one below.</div>
            <?php else: ?>
                <form method="post" action="/api/content/entries.php" class="space-y-4 p-4">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= (int)$active_entry['id'] ?>">
                    <input type="hidden" name="type" value="<?= e($active_type_slug) ?>">

                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="text-lg font-semibold text-ink-900"><?= e($active_entry['title']) ?></h2>
                        <a href="#" onclick="event.preventDefault();if(confirm('Delete this entry?')){document.getElementById('delete-entry').submit();}" class="text-xs text-rose-700 hover:text-rose-800">Delete</a>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wider text-ink-500" for="entry_title">Title</label>
                            <input id="entry_title" name="title" type="text" value="<?= e($active_entry['title']) ?>" required
                                   class="mt-1 w-full rounded-md border border-ink-200 bg-white px-3 py-2 text-sm">
                        </div>
                        <?php if ((int)$active_type['is_routable'] === 1): ?>
                            <div>
                                <label class="text-xs font-medium uppercase tracking-wider text-ink-500" for="entry_slug">Slug</label>
                                <input id="entry_slug" name="slug" type="text" value="<?= e($active_entry['slug'] ?? '') ?>" required pattern="[a-z0-9](?:[a-z0-9-]*[a-z0-9])?"
                                       class="mt-1 w-full rounded-md border border-ink-200 bg-white px-3 py-2 font-mono text-xs">
                                <p class="mt-1 text-xs text-ink-500">URL: <code><?= e(str_replace('{slug}', $active_entry['slug'] ?? '…', (string)$active_type['route_pattern'])) ?></code></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div>
                            <label class="text-xs font-medium uppercase tracking-wider text-ink-500" for="entry_status">Status</label>
                            <select id="entry_status" name="status" class="mt-1 w-full rounded-md border border-ink-200 bg-white px-3 py-2 text-sm">
                                <?php foreach (['published','draft','archived'] as $s): ?>
                                    <option value="<?= $s ?>" <?= $active_entry['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ((int)$active_type['is_routable'] === 1): ?>
                            <div class="sm:col-span-2">
                                <label class="text-xs font-medium uppercase tracking-wider text-ink-500" for="entry_robots">Robots meta</label>
                                <input id="entry_robots" name="robots" type="text" value="<?= e($active_entry['robots'] ?? '') ?>"
                                       placeholder="<?= $active_type_slug === 'ad_landing_pages' ? 'noindex,nofollow (default for Ad LPs)' : 'leave blank for default index,follow' ?>"
                                       class="mt-1 w-full rounded-md border border-ink-200 bg-white px-3 py-2 font-mono text-xs">
                            </div>
                        <?php endif; ?>
                    </div>

                    <h3 class="text-sm font-semibold uppercase tracking-wider text-ink-500">Fields</h3>
                    <?php if ($type_fields === []): ?>
                        <p class="text-xs text-ink-500">No fields defined in <code>schema_json</code> for this type.</p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($type_fields as $f):
                                $name  = 'data[' . $f['key'] . ']';
                                $value = (string)($entry_data[$f['key']] ?? '');
                                $is_long = $f['type'] === 'textarea' || mb_strlen($value) > 80;
                            ?>
                                <div>
                                    <label class="text-xs font-medium text-ink-700" for="entry_<?= e($f['key']) ?>">
                                        <?= e($f['label']) ?>
                                        <?php if ($f['required']): ?><span class="text-rose-600">*</span><?php endif; ?>
                                        <code class="ml-1 text-[10px] text-ink-400"><?= e($f['key']) ?></code>
                                    </label>
                                    <?php if ($is_long): ?>
                                        <textarea id="entry_<?= e($f['key']) ?>" name="<?= e($name) ?>" rows="3"
                                                  class="mt-1 w-full rounded-md border border-ink-200 bg-white px-3 py-2 font-mono text-xs"><?= e($value) ?></textarea>
                                    <?php else: ?>
                                        <input id="entry_<?= e($f['key']) ?>" name="<?= e($name) ?>" type="<?= $f['type'] === 'number' ? 'number' : 'text' ?>" value="<?= e($value) ?>"
                                               class="mt-1 w-full rounded-md border border-ink-200 bg-white px-3 py-2 text-sm">
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <details class="rounded-lg border border-ink-100 bg-ink-50/40 p-3">
                        <summary class="cursor-pointer text-xs font-semibold uppercase tracking-wider text-ink-500">SEO</summary>
                        <div class="mt-3 space-y-2">
                            <input name="seo_title" type="text" placeholder="SEO title (≤60 chars)" value="<?= e($active_entry['seo_title'] ?? '') ?>"
                                   class="w-full rounded-md border border-ink-200 bg-white px-3 py-2 text-sm">
                            <textarea name="seo_description" rows="2" placeholder="Meta description (≤160 chars)"
                                      class="w-full rounded-md border border-ink-200 bg-white px-3 py-2 text-sm"><?= e($active_entry['seo_description'] ?? '') ?></textarea>
                            <input name="seo_og_image" type="text" placeholder="OG image URL" value="<?= e($active_entry['seo_og_image'] ?? '') ?>"
                                   class="w-full rounded-md border border-ink-200 bg-white px-3 py-2 text-sm">
                        </div>
                    </details>

                    <div class="text-right">
                        <button type="submit" class="rounded-md border border-brand-600 bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Save entry</button>
                    </div>
                </form>

                <form id="delete-entry" method="post" action="/api/content/entries.php" class="hidden">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$active_entry['id'] ?>">
                    <input type="hidden" name="type" value="<?= e($active_type_slug) ?>">
                </form>
            <?php endif; ?>

            <!-- New entry form -->
            <?php if ($active_type !== null): ?>
                <details id="new-entry" class="border-t border-ink-100 p-4" <?= $active_entry === null ? 'open' : '' ?>>
                    <summary class="cursor-pointer text-sm font-medium text-brand-700">+ New <?= e($active_type['name']) ?> entry</summary>
                    <form method="post" action="/api/content/entries.php" class="mt-3 space-y-3">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="create">
                        <input type="hidden" name="type" value="<?= e($active_type_slug) ?>">
                        <input type="hidden" name="type_id" value="<?= (int)$active_type['id'] ?>">

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="text-xs font-medium uppercase tracking-wider text-ink-500" for="ne_title">Title</label>
                                <input id="ne_title" name="title" type="text" required
                                       class="mt-1 w-full rounded-md border border-ink-200 bg-white px-3 py-2 text-sm">
                            </div>
                            <?php if ((int)$active_type['is_routable'] === 1): ?>
                                <div>
                                    <label class="text-xs font-medium uppercase tracking-wider text-ink-500" for="ne_slug">Slug</label>
                                    <input id="ne_slug" name="slug" type="text" required pattern="[a-z0-9](?:[a-z0-9-]*[a-z0-9])?"
                                           class="mt-1 w-full rounded-md border border-ink-200 bg-white px-3 py-2 font-mono text-xs">
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="text-right">
                            <button type="submit" class="rounded-md border border-brand-600 bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Create</button>
                        </div>
                        <p class="text-xs text-ink-500">After creation you'll be redirected to edit the rest of the fields.</p>
                    </form>
                </details>
            <?php endif; ?>
        </section>
    </div>
<?php
admin_foot();
