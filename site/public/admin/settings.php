<?php
// site/public/admin/settings.php — DB-backed site settings (v2 Stage 1).
//
// Tabs map to site_settings.group_name. Each tab is an HTML form that
// POSTs to /api/settings.php; the API redirects back here with ?saved=1
// (or ?error=…) so the page works without JS.
//
// Layered display:
//   - "DB value" badge → admin override is in effect
//   - "from .env" badge → no DB override, .env supplies the value
//   - "default" badge   → neither DB nor .env set, code default applies
// Editing the input + Save writes the DB value (or clears it if blank,
// which restores the .env / default fallback for subsequent requests).

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';
require __DIR__ . '/_layout.php';
auth_require_login();

$groups = settings_groups();
if ($groups === []) {
    // Migrations not run yet — bail with a helpful note.
    admin_head('Settings', 'settings');
    echo '<h1 class="text-2xl font-semibold tracking-tight text-ink-900">Settings</h1>';
    echo '<p class="mt-4 text-ink-600">Run <code class="rounded bg-ink-100 px-1.5 py-0.5">php core/scripts/migrate.php</code> to create the <code class="rounded bg-ink-100 px-1.5 py-0.5">site_settings</code> table.</p>';
    admin_foot();
    exit;
}

$active = (string) ($_GET['tab'] ?? $groups[0]);
if (!in_array($active, $groups, true)) {
    $active = $groups[0];
}

$rows  = settings_all_in_group($active);
$saved = isset($_GET['saved']);
$error = (string) ($_GET['error'] ?? '');

$group_labels = [
    'general'  => 'General',
    'seo'      => 'SEO Defaults',
    'forms'    => 'Forms',
    'media'    => 'Media',
    'ai'       => 'AI',
    'webhooks' => 'Webhooks',
    'branding' => 'Branding',
];

admin_head('Settings', 'settings');
?>
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-ink-900">Settings</h1>
            <p class="mt-2 text-ink-600">Runtime config for this site. Saved values override <code class="rounded bg-ink-100 px-1.5 py-0.5">.env</code> on the next request.</p>
        </div>
    </div>

    <?php if ($saved): ?>
        <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-900">
            Settings saved.
        </div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-900">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <nav class="mt-6 flex flex-wrap gap-1 border-b border-ink-100">
        <?php foreach ($groups as $g):
            $is_active = $g === $active;
            $label = $group_labels[$g] ?? ucfirst($g);
            $cls = 'whitespace-nowrap border-b-2 px-3 py-2 text-sm transition '
                . ($is_active
                    ? 'border-brand-600 font-medium text-ink-900'
                    : 'border-transparent text-ink-500 hover:text-ink-800');
        ?>
            <a href="?tab=<?= e($g) ?>" class="<?= $cls ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <form method="post" action="/api/settings.php" class="mt-6 space-y-5">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="tab" value="<?= e($active) ?>">

        <?php foreach ($rows as $row):
            $key  = $row['key'];
            $type = $row['value_type'];
            $src  = settings_source($key);
            $eff  = settings_get($key);
            // Display the DB value when present, else the effective .env / default value
            $display = $row['value'] !== null ? (string)$row['value'] : (string)($eff ?? '');
        ?>
            <div class="rounded-xl border border-ink-100 bg-white p-4">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <label for="set_<?= e($key) ?>" class="text-sm font-semibold text-ink-900"><?= e($row['label']) ?></label>
                    <span class="inline-flex items-center gap-1 text-xs">
                        <?php if ($src === 'db'): ?>
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 font-medium text-emerald-800">DB override</span>
                        <?php elseif ($src === 'env'): ?>
                            <span class="rounded-full bg-ink-100 px-2 py-0.5 font-medium text-ink-700">from .env</span>
                        <?php else: ?>
                            <span class="rounded-full bg-ink-100 px-2 py-0.5 font-medium text-ink-500">default</span>
                        <?php endif; ?>
                        <code class="rounded bg-ink-50 px-1.5 py-0.5 text-ink-500"><?= e($key) ?></code>
                    </span>
                </div>

                <?php if (!empty($row['description'])): ?>
                    <p class="mt-1 text-xs text-ink-500"><?= e($row['description']) ?></p>
                <?php endif; ?>

                <div class="mt-3">
                    <?php if ($type === 'boolean'): ?>
                        <select id="set_<?= e($key) ?>" name="settings[<?= e($key) ?>]"
                                class="w-full rounded-md border border-ink-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                            <option value="">— use .env / default —</option>
                            <option value="1" <?= $row['value'] === '1' ? 'selected' : '' ?>>On</option>
                            <option value="0" <?= $row['value'] === '0' ? 'selected' : '' ?>>Off</option>
                        </select>
                    <?php elseif ($type === 'number'): ?>
                        <input id="set_<?= e($key) ?>" name="settings[<?= e($key) ?>]" type="number"
                               value="<?= e($row['value'] ?? '') ?>"
                               placeholder="<?= e((string)($eff ?? $row['default_value'] ?? '')) ?>"
                               class="w-full rounded-md border border-ink-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    <?php elseif ($type === 'json'): ?>
                        <textarea id="set_<?= e($key) ?>" name="settings[<?= e($key) ?>]" rows="4"
                                  placeholder="<?= e((string)($row['default_value'] ?? '[]')) ?>"
                                  class="w-full rounded-md border border-ink-200 bg-white px-3 py-2 font-mono text-xs focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"><?= e($row['value'] ?? '') ?></textarea>
                    <?php else: /* string / secret */ ?>
                        <input id="set_<?= e($key) ?>"
                               name="settings[<?= e($key) ?>]"
                               type="<?= $row['is_secret'] ? 'password' : 'text' ?>"
                               value="<?= e($row['value'] ?? '') ?>"
                               placeholder="<?= e((string)($eff ?? $row['default_value'] ?? '')) ?>"
                               class="w-full rounded-md border border-ink-200 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    <?php endif; ?>
                </div>

                <p class="mt-2 text-xs text-ink-500">
                    Effective: <code class="rounded bg-ink-50 px-1.5 py-0.5 text-ink-700"><?= e($display === '' ? '—' : $display) ?></code>
                    <?php if (!empty($row['default_value']) && $src !== 'default'): ?>
                        · default: <code class="rounded bg-ink-50 px-1.5 py-0.5 text-ink-500"><?= e($row['default_value']) ?></code>
                    <?php endif; ?>
                </p>
            </div>
        <?php endforeach; ?>

        <div class="flex items-center justify-end gap-3">
            <p class="mr-auto text-xs text-ink-500">
                Empty fields clear the DB override and fall back to <code class="rounded bg-ink-100 px-1.5 py-0.5">.env</code> / default.
            </p>
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-md border border-brand-600 bg-brand-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-1">
                Save <?= e($group_labels[$active] ?? ucfirst($active)) ?>
            </button>
        </div>
    </form>
<?php
admin_foot();
