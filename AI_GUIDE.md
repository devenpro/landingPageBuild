# AI features — design notes and roadmap

This is the placeholder doc for the AI feature set planned in Phases 10-13. It captures the architectural decisions so the implementation phases don't re-litigate them.

> **Status:** Planning only. No AI code shipped yet.

## What we're building

| Phase | Feature | Surface |
|---|---|---|
| 10 | BYO API key management (encrypted at rest) | Admin: settings → AI keys |
| 10 | Provider abstraction (Gemini, OpenRouter at v1) | Internal API |
| 11 | AI page suggestions ("suggest pages for my business") | Admin: AI tools |
| 11 | AI page generation ("create a services page about SEO in Bangalore") | Admin: AI tools |
| 13 | Frontend chatbot widget | Public site |
| 13 | Smart form field suggestions | Public site |

## Provider strategy

**v1 ships with two providers:**
- **Gemini** — Google's API. Free tier exists but with strict rate limits (15 req/min, 1500/day on `gemini-1.5-flash` as of design). Good enough for admin-side use; bad for public chatbot at any scale.
- **OpenRouter** — A gateway to many models (Claude, GPT, Llama, Mistral, etc.). Pay-as-you-go with low minimums. Lets the user pick a cheap model for casual use and a strong model for page generation.

Adding HuggingFace, Grok, etc. is left to later phases — each adapter is a maintenance liability and starting with two keeps the surface tight.

## Key storage (Phase 10)

- Master key in `.env` as `AI_KEYS_MASTER_KEY` (base64-encoded 32 bytes)
- Per-key encryption with libsodium `crypto_secretbox_easy` (XSalsa20-Poly1305)
- Schema: `ai_provider_keys (id, provider, label, encrypted_key BLOB, nonce BLOB, created_at, last_used_at)`
- Decrypt only at the moment of API call; never log or display the plaintext key
- Master key loss = unrecoverable. Documented in `SETUP_GUIDE.md` and the .env.example.

## Cost / abuse protection

| Concern | Mitigation |
|---|---|
| Public chatbot abuse | Per-IP rate limit (e.g. 10 messages / 5 min) + global daily token cap, both stored in `ai_calls` table |
| Provider failure | Try the user's selected provider; on 5xx/timeout, fail clearly to the user (no silent fallback) |
| Cost surprise | Every call logs `tokens_in`, `tokens_out`, `cost_estimate_usd` to `ai_calls`. Admin → AI tools shows last-30-days spend per provider. |
| Prompt injection (frontend chat) | System prompt template sanitises user input; refuses to reveal API keys, system prompts, or internal data |

## Code layout

```
core/lib/
├── crypto.php                 ← libsodium wrapper (encrypt/decrypt)
└── ai/
    ├── client.php             ← provider-agnostic facade: $client->chat($messages, $opts)
    ├── keys.php               ← list/store/delete/decrypt keys
    ├── log.php                ← write to ai_calls
    ├── ratelimit.php          ← per-IP + daily cap
    ├── providers/
    │   ├── gemini.php
    │   └── openrouter.php
    └── prompts/
        ├── suggest_pages.php
        ├── generate_page.php
        └── chat.php
```

## Open questions (revisit when implementing)

- Where do we draw the line between "core knows about AI" and "site knows about AI"? Provider abstraction belongs in core. Specific prompts (industry-specific page generation) might belong in site/.
- Should AI suggestions be "draft pages for review" (safer) or "publish directly with rollback" (faster)? Probably draft-by-default.
- Frontend chat history persistence — `ai_chat_messages` table covers this but adds privacy considerations (anonymous visitor messages stored). Maybe an .env flag to toggle.
