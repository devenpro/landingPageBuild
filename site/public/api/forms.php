<?php
// site/public/api/forms.php — admin-only CRUD for forms / fields / webhooks (v2 Stage 6).
//
// Form-driven. $_POST['action'] selects:
//   create_form / save_settings / delete_form
//   add_field / save_field / delete_field
//   add_webhook / save_webhook / delete_webhook
//
// Redirects back to /admin/forms.php (or ?form=<id>&tab=…) with
// ?saved=1 / ?created=1 / ?error=…

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';
require_once __DIR__ . '/../../../core/lib/forms.php';

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

$token = (string)($_POST['csrf'] ?? '');
if (!csrf_check($token)) {
    header('Location: /admin/forms.php?error=' . urlencode('Invalid CSRF token'));
    exit;
}

$action = (string)($_POST['action'] ?? '');
$form_id = (int)($_POST['form_id'] ?? $_POST['id'] ?? 0);

function forms_back(int $form_id, string $tab = '', string $qs = ''): string
{
    if ($form_id <= 0) return '/admin/forms.php' . ($qs !== '' ? '?' . $qs : '');
    $u = '/admin/forms.php?form=' . $form_id;
    if ($tab !== '') $u .= '&tab=' . urlencode($tab);
    if ($qs !== '') $u .= '&' . $qs;
    return $u;
}

try {
    switch ($action) {
        case 'create_form': {
            $id = form_create([
                'slug'        => trim((string)($_POST['slug'] ?? '')),
                'name'        => trim((string)($_POST['name'] ?? '')),
                'description' => trim((string)($_POST['description'] ?? '')),
                'status'      => 'active',
                'settings'    => ['success_heading' => 'Thanks', 'success_body' => "We'll be in touch shortly.", 'honeypot' => 'website'],
            ], (int)$user['id']);
            header('Location: ' . forms_back($id, 'fields', 'created=1'));
            exit;
        }

        case 'save_settings': {
            $settings = [
                'success_heading'    => trim((string)($_POST['success_heading']    ?? '')),
                'success_body'       => trim((string)($_POST['success_body']       ?? '')),
                'redirect_url'       => trim((string)($_POST['redirect_url']       ?? '')) ?: null,
                'notification_email' => trim((string)($_POST['notification_email'] ?? '')) ?: null,
                'honeypot'           => trim((string)($_POST['honeypot']           ?? 'website')),
            ];
            form_update($form_id, [
                'name'        => trim((string)($_POST['name'] ?? '')),
                'description' => trim((string)($_POST['description'] ?? '')),
                'status'      => (string)($_POST['status'] ?? 'active'),
                'settings'    => $settings,
            ], (int)$user['id']);
            header('Location: ' . forms_back($form_id, 'settings', 'saved=1'));
            exit;
        }

        case 'delete_form': {
            form_delete($form_id);
            header('Location: /admin/forms.php?saved=1');
            exit;
        }

        case 'add_field': {
            form_field_create($form_id, [
                'name'  => trim((string)($_POST['name'] ?? '')),
                'label' => trim((string)($_POST['label'] ?? '')),
                'type'  => (string)($_POST['type'] ?? 'text'),
            ]);
            header('Location: ' . forms_back($form_id, 'fields', 'saved=1'));
            exit;
        }

        case 'save_field': {
            $field_id = (int)($_POST['field_id'] ?? 0);
            form_field_update($field_id, [
                'label'           => trim((string)($_POST['label'] ?? '')),
                'type'            => (string)($_POST['type'] ?? 'text'),
                'placeholder'     => trim((string)($_POST['placeholder'] ?? '')) ?: null,
                'default_value'   => trim((string)($_POST['default_value'] ?? '')) ?: null,
                'required'        => isset($_POST['required']) && $_POST['required'] === '1',
                'options_json'    => trim((string)($_POST['options_json'] ?? '')) ?: null,
                'validation_json' => trim((string)($_POST['validation_json'] ?? '')) ?: null,
                'help_text'       => trim((string)($_POST['help_text'] ?? '')) ?: null,
                'position'        => (int)($_POST['position'] ?? 0),
            ]);
            header('Location: ' . forms_back($form_id, 'fields', 'saved=1'));
            exit;
        }

        case 'delete_field': {
            $field_id = (int)($_POST['field_id'] ?? 0);
            form_field_delete($field_id);
            header('Location: ' . forms_back($form_id, 'fields', 'saved=1'));
            exit;
        }

        case 'add_webhook': {
            form_webhook_create($form_id, [
                'name' => trim((string)($_POST['name'] ?? 'Webhook')),
                'url'  => trim((string)($_POST['url']  ?? '')),
            ]);
            header('Location: ' . forms_back($form_id, 'webhooks', 'saved=1'));
            exit;
        }

        case 'save_webhook': {
            $webhook_id = (int)($_POST['webhook_id'] ?? 0);
            form_webhook_update($webhook_id, [
                'name'                  => trim((string)($_POST['name'] ?? 'Webhook')),
                'url'                   => trim((string)($_POST['url'] ?? '')),
                'method'                => (string)($_POST['method'] ?? 'POST'),
                'headers_json'          => trim((string)($_POST['headers_json'] ?? '')) ?: null,
                'payload_template_json' => trim((string)($_POST['payload_template_json'] ?? '')) ?: null,
                'signing_secret'        => trim((string)($_POST['signing_secret'] ?? '')) ?: null,
                'enabled'               => isset($_POST['enabled']) && $_POST['enabled'] === '1',
            ]);
            header('Location: ' . forms_back($form_id, 'webhooks', 'saved=1'));
            exit;
        }

        case 'delete_webhook': {
            $webhook_id = (int)($_POST['webhook_id'] ?? 0);
            form_webhook_delete($webhook_id);
            header('Location: ' . forms_back($form_id, 'webhooks', 'saved=1'));
            exit;
        }

        default:
            header('Location: ' . forms_back($form_id, '', 'error=' . urlencode("Unknown action '$action'")));
            exit;
    }
} catch (Throwable $e) {
    header('Location: ' . forms_back($form_id, '', 'error=' . urlencode($e->getMessage())));
    exit;
}
