<?php
// site/public/admin/forms.php — Forms hub + per-form editor (v2 Stage 6).
//
// Without ?form param: list every form with submission/webhook counts.
// With ?form=<id>:    tabbed editor (Fields / Settings / Webhooks /
//                     Submissions / Embed). Replaces the v1 single-form
//                     waitlist inbox.

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';
require __DIR__ . '/_layout.php';
require_once __DIR__ . '/../../../core/lib/forms.php';

auth_require_login();

$pdo = db();
$forms = forms_all();

$active_id = (int)($_GET['form'] ?? 0);
$active = $active_id > 0 ? form_by_id($active_id) : null;

$saved   = isset($_GET['saved']);
$created = isset($_GET['created']);
$error   = (string)($_GET['error'] ?? '');

if ($active === null) {
    // ============================================================ INDEX VIEW
    admin_head('Forms', 'forms');
    ?>
        <div class="flex flex-wrap items-baseline justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-ink-900">Forms</h1>
                <p class="mt-2 text-ink-600">Multi-form CRUD. Each form has its own fields and outbound webhooks.</p>
            </div>
            <a href="#new-form" class="rounded-md border border-brand-600 bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">+ New form</a>
        </div>

        <?php if ($saved): ?>
            <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-900">Saved.</div>
        <?php endif; ?>
        <?php if ($created): ?>
            <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-900">Form created.</div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-900"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="mt-6 space-y-3">
            <?php foreach ($forms as $f):
                $count_subs = form_submission_count((int)$f['id']);
                $webhooks   = form_webhooks((int)$f['id']);
            ?>
                <a href="?form=<?= (int)$f['id'] ?>" class="block rounded-xl border border-ink-100 bg-white p-4 transition hover:border-brand-200 hover:shadow-sm">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <div>
                            <h2 class="text-base font-semibold text-ink-900"><?= e($f['name']) ?></h2>
                            <div class="mt-0.5 text-xs text-ink-500">
                                <code><?= e($f['slug']) ?></code>
                                <?php if ((int)$f['is_builtin'] === 1): ?> · <span class="rounded bg-ink-100 px-1.5 py-0.5 text-[10px] uppercase tracking-wider text-ink-600">builtin</span><?php endif; ?>
                                <?php if ($f['status'] !== 'active'): ?> · <span class="rounded bg-ink-100 px-1.5 py-0.5 text-[10px] uppercase tracking-wider text-ink-500"><?= e($f['status']) ?></span><?php endif; ?>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-ink-500">
                            <span><?= $count_subs ?> submission<?= $count_subs === 1 ? '' : 's' ?></span>
                            <?php if ($webhooks !== []): ?>
                                <span>·</span>
                                <span><?= count($webhooks) ?> webhook<?= count($webhooks) === 1 ? '' : 's' ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($f['description'])): ?>
                        <p class="mt-2 text-sm text-ink-600"><?= e($f['description']) ?></p>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <details id="new-form" class="mt-6 rounded-xl border border-ink-100 bg-white p-4">
            <summary class="cursor-pointer text-sm font-medium text-brand-700">+ Create a new form</summary>
            <form method="post" action="/api/forms.php" class="mt-3 grid gap-3 sm:grid-cols-2">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="create_form">
                <div>
                    <label class="text-xs font-medium uppercase tracking-wider text-ink-500" for="nf_slug">Slug</label>
                    <input id="nf_slug" name="slug" type="text" required pattern="[a-z0-9](?:[a-z0-9-]*[a-z0-9])?"
                           class="mt-1 w-full rounded-md border border-ink-200 bg-white px-3 py-2 font-mono text-xs">
                </div>
                <div>
                    <label class="text-xs font-medium uppercase tracking-wider text-ink-500" for="nf_name">Name</label>
                    <input id="nf_name" name="name" type="text" required
                           class="mt-1 w-full rounded-md border border-ink-200 bg-white px-3 py-2 text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="text-xs font-medium uppercase tracking-wider text-ink-500" for="nf_desc">Description (optional)</label>
                    <textarea id="nf_desc" name="description" rows="2" class="mt-1 w-full rounded-md border border-ink-200 bg-white px-3 py-2 text-sm"></textarea>
                </div>
                <div class="sm:col-span-2 text-right">
                    <button type="submit" class="rounded-md border border-brand-600 bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Create</button>
                </div>
            </form>
        </details>
    <?php
    admin_foot();
    exit;
}

// ============================================================ EDITOR VIEW
$tab = (string)($_GET['tab'] ?? 'fields');
$fields_list = form_fields((int)$active['id']);
$webhooks    = form_webhooks((int)$active['id']);
$settings    = form_settings($active);
$count_subs  = form_submission_count((int)$active['id']);

admin_head('Form: ' . $active['name'], 'forms');
?>
    <div class="flex flex-wrap items-baseline justify-between gap-3">
        <div>
            <a href="/admin/forms.php" class="text-xs text-ink-500 hover:text-ink-700">&larr; All forms</a>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-ink-900"><?= e($active['name']) ?></h1>
            <div class="mt-1 text-xs text-ink-500">
                <code><?= e($active['slug']) ?></code> · <?= e($active['status']) ?>
                <?php if ((int)$active['is_builtin'] === 1): ?> · builtin<?php endif; ?>
            </div>
        </div>
        <?php if ((int)$active['is_builtin'] !== 1): ?>
            <form method="post" action="/api/forms.php" onsubmit="return confirm('Delete this form, its fields, webhooks, and all submissions?');">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete_form">
                <input type="hidden" name="id" value="<?= (int)$active['id'] ?>">
                <button type="submit" class="text-xs text-rose-700 hover:text-rose-800">Delete form</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($saved): ?>
        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-900">Saved.</div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-900"><?= e($error) ?></div>
    <?php endif; ?>

    <nav class="mt-6 flex flex-wrap gap-1 border-b border-ink-100">
        <?php foreach (['fields','settings','webhooks','submissions','embed'] as $t):
            $is_active = $t === $tab;
            $cls = 'whitespace-nowrap border-b-2 px-3 py-2 text-sm transition '
                . ($is_active ? 'border-brand-600 font-medium text-ink-900' : 'border-transparent text-ink-500 hover:text-ink-800');
        ?>
            <a href="?form=<?= (int)$active['id'] ?>&tab=<?= $t ?>" class="<?= $cls ?>"><?= ucfirst($t) ?><?php if ($t === 'submissions') echo ' (' . $count_subs . ')'; ?></a>
        <?php endforeach; ?>
    </nav>

    <div class="mt-6">
        <?php if ($tab === 'fields'): ?>
            <p class="text-sm text-ink-500">Each row is one input on the public form. Lower position numbers render first.</p>
            <div class="mt-4 space-y-3">
                <?php foreach ($fields_list as $f): ?>
                    <form method="post" action="/api/forms.php" class="rounded-lg border border-ink-100 bg-white p-3 space-y-2">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="save_field">
                        <input type="hidden" name="form_id" value="<?= (int)$active['id'] ?>">
                        <input type="hidden" name="field_id" value="<?= (int)$f['id'] ?>">

                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <code class="text-sm font-medium text-ink-900"><?= e($f['name']) ?></code>
                            <span class="text-xs text-ink-500">position <?= (int)$f['position'] ?></span>
                        </div>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-4">
                            <input name="label" type="text" value="<?= e($f['label']) ?>" placeholder="Label" required class="rounded-md border border-ink-200 bg-white px-3 py-2 text-sm">
                            <select name="type" class="rounded-md border border-ink-200 bg-white px-3 py-2 text-sm">
                                <?php foreach (['text','email','phone','textarea','select','radio','checkbox','file','hidden','url','number','date'] as $t): ?>
                                    <option value="<?= $t ?>" <?= $f['type'] === $t ? 'selected' : '' ?>><?= $t ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input name="placeholder" type="text" value="<?= e((string)($f['placeholder'] ?? '')) ?>" placeholder="Placeholder" class="rounded-md border border-ink-200 bg-white px-3 py-2 text-sm">
                            <input name="position" type="number" value="<?= (int)$f['position'] ?>" class="rounded-md border border-ink-200 bg-white px-3 py-2 font-mono text-xs">
                        </div>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <input name="default_value" type="text" value="<?= e((string)($f['default_value'] ?? '')) ?>" placeholder="Default value" class="rounded-md border border-ink-200 bg-white px-3 py-2 text-sm">
                            <input name="help_text" type="text" value="<?= e((string)($f['help_text'] ?? '')) ?>" placeholder="Help text" class="rounded-md border border-ink-200 bg-white px-3 py-2 text-sm">
                        </div>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                            <label class="inline-flex items-center gap-2 text-sm text-ink-700">
                                <input type="checkbox" name="required" value="1" <?= (int)$f['required'] === 1 ? 'checked' : '' ?>>
                                Required
                            </label>
                            <input name="options_json" type="text" value="<?= e((string)($f['options_json'] ?? '')) ?>" placeholder='Options JSON, e.g. ["a","b"]' class="rounded-md border border-ink-200 bg-white px-3 py-2 font-mono text-xs sm:col-span-2">
                        </div>
                        <input name="validation_json" type="text" value="<?= e((string)($f['validation_json'] ?? '')) ?>" placeholder='Validation JSON, e.g. {"max_length":100}' class="w-full rounded-md border border-ink-200 bg-white px-3 py-2 font-mono text-xs">

                        <div class="flex items-center justify-between gap-2">
                            <button type="submit" class="rounded-md border border-brand-600 bg-brand-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-700">Save field</button>
                            <button type="submit" name="action" value="delete_field" onclick="return confirm('Delete this field?');" class="text-xs text-rose-700 hover:text-rose-800">Delete field</button>
                        </div>
                    </form>
                <?php endforeach; ?>
            </div>

            <details class="mt-4 rounded-xl border border-ink-100 bg-white p-4">
                <summary class="cursor-pointer text-sm font-medium text-brand-700">+ Add field</summary>
                <form method="post" action="/api/forms.php" class="mt-3 grid gap-2 sm:grid-cols-3">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="add_field">
                    <input type="hidden" name="form_id" value="<?= (int)$active['id'] ?>">
                    <input name="name" type="text" required pattern="[a-z_][a-z0-9_]*" placeholder="name (snake_case)" class="rounded-md border border-ink-200 bg-white px-3 py-2 font-mono text-xs">
                    <input name="label" type="text" required placeholder="Label" class="rounded-md border border-ink-200 bg-white px-3 py-2 text-sm">
                    <select name="type" class="rounded-md border border-ink-200 bg-white px-3 py-2 text-sm">
                        <?php foreach (['text','email','phone','textarea','select','radio','checkbox','file','hidden','url','number','date'] as $t): ?>
                            <option value="<?= $t ?>"><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="sm:col-span-3 text-right">
                        <button type="submit" class="rounded-md border border-brand-600 bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Add field</button>
                    </div>
                </form>
            </details>

        <?php elseif ($tab === 'settings'): ?>
            <form method="post" action="/api/forms.php" class="space-y-3 max-w-2xl">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_settings">
                <input type="hidden" name="id" value="<?= (int)$active['id'] ?>">

                <div>
                    <label class="text-xs font-medium uppercase tracking-wider text-ink-500" for="fs_name">Name</label>
                    <input id="fs_name" name="name" type="text" value="<?= e($active['name']) ?>" required class="mt-1 w-full rounded-md border border-ink-200 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs font-medium uppercase tracking-wider text-ink-500" for="fs_desc">Description</label>
                    <textarea id="fs_desc" name="description" rows="2" class="mt-1 w-full rounded-md border border-ink-200 bg-white px-3 py-2 text-sm"><?= e((string)($active['description'] ?? '')) ?></textarea>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="text-xs font-medium uppercase tracking-wider text-ink-500" for="fs_status">Status</label>
                        <select id="fs_status" name="status" class="mt-1 w-full rounded-md border border-ink-200 bg-white px-3 py-2 text-sm">
                            <?php foreach (['active','draft','archived'] as $s): ?>
                                <option value="<?= $s ?>" <?= $active['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-medium uppercase tracking-wider text-ink-500" for="fs_honeypot">Honeypot field name</label>
                        <input id="fs_honeypot" name="honeypot" type="text" value="<?= e((string)($settings['honeypot'] ?? 'website')) ?>" class="mt-1 w-full rounded-md border border-ink-200 bg-white px-3 py-2 font-mono text-xs">
                    </div>
                </div>
                <div>
                    <label class="text-xs font-medium uppercase tracking-wider text-ink-500" for="fs_succ_h">Success heading</label>
                    <input id="fs_succ_h" name="success_heading" type="text" value="<?= e((string)($settings['success_heading'] ?? '')) ?>" class="mt-1 w-full rounded-md border border-ink-200 bg-white px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs font-medium uppercase tracking-wider text-ink-500" for="fs_succ_b">Success body</label>
                    <textarea id="fs_succ_b" name="success_body" rows="2" class="mt-1 w-full rounded-md border border-ink-200 bg-white px-3 py-2 text-sm"><?= e((string)($settings['success_body'] ?? '')) ?></textarea>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="text-xs font-medium uppercase tracking-wider text-ink-500" for="fs_redir">Success redirect URL</label>
                        <input id="fs_redir" name="redirect_url" type="text" value="<?= e((string)($settings['redirect_url'] ?? '')) ?>" placeholder="(optional)" class="mt-1 w-full rounded-md border border-ink-200 bg-white px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="text-xs font-medium uppercase tracking-wider text-ink-500" for="fs_notif">Notification email</label>
                        <input id="fs_notif" name="notification_email" type="email" value="<?= e((string)($settings['notification_email'] ?? '')) ?>" placeholder="(optional)" class="mt-1 w-full rounded-md border border-ink-200 bg-white px-3 py-2 text-sm">
                    </div>
                </div>
                <div class="text-right">
                    <button type="submit" class="rounded-md border border-brand-600 bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Save settings</button>
                </div>
            </form>

        <?php elseif ($tab === 'webhooks'): ?>
            <p class="text-sm text-ink-500">Each enabled webhook is fired on every submission. Payload templates support <code>{{field_name}}</code> and <code>{{meta.submitted_at}}</code> placeholders.</p>
            <div class="mt-4 space-y-3">
                <?php foreach ($webhooks as $wh): ?>
                    <form method="post" action="/api/forms.php" class="rounded-lg border border-ink-100 bg-white p-3 space-y-2">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="save_webhook">
                        <input type="hidden" name="form_id" value="<?= (int)$active['id'] ?>">
                        <input type="hidden" name="webhook_id" value="<?= (int)$wh['id'] ?>">

                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <input name="name" type="text" value="<?= e($wh['name']) ?>" placeholder="Name" required class="rounded-md border border-ink-200 bg-white px-3 py-2 text-sm">
                            <input name="url" type="url" value="<?= e($wh['url']) ?>" placeholder="https://..." required class="rounded-md border border-ink-200 bg-white px-3 py-2 text-sm">
                        </div>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                            <select name="method" class="rounded-md border border-ink-200 bg-white px-3 py-2 text-sm">
                                <?php foreach (['POST','PUT','PATCH'] as $m): ?>
                                    <option value="<?= $m ?>" <?= $wh['method'] === $m ? 'selected' : '' ?>><?= $m ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input name="signing_secret" type="text" value="<?= e((string)($wh['signing_secret'] ?? '')) ?>" placeholder="Signing secret (optional)" class="rounded-md border border-ink-200 bg-white px-3 py-2 font-mono text-xs sm:col-span-2">
                        </div>
                        <textarea name="headers_json" rows="2" placeholder='Extra headers JSON, e.g. {"Authorization":"Bearer ..."}' class="w-full rounded-md border border-ink-200 bg-white px-3 py-2 font-mono text-xs"><?= e((string)($wh['headers_json'] ?? '')) ?></textarea>
                        <textarea name="payload_template_json" rows="3" placeholder='Payload template (JSON). Use {{field_name}} placeholders.' class="w-full rounded-md border border-ink-200 bg-white px-3 py-2 font-mono text-xs"><?= e((string)($wh['payload_template_json'] ?? '')) ?></textarea>
                        <div class="flex items-center justify-between gap-2">
                            <label class="inline-flex items-center gap-2 text-sm text-ink-700">
                                <input type="checkbox" name="enabled" value="1" <?= (int)$wh['enabled'] === 1 ? 'checked' : '' ?>>
                                Enabled
                            </label>
                            <div class="flex items-center gap-2">
                                <button type="submit" class="rounded-md border border-brand-600 bg-brand-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-700">Save</button>
                                <button type="submit" name="action" value="delete_webhook" onclick="return confirm('Delete this webhook?');" class="text-xs text-rose-700 hover:text-rose-800">Delete</button>
                            </div>
                        </div>
                    </form>
                <?php endforeach; ?>
            </div>
            <details class="mt-4 rounded-xl border border-ink-100 bg-white p-4">
                <summary class="cursor-pointer text-sm font-medium text-brand-700">+ Add webhook</summary>
                <form method="post" action="/api/forms.php" class="mt-3 grid gap-2 sm:grid-cols-2">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="add_webhook">
                    <input type="hidden" name="form_id" value="<?= (int)$active['id'] ?>">
                    <input name="name" type="text" required placeholder="Name" class="rounded-md border border-ink-200 bg-white px-3 py-2 text-sm">
                    <input name="url" type="url" required placeholder="https://..." class="rounded-md border border-ink-200 bg-white px-3 py-2 text-sm">
                    <div class="sm:col-span-2 text-right">
                        <button type="submit" class="rounded-md border border-brand-600 bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Add webhook</button>
                    </div>
                </form>
            </details>

        <?php elseif ($tab === 'submissions'): ?>
            <?php
            $stmt = $pdo->prepare(
                'SELECT id, data_json, full_name, email, webhook_status, submitted_at, ip_address
                   FROM form_submissions WHERE form_id = :f ORDER BY submitted_at DESC LIMIT 100'
            );
            $stmt->execute([':f' => (int)$active['id']]);
            $subs = $stmt->fetchAll();
            ?>
            <p class="text-sm text-ink-500">Most recent <?= count($subs) ?> of <?= $count_subs ?> submissions.</p>
            <?php if ($subs === []): ?>
                <p class="mt-4 text-sm text-ink-500">No submissions yet.</p>
            <?php else: ?>
                <div class="mt-4 overflow-x-auto rounded-xl border border-ink-100 bg-white">
                    <table class="w-full text-sm">
                        <thead class="border-b border-ink-100 bg-ink-50/50 text-left text-xs uppercase tracking-wider text-ink-500">
                            <tr>
                                <th class="px-3 py-2">When</th>
                                <th class="px-3 py-2">Data</th>
                                <th class="px-3 py-2">Webhook</th>
                                <th class="px-3 py-2">IP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            <?php foreach ($subs as $s):
                                $data = $s['data_json'] ? json_decode($s['data_json'], true) : [];
                                if (!is_array($data)) $data = [];
                                $preview = $data['full_name'] ?? ($data['email'] ?? ($data['name'] ?? '(no data)'));
                            ?>
                                <tr>
                                    <td class="px-3 py-2 text-xs text-ink-500 whitespace-nowrap"><?= e($s['submitted_at']) ?></td>
                                    <td class="px-3 py-2">
                                        <div class="font-medium text-ink-900"><?= e((string)$preview) ?></div>
                                        <?php if (!empty($data['email']) && $preview !== $data['email']): ?>
                                            <div class="text-xs text-ink-500"><?= e((string)$data['email']) ?></div>
                                        <?php endif; ?>
                                        <details class="mt-1 text-xs text-ink-500">
                                            <summary class="cursor-pointer">Full data</summary>
                                            <pre class="mt-1 max-h-48 overflow-auto rounded border border-ink-100 bg-ink-50 p-2 font-mono text-[11px]"><?= e(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>
                                        </details>
                                    </td>
                                    <td class="px-3 py-2 text-xs"><?= e((string)($s['webhook_status'] ?? '—')) ?></td>
                                    <td class="px-3 py-2 text-xs text-ink-500"><?= e((string)($s['ip_address'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        <?php elseif ($tab === 'embed'): ?>
            <p class="text-sm text-ink-500">Drop this snippet into a page (or a section partial) to render the form.</p>
            <pre class="mt-4 overflow-x-auto rounded-xl border border-ink-100 bg-ink-900 p-4 font-mono text-xs text-emerald-50">&lt;?php require_once GUA_CORE_PATH . '/lib/forms.php'; ?&gt;
&lt;?= form_render('<?= e($active['slug']) ?>') ?&gt;</pre>
            <p class="mt-4 text-sm text-ink-500">Or post directly: <code>POST /api/form.php?form=<?= e($active['slug']) ?></code></p>
        <?php endif; ?>
    </div>
<?php
admin_foot();
