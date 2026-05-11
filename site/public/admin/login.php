<?php
// site/public/admin/login.php — admin login form + handler.
//
// GET: render the form (CSRF-tokened)
// POST: validate CSRF, check lockout, attempt login, redirect on success
//
// Both branches render the same form; on POST failure, $error is shown.
// Already-logged-in admins get bounced straight to the dashboard.

declare(strict_types=1);

require __DIR__ . '/../../../core/lib/bootstrap.php';

if (auth_current_user() !== null) {
    header('Location: /admin/dashboard.php', true, 302);
    exit;
}

$error = '';
$email_in = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $email_in = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $token    = (string) ($_POST['csrf'] ?? '');

    if (!csrf_check($token)) {
        $error = 'Session expired. Reload the page and try again.';
    } elseif (auth_is_locked_out(auth_client_ip())) {
        $error = 'Too many failed attempts. Please try again in 10 minutes.';
    } elseif (auth_login($email_in, $password)) {
        header('Location: /admin/dashboard.php', true, 302);
        exit;
    } else {
        $error = 'Invalid email or password.';
    }
}

http_response_code($error !== '' ? 401 : 200);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Admin sign in — <?= e(GUA_SITE_NAME) ?></title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="preload" href="/assets/fonts/InterVariable.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/assets/css/styles.css">
</head>
<body class="font-sans bg-ink-50/60 text-ink-800 antialiased">
    <main class="mx-auto flex min-h-screen max-w-md flex-col items-center justify-center px-4 py-12 sm:px-6">
        <a href="/" class="mb-8 flex items-center gap-2 text-base font-semibold text-ink-900">
            <span class="grid h-7 w-7 place-items-center rounded-md bg-brand-600 text-white">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/></svg>
            </span>
            <span><?= e(GUA_SITE_NAME) ?></span>
        </a>

        <form method="post" action="/admin/login.php" novalidate
              class="w-full rounded-2xl border border-ink-100 bg-white p-7 shadow-sm sm:p-8">
            <h1 class="text-xl font-semibold tracking-tight text-ink-900">Sign in</h1>
            <p class="mt-1 text-sm text-ink-500">Admin access only.</p>

            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

            <div class="mt-6 space-y-4">
                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-ink-800">Email</label>
                    <input id="email" name="email" type="email" required maxlength="150" autofocus autocomplete="email"
                           value="<?= e($email_in) ?>"
                           class="w-full rounded-lg border border-ink-200 bg-white px-3 py-2.5 text-base text-ink-900 placeholder:text-ink-500 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-ink-800">Password</label>
                    <input id="password" name="password" type="password" required minlength="8" autocomplete="current-password"
                           class="w-full rounded-lg border border-ink-200 bg-white px-3 py-2.5 text-base text-ink-900 placeholder:text-ink-500 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
            </div>

            <button type="submit"
                    class="mt-6 inline-flex w-full items-center justify-center gap-1.5 rounded-full bg-brand-600 px-6 py-3 text-base font-medium text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
                Sign in
            </button>

            <?php if ($error !== ''): ?>
                <div role="alert" aria-live="polite"
                     class="mt-5 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>
        </form>

        <a href="/" class="mt-6 text-sm text-ink-500 hover:text-ink-800">&larr; Back to site</a>
    </main>
</body>
</html>
