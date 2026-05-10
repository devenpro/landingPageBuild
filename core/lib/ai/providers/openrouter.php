<?php
// core/lib/ai/providers/openrouter.php — adapter stub for OpenRouter.
//
// Endpoint: https://openrouter.ai/api/v1/chat/completions
// Auth:     Authorization: Bearer <API_KEY>
// Spec:     OpenAI-compatible chat-completions body (messages[], model, etc.)
//
// This is a SKELETON. The actual cURL call lands when Phase 10 finishes
// the smoke test (see AI_GUIDE.md "Implementation reading order"). The
// response shape is OpenAI-compatible so the parsing here will be
// straightforward; the only OpenRouter-specific bits are recommended
// HTTP-Referer and X-Title headers for attribution.

declare(strict_types=1);

const GUA_OPENROUTER_DEFAULT_MODEL = 'anthropic/claude-3.5-haiku';
const GUA_OPENROUTER_ENDPOINT      = 'https://openrouter.ai/api/v1/chat/completions';

function ai_provider_openrouter_chat(string $api_key, array $messages, array $opts = []): array
{
    throw new RuntimeException('OpenRouter adapter not yet implemented (Phase 10 in progress).');
}
