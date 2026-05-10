<?php
// site/public/api/upload.php — admin-only file upload endpoint.
//
// POST multipart/form-data with `file` (the upload) and `csrf` (token,
// or X-CSRF-Token header). Returns:
//   { ok: true, id: N, url: "/uploads/...", filename, original_name,
//     mime_type, size_bytes, kind: "image"|"video" }
//
// Validation order (each step bails with a specific error so callers can
// surface useful messages):
//   1. Method, auth, CSRF
//   2. Upload error code (PHP-side limits)
//   3. MIME via finfo on the temp file (not $_FILES type — that's
//      client-supplied and can lie)
//   4. MIME ↔ extension match (against an allowlist)
//   5. Size cap by kind (GUA_MAX_IMAGE_BYTES / GUA_MAX_VIDEO_BYTES)
//   6. Generate a unique, safe stored filename and move into place
//   7. INSERT into media_assets
//
// Security notes:
// - SVG is allowed; admin-only auth is the protection. Future hardening
//   could strip <script>/event handlers from SVGs on upload.
// - The uploads/ .htaccess already disables PHP execution and blocks
//   common script extensions, so even an attacker-pleasing filename
//   can't get RCE through the directory.
// - Stored filename is generated server-side: original names never end
//   up on disk verbatim, which avoids path traversal and filesystem
//   case-sensitivity surprises.

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const GUA_UPLOAD_MIME_ALLOW = [
    'image/jpeg'    => ['ext' => ['jpg', 'jpeg'], 'kind' => 'image'],
    'image/png'     => ['ext' => ['png'],         'kind' => 'image'],
    'image/webp'    => ['ext' => ['webp'],        'kind' => 'image'],
    'image/gif'     => ['ext' => ['gif'],         'kind' => 'image'],
    'image/svg+xml' => ['ext' => ['svg'],         'kind' => 'image'],
    'video/mp4'     => ['ext' => ['mp4'],         'kind' => 'video'],
    'video/webm'    => ['ext' => ['webm'],        'kind' => 'video'],
];

function upload_fail(int $code, string $msg): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$user = auth_current_user();
if ($user === null) upload_fail(401, 'Not authenticated');

$token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf'] ?? '');
if (!csrf_check($token)) upload_fail(403, 'Invalid CSRF token');

if (!isset($_FILES['file'])) upload_fail(422, 'No file in request (expected field "file")');
$f = $_FILES['file'];

if (!is_array($f) || !isset($f['error'])) upload_fail(422, 'Malformed upload');
if ($f['error'] !== UPLOAD_ERR_OK) {
    $msg = match ((int)$f['error']) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds server upload size limit',
        UPLOAD_ERR_PARTIAL                       => 'Upload was interrupted; try again',
        UPLOAD_ERR_NO_FILE                       => 'No file received',
        UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'Server could not write to temp dir',
        UPLOAD_ERR_EXTENSION                     => 'A PHP extension blocked the upload',
        default                                  => 'Upload error code ' . $f['error'],
    };
    upload_fail(500, $msg);
}

if (!is_uploaded_file($f['tmp_name'])) upload_fail(400, 'Upload was not via HTTP POST');

// MIME from finfo (server-detected; ignore client-supplied $f['type']).
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = $finfo === false ? '' : (string) finfo_file($finfo, $f['tmp_name']);
if ($finfo !== false) finfo_close($finfo);
if ($mime === '') upload_fail(415, 'Could not detect MIME type');

if (!isset(GUA_UPLOAD_MIME_ALLOW[$mime])) {
    upload_fail(415, 'Unsupported file type: ' . $mime
        . '. Allowed: ' . implode(', ', array_keys(GUA_UPLOAD_MIME_ALLOW)));
}

$rule = GUA_UPLOAD_MIME_ALLOW[$mime];
$kind = $rule['kind'];

$ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
if ($ext === '' || !in_array($ext, $rule['ext'], true)) {
    upload_fail(415, "File extension '$ext' does not match detected MIME '$mime'");
}

$size = (int) $f['size'];
$cap  = $kind === 'image' ? GUA_MAX_IMAGE_BYTES : GUA_MAX_VIDEO_BYTES;
if ($size <= 0)   upload_fail(422, 'Empty file');
if ($size > $cap) upload_fail(413, sprintf('File too large: %d bytes (max %d for %s)', $size, $cap, $kind));

// Sanitise the original name for a hint — never used as the on-disk name.
$orig_clean = preg_replace('/[^a-zA-Z0-9._-]+/', '-', (string)$f['name']) ?? '';
$orig_clean = trim((string)$orig_clean, '-.');
if ($orig_clean === '') $orig_clean = $kind;
if (strlen($orig_clean) > 80) $orig_clean = substr($orig_clean, 0, 80);

// Stored filename: <unix>-<rand>-<safe-orig>.<ext>
$base    = pathinfo($orig_clean, PATHINFO_FILENAME);
$rand    = bin2hex(random_bytes(4));
$stored  = sprintf('%d-%s-%s.%s', time(), $rand, strtolower($base), $ext);
$dir     = GUA_SITE_PATH . '/public/uploads';
$dest    = $dir . '/' . $stored;

if (!is_dir($dir)) upload_fail(500, 'Uploads directory missing: ' . $dir);
if (!is_writable($dir)) upload_fail(500, 'Uploads directory not writable: ' . $dir);

if (!move_uploaded_file($f['tmp_name'], $dest)) {
    upload_fail(500, 'move_uploaded_file failed');
}
@chmod($dest, 0644);

try {
    $stmt = db()->prepare(
        'INSERT INTO media_assets (filename, original_name, mime_type, size_bytes, kind, uploaded_by)
         VALUES (:f, :o, :m, :s, :k, :u)'
    );
    $stmt->execute([
        ':f' => $stored,
        ':o' => (string)$f['name'],
        ':m' => $mime,
        ':s' => $size,
        ':k' => $kind,
        ':u' => (int)$user['id'],
    ]);
    $id = (int) db()->lastInsertId();
} catch (Throwable $e) {
    // Roll back the file write so DB and disk stay in sync.
    @unlink($dest);
    upload_fail(500, 'DB error: ' . $e->getMessage());
}

echo json_encode([
    'ok'            => true,
    'id'            => $id,
    'url'           => '/uploads/' . $stored,
    'filename'      => $stored,
    'original_name' => (string)$f['name'],
    'mime_type'     => $mime,
    'size_bytes'    => $size,
    'kind'          => $kind,
]);
