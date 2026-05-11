<?php
// core/scripts/test_stage_6.php — smoke test for v2 Stage 6 (Forms builder).
//
// Exercises:
//   1. Schema (forms, form_fields, form_webhooks)
//   2. Seed (waitlist form #1 with 6 fields)
//   3. Form CRUD with slug validation
//   4. Field CRUD with type validation
//   5. Webhook CRUD with method clamping
//   6. form_validate accepts good input, rejects bad
//   7. form_resolve_payload substitutes {{field}} and {{meta.X}} placeholders
//   8. form_submission_count

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/forms.php';

$failures = [];
$assert = function (string $name, $expected, $actual) use (&$failures): void {
    if ($expected === $actual) {
        echo "  ✓ $name\n";
    } else {
        echo "  ✗ $name\n";
        echo "      expected: " . var_export($expected, true) . "\n";
        echo "      actual:   " . var_export($actual, true) . "\n";
        $failures[] = $name;
    }
};
$assert_true = function (string $name, $cond) use (&$failures): void {
    if ($cond) {
        echo "  ✓ $name\n";
    } else {
        echo "  ✗ $name (expected truthy)\n";
        $failures[] = $name;
    }
};

echo "Stage 6 smoke test\n";
echo "------------------\n";

$pdo = db();

// 1. Schema
foreach (['forms', 'form_fields', 'form_webhooks'] as $t) {
    $exists = (bool) $pdo->query(
        "SELECT 1 FROM sqlite_master WHERE type='table' AND name='" . $t . "' LIMIT 1"
    )->fetchColumn();
    $assert("table $t exists", true, $exists);
}

// form_submissions gained columns
$cols = $pdo->query("PRAGMA table_info(form_submissions)")->fetchAll();
$col_names = array_column($cols, 'name');
$assert('form_submissions has form_id',   true, in_array('form_id',   $col_names, true));
$assert('form_submissions has data_json', true, in_array('data_json', $col_names, true));

// 2. Seed
$waitlist = form_by_slug('waitlist');
$assert_true('waitlist form seeded', $waitlist !== null);
$assert('waitlist id is 1', 1, (int)$waitlist['id']);

$waitlist_fields = form_fields((int)$waitlist['id']);
$assert('waitlist has 6 fields', 6, count($waitlist_fields));
$field_names = array_column($waitlist_fields, 'name');
$assert('field names match', ['full_name','email','phone','role','clients_managed','bottleneck'], $field_names);

// 3. Form CRUD
$test_form_id = form_create([
    'slug' => 'stage6-test-form',
    'name' => 'Stage 6 Test Form',
    'description' => 'Test',
    'settings' => ['honeypot' => 'gotcha', 'success_heading' => 'Got it'],
]);
$assert_true('form_create returns id', $test_form_id > 0);

$row = form_by_id($test_form_id);
$assert('form_by_id slug', 'stage6-test-form', $row['slug']);
$assert('form_by_id is_builtin = 0', 0, (int)$row['is_builtin']);

$rejected_slug = false;
try { form_create(['slug' => 'BAD SLUG', 'name' => 'x']); } catch (InvalidArgumentException $e) { $rejected_slug = true; }
$assert('form_create rejects bad slug', true, $rejected_slug);

// 4. Field CRUD
$test_field_id = form_field_create($test_form_id, [
    'name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true,
    'validation' => ['max_length' => 150],
]);
$assert_true('form_field_create returns id', $test_field_id > 0);

form_field_create($test_form_id, ['name' => 'message', 'label' => 'Message', 'type' => 'textarea']);

$rejected_type = false;
try { form_field_create($test_form_id, ['name' => 'x', 'label' => 'X', 'type' => 'unknown']); } catch (InvalidArgumentException $e) { $rejected_type = true; }
$assert('form_field_create rejects bad type', true, $rejected_type);

$test_form_fields = form_fields($test_form_id);
$assert('test form has 2 fields', 2, count($test_form_fields));

// 5. Webhook CRUD
$wh_id = form_webhook_create($test_form_id, [
    'name' => 'Slack test', 'url' => 'https://hooks.slack.com/services/TEST',
    'method' => 'POST', 'enabled' => true,
    'payload_template' => ['text' => 'New lead: {{email}} - {{message}}'],
]);
$assert_true('webhook create returns id', $wh_id > 0);

form_webhook_update($wh_id, ['method' => 'BOGUS']); // should clamp to POST
$wh = $pdo->query("SELECT method FROM form_webhooks WHERE id = $wh_id")->fetchColumn();
$assert('webhook bad method clamped to POST', 'POST', $wh);

// 6. Validate
$ok = form_validate(form_by_id($test_form_id), [
    'email' => 'test@example.com',
    'message' => 'hello',
]);
$assert('valid input → ok=true', true, $ok['ok']);
$assert('clean data passed through', 'test@example.com', $ok['data']['email']);

$bad = form_validate(form_by_id($test_form_id), ['email' => 'not-an-email']);
$assert('missing required → ok=false', false, $bad['ok']);
$assert_true('error includes message field', isset($bad['errors']));

$bad2 = form_validate(form_by_id($test_form_id), ['email' => 'not-an-email', 'message' => 'hi']);
$assert('bad email → error reported', false, $bad2['ok']);
$assert_true('email error', isset($bad2['errors']['email']));

// 7. Payload template resolution
$resolved = form_resolve_payload(
    ['text' => 'New lead: {{email}} (form {{meta.form_slug}})', 'extra' => '{{message}}'],
    ['email' => 'foo@bar.com', 'message' => 'hi'],
    ['form_slug' => 'stage6-test-form']
);
$assert('payload {{field}} substituted',   'New lead: foo@bar.com (form stage6-test-form)', $resolved['text']);
$assert('payload {{message}} substituted', 'hi', $resolved['extra']);

// Null template → use raw data + meta
$resolved_null = form_resolve_payload(null, ['x' => 1], ['form_slug' => 'y']);
$assert_true('null template adds _meta', isset($resolved_null['_meta']));

// 8. form_submission_count
$count_before = form_submission_count($test_form_id);
$assert('no submissions yet', 0, $count_before);

// Insert a row and recount
$pdo->prepare(
    'INSERT INTO form_submissions (form_id, data_json, full_name, email, phone, role) VALUES (:f, :d, "x", "x@x.com", "111", "x")'
)->execute([':f' => $test_form_id, ':d' => '{"email":"x@x.com"}']);
$assert('count = 1 after insert', 1, form_submission_count($test_form_id));

// Cleanup
form_delete($test_form_id); // cascades to form_fields, form_webhooks
$pdo->prepare('DELETE FROM form_submissions WHERE form_id = :f')->execute([':f' => $test_form_id]);

$rejected_builtin_delete = false;
try { form_delete(1); } catch (InvalidArgumentException $e) { $rejected_builtin_delete = true; }
$assert('cannot delete builtin form', true, $rejected_builtin_delete);

echo "\n";
if ($failures === []) {
    echo "PASS — all assertions met.\n";
    exit(0);
}
echo "FAIL — " . count($failures) . " assertion(s) did not match:\n";
foreach ($failures as $f) {
    echo "  - $f\n";
}
exit(1);
