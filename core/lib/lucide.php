<?php
// core/lib/lucide.php — server-side lucide icon renderer.
// Phase 14 round D-3 — replaces the 358KB unpkg.com/lucide@latest UMD
// bundle (render-blocking, no long-term cache) with an inline SVG emitted
// from a curated set of paths in core/lib/lucide-icons.php (~21KB PHP
// array, loaded once per request, byte-for-byte equivalent to lucide).
//
// Adding an icon: run `php core/build/extract-lucide.php` after editing
// the icon list at the top of that script. The map is checked in.
//
// Unknown icons get a decorative placeholder span so layout stays stable
// — useful both as a hint to the admin (they'll notice the missing icon
// during editing) and to keep CLS at zero.

declare(strict_types=1);

/**
 * Render a lucide icon as inline SVG.
 *
 * @param string $name   icon name (e.g. "arrow-right", "zap")
 * @param string $class  Tailwind classes for size/color (e.g. "h-5 w-5")
 * @param array  $attrs  extra attributes to add or override on the <svg>
 *                       (commonly: data-edit, data-key, aria-label)
 */
function lucide(string $name, string $class = '', array $attrs = []): string
{
    static $map = null;
    if ($map === null) {
        $map = require __DIR__ . '/lucide-icons.php';
    }

    if (!isset($map[$name])) {
        // Unknown icon — emit a visible-ish placeholder so the admin
        // notices in edit mode and the layout doesn't collapse to zero.
        return sprintf(
            '<span class="%s inline-block rounded border border-dashed border-ink-300" aria-hidden="true" data-lucide-missing="%s"></span>',
            htmlspecialchars($class, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
        );
    }

    // Defaults match lucide's defaultAttributes — same look as the JS lib.
    $svg_attrs = [
        'xmlns'            => 'http://www.w3.org/2000/svg',
        'width'            => '24',
        'height'           => '24',
        'viewBox'          => '0 0 24 24',
        'fill'             => 'none',
        'stroke'           => 'currentColor',
        'stroke-width'     => '2',
        'stroke-linecap'   => 'round',
        'stroke-linejoin'  => 'round',
        'class'            => $class,
        'aria-hidden'      => 'true',
        'data-lucide'      => $name,
    ];
    // Caller wins for collisions (so a data-edit icon can override
    // aria-hidden, or a labeled icon can drop it and add aria-label).
    foreach ($attrs as $k => $v) {
        $svg_attrs[$k] = $v;
    }

    $open = '<svg';
    foreach ($svg_attrs as $k => $v) {
        $open .= ' ' . $k . '="' . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . '"';
    }
    $open .= '>';

    $children = '';
    foreach ($map[$name] as $el) {
        $children .= '<' . $el['tag'];
        foreach ($el['attrs'] as $k => $v) {
            $children .= ' ' . $k . '="' . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . '"';
        }
        $children .= '/>';
    }

    return $open . $children . '</svg>';
}
