<?php
// site/public/admin/logout.php — POST-only logout. Validates CSRF, then
// destroys the session and redirects to /. GET requests are rejected so
// a forged image tag or link from another origin can't sign the admin out.

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo 'POST only';
    exit;
}

$token = (string) ($_POST['csrf'] ?? '');
if (!csrf_check($token)) {
    http_response_code(403);
    echo 'Invalid CSRF token';
    exit;
}

auth_logout();
header('Location: /', true, 302);
exit;
