<?php
// core/lib/crypto.php — libsodium secretbox wrapper for at-rest encryption.
//
// Used by core/lib/ai/keys.php to protect admin-pasted API keys. The master
// key lives in .env (AI_KEYS_MASTER_KEY) as base64-encoded 32 bytes; never
// in the database, never in git, never logged. Callers receive plaintext
// only as a return value and should overwrite the variable as soon as the
// outbound HTTP call completes.
//
// All functions throw RuntimeException rather than returning false so that
// callers can't accidentally treat a decryption failure as success.

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function crypto_master_key(): string
{
    if (!extension_loaded('sodium')) {
        throw new RuntimeException('libsodium extension not loaded; required for AI key storage.');
    }
    if (GUA_AI_KEYS_MASTER_KEY === '') {
        throw new RuntimeException(
            'AI_KEYS_MASTER_KEY missing from .env. Generate with: '
            . "php -r 'echo base64_encode(random_bytes(32)), \"\\n\";'"
        );
    }
    $raw = base64_decode(GUA_AI_KEYS_MASTER_KEY, true);
    if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        throw new RuntimeException(
            'AI_KEYS_MASTER_KEY must be base64-encoded ' . SODIUM_CRYPTO_SECRETBOX_KEYBYTES . ' bytes.'
        );
    }
    return $raw;
}

/**
 * Encrypt $plaintext with a fresh nonce. Returns [ciphertext, nonce] as raw bytes.
 * Both should be stored as BLOB columns alongside each other.
 */
function crypto_encrypt(string $plaintext): array
{
    $key   = crypto_master_key();
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $cipher = sodium_crypto_secretbox($plaintext, $nonce, $key);
    sodium_memzero($key);
    return [$cipher, $nonce];
}

/**
 * Decrypt $ciphertext with $nonce. Throws on tamper / wrong key / wrong nonce.
 */
function crypto_decrypt(string $ciphertext, string $nonce): string
{
    if (strlen($nonce) !== SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
        throw new RuntimeException('Invalid nonce length for secretbox.');
    }
    $key = crypto_master_key();
    $plain = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
    sodium_memzero($key);
    if ($plain === false) {
        throw new RuntimeException(
            'Decryption failed (wrong master key, corrupted ciphertext, or tampered nonce).'
        );
    }
    return $plain;
}
