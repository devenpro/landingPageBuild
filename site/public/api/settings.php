<?php
// site/public/api/settings.php — admin-only save endpoint for site_settings.
//
// Accepts:
//   - form-urlencoded body: csrf=&tab=&settings[key1]=&settings[key2]=…
//   - the empty string for any key means "clear the DB value" (fall back
//     to .env / default on the next request)
//
// Auth: must be logged in admin
// CSRF: form 'csrf' field (the settings page is server-rendered, no JSON path)
// Method: POST only
//
// Redirects back to /admin/settings.php?tab=<tab>&saved=1 on success, or
// ?tab=<tab>&error=… on validation failure. No JSON path in Stage 1 — the
// settings page submits a normal form.

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo 'Method not allowed';
    exit;
}

$user = auth_current_user();
if ($user === null) {
    http_response_code(401);
    echo 'Not authenticated';
    exit;
}

$tab = (string) ($_POST['tab'] ?? '');
if ($tab === '') {
    $tab = 'general';
}

$back = '/admin/settings.php?tab=' . urlencode($tab);

$token = (string) ($_POST['csrf'] ?? '');
if (!csrf_check($token)) {
    header('Location: ' . $back . '&error=' . urlencode('Invalid CSRF token'));
    exit;
}

$incoming = $_POST['settings'] ?? [];
if (!is_array($incoming) || $incoming === []) {
    header('Location: ' . $back . '&saved=1');
    exit;
}

$pdo  = db();
$rows = settings_all_in_group($tab);
$by_key = [];
foreach ($rows as $r) {
    $by_key[$r['key']] = $r;
}

$saved = 0;
$errors = [];

foreach ($incoming as $key => $raw) {
    $key = (string) $key;
    if (!isset($by_key[$key])) {
        // Reject unknown keys — caller can only edit settings in the
        // tab they POSTed from.
        $errors[] = "Unknown setting: $key";
        continue;
    }

    $row = $by_key[$key];
    $value = is_string($raw) ? trim($raw) : (string) $raw;

    // Empty input → clear DB value (NULL), restoring .env / default fallback.
    if ($value === '') {
        settings_set($key, null, (int) $user['id']);
        $saved++;
        continue;
    }

    // Validate by type before persisting.
    $type = $row['value_type'];
    switch ($type) {
        case 'number':
            if (!is_numeric($value)) {
                $errors[] = "{$row['label']}: must be a number";
                continue 2;
            }
            break;
        case 'boolean':
            if ($value !== '0' && $value !== '1') {
                $errors[] = "{$row['label']}: must be '0' or '1'";
                continue 2;
            }
            break;
        case 'json':
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = "{$row['label']}: invalid JSON (" . json_last_error_msg() . ')';
                continue 2;
            }
            break;
    }

    settings_set($key, $value, (int) $user['id']);
    $saved++;
}

if ($errors !== []) {
    header('Location: ' . $back . '&error=' . urlencode(implode('; ', $errors)));
    exit;
}

header('Location: ' . $back . '&saved=1');
exit;
