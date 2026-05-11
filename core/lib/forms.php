<?php
// core/lib/forms.php — multi-form CRUD + rendering + validation (v2 Stage 6).
//
// Forms have a slug, a settings JSON blob, a set of fields, and a set of
// webhooks. Public submissions go through /api/form.php?form=<slug> which
// uses form_validate() and persists into form_submissions (with form_id
// and data_json), then fires every enabled form_webhooks row.
//
// form_render($slug) emits the HTML form — used by /admin/forms.php
// embed snippet and by site/sections/final_cta.php for the waitlist
// back-compat path.

declare(strict_types=1);

require_once __DIR__ . '/db.php';

const FORM_FIELD_TYPES = [
    'text', 'email', 'phone', 'textarea', 'select', 'radio', 'checkbox',
    'file', 'hidden', 'url', 'number', 'date',
];
const FORM_STATUSES = ['active', 'draft', 'archived'];

/* ---------- Form reads ---------- */

function forms_all(?string $status = null): array
{
    try {
        if ($status === null) {
            return db()->query('SELECT * FROM forms ORDER BY id')->fetchAll();
        }
        $stmt = db()->prepare('SELECT * FROM forms WHERE status = :s ORDER BY id');
        $stmt->execute([':s' => $status]);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function form_by_slug(string $slug): ?array
{
    try {
        $stmt = db()->prepare('SELECT * FROM forms WHERE slug = :s LIMIT 1');
        $stmt->execute([':s' => $slug]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    } catch (Throwable $e) {
        return null;
    }
}

function form_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM forms WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function form_settings(array $form): array
{
    $raw = $form['settings_json'] ?? '';
    if (!is_string($raw) || $raw === '') return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function form_fields(int $form_id): array
{
    $stmt = db()->prepare(
        'SELECT * FROM form_fields WHERE form_id = :f ORDER BY position, id'
    );
    $stmt->execute([':f' => $form_id]);
    return $stmt->fetchAll();
}

function form_webhooks(int $form_id, ?bool $enabled_only = null): array
{
    if ($enabled_only === true) {
        $stmt = db()->prepare(
            'SELECT * FROM form_webhooks WHERE form_id = :f AND enabled = 1 ORDER BY id'
        );
    } else {
        $stmt = db()->prepare(
            'SELECT * FROM form_webhooks WHERE form_id = :f ORDER BY id'
        );
    }
    $stmt->execute([':f' => $form_id]);
    return $stmt->fetchAll();
}

function form_submission_count(int $form_id): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM form_submissions WHERE form_id = :f');
    $stmt->execute([':f' => $form_id]);
    return (int) $stmt->fetchColumn();
}

/* ---------- Form CRUD ---------- */

function form_assert_slug(string $slug): void
{
    if (!preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $slug)) {
        throw new InvalidArgumentException("form: slug '$slug' must match ^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$");
    }
}

function form_create(array $fields, ?int $user_id = null): int
{
    $slug = trim((string)($fields['slug'] ?? ''));
    $name = trim((string)($fields['name'] ?? ''));
    form_assert_slug($slug);
    if ($name === '') throw new InvalidArgumentException('form: name required');
    $settings = $fields['settings'] ?? [];
    $stmt = db()->prepare(
        'INSERT INTO forms (slug, name, description, status, settings_json, is_builtin, created_at, updated_at, updated_by)
         VALUES (:s, :n, :d, :st, :j, 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, :u)'
    );
    $stmt->execute([
        ':s'  => $slug,
        ':n'  => $name,
        ':d'  => $fields['description'] ?? '',
        ':st' => $fields['status'] ?? 'active',
        ':j'  => is_array($settings) ? json_encode($settings) : (string)$settings,
        ':u'  => $user_id,
    ]);
    return (int) db()->lastInsertId();
}

function form_update(int $id, array $fields, ?int $user_id = null): void
{
    $existing = form_by_id($id);
    if ($existing === null) throw new InvalidArgumentException("form: id $id not found");
    $name = array_key_exists('name', $fields) ? trim((string)$fields['name']) : $existing['name'];
    if ($name === '') throw new InvalidArgumentException('form: name required');
    $settings = array_key_exists('settings', $fields) ? $fields['settings'] : form_settings($existing);
    $stmt = db()->prepare(
        'UPDATE forms SET name = :n, description = :d, status = :st,
                settings_json = :j, updated_at = CURRENT_TIMESTAMP, updated_by = :u
          WHERE id = :id'
    );
    $stmt->execute([
        ':n'  => $name,
        ':d'  => array_key_exists('description', $fields) ? (string)$fields['description'] : (string)($existing['description'] ?? ''),
        ':st' => array_key_exists('status', $fields) ? (string)$fields['status'] : $existing['status'],
        ':j'  => is_array($settings) ? json_encode($settings) : (string)$settings,
        ':u'  => $user_id,
        ':id' => $id,
    ]);
}

function form_delete(int $id): void
{
    $existing = form_by_id($id);
    if ($existing && (int)$existing['is_builtin'] === 1) {
        throw new InvalidArgumentException("form: cannot delete builtin form '{$existing['slug']}'");
    }
    db()->prepare('DELETE FROM forms WHERE id = :id')->execute([':id' => $id]);
}

/* ---------- Field CRUD ---------- */

function form_field_create(int $form_id, array $fields): int
{
    $name = trim((string)($fields['name'] ?? ''));
    if (!preg_match('/^[a-z_][a-z0-9_]*$/', $name)) {
        throw new InvalidArgumentException("form_field: name '$name' must be lowercase letters/digits/underscores");
    }
    $type = (string)($fields['type'] ?? 'text');
    if (!in_array($type, FORM_FIELD_TYPES, true)) {
        throw new InvalidArgumentException("form_field: invalid type '$type'");
    }
    $stmt = db()->prepare(
        'INSERT INTO form_fields
            (form_id, position, type, name, label, placeholder, default_value, required, options_json, validation_json, help_text)
         VALUES (:f,
                 (SELECT COALESCE(MAX(position), -1) + 1 FROM form_fields WHERE form_id = :f),
                 :t, :n, :l, :p, :dv, :r, :o, :v, :h)'
    );
    $stmt->execute([
        ':f'  => $form_id,
        ':t'  => $type,
        ':n'  => $name,
        ':l'  => trim((string)($fields['label'] ?? $name)),
        ':p'  => $fields['placeholder']   ?? null,
        ':dv' => $fields['default_value'] ?? null,
        ':r'  => !empty($fields['required']) ? 1 : 0,
        ':o'  => isset($fields['options'])    && is_array($fields['options'])    ? json_encode($fields['options'])    : ($fields['options_json']    ?? null),
        ':v'  => isset($fields['validation']) && is_array($fields['validation']) ? json_encode($fields['validation']) : ($fields['validation_json'] ?? null),
        ':h'  => $fields['help_text']     ?? null,
    ]);
    return (int) db()->lastInsertId();
}

function form_field_update(int $id, array $fields): void
{
    $existing = db()->prepare('SELECT * FROM form_fields WHERE id = :id LIMIT 1');
    $existing->execute([':id' => $id]);
    $row = $existing->fetch();
    if ($row === false) throw new InvalidArgumentException("form_field: id $id not found");

    $type = array_key_exists('type', $fields) ? (string)$fields['type'] : $row['type'];
    if (!in_array($type, FORM_FIELD_TYPES, true)) {
        throw new InvalidArgumentException("form_field: invalid type '$type'");
    }

    db()->prepare(
        'UPDATE form_fields
            SET type = :t, label = :l, placeholder = :p, default_value = :dv,
                required = :r, options_json = :o, validation_json = :v,
                help_text = :h, position = :pos
          WHERE id = :id'
    )->execute([
        ':t'   => $type,
        ':l'   => array_key_exists('label', $fields) ? trim((string)$fields['label']) : $row['label'],
        ':p'   => array_key_exists('placeholder',   $fields) ? $fields['placeholder']   : $row['placeholder'],
        ':dv'  => array_key_exists('default_value', $fields) ? $fields['default_value'] : $row['default_value'],
        ':r'   => array_key_exists('required',      $fields) ? (!empty($fields['required']) ? 1 : 0) : (int)$row['required'],
        ':o'   => array_key_exists('options', $fields)
                    ? (is_array($fields['options']) ? json_encode($fields['options']) : (string)$fields['options'])
                    : $row['options_json'],
        ':v'   => array_key_exists('validation', $fields)
                    ? (is_array($fields['validation']) ? json_encode($fields['validation']) : (string)$fields['validation'])
                    : $row['validation_json'],
        ':h'   => array_key_exists('help_text', $fields) ? $fields['help_text'] : $row['help_text'],
        ':pos' => array_key_exists('position',  $fields) ? (int)$fields['position']  : (int)$row['position'],
        ':id'  => $id,
    ]);
}

function form_field_delete(int $id): void
{
    db()->prepare('DELETE FROM form_fields WHERE id = :id')->execute([':id' => $id]);
}

/* ---------- Webhook CRUD ---------- */

function form_webhook_create(int $form_id, array $fields): int
{
    $url = trim((string)($fields['url'] ?? ''));
    if ($url === '') throw new InvalidArgumentException('webhook: url required');
    $method = strtoupper((string)($fields['method'] ?? 'POST'));
    if (!in_array($method, ['POST', 'PUT', 'PATCH'], true)) $method = 'POST';
    $stmt = db()->prepare(
        'INSERT INTO form_webhooks
            (form_id, name, url, method, headers_json, payload_template_json, fire_on_json,
             signing_secret, max_retries, enabled)
         VALUES (:f, :n, :u, :m, :h, :p, :fo, :s, :r, :e)'
    );
    $stmt->execute([
        ':f'  => $form_id,
        ':n'  => trim((string)($fields['name'] ?? 'Webhook')),
        ':u'  => $url,
        ':m'  => $method,
        ':h'  => isset($fields['headers']) && is_array($fields['headers']) ? json_encode($fields['headers']) : ($fields['headers_json'] ?? null),
        ':p'  => isset($fields['payload_template']) && is_array($fields['payload_template']) ? json_encode($fields['payload_template']) : ($fields['payload_template_json'] ?? null),
        ':fo' => isset($fields['fire_on']) && is_array($fields['fire_on']) ? json_encode($fields['fire_on']) : ($fields['fire_on_json'] ?? null),
        ':s'  => $fields['signing_secret'] ?? null,
        ':r'  => (int)($fields['max_retries'] ?? 6),
        ':e'  => array_key_exists('enabled', $fields) ? (!empty($fields['enabled']) ? 1 : 0) : 1,
    ]);
    return (int) db()->lastInsertId();
}

function form_webhook_update(int $id, array $fields): void
{
    $stmt = db()->prepare('SELECT * FROM form_webhooks WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if ($row === false) throw new InvalidArgumentException("webhook: id $id not found");

    $method = array_key_exists('method', $fields) ? strtoupper((string)$fields['method']) : $row['method'];
    if (!in_array($method, ['POST', 'PUT', 'PATCH'], true)) $method = 'POST';

    db()->prepare(
        'UPDATE form_webhooks
            SET name = :n, url = :u, method = :m, headers_json = :h,
                payload_template_json = :p, fire_on_json = :fo,
                signing_secret = :s, max_retries = :r, enabled = :e
          WHERE id = :id'
    )->execute([
        ':n'  => array_key_exists('name', $fields) ? trim((string)$fields['name']) : $row['name'],
        ':u'  => array_key_exists('url',  $fields) ? trim((string)$fields['url'])  : $row['url'],
        ':m'  => $method,
        ':h'  => array_key_exists('headers', $fields) ? (is_array($fields['headers']) ? json_encode($fields['headers']) : (string)$fields['headers']) : $row['headers_json'],
        ':p'  => array_key_exists('payload_template', $fields) ? (is_array($fields['payload_template']) ? json_encode($fields['payload_template']) : (string)$fields['payload_template']) : $row['payload_template_json'],
        ':fo' => array_key_exists('fire_on', $fields) ? (is_array($fields['fire_on']) ? json_encode($fields['fire_on']) : (string)$fields['fire_on']) : $row['fire_on_json'],
        ':s'  => array_key_exists('signing_secret', $fields) ? $fields['signing_secret'] : $row['signing_secret'],
        ':r'  => array_key_exists('max_retries',    $fields) ? (int)$fields['max_retries'] : (int)$row['max_retries'],
        ':e'  => array_key_exists('enabled',        $fields) ? (!empty($fields['enabled']) ? 1 : 0) : (int)$row['enabled'],
        ':id' => $id,
    ]);
}

function form_webhook_delete(int $id): void
{
    db()->prepare('DELETE FROM form_webhooks WHERE id = :id')->execute([':id' => $id]);
}

/* ---------- Validation ---------- */

/**
 * Validate $input against $form's fields. Returns
 *   ['ok' => true,  'data' => [...]]                  on success
 *   ['ok' => false, 'errors' => [field => message]]  on failure
 */
function form_validate(array $form, array $input): array
{
    $errors = [];
    $clean = [];
    foreach (form_fields((int)$form['id']) as $f) {
        $name  = $f['name'];
        $type  = $f['type'];
        $raw   = $input[$name] ?? null;
        $value = is_array($raw) ? array_map('trim', array_map('strval', $raw)) : trim((string)($raw ?? ''));
        $is_empty = ($value === '' || $value === []);

        if ((int)$f['required'] === 1 && $is_empty) {
            $errors[$name] = ($f['label'] ?? $name) . ' is required';
            continue;
        }
        if ($is_empty) {
            $clean[$name] = '';
            continue;
        }

        switch ($type) {
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$name] = 'Enter a valid email address';
                }
                break;
            case 'phone':
                // permissive: digits, spaces, +-() . at least 6 digits total
                if (preg_match_all('/\d/', (string)$value) < 6) {
                    $errors[$name] = 'Enter a valid phone number';
                }
                break;
            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    $errors[$name] = 'Enter a valid URL';
                }
                break;
            case 'number':
                if (!is_numeric($value)) {
                    $errors[$name] = ($f['label'] ?? $name) . ' must be a number';
                }
                break;
        }

        $rules = $f['validation_json'] ? json_decode($f['validation_json'], true) : null;
        if (is_array($rules)) {
            if (isset($rules['max_length']) && mb_strlen((string)$value) > (int)$rules['max_length']) {
                $errors[$name] = ($f['label'] ?? $name) . ' is too long (max ' . (int)$rules['max_length'] . ')';
            }
            if (isset($rules['min_length']) && mb_strlen((string)$value) < (int)$rules['min_length']) {
                $errors[$name] = ($f['label'] ?? $name) . ' is too short (min ' . (int)$rules['min_length'] . ')';
            }
            if (isset($rules['pattern']) && is_string($rules['pattern'])) {
                $pat = '#' . str_replace('#', '\\#', $rules['pattern']) . '#u';
                if (!@preg_match($pat, (string)$value)) {
                    $errors[$name] = $rules['error'] ?? (($f['label'] ?? $name) . ' is invalid');
                }
            }
        }

        if (!isset($errors[$name])) {
            $clean[$name] = $value;
        }
    }
    if ($errors !== []) {
        return ['ok' => false, 'errors' => $errors];
    }
    return ['ok' => true, 'data' => $clean];
}

/* ---------- Rendering ---------- */

/**
 * Emit the HTML form for a given form slug. Designed for site/sections/
 * partials to use ("inject the waitlist form here"). Includes CSRF token,
 * honeypot (from settings.honeypot), and one input per form_fields row.
 *
 * Caller wraps the form with its own <section> / heading / styling.
 */
function form_render(string $slug, array $opts = []): string
{
    $form = form_by_slug($slug);
    if ($form === null) {
        return '<!-- form_render: unknown form ' . htmlspecialchars($slug, ENT_QUOTES) . ' -->';
    }
    $settings = form_settings($form);
    $honeypot = (string)($settings['honeypot'] ?? 'website');
    $fields   = form_fields((int)$form['id']);
    $action   = $opts['action'] ?? '/api/form.php?form=' . urlencode($slug);
    $form_id  = $opts['html_id'] ?? ($slug . '-form');

    ob_start();
    ?>
    <form id="<?= htmlspecialchars($form_id, ENT_QUOTES) ?>"
          action="<?= htmlspecialchars($action, ENT_QUOTES) ?>"
          method="post" novalidate
          class="grid gap-4 rounded-2xl border border-ink-100 bg-white p-6 shadow-sm sm:p-8">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
        <input type="hidden" name="form" value="<?= htmlspecialchars($slug, ENT_QUOTES) ?>">

        <!-- Honeypot — humans don't fill it; bots usually do. -->
        <div aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">
            <label for="<?= htmlspecialchars($honeypot, ENT_QUOTES) ?>">Leave this empty</label>
            <input id="<?= htmlspecialchars($honeypot, ENT_QUOTES) ?>" name="<?= htmlspecialchars($honeypot, ENT_QUOTES) ?>" type="text" tabindex="-1" autocomplete="off">
        </div>

        <?php foreach ($fields as $f): if ($f['type'] === 'hidden') continue; ?>
            <div>
                <label for="<?= htmlspecialchars($f['name'], ENT_QUOTES) ?>" class="mb-1.5 block text-sm font-medium text-ink-800">
                    <?= htmlspecialchars($f['label'], ENT_QUOTES) ?>
                    <?php if ((int)$f['required'] === 1): ?><span class="text-brand-600" aria-hidden="true">*</span><?php endif; ?>
                </label>
                <?php form_render_field($f); ?>
                <?php if (!empty($f['help_text'])): ?>
                    <p class="mt-1 text-xs text-ink-500"><?= htmlspecialchars($f['help_text'], ENT_QUOTES) ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <!-- Hidden fields (no label) -->
        <?php foreach ($fields as $f): if ($f['type'] !== 'hidden') continue; ?>
            <input type="hidden" name="<?= htmlspecialchars($f['name'], ENT_QUOTES) ?>" value="<?= htmlspecialchars((string)($f['default_value'] ?? ''), ENT_QUOTES) ?>">
        <?php endforeach; ?>

        <button type="submit"
                class="rounded-lg bg-brand-600 px-5 py-2.5 text-base font-medium text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">
            <?= htmlspecialchars($opts['submit_label'] ?? 'Submit', ENT_QUOTES) ?>
        </button>
    </form>
    <?php
    return (string) ob_get_clean();
}

function form_render_field(array $f): void
{
    $name = htmlspecialchars($f['name'], ENT_QUOTES);
    $placeholder = htmlspecialchars((string)($f['placeholder'] ?? ''), ENT_QUOTES);
    $default = htmlspecialchars((string)($f['default_value'] ?? ''), ENT_QUOTES);
    $required = (int)$f['required'] === 1 ? 'required' : '';
    $rules = $f['validation_json'] ? json_decode($f['validation_json'], true) : null;
    $max = isset($rules['max_length']) ? ' maxlength="' . (int)$rules['max_length'] . '"' : '';
    $base_class = 'w-full rounded-lg border border-ink-200 bg-white px-3 py-2.5 text-base text-ink-900 placeholder:text-ink-500 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500';

    switch ($f['type']) {
        case 'textarea':
            echo '<textarea id="' . $name . '" name="' . $name . '" rows="4" placeholder="' . $placeholder . '" ' . $required . $max . ' class="' . $base_class . '">' . $default . '</textarea>';
            break;
        case 'select':
            $opts = $f['options_json'] ? json_decode($f['options_json'], true) : [];
            if (!is_array($opts)) $opts = [];
            echo '<select id="' . $name . '" name="' . $name . '" ' . $required . ' class="' . $base_class . '">';
            if (!$required) echo '<option value="">— select —</option>';
            foreach ($opts as $o) {
                $v = is_array($o) ? (string)($o['value'] ?? $o['label'] ?? '') : (string)$o;
                $l = is_array($o) ? (string)($o['label'] ?? $o['value'] ?? '') : (string)$o;
                echo '<option value="' . htmlspecialchars($v, ENT_QUOTES) . '"' . ($v === (string)($f['default_value'] ?? '') ? ' selected' : '') . '>' . htmlspecialchars($l, ENT_QUOTES) . '</option>';
            }
            echo '</select>';
            break;
        case 'radio':
        case 'checkbox':
            $opts = $f['options_json'] ? json_decode($f['options_json'], true) : [];
            if (!is_array($opts)) $opts = [];
            $input_type = $f['type'];
            $name_attr  = $f['type'] === 'checkbox' ? $name . '[]' : $name;
            echo '<div class="space-y-1">';
            foreach ($opts as $o) {
                $v = is_array($o) ? (string)($o['value'] ?? $o['label'] ?? '') : (string)$o;
                $l = is_array($o) ? (string)($o['label'] ?? $o['value'] ?? '') : (string)$o;
                echo '<label class="inline-flex items-center gap-2 text-sm text-ink-800">';
                echo '<input type="' . $input_type . '" name="' . $name_attr . '" value="' . htmlspecialchars($v, ENT_QUOTES) . '">';
                echo htmlspecialchars($l, ENT_QUOTES);
                echo '</label><br>';
            }
            echo '</div>';
            break;
        default:
            $itype = match ($f['type']) {
                'email'  => 'email',
                'phone'  => 'tel',
                'url'    => 'url',
                'number' => 'number',
                'date'   => 'date',
                'file'   => 'file',
                default  => 'text',
            };
            echo '<input id="' . $name . '" name="' . $name . '" type="' . $itype . '" value="' . $default . '" placeholder="' . $placeholder . '" ' . $required . $max . ' class="' . $base_class . '">';
    }
}

/**
 * Resolve a payload template (JSON object). Strings of form {{field_name}}
 * are replaced with submission data. {{meta.<key>}} pulls from $meta
 * (submitted_at, ip_address, etc.). Returns the resolved associative
 * array — caller json_encodes for the actual HTTP body.
 */
function form_resolve_payload(?array $template, array $data, array $meta): array
{
    if ($template === null || $template === []) {
        return array_merge($data, ['_meta' => $meta]);
    }
    $resolve = function ($v) use (&$resolve, $data, $meta) {
        if (is_array($v)) {
            $out = [];
            foreach ($v as $k => $vv) $out[$k] = $resolve($vv);
            return $out;
        }
        if (!is_string($v)) return $v;
        return preg_replace_callback('/\{\{\s*([a-z0-9_.]+)\s*\}\}/i', function ($m) use ($data, $meta) {
            $key = $m[1];
            if (str_starts_with($key, 'meta.')) {
                $k = substr($key, 5);
                return (string)($meta[$k] ?? '');
            }
            return (string)($data[$key] ?? '');
        }, $v);
    };
    return (array) $resolve($template);
}
