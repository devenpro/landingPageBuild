<?php
// core/scripts/seed_admin.php — create or update the single admin user.
// Email is read from .env (ADMIN_EMAIL). Password is prompted (no echo on
// POSIX; visible on Windows — flagged below). CLI only.

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../lib/bootstrap.php';

if (GUA_ADMIN_EMAIL === '') {
    fwrite(STDERR, "ADMIN_EMAIL is empty in .env\n");
    exit(1);
}

echo "Seeding admin: " . GUA_ADMIN_EMAIL . "\n";

$is_windows = stripos(PHP_OS_FAMILY, 'WIN') === 0;
if (!$is_windows) {
    @system('stty -echo');
}
echo $is_windows
    ? "Password (visible on Windows): "
    : "Password: ";
$password = rtrim((string)fgets(STDIN), "\r\n");
if (!$is_windows) {
    @system('stty echo');
    echo "\n";
}

if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_BCRYPT);
if ($hash === false) {
    fwrite(STDERR, "password_hash failed.\n");
    exit(1);
}

$pdo = db();
$stmt = $pdo->prepare(
    'INSERT INTO admin_users (email, password_hash) VALUES (:e, :h)
     ON CONFLICT(email) DO UPDATE SET password_hash = excluded.password_hash'
);
$stmt->execute([':e' => GUA_ADMIN_EMAIL, ':h' => $hash]);

echo "Admin saved.\n";
