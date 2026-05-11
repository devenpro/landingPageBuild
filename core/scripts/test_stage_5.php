<?php
// core/scripts/test_stage_5.php — smoke test for v2 Stage 5 (Taxonomy + Location Services).
//
// Exercises:
//   1. Schema (taxonomies, taxonomy_terms, entry_taxonomy_terms)
//   2. Seed (locations + service_categories taxonomies, location_services type)
//   3. Term CRUD with hierarchy (3-level locations tree)
//   4. taxonomy_tree() nested shape
//   5. term_path() ancestor chain
//   6. Cycle prevention on update
//   7. Entry term assignments (set / read / clear)
//   8. Multi-placeholder route resolution (/services/{service_slug}/{location_slug})
//   9. taxonomies_for_type filter

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/taxonomy.php';
require_once __DIR__ . '/../lib/content/types.php';
require_once __DIR__ . '/../lib/content/entries.php';

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

echo "Stage 5 smoke test\n";
echo "------------------\n";

$pdo = db();

// 1. Schema
foreach (['taxonomies', 'taxonomy_terms', 'entry_taxonomy_terms'] as $t) {
    $exists = (bool) $pdo->query(
        "SELECT 1 FROM sqlite_master WHERE type='table' AND name='" . $t . "' LIMIT 1"
    )->fetchColumn();
    $assert("table $t exists", true, $exists);
}

// 2. Seed
$locations  = taxonomy_by_slug('locations');
$categories = taxonomy_by_slug('service_categories');
$assert_true('locations taxonomy seeded',         $locations !== null);
$assert_true('service_categories taxonomy seeded', $categories !== null);
$assert('locations is hierarchical', 1, (int)$locations['is_hierarchical']);

$location_services = content_type_by_slug('location_services');
$assert_true('location_services content type seeded', $location_services !== null);
$assert('location_services route pattern', '/services/{service_slug}/{location_slug}', $location_services['route_pattern']);

// 3. Build 3-level location tree: India → Maharashtra → Mumbai
$india_id      = term_create((int)$locations['id'], 'stage5-india',       'India');
$maharashtra_id = term_create((int)$locations['id'], 'stage5-maharashtra', 'Maharashtra', $india_id);
$mumbai_id     = term_create((int)$locations['id'], 'stage5-mumbai',      'Mumbai', $maharashtra_id);

$assert_true('root term created (no parent)', taxonomy_term_by_id($india_id)['parent_id'] === null);
$assert('level 2 parent_id matches',    $india_id,      (int)taxonomy_term_by_id($maharashtra_id)['parent_id']);
$assert('level 3 parent_id matches',    $maharashtra_id,(int)taxonomy_term_by_id($mumbai_id)['parent_id']);

// 4. Tree shape
$tree = taxonomy_tree((int)$locations['id']);
$india_node = null;
foreach ($tree as $node) {
    if ((int)$node['id'] === $india_id) { $india_node = $node; break; }
}
$assert_true('India appears as root in tree', $india_node !== null);
$assert_true('India has one child (Maharashtra)',
    $india_node !== null && count($india_node['children']) === 1);
$assert_true('Maharashtra has one child (Mumbai)',
    $india_node !== null
    && !empty($india_node['children'])
    && count($india_node['children'][0]['children']) === 1);

// 5. Term path
$path = term_path($mumbai_id);
$names = array_map(fn($n) => $n['name'], $path);
$assert('term_path returns root→...→term', ['India', 'Maharashtra', 'Mumbai'], $names);

// 6. Cycle prevention
$rejected = false;
try {
    // Try to make India a child of Mumbai (which would create a cycle since Mumbai is under India)
    term_update($india_id, ['parent_id' => $mumbai_id]);
} catch (InvalidArgumentException $e) {
    $rejected = true;
}
$assert('cycle prevention rejects descendant-as-parent', true, $rejected);

// Try to make a term its own parent
$rejected2 = false;
try {
    term_update($india_id, ['parent_id' => $india_id]);
} catch (InvalidArgumentException $e) {
    $rejected2 = true;
}
$assert('cycle prevention rejects self-parent', true, $rejected2);

// 7. Entry term assignments
$service_type_id = (int)content_type_by_slug('services')['id'];
$service_entry_id = content_entry_create([
    'type_id' => $service_type_id,
    'slug'    => 'stage5-seo-audit',
    'title'   => 'Stage 5 SEO Audit',
    'data'    => ['short_desc' => 'Test'],
    'status'  => 'published',
]);

entry_terms_set($service_entry_id, $service_type_id, [$mumbai_id]);
$assigned = entry_terms($service_entry_id, 'locations');
$assert('entry has 1 location term', 1, count($assigned));
$assert('assigned term is Mumbai', $mumbai_id, (int)$assigned[0]['id']);

// Replace with multiple terms
entry_terms_set($service_entry_id, $service_type_id, [$mumbai_id, $maharashtra_id]);
$assigned = entry_terms($service_entry_id, 'locations');
$assert('entry has 2 location terms after replace', 2, count($assigned));

// Clear
entry_terms_set($service_entry_id, $service_type_id, []);
$assigned = entry_terms($service_entry_id, 'locations');
$assert('entry has 0 terms after clear', 0, count($assigned));

// 8. Multi-placeholder routing
$location_services_type_id = (int)$location_services['id'];
$location_service_entry_id = content_entry_create([
    'type_id' => $location_services_type_id,
    'slug'    => 'stage5-seo-audit/stage5-mumbai',
    'title'   => 'SEO Audit in Mumbai (Stage 5 test)',
    'data'    => [
        'service_entry_id' => $service_entry_id,
        'location_term_id' => $mumbai_id,
        'city'             => 'Mumbai',
    ],
    'status'  => 'published',
]);

$match = content_resolve_route('/services/stage5-seo-audit/stage5-mumbai');
$assert_true('multi-placeholder route resolves', $match !== null);
if ($match !== null) {
    $assert('routed type is location_services', 'location_services', $match['type']['slug']);
    $assert('routed entry id matches', $location_service_entry_id, (int)$match['entry']['id']);
}

// Negative: single-segment shouldn't match the 2-segment pattern
$single = content_resolve_route('/services/stage5-seo-audit');
$assert_true('single-segment still resolves to Services entry (not location service)',
    $single !== null && $single['type']['slug'] === 'services');

// 9. taxonomies_for_type — both taxonomies apply to services (because applies_to_type_ids_json is NULL)
$tax_for_services = taxonomies_for_type($service_type_id);
$assert('both taxonomies apply to services', 2, count($tax_for_services));

// Cleanup
content_entry_delete($location_service_entry_id);
content_entry_delete($service_entry_id);
term_delete($mumbai_id);
term_delete($maharashtra_id);
term_delete($india_id);
$assert('cleanup: India term deleted', null, taxonomy_term_by_id($india_id));

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
