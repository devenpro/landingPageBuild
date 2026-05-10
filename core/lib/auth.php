<?php
// core/lib/auth.php — admin login/logout/lockout helpers.
//
// Sits on top of core/lib/csrf.php's session. The session is started in
// bootstrap on every HTTP request, so all functions here can assume
// $_SESSION is available.
//
// Brute-force protection: 5 failed attempts per IP in a 10-minute window
// locks that IP out of further attempts for the rest of the window. Both
// successes and failures are recorded in login_attempts for audit.
//
// Inactivity timeout: GUA_SESSION_LIFETIME_HOURS (default 8). Tracked via
// $_SESSION['admin_last_active']. Each call to auth_current_user() slides
// the window forward.

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/config.php';

const GUA_AUTH_LOCKOUT_THRESHOLD = 5;
const GUA_AUTH_LOCKOUT_MINUTES   = 10;

function auth_client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    // Trust nothing about X-Forwarded-For unless explicitly configured;
    // shared cPanel hosts are typically not behind a trusted proxy.
    return is_string($ip) ? $ip : '0.0.0.0';
}

function auth_record_attempt(string $ip, ?string $email, bool $success): void
{
    $stmt = db()->prepare(
        'INSERT INTO login_attempts (ip_address, email, success) VALUES (:ip, :em, :s)'
    );
    $stmt->execute([':ip' => $ip, ':em' => $email, ':s' => $success ? 1 : 0]);
}

function auth_is_locked_out(string $ip): bool
{
    // SQLite's CURRENT_TIMESTAMP is UTC. Compare against a UTC cutoff
    // (gmdate, not DateTimeImmutable which uses the local timezone).
    $cutoff = gmdate('Y-m-d H:i:s', time() - GUA_AUTH_LOCKOUT_MINUTES * 60);
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE ip_address = :ip AND success = 0 AND attempted_at > :since'
    );
    $stmt->execute([':ip' => $ip, ':since' => $cutoff]);
    return (int) $stmt->fetchColumn() >= GUA_AUTH_LOCKOUT_THRESHOLD;
}

function auth_login(string $email, string $password): bool
{
    csrf_session_start();

    $ip = auth_client_ip();
    if (auth_is_locked_out($ip)) {
        return false;
    }

    $stmt = db()->prepare(
        'SELECT id, password_hash FROM admin_users WHERE email = :e LIMIT 1'
    );
    $stmt->execute([':e' => $email]);
    $row = $stmt->fetch();

    $ok = is_array($row) && password_verify($password, (string) $row['password_hash']);
    auth_record_attempt($ip, $email, $ok);

    if (!$ok) {
        return false;
    }

    // Prevent session fixation: rotate the session ID on privilege change.
    session_regenerate_id(true);
    $_SESSION['admin_user_id']     = (int) $row['id'];
    $_SESSION['admin_email']       = $email;
    $_SESSION['admin_last_active'] = time();
    $_SESSION['csrf']              = bin2hex(random_bytes(32));

    return true;
}

function auth_logout(): void
{
    csrf_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path']     ?? '/',
            $params['domain']   ?? '',
            (bool)($params['secure']   ?? false),
            (bool)($params['httponly'] ?? true)
        );
    }
    session_destroy();
}

function auth_current_user(): ?array
{
    csrf_session_start();
    if (empty($_SESSION['admin_user_id'])) {
        return null;
    }

    $last = (int) ($_SESSION['admin_last_active'] ?? 0);
    $timeout = GUA_SESSION_LIFETIME_HOURS * 3600;
    if ($last > 0 && (time() - $last) > $timeout) {
        auth_logout();
        return null;
    }

    $_SESSION['admin_last_active'] = time(); // sliding window

    $stmt = db()->prepare(
        'SELECT id, email FROM admin_users WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $_SESSION['admin_user_id']]);
    $row = $stmt->fetch();
    return $row !== false ? $row : null;
}

function auth_require_login(string $redirect_to = '/admin/login.php'): void
{
    if (auth_current_user() === null) {
        header('Location: ' . $redirect_to, true, 302);
        exit;
    }
}
