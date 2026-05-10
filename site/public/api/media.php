<?php
// site/public/api/media.php — admin-only media library list + delete.
//
// GET    → { ok, items: [{id, url, filename, original_name, mime_type,
//                         size_bytes, kind, uploaded_at}], total }
//   Optional ?kind=image|video filter, ?limit=N (default 200, max 500).
// DELETE { id } → { ok } — removes the row AND the file from
//   site/public/uploads/. We never leave orphaned files: DB delete and
//   filesystem unlink happen together; if either fails, callers see a
//   500 with a specific message.
//
// Uploads happen via /api/upload.php (separate endpoint because
// multipart parsing is its own concern).

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (auth_current_user() === null) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $kind  = (string)($_GET['kind']  ?? '');
    $limit = (int)   ($_GET['limit'] ?? 200);
    if ($limit < 1)   $limit = 200;
    if ($limit > 500) $limit = 500;

    if ($kind !== '' && !in_array($kind, ['image', 'video'], true)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Invalid kind']);
        exit;
    }

    $sql = 'SELECT id, filename, original_name, mime_type, size_bytes, kind, uploaded_at
            FROM media_assets';
    $params = [];
    if ($kind !== '') {
        $sql .= ' WHERE kind = :k';
        $params[':k'] = $kind;
    }
    $sql .= ' ORDER BY uploaded_at DESC, id DESC LIMIT :limit';

    $stmt = db()->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $r['url']        = '/uploads/' . $r['filename'];
        $r['size_bytes'] = (int) $r['size_bytes'];
        $r['id']         = (int) $r['id'];
    }
    unset($r);

    if ($kind === '') {
        $total = (int) db()->query('SELECT COUNT(*) FROM media_assets')->fetchColumn();
    } else {
        $stmt = db()->prepare('SELECT COUNT(*) FROM media_assets WHERE kind = :k');
        $stmt->execute([':k' => $kind]);
        $total = (int) $stmt->fetchColumn();
    }

    echo json_encode(['ok' => true, 'items' => $rows, 'total' => $total]);
    exit;
}

if ($method === 'DELETE') {
    // Body parsing: JSON or form-urlencoded; PHP doesn't auto-populate
    // $_POST for DELETE bodies.
    $body = $_POST;
    $ctype = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ctype, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw === false ? '' : $raw, true);
        if (is_array($decoded)) $body = $decoded;
    } elseif (empty($body)) {
        $raw = file_get_contents('php://input');
        if ($raw !== false && $raw !== '') parse_str($raw, $body);
    }

    $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $body['csrf'] ?? $_POST['csrf'] ?? '');
    if (!csrf_check($token)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $id = (int)($body['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Missing id']);
        exit;
    }

    $stmt = db()->prepare('SELECT filename FROM media_assets WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $filename = $stmt->fetchColumn();
    if ($filename === false) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'No media with that id']);
        exit;
    }

    $path = GUA_SITE_PATH . '/public/uploads/' . $filename;
    // realpath check — defence in depth against a stored filename that
    // somehow contains '..' (it shouldn't; upload.php sanitises) but
    // we never want to delete outside the uploads dir.
    $uploads_dir = realpath(GUA_SITE_PATH . '/public/uploads');
    $real        = realpath($path);
    if ($uploads_dir === false) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'uploads dir unresolved']);
        exit;
    }
    if ($real !== false && !str_starts_with($real, $uploads_dir . DIRECTORY_SEPARATOR)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'refusing to delete outside uploads dir']);
        exit;
    }

    db()->prepare('DELETE FROM media_assets WHERE id = :id')->execute([':id' => $id]);

    if ($real !== false) {
        @unlink($real); // best-effort; row is already gone, file might be missing
    }

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
header('Allow: GET, DELETE');
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
