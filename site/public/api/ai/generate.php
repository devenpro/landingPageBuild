<?php
// site/public/api/ai/generate.php — admin-only "generate page" endpoint.
//
// POST { brief: string, slug?: string, provider?: string }
//   → calls ai_chat() with the generate_page prompt
//   → parses + validates the JSON page
//   → inserts a draft pages row + page-scoped content_blocks rows in
//     a single transaction
//   → returns { ok: true, slug, page_id, suggestions, usage }
//
// On slug conflict (admin-supplied or AI-proposed), we suffix '-2',
// '-3', … until unique. Existing pages are NEVER overwritten silently.
//
// v1 supports three sections — hero, features, final_cta — matching
// the generate_page prompt schema. Adding sections later just means
// extending the GUA_GEN_SECTIONS table below.

declare(strict_types=1);

require __DIR__ . '/../../../../core/lib/bootstrap.php';
require_once __DIR__ . '/../../../../core/lib/ai/client.php';
require_once __DIR__ . '/../../../../core/lib/ai/prompts/generate_page.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$user = auth_current_user();
if ($user === null) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

$body = $_POST;
$ctype = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($ctype, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw === false ? '' : $raw, true);
    if (is_array($decoded)) {
        $body = $decoded;
    }
}

$token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $body['csrf'] ?? $_POST['csrf'] ?? '');
if (!csrf_check($token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$brief = trim((string)($body['brief'] ?? ''));
if ($brief === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Missing or empty brief']);
    exit;
}
if (strlen($brief) > 4000) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Brief too long (max 4000 chars)']);
    exit;
}

$slug_in  = trim((string)($body['slug'] ?? ''));
$slug_in  = ltrim($slug_in, '/');
if ($slug_in !== '' && !preg_match('#^[a-z0-9]+(?:-[a-z0-9]+)*(?:/[a-z0-9]+(?:-[a-z0-9]+)*)?$#', $slug_in)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Slug must be kebab-case (single nested segment OK)']);
    exit;
}

$provider = trim((string)($body['provider'] ?? ''));
if ($provider === '' || !in_array($provider, GUA_AI_PROVIDERS, true)) {
    $provider = ai_default_provider();
}

try {
    $result = ai_chat($provider, generate_page_messages($brief, $slug_in === '' ? null : $slug_in), [
        'caller'         => 'admin.generate_page',
        'skip_ratelimit' => true,
        'temperature'    => 0.6,
    ]);
} catch (Throwable $e) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}

try {
    $parsed = ai_parse_json((string)($result['text'] ?? ''));
} catch (RuntimeException $e) {
    http_response_code(502);
    echo json_encode([
        'ok'       => false,
        'error'    => $e->getMessage(),
        'raw_text' => $result['text'] ?? '',
    ]);
    exit;
}

if (!is_array($parsed)) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'Model response was not a JSON object']);
    exit;
}

// ----- Validate + normalise the parsed page --------------------------------

$slug = trim((string)($parsed['slug'] ?? $slug_in));
$slug = ltrim($slug, '/');
if ($slug === '' || $slug === 'home') {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'Model returned an invalid slug', 'raw' => $parsed]);
    exit;
}

$page_title       = trim((string)($parsed['title']           ?? ''));
$seo_title        = trim((string)($parsed['seo_title']       ?? $page_title));
$seo_description  = trim((string)($parsed['seo_description'] ?? ''));
$model_sections   = $parsed['sections'] ?? null;

if ($page_title === '' || !is_array($model_sections)) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'Model response missing title or sections', 'raw' => $parsed]);
    exit;
}

// Build page-scoped content_blocks (key => value) by translating each
// section to its partial's actual key convention.
$prefix = 'page.' . $slug;
$kv     = [];                 // key => value (string)
$kv_types = [];               // key => type ('text' default)

foreach ($model_sections as $sec) {
    if (!is_array($sec)) continue;
    $name = (string)($sec['name'] ?? '');
    $f    = is_array($sec['fields'] ?? null) ? $sec['fields'] : [];

    if ($name === 'hero') {
        foreach (['eyebrow', 'headline', 'subheadline', 'cta_label', 'cta_secondary_label'] as $k) {
            if (isset($f[$k])) {
                $kv["$prefix.hero.$k"]      = (string)$f[$k];
                $kv_types["$prefix.hero.$k"] = 'text';
            }
        }
    } elseif ($name === 'features') {
        foreach (['heading', 'subheading'] as $k) {
            if (isset($f[$k])) {
                $kv["$prefix.features.$k"]      = (string)$f[$k];
                $kv_types["$prefix.features.$k"] = 'text';
            }
        }
        $items = is_array($f['items'] ?? null) ? $f['items'] : [];
        $i = 0;
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $i++;
            if ($i > 6) break;
            // Note: site/sections/features.php uses singular 'feature.<i>.*'
            // for per-card keys (matching the partial's data-key attrs).
            if (isset($item['icon'])) {
                $kv["$prefix.feature.$i.icon"]      = (string)$item['icon'];
                $kv_types["$prefix.feature.$i.icon"] = 'icon';
            }
            if (isset($item['title'])) {
                $kv["$prefix.feature.$i.title"]      = (string)$item['title'];
                $kv_types["$prefix.feature.$i.title"] = 'text';
            }
            if (isset($item['body'])) {
                $kv["$prefix.feature.$i.body"]      = (string)$item['body'];
                $kv_types["$prefix.feature.$i.body"] = 'text';
            }
        }
    } elseif ($name === 'final_cta') {
        foreach (['heading', 'subheading'] as $k) {
            if (isset($f[$k])) {
                $kv["$prefix.final_cta.$k"]      = (string)$f[$k];
                $kv_types["$prefix.final_cta.$k"] = 'text';
            }
        }
    }
    // Unknown section names are skipped silently — extending support is a
    // matter of adding another branch here.
}

if ($kv === []) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'Model response had no usable section content', 'raw' => $parsed]);
    exit;
}

// ----- Insert page + content_blocks in a transaction -----------------------

$pdo = db();

// Resolve slug uniqueness (suffix '-2', '-3', … if needed).
$base = $slug;
$try  = 0;
$check = $pdo->prepare('SELECT 1 FROM pages WHERE slug = :s LIMIT 1');
while (true) {
    $check->execute([':s' => $slug]);
    if ($check->fetchColumn() === false) break;
    $try++;
    $slug = $base . '-' . ($try + 1);
    // Update prefix and keys when slug changes
    if ($try === 1) {
        $new_prefix = 'page.' . $slug;
        $renamed = [];
        $renamed_types = [];
        foreach ($kv as $k => $v) {
            $renamed[$new_prefix . substr($k, strlen($prefix))] = $v;
        }
        foreach ($kv_types as $k => $v) {
            $renamed_types[$new_prefix . substr($k, strlen($prefix))] = $v;
        }
        $kv = $renamed;
        $kv_types = $renamed_types;
        $prefix = $new_prefix;
    } else {
        $new_prefix = 'page.' . $slug;
        $renamed = [];
        $renamed_types = [];
        foreach ($kv as $k => $v) {
            // Strip old prefix (which has the previous '-N') and rebuild.
            $tail = substr($k, strpos($k, '.', strlen('page.')) + 1);
            $renamed[$new_prefix . '.' . $tail] = $v;
            $renamed_types[$new_prefix . '.' . $tail] = $kv_types[$k] ?? 'text';
        }
        $kv = $renamed;
        $kv_types = $renamed_types;
        $prefix = $new_prefix;
    }
    if ($try > 50) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not find an unused slug after 50 attempts']);
        exit;
    }
}

$sections_json = json_encode([
    ['section' => 'hero'],
    ['section' => 'features'],
    ['section' => 'final_cta'],
]);

$meta_json = json_encode([
    'generated_by'   => 'ai',
    'generator'      => 'admin.generate_page',
    'provider'       => $provider,
    'model'          => $result['model']      ?? null,
    'tokens_in'      => $result['tokens_in']  ?? 0,
    'tokens_out'     => $result['tokens_out'] ?? 0,
    'brief_excerpt'  => mb_substr($brief, 0, 280),
    'generated_at'   => gmdate('Y-m-d H:i:s'),
    'admin_user_id'  => (int)$user['id'],
]);

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO pages (slug, title, status, is_file_based, sections_json, meta_json, seo_title, seo_description)
         VALUES (:slug, :title, "draft", 0, :sj, :mj, :st, :sd)'
    );
    $stmt->execute([
        ':slug'  => $slug,
        ':title' => $page_title,
        ':sj'    => $sections_json,
        ':mj'    => $meta_json,
        ':st'    => $seo_title,
        ':sd'    => $seo_description,
    ]);
    $page_id = (int)$pdo->lastInsertId();

    // v2 Stage 3: page-scoped content now lives in page_fields, not the
    // renamed content_blocks. Strip the "page.<slug>." prefix from each
    // built key and write to page_fields(page_id, field_key, value, type).
    $upsert = $pdo->prepare(
        'INSERT INTO page_fields (page_id, field_key, value, type, updated_at, updated_by)
         VALUES (:p, :k, :v, :t, CURRENT_TIMESTAMP, :u)
         ON CONFLICT(page_id, field_key) DO UPDATE SET
            value      = excluded.value,
            type       = excluded.type,
            updated_at = CURRENT_TIMESTAMP,
            updated_by = excluded.updated_by'
    );
    $prefix_len = strlen($prefix) + 1; // "page.<slug>" + "."
    foreach ($kv as $k => $v) {
        $upsert->execute([
            ':p' => $page_id,
            ':k' => substr($k, $prefix_len),
            ':v' => $v,
            ':t' => $kv_types[$k] ?? 'text',
            ':u' => (int)$user['id'],
        ]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB error: ' . $e->getMessage()]);
    exit;
}

echo json_encode([
    'ok'        => true,
    'slug'      => $slug,
    'page_id'   => $page_id,
    'keys_written' => count($kv),
    'usage' => [
        'provider'   => $provider,
        'model'      => $result['model']      ?? null,
        'tokens_in'  => $result['tokens_in']  ?? 0,
        'tokens_out' => $result['tokens_out'] ?? 0,
    ],
]);
