<?php
// core/lib/ai/prompts/brand_item_generate.php — generate a brand-item body
// from a category + brief (v2 Stage 2).
//
// Used by:
//   - Admin "AI fill" button on /admin/brand.php for empty items
//   - Stage 10 bootstrap wizard for filling missing items during site creation
//
// Output contract: strict JSON {"title":"...", "body":"..."}. The body
// matches the category's intended kind (markdown for most, fact bullet
// lists for brand_facts, link entries for social).
//
// Items generated through this prompt are stored with source='ai',
// ai_reviewed=0 — they DO NOT appear in subsequent AI prompt context
// until the admin opens and saves them via /admin/brand.php.

declare(strict_types=1);

const GUA_BRAND_ITEM_GENERATE_SYSTEM = <<<'SYS'
You are filling a slot in a brand context library that downstream AI tools
(page generators, chatbots, ad copywriters) will read as fact when building
content for this business.

Output STRICT JSON only. No prose, no markdown fences, no commentary. The
JSON must match exactly:

{
  "title": "Short label for the item (Title Case, ≤60 chars)",
  "body":  "Body text — see per-category guidance below"
}

General rules:
- Be specific to the brief. Refuse to invent facts (founding dates,
  certifications, headcount, named customers) that the brief doesn't
  provide. If a fact is needed and missing, leave a clearly marked
  placeholder like "[Founded: TBD]" rather than guessing.
- Write in plain text. Use line breaks for structure where it helps.
- Keep the body under 1500 characters.

Per-category guidance:

brand_voice — Describe the tone, vocabulary, and writing style. Examples:
  - "Direct, confident, never salesy."
  - "Plain English; avoid jargon unless the audience uses it."
  - "Use 'we' for our team and 'you' for the reader; never 'I'."
Body should read like style guide bullets, not narrative prose.

brand_facts — Verifiable claims only. Bulleted format:
  - Founded: 2022 in Bengaluru
  - Team: 12 (8 engineers, 2 designers, 2 ops)
  - Customers: ACME Co., FooBar Inc. (logos may be used)
  - Certifications: SOC 2 Type II (in audit)
Any field that the brief doesn't establish: mark "[TBD]".

audience — Who the site is for. Cover roles, jobs-to-be-done, vocabulary
they use, common objections. 200-500 words of plain prose works well.

services — One-line summary per offering. Use a heading + body pattern:
## Service name
One sentence on what it is. One sentence on who it's for. One sentence
on what makes it different.
(Repeat per service.)

design_guide — Brand colours (with hex values if known), typography (font
names + when to use), layout patterns, image style. Bulleted is fine.

page_guide — How AI should structure new pages: section order
recommendations, typical length, what to lead with, CTA conventions.

seo — Target keywords (head terms + long-tail), geographic focus, named
competitors to differentiate against, internal linking habits.

social — One link per line, like:
- LinkedIn: https://linkedin.com/company/example
- X: https://x.com/example
- YouTube: https://youtube.com/@example
SYS;

/**
 * Build messages for the brand_item_generate AI call.
 *
 * @param string $category_slug   Category being filled (brand_voice, etc.)
 * @param string $category_label  Human label, e.g. "Brand Voice"
 * @param string $brief           Free-form description of the business or item.
 */
function brand_item_generate_messages(string $category_slug, string $category_label, string $brief): array
{
    $brief = trim($brief);
    return [
        ['role' => 'system', 'content' => GUA_BRAND_ITEM_GENERATE_SYSTEM],
        ['role' => 'user',   'content' => "Category: {$category_slug} ({$category_label})\n\nBrief:\n\n{$brief}"],
    ];
}
