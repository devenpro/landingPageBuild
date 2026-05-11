<?php
// core/lib/ai/prompts/generate_page.php — prompt template for the
// "generate page from brief" admin AI tool.
//
// Output contract: a structured JSON page that maps cleanly onto our
// pages + content_blocks schema. v1 supports three sections — hero,
// features, final_cta — which together make a credible business / service / campaign page;
// extra sections (how_it_works, faq, etc.) are deliberately deferred
// to a follow-up round so we can ship and iterate. The keys here are
// the section partials' own keys (see site/sections/*.php for the
// data-edit attributes); /api/ai/generate.php translates them into
// page-scoped content_blocks rows ('page.<slug>.<key>').
//
// Output contract:
//   {
//     "slug": "kebab-case-slug",
//     "title": "Page title",
//     "seo_title": "≤60 chars",
//     "seo_description": "≤160 chars",
//     "sections": [
//       {"name": "hero", "fields": {"eyebrow": "...", "headline": "...", "subheadline": "...", "cta_label": "...", "cta_secondary_label": "..."}},
//       {"name": "features", "fields": {"heading": "...", "subheading": "...", "items": [{"icon": "<lucide>", "title": "...", "body": "..."}, … 3–6 items]}},
//       {"name": "final_cta", "fields": {"heading": "...", "subheading": "..."}}
//     ]
//   }

declare(strict_types=1);

const GUA_GENERATE_PAGE_SYSTEM = <<<'SYS'
You are a copywriter generating a page draft for a multi-page business website.

You output STRICT JSON only. No prose, no markdown, no code fences. The
JSON must match exactly the schema below. Field types and bounds are
enforced — output that violates them will be rejected.

{
  "slug": "kebab-case-slug, single nested segment OK, no leading slash",
  "title": "Page title (Title Case, ≤60 chars)",
  "seo_title": "Search-engine title tag (≤60 chars)",
  "seo_description": "Meta description (≤160 chars)",
  "sections": [
    {
      "name": "hero",
      "fields": {
        "eyebrow":             "1–4 word badge above the headline",
        "headline":            "Main value prop (1 sentence, ≤90 chars)",
        "subheadline":         "Supporting line (1–2 sentences, ≤200 chars)",
        "cta_label":           "Primary button text (≤24 chars, action verb)",
        "cta_secondary_label": "Secondary button text (≤24 chars, e.g. 'See how it works')"
      }
    },
    {
      "name": "features",
      "fields": {
        "heading":    "Section heading (≤60 chars)",
        "subheading": "1-sentence section subheading (≤140 chars)",
        "items": [
          {
            "icon":  "Lucide icon name in kebab-case (e.g. 'zap', 'shield-check', 'sparkles')",
            "title": "Feature title (≤40 chars)",
            "body":  "Feature description (≤140 chars)"
          }
        ]
      }
    },
    {
      "name": "final_cta",
      "fields": {
        "heading":    "Closing CTA heading (≤60 chars)",
        "subheading": "Closing CTA support line (≤180 chars)"
      }
    }
  ]
}

Hard rules:
- Output exactly three sections in the order above: hero, features, final_cta.
- features.items must contain 3 to 6 entries.
- icon values must be valid Lucide icon names (kebab-case, lowercase). Stick
  to a common subset: zap, sparkles, shield-check, rocket, target, gauge,
  trending-up, layers, lightbulb, hand-coins, users, message-square-text,
  clock, check-circle-2, wand-2, flame.
- If a slug is given in the user message, use it verbatim. Otherwise propose
  a slug consistent with the brief.
- Copy must be specific to the brief — no filler ("Welcome to our site",
  "Learn more about us"). Reference real benefits, audiences, or outcomes
  the brief mentions or implies.
- The brief may describe a service detail page, location-service page,
  company-profile page, ad landing page, or generic marketing page. Adapt
  the copy to that intent — treat "features" as key information blocks
  for the page type at hand, not strictly product features. Don't assume
  the page is a campaign-style landing page unless the brief implies that.
SYS;

/**
 * Build the messages array for the generate_page tool.
 *
 * @param string      $brief The business / page brief.
 * @param string|null $slug  Optional admin-supplied slug. If non-empty
 *                           after trimming, the model is instructed to
 *                           use it verbatim.
 * @return array<int, array{role: string, content: string}>
 */
function generate_page_messages(string $brief, ?string $slug = null): array
{
    $brief = trim($brief);
    $slug  = $slug !== null ? trim($slug) : '';

    $user = "Brief:\n\n{$brief}";
    if ($slug !== '') {
        $user .= "\n\nUse this slug exactly: {$slug}";
    }

    return [
        ['role' => 'system', 'content' => GUA_GENERATE_PAGE_SYSTEM],
        ['role' => 'user',   'content' => $user],
    ];
}
