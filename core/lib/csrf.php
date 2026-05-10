<?php
// core/lib/csrf.php — session-bound CSRF tokens for state-changing requests.
//
// Sessions are started lazily — only when csrf_token() or csrf_check() is
// called. Pages without forms (most of them) never start a session, so
// no Set-Cookie header on plain content reads. Phase 6's admin auth
// uses the same session.

declare(strict_types=1);

function csrf_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    csrf_session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf'];
}

function csrf_check(string $token): bool
{
    csrf_session_start();
    $expected = $_SESSION['csrf'] ?? '';
    return is_string($expected) && $expected !== '' && $token !== ''
        && hash_equals($expected, $token);
}
