<?php
// core/lib/ai/brand_context.php — assemble Brand Context Library content for AI prompts.
//
// Three entry points, used by the existing prompts and Stage 10 bootstrap:
//
//   brand_context_summary()
//     ~200 word digest covering brand_voice, brand_facts, audience, services.
//     Cheap to inject into every chat / completion. Used by chat.php so the
//     public chatbot always knows the brand basics.
//
//   brand_context_for_categories(array $slugs)
//     Full body of every active+reviewed item in the named categories, with
//     a per-category cap of ~4K characters and a global cap of ~8K. Used by
//     the page generator (Stage 0 prompt edit added the multi-page-type
//     framing this content fills in) and "suggest pages" tool.
//
//   brand_context_always()
//     Items with always_on=1 only. Used by the chatbot system prompt as a
//     persistent voice/brand anchor.
//
// All three skip items where source='ai' AND ai_reviewed=0 — the review
// gate from the v2 plan. Filtering happens in brand_items_for_prompt_context()
// and brand_items_always_on(), not here.

declare(strict_types=1);

require_once __DIR__ . '/../brand/items.php';
require_once __DIR__ . '/../brand/categories.php';

const BRAND_CONTEXT_PER_CATEGORY_CHARS = 4000;
const BRAND_CONTEXT_GLOBAL_CHARS       = 8000;
const BRAND_CONTEXT_SUMMARY_CHARS      = 1200;

/**
 * Markdown digest of the four "voice + identity" categories. Used in chat.php.
 * Empty string if the BCL has no reviewed content yet (don't pollute the
 * prompt with empty headings).
 */
function brand_context_summary(): string
{
    $items = brand_items_for_prompt_context(['brand_voice', 'brand_facts', 'audience', 'services']);
    if ($items === []) {
        return '';
    }
    $by_cat = [];
    foreach ($items as $i) {
        $by_cat[$i['category_slug']][] = $i;
    }

    $out = ["== Brand basics =="];
    $budget = BRAND_CONTEXT_SUMMARY_CHARS;
    foreach (['brand_voice','brand_facts','audience','services'] as $cat) {
        if (empty($by_cat[$cat])) continue;
        $section = '';
        foreach ($by_cat[$cat] as $i) {
            $body = trim((string)$i['body']);
            if ($body === '') continue;
            $section .= '- ' . trim($i['title']) . ': ' . brand_first_sentences($body, 240) . "\n";
        }
        $section = rtrim($section);
        if ($section === '') continue;
        $out[] = ucwords(str_replace('_', ' ', $cat)) . ':';
        $out[] = $section;
        $out[] = '';
        $budget -= mb_strlen($section);
        if ($budget <= 0) break;
    }

    $text = rtrim(implode("\n", $out));
    if (mb_strlen($text) > BRAND_CONTEXT_SUMMARY_CHARS) {
        $text = mb_substr($text, 0, BRAND_CONTEXT_SUMMARY_CHARS) . '…';
    }
    return $text;
}

/**
 * Full bodies of reviewed items in the requested categories, framed as
 * "== <Category Label> ==" sections. Caps per-category and global so a
 * pathological brand library can't blow the model's context window.
 */
function brand_context_for_categories(array $category_slugs): string
{
    $items = brand_items_for_prompt_context($category_slugs);
    if ($items === []) {
        return '';
    }

    $cats = [];
    foreach (brand_categories_all() as $c) {
        $cats[$c['slug']] = $c;
    }

    $by_cat = [];
    foreach ($items as $i) {
        $by_cat[$i['category_slug']][] = $i;
    }

    $sections = [];
    $global_used = 0;

    // Preserve the order the caller asked for, so 'brand_voice' comes
    // before 'design_guide' in generate_page-style calls.
    foreach ($category_slugs as $slug) {
        if (empty($by_cat[$slug])) continue;
        $label = $cats[$slug]['label'] ?? ucwords(str_replace('_', ' ', $slug));
        $section_body = '';
        foreach ($by_cat[$slug] as $i) {
            $body = trim((string)$i['body']);
            if ($body === '') continue;
            $piece = '### ' . $i['title'] . "\n\n" . $body . "\n\n";
            if (mb_strlen($section_body . $piece) > BRAND_CONTEXT_PER_CATEGORY_CHARS) {
                $remaining = BRAND_CONTEXT_PER_CATEGORY_CHARS - mb_strlen($section_body);
                if ($remaining > 100) {
                    $section_body .= mb_substr($piece, 0, $remaining - 1) . '…';
                }
                break;
            }
            $section_body .= $piece;
        }
        $section_body = rtrim($section_body);
        if ($section_body === '') continue;

        $section = "== {$label} ==\n\n{$section_body}";
        if ($global_used + mb_strlen($section) > BRAND_CONTEXT_GLOBAL_CHARS) {
            // No more room without truncating mid-content; bail clean.
            break;
        }
        $sections[] = $section;
        $global_used += mb_strlen($section) + 2; // +2 for the joining "\n\n"
    }

    return implode("\n\n", $sections);
}

/**
 * Always-on items only. Used as the persistent voice anchor in chat.php.
 */
function brand_context_always(): string
{
    $items = brand_items_always_on();
    if ($items === []) {
        return '';
    }
    $lines = ["== Persistent brand anchor =="];
    $budget = 2000;
    foreach ($items as $i) {
        $body = trim((string)$i['body']);
        if ($body === '') continue;
        $piece = '- (' . $i['category_label'] . ') ' . $i['title'] . ': ' . $body . "\n";
        if (mb_strlen($piece) > $budget) {
            $piece = mb_substr($piece, 0, $budget - 1) . '…';
        }
        $lines[] = $piece;
        $budget -= mb_strlen($piece);
        if ($budget <= 0) break;
    }
    return rtrim(implode("\n", $lines));
}

/**
 * Return up to $chars of the first sentences of $text.
 * Falls back to a hard substring if no sentence break is found.
 */
function brand_first_sentences(string $text, int $chars): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text));
    if (mb_strlen($text) <= $chars) {
        return $text;
    }
    $slice = mb_substr($text, 0, $chars);
    // Try to back up to the last sentence boundary
    $last_period = max(mb_strrpos($slice, '. '), mb_strrpos($slice, '! '), mb_strrpos($slice, '? '));
    if ($last_period !== false && $last_period > $chars * 0.6) {
        return mb_substr($slice, 0, $last_period + 1);
    }
    return $slice . '…';
}
