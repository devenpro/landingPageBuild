<?php
// core/lib/ai/providers/gemini.php — adapter stub for Google Gemini.
//
// Endpoint: https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent
// Auth:     ?key=API_KEY query param
//
// This is a SKELETON. The actual cURL call, request-body shaping, and
// response parsing land when Phase 10 finishes the smoke test against a
// real free-tier key (see AI_GUIDE.md "Implementation reading order").
// Calling ai_chat('gemini', ...) right now will throw.

declare(strict_types=1);

const GUA_GEMINI_DEFAULT_MODEL = 'gemini-1.5-flash';
const GUA_GEMINI_ENDPOINT      = 'https://generativelanguage.googleapis.com/v1beta/models/';

function ai_provider_gemini_chat(string $api_key, array $messages, array $opts = []): array
{
    throw new RuntimeException('Gemini adapter not yet implemented (Phase 10 in progress).');
}
