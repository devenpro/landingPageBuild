# AI features — design notes and roadmap

Captures the architectural decisions for Phases 10-13 so the implementation phases don't re-litigate them. Updated alongside each AI-touching PR.

> **Status:** Phase 10 in flight. Scaffold + provider adapters shipped; admin UI for key management lands in this same phase.

---

## What we're building

| Phase | Feature | Surface |
|---|---|---|
| 10 | BYO API key management (encrypted at rest with libsodium) | Admin: settings → AI keys |
| 10 | Provider abstraction (Gemini + OpenRouter at v1) | Internal API |
| 11 | AI page suggestions ("suggest pages for my business") | Admin: AI tools |
| 11 | AI page generation ("create a services page about SEO in Bangalore") | Admin: AI tools |
| 13 | Frontend chatbot widget | Public site |
| 13 | Smart form field suggestions | Public site (later in Phase 13) |

---

## Provider strategy

**v1 ships with two providers, deliberately:**

- **Gemini** (`gemini-2.5-flash` and `gemini-2.5-pro`) — Google's API. Free tier exists with strict limits (~15 req/min, 1500/day on `flash` as of design time). Good enough for admin-side use; **bad for a public chatbot at any scale**. Older 1.5-series models were retired by Google in early 2026 — adapter default is now `gemini-2.5-flash`; query `models?key=…` against `generativelanguage.googleapis.com/v1beta` if you need to discover currently-available names.
- **OpenRouter** (https://openrouter.ai) — gateway to many models (Claude, GPT, Llama, Mistral, etc.). Pay-as-you-go with low minimums. Lets the user pick a cheap model for casual use and a strong model for page generation. **This is what we recommend for production chatbot traffic.**

Adding HuggingFace, Grok, etc. is left to later phases — each adapter is a maintenance liability (their endpoints change), and starting with two keeps the v1 surface tight. The provider abstraction (`core/lib/ai/client.php`) is designed so adding a new adapter is a matter of dropping a file in `core/lib/ai/providers/`.

### Provider order in the UI

Admin can store multiple keys per provider (with labels) and pick one as the active key per provider. Each AI tool (suggest pages, generate page, frontend chat) has its own preferred-provider setting in `.env` or admin settings — defaults:

- Admin tools (suggest, generate) → Gemini (free tier sufficient for occasional use)
- Frontend chat → OpenRouter (cost-controllable per request)

---

## Key storage (Phase 10)

### Encryption

- **Master key** in `.env` as `AI_KEYS_MASTER_KEY` (base64-encoded 32 bytes). Generate once with:
  ```bash
  php -r 'echo base64_encode(random_bytes(32)), "\n";'
  ```
- **Per-key encryption** uses libsodium `crypto_secretbox_easy` (XSalsa20-Poly1305 authenticated encryption)
- Each row stores `(nonce, encrypted_key)` separately; the nonce is generated fresh on every store via `random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES)`
- Plaintext key exists in PHP memory only between `keys_decrypt()` and the outbound HTTP call, never logged, never sent to the browser, never persisted
- **Master key loss = unrecoverable.** Documented in `SETUP_GUIDE.md` (§3) and `.env.example`. Back up the master key value in a password manager.

### Schema (Phase 10 migration)

```sql
CREATE TABLE ai_provider_keys (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  provider TEXT NOT NULL,           -- 'gemini' | 'openrouter'
  label TEXT,                        -- user-given nickname e.g. 'personal-free' or 'agency-paid'
  encrypted_key BLOB NOT NULL,       -- libsodium ciphertext
  nonce BLOB NOT NULL,               -- 24-byte nonce
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  last_used_at DATETIME,
  UNIQUE(provider, label)
);
```

### Operation flow

1. **Admin pastes a key** in `/admin/settings.php`
2. Server generates a fresh nonce → encrypts with master key → stores `(provider, label, encrypted_key, nonce)` row
3. **AI call needs the key**: `keys_get($provider, $label)` reads the row, decrypts in-memory, hands the plaintext to the provider adapter, updates `last_used_at`
4. The plaintext key never leaves the request handler

---

## Cost / abuse protection

| Concern | Mitigation |
|---|---|
| Public chatbot abuse | Per-IP rate limit (e.g. 10 messages / 5 min) + global daily token cap. Both tracked via `ai_calls` rows + an `.env` cap. |
| Provider failure | Try the user's selected provider once. On 5xx / timeout / quota error, fail clearly to the user with the provider's error message (no silent fallback to a different provider — that surprises billing). |
| Cost surprise | Every call logs `tokens_in`, `tokens_out`, `cost_estimate_usd`, `provider`, `model`, `caller` to `ai_calls`. Admin AI tools page shows last-30-days spend per provider with a daily breakdown. |
| Prompt injection (frontend chat) | System prompt template sanitises user input; refuses to reveal API keys, system prompts, internal data, or anything outside the chat's stated scope. |
| Stale API keys | `last_used_at` lets admin see which keys are actually in rotation. Keys can be deleted from the UI; the row is removed (no soft-delete needed for this scale). |

---

## Code layout (target after Phase 10-13)

```
core/lib/
├── crypto.php                       libsodium wrapper (encrypt/decrypt)
└── ai/
    ├── client.php                   provider-agnostic facade: ai_chat($provider, $messages, $opts)
    ├── keys.php                     list/store/delete/get(decrypt) keys
    ├── log.php                      write to ai_calls
    ├── ratelimit.php                per-IP + daily token cap
    ├── providers/
    │   ├── gemini.php               implements the Gemini REST contract
    │   └── openrouter.php           implements the OpenRouter REST contract (chat-completions API)
    └── prompts/
        ├── suggest_pages.php        prompt template + few-shot examples
        ├── generate_page.php        prompt template (output: structured JSON for a pages row)
        └── chat.php                 system prompt + sanitisation rules
```

API endpoints (under `site/public/api/`):

- `/api/ai/keys.php` — admin POST/DELETE for key management
- `/api/ai/suggest.php` — admin POST for page suggestions
- `/api/ai/generate.php` — admin POST for page generation
- `/api/chat.php` — public POST for frontend chat (rate-limited)

Admin UI pages:

- `/admin/ai-keys.php` — manage API keys (Phase 10)
- `/admin/ai.php` — AI tools (suggest, generate, view spend log) (Phase 11)
- `/admin/settings.php` — general site settings, reserved for later phases

---

## What's NOT decided yet

These are deliberately deferred — pick when the implementing phase starts:

- **Frontend chat persistence on/off** — `ai_chat_messages` table is in the master plan, but storing anonymous visitor messages has privacy implications. Likely an `.env` flag (`AI_CHAT_PERSIST=0|1`); when off, chat is in-session only.
- **Page-template variable substitution syntax** — for programmatic SEO, a "Services in {city}" template generating one page per city. Could be Mustache-like (`{{city}}`), Twig-like (`{{ city }}`), or just `:city` placeholders. No prior art in this codebase to anchor to; pick when Phase 11 is detailed.
- **AI-suggested vs AI-generated default state** — both default to `status='draft'` so admin reviews before publish. But "auto-publish AI suggestions" might be a per-site setting (e.g. for trusted internal use). Defer until we have actual usage data.
- **Where industry-specific prompts live** — generic prompts (suggest pages, chat) belong in `core/lib/ai/prompts/`. Industry-specific tweaks (e.g. "you're an SEO expert for Bangalore freelancers") might belong in `site/` so each site can customise. Pattern TBD when Phase 11 lands.
- **Streaming vs blocking AI responses** — both providers support streaming. For the chatbot this matters (UX); for admin-side generation, blocking is simpler. Likely chatbot streams via SSE and admin tools block.
- **Token estimation before call** — would prevent surprise overruns ("this prompt costs $5"), but provider tokenizers vary. Phase 14 polish item; not gating Phase 10-11.

---

## Implementation reading order (Phase 10 progress)

1. ✅ Re-read [BUILD_BRIEF.md §5](BUILD_BRIEF.md) for the current data model (where `ai_provider_keys` and `ai_calls` slot in)
2. ✅ Re-read this file
3. ✅ Test libsodium availability: `php -r 'echo extension_loaded("sodium") ? "ok" : "missing";'`
4. ⏳ Generate a master key per deployment, add to `.env`, document in `SETUP_GUIDE.md` (per-site, not committed)
5. ✅ Write the migration first (consolidated into `core/migrations/0003_ai_keys.sql` — both tables in one file since they ship together)
6. ✅ Build `core/lib/crypto.php` (libsodium secretbox wrapper, encrypt/decrypt round-trip verified locally)
7. ✅ Build `core/lib/ai/keys.php`
8. ✅ Build first provider adapter (Gemini) — request/response shaping, cost estimate for `gemini-1.5-flash`/`pro`
9. ✅ End-to-end smoke test: real free-tier key stored encrypted → `ai_chat()` → live `gemini-2.5-flash` round-trip in ~700ms → tokens logged to `ai_calls`
10. ✅ Admin UI (`/admin/ai-keys.php` + `/api/ai/keys.php`)

Notes captured during the smoke test:
- **Gemini 2.5 "thinking" tokens are billable.** `usageMetadata.thoughtsTokenCount` is hidden from the response text but counts toward your quota. The adapter now sums it into `tokens_out` so the daily cap and spend reports reflect what Google actually bills. A small `max_tokens` budget can be entirely consumed by thoughts, leaving the visible text empty — callers should treat empty `text` as "model ran out of budget before producing output" and either retry with a higher cap or surface that to the user.
- Older `gemini-1.5-*` aliases were retired by Google in early 2026. Use `gemini-2.5-flash` / `2.5-pro`, or query `models?key=…` against `generativelanguage.googleapis.com/v1beta` to discover currently-available names.
