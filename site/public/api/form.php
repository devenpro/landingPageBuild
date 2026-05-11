<?php
// site/public/api/form.php — public form submission endpoint (v2 Stage 6).
//
// Multi-form aware: $_GET['form'] (or $_POST['form']) selects the form
// by slug. Defaults to 'waitlist' for v1 back-compat — existing markup
// posts to /api/form.php without a form param and still works.
//
// Pipeline:
//   1. Negotiate JSON vs HTML response format
//   2. CSRF check (session-bound)
//   3. Per-IP rate limit (form_submissions count in rolling window)
//   4. Resolve form by slug — 404 if unknown
//   5. Honeypot (field name from form.settings_json.honeypot, default 'website')
//   6. Validate via form_validate($form, $_POST)
//   7. INSERT form_submissions with form_id + data_json (and v1 fixed
//      columns when present, so legacy readers keep working)
//   8. Fire every form_webhooks row where enabled=1 (resolve payload
//      template against submission data + meta). Legacy fallback: if
//      form='waitlist' AND no form_webhooks rows AND GUA_WEBHOOK_URL set,
//      fire that as a single delivery.
//   9. Respond — JSON for fetch / XHR, full HTML page for plain form POST

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';
require_once __DIR__ . '/../../../core/lib/forms.php';

header('Cache-Control: no-store');

$wants_json = ((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch'
    || stripos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false
       && stripos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'text/html') === false;

function form_respond(int $status, array $json, string $html_title, string $html_body, bool $wants_json): void
{
    http_response_code($status);
    if ($wants_json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($json);
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    form_render_html_page($html_title, $html_body, $status);
    exit;
}

function form_render_html_page(string $title, string $body_html, int $status): void
{
    $site   = e(GUA_SITE_NAME);
    $accent = $status >= 400 ? '#b91c1c' : '#7c3aed';
    echo <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>{$title} — {$site}</title>
<style>
  *,*::before,*::after{box-sizing:border-box}
  body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f9fafb;color:#1f2937;line-height:1.5}
  .wrap{min-height:100vh;display:grid;place-items:center;padding:1.5rem}
  .card{max-width:520px;width:100%;background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:2rem;box-shadow:0 1px 2px rgba(0,0,0,.04)}
  .dot{width:48px;height:48px;border-radius:9999px;background:{$accent}1a;color:{$accent};display:grid;place-items:center;margin:0 auto 1rem;font-size:24px;font-weight:600}
  h1{margin:0 0 .5rem;font-size:1.5rem;color:#111827;text-align:center}
  .body{color:#4b5563;text-align:center}
  .errors{margin-top:1rem;text-align:left;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:.75rem 1rem;font-size:.9rem;color:#7f1d1d}
  .errors li{margin:.15rem 0}
  .actions{margin-top:1.5rem;text-align:center}
  a.btn{display:inline-block;background:#7c3aed;color:#fff;text-decoration:none;padding:.65rem 1.25rem;border-radius:9999px;font-weight:500}
</style>
</head>
<body>
  <div class="wrap"><div class="card">
    <div class="dot">{$body_html}
  </div></div>
</body>
</html>
HTML;
}

function form_html_success(string $heading, string $body): string
{
    $h = e($heading);
    $b = e($body);
    $back = e((GUA_APP_URL !== '' ? GUA_APP_URL : '') . '/');
    return <<<HTML
✓</div>
    <h1>{$h}</h1>
    <p class="body">{$b}</p>
    <div class="actions"><a class="btn" href="{$back}">Back to home</a></div>
HTML;
}

function form_html_error(string $title, string $message, array $field_errors = []): string
{
    $t = e($title);
    $m = e($message);
    $back = e((GUA_APP_URL !== '' ? GUA_APP_URL : '') . '/');
    $errs = '';
    if ($field_errors !== []) {
        $errs = '<ul class="errors">';
        foreach ($field_errors as $field => $msg) {
            $errs .= '<li><strong>' . e($field) . ':</strong> ' . e($msg) . '</li>';
        }
        $errs .= '</ul>';
    }
    return <<<HTML
!</div>
    <h1>{$t}</h1>
    <p class="body">{$m}</p>
    {$errs}
    <div class="actions"><a class="btn" href="{$back}">Back</a></div>
HTML;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    form_respond(405, ['ok' => false, 'error' => 'Method not allowed'], 'Not allowed', form_html_error('Not allowed', 'POST only.'), $wants_json);
}

// --- CSRF -------------------------------------------------------------------

$token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf'] ?? '');
if (!csrf_check($token)) {
    form_respond(403, ['ok' => false, 'error' => 'Invalid CSRF token'], 'Security check failed', form_html_error('Security check failed', 'Please reload the page and try again.'), $wants_json);
}

// --- rate limit -------------------------------------------------------------

$ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
if ($ip !== '') {
    $window = (int)(getenv('FORM_RATE_PER_IP_WINDOW_SECONDS') ?: 3600);
    $max    = (int)(getenv('FORM_RATE_PER_IP_MAX') ?: 5);
    $since  = gmdate('Y-m-d H:i:s', time() - $window);
    $check  = db()->prepare(
        'SELECT COUNT(*) FROM form_submissions WHERE ip_address = :ip AND submitted_at >= :since'
    );
    $check->execute([':ip' => $ip, ':since' => $since]);
    if ((int) $check->fetchColumn() >= $max) {
        header('Retry-After: ' . $window);
        form_respond(429,
            ['ok' => false, 'error' => 'Too many submissions from this device. Try again later.'],
            'Slow down',
            form_html_error('Slow down', "You've submitted recently. Please try again later."),
            $wants_json);
    }
}

// --- resolve form -----------------------------------------------------------

$form_slug = trim((string)($_GET['form'] ?? $_POST['form'] ?? 'waitlist'));
if (!preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $form_slug)) {
    form_respond(400, ['ok' => false, 'error' => 'Invalid form'], 'Form error', form_html_error('Form error', 'Unknown form.'), $wants_json);
}
$form = form_by_slug($form_slug);
if ($form === null || $form['status'] !== 'active') {
    form_respond(404, ['ok' => false, 'error' => "Form '$form_slug' not found"], 'Form not found', form_html_error('Form not found', "We can't find a form named '$form_slug'."), $wants_json);
}

$settings = form_settings($form);
$success_heading = (string)($settings['success_heading'] ?? "Thanks");
$success_body    = (string)($settings['success_body']    ?? "We'll be in touch shortly.");

// --- honeypot ---------------------------------------------------------------

$honeypot = (string)($settings['honeypot'] ?? 'website');
if (trim((string)($_POST[$honeypot] ?? '')) !== '') {
    // Fake success — don't tip off the bot. No row inserted.
    form_respond(200, ['ok' => true, 'message' => $success_body], 'Thanks', form_html_success($success_heading, $success_body), $wants_json);
}

// --- validate ---------------------------------------------------------------

$result = form_validate($form, $_POST);
if (!$result['ok']) {
    form_respond(422,
        ['ok' => false, 'errors' => $result['errors']],
        'Please check your entry',
        form_html_error('Please check your entry', 'Some fields need attention.', $result['errors']),
        $wants_json);
}
$data = $result['data'];

// --- insert -----------------------------------------------------------------

$pdo = db();
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
$referrer   = $_SERVER['HTTP_REFERER']    ?? null;
$ip_addr    = $_SERVER['REMOTE_ADDR']     ?? null;

// v1 had fixed columns (full_name, email, phone, role, clients_managed,
// bottleneck). Populate them when the form's fields use those names so
// any v1 reader (CSV export, dashboard count) keeps working for the
// waitlist form. New forms put everything in data_json.
$legacy_cols = ['full_name','email','phone','role','clients_managed','bottleneck'];
$legacy_vals = [];
foreach ($legacy_cols as $col) {
    $legacy_vals[$col] = isset($data[$col]) && $data[$col] !== '' ? (string)$data[$col] : null;
}

$stmt = $pdo->prepare(
    'INSERT INTO form_submissions
       (form_id, data_json, full_name, email, phone, role, clients_managed, bottleneck,
        user_agent, referrer, ip_address)
     VALUES (:fid, :dj, :n, :e, :p, :r, :c, :b, :ua, :ref, :ip)'
);
$stmt->execute([
    ':fid' => (int)$form['id'],
    ':dj'  => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ':n'   => $legacy_vals['full_name']       ?? '',
    ':e'   => $legacy_vals['email']           ?? '',
    ':p'   => $legacy_vals['phone']           ?? '',
    ':r'   => $legacy_vals['role']            ?? '',
    ':c'   => $legacy_vals['clients_managed'],
    ':b'   => $legacy_vals['bottleneck'],
    ':ua'  => $user_agent,
    ':ref' => $referrer,
    ':ip'  => $ip_addr,
]);
$submission_id = (int)$pdo->lastInsertId();

// --- webhooks --------------------------------------------------------------

$meta = [
    'submission_id' => $submission_id,
    'form_slug'     => $form_slug,
    'form_name'     => $form['name'],
    'submitted_at'  => now_iso(),
    'user_agent'    => $user_agent,
    'referrer'      => $referrer,
    'ip_address'    => $ip_addr,
    'source'        => parse_url(GUA_APP_URL ?: 'site', PHP_URL_HOST) ?: 'site',
];

$webhooks = form_webhooks((int)$form['id'], true);
$overall_status = 'skipped';

if ($webhooks !== []) {
    foreach ($webhooks as $wh) {
        $template = $wh['payload_template_json']
            ? (json_decode($wh['payload_template_json'], true) ?: null)
            : null;
        $payload = form_resolve_payload($template, $data, $meta);

        $headers = [];
        if (!empty($wh['headers_json'])) {
            $decoded = json_decode($wh['headers_json'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $hk => $hv) $headers[] = $hk . ': ' . (string)$hv;
            }
        }
        // HMAC signature header when signing_secret is set
        if (!empty($wh['signing_secret'])) {
            $sig = hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), (string)$wh['signing_secret']);
            $headers[] = 'X-Webhook-Signature: sha256=' . $sig;
        }

        $result = webhook_post((string)$wh['url'], $payload, GUA_WEBHOOK_TIMEOUT_SECONDS, $headers, (string)$wh['method']);

        if ($result['ok']) {
            $status = 'sent';
        } elseif ($result['permanent']) {
            $status = 'failed';
        } else {
            // Queue with the legacy queue table; carry form_id + webhook_id for the admin inbox
            webhook_enqueue($pdo, $submission_id, (string)$wh['url'], $payload, 60);
            $pdo->prepare(
                'UPDATE webhook_deliveries SET form_id = :f, webhook_id = :w
                   WHERE submission_id = :s AND form_id IS NULL'
            )->execute([':f' => (int)$form['id'], ':w' => (int)$wh['id'], ':s' => $submission_id]);
            $status = 'queued';
        }

        // Update the per-submission rollup status: sent > queued > failed > skipped
        $overall_status = webhook_rollup_status($overall_status, $status);
    }
} elseif ($form_slug === 'waitlist' && GUA_WEBHOOK_URL !== '') {
    // Legacy fallback for the v1 hard-coded webhook URL when no
    // form_webhooks rows exist yet (admin hasn't migrated config).
    $payload = array_merge($data, ['_meta' => $meta]);
    $result = webhook_post(GUA_WEBHOOK_URL, $payload, GUA_WEBHOOK_TIMEOUT_SECONDS);
    if ($result['ok']) {
        $overall_status = 'sent';
    } elseif ($result['permanent']) {
        $overall_status = 'failed';
    } else {
        webhook_enqueue($pdo, $submission_id, GUA_WEBHOOK_URL, $payload, 60);
        $overall_status = 'queued';
    }
}

if ($overall_status !== 'skipped') {
    $pdo->prepare('UPDATE form_submissions SET webhook_status = :s WHERE id = :id')
        ->execute([':s' => $overall_status, ':id' => $submission_id]);
}

form_respond(200,
    ['ok' => true, 'message' => $success_body],
    'Thanks',
    form_html_success($success_heading, $success_body),
    $wants_json);

/** Combine per-webhook outcomes into one summary status. */
function webhook_rollup_status(string $current, string $new): string
{
    $rank = ['sent' => 4, 'queued' => 3, 'failed' => 2, 'skipped' => 1];
    return ($rank[$new] ?? 0) > ($rank[$current] ?? 0) ? $new : $current;
}
