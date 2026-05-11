<?php
// site/public/api/brand/sync.php — admin-only drift resolution endpoint (v2 Stage 2).
//
// POST body:
//   csrf=<token>
//   item_id=<int>
//   strategy=accept_disk | keep_db | manual
//   manual_body=<string>   (only when strategy=manual)
//
// Redirects back to /admin/brand-sync.php with ?saved=1 or ?error=...

declare(strict_types=1);

require __DIR__ . '/../../../../core/lib/bootstrap.php';
require_once __DIR__ . '/../../../../core/lib/brand/sync.php';

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

$back = '/admin/brand-sync.php';

$token = (string) ($_POST['csrf'] ?? '');
if (!csrf_check($token)) {
    header('Location: ' . $back . '?error=' . urlencode('Invalid CSRF token'));
    exit;
}

$item_id  = (int) ($_POST['item_id'] ?? 0);
$strategy = (string) ($_POST['strategy'] ?? '');
$manual_body = isset($_POST['manual_body']) ? (string)$_POST['manual_body'] : null;

try {
    brand_sync_pull($item_id, $strategy, $manual_body, (int)$user['id']);
    header('Location: ' . $back . '?saved=1');
    exit;
} catch (Throwable $e) {
    header('Location: ' . $back . '?error=' . urlencode($e->getMessage()));
    exit;
}
