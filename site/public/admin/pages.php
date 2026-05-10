<?php
// site/public/admin/pages.php — pages CRUD (list, create, edit, delete,
// status toggle).
//
// All operations server-rendered with form posts; no JS required.
//   GET  /admin/pages.php                  -> list view
//   GET  /admin/pages.php?action=new       -> blank create form
//   GET  /admin/pages.php?action=edit&id=N -> populated edit form
//   POST /admin/pages.php (action=save)    -> create or update (id present = update)
//   POST /admin/pages.php (action=delete)  -> delete by id
//   POST /admin/pages.php (action=status)  -> toggle status
//
// All POST paths CSRF-checked. Validation runs server-side; error
// messages render inline with the form. On success, redirects to the
// list (or back to the edit form for save) with a flash message.

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';
require __DIR__ . '/_layout.php';
auth_require_login();

$pdo = db();

// ----- Constants ------------------------------------------------------------

$VALID_STATUSES = ['draft', 'published', 'archived'];
$VALID_TYPES    = ['file_based', 'data_driven'];

// ----- Helpers --------------------------------------------------------------

function pages_validate_slug(string $slug): ?string
{
    if ($slug === '')                       return 'Slug is required.';
    if (strlen($slug) > 200)                return 'Slug too long (max 200 chars).';
    if (!preg_match('#^[a-z0-9]+(?:[-/][a-z0-9]+)*$#', $slug))
                                            return 'Slug must be lowercase, alphanumeric, with single dashes or slashes between segments.';
    return null;
}

function pages_validate_file_path(string $file): ?string
{
    if ($file === '')                                       return 'File path is required for file-based pages.';
    if (!preg_match('#^[a-z0-9_\-/]+\.php$#i', $file))      return 'File path must be a relative .php file under site/pages/.';
    $pages_dir = realpath(GUA_SITE_PATH . '/pages');
    $target    = realpath(GUA_SITE_PATH . '/pages/' . $file);
    if ($pages_dir === false || $target === false)          return 'File does not exist at site/pages/' . $file;
    if (!str_starts_with($target, $pages_dir . DIRECTORY_SEPARATOR))
                                                            return 'File path resolves outside site/pages/.';
    return null;
}

function pages_validate_sections_json(string $json): ?string
{
    $json = trim($json);
    if ($json === '') return 'Sections list is required for data-driven pages.';
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) return 'Sections must be a valid JSON array.';
    $sections_dir = realpath(GUA_SITE_PATH . '/sections');
    foreach ($decoded as $entry) {
        $name = is_string($entry) ? $entry : (is_array($entry) ? ($entry['section'] ?? null) : null);
        if (!is_string($name) || !preg_match('/^[a-z0-9_]+$/', $name)) {
            return 'Each section must be a string name like "hero" or {"section":"hero"}.';
        }
        $f = realpath(GUA_SITE_PATH . '/sections/' . $name . '.php');
        if ($sections_dir === false || $f === false || !str_starts_with($f, $sections_dir . DIRECTORY_SEPARATOR)) {
            return 'Section "' . $name . '" does not exist at site/sections/.';
        }
    }
    return null;
}

function pages_post_input(): array
{
    return [
        'id'              => (int) ($_POST['id'] ?? 0),
        'slug'            => trim((string)($_POST['slug'] ?? '')),
        'title'           => trim((string)($_POST['title'] ?? '')),
        'status'          => (string)($_POST['status'] ?? 'draft'),
        'type'            => (string)($_POST['type'] ?? 'file_based'),
        'file_path'       => trim((string)($_POST['file_path'] ?? '')),
        'sections_json'   => (string)($_POST['sections_json'] ?? ''),
        'layout'          => trim((string)($_POST['layout'] ?? 'default')),
        'seo_title'       => trim((string)($_POST['seo_title'] ?? '')),
        'seo_description' => trim((string)($_POST['seo_description'] ?? '')),
        'seo_og_image'    => trim((string)($_POST['seo_og_image'] ?? '')),
    ];
}

function pages_redirect(string $path): void
{
    header('Location: ' . $path, true, 303);
    exit;
}

// ----- Action dispatch ------------------------------------------------------

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$flash  = $_GET['flash'] ?? '';
$errors = [];
$form   = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_check((string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        $errors['_global'] = 'Session expired. Reload the page and try again.';
        $action = 'list';
    } else {
        $post_action = (string)($_POST['action'] ?? '');

        if ($post_action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM pages WHERE id = :id');
            $stmt->execute([':id' => $id]);
            pages_redirect('/admin/pages.php?flash=deleted');
        }

        if ($post_action === 'status') {
            $id = (int)($_POST['id'] ?? 0);
            $new_status = (string)($_POST['status'] ?? '');
            if (!in_array($new_status, $VALID_STATUSES, true)) {
                pages_redirect('/admin/pages.php?flash=error');
            }
            $stmt = $pdo->prepare('UPDATE pages SET status = :s, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
            $stmt->execute([':s' => $new_status, ':id' => $id]);
            pages_redirect('/admin/pages.php?flash=updated');
        }

        if ($post_action === 'save') {
            $form = pages_post_input();

            // Validate
            $err = pages_validate_slug($form['slug']);
            if ($err) $errors['slug'] = $err;
            if ($form['title'] === '' || mb_strlen($form['title']) > 200) {
                $errors['title'] = 'Title is required (max 200 chars).';
            }
            if (!in_array($form['status'], $VALID_STATUSES, true)) {
                $errors['status'] = 'Invalid status.';
            }
            if (!in_array($form['type'], $VALID_TYPES, true)) {
                $errors['type'] = 'Invalid type.';
            }
            if ($form['type'] === 'file_based') {
                $err = pages_validate_file_path($form['file_path']);
                if ($err) $errors['file_path'] = $err;
            } else {
                $err = pages_validate_sections_json($form['sections_json']);
                if ($err) $errors['sections_json'] = $err;
            }

            // Slug uniqueness (exclude self on update)
            if (!isset($errors['slug'])) {
                $stmt = $pdo->prepare('SELECT id FROM pages WHERE slug = :slug AND id != :id LIMIT 1');
                $stmt->execute([':slug' => $form['slug'], ':id' => $form['id']]);
                if ($stmt->fetchColumn()) {
                    $errors['slug'] = 'Another page already uses this slug.';
                }
            }

            if (empty($errors)) {
                $is_file_based = $form['type'] === 'file_based' ? 1 : 0;
                $params = [
                    ':slug'            => $form['slug'],
                    ':title'           => $form['title'],
                    ':status'          => $form['status'],
                    ':is_file_based'   => $is_file_based,
                    ':file_path'       => $is_file_based === 1 ? $form['file_path'] : null,
                    ':sections_json'   => $is_file_based === 0 ? $form['sections_json'] : null,
                    ':layout'          => $form['layout'] !== '' ? $form['layout'] : 'default',
                    ':seo_title'       => $form['seo_title']       !== '' ? $form['seo_title']       : null,
                    ':seo_description' => $form['seo_description'] !== '' ? $form['seo_description'] : null,
                    ':seo_og_image'    => $form['seo_og_image']    !== '' ? $form['seo_og_image']    : null,
                ];

                if ($form['id'] > 0) {
                    $params[':id'] = $form['id'];
                    $sql = 'UPDATE pages SET
                              slug = :slug, title = :title, status = :status,
                              is_file_based = :is_file_based, file_path = :file_path,
                              sections_json = :sections_json, layout = :layout,
                              seo_title = :seo_title, seo_description = :seo_description,
                              seo_og_image = :seo_og_image,
                              updated_at = CURRENT_TIMESTAMP
                            WHERE id = :id';
                    $pdo->prepare($sql)->execute($params);
                    pages_redirect('/admin/pages.php?flash=updated');
                } else {
                    $sql = 'INSERT INTO pages
                              (slug, title, status, is_file_based, file_path,
                               sections_json, layout, seo_title, seo_description, seo_og_image)
                            VALUES
                              (:slug, :title, :status, :is_file_based, :file_path,
                               :sections_json, :layout, :seo_title, :seo_description, :seo_og_image)';
                    $pdo->prepare($sql)->execute($params);
                    pages_redirect('/admin/pages.php?flash=created');
                }
            }
            // fall through to render edit form with errors
            $action = $form['id'] > 0 ? 'edit' : 'new';
        }
    }
}

// ----- Pre-render data fetch ------------------------------------------------

if ($action === 'edit') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($form === null) {
        $stmt = $pdo->prepare('SELECT * FROM pages WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            pages_redirect('/admin/pages.php?flash=missing');
        }
        $form = [
            'id'              => (int)$row['id'],
            'slug'            => (string)$row['slug'],
            'title'           => (string)$row['title'],
            'status'          => (string)$row['status'],
            'type'            => (int)$row['is_file_based'] === 1 ? 'file_based' : 'data_driven',
            'file_path'       => (string)($row['file_path'] ?? ''),
            'sections_json'   => (string)($row['sections_json'] ?? ''),
            'layout'          => (string)$row['layout'],
            'seo_title'       => (string)($row['seo_title'] ?? ''),
            'seo_description' => (string)($row['seo_description'] ?? ''),
            'seo_og_image'    => (string)($row['seo_og_image'] ?? ''),
        ];
    }
} elseif ($action === 'new' && $form === null) {
    $form = [
        'id' => 0, 'slug' => '', 'title' => '', 'status' => 'draft',
        'type' => 'file_based', 'file_path' => '', 'sections_json' => '[]',
        'layout' => 'default', 'seo_title' => '', 'seo_description' => '', 'seo_og_image' => '',
    ];
}

if ($action === 'list') {
    $rows = $pdo->query(
        'SELECT id, slug, title, status, is_file_based, file_path, updated_at
         FROM pages
         ORDER BY status = "draft" DESC, status = "published" DESC, slug'
    )->fetchAll(PDO::FETCH_ASSOC);
}

// ----- Render --------------------------------------------------------------

admin_head($action === 'list' ? 'Pages' : ($action === 'new' ? 'New page' : 'Edit page'), 'pages');
?>
    <?php if ($flash !== ''): ?>
        <?php
            $flash_text = match ($flash) {
                'created' => 'Page created.',
                'updated' => 'Saved.',
                'deleted' => 'Page deleted.',
                'missing' => 'That page no longer exists.',
                'error'   => 'Something went wrong.',
                default   => '',
            };
            $flash_kind = $flash === 'error' || $flash === 'missing' ? 'red' : 'emerald';
        ?>
        <?php if ($flash_text !== ''): ?>
            <div class="mb-4 rounded-lg border border-<?= $flash_kind ?>-200 bg-<?= $flash_kind ?>-50 px-4 py-2 text-sm text-<?= $flash_kind ?>-800">
                <?= e($flash_text) ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

<?php if ($action === 'list'): ?>

    <div class="flex items-baseline justify-between">
        <h1 class="text-2xl font-semibold tracking-tight text-ink-900">Pages</h1>
        <a href="/admin/pages.php?action=new"
           class="inline-flex items-center gap-1.5 rounded-full bg-brand-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-brand-700">
            + New page
        </a>
    </div>
    <p class="mt-2 text-ink-600">All pages on this site. File-based pages are PHP files in <code>site/pages/</code>; data-driven pages are rendered from the <code>sections_json</code> column.</p>

    <?php if (empty($rows)): ?>
        <div class="mt-8 rounded-2xl border border-dashed border-ink-200 bg-white/60 p-10 text-center text-sm text-ink-500">
            No pages yet. Click <a href="/admin/pages.php?action=new" class="text-brand-700 underline">New page</a> to add one.
        </div>
    <?php else: ?>
        <div class="mt-6 overflow-hidden rounded-2xl border border-ink-100 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="bg-ink-50/60 text-xs uppercase tracking-wider text-ink-500">
                    <tr>
                        <th class="px-4 py-3 font-medium">Slug</th>
                        <th class="px-4 py-3 font-medium">Title</th>
                        <th class="px-4 py-3 font-medium">Type</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Updated</th>
                        <th class="px-4 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    <?php foreach ($rows as $r):
                        $type = (int)$r['is_file_based'] === 1 ? 'file' : 'data';
                        $status_color = match ($r['status']) {
                            'published' => 'bg-emerald-50 text-emerald-700',
                            'draft'     => 'bg-amber-50 text-amber-700',
                            'archived'  => 'bg-ink-100 text-ink-500',
                            default     => 'bg-ink-100 text-ink-400',
                        };
                    ?>
                        <tr class="align-top hover:bg-ink-50/40">
                            <td class="px-4 py-3 font-medium text-ink-900">
                                <a href="/<?= e($r['slug']) ?>" target="_blank" class="hover:text-brand-700"><?= e($r['slug']) ?></a>
                            </td>
                            <td class="px-4 py-3 text-ink-700"><?= e($r['title']) ?></td>
                            <td class="px-4 py-3 text-xs">
                                <span class="rounded bg-brand-50 px-1.5 py-0.5 font-medium uppercase tracking-wider text-brand-700"><?= $type ?></span>
                                <?php if ($type === 'file'): ?>
                                    <code class="ml-1 text-ink-400"><?= e((string)$r['file_path']) ?></code>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium <?= $status_color ?>"><?= e($r['status']) ?></span>
                            </td>
                            <td class="px-4 py-3 text-xs text-ink-500"><?= e((string)$r['updated_at']) ?></td>
                            <td class="px-4 py-3 text-right text-sm">
                                <a href="/admin/pages.php?action=edit&amp;id=<?= (int)$r['id'] ?>" class="text-brand-700 hover:underline">Edit</a>
                                <span class="mx-1 text-ink-200">·</span>
                                <form method="post" action="/admin/pages.php" class="inline" onsubmit="return confirm('Delete page &quot;<?= e($r['slug']) ?>&quot;? This cannot be undone.');">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                    <button type="submit" class="text-red-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

<?php else: /* new or edit form */ ?>

    <div class="flex items-baseline justify-between">
        <h1 class="text-2xl font-semibold tracking-tight text-ink-900">
            <?= $action === 'new' ? 'New page' : 'Edit page' ?>
            <?php if ($action === 'edit'): ?>
                <code class="ml-2 text-base font-normal text-ink-500"><?= e($form['slug']) ?></code>
            <?php endif; ?>
        </h1>
        <a href="/admin/pages.php" class="text-sm text-ink-500 hover:text-ink-800">&larr; Back to list</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div role="alert" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <p class="font-medium">Please fix the errors below.</p>
            <?php if (isset($errors['_global'])): ?>
                <p class="mt-1"><?= e($errors['_global']) ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="/admin/pages.php" class="mt-6 grid gap-5 rounded-2xl border border-ink-100 bg-white p-6 sm:p-8">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="slug" class="mb-1.5 block text-sm font-medium text-ink-800">Slug <span class="text-brand-600">*</span></label>
                <input id="slug" name="slug" type="text" required maxlength="200"
                       value="<?= e($form['slug']) ?>"
                       placeholder="services/seo-bangalore"
                       class="w-full rounded-lg border <?= isset($errors['slug']) ? 'border-red-400' : 'border-ink-200' ?> bg-white px-3 py-2.5 text-sm text-ink-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">
                <p class="mt-1 text-xs <?= isset($errors['slug']) ? 'text-red-600' : 'text-ink-400' ?>">
                    <?= isset($errors['slug']) ? e($errors['slug']) : 'Lowercase, hyphens or slashes only. Used as the URL path.' ?>
                </p>
            </div>
            <div>
                <label for="title" class="mb-1.5 block text-sm font-medium text-ink-800">Title <span class="text-brand-600">*</span></label>
                <input id="title" name="title" type="text" required maxlength="200"
                       value="<?= e($form['title']) ?>"
                       class="w-full rounded-lg border <?= isset($errors['title']) ? 'border-red-400' : 'border-ink-200' ?> bg-white px-3 py-2.5 text-sm text-ink-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">
                <?php if (isset($errors['title'])): ?>
                    <p class="mt-1 text-xs text-red-600"><?= e($errors['title']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label for="status" class="mb-1.5 block text-sm font-medium text-ink-800">Status</label>
                <select id="status" name="status" class="w-full rounded-lg border border-ink-200 bg-white px-3 py-2.5 text-sm">
                    <?php foreach ($VALID_STATUSES as $s): ?>
                        <option value="<?= e($s) ?>" <?= $form['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="type" class="mb-1.5 block text-sm font-medium text-ink-800">Type</label>
                <select id="type" name="type" class="w-full rounded-lg border border-ink-200 bg-white px-3 py-2.5 text-sm">
                    <option value="file_based" <?= $form['type'] === 'file_based' ? 'selected' : '' ?>>File-based</option>
                    <option value="data_driven" <?= $form['type'] === 'data_driven' ? 'selected' : '' ?>>Data-driven</option>
                </select>
            </div>
            <div>
                <label for="layout" class="mb-1.5 block text-sm font-medium text-ink-800">Layout</label>
                <input id="layout" name="layout" type="text" value="<?= e($form['layout']) ?>"
                       class="w-full rounded-lg border border-ink-200 bg-white px-3 py-2.5 text-sm">
                <p class="mt-1 text-xs text-ink-400">Reserved; only "default" is used today.</p>
            </div>
        </div>

        <div data-when-type="file_based" <?= $form['type'] !== 'file_based' ? 'hidden' : '' ?>>
            <label for="file_path" class="mb-1.5 block text-sm font-medium text-ink-800">File path (under <code>site/pages/</code>)</label>
            <input id="file_path" name="file_path" type="text" value="<?= e($form['file_path']) ?>"
                   placeholder="home.php"
                   class="w-full rounded-lg border <?= isset($errors['file_path']) ? 'border-red-400' : 'border-ink-200' ?> bg-white px-3 py-2.5 text-sm text-ink-900 font-mono focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200">
            <p class="mt-1 text-xs <?= isset($errors['file_path']) ? 'text-red-600' : 'text-ink-400' ?>">
                <?= isset($errors['file_path']) ? e($errors['file_path']) : 'e.g. home.php, contact.php. Must already exist in site/pages/.' ?>
            </p>
        </div>

        <div data-when-type="data_driven" <?= $form['type'] !== 'data_driven' ? 'hidden' : '' ?>>
            <label for="sections_json" class="mb-1.5 block text-sm font-medium text-ink-800">Sections (JSON array)</label>
            <textarea id="sections_json" name="sections_json" rows="4"
                      placeholder='["navbar","hero","features","footer"]'
                      class="w-full resize-y rounded-lg border <?= isset($errors['sections_json']) ? 'border-red-400' : 'border-ink-200' ?> bg-white px-3 py-2.5 text-sm font-mono text-ink-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200"><?= e($form['sections_json']) ?></textarea>
            <p class="mt-1 text-xs <?= isset($errors['sections_json']) ? 'text-red-600' : 'text-ink-400' ?>">
                <?= isset($errors['sections_json']) ? e($errors['sections_json']) : 'Each entry must be a section name (matches a file in site/sections/). Override per-page content with content keys prefixed page.<slug>.<section>.<field>.' ?>
            </p>
        </div>

        <fieldset class="rounded-lg border border-ink-100 p-4">
            <legend class="px-2 text-sm font-medium text-ink-600">SEO (optional, overrides global)</legend>
            <div class="grid gap-4">
                <div>
                    <label for="seo_title" class="mb-1.5 block text-xs font-medium text-ink-700">Title tag</label>
                    <input id="seo_title" name="seo_title" type="text" maxlength="200" value="<?= e($form['seo_title']) ?>"
                           class="w-full rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="seo_description" class="mb-1.5 block text-xs font-medium text-ink-700">Meta description</label>
                    <textarea id="seo_description" name="seo_description" rows="2" maxlength="500"
                              class="w-full resize-y rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm"><?= e($form['seo_description']) ?></textarea>
                </div>
                <div>
                    <label for="seo_og_image" class="mb-1.5 block text-xs font-medium text-ink-700">OG image URL</label>
                    <input id="seo_og_image" name="seo_og_image" type="text" value="<?= e($form['seo_og_image']) ?>"
                           placeholder="/assets/placeholders/og.jpg"
                           class="w-full rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm font-mono">
                </div>
            </div>
        </fieldset>

        <div class="flex items-center justify-between gap-3">
            <a href="/admin/pages.php" class="text-sm text-ink-500 hover:text-ink-800">Cancel</a>
            <button type="submit"
                    class="inline-flex items-center justify-center gap-1.5 rounded-full bg-brand-600 px-6 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-brand-700">
                <?= $action === 'new' ? 'Create page' : 'Save changes' ?>
            </button>
        </div>
    </form>

    <script>
      // Show only the relevant fields when the type select changes.
      (function () {
        const sel = document.getElementById('type');
        if (!sel) return;
        function sync() {
          document.querySelectorAll('[data-when-type]').forEach(function (el) {
            el.hidden = el.getAttribute('data-when-type') !== sel.value;
          });
        }
        sel.addEventListener('change', sync);
      })();
    </script>

<?php endif; ?>
<?php
admin_foot();
