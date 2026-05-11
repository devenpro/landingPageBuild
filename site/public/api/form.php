<?php
// site/public/api/form.php — public waitlist endpoint.
//
// 1. Negotiate response format (fetch/XHR -> JSON; plain form POST -> HTML)
// 2. CSRF check (session-bound token from core/lib/csrf.php) — runs first
//    because the HMAC verify is cheaper than the rate-limit DB hit, and we
//    don't want bots without a token to share quota with legitimate users
// 3. Per-IP rate limit (count form_submissions rows in rolling window)
// 4. Honeypot — fake success if filled (don't tip off the bot)
// 5. Server-side validation (don't trust client)
// 6. INSERT into form_submissions FIRST (so we never lose a lead)
// 7. Best-effort webhook POST (if WEBHOOK_URL set in .env)
// 8. UPDATE webhook_status / webhook_response on the row
// 9. Respond — JSON {ok:true} for fetch callers, full HTML page for plain
//    form POSTs (Phase 14 round B: graceful no-JS fallback)

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';

header('Cache-Control: no-store');

// --- response-format negotiation ---------------------------------------------
// JS in form.js sets X-Requested-With: fetch + Accept: application/json.
// Plain HTML form POST sends neither (browser sets Accept: text/html,...).
// Use that as the signal — no Accept header parsing gymnastics.
$wants_json = ((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch'
    || stripos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false
       && stripos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'text/html') === false;

/** Render the JSON or HTML response and exit. */
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

/** Minimal self-contained HTML page — no Tailwind, no JS, no external deps. */
function form_render_html_page(string $title, string $body_html, int $status): void
{
    $site   = e(GUA_SITE_NAME);
    $accent = $status >= 400 ? '#b91c1c' : '#7c3aed'; // red-700 / brand-600
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
  a.btn:hover{background:#6d28d9}
  a.link{color:#6b7280;text-decoration:underline}
</style>
</head>
<body>
  <div class="wrap"><div class="card">
    <div class="dot">{$body_html}
  </div></div>
  <noscript style="display:none"></noscript>
</body>
</html>
HTML;
}

/** Build the "success" inner HTML — opens after <div class="dot">. */
function form_html_success(): string
{
    $heading = e((string) c('form.success_heading', 'You\'re on the list'));
    $body    = e((string) c('form.success_body',    'Thanks — we\'ll be in touch shortly.'));
    $back    = e((GUA_APP_URL !== '' ? GUA_APP_URL : '') . '/');
    return <<<HTML
✓</div>
    <h1>{$heading}</h1>
    <p class="body">{$body}</p>
    <div class="actions"><a class="btn" href="{$back}">Back to home</a></div>
HTML;
}

/** Build the "error" inner HTML — opens after <div class="dot">. */
function form_html_error(string $title, string $message, array $field_errors = []): string
{
    $t = e($title);
    $m = e($message);
    $back = e((GUA_APP_URL !== '' ? GUA_APP_URL : '') . '/#waitlist');
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
    <div class="actions"><a class="btn" href="{$back}">Back to form</a></div>
HTML;
}

// --- request guard -----------------------------------------------------------

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    form_respond(
        405,
        ['ok' => false, 'error' => 'Method not allowed'],
        'Method not allowed',
        form_html_error('Method not allowed', 'This URL only accepts form submissions.'),
        $wants_json
    );
}

// --- CSRF --------------------------------------------------------------------

$token = (string)($_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!csrf_check($token)) {
    form_respond(
        403,
        ['ok' => false, 'error' => 'Session expired. Reload the page and try again.'],
        'Session expired',
        form_html_error('Session expired', 'Your session expired. Please reload the page and submit again.'),
        $wants_json
    );
}

// --- per-IP rate limit -------------------------------------------------------
// Count of form_submissions rows from this IP in a rolling window. No
// separate counter table — submissions are the counter. Defaults: 5
// submissions / IP / hour. .env overrides:
//   FORM_RATE_PER_IP_WINDOW_SECONDS=3600
//   FORM_RATE_PER_IP_MAX=5

$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
if ($ip !== '') {
    $window = (int) (getenv('FORM_RATE_PER_IP_WINDOW_SECONDS') ?: 3600);
    $max    = (int) (getenv('FORM_RATE_PER_IP_MAX')            ?: 5);
    $since  = gmdate('Y-m-d H:i:s', time() - $window);
    $check  = db()->prepare(
        'SELECT COUNT(*) FROM form_submissions WHERE ip_address = :ip AND submitted_at >= :since'
    );
    $check->execute([':ip' => $ip, ':since' => $since]);
    if ((int) $check->fetchColumn() >= $max) {
        header('Retry-After: ' . $window);
        form_respond(
            429,
            ['ok' => false, 'error' => 'Too many submissions from this device. Try again later.'],
            'Slow down',
            form_html_error('Slow down', 'You\'ve submitted this form several times recently. Please try again later.'),
            $wants_json
        );
    }
}

// --- honeypot ---------------------------------------------------------------

if (trim((string)($_POST['website'] ?? '')) !== '') {
    // Fake success — don't tip off the bot. No row inserted.
    form_respond(
        200,
        ['ok' => true, 'message' => 'Thanks — we\'ll be in touch.'],
        'Thanks',
        form_html_success(),
        $wants_json
    );
}

// --- validate ---------------------------------------------------------------

$full_name  = trim((string)($_POST['full_name'] ?? ''));
$email      = trim((string)($_POST['email'] ?? ''));
$phone      = trim((string)($_POST['phone'] ?? ''));
$role       = trim((string)($_POST['role'] ?? ''));
$clients    = trim((string)($_POST['clients_managed'] ?? ''));
$bottleneck = trim((string)($_POST['bottleneck'] ?? ''));

$errors = [];
if ($full_name === '' || mb_strlen($full_name) > 100) {
    $errors['full_name'] = 'Required, max 100 characters.';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
    $errors['email'] = 'Please enter a valid email address.';
}
if ($phone === '' || mb_strlen($phone) > 25) {
    $errors['phone'] = 'Required, max 25 characters.';
}
$role_options = c_list('form.role_options', ['Freelancer','Agency Owner','In-house Marketer','Other']);
if ($role === '' || !in_array($role, $role_options, true)) {
    $errors['role'] = 'Please pick one.';
}
$clients_options = c_list('form.clients_options', ['1–3','4–10','10+','Just exploring']);
if ($clients !== '' && !in_array($clients, $clients_options, true)) {
    $errors['clients_managed'] = 'Please pick one.';
}
if (mb_strlen($bottleneck) > 500) {
    $errors['bottleneck'] = 'Max 500 characters.';
}

if ($errors) {
    form_respond(
        422,
        ['ok' => false, 'errors' => $errors],
        'Please check your entry',
        form_html_error(
            'Please check your entry',
            'Some fields need attention before we can save your submission.',
            $errors
        ),
        $wants_json
    );
}

// --- insert (always) --------------------------------------------------------

$pdo = db();
$stmt = $pdo->prepare(
    'INSERT INTO form_submissions
       (full_name, email, phone, role, clients_managed, bottleneck,
        user_agent, referrer, ip_address)
     VALUES (:n, :e, :p, :r, :c, :b, :ua, :ref, :ip)'
);
$stmt->execute([
    ':n'   => $full_name,
    ':e'   => $email,
    ':p'   => $phone,
    ':r'   => $role,
    ':c'   => $clients !== ''    ? $clients    : null,
    ':b'   => $bottleneck !== '' ? $bottleneck : null,
    ':ua'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ':ref' => $_SERVER['HTTP_REFERER']    ?? null,
    ':ip'  => $_SERVER['REMOTE_ADDR']     ?? null,
]);
$submission_id = (int)$pdo->lastInsertId();

// --- best-effort webhook ----------------------------------------------------

$webhook_status   = 'skipped';
$webhook_response = null;
if (GUA_WEBHOOK_URL !== '') {
    $payload = [
        'full_name'       => $full_name,
        'email'           => $email,
        'phone'           => $phone,
        'role'            => $role,
        'clients_managed' => $clients !== ''    ? $clients    : null,
        'bottleneck'      => $bottleneck !== '' ? $bottleneck : null,
        'submitted_at'    => now_iso(),
        'source'          => 'go-ultra-ai-landing',
        'user_agent'      => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'referrer'        => $_SERVER['HTTP_REFERER']    ?? null,
    ];

    $ch = curl_init(GUA_WEBHOOK_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_safe($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => GUA_WEBHOOK_TIMEOUT_SECONDS,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_USERAGENT      => 'GoUltraAI-Webhook/1.0',
    ]);
    $response  = curl_exec($ch);
    $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err       = curl_error($ch);
    curl_close($ch);

    if ($response !== false && $http_code >= 200 && $http_code < 300) {
        $webhook_status   = 'sent';
        $webhook_response = mb_substr((string)$response, 0, 1000);
    } else {
        $webhook_status   = 'failed';
        $webhook_response = $err !== '' ? $err : "HTTP {$http_code}";
    }

    $upd = $pdo->prepare(
        'UPDATE form_submissions SET webhook_status = :s, webhook_response = :r WHERE id = :id'
    );
    $upd->execute([':s' => $webhook_status, ':r' => $webhook_response, ':id' => $submission_id]);
}

form_respond(
    200,
    ['ok' => true, 'message' => c('form.success_body', 'Thanks — we\'ll be in touch shortly.')],
    'Thanks',
    form_html_success(),
    $wants_json
);
