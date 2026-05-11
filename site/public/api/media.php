<?php
// site/public/api/media.php — admin-only media library list + update + delete.
//
// GET    → { ok, items: [...], total }
//   Each item has the v1 fields (id/url/filename/original_name/mime_type/
//   size_bytes/kind/uploaded_at) plus the v2 Stage 7 fields:
//     alt_text, caption, original_width, original_height, processed,
//     processing_error, variants: [{preset_name, width, height,
//                                   mime_type, url, size_bytes}]
//   Optional ?kind=image|video filter, ?limit=N (default 200, max 500).
//
// PATCH { id, alt_text?, caption? } → { ok }
//   Update accessibility / library metadata. Alt text updates propagate
//   to every page referencing this media (callers read alt_text from
//   media_assets, not from a hard-coded string).
//
// DELETE { id } → { ok }
//   Removes the row, the upload file, and every variant file/row. CSRF
//   required for both PATCH and DELETE.
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

    $sql = 'SELECT id, filename, original_name, mime_type, size_bytes, kind, uploaded_at,
                   alt_text, caption, original_width, original_height,
                   processed, processing_error
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

    // Variants — one query for all assets in this listing.
    $variants_by_media = [];
    if ($rows !== []) {
        $ids = array_map(fn($r) => (int)$r['id'], $rows);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $vstmt = db()->prepare(
            "SELECT id, media_id, preset_name, width, height, mime_type, path, size_bytes
               FROM media_variants WHERE media_id IN ($placeholders)
              ORDER BY media_id, width, mime_type"
        );
        $vstmt->execute($ids);
        foreach ($vstmt as $v) {
            $variants_by_media[(int)$v['media_id']][] = [
                'preset_name' => $v['preset_name'],
                'width'       => (int)$v['width'],
                'height'      => (int)$v['height'],
                'mime_type'   => $v['mime_type'],
                'url'         => '/' . preg_replace('#^site/public/#', '', (string)$v['path']),
                'size_bytes'  => (int)$v['size_bytes'],
            ];
        }
    }

    foreach ($rows as &$r) {
        $r['url']        = '/uploads/' . $r['filename'];
        $r['size_bytes'] = (int) $r['size_bytes'];
        $r['id']         = (int) $r['id'];
        $r['processed']  = (int) $r['processed'];
        $r['original_width']  = $r['original_width']  !== null ? (int)$r['original_width']  : null;
        $r['original_height'] = $r['original_height'] !== null ? (int)$r['original_height'] : null;
        $r['variants']   = $variants_by_media[(int)$r['id']] ?? [];
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

if ($method === 'PATCH' || ($method === 'POST' && (string)($_GET['_method'] ?? '') === 'PATCH')) {
    // v2 Stage 7: update alt_text / caption / re-trigger processing.
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

    $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $body['csrf'] ?? $_POST['csrf'] ?? '');
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

    $fields = [];
    $params = [':id' => $id];
    if (array_key_exists('alt_text', $body)) {
        $fields[] = 'alt_text = :a';
        $params[':a'] = trim((string)$body['alt_text']) ?: null;
    }
    if (array_key_exists('caption', $body)) {
        $fields[] = 'caption = :c';
        $params[':c'] = trim((string)$body['caption']) ?: null;
    }
    if ($fields === []) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'No fields to update']);
        exit;
    }

    db()->prepare('UPDATE media_assets SET ' . implode(', ', $fields) . ' WHERE id = :id')
        ->execute($params);

    // Optional re-process trigger
    if (!empty($body['reprocess'])) {
        require_once __DIR__ . '/../../../core/lib/media/processor.php';
        media_process($id);
    }

    echo json_encode(['ok' => true]);
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

    // v2 Stage 7: collect variant file paths BEFORE the cascade fires (FK
    // ON DELETE CASCADE will drop the media_variants rows, so we can't
    // read them after the DELETE).
    $variant_paths = [];
    $vstmt = db()->prepare('SELECT path FROM media_variants WHERE media_id = :id');
    $vstmt->execute([':id' => $id]);
    while ($p = $vstmt->fetchColumn()) {
        $variant_paths[] = GUA_PROJECT_ROOT . '/' . $p;
    }

    db()->prepare('DELETE FROM media_assets WHERE id = :id')->execute([':id' => $id]);

    if ($real !== false) {
        @unlink($real); // best-effort; row is already gone, file might be missing
    }
    foreach ($variant_paths as $vp) {
        @unlink($vp);
    }
    // Drop the variants directory if empty
    $variants_dir = GUA_SITE_PATH . '/public/uploads/variants/' . $id;
    if (is_dir($variants_dir)) {
        @rmdir($variants_dir);
    }

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
header('Allow: GET, PATCH, DELETE');
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
