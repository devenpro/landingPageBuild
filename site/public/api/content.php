<?php
// site/public/api/content.php — admin-only UPSERT endpoint for content (v2 Stage 3).
//
// Backwards-compatible with the v1 inline editor which sends concatenated
// "<prefix>.<key>" strings. Internally it routes writes to the new tables:
//
//   keys matching "page.<page_slug>.<field>"  →  page_fields(page_id, field_key)
//   keys matching "<block_slug>.<field>"      →  content_block_fields(block_id, field_key)
//   bare keys (no dot)                         →  rejected (always had a dot in v1)
//
// Accepts:
//   - JSON body: {"key":"hero.headline","value":"new value","type":"text"}
//   - JSON body: {"changes":[{"key":"...","value":"...","type":"..."}, …]} for batch
//   - form-urlencoded equivalents (csrf=&key=&value=&type=)
//
// Auth: must be logged in admin
// CSRF: X-CSRF-Token header OR csrf form field
// Method: PATCH or POST (Apache shared hosts sometimes drop PATCH)
//
// Returns JSON {ok:true, applied:N, updated:N, inserted:N, updated_at:'...'}
// or {ok:false, error:'...'} with the appropriate HTTP status.

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'PATCH' && $method !== 'POST') {
    http_response_code(405);
    header('Allow: PATCH, POST');
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$user = auth_current_user();
if ($user === null) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

$token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf'] ?? '');
if (!csrf_check($token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Parse body — JSON if Content-Type indicates, else $_POST
$body = $_POST;
$ctype = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($ctype, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw === false ? '' : $raw, true);
    if (is_array($decoded)) {
        $body = $decoded;
    }
}

$VALID_TYPES = ['text', 'image', 'video', 'icon', 'list', 'seo', 'html'];

$normalize = function (array $c) use ($VALID_TYPES): ?array {
    if (!isset($c['key'])) return null;
    $type = isset($c['type']) ? (string) $c['type'] : 'text';
    if (!in_array($type, $VALID_TYPES, true)) {
        $type = 'text';
    }
    return [
        'key'   => (string) $c['key'],
        'value' => (string) ($c['value'] ?? ''),
        'type'  => $type,
    ];
};

$changes = [];
if (isset($body['key'])) {
    $c = $normalize($body);
    if ($c) $changes[] = $c;
} elseif (isset($body['changes']) && is_array($body['changes'])) {
    foreach ($body['changes'] as $entry) {
        if (is_array($entry)) {
            $c = $normalize($entry);
            if ($c) $changes[] = $c;
        }
    }
}

if ($changes === []) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No changes in payload']);
    exit;
}

$pdo = db();

// Pre-resolve page slugs once (so a single batch hits the DB at most once per slug).
$page_id_cache = [];
$lookup_page_id = function (string $slug) use ($pdo, &$page_id_cache): ?int {
    if (array_key_exists($slug, $page_id_cache)) {
        return $page_id_cache[$slug];
    }
    $stmt = $pdo->prepare('SELECT id FROM pages WHERE slug = :s LIMIT 1');
    $stmt->execute([':s' => $slug]);
    $id = $stmt->fetchColumn();
    return $page_id_cache[$slug] = $id === false ? null : (int) $id;
};

$find_or_create_block = function (string $slug) use ($pdo, $user): int {
    $stmt = $pdo->prepare('SELECT id FROM content_blocks WHERE slug = :s LIMIT 1');
    $stmt->execute([':s' => $slug]);
    $id = $stmt->fetchColumn();
    if ($id !== false) {
        return (int) $id;
    }
    $ins = $pdo->prepare(
        "INSERT INTO content_blocks (slug, name, category, status, created_at, updated_at, updated_by)
         VALUES (:s, :n, 'section', 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, :u)"
    );
    $ins->execute([
        ':s' => $slug,
        ':n' => ucwords(str_replace(['_', '-'], ' ', $slug)),
        ':u' => $user['id'],
    ]);
    return (int) $pdo->lastInsertId();
};

$pdo->beginTransaction();
$updated  = 0;
$inserted = 0;
try {
    $page_upsert = $pdo->prepare(
        'INSERT INTO page_fields (page_id, field_key, value, type, updated_at, updated_by)
         VALUES (:p, :k, :v, :t, CURRENT_TIMESTAMP, :u)
         ON CONFLICT(page_id, field_key) DO UPDATE SET
            value      = excluded.value,
            type       = excluded.type,
            updated_at = CURRENT_TIMESTAMP,
            updated_by = excluded.updated_by'
    );
    $page_existed = $pdo->prepare(
        'SELECT 1 FROM page_fields WHERE page_id = :p AND field_key = :k LIMIT 1'
    );

    $block_upsert = $pdo->prepare(
        'INSERT INTO content_block_fields (block_id, field_key, value, type, updated_at, updated_by)
         VALUES (:b, :k, :v, :t, CURRENT_TIMESTAMP, :u)
         ON CONFLICT(block_id, field_key) DO UPDATE SET
            value      = excluded.value,
            type       = excluded.type,
            updated_at = CURRENT_TIMESTAMP,
            updated_by = excluded.updated_by'
    );
    $block_existed = $pdo->prepare(
        'SELECT 1 FROM content_block_fields WHERE block_id = :b AND field_key = :k LIMIT 1'
    );

    foreach ($changes as $c) {
        $key = $c['key'];

        // Page-scoped: "page.<page_slug>.<field>"
        if (preg_match('/^page\.([a-z0-9](?:[a-z0-9\-]*[a-z0-9])?)\.(.+)$/', $key, $m)) {
            $page_slug = $m[1];
            $field_key = $m[2];
            $page_id = $lookup_page_id($page_slug);
            if ($page_id === null) {
                // Page doesn't exist — skip silently rather than 500 the whole batch.
                continue;
            }
            $page_existed->execute([':p' => $page_id, ':k' => $field_key]);
            $was_present = (bool) $page_existed->fetchColumn();
            $page_upsert->execute([
                ':p' => $page_id, ':k' => $field_key,
                ':v' => $c['value'], ':t' => $c['type'], ':u' => $user['id'],
            ]);
            if ($was_present) { $updated++; } else { $inserted++; }
            continue;
        }

        // Block-scoped: "<block_slug>.<field>" (field may itself contain dots)
        $dot = strpos($key, '.');
        if ($dot === false) {
            // v1 always used dotted keys. Reject bare keys defensively.
            continue;
        }
        $block_slug = substr($key, 0, $dot);
        $field_key  = substr($key, $dot + 1);
        $block_id   = $find_or_create_block($block_slug);

        $block_existed->execute([':b' => $block_id, ':k' => $field_key]);
        $was_present = (bool) $block_existed->fetchColumn();
        $block_upsert->execute([
            ':b' => $block_id, ':k' => $field_key,
            ':v' => $c['value'], ':t' => $c['type'], ':u' => $user['id'],
        ]);
        if ($was_present) { $updated++; } else { $inserted++; }
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB error: ' . $e->getMessage()]);
    exit;
}

// Return updated_at of the first key for the UI to refresh.
$updated_at = null;
$first = $changes[0]['key'];
if (preg_match('/^page\.([a-z0-9](?:[a-z0-9\-]*[a-z0-9])?)\.(.+)$/', $first, $m)) {
    $page_id = $lookup_page_id($m[1]);
    if ($page_id !== null) {
        $stmt = $pdo->prepare('SELECT updated_at FROM page_fields WHERE page_id = :p AND field_key = :k');
        $stmt->execute([':p' => $page_id, ':k' => $m[2]]);
        $updated_at = $stmt->fetchColumn() ?: null;
    }
} else {
    $dot = strpos($first, '.');
    if ($dot !== false) {
        $stmt = $pdo->prepare(
            'SELECT cbf.updated_at
               FROM content_block_fields cbf
               JOIN content_blocks cb ON cb.id = cbf.block_id
              WHERE cb.slug = :s AND cbf.field_key = :k'
        );
        $stmt->execute([':s' => substr($first, 0, $dot), ':k' => substr($first, $dot + 1)]);
        $updated_at = $stmt->fetchColumn() ?: null;
    }
}

echo json_encode([
    'ok'         => true,
    'applied'    => $updated + $inserted,
    'updated'    => $updated,
    'inserted'   => $inserted,
    'updated_at' => $updated_at,
]);
