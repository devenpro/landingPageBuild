<?php
// site/public/api/bootstrap.php — site-bootstrap wizard backend (v2 Stage 10).
//
// POST { action: 'save_identity', site_name, app_url, admin_email }
//                                       → upsert 3 site_settings rows
// POST { action: 'mark_seen' }          → stamp bootstrap_started_at if not set
// POST { action: 'brand_fill', brief }  → AI-fill every missing-required brand
//                                          item using brand_item_generate_messages.
//                                          Items land with source='ai', ai_reviewed=0
//                                          so the admin reviews them via /admin/brand.php
// POST { action: 'complete' }           → flip bootstrap_completed=1
//
// Auth: admin login required. CSRF: X-CSRF-Token header or `csrf` body field.
// Body: JSON or form-urlencoded — both accepted (matches /api/content.php).
// Response: { ok: true, … } or { ok: false, error } with sensible HTTP codes.

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';
require_once __DIR__ . '/../../../core/lib/settings.php';
require_once __DIR__ . '/../../../core/lib/ai/client.php';
require_once __DIR__ . '/../../../core/lib/ai/keys.php';
require_once __DIR__ . '/../../../core/lib/brand/audit.php';
require_once __DIR__ . '/../../../core/lib/brand/items.php';
require_once __DIR__ . '/../../../core/lib/brand/categories.php';
require_once __DIR__ . '/../../../core/lib/ai/prompts/brand_item_generate.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
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
    $raw     = file_get_contents('php://input');
    $decoded = json_decode($raw === false ? '' : $raw, true);
    if (is_array($decoded)) $body = $decoded;
}
$token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $body['csrf'] ?? $_POST['csrf'] ?? '');
if (!csrf_check($token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$action = (string)($body['action'] ?? '');

try {
    switch ($action) {
        case 'mark_seen':
            $existing = (string) settings_get('bootstrap_started_at', '');
            if ($existing === '') {
                settings_set('bootstrap_started_at', gmdate('Y-m-d H:i:s'), (int)$user['id']);
            }
            echo json_encode(['ok' => true]);
            exit;

        case 'save_identity':
            $errors = [];
            $site_name   = trim((string)($body['site_name']   ?? ''));
            $app_url     = trim((string)($body['app_url']     ?? ''));
            $admin_email = trim((string)($body['admin_email'] ?? ''));

            if ($site_name === '') $errors['site_name'] = 'Site name is required';
            if ($app_url   !== '' && !preg_match('#^https?://#i', $app_url)) {
                $errors['app_url'] = 'App URL must start with http:// or https://';
            }
            if ($admin_email !== '' && !filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
                $errors['admin_email'] = 'Admin email is invalid';
            }
            if ($errors) {
                http_response_code(422);
                echo json_encode(['ok' => false, 'error' => 'Validation failed', 'errors' => $errors]);
                exit;
            }

            settings_set('site_name',   $site_name,   (int)$user['id']);
            if ($app_url !== '')     settings_set('app_url',     $app_url,     (int)$user['id']);
            if ($admin_email !== '') settings_set('admin_email', $admin_email, (int)$user['id']);
            echo json_encode(['ok' => true]);
            exit;

        case 'brand_fill':
            $brief = trim((string)($body['brief'] ?? ''));
            if (strlen($brief) < 30) {
                http_response_code(422);
                echo json_encode(['ok' => false, 'error' => 'Brief must be at least 30 characters so the AI has something to work with']);
                exit;
            }

            $provider = ai_default_provider();
            if (ai_keys_get($provider) === null) {
                http_response_code(409);
                echo json_encode([
                    'ok'    => false,
                    'error' => "No API key on file for default provider '$provider'. Add one at /admin/ai-keys.php first.",
                ]);
                exit;
            }

            // Discover the targets: every brand_items row in a required
            // category whose body is empty. These correspond to the seed
            // placeholders Stage 2 inserts (one per category).
            $rows = db()->query(
                "SELECT i.id, i.title, i.slug, c.slug AS category_slug, c.label AS category_label
                   FROM brand_items i
                   JOIN brand_categories c ON c.id = i.category_id
                  WHERE c.required = 1
                    AND i.status   = 'active'
                    AND length(i.body) = 0
                  ORDER BY c.sort_order, c.slug"
            )->fetchAll();

            $filled = [];
            $errors = [];
            foreach ($rows as $r) {
                try {
                    $messages = brand_item_generate_messages(
                        (string)$r['category_slug'],
                        (string)$r['category_label'],
                        $brief
                    );
                    $result = ai_chat($provider, $messages, [
                        'caller'         => 'admin.bootstrap.brand_fill',
                        'skip_ratelimit' => true,
                        'max_tokens'     => 2000,
                    ]);
                    $parsed = ai_parse_json($result['text'] ?? '');
                    if (!is_array($parsed) || !isset($parsed['body']) || trim((string)$parsed['body']) === '') {
                        throw new RuntimeException('AI returned no body');
                    }
                    $new_title = trim((string)($parsed['title'] ?? $r['title'])) ?: (string)$r['title'];
                    brand_item_update((int)$r['id'], [
                        'title'       => $new_title,
                        'body'        => (string)$parsed['body'],
                        'source'      => 'ai',
                        'ai_reviewed' => 0,
                        'source_meta' => [
                            'caller'   => 'bootstrap_brand_fill',
                            'provider' => $provider,
                            'model'    => $result['model'] ?? null,
                            'tokens'   => [
                                'in'  => (int)($result['tokens_in']  ?? 0),
                                'out' => (int)($result['tokens_out'] ?? 0),
                            ],
                            'briefed'  => mb_substr($brief, 0, 200),
                        ],
                    ], (int)$user['id']);
                    $filled[] = [
                        'category' => $r['category_slug'],
                        'slug'     => $r['slug'],
                        'title'    => $new_title,
                    ];
                } catch (Throwable $e) {
                    $errors[] = [
                        'category' => $r['category_slug'],
                        'slug'     => $r['slug'],
                        'error'    => substr($e->getMessage(), 0, 300),
                    ];
                }
            }
            echo json_encode([
                'ok'         => true,
                'filled'     => $filled,
                'errors'     => $errors,
                'attempted'  => count($rows),
            ]);
            exit;

        case 'complete':
            settings_set('bootstrap_completed', '1', (int)$user['id']);
            echo json_encode(['ok' => true]);
            exit;

        default:
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    }
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
} catch (RuntimeException $e) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
