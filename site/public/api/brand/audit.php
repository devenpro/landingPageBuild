<?php
// site/public/api/brand/audit.php — admin-only audit JSON (v2 Stage 2).
//
// GET only. Returns the brand_audit() result as JSON. Used by the dashboard
// banner (Stage 2 inserts a quick visibility row) and by the Stage 10
// bootstrap wizard to gate progression on minimum BCL completeness.

declare(strict_types=1);

require __DIR__ . '/../../../../core/lib/bootstrap.php';
require_once __DIR__ . '/../../../../core/lib/brand/audit.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$user = auth_current_user();
if ($user === null) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

echo json_encode(['ok' => true, 'audit' => brand_audit()]);
