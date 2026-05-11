<?php
// core/lib/ai/prompts/suggest_pages.php — prompt template for the
// "suggest pages" admin AI tool.
//
// Caller passes a free-form business brief and gets back a list of
// suggested page ideas the admin can review. The model returns strict
// JSON; the API endpoint validates and surfaces it. Each suggestion
// has a kebab-case slug (will become the route segment), a human
// title, and a one-line rationale tying back to the brief.
//
// Output contract (strictly enforced by the prompt):
//   { "suggestions": [ { "slug": "...", "title": "...", "description": "...", "why": "..." }, ... ] }

declare(strict_types=1);

require_once __DIR__ . '/../brand_context.php';

const GUA_SUGGEST_PAGES_SYSTEM = <<<'SYS'
You are a website strategist helping a small business plan its site.

Your job: given a one-paragraph brief about the business, propose 5 to 8
landing/marketing pages the site should have to convert visitors into
customers and rank for relevant queries.

Output STRICT JSON only. No prose, no markdown, no code fences. The JSON
must match exactly:

{
  "suggestions": [
    {
      "slug":        "kebab-case-url-slug, no leading slash, no spaces",
      "title":       "Human-readable page title (Title Case, ≤60 chars)",
      "description": "One sentence summarising what the page covers (≤140 chars)",
      "why":         "One sentence explaining why this page helps conversion or SEO for the brief"
    }
  ]
}

Constraints:
- The "home" slug already exists; do NOT include it.
- Avoid generic boilerplate ("about-us") unless the brief explicitly justifies it.
- Prefer slugs that read like real intents (e.g. "pricing", "case-studies",
  "for-agencies", "services/seo-audit", "compare-vs-competitor-x").
- Slugs may include a single nested segment with "/" if it improves IA
  (e.g. "services/seo"); reject deeper nesting.
SYS;

/**
 * Build the messages array for the suggest_pages tool.
 *
 * @param string $brief A one-paragraph (or shorter) description of the
 *                      business — what they sell, who their customer is,
 *                      what's distinctive. Trimmed; long inputs aren't
 *                      truncated here (caller decides whether to cap).
 * @return array<int, array{role: string, content: string}>
 */
function suggest_pages_messages(string $brief): array
{
    $brief = trim($brief);

    // v2 Stage 2: prepend Brand Context Library content (brand_voice,
    // audience, services) so suggestions reflect this business's actual
    // positioning, not a generic plan. Empty when BCL has no reviewed
    // content yet, so this is safe on fresh sites.
    $brand = brand_context_for_categories(['brand_voice', 'audience', 'services']);
    $user_content = "Brief:\n\n{$brief}";
    if ($brand !== '') {
        $user_content = "Brand context:\n\n{$brand}\n\n---\n\nBrief:\n\n{$brief}";
    }

    return [
        ['role' => 'system', 'content' => GUA_SUGGEST_PAGES_SYSTEM],
        ['role' => 'user',   'content' => $user_content],
    ];
}
