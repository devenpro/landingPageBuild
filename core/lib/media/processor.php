<?php
// core/lib/media/processor.php — image variant generator (v2 Stage 7).
//
// Generates a set of resized variants (and optionally .webp twins) for
// each uploaded image. Variants live under site/public/uploads/variants/
// <media_id>/<preset>.<ext> so the existing .htaccess (no PHP exec, blocked
// script extensions) applies to them too.
//
// Drivers:
//   - Imagick — preferred. Better quality, native WebP support, faster.
//   - GD — fallback. WebP needs PHP 7.0+ with imagewebp() compiled.
//   - none — recorded as processing_error so the admin can install one.
//
// Settings (admin-editable):
//   media_preset_widths   JSON array of max-widths (each upload produces
//                          one variant per width, capped at original size)
//   media_webp_enabled    1 = also generate .webp twins
//   media_webp_quality    1-100 (default 82)
//   media_jpeg_quality    1-100 (default 85)
//
// Aspect ratio is preserved — only width is set per preset, height
// computes. The width=original_width preset (largest meaningful size)
// is skipped if it would equal the original.

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../settings.php';

const MEDIA_VARIANTS_SUBDIR = 'uploads/variants';

function media_processor_driver(): string
{
    if (class_exists('Imagick')) {
        return 'imagick';
    }
    if (function_exists('gd_info')) {
        return 'gd';
    }
    return 'none';
}

/**
 * Process a single asset: capture dimensions, generate variants, mark row
 * processed. Returns ['ok' => bool, 'variants' => int, 'error' => string|null].
 */
function media_process(int $media_id): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM media_assets WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $media_id]);
    $asset = $stmt->fetch();
    if ($asset === false) {
        return ['ok' => false, 'variants' => 0, 'error' => "asset $media_id not found"];
    }
    if ($asset['kind'] !== 'image') {
        // Videos are tracked but not processed in Stage 7 (no transcoding pipeline yet).
        media_update_processed($media_id, 1, null);
        return ['ok' => true, 'variants' => 0, 'error' => null];
    }

    $src_path = GUA_SITE_PATH . '/public/uploads/' . $asset['filename'];
    if (!is_file($src_path)) {
        media_update_processed($media_id, 0, "source file missing: $src_path");
        return ['ok' => false, 'variants' => 0, 'error' => 'source file missing'];
    }

    $driver = media_processor_driver();
    if ($driver === 'none') {
        media_update_processed($media_id, 0, 'no image driver (Imagick or GD) available');
        return ['ok' => false, 'variants' => 0, 'error' => 'no driver'];
    }

    // SVGs have no raster dimensions to thumbnail — just record source and skip.
    if ($asset['mime_type'] === 'image/svg+xml') {
        media_update_processed($media_id, 1, null);
        return ['ok' => true, 'variants' => 0, 'error' => null];
    }

    try {
        $widths = settings_get('media_preset_widths', [320, 640, 960, 1280, 1920, 2560]);
        if (is_string($widths)) {
            $widths = json_decode($widths, true) ?: [320, 640, 960, 1280, 1920, 2560];
        }
        $widths = array_values(array_filter(array_map('intval', (array)$widths), fn($w) => $w > 0));
        sort($widths);

        $webp_on = (bool) settings_get('media_webp_enabled', true);
        $webp_q  = (int)  settings_get('media_webp_quality', 82);
        $jpeg_q  = (int)  settings_get('media_jpeg_quality', 85);

        // Read original dimensions
        $info = getimagesize($src_path);
        if ($info === false) {
            throw new RuntimeException('not a readable image');
        }
        [$orig_w, $orig_h] = $info;

        // Target dir
        $variants_root = GUA_SITE_PATH . '/public/' . MEDIA_VARIANTS_SUBDIR;
        $target_dir    = $variants_root . '/' . $media_id;
        if (!is_dir($target_dir)) {
            if (!mkdir($target_dir, 0755, true) && !is_dir($target_dir)) {
                throw new RuntimeException("cannot create variants dir: $target_dir");
            }
        }

        // Clear previous variants for this asset (idempotent reprocess)
        $pdo->prepare('DELETE FROM media_variants WHERE media_id = :id')->execute([':id' => $media_id]);
        foreach (glob($target_dir . '/*') ?: [] as $old) {
            @unlink($old);
        }

        // Native extension for the original mime
        $native_ext = media_ext_for_mime((string)$asset['mime_type']);
        $generated = 0;

        foreach ($widths as $w) {
            if ($w >= $orig_w) {
                // Don't upscale or duplicate the original.
                continue;
            }
            $new_h = (int) round(($orig_h / $orig_w) * $w);

            // Native variant
            $native_path = $target_dir . "/w{$w}.{$native_ext}";
            media_resize_to($driver, $src_path, $native_path, $w, $new_h, (string)$asset['mime_type'], $jpeg_q);
            media_variants_insert(
                $media_id,
                "w{$w}",
                $w,
                $new_h,
                (string)$asset['mime_type'],
                media_relative_path($native_path),
                (int) filesize($native_path)
            );
            $generated++;

            // WebP twin
            if ($webp_on) {
                $webp_path = $target_dir . "/w{$w}.webp";
                media_resize_to($driver, $src_path, $webp_path, $w, $new_h, 'image/webp', $webp_q);
                if (is_file($webp_path)) {
                    media_variants_insert(
                        $media_id,
                        "webp-w{$w}",
                        $w,
                        $new_h,
                        'image/webp',
                        media_relative_path($webp_path),
                        (int) filesize($webp_path)
                    );
                    $generated++;
                }
            }
        }

        // Capture original dimensions on the asset row
        $pdo->prepare(
            'UPDATE media_assets
                SET original_width = :w, original_height = :h,
                    processed = 1, processing_error = NULL
              WHERE id = :id'
        )->execute([':w' => $orig_w, ':h' => $orig_h, ':id' => $media_id]);

        return ['ok' => true, 'variants' => $generated, 'error' => null];
    } catch (Throwable $e) {
        media_update_processed($media_id, 0, $e->getMessage());
        return ['ok' => false, 'variants' => 0, 'error' => $e->getMessage()];
    }
}

/**
 * Process every asset still flagged processed=0 (or all if $only_unprocessed=false).
 * Used by core/scripts/media_reprocess.php and as a fallback after upload errors.
 */
function media_process_all(bool $only_unprocessed = true): array
{
    $where = $only_unprocessed ? 'WHERE processed = 0' : '';
    $rows = db()->query("SELECT id FROM media_assets $where")->fetchAll();
    $ok = 0; $fail = 0; $variants = 0;
    foreach ($rows as $r) {
        $res = media_process((int)$r['id']);
        if ($res['ok']) { $ok++; $variants += $res['variants']; } else { $fail++; }
    }
    return ['processed' => $ok, 'failed' => $fail, 'variants' => $variants];
}

function media_variants_for(int $media_id): array
{
    $stmt = db()->prepare('SELECT * FROM media_variants WHERE media_id = :id ORDER BY width, mime_type');
    $stmt->execute([':id' => $media_id]);
    return $stmt->fetchAll();
}

/* ---------- helpers ---------- */

function media_update_processed(int $media_id, int $processed, ?string $error): void
{
    db()->prepare(
        'UPDATE media_assets SET processed = :p, processing_error = :e WHERE id = :id'
    )->execute([':p' => $processed, ':e' => $error, ':id' => $media_id]);
}

function media_variants_insert(int $media_id, string $preset, int $w, int $h, string $mime, string $path, int $size): void
{
    db()->prepare(
        'INSERT INTO media_variants (media_id, preset_name, width, height, mime_type, path, size_bytes)
         VALUES (:m, :p, :w, :h, :mt, :pa, :s)'
    )->execute([':m' => $media_id, ':p' => $preset, ':w' => $w, ':h' => $h, ':mt' => $mime, ':pa' => $path, ':s' => $size]);
}

function media_ext_for_mime(string $mime): string
{
    return match ($mime) {
        'image/png'     => 'png',
        'image/webp'    => 'webp',
        'image/gif'     => 'gif',
        'image/svg+xml' => 'svg',
        default         => 'jpg',
    };
}

function media_relative_path(string $abs_path): string
{
    $root = realpath(GUA_PROJECT_ROOT);
    $abs  = realpath($abs_path) ?: $abs_path;
    if ($root !== false && str_starts_with($abs, $root . '/')) {
        return substr($abs, strlen($root) + 1);
    }
    return $abs;
}

/**
 * Resize a source image to the given dimensions and save to $dst_path with
 * the desired output mime type. Driver chosen by media_processor_driver().
 */
function media_resize_to(string $driver, string $src_path, string $dst_path, int $w, int $h, string $out_mime, int $quality): void
{
    if ($driver === 'imagick') {
        $img = new Imagick($src_path);
        $img->setImageBackgroundColor(new ImagickPixel('transparent'));
        $img = $img->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
        $img->resizeImage($w, $h, Imagick::FILTER_LANCZOS, 1);
        $img->stripImage();
        switch ($out_mime) {
            case 'image/webp':
                $img->setImageFormat('webp');
                $img->setImageCompressionQuality($quality);
                break;
            case 'image/png':
                $img->setImageFormat('png');
                break;
            case 'image/gif':
                $img->setImageFormat('gif');
                break;
            default:
                $img->setImageFormat('jpeg');
                $img->setImageCompressionQuality($quality);
        }
        $img->writeImage($dst_path);
        $img->clear();
        return;
    }

    // GD fallback
    $src = match (true) {
        str_starts_with(mime_content_type($src_path) ?: '', 'image/png')  => imagecreatefrompng($src_path),
        str_starts_with(mime_content_type($src_path) ?: '', 'image/webp') => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($src_path) : null,
        str_starts_with(mime_content_type($src_path) ?: '', 'image/gif')  => imagecreatefromgif($src_path),
        default                                                            => imagecreatefromjpeg($src_path),
    };
    if ($src === false || $src === null) {
        throw new RuntimeException('GD: could not decode source');
    }
    $dst = imagecreatetruecolor($w, $h);
    // Preserve transparency for PNG/WebP
    if (in_array($out_mime, ['image/png', 'image/webp'], true)) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $w, $h, $transparent);
    }
    $src_w = imagesx($src); $src_h = imagesy($src);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $src_w, $src_h);

    switch ($out_mime) {
        case 'image/webp':
            if (!function_exists('imagewebp')) {
                throw new RuntimeException('GD: WebP not compiled in');
            }
            imagewebp($dst, $dst_path, $quality);
            break;
        case 'image/png':
            imagepng($dst, $dst_path, 9);
            break;
        case 'image/gif':
            imagegif($dst, $dst_path);
            break;
        default:
            imagejpeg($dst, $dst_path, $quality);
    }
    imagedestroy($dst);
    imagedestroy($src);
}
