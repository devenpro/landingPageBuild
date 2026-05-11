<?php
// core/scripts/media_reprocess.php — backfill image variants for v1 uploads (v2 Stage 7).
//
// Usage:
//   php core/scripts/media_reprocess.php           # process only unprocessed
//   php core/scripts/media_reprocess.php --all     # re-process every image asset
//
// Lazy: an upload from v1 has processed=0 by default (the column was added
// in 0012_media_v2.sql). Run this once per site after upgrading.

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/media/processor.php';

$only_unprocessed = !in_array('--all', $argv, true);
$mode = $only_unprocessed ? 'unprocessed only' : 'every image';

echo "media_reprocess: driver=", media_processor_driver(), ", mode={$mode}\n";

$result = media_process_all($only_unprocessed);
echo "  processed: ", $result['processed'], "\n";
echo "  failed:    ", $result['failed'], "\n";
echo "  variants:  ", $result['variants'], "\n";
echo "done.\n";
