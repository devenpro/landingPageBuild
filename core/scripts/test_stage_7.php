<?php
// core/scripts/test_stage_7.php — smoke test for v2 Stage 7 (Media v2).
//
// Exercises:
//   1. Schema (media_assets new columns + media_variants table)
//   2. Settings (4 media keys)
//   3. Driver detection (imagick or gd available)
//   4. media_process on a real image (generates variants for each preset)
//   5. media_variants_for() returns the generated rows
//   6. Re-process is idempotent (clears old variants and regenerates)
//   7. media_process_all backfills unprocessed assets
//   8. media_processor_driver returns a known driver name

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/media/processor.php';

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

echo "Stage 7 smoke test\n";
echo "------------------\n";

$pdo = db();

// 1. Schema
$cols = array_column($pdo->query('PRAGMA table_info(media_assets)')->fetchAll(), 'name');
foreach (['alt_text', 'caption', 'original_width', 'original_height', 'processed', 'processing_error'] as $c) {
    $assert("media_assets has $c", true, in_array($c, $cols, true));
}
$mv_exists = (bool) $pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='media_variants'")->fetchColumn();
$assert('media_variants table exists', true, $mv_exists);

// 2. Settings
$keys = $pdo->query("SELECT key FROM site_settings WHERE group_name = 'media'")->fetchAll(PDO::FETCH_COLUMN);
$expected_keys = ['max_image_bytes', 'max_video_bytes', 'media_preset_widths', 'media_webp_enabled', 'media_webp_quality', 'media_jpeg_quality'];
foreach ($expected_keys as $k) {
    $assert("setting $k seeded", true, in_array($k, $keys, true));
}

// 3. Driver
$driver = media_processor_driver();
$assert_true('driver detected (imagick or gd)', in_array($driver, ['imagick', 'gd'], true));
echo "  (driver: $driver)\n";

if ($driver === 'none') {
    echo "  Skipping process tests — no image driver. Install Imagick or GD.\n";
    if ($failures === []) {
        echo "\nPARTIAL — schema + settings OK; install image driver for full coverage.\n";
        exit(0);
    }
    echo "\nFAIL — " . count($failures) . " assertion(s) did not match\n";
    exit(1);
}

// 4. Create a real test image (800x600 JPEG) and process it
$uploads_dir = GUA_SITE_PATH . '/public/uploads';
if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0755, true);
$test_filename = 'stage7-test-' . uniqid() . '.jpg';
$test_path = $uploads_dir . '/' . $test_filename;

$img = imagecreatetruecolor(800, 600);
$red  = imagecolorallocate($img, 220, 60, 60);
$blue = imagecolorallocate($img, 30, 90, 200);
imagefilledrectangle($img, 0, 0, 800, 600, $red);
imagefilledrectangle($img, 200, 150, 600, 450, $blue);
imagejpeg($img, $test_path, 90);
imagedestroy($img);

$stmt = $pdo->prepare(
    "INSERT INTO media_assets (filename, original_name, mime_type, size_bytes, kind)
     VALUES (:f, 'stage7-test.jpg', 'image/jpeg', :s, 'image')"
);
$stmt->execute([':f' => $test_filename, ':s' => filesize($test_path)]);
$media_id = (int) $pdo->lastInsertId();

$result = media_process($media_id);
$assert('media_process returns ok',           true, $result['ok']);
$assert_true('media_process generated >0 variants', $result['variants'] > 0);

// 5. Variants persisted
$variants = media_variants_for($media_id);
$assert_true('variants_for() returns >0 rows', count($variants) > 0);

// With default widths [320,640,960,1280,1920,2560] vs source 800px,
// we expect variants only for widths < 800: 320 and 640. With WebP on,
// that's 4 variants total (320 + webp-320 + 640 + webp-640).
$widths = array_unique(array_map(fn($v) => (int)$v['width'], $variants));
sort($widths);
$assert('widths skip ones >= original', [320, 640], $widths);

$assert_true('processed flag = 1',
    (int) $pdo->query("SELECT processed FROM media_assets WHERE id = $media_id")->fetchColumn() === 1);

$row = $pdo->query("SELECT original_width, original_height FROM media_assets WHERE id = $media_id")->fetch();
$assert('original_width captured',  800, (int)$row['original_width']);
$assert('original_height captured', 600, (int)$row['original_height']);

// 6. Variant files exist on disk
foreach ($variants as $v) {
    $abs = GUA_PROJECT_ROOT . '/' . $v['path'];
    $assert_true("variant file on disk: {$v['preset_name']}", is_file($abs));
}

// 7. Re-process is idempotent
$v_before = count(media_variants_for($media_id));
$result2 = media_process($media_id);
$v_after  = count(media_variants_for($media_id));
$assert('reprocess preserves variant count', $v_before, $v_after);

// 8. media_process_all backfills (mark another asset unprocessed and run)
$test2_filename = 'stage7-test2-' . uniqid() . '.jpg';
$test2_path = $uploads_dir . '/' . $test2_filename;
copy($test_path, $test2_path);
$stmt = $pdo->prepare(
    "INSERT INTO media_assets (filename, original_name, mime_type, size_bytes, kind, processed)
     VALUES (:f, 'stage7-test2.jpg', 'image/jpeg', :s, 'image', 0)"
);
$stmt->execute([':f' => $test2_filename, ':s' => filesize($test2_path)]);
$media2_id = (int) $pdo->lastInsertId();

$summary = media_process_all(true);
$assert_true('process_all reports at least 1 processed', $summary['processed'] >= 1);

// Cleanup
foreach ([$media_id, $media2_id] as $mid) {
    $row = $pdo->query("SELECT filename FROM media_assets WHERE id = $mid")->fetch();
    if ($row !== false) {
        $abs = $uploads_dir . '/' . $row['filename'];
        if (is_file($abs)) @unlink($abs);
        foreach (media_variants_for($mid) as $v) {
            @unlink(GUA_PROJECT_ROOT . '/' . $v['path']);
        }
        @rmdir($uploads_dir . '/variants/' . $mid);
    }
    $pdo->prepare("DELETE FROM media_assets WHERE id = :id")->execute([':id' => $mid]);
}

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
