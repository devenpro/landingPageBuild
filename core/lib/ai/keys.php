<?php
// core/lib/ai/keys.php — CRUD for ai_provider_keys + decrypt-on-demand.
//
// Plaintext API keys never leave this file's return values. Callers
// (provider adapters in core/lib/ai/providers/) call ai_keys_get() right
// before the outbound HTTP call, use the value once, and let it fall out
// of scope. We update last_used_at on every successful decrypt so admins
// can see which keys are actually in rotation.
//
// Provider whitelist: only providers we ship adapters for can be stored.
// This prevents typos like 'gemni' from creating ghost rows that no
// adapter will ever read.

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../crypto.php';

const GUA_AI_PROVIDERS = ['huggingface', 'gemini', 'openrouter'];

function ai_keys_validate_provider(string $provider): void
{
    if (!in_array($provider, GUA_AI_PROVIDERS, true)) {
        throw new InvalidArgumentException(
            "Unknown provider '$provider'. Supported: " . implode(', ', GUA_AI_PROVIDERS)
        );
    }
}

/**
 * Store a new key. Returns the new row id.
 * Throws on duplicate (provider, label) — caller should delete-then-store
 * to rotate, so the audit log makes the rotation visible.
 */
function ai_keys_store(string $provider, ?string $label, string $plaintext_key): int
{
    ai_keys_validate_provider($provider);
    if ($plaintext_key === '') {
        throw new InvalidArgumentException('API key cannot be empty.');
    }

    [$cipher, $nonce] = crypto_encrypt($plaintext_key);
    sodium_memzero($plaintext_key);

    $stmt = db()->prepare(
        'INSERT INTO ai_provider_keys (provider, label, encrypted_key, nonce)
         VALUES (:p, :l, :c, :n)'
    );
    $stmt->bindValue(':p', $provider);
    $stmt->bindValue(':l', $label);
    $stmt->bindValue(':c', $cipher, PDO::PARAM_LOB);
    $stmt->bindValue(':n', $nonce, PDO::PARAM_LOB);
    $stmt->execute();

    return (int) db()->lastInsertId();
}

/**
 * List stored keys (provider, label, timestamps) — never returns plaintext.
 * Suitable for the admin settings UI.
 */
function ai_keys_list(?string $provider = null): array
{
    if ($provider !== null) {
        ai_keys_validate_provider($provider);
        $stmt = db()->prepare(
            'SELECT id, provider, label, created_at, last_used_at
             FROM ai_provider_keys WHERE provider = :p
             ORDER BY provider, label'
        );
        $stmt->execute([':p' => $provider]);
    } else {
        $stmt = db()->query(
            'SELECT id, provider, label, created_at, last_used_at
             FROM ai_provider_keys ORDER BY provider, label'
        );
    }
    return $stmt->fetchAll();
}

/**
 * Decrypt and return the plaintext key for ($provider, $label). Updates
 * last_used_at as a side effect. Returns null if no row matches.
 */
function ai_keys_get(string $provider, ?string $label = null): ?string
{
    ai_keys_validate_provider($provider);

    if ($label === null) {
        $stmt = db()->prepare(
            'SELECT id, encrypted_key, nonce FROM ai_provider_keys
             WHERE provider = :p ORDER BY last_used_at DESC, id ASC LIMIT 1'
        );
        $stmt->execute([':p' => $provider]);
    } else {
        $stmt = db()->prepare(
            'SELECT id, encrypted_key, nonce FROM ai_provider_keys
             WHERE provider = :p AND label = :l LIMIT 1'
        );
        $stmt->execute([':p' => $provider, ':l' => $label]);
    }

    $row = $stmt->fetch();
    if ($row === false) {
        return null;
    }

    $plain = crypto_decrypt($row['encrypted_key'], $row['nonce']);

    $upd = db()->prepare(
        "UPDATE ai_provider_keys SET last_used_at = strftime('%Y-%m-%d %H:%M:%S','now') WHERE id = :id"
    );
    $upd->execute([':id' => (int) $row['id']]);

    return $plain;
}

function ai_keys_delete(int $id): bool
{
    $stmt = db()->prepare('DELETE FROM ai_provider_keys WHERE id = :id');
    $stmt->execute([':id' => $id]);
    return $stmt->rowCount() > 0;
}
