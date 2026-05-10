<?php
// site/public/api/form.php — public waitlist endpoint.
//
// 1. CSRF check (session-bound token from core/lib/csrf.php)
// 2. Honeypot — fake success if filled (don't tip off the bot)
// 3. Server-side validation (don't trust client)
// 4. INSERT into form_submissions FIRST (so we never lose a lead)
// 5. Best-effort webhook POST (if WEBHOOK_URL set in .env)
// 6. UPDATE webhook_status / webhook_response on the row
// 7. Return JSON {ok:true} regardless of webhook outcome (we have the lead)

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// CSRF — accept token from form field or X-CSRF-Token header
$token = (string)($_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!csrf_check($token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Session expired. Reload the page and try again.']);
    exit;
}

// Honeypot — fake success
if (trim((string)($_POST['website'] ?? '')) !== '') {
    echo json_encode(['ok' => true, 'message' => 'Thanks — we\'ll be in touch.']);
    exit;
}

// Validate
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
    http_response_code(422);
    echo json_encode(['ok' => false, 'errors' => $errors]);
    exit;
}

// Insert immediately so we never lose a lead, even if webhook fails
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

// Best-effort webhook
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

echo json_encode([
    'ok'      => true,
    'message' => c('form.success_body', 'Thanks — we\'ll be in touch shortly.'),
]);
